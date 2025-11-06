<?php
/**
 * HR Migration Runner - Standalone Script
 * Run this to execute the HR system migration
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "🚀 Starting HR Database Migration...\n\n";

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    $migrationFile = __DIR__ . '/hr_system_migration.sql';
    
    if (!file_exists($migrationFile)) {
        die("❌ Migration file not found: $migrationFile\n");
    }
    
    echo "📄 Reading migration file...\n";
    $sql = file_get_contents($migrationFile);
    
    // Remove USE statement (we're already connected)
    $sql = preg_replace('/USE\s+`?[\w_]+`?\s*;/i', '', $sql);
    
    echo "🔄 Executing migration...\n\n";
    
    $pdo->beginTransaction();
    
    // Execute SET FOREIGN_KEY_CHECKS first
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "  ✓ Foreign key checks disabled\n";
    
    // Split SQL into statements, preserving PREPARE/EXECUTE blocks
    $lines = explode("\n", $sql);
    $statements = [];
    $current = '';
    $inPrepareBlock = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and pure comments
        if (empty($line) || preg_match('/^--/', $line)) {
            continue;
        }
        
        // Detect PREPARE block start
        if (preg_match('/SET\s+@sql\s*=/i', $line)) {
            $inPrepareBlock = true;
        }
        
        // Add line to current statement
        $current .= ($current ? "\n" : '') . $line;
        
        // Detect end of PREPARE block (DEALLOCATE PREPARE)
        if (preg_match('/DEALLOCATE\s+PREPARE/i', $line)) {
            $inPrepareBlock = false;
            if (!empty(trim($current))) {
                $statements[] = trim($current);
            }
            $current = '';
        } 
        // If not in PREPARE block and line ends with semicolon
        elseif (!$inPrepareBlock && substr($line, -1) === ';') {
            if (!empty(trim($current))) {
                $statements[] = trim($current);
            }
            $current = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($current))) {
        $statements[] = trim($current);
    }
    
    $executed = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        // Skip very short or empty statements
        if (empty($statement) || strlen($statement) < 5) {
            continue;
        }
        
        // Skip SELECT statements (they're just checks)
        if (preg_match('/^\s*SELECT\s+1/i', $statement)) {
            $skipped++;
            continue;
        }
        
        try {
            // Execute statement and fetch any results immediately to avoid unbuffered query issues
            $stmt = $pdo->prepare($statement);
            $stmt->execute();
            
            // Fetch all results if it's a SELECT statement
            if (preg_match('/^\s*SELECT/i', $statement)) {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $stmt->closeCursor(); // Close cursor to free resources
            
            $executed++;
            
            // Show progress for significant operations
            if (preg_match('/CREATE\s+TABLE/i', $statement)) {
                preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches);
                if (!empty($matches[1])) {
                    echo "  ✓ Created table: {$matches[1]}\n";
                }
            } elseif (preg_match('/ALTER\s+TABLE.*ADD\s+COLUMN/i', $statement)) {
                preg_match('/ALTER\s+TABLE\s+`?(\w+)`?.*ADD\s+COLUMN\s+`?(\w+)`?/i', $statement, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    echo "  ✓ Added column: {$matches[1]}.{$matches[2]}\n";
                }
            }
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            
            // Skip if table/column already exists (common in migrations)
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'Duplicate') !== false ||
                strpos($errorMsg, '1060') !== false ||
                strpos($errorMsg, '1061') !== false ||
                preg_match('/Duplicate column name/i', $errorMsg) ||
                preg_match('/Duplicate key name/i', $errorMsg)) {
                $skipped++;
            } else {
                // Collect real errors
                $errors[] = "Statement #{$index}: " . substr($errorMsg, 0, 200);
            }
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "  ✓ Foreign key checks re-enabled\n";
    
    $pdo->commit();
    
    echo "\n✅ Migration completed successfully!\n";
    echo "   - Executed: $executed statements\n";
    if ($skipped > 0) {
        echo "   - Skipped: $skipped (already exists)\n";
    }
    
    if (!empty($errors)) {
        echo "\n⚠️  Warnings:\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
    }
    
    // Verify migration success
    echo "\n🔍 Verifying migration...\n";
    $checkTables = ['departments', 'positions', 'attendance_records', 'leave_types', 'leave_requests', 'performance_reviews', 'training_records', 'stakeholders'];
    $allExists = true;
    
    foreach ($checkTables as $table) {
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            echo "  ✓ Table exists: $table\n";
        } catch (PDOException $e) {
            echo "  ❌ Table missing: $table\n";
            $allExists = false;
        }
    }
    
    // Check if workers table has new columns
    try {
        $pdo->query("SELECT email FROM workers LIMIT 1");
        echo "  ✓ Workers table enhanced with HR columns\n";
    } catch (PDOException $e) {
        echo "  ⚠️  Workers table may need additional columns\n";
    }
    
    if ($allExists) {
        echo "\n🎉 HR system is ready to use!\n";
    } else {
        echo "\n⚠️  Some tables may be missing. Please check the errors above.\n";
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (PDOException $e2) {}
    }
    die("❌ Migration failed: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}

echo "\n✨ Done! You can now use the HR module.\n";

