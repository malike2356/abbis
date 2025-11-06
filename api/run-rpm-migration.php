<?php
/**
 * Run RPM Enhancement Migration
 * Safe migration runner for RPM tracking columns
 */
require_once '../config/app.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

try {
    $pdo = getDBConnection();
    
    // Read migration file
    $migrationFile = __DIR__ . '/../database/maintenance_rpm_enhancement.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception('Migration file not found');
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Remove USE statement (we'll use the current connection)
    $sql = preg_replace('/^USE\s+`?[^`;]+`?\s*;/mi', '', $sql);
    
    // Split into statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    $skipped = 0;
    $errors = [];
    
    $pdo->beginTransaction();
    
    // Check which columns exist in rigs table
    $existingColumns = [];
    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM rigs");
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = strtolower($col['Field']);
        }
    } catch (PDOException $e) {
        $errors[] = "Failed to check existing columns: " . $e->getMessage();
    }
    
    // Columns to add to rigs table
    $rigsColumns = [
        'current_rpm' => "ALTER TABLE `rigs` ADD COLUMN `current_rpm` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Current total RPM from compressor engine'",
        'last_maintenance_rpm' => "ALTER TABLE `rigs` ADD COLUMN `last_maintenance_rpm` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'RPM reading at last maintenance'",
        'maintenance_due_at_rpm' => "ALTER TABLE `rigs` ADD COLUMN `maintenance_due_at_rpm` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM threshold when maintenance is due'",
        'maintenance_rpm_interval' => "ALTER TABLE `rigs` ADD COLUMN `maintenance_rpm_interval` DECIMAL(10,2) DEFAULT 30.00 COMMENT 'RPM interval between maintenance (e.g., 30.00 means service every 30 RPM)'"
    ];
    
    // Add missing columns to rigs
    foreach ($rigsColumns as $colName => $sql) {
        if (!in_array(strtolower($colName), $existingColumns)) {
            try {
                $pdo->exec($sql);
                $executed++;
            } catch (PDOException $e) {
                $errors[] = "Failed to add column $colName: " . $e->getMessage();
            }
        } else {
            $skipped++;
        }
    }
    
    // Add indexes
    try {
        $indexesStmt = $pdo->query("SHOW INDEXES FROM rigs WHERE Key_name = 'idx_current_rpm'");
        if ($indexesStmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `rigs` ADD INDEX `idx_current_rpm` (`current_rpm`)");
            $executed++;
        }
    } catch (PDOException $e) {
        // Index might already exist or column doesn't exist
    }
    
    try {
        $indexesStmt = $pdo->query("SHOW INDEXES FROM rigs WHERE Key_name = 'idx_maintenance_due'");
        if ($indexesStmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `rigs` ADD INDEX `idx_maintenance_due` (`maintenance_due_at_rpm`)");
            $executed++;
        }
    } catch (PDOException $e) {
        // Index might already exist or column doesn't exist
    }
    
    // Process other statements from migration file
    foreach ($statements as $statement) {
        if (empty($statement) || preg_match('/^\s*--/', $statement)) {
            continue;
        }
        
        // Skip ALTER TABLE rigs statements (already handled above)
        if (preg_match('/ALTER TABLE\s+`?rigs`?/i', $statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Skip if column/table already exists
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                $e->getCode() == '42S21' || $e->getCode() == '42000') {
                $skipped++;
            } else {
                $errors[] = $e->getMessage();
            }
        }
    }
    
    // Initialize existing rigs with default RPM intervals if needed
    try {
        $updateStmt = $pdo->prepare("
            UPDATE rigs 
            SET maintenance_rpm_interval = 30.00,
                maintenance_due_at_rpm = COALESCE(maintenance_due_at_rpm, current_rpm + 30.00)
            WHERE maintenance_due_at_rpm IS NULL AND maintenance_rpm_interval IS NULL
        ");
        $updateStmt->execute();
        if ($updateStmt->rowCount() > 0) {
            $executed++;
        }
    } catch (PDOException $e) {
        // Non-critical
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration completed',
        'executed' => $executed,
        'skipped' => $skipped,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
