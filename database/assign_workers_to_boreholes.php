<?php
/**
 * Assign All Workers to Boreholes Department
 * Updates all workers to be in the "Boreholes" department
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting department assignment...\n\n";

try {
    // Check if departments table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'departments'");
    if ($checkTable->rowCount() == 0) {
        echo "Creating departments table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `departments` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `department_code` VARCHAR(20) NOT NULL UNIQUE,
              `department_name` VARCHAR(100) NOT NULL,
              `description` TEXT DEFAULT NULL,
              `manager_id` INT(11) DEFAULT NULL,
              `is_active` TINYINT(1) DEFAULT 1,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `department_code` (`department_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ Table created\n\n";
    }
    
    // Check if workers table has department_id column
    $checkColumn = $pdo->query("SHOW COLUMNS FROM workers LIKE 'department_id'");
    if ($checkColumn->rowCount() == 0) {
        echo "Adding department_id column to workers table...\n";
        $pdo->exec("ALTER TABLE `workers` ADD COLUMN `department_id` INT(11) DEFAULT NULL");
        echo "✓ Column added\n\n";
    }
    
    $pdo->beginTransaction();
    
    // Get all departments
    $deptsStmt = $pdo->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $deptsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current departments:\n";
    foreach ($departments as $dept) {
        echo "  - ID: {$dept['id']}, Name: {$dept['department_name']}, Code: {$dept['department_code']}\n";
    }
    echo "\n";
    
    // Find or create "Boreholes" department
    $boreholesDeptId = null;
    foreach ($departments as $dept) {
        if (stripos($dept['department_name'], 'Borehole') !== false) {
            $boreholesDeptId = $dept['id'];
            echo "Found Boreholes department: {$dept['department_name']} (ID: {$boreholesDeptId})\n";
            break;
        }
    }
    
    if (!$boreholesDeptId) {
        // Create Boreholes department
        echo "Creating Boreholes department...\n";
        $createStmt = $pdo->prepare("
            INSERT INTO departments (department_code, department_name, description, is_active) 
            VALUES (?, ?, ?, 1)
        ");
        $createStmt->execute(['BOREHOLES', 'Boreholes', 'Boreholes drilling operations department']);
        $boreholesDeptId = $pdo->lastInsertId();
        echo "✓ Created Boreholes department (ID: {$boreholesDeptId})\n";
    }
    
    echo "\n";
    
    // Get all workers
    $workersStmt = $pdo->query("SELECT id, worker_name, role, department_id FROM workers ORDER BY worker_name");
    $workers = $workersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($workers) . " workers\n\n";
    
    // Update all workers to Boreholes department
    $updateStmt = $pdo->prepare("UPDATE workers SET department_id = ? WHERE id = ?");
    
    $updatedCount = 0;
    $alreadyAssigned = 0;
    
    echo "Assigning workers to Boreholes department:\n";
    foreach ($workers as $worker) {
        if ($worker['department_id'] == $boreholesDeptId) {
            $alreadyAssigned++;
            echo "  ⊙ {$worker['worker_name']} ({$worker['role']}) - Already in Boreholes\n";
        } else {
            $updateStmt->execute([$boreholesDeptId, $worker['id']]);
            $updatedCount++;
            $oldDept = $worker['department_id'] ? " (was in department ID: {$worker['department_id']})" : " (was unassigned)";
            echo "  ✓ {$worker['worker_name']} ({$worker['role']}) - Assigned to Boreholes{$oldDept}\n";
        }
    }
    
    echo "\n";
    
    // Verify assignments
    echo "=== Verification ===\n";
    $verifyStmt = $pdo->query("
        SELECT 
            w.worker_name,
            w.role,
            d.department_name,
            d.department_code,
            w.department_id
        FROM workers w
        LEFT JOIN departments d ON w.department_id = d.id
        ORDER BY w.worker_name
    ");
    
    $workers = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $inBoreholes = 0;
    $inOtherDept = 0;
    $unassigned = 0;
    
    foreach ($workers as $worker) {
        if ($worker['department_id'] == $boreholesDeptId) {
            $inBoreholes++;
            echo "✓ {$worker['worker_name']}: {$worker['role']} → {$worker['department_name']} ({$worker['department_code']})\n";
        } elseif ($worker['department_id']) {
            $inOtherDept++;
            echo "⚠ {$worker['worker_name']}: {$worker['role']} → {$worker['department_name']} ({$worker['department_code']}) - NOT in Boreholes!\n";
        } else {
            $unassigned++;
            echo "⚠ {$worker['worker_name']}: {$worker['role']} → No Department\n";
        }
    }
    
    echo "\nSummary:\n";
    echo "  - Workers in Boreholes: {$inBoreholes}\n";
    echo "  - Workers in other departments: {$inOtherDept}\n";
    echo "  - Workers unassigned: {$unassigned}\n";
    echo "  - Workers updated: {$updatedCount}\n";
    echo "  - Workers already assigned: {$alreadyAssigned}\n";
    
    $pdo->commit();
    
    if ($inOtherDept == 0 && $unassigned == 0) {
        echo "\n✅ All workers successfully assigned to Boreholes department!\n";
    } else {
        echo "\n⚠ Warning: Some workers are not in Boreholes department. Please review.\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

