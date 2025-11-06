<?php
/**
 * Worker Name Standardization Script
 * Standardizes worker names to canonical list and updates all references
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// Canonical worker names and their roles
$canonicalWorkers = [
    'Atta' => 'Driller',
    'Isaac' => 'Rig Driver / Spanner',
    'Tawiah' => 'Rodboy',
    'Godwin' => 'Rodboy',
    'Asare' => 'Rodboy',
    'Castro' => 'Rodboy',
    'Earnest' => 'Driller',
    'Owusua' => 'Rig Driver',
    'Rasta' => 'Spanner boy / Table boy',
    'Chief' => 'Rodboy',
    'Kwesi' => 'Rodboy'
];

// Name mapping: old_name => canonical_name
$nameMapping = [
    // Atta variations
    'Atta' => 'Atta',
    'Atta Isaac' => 'Isaac', // Split compound name
    
    // Isaac variations
    'Isaac' => 'Isaac',
    'Isaal' => 'Isaac',
    'Attu Isaal' => 'Isaac',
    
    // Tawiah variations
    'Tawiah' => 'Tawiah',
    'Tawich' => 'Tawiah',
    
    // Godwin variations
    'Godwin' => 'Godwin',
    'Godwin Asare' => 'Godwin', // Keep first part, Asare handled separately
    
    // Asare variations
    'Asare' => 'Asare',
    '& Asare' => 'Asare',
    'Asure' => 'Asare',
    
    // Castro variations
    'Castro' => 'Castro',
    
    // Earnest variations
    'Ernest' => 'Earnest',
    'frnest' => 'Earnest',
    
    // Owusua variations
    'MI. Owusu' => 'Owusua',
    'Mr. Owusu' => 'Owusua',
    'thitf Mr. Owusu' => 'Owusua',
    
    // Rasta variations
    'Rasta' => 'Rasta',
    'Rasto' => 'Rasta',
    'Rastu' => 'Rasta',
    'Rusta' => 'Rasta',
    
    // Chief variations
    'chief' => 'Chief',
    
    // Kwesi variations
    'Kwesi' => 'Kwesi',
    'kwasi' => 'Kwesi',
    'Kweci' => 'Kwesi',
    'Kween' => 'Kwesi',
    'Kweka' => 'Kwesi',
    'Kweku' => 'Kwesi',
    'Kweky' => 'Kwesi',
    'kwelry' => 'Kwesi',
    'kwenu' => 'Kwesi',
    'Kwerd' => 'Kwesi',
    'kwerku' => 'Kwesi',
    'Iewesi' => 'Kwesi',
];

$pdo = getDBConnection();
$pdo->beginTransaction();

try {
    echo "Starting worker name standardization...\n\n";
    
    // Step 1: Get all current workers
    $stmt = $pdo->query("SELECT id, worker_name, role, status FROM workers ORDER BY worker_name");
    $allWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($allWorkers) . " workers in database\n\n";
    
    // Step 2: Create canonical worker records (if they don't exist)
    echo "Creating/updating canonical workers...\n";
    $canonicalWorkerIds = [];
    
    foreach ($canonicalWorkers as $canonicalName => $canonicalRole) {
        // Check if canonical worker exists
        $checkStmt = $pdo->prepare("SELECT id FROM workers WHERE worker_name = ?");
        $checkStmt->execute([$canonicalName]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $canonicalWorkerIds[$canonicalName] = $existing['id'];
            // Update role if needed
            $updateStmt = $pdo->prepare("UPDATE workers SET role = ?, status = 'active' WHERE id = ?");
            $updateStmt->execute([$canonicalRole, $existing['id']]);
            echo "  Updated: {$canonicalName} (ID: {$existing['id']})\n";
        } else {
            // Create canonical worker
            $insertStmt = $pdo->prepare("
                INSERT INTO workers (worker_name, role, status, created_at) 
                VALUES (?, ?, 'active', NOW())
            ");
            $insertStmt->execute([$canonicalName, $canonicalRole]);
            $canonicalWorkerIds[$canonicalName] = $pdo->lastInsertId();
            echo "  Created: {$canonicalName} (ID: {$canonicalWorkerIds[$canonicalName]})\n";
        }
    }
    
    echo "\n";
    
    // Step 3: Map and merge workers
    echo "Mapping and merging workers...\n";
    $mergedCount = 0;
    $updatedCount = 0;
    
    foreach ($allWorkers as $worker) {
        $oldName = $worker['worker_name'];
        $workerId = $worker['id'];
        
        // Find canonical name
        $canonicalName = null;
        if (isset($nameMapping[$oldName])) {
            $canonicalName = $nameMapping[$oldName];
        } elseif (isset($canonicalWorkers[$oldName])) {
            // Already canonical
            continue;
        } else {
            // Try fuzzy matching
            $canonicalName = findBestMatch($oldName, array_keys($canonicalWorkers));
        }
        
        if (!$canonicalName || $canonicalName === $oldName) {
            // No match found or already correct - skip or mark for deletion
            if (!isset($canonicalWorkers[$oldName])) {
                echo "  ⚠️  No match for: {$oldName} (ID: {$workerId}) - will be deactivated\n";
                $deactivateStmt = $pdo->prepare("UPDATE workers SET status = 'inactive' WHERE id = ?");
                $deactivateStmt->execute([$workerId]);
            }
            continue;
        }
        
        $canonicalId = $canonicalWorkerIds[$canonicalName];
        
        // Skip if this is already the canonical worker
        if ($workerId == $canonicalId) {
            continue;
        }
        
        echo "  Mapping: '{$oldName}' -> '{$canonicalName}'\n";
        
        // Update all references to this worker
        // 1. Update payroll_entries (both worker_id and worker_name)
        $updatePayrollStmt = $pdo->prepare("
            UPDATE payroll_entries 
            SET worker_name = ?, worker_id = ? 
            WHERE worker_name = ? OR worker_id = ?
        ");
        $updatePayrollStmt->execute([$canonicalName, $canonicalId, $oldName, $workerId]);
        $payrollUpdated = $updatePayrollStmt->rowCount();
        
        // 2. Update loans
        $updateLoansStmt = $pdo->prepare("
            UPDATE loans 
            SET worker_name = ? 
            WHERE worker_name = ?
        ");
        $updateLoansStmt->execute([$canonicalName, $oldName]);
        $loansUpdated = $updateLoansStmt->rowCount();
        
        // 3. Update field_reports supervisor (text field, partial match)
        $updateSupervisorStmt = $pdo->prepare("
            UPDATE field_reports 
            SET supervisor = REPLACE(supervisor, ?, ?) 
            WHERE supervisor LIKE ?
        ");
        $updateSupervisorStmt->execute([$oldName, $canonicalName, "%{$oldName}%"]);
        $supervisorUpdated = $updateSupervisorStmt->rowCount();
        
        // 4. Update worker_role_assignments (if table exists)
        try {
            $updateRolesStmt = $pdo->prepare("
                UPDATE worker_role_assignments wra
                INNER JOIN workers w ON wra.worker_id = w.id
                SET wra.worker_id = ?
                WHERE w.id = ? AND wra.worker_id != ?
            ");
            $updateRolesStmt->execute([$canonicalId, $workerId, $canonicalId]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        // 5. Update worker_rig_preferences (if table exists)
        try {
            $updateRigsStmt = $pdo->prepare("
                UPDATE worker_rig_preferences wrp
                INNER JOIN workers w ON wrp.worker_id = w.id
                SET wrp.worker_id = ?
                WHERE w.id = ? AND wrp.worker_id != ?
            ");
            $updateRigsStmt->execute([$canonicalId, $workerId, $canonicalId]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        // 6. Update attendance_records (if table exists)
        try {
            $updateAttendanceStmt = $pdo->prepare("
                UPDATE attendance_records ar
                INNER JOIN workers w ON ar.worker_id = w.id
                SET ar.worker_id = ?
                WHERE w.id = ? AND ar.worker_id != ?
            ");
            $updateAttendanceStmt->execute([$canonicalId, $workerId, $canonicalId]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        // 7. Update leave_requests (if table exists)
        try {
            $updateLeaveStmt = $pdo->prepare("
                UPDATE leave_requests lr
                INNER JOIN workers w ON lr.worker_id = w.id
                SET lr.worker_id = ?
                WHERE w.id = ? AND lr.worker_id != ?
            ");
            $updateLeaveStmt->execute([$canonicalId, $workerId, $canonicalId]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        // 8. Update performance_reviews (if table exists)
        try {
            $updatePerfStmt = $pdo->prepare("
                UPDATE performance_reviews pr
                INNER JOIN workers w ON pr.worker_id = w.id
                SET pr.worker_id = ?
                WHERE w.id = ? AND pr.worker_id != ?
            ");
            $updatePerfStmt->execute([$canonicalId, $workerId, $canonicalId]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        // 9. Update training_records (if table exists)
        try {
            $updateTrainingStmt = $pdo->prepare("
                UPDATE training_records tr
                INNER JOIN workers w ON tr.worker_id = w.id
                SET tr.worker_id = ?
                WHERE w.id = ? AND tr.worker_id != ?
            ");
            $updateTrainingStmt->execute([$canonicalId, $workerId, $canonicalId]);
        } catch (PDOException $e) {
            // Table might not exist
        }
        
        echo "    - Payroll: {$payrollUpdated} entries\n";
        echo "    - Loans: {$loansUpdated} entries\n";
        echo "    - Supervisors: {$supervisorUpdated} entries\n";
        
        // Delete or deactivate the old worker record
        // Check if worker has any remaining references
        $checkRefsStmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM payroll_entries WHERE worker_name = ? OR worker_id = ?) as payroll_count,
                (SELECT COUNT(*) FROM loans WHERE worker_name = ?) as loans_count,
                (SELECT COUNT(*) FROM field_reports WHERE supervisor LIKE ?) as supervisor_count
        ");
        $checkRefsStmt->execute([$oldName, $workerId, $oldName, "%{$oldName}%"]);
        $refs = $checkRefsStmt->fetch();
        
        if ($refs['payroll_count'] == 0 && $refs['loans_count'] == 0 && $refs['supervisor_count'] == 0) {
            // Safe to delete - but first check HR tables
            $hasHRRefs = false;
            try {
                $hrCheckStmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM attendance_records WHERE worker_id = ?) as attendance_count,
                        (SELECT COUNT(*) FROM leave_requests WHERE worker_id = ?) as leave_count,
                        (SELECT COUNT(*) FROM performance_reviews WHERE worker_id = ?) as perf_count
                ");
                $hrCheckStmt->execute([$workerId, $workerId, $workerId]);
                $hrRefs = $hrCheckStmt->fetch();
                if ($hrRefs['attendance_count'] > 0 || $hrRefs['leave_count'] > 0 || $hrRefs['perf_count'] > 0) {
                    $hasHRRefs = true;
                }
            } catch (PDOException $e) {
                // HR tables might not exist
            }
            
            if (!$hasHRRefs) {
                $deleteStmt = $pdo->prepare("DELETE FROM workers WHERE id = ?");
                $deleteStmt->execute([$workerId]);
                $mergedCount++;
                echo "    ✓ Deleted old worker record\n";
            } else {
                $deactivateStmt = $pdo->prepare("UPDATE workers SET status = 'inactive' WHERE id = ?");
                $deactivateStmt->execute([$workerId]);
                echo "    ⚠️  Deactivated old worker record (has HR references)\n";
            }
        } else {
            // Deactivate instead
            $deactivateStmt = $pdo->prepare("UPDATE workers SET status = 'inactive' WHERE id = ?");
            $deactivateStmt->execute([$workerId]);
            echo "    ⚠️  Deactivated old worker record (has remaining references)\n";
        }
        
        $updatedCount++;
    }
    
    // Step 4: Deactivate any remaining non-canonical workers
    echo "\nDeactivating remaining non-canonical workers...\n";
    $canonicalNamesList = array_keys($canonicalWorkers);
    $placeholders = str_repeat('?,', count($canonicalNamesList) - 1) . '?';
    $deactivateStmt = $pdo->prepare("
        UPDATE workers 
        SET status = 'inactive' 
        WHERE worker_name NOT IN ({$placeholders}) AND status = 'active'
    ");
    $deactivateStmt->execute($canonicalNamesList);
    $deactivated = $deactivateStmt->rowCount();
    echo "  Deactivated {$deactivated} non-canonical workers\n";
    
    // Step 5: Verify final state
    echo "\nVerifying final state...\n";
    $finalStmt = $pdo->query("SELECT worker_name, role, status FROM workers WHERE status = 'active' ORDER BY worker_name");
    $finalWorkers = $finalStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Active workers:\n";
    foreach ($finalWorkers as $worker) {
        echo "  - {$worker['worker_name']} ({$worker['role']})\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ Worker name standardization completed successfully!\n";
    echo "   - Updated: {$updatedCount} workers\n";
    echo "   - Merged/Deleted: {$mergedCount} duplicate workers\n";
    echo "   - Active canonical workers: " . count($finalWorkers) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

/**
 * Find best matching canonical name using fuzzy matching
 */
function findBestMatch($name, $canonicalNames) {
    $name = strtolower(trim($name));
    $bestMatch = null;
    $bestScore = 0;
    
    foreach ($canonicalNames as $canonical) {
        $canonicalLower = strtolower($canonical);
        
        // Exact match
        if ($name === $canonicalLower) {
            return $canonical;
        }
        
        // Contains match
        if (strpos($name, $canonicalLower) !== false || strpos($canonicalLower, $name) !== false) {
            $score = min(strlen($name), strlen($canonicalLower)) / max(strlen($name), strlen($canonicalLower));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $canonical;
            }
        }
        
        // Levenshtein distance
        $distance = levenshtein($name, $canonicalLower);
        $maxLen = max(strlen($name), strlen($canonicalLower));
        if ($maxLen > 0) {
            $similarity = 1 - ($distance / $maxLen);
            if ($similarity > 0.7 && $similarity > $bestScore) {
                $bestScore = $similarity;
                $bestMatch = $canonical;
            }
        }
    }
    
    return $bestMatch;
}

