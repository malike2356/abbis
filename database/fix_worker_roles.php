<?php
/**
 * Fix Worker Roles to Match Corrected List
 * Roles needed:
 * - Driller (not "Driller (Operator)")
 * - Rig Driver (keep)
 * - Rig Driver / Spanner (add)
 * - Rodboy (not "Rod Boy (General Labourer)")
 * - Spanner boy / Table boy (not just "Spanner Boy")
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting worker roles fix...\n\n";

try {
    // Check if worker_roles table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'worker_roles'");
    if ($checkTable->rowCount() == 0) {
        echo "Creating worker_roles table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `worker_roles` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `role_name` VARCHAR(100) NOT NULL UNIQUE,
              `description` TEXT DEFAULT NULL,
              `is_system` TINYINT(1) DEFAULT 0,
              `is_active` TINYINT(1) DEFAULT 1,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `role_name` (`role_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ Table created\n\n";
    }
    
    $pdo->beginTransaction();
    
    // Get current roles
    $currentRolesStmt = $pdo->query("SELECT id, role_name, is_system FROM worker_roles ORDER BY role_name");
    $currentRoles = $currentRolesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current roles:\n";
    foreach ($currentRoles as $role) {
        echo "  - {$role['role_name']} (ID: {$role['id']}, System: {$role['is_system']})\n";
    }
    echo "\n";
    
    // Define correct roles
    $correctRoles = [
        'Driller' => 'Main drilling operator',
        'Rig Driver' => 'Rig truck driver',
        'Rig Driver / Spanner' => 'Rig driver who also handles spanner work',
        'Rodboy' => 'General laborer handling rods',
        'Spanner boy / Table boy' => 'Spanner and table work - Usually also a labourer'
    ];
    
    // Update existing roles to match correct names
    echo "Updating roles:\n";
    
    // Update "Driller (Operator)" to "Driller"
    $updateStmt = $pdo->prepare("UPDATE worker_roles SET role_name = ?, description = ? WHERE role_name = 'Driller (Operator)'");
    $updateStmt->execute(['Driller', 'Main drilling operator']);
    if ($updateStmt->rowCount() > 0) {
        echo "  ✓ Updated 'Driller (Operator)' → 'Driller'\n";
        
        // Update workers table
        $pdo->exec("UPDATE workers SET role = 'Driller' WHERE role = 'Driller (Operator)'");
        echo "  ✓ Updated workers table\n";
    }
    
    // Update "Rod Boy (General Labourer)" to "Rodboy"
    $updateStmt = $pdo->prepare("UPDATE worker_roles SET role_name = ?, description = ? WHERE role_name LIKE '%Rod Boy%' OR role_name LIKE '%Rodboy%'");
    $updateStmt->execute(['Rodboy', 'General laborer handling rods']);
    if ($updateStmt->rowCount() > 0) {
        echo "  ✓ Updated 'Rod Boy (General Labourer)' → 'Rodboy'\n";
        
        // Update workers table - handle variations
        $pdo->exec("UPDATE workers SET role = 'Rodboy' WHERE role LIKE '%Rod Boy%' OR role LIKE '%Rodboy%'");
        echo "  ✓ Updated workers table\n";
    }
    
    // Update "Spanner Boy" to "Spanner boy / Table boy" if it exists
    $updateStmt = $pdo->prepare("UPDATE worker_roles SET role_name = ?, description = ? WHERE role_name = 'Spanner Boy'");
    $updateStmt->execute(['Spanner boy / Table boy', 'Spanner and table work - Usually also a labourer']);
    if ($updateStmt->rowCount() > 0) {
        echo "  ✓ Updated 'Spanner Boy' → 'Spanner boy / Table boy'\n";
        
        // Update workers table
        $pdo->exec("UPDATE workers SET role = 'Spanner boy / Table boy' WHERE role = 'Spanner Boy'");
        echo "  ✓ Updated workers table\n";
    }
    
    // Add missing roles
    echo "\nAdding missing roles:\n";
    
    $insertStmt = $pdo->prepare("
        INSERT INTO worker_roles (role_name, description, is_system, is_active) 
        VALUES (?, ?, 0, 1)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");
    
    foreach ($correctRoles as $roleName => $description) {
        // Check if role exists
        $checkStmt = $pdo->prepare("SELECT id FROM worker_roles WHERE role_name = ?");
        $checkStmt->execute([$roleName]);
        
        if (!$checkStmt->fetch()) {
            $insertStmt->execute([$roleName, $description]);
            echo "  ✓ Added role: {$roleName}\n";
        } else {
            echo "  ⊙ Role already exists: {$roleName}\n";
        }
    }
    
    // Ensure all workers have correct roles
    echo "\nUpdating worker roles to match corrected list:\n";
    $workerUpdates = [
        ['Atta', 'Driller'],
        ['Isaac', 'Rig Driver / Spanner'],
        ['Tawiah', 'Rodboy'],
        ['Godwin', 'Rodboy'],
        ['Asare', 'Rodboy'],
        ['Earnest', 'Driller'],
        ['Owusua', 'Rig Driver'],
        ['Rasta', 'Spanner boy / Table boy'],
        ['Chief', 'Rodboy'],
        ['Kwesi', 'Rodboy']
    ];
    
    $updateWorkerStmt = $pdo->prepare("UPDATE workers SET role = ? WHERE worker_name = ?");
    foreach ($workerUpdates as $update) {
        $updateWorkerStmt->execute([$update[1], $update[0]]);
        if ($updateWorkerStmt->rowCount() > 0) {
            echo "  ✓ Updated {$update[0]} → {$update[1]}\n";
        }
    }
    
    // Verify final roles
    echo "\n=== Final Roles ===\n";
    $finalRolesStmt = $pdo->query("SELECT role_name, description, is_system, is_active, 
        (SELECT COUNT(*) FROM workers WHERE role = worker_roles.role_name) as worker_count
        FROM worker_roles 
        ORDER BY role_name");
    $finalRoles = $finalRolesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalRoles as $role) {
        echo sprintf("  - %s (%s) - %d workers\n", 
            $role['role_name'], 
            $role['is_system'] ? 'System' : 'Custom',
            $role['worker_count']
        );
    }
    
    // Verify workers
    echo "\n=== Worker Roles ===\n";
    $workersStmt = $pdo->query("SELECT worker_name, role FROM workers ORDER BY worker_name");
    $workers = $workersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($workers as $worker) {
        echo "  - {$worker['worker_name']}: {$worker['role']}\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ Worker roles fixed successfully!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

