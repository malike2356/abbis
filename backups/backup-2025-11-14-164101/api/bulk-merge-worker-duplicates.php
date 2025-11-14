<?php
/**
 * Bulk Merge All Duplicate Workers API
 * Automatically merges all detected duplicates into single records
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Analyze duplicates to get all groups
    $duplicates = analyzeAllDuplicates($pdo);
    
    $totalMerged = 0;
    $totalPayrollUpdated = 0;
    $totalLoansUpdated = 0;
    $mergeDetails = [];
    
    // Helper function to select best worker to keep (most complete data)
    function selectBestWorker($workers) {
        $best = null;
        $bestScore = -1;
        
        foreach ($workers as $worker) {
            $score = 0;
            // Prefer workers with contact number
            if (!empty($worker['contact_number'])) $score += 10;
            // Prefer workers with email
            if (!empty($worker['email'])) $score += 10;
            // Prefer workers with non-zero default rate
            if (floatval($worker['default_rate']) > 0) $score += 5;
            // Prefer active workers
            if ($worker['status'] === 'active') $score += 5;
            // Prefer older records (first created)
            $score += 1;
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $worker;
            }
        }
        
        return $best ? $best : $workers[0]; // Fallback to first if no best found
    }
    
    // Merge exact name duplicates
    foreach ($duplicates['by_name'] as $group) {
        if (count($group['workers']) < 2) continue;
        
        $keepWorker = selectBestWorker($group['workers']);
        $mergeWorkers = array_filter($group['workers'], function($w) use ($keepWorker) {
            return $w['id'] !== $keepWorker['id'];
        });
        
        $result = mergeWorkers($pdo, $keepWorker, $mergeWorkers);
        $totalMerged += $result['merged'];
        $totalPayrollUpdated += $result['payroll_updated'];
        $totalLoansUpdated += $result['loans_updated'];
        $mergeDetails[] = "Name group '{$group['normalized_name']}': Merged " . count($mergeWorkers) . " into {$keepWorker['worker_name']}";
    }
    
    // Merge contact duplicates
    foreach ($duplicates['by_contact'] as $group) {
        if (count($group['workers']) < 2) continue;
        
        $keepWorker = selectBestWorker($group['workers']);
        $mergeWorkers = array_filter($group['workers'], function($w) use ($keepWorker) {
            return $w['id'] !== $keepWorker['id'];
        });
        
        $result = mergeWorkers($pdo, $keepWorker, $mergeWorkers);
        $totalMerged += $result['merged'];
        $totalPayrollUpdated += $result['payroll_updated'];
        $totalLoansUpdated += $result['loans_updated'];
        $mergeDetails[] = "Contact group '{$group['contact']}': Merged " . count($mergeWorkers) . " into {$keepWorker['worker_name']}";
    }
    
    // Merge email duplicates
    if (!empty($duplicates['by_email'])) {
        foreach ($duplicates['by_email'] as $group) {
            if (count($group['workers']) < 2) continue;
            
            $keepWorker = selectBestWorker($group['workers']);
            $mergeWorkers = array_filter($group['workers'], function($w) use ($keepWorker) {
                return $w['id'] !== $keepWorker['id'];
            });
            
            $result = mergeWorkers($pdo, $keepWorker, $mergeWorkers);
            $totalMerged += $result['merged'];
            $totalPayrollUpdated += $result['payroll_updated'];
            $totalLoansUpdated += $result['loans_updated'];
            $mergeDetails[] = "Email group '{$group['email']}': Merged " . count($mergeWorkers) . " into {$keepWorker['worker_name']}";
        }
    }
    
    // Merge potential duplicates (similar names)
    foreach ($duplicates['potential_duplicates'] as $pair) {
        $worker1 = $pair['worker1'];
        $worker2 = $pair['worker2'];
        
        $keepWorker = selectBestWorker([$worker1, $worker2]);
        $mergeWorker = $keepWorker['id'] === $worker1['id'] ? $worker2 : $worker1;
        
        // Check if merge worker still exists (might have been merged already)
        $checkStmt = $pdo->prepare("SELECT id FROM workers WHERE id = ?");
        $checkStmt->execute([$mergeWorker['id']]);
        if (!$checkStmt->fetch()) {
            continue; // Already merged
        }
        
        $result = mergeWorkers($pdo, $keepWorker, [$mergeWorker]);
        $totalMerged += $result['merged'];
        $totalPayrollUpdated += $result['payroll_updated'];
        $totalLoansUpdated += $result['loans_updated'];
        $mergeDetails[] = "Similar names ({$pair['similarity']}%): Merged {$mergeWorker['worker_name']} into {$keepWorker['worker_name']}";
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully merged {$totalMerged} duplicate worker(s)",
        'stats' => [
            'workers_merged' => $totalMerged,
            'payroll_entries_updated' => $totalPayrollUpdated,
            'loans_updated' => $totalLoansUpdated
        ],
        'details' => $mergeDetails
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error bulk merging duplicates: ' . $e->getMessage()
    ]);
}

// Helper function to merge workers
function mergeWorkers($pdo, $keepWorker, $mergeWorkers) {
    $merged = 0;
    $payrollUpdated = 0;
    $loansUpdated = 0;
    
    $keepWorkerId = $keepWorker['id'];
    $keepWorkerName = $keepWorker['worker_name'];
    
    foreach ($mergeWorkers as $mergeWorker) {
        $mergeWorkerId = $mergeWorker['id'];
        $mergeWorkerName = $mergeWorker['worker_name'];
        
        // Check if merge worker still exists
        $checkStmt = $pdo->prepare("SELECT id FROM workers WHERE id = ?");
        $checkStmt->execute([$mergeWorkerId]);
        if (!$checkStmt->fetch()) {
            continue; // Already merged
        }
        
        // Update payroll_entries
        $updatePayrollStmt = $pdo->prepare("UPDATE payroll_entries SET worker_name = ? WHERE worker_name = ?");
        $updatePayrollStmt->execute([$keepWorkerName, $mergeWorkerName]);
        $payrollUpdated += $updatePayrollStmt->rowCount();
        
        // Update worker_id in payroll_entries if column exists
        try {
            $updatePayrollIdStmt = $pdo->prepare("UPDATE payroll_entries SET worker_id = ? WHERE worker_id = ?");
            $updatePayrollIdStmt->execute([$keepWorkerId, $mergeWorkerId]);
        } catch (PDOException $e) {
            // Column might not exist
        }
        
        // Update worker_loans
        try {
            $updateLoansStmt = $pdo->prepare("UPDATE worker_loans SET worker_id = ?, worker_name = ? WHERE worker_id = ? OR worker_name = ?");
            $updateLoansStmt->execute([$keepWorkerId, $keepWorkerName, $mergeWorkerId, $mergeWorkerName]);
            $loansUpdated += $updateLoansStmt->rowCount();
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        // Update field_reports supervisor
        try {
            $updateSupervisorStmt = $pdo->prepare("UPDATE field_reports SET supervisor = ? WHERE supervisor = ?");
            $updateSupervisorStmt->execute([$keepWorkerName, $mergeWorkerName]);
        } catch (PDOException $e) {
            // Ignore
        }
        
        // Merge worker data - fill missing fields in keep worker
        $updates = [];
        $updateParams = [];
        
        if (empty($keepWorker['contact_number']) && !empty($mergeWorker['contact_number'])) {
            $updates[] = "contact_number = ?";
            $updateParams[] = $mergeWorker['contact_number'];
        }
        
        try {
            $checkEmailStmt = $pdo->query("SHOW COLUMNS FROM workers LIKE 'email'");
            if ($checkEmailStmt->rowCount() > 0) {
                if (empty($keepWorker['email']) && !empty($mergeWorker['email'])) {
                    $updates[] = "email = ?";
                    $updateParams[] = $mergeWorker['email'];
                }
            }
        } catch (PDOException $e) {
            // Email column doesn't exist
        }
        
        if (floatval($keepWorker['default_rate']) == 0 && floatval($mergeWorker['default_rate']) > 0) {
            $updates[] = "default_rate = ?";
            $updateParams[] = $mergeWorker['default_rate'];
        }
        
        if (!empty($updates)) {
            $updateParams[] = $keepWorkerId;
            $updateKeepStmt = $pdo->prepare("UPDATE workers SET " . implode(', ', $updates) . " WHERE id = ?");
            $updateKeepStmt->execute($updateParams);
        }
        
        // Delete duplicate worker
        $deleteStmt = $pdo->prepare("DELETE FROM workers WHERE id = ?");
        $deleteStmt->execute([$mergeWorkerId]);
        
        $merged++;
    }
    
    return [
        'merged' => $merged,
        'payroll_updated' => $payrollUpdated,
        'loans_updated' => $loansUpdated
    ];
}

// Function to analyze duplicates (extracted from analyze-worker-duplicates.php logic)
function analyzeAllDuplicates($pdo) {
    // Check if email column exists
    $hasEmail = false;
    try {
        $checkStmt = $pdo->query("SHOW COLUMNS FROM workers LIKE 'email'");
        $hasEmail = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        $hasEmail = false;
    }
    
    // Get all workers
    $query = "SELECT id, worker_name, contact_number, role, default_rate, status, created_at";
    if ($hasEmail) {
        $query .= ", email";
    }
    $query .= " FROM workers ORDER BY worker_name";
    
    $stmt = $pdo->query($query);
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $duplicates = [
        'by_name' => [],
        'by_contact' => [],
        'by_email' => [],
        'potential_duplicates' => []
    ];
    
    function normalizeName($name) {
        $normalized = trim(strtolower($name));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }
    
    function normalizePhone($phone) {
        if (empty($phone)) return '';
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($normalized) > 9 && substr($normalized, 0, 3) === '233') {
            $normalized = substr($normalized, 3);
        }
        if (strlen($normalized) > 9 && substr($normalized, 0, 1) === '0') {
            $normalized = substr($normalized, 1);
        }
        return $normalized;
    }
    
    function nameSimilarity($name1, $name2) {
        $norm1 = normalizeName($name1);
        $norm2 = normalizeName($name2);
        if ($norm1 === $norm2) return 100;
        if (strpos($norm1, $norm2) !== false || strpos($norm2, $norm1) !== false) return 85;
        $distance = levenshtein($norm1, $norm2);
        $maxLen = max(strlen($norm1), strlen($norm2));
        if ($maxLen == 0) return 100;
        return (1 - ($distance / $maxLen)) * 100;
    }
    
    // Group by normalized name
    $nameGroups = [];
    foreach ($workers as $worker) {
        $normalizedName = normalizeName($worker['worker_name']);
        if (!isset($nameGroups[$normalizedName])) {
            $nameGroups[$normalizedName] = [];
        }
        $nameGroups[$normalizedName][] = $worker;
    }
    
    foreach ($nameGroups as $normalizedName => $group) {
        if (count($group) > 1) {
            $duplicates['by_name'][] = [
                'normalized_name' => $normalizedName,
                'workers' => $group,
                'count' => count($group)
            ];
        }
    }
    
    // Group by contact
    $contactGroups = [];
    foreach ($workers as $worker) {
        if (!empty($worker['contact_number'])) {
            $normalizedContact = normalizePhone($worker['contact_number']);
            if (!empty($normalizedContact)) {
                if (!isset($contactGroups[$normalizedContact])) {
                    $contactGroups[$normalizedContact] = [];
                }
                $contactGroups[$normalizedContact][] = $worker;
            }
        }
    }
    
    foreach ($contactGroups as $normalizedContact => $group) {
        if (count($group) > 1) {
            $duplicates['by_contact'][] = [
                'contact' => $normalizedContact,
                'workers' => $group,
                'count' => count($group)
            ];
        }
    }
    
    // Group by email
    if ($hasEmail) {
        $emailGroups = [];
        foreach ($workers as $worker) {
            if (!empty($worker['email'])) {
                $email = strtolower(trim($worker['email']));
                if (!isset($emailGroups[$email])) {
                    $emailGroups[$email] = [];
                }
                $emailGroups[$email][] = $worker;
            }
        }
        
        foreach ($emailGroups as $email => $group) {
            if (count($group) > 1) {
                $duplicates['by_email'][] = [
                    'email' => $email,
                    'workers' => $group,
                    'count' => count($group)
                ];
            }
        }
    }
    
    // Find potential duplicates
    for ($i = 0; $i < count($workers); $i++) {
        for ($j = $i + 1; $j < count($workers); $j++) {
            $worker1 = $workers[$i];
            $worker2 = $workers[$j];
            
            $similarity = nameSimilarity($worker1['worker_name'], $worker2['worker_name']);
            
            if ($similarity >= 70 && $similarity < 100) {
                $isExactDuplicate = false;
                foreach ($duplicates['by_name'] as $group) {
                    $groupIds = array_column($group['workers'], 'id');
                    if (in_array($worker1['id'], $groupIds) && in_array($worker2['id'], $groupIds)) {
                        $isExactDuplicate = true;
                        break;
                    }
                }
                
                if (!$isExactDuplicate) {
                    $duplicates['potential_duplicates'][] = [
                        'worker1' => $worker1,
                        'worker2' => $worker2,
                        'similarity' => $similarity
                    ];
                }
            }
        }
    }
    
    return $duplicates;
}
