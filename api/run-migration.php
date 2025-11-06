<?php
/**
 * Run Data Interconnection Migration
 * Executes the database migration script
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Allow CLI or authenticated web access
if (php_sapi_name() !== 'cli') {
    $auth->requireAuth();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die('Access denied. Admin only.');
    }
}

header('Content-Type: text/plain');

$pdo = getDBConnection();
$migrationFile = __DIR__ . '/../database/migration-interconnect-data.sql';

if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

echo "===========================================\n";
echo "Data Interconnection Migration\n";
echo "===========================================\n\n";

$sql = file_get_contents($migrationFile);

// Split by semicolon, but be careful with CREATE VIEW statements
$statements = [];
$currentStatement = '';
$inView = false;

foreach (explode("\n", $sql) as $line) {
    $trimmed = trim($line);
    
    // Skip comments and empty lines
    if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
        continue;
    }
    
    $currentStatement .= $line . "\n";
    
    // Check if we're in a CREATE VIEW statement
    if (stripos($trimmed, 'CREATE') !== false && stripos($trimmed, 'VIEW') !== false) {
        $inView = true;
    }
    
    // End of statement
    if (substr(rtrim($trimmed), -1) === ';' && !$inView) {
        $statements[] = trim($currentStatement);
        $currentStatement = '';
    } elseif ($inView && stripos($trimmed, ';') !== false) {
        $statements[] = trim($currentStatement);
        $currentStatement = '';
        $inView = false;
    }
}

// Add any remaining statement
if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    
    // Skip SET and USE statements
    if (stripos($stmt, 'SET ') === 0 || stripos($stmt, 'USE ') === 0) {
        continue;
    }
    
    try {
        $pdo->exec($stmt);
        $success++;
        echo "✓ " . substr($stmt, 0, 60) . "...\n";
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        
        // Skip errors for existing columns/indexes
        if (strpos($errorMsg, 'Duplicate column') !== false ||
            strpos($errorMsg, 'already exists') !== false ||
            strpos($errorMsg, 'Duplicate key') !== false ||
            strpos($errorMsg, 'Duplicate entry') !== false) {
            $skipped++;
            echo "⏭  Skipped (already exists): " . substr($stmt, 0, 40) . "...\n";
        } else {
            $errors++;
            echo "✗ Error: " . $errorMsg . "\n";
            echo "   Statement: " . substr($stmt, 0, 60) . "...\n";
        }
    }
}

echo "\n===========================================\n";
echo "Migration Complete!\n";
echo "===========================================\n";
echo "Successfully executed: {$success}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "===========================================\n";

// Verify migration
echo "\nVerifying migration...\n";
try {
    // Check field_report_id in maintenance_records
    $pdo->query("SELECT field_report_id FROM maintenance_records LIMIT 1");
    echo "✓ maintenance_records.field_report_id exists\n";
} catch (PDOException $e) {
    echo "✗ maintenance_records.field_report_id missing\n";
}

try {
    // Check is_maintenance_work in field_reports
    $pdo->query("SELECT is_maintenance_work FROM field_reports LIMIT 1");
    echo "✓ field_reports.is_maintenance_work exists\n";
} catch (PDOException $e) {
    echo "✗ field_reports.is_maintenance_work missing\n";
}

try {
    // Check maintenance_record_id in expense_entries
    $pdo->query("SELECT maintenance_record_id FROM expense_entries LIMIT 1");
    echo "✓ expense_entries.maintenance_record_id exists\n";
} catch (PDOException $e) {
    echo "⚠ expense_entries.maintenance_record_id missing (may not exist if no expenses yet)\n";
}

echo "\nMigration verification complete!\n";

