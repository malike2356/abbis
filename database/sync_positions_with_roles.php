<?php
/**
 * Sync Positions with Roles
 * Creates positions based on worker roles and assigns them to workers
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting position sync with roles...\n\n";

try {
    // Check if positions table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'positions'");
    if ($checkTable->rowCount() == 0) {
        echo "Creating positions table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `positions` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `position_code` VARCHAR(20) NOT NULL UNIQUE,
              `position_title` VARCHAR(100) NOT NULL,
              `department_id` INT(11) DEFAULT NULL,
              `description` TEXT DEFAULT NULL,
              `requirements` TEXT DEFAULT NULL,
              `min_salary` DECIMAL(12,2) DEFAULT 0.00,
              `max_salary` DECIMAL(12,2) DEFAULT 0.00,
              `reports_to_position_id` INT(11) DEFAULT NULL,
              `is_active` TINYINT(1) DEFAULT 1,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `position_code` (`position_code`),
              KEY `department_id` (`department_id`),
              KEY `reports_to_position_id` (`reports_to_position_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ Table created\n\n";
    }
    
    // Check if workers table has position_id column
    $checkColumn = $pdo->query("SHOW COLUMNS FROM workers LIKE 'position_id'");
    if ($checkColumn->rowCount() == 0) {
        echo "Adding position_id column to workers table...\n";
        $pdo->exec("ALTER TABLE `workers` ADD COLUMN `position_id` INT(11) DEFAULT NULL");
        echo "✓ Column added\n\n";
    }
    
    $pdo->beginTransaction();
    
    // Get all unique roles from workers
    $rolesStmt = $pdo->query("SELECT DISTINCT role FROM workers WHERE role IS NOT NULL AND role != '' ORDER BY role");
    $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($roles) . " unique roles:\n";
    foreach ($roles as $role) {
        echo "  - {$role}\n";
    }
    echo "\n";
    
    // Create positions for each role
    $positionMap = []; // Maps role name to position ID
    $insertPositionStmt = $pdo->prepare("
        INSERT INTO positions (position_code, position_title, description, is_active) 
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE position_title = VALUES(position_title)
    ");
    
    echo "Creating/updating positions:\n";
    foreach ($roles as $role) {
        // Generate position code from role name
        $positionCode = strtoupper(str_replace([' ', '/', '-'], ['_', '_', '_'], $role));
        $positionCode = preg_replace('/[^A-Z0-9_]/', '', $positionCode);
        $positionCode = substr($positionCode, 0, 20); // Limit to 20 chars
        
        // Check if position already exists
        $checkStmt = $pdo->prepare("SELECT id FROM positions WHERE position_code = ? OR position_title = ?");
        $checkStmt->execute([$positionCode, $role]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $positionId = $existing['id'];
            // Update to match role exactly
            $updateStmt = $pdo->prepare("UPDATE positions SET position_title = ? WHERE id = ?");
            $updateStmt->execute([$role, $positionId]);
            echo "  ✓ Updated position: {$role} (ID: {$positionId}, Code: {$positionCode})\n";
        } else {
            // Insert new position
            $insertPositionStmt->execute([$positionCode, $role, "Position for {$role}"]);
            $positionId = $pdo->lastInsertId();
            echo "  ✓ Created position: {$role} (ID: {$positionId}, Code: {$positionCode})\n";
        }
        
        $positionMap[$role] = $positionId;
    }
    
    echo "\n";
    
    // Update workers to assign positions based on their roles
    echo "Assigning positions to workers:\n";
    $updateWorkerStmt = $pdo->prepare("UPDATE workers SET position_id = ? WHERE role = ? AND (position_id IS NULL OR position_id != ?)");
    
    $updatedCount = 0;
    foreach ($positionMap as $role => $positionId) {
        $updateWorkerStmt->execute([$positionId, $role, $positionId]);
        $count = $updateWorkerStmt->rowCount();
        if ($count > 0) {
            $updatedCount += $count;
            echo "  ✓ Assigned {$count} worker(s) with role '{$role}' to position ID {$positionId}\n";
        }
    }
    
    // Verify assignments
    echo "\n=== Verification ===\n";
    $verifyStmt = $pdo->query("
        SELECT 
            w.worker_name,
            w.role,
            p.position_title,
            p.position_code,
            w.position_id
        FROM workers w
        LEFT JOIN positions p ON w.position_id = p.id
        ORDER BY w.worker_name
    ");
    
    $workers = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $withPosition = 0;
    $withoutPosition = 0;
    
    foreach ($workers as $worker) {
        if ($worker['position_id']) {
            $withPosition++;
            echo "✓ {$worker['worker_name']}: Role='{$worker['role']}', Position='{$worker['position_title']}' ({$worker['position_code']})\n";
        } else {
            $withoutPosition++;
            echo "⚠ {$worker['worker_name']}: Role='{$worker['role']}', Position=NULL\n";
        }
    }
    
    echo "\nSummary:\n";
    echo "  - Workers with positions: {$withPosition}\n";
    echo "  - Workers without positions: {$withoutPosition}\n";
    echo "  - Total positions created/updated: " . count($positionMap) . "\n";
    echo "  - Workers updated: {$updatedCount}\n";
    
    $pdo->commit();
    
    echo "\n✅ Position sync completed successfully!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

