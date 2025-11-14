<?php
/**
 * Merge Duplicate Workers API
 * Consolidates duplicate workers into a single record
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
    
    $keepWorkerId = intval($_POST['keep_worker_id'] ?? 0);
    $mergeWorkerIds = $_POST['merge_worker_ids'] ?? [];
    
    if (empty($keepWorkerId)) {
        throw new Exception('Keep worker ID is required');
    }
    
    if (empty($mergeWorkerIds) || !is_array($mergeWorkerIds)) {
        throw new Exception('Merge worker IDs are required');
    }
    
    // Get the worker to keep
    $keepStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
    $keepStmt->execute([$keepWorkerId]);
    $keepWorker = $keepStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keepWorker) {
        throw new Exception('Keep worker not found');
    }
    
    $keepWorkerName = $keepWorker['worker_name'];
    $mergedCount = 0;
    $updatedPayrollEntries = 0;
    $updatedLoans = 0;
    
    // Process each worker to merge
    foreach ($mergeWorkerIds as $mergeWorkerId) {
        $mergeWorkerId = intval($mergeWorkerId);
        
        if ($mergeWorkerId === $keepWorkerId) {
            continue; // Skip the keep worker itself
        }
        
        // Get the worker to merge
        $mergeStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
        $mergeStmt->execute([$mergeWorkerId]);
        $mergeWorker = $mergeStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mergeWorker) {
            continue; // Skip if not found
        }
        
        $mergeWorkerName = $mergeWorker['worker_name'];
        
        // Update payroll_entries to use the keep worker's name
        $updatePayrollStmt = $pdo->prepare("
            UPDATE payroll_entries 
            SET worker_name = ? 
            WHERE worker_name = ?
        ");
        $updatePayrollStmt->execute([$keepWorkerName, $mergeWorkerName]);
        $updatedPayrollEntries += $updatePayrollStmt->rowCount();
        
        // Update worker_id in payroll_entries if column exists
        try {
            $updatePayrollIdStmt = $pdo->prepare("
                UPDATE payroll_entries 
                SET worker_id = ? 
                WHERE worker_id = ?
            ");
            $updatePayrollIdStmt->execute([$keepWorkerId, $mergeWorkerId]);
        } catch (PDOException $e) {
            // Column might not exist, ignore
        }
        
        // Update worker_loans table if it exists
        try {
            $updateLoansStmt = $pdo->prepare("
                UPDATE worker_loans 
                SET worker_id = ?, worker_name = ? 
                WHERE worker_id = ? OR worker_name = ?
            ");
            $updateLoansStmt->execute([$keepWorkerId, $keepWorkerName, $mergeWorkerId, $mergeWorkerName]);
            $updatedLoans += $updateLoansStmt->rowCount();
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
        
        // Update any other tables that reference workers by name
        // Update field_reports supervisor if it matches
        try {
            $updateSupervisorStmt = $pdo->prepare("
                UPDATE field_reports 
                SET supervisor = ? 
                WHERE supervisor = ?
            ");
            $updateSupervisorStmt->execute([$keepWorkerName, $mergeWorkerName]);
        } catch (PDOException $e) {
            // Ignore errors
        }
        
        // Merge worker data - keep the most complete record
        // If keep worker has missing data, use merge worker's data
        $updates = [];
        $updateParams = [];
        
        if (empty($keepWorker['contact_number']) && !empty($mergeWorker['contact_number'])) {
            $updates[] = "contact_number = ?";
            $updateParams[] = $mergeWorker['contact_number'];
        }
        
        // Check if email column exists
        try {
            $checkEmailStmt = $pdo->query("SHOW COLUMNS FROM workers LIKE 'email'");
            if ($checkEmailStmt->rowCount() > 0) {
                if (empty($keepWorker['email']) && !empty($mergeWorker['email'])) {
                    $updates[] = "email = ?";
                    $updateParams[] = $mergeWorker['email'];
                }
            }
        } catch (PDOException $e) {
            // Email column doesn't exist, ignore
        }
        
        // Update default_rate if keep worker's rate is 0 and merge worker has a rate
        if (floatval($keepWorker['default_rate']) == 0 && floatval($mergeWorker['default_rate']) > 0) {
            $updates[] = "default_rate = ?";
            $updateParams[] = $mergeWorker['default_rate'];
        }
        
        // Update keep worker with merged data if any
        if (!empty($updates)) {
            $updateParams[] = $keepWorkerId;
            $updateKeepStmt = $pdo->prepare("
                UPDATE workers 
                SET " . implode(', ', $updates) . " 
                WHERE id = ?
            ");
            $updateKeepStmt->execute($updateParams);
        }
        
        // Delete the duplicate worker
        $deleteStmt = $pdo->prepare("DELETE FROM workers WHERE id = ?");
        $deleteStmt->execute([$mergeWorkerId]);
        
        $mergedCount++;
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully merged {$mergedCount} duplicate worker(s) into {$keepWorkerName}",
        'stats' => [
            'workers_merged' => $mergedCount,
            'payroll_entries_updated' => $updatedPayrollEntries,
            'loans_updated' => $updatedLoans,
            'keep_worker_id' => $keepWorkerId,
            'keep_worker_name' => $keepWorkerName
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error merging duplicates: ' . $e->getMessage()
    ]);
}
