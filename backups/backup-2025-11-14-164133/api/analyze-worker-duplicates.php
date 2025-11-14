<?php
/**
 * Analyze Duplicate Workers API
 * Identifies potential duplicate workers based on name, contact number, and email
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
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
    
    // Helper function to normalize names for comparison
    function normalizeName($name) {
        // Remove extra spaces, convert to lowercase, remove special characters
        $normalized = trim(strtolower($name));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }
    
    // Helper function to normalize phone numbers
    function normalizePhone($phone) {
        if (empty($phone)) return '';
        // Remove all non-digit characters
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        // Remove leading country codes (Ghana: 233)
        if (strlen($normalized) > 9 && substr($normalized, 0, 3) === '233') {
            $normalized = substr($normalized, 3);
        }
        // Remove leading 0
        if (strlen($normalized) > 9 && substr($normalized, 0, 1) === '0') {
            $normalized = substr($normalized, 1);
        }
        return $normalized;
    }
    
    // Helper function to calculate similarity between two names
    function nameSimilarity($name1, $name2) {
        $norm1 = normalizeName($name1);
        $norm2 = normalizeName($name2);
        
        // Exact match after normalization
        if ($norm1 === $norm2) return 100;
        
        // Check if one contains the other
        if (strpos($norm1, $norm2) !== false || strpos($norm2, $norm1) !== false) {
            return 85;
        }
        
        // Calculate Levenshtein distance
        $distance = levenshtein($norm1, $norm2);
        $maxLen = max(strlen($norm1), strlen($norm2));
        if ($maxLen == 0) return 100;
        
        $similarity = (1 - ($distance / $maxLen)) * 100;
        
        return $similarity;
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
    
    // Find exact duplicate names
    foreach ($nameGroups as $normalizedName => $group) {
        if (count($group) > 1) {
            $duplicates['by_name'][] = [
                'normalized_name' => $normalizedName,
                'workers' => $group,
                'count' => count($group)
            ];
        }
    }
    
    // Group by contact number (if available)
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
    
    // Find duplicate contact numbers
    foreach ($contactGroups as $normalizedContact => $group) {
        if (count($group) > 1) {
            $duplicates['by_contact'][] = [
                'contact' => $normalizedContact,
                'workers' => $group,
                'count' => count($group)
            ];
        }
    }
    
    // Group by email (if available)
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
        
        // Find duplicate emails
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
    
    // Find potential duplicates based on name similarity
    for ($i = 0; $i < count($workers); $i++) {
        for ($j = $i + 1; $j < count($workers); $j++) {
            $worker1 = $workers[$i];
            $worker2 = $workers[$j];
            
            $similarity = nameSimilarity($worker1['worker_name'], $worker2['worker_name']);
            
            // If similarity is high (>= 70%) and not already in exact duplicates
            if ($similarity >= 70 && $similarity < 100) {
                // Check if they're not already grouped in exact duplicates
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
                        'similarity' => round($similarity, 2),
                        'shared_contact' => !empty($worker1['contact_number']) && !empty($worker2['contact_number']) && 
                                          normalizePhone($worker1['contact_number']) === normalizePhone($worker2['contact_number']),
                        'shared_email' => $hasEmail && !empty($worker1['email']) && !empty($worker2['email']) && 
                                       strtolower(trim($worker1['email'])) === strtolower(trim($worker2['email']))
                    ];
                }
            }
        }
    }
    
    // Calculate statistics
    $stats = [
        'total_workers' => count($workers),
        'exact_name_duplicates' => count($duplicates['by_name']),
        'contact_duplicates' => count($duplicates['by_contact']),
        'email_duplicates' => count($duplicates['by_email']),
        'potential_duplicates' => count($duplicates['potential_duplicates']),
        'total_duplicate_entries' => 0
    ];
    
    foreach ($duplicates['by_name'] as $group) {
        $stats['total_duplicate_entries'] += count($group['workers']);
    }
    foreach ($duplicates['by_contact'] as $group) {
        $stats['total_duplicate_entries'] += count($group['workers']);
    }
    foreach ($duplicates['by_email'] as $group) {
        $stats['total_duplicate_entries'] += count($group['workers']);
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'duplicates' => $duplicates
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error analyzing duplicates: ' . $e->getMessage()
    ]);
}
