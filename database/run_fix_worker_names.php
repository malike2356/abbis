<?php
/**
 * Run script to fix worker names to match corrected list
 * This script updates worker names in the database to match the corrected list
 */

require_once __DIR__ . '/../config/app.php';

$pdo = getDBConnection();

echo "Starting worker name fix...\n\n";

try {
    $pdo->beginTransaction();
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/fix_worker_names.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove USE statement and comments, split into statements
    $sql = preg_replace('/^USE.*;/mi', '', $sql);
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/START TRANSACTION;/i', '', $sql);
    $sql = preg_replace('/COMMIT;/i', '', $sql);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, 'SELECT') === 0) {
            continue; // Skip empty statements and SELECT queries
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
        } catch (PDOException $e) {
            // Ignore errors for statements that might not apply (e.g., DELETE on non-existent rows)
            if (strpos($e->getMessage(), 'No error') !== false || 
                strpos($e->getMessage(), '0 rows') !== false) {
                echo "⊘ Skipped (no rows affected): " . substr($statement, 0, 60) . "...\n";
            } else {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Verify results
    echo "\n=== Verification ===\n";
    $stmt = $pdo->query("
        SELECT worker_name, role, status, COUNT(*) as count 
        FROM workers 
        GROUP BY worker_name, role, status 
        ORDER BY worker_name
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Current workers in database:\n";
    foreach ($results as $row) {
        echo sprintf("  - %s (%s) [%s] - Count: %d\n", 
            $row['worker_name'], 
            $row['role'], 
            $row['status'],
            $row['count']
        );
    }
    
    $pdo->commit();
    echo "\n✅ Worker name fix completed successfully!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

