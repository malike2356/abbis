<?php
/**
 * Run Worker System-Wide Integration
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting worker system-wide integration...\n\n";

try {
    // Read SQL file
    $sqlFile = __DIR__ . '/integrate_workers_system_wide.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove USE statement
    $sql = preg_replace('/^USE.*;/mi', '', $sql);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 80) . "...\n";
        } catch (PDOException $e) {
            // Ignore errors for statements that might not apply
            if (strpos($e->getMessage(), 'Duplicate') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⊘ Skipped (already exists): " . substr($statement, 0, 80) . "...\n";
            } else {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Verify results
    echo "\n=== Verification ===\n";
    
    // Check payroll_entries with worker_id
    $checkStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(worker_id) as with_worker_id,
            COUNT(*) - COUNT(worker_id) as without_worker_id
        FROM payroll_entries
    ");
    $payrollStats = $checkStmt->fetch(PDO::FETCH_ASSOC);
    echo "Payroll Entries: Total={$payrollStats['total']}, With worker_id={$payrollStats['with_worker_id']}, Without={$payrollStats['without_worker_id']}\n";
    
    // Check field_report_workers
    $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM field_report_workers");
    $frwCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Field Report Workers: {$frwCount} records\n";
    
    // Check views
    $views = ['worker_job_activity', 'worker_statistics', 'worker_weekly_jobs'];
    foreach ($views as $view) {
        try {
            $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM {$view} LIMIT 1");
            $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "View {$view}: Created successfully\n";
        } catch (PDOException $e) {
            echo "⚠ View {$view}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Worker system-wide integration completed!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

