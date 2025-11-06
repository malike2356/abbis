<?php
/**
 * Cleanup Old Test Records
 * Removes the 5 old test records while preserving RED RIG data
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// CLI mode
if (php_sapi_name() === 'cli') {
    $_SESSION = ['user_id' => 1, 'role' => 'admin'];
}

$pdo = getDBConnection();

echo "=== Cleaning Up Old Test Records ===\n\n";

// Start transaction
$pdo->beginTransaction();

try {
    // Step 1: Identify old test reports (not RED RIG)
    echo "1. Identifying old test reports...\n";
    $oldReports = $pdo->query("
        SELECT id, report_id, report_date, site_name, rig_id, client_id
        FROM field_reports
        WHERE rig_id != 4
        ORDER BY report_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $oldReportIds = array_column($oldReports, 'id');
    $oldReportCount = count($oldReportIds);
    
    echo "   Found {$oldReportCount} old test reports to delete:\n";
    foreach ($oldReports as $r) {
        echo "     - ID: {$r['id']}, Report: {$r['report_id']}, Date: {$r['report_date']}, Site: {$r['site_name']}\n";
    }
    echo "\n";
    
    if ($oldReportCount === 0) {
        echo "✅ No old test records found. Nothing to delete.\n";
        $pdo->rollBack();
        exit(0);
    }
    
    // Step 2: Get client IDs and rig IDs from old reports
    $oldClientIds = array_filter(array_column($oldReports, 'client_id'));
    $oldRigIds = array_unique(array_column($oldReports, 'rig_id'));
    
    echo "2. Deleting related data...\n";
    
    // Delete expense entries
    if (!empty($oldReportIds)) {
        $placeholders = implode(',', array_fill(0, count($oldReportIds), '?'));
        $expenseStmt = $pdo->prepare("DELETE FROM expense_entries WHERE report_id IN ({$placeholders})");
        $expenseStmt->execute($oldReportIds);
        $expenseCount = $expenseStmt->rowCount();
        echo "   Deleted {$expenseCount} expense entries\n";
        
        // Delete payroll entries
        $payrollStmt = $pdo->prepare("DELETE FROM payroll_entries WHERE report_id IN ({$placeholders})");
        $payrollStmt->execute($oldReportIds);
        $payrollCount = $payrollStmt->rowCount();
        echo "   Deleted {$payrollCount} payroll entries\n";
        
        // Delete debt recovery records linked to old reports
        $debtStmt = $pdo->prepare("DELETE FROM debt_recoveries WHERE field_report_id IN ({$placeholders})");
        $debtStmt->execute($oldReportIds);
        $debtCount = $debtStmt->rowCount();
        echo "   Deleted {$debtCount} debt recovery records\n";
        
        // Delete rig fee debts linked to old reports
        $rigFeeDebtStmt = $pdo->prepare("DELETE FROM rig_fee_debts WHERE report_id IN ({$placeholders})");
        $rigFeeDebtStmt->execute($oldReportIds);
        $rigFeeDebtCount = $rigFeeDebtStmt->rowCount();
        echo "   Deleted {$rigFeeDebtCount} rig fee debt records\n";
    }
    
    // Step 3: Delete the old field reports
    echo "\n3. Deleting old field reports...\n";
    $reportStmt = $pdo->prepare("DELETE FROM field_reports WHERE id IN ({$placeholders})");
    $reportStmt->execute($oldReportIds);
    $reportCount = $reportStmt->rowCount();
    echo "   Deleted {$reportCount} field reports\n";
    
    // Step 4: Delete orphaned clients (not linked to any remaining reports)
    echo "\n4. Cleaning up orphaned clients...\n";
    if (!empty($oldClientIds)) {
        $clientPlaceholders = implode(',', array_fill(0, count($oldClientIds), '?'));
        $orphanedClients = $pdo->prepare("
            SELECT c.id FROM clients c
            WHERE c.id IN ({$clientPlaceholders})
            AND NOT EXISTS (
                SELECT 1 FROM field_reports fr WHERE fr.client_id = c.id
            )
        ");
        $orphanedClients->execute($oldClientIds);
        $orphanedClientIds = array_column($orphanedClients->fetchAll(PDO::FETCH_ASSOC), 'id');
        
        if (!empty($orphanedClientIds)) {
            $clientDeletePlaceholders = implode(',', array_fill(0, count($orphanedClientIds), '?'));
            $clientDeleteStmt = $pdo->prepare("DELETE FROM clients WHERE id IN ({$clientDeletePlaceholders})");
            $clientDeleteStmt->execute($orphanedClientIds);
            $clientDeleteCount = $clientDeleteStmt->rowCount();
            echo "   Deleted {$clientDeleteCount} orphaned clients\n";
        } else {
            echo "   No orphaned clients to delete\n";
        }
    } else {
        echo "   No clients from old reports\n";
    }
    
    // Step 5: Delete orphaned workers (not in any payroll entries)
    echo "\n5. Cleaning up orphaned workers...\n";
    $orphanedWorkers = $pdo->query("
        SELECT w.id, w.worker_name FROM workers w
        WHERE NOT EXISTS (
            SELECT 1 FROM payroll_entries pe WHERE pe.worker_name = w.worker_name
        )
        AND w.id NOT IN (
            SELECT DISTINCT w2.id FROM workers w2
            INNER JOIN payroll_entries pe2 ON pe2.worker_name = w2.worker_name
            INNER JOIN field_reports fr ON pe2.report_id = fr.id
            WHERE fr.rig_id = 4
        )
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($orphanedWorkers)) {
        $workerIds = array_column($orphanedWorkers, 'id');
        $workerPlaceholders = implode(',', array_fill(0, count($workerIds), '?'));
        $workerDeleteStmt = $pdo->prepare("DELETE FROM workers WHERE id IN ({$workerPlaceholders})");
        $workerDeleteStmt->execute($workerIds);
        $workerDeleteCount = $workerDeleteStmt->rowCount();
        echo "   Deleted {$workerDeleteCount} orphaned workers\n";
        foreach ($orphanedWorkers as $w) {
            echo "     - {$w['worker_name']}\n";
        }
    } else {
        echo "   No orphaned workers to delete\n";
    }
    
    // Step 6: Delete old rigs (not RED RIG and not linked to any reports)
    echo "\n6. Cleaning up old rigs...\n";
    if (!empty($oldRigIds)) {
        $rigPlaceholders = implode(',', array_fill(0, count($oldRigIds), '?'));
        $orphanedRigs = $pdo->prepare("
            SELECT r.id, r.rig_name FROM rigs r
            WHERE r.id IN ({$rigPlaceholders})
            AND NOT EXISTS (
                SELECT 1 FROM field_reports fr WHERE fr.rig_id = r.id
            )
        ");
        $orphanedRigs->execute($oldRigIds);
        $orphanedRigData = $orphanedRigs->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($orphanedRigData)) {
            $rigIds = array_column($orphanedRigData, 'id');
            $rigDeletePlaceholders = implode(',', array_fill(0, count($rigIds), '?'));
            $rigDeleteStmt = $pdo->prepare("DELETE FROM rigs WHERE id IN ({$rigDeletePlaceholders})");
            $rigDeleteStmt->execute($rigIds);
            $rigDeleteCount = $rigDeleteStmt->rowCount();
            echo "   Deleted {$rigDeleteCount} orphaned rigs\n";
            foreach ($orphanedRigData as $r) {
                echo "     - {$r['rig_name']}\n";
            }
        } else {
            echo "   No orphaned rigs to delete\n";
        }
    } else {
        echo "   No old rigs to check\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Cleanup completed successfully!\n\n";
    
    // Final summary
    echo "=== Final Summary ===\n";
    $redRigReports = $pdo->query("SELECT COUNT(*) FROM field_reports WHERE rig_id = 4")->fetchColumn();
    $totalReports = $pdo->query("SELECT COUNT(*) FROM field_reports")->fetchColumn();
    $totalClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $totalWorkers = $pdo->query("SELECT COUNT(*) FROM workers")->fetchColumn();
    $totalRigs = $pdo->query("SELECT COUNT(*) FROM rigs")->fetchColumn();
    
    echo "Field Reports: {$totalReports} (RED RIG: {$redRigReports})\n";
    echo "Clients: {$totalClients}\n";
    echo "Workers: {$totalWorkers}\n";
    echo "Rigs: {$totalRigs}\n";
    echo "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

