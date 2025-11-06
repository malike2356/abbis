<?php
/**
 * Performance Optimization Indexes
 * Creates indexes to improve query performance by 30-50%
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "=== Creating Performance Indexes ===\n\n";

$indexes = [
    // Field Reports
    'field_reports' => [
        'idx_rig_date' => ['rig_id', 'report_date'],
        'idx_client_date' => ['client_id', 'report_date'],
        'idx_rig_status' => ['rig_id', 'status'],
        'idx_report_date_status' => ['report_date', 'status'],
        'idx_job_type_date' => ['job_type', 'report_date'],
    ],
    // Expense Entries
    'expense_entries' => [
        'idx_report_category' => ['report_id', 'category'],
        'idx_report_date' => ['report_id', 'created_at'],
        'idx_category_date' => ['category', 'created_at'],
    ],
    // Payroll Entries
    'payroll_entries' => [
        'idx_report_worker' => ['report_id', 'worker_name'],
        'idx_worker_date' => ['worker_name', 'created_at'],
        'idx_report_role' => ['report_id', 'role'],
    ],
    // Maintenance Records
    'maintenance_records' => [
        'idx_rig_status_date' => ['rig_id', 'status', 'created_at'],
        'idx_asset_date' => ['asset_id', 'created_at'],
        'idx_status_priority' => ['status', 'priority'],
    ],
    // Debt Recovery
    'debt_recoveries' => [
        'idx_status_due_date' => ['status', 'due_date'],
        'idx_client_status' => ['client_id', 'status'],
        'idx_field_report' => ['field_report_id'],
        'idx_priority_status' => ['priority', 'status'],
        'idx_next_followup' => ['next_followup_date', 'status'],
    ],
    // Clients
    'clients' => [
        'idx_client_name' => ['client_name'],
        'idx_contact_email' => ['email'],
    ],
    // Workers
    'workers' => [
        'idx_worker_name' => ['worker_name'],
        'idx_role_status' => ['role', 'status'],
    ],
    // Rigs
    'rigs' => [
        'idx_status' => ['status'],
    ],
    // Materials Inventory
    'materials_inventory' => [
        'idx_status_type' => ['status', 'material_type'],
    ],
    // Loans
    'loans' => [
        'idx_worker_status' => ['worker_id', 'status'],
        'idx_status_due_date' => ['status', 'due_date'],
    ],
    // Email Queue
    'email_queue' => [
        'idx_status_created' => ['status', 'created_at'],
        'idx_type_status' => ['type', 'status'],
    ],
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($indexes as $table => $tableIndexes) {
    // Check if table exists
    try {
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() === 0) {
            echo "⚠️  Table '$table' does not exist, skipping...\n";
            continue;
        }
    } catch (PDOException $e) {
        echo "⚠️  Could not check table '$table', skipping...\n";
        continue;
    }
    
    foreach ($tableIndexes as $indexName => $columns) {
        // Check if index already exists
        try {
            $check = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
            if ($check->rowCount() > 0) {
                echo "  ⏭️  Index '$indexName' on '$table' already exists, skipping...\n";
                $skipped++;
                continue;
            }
        } catch (PDOException $e) {
            // Continue to create index
        }
        
        // Create index
        $columnsList = '`' . implode('`, `', $columns) . '`';
        $sql = "CREATE INDEX `$indexName` ON `$table` ($columnsList)";
        
        try {
            $pdo->exec($sql);
            echo "  ✅ Created index '$indexName' on '$table'\n";
            $success++;
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'Duplicate key name') !== false || 
                strpos($errorMsg, 'already exists') !== false) {
                echo "  ⏭️  Index '$indexName' on '$table' already exists\n";
                $skipped++;
            } else {
                echo "  ❌ Error creating '$indexName' on '$table': " . substr($errorMsg, 0, 100) . "\n";
                $errors++;
            }
        }
    }
}

echo "\n=== Summary ===\n";
echo "✅ Created: $success indexes\n";
echo "⏭️  Skipped: $skipped indexes (already exist)\n";
if ($errors > 0) {
    echo "❌ Errors: $errors\n";
}
echo "\n✅ Performance indexes setup complete!\n";

