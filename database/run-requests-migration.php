<?php
/**
 * Run Requests Migration
 * Executes the requests_migration.sql file
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting Requests System Migration...\n\n";

// Read the migration file
$migrationFile = __DIR__ . '/requests_migration.sql';
if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

// Remove USE statement as we'll use the connection's database
$sql = preg_replace('/USE\s+`[^`]+`;\s*/i', '', $sql);

// Remove comments
$sql = preg_replace('/--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// Extract trigger SQL separately (handle DELIMITER blocks)
$triggerSql = null;
if (preg_match('/DELIMITER\s+\/\/\s*(.*?)\s*DELIMITER\s+;/s', $sql, $matches)) {
    $triggerSql = trim($matches[1]);
    // Remove the DELIMITER block from main SQL
    $sql = preg_replace('/DELIMITER\s+\/\/.*?DELIMITER\s+;/s', '', $sql);
}

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

$executed = 0;
$failed = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements or very short ones
        if (empty($statement) || strlen($statement) < 10) {
            continue;
        }
        
        // Skip comments
        if (preg_match('/^--/', $statement) || preg_match('/^\/\*/', $statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
            echo "✓ Executed statement #$executed: " . substr($statement, 0, 60) . "...\n";
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            // For ALTER TABLE ADD COLUMN, check if column already exists
            if (preg_match('/ALTER TABLE\s+`?(\w+)`?\s+ADD COLUMN\s+`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                $columnName = $matches[2];
                
                // Check if column exists
                try {
                    $checkCol = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
                    if ($checkCol->rowCount() > 0) {
                        echo "⚠ Skipped (column $columnName already exists in $tableName)\n";
                        continue;
                    }
                } catch (PDOException $checkE) {
                    // If we can't check, continue with original error handling
                }
                
                // If column doesn't exist but we got an error, it's a real problem
                if (strpos($errorMessage, 'Duplicate column') !== false || 
                    strpos($errorMessage, 'already exists') !== false ||
                    $errorCode == '42S21') {
                    echo "⚠ Skipped (column already exists): " . substr($statement, 0, 50) . "...\n";
                    continue;
                }
            }
            
            // For CREATE TABLE IF NOT EXISTS, check if table exists first
            if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                
                // Check if table exists
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '$tableName'");
                    if ($checkTable->rowCount() > 0) {
                        echo "⚠ Skipped (table $tableName already exists)\n";
                        continue;
                    }
                } catch (PDOException $checkE) {
                    // If we can't check, continue with original error handling
                }
                
                // If table doesn't exist but we got an error, it's a real problem
                if (strpos($errorMessage, 'already exists') === false) {
                    // Real error, don't skip
                    $errors[] = [
                        'statement' => substr($statement, 0, 100),
                        'error' => $errorMessage
                    ];
                    $failed++;
                    echo "✗ Failed: " . substr($errorMessage, 0, 100) . "\n";
                } else {
                    echo "⚠ Table already exists (expected with IF NOT EXISTS)\n";
                }
                continue;
            }
            
            // Ignore other "already exists" errors
            if (strpos($errorMessage, 'Duplicate key') !== false ||
                strpos($errorMessage, 'Duplicate entry') !== false) {
                echo "⚠ Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
                continue;
            }
            
            // For foreign key errors, try to continue but log
            if (strpos($errorMessage, 'errno: 150') !== false || 
                strpos($errorMessage, 'Cannot add foreign key') !== false ||
                strpos($errorMessage, 'errno: 1005') !== false) {
                echo "⚠ Foreign key constraint issue: " . substr($errorMessage, 0, 80) . "\n";
                echo "  Statement: " . substr($statement, 0, 80) . "...\n";
                // This is a real error that needs fixing
                $errors[] = [
                    'statement' => substr($statement, 0, 100),
                    'error' => $errorMessage
                ];
                $failed++;
            } else {
                $errors[] = [
                    'statement' => substr($statement, 0, 100),
                    'error' => $errorMessage
                ];
                $failed++;
                echo "✗ Failed: " . substr($errorMessage, 0, 100) . "\n";
            }
        }
    }
    
    // Commit transaction before creating trigger (triggers can't be in transactions)
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    // Handle trigger creation separately (since DELIMITER doesn't work in PDO)
    // Only create trigger if rig_requests table exists
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'rig_requests'");
        if ($checkTable->rowCount() > 0) {
            // Drop trigger if exists
            $pdo->exec("DROP TRIGGER IF EXISTS `generate_rig_request_number`");
            
            // Create trigger with proper syntax (no DELIMITER needed)
            $triggerSql = "
            CREATE TRIGGER `generate_rig_request_number` 
            BEFORE INSERT ON `rig_requests`
            FOR EACH ROW
            BEGIN
                IF NEW.request_number IS NULL OR NEW.request_number = '' THEN
                    SET NEW.request_number = CONCAT('RR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', 
                        LPAD(COALESCE((SELECT MAX(CAST(SUBSTRING(request_number, -4) AS UNSIGNED)) + 1 
                        FROM rig_requests 
                        WHERE DATE(created_at) = CURDATE()), 1), 4, '0'));
                END IF;
            END";
            
            $pdo->exec($triggerSql);
            $executed++;
            echo "✓ Created trigger: generate_rig_request_number\n";
        } else {
            echo "⚠ Skipping trigger creation - rig_requests table not found\n";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false && 
            strpos($e->getMessage(), 'Duplicate') === false) {
            $errors[] = [
                'statement' => 'CREATE TRIGGER',
                'error' => $e->getMessage()
            ];
            $failed++;
            echo "✗ Failed to create trigger: " . $e->getMessage() . "\n";
        } else {
            echo "⚠ Trigger already exists or duplicate\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Migration Summary:\n";
    echo "  ✓ Executed: $executed statements\n";
    echo "  ✗ Failed: $failed statements\n";
    
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - " . $error['error'] . "\n";
        }
    }
    
    if ($failed === 0) {
        echo "\n✅ Migration completed successfully!\n";
    } else {
        echo "\n⚠ Migration completed with some errors. Please review above.\n";
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

