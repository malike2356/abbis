<?php
/**
 * Run receipt numbers migration
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDBConnection();

echo "Running receipt numbers migration...\n\n";

// Read the migration file
$migrationFile = __DIR__ . '/database/migrations/pos/007_receipt_numbers.sql';
if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);
if ($sql === false) {
    die("Error: Failed to read migration file\n");
}

// Split into statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

$executed = 0;
$failed = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement) || preg_match('/^\s*--/', $statement)) {
        continue; // Skip empty lines and comments
    }
    
    try {
        $pdo->exec($statement);
        $executed++;
        echo "✓ Executed statement\n";
    } catch (PDOException $e) {
        // Check if it's a "column already exists" error (which is okay)
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠ Column already exists (skipping)\n";
            $executed++;
        } else {
            $failed++;
            $errors[] = $e->getMessage();
            echo "✗ Failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Migration Summary ===\n";
echo "Executed: $executed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

// Verify columns were created
echo "\n=== Verifying Columns ===\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM pos_sales WHERE Field IN ('receipt_number', 'paper_receipt_number')");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cols) === 0) {
        echo "⚠ No receipt columns found\n";
    } else {
        foreach ($cols as $col) {
            echo "✓ Column '{$col['Field']}' exists ({$col['Type']})\n";
        }
    }
} catch (PDOException $e) {
    echo "✗ Error checking columns: " . $e->getMessage() . "\n";
}

echo "\n✅ Migration completed!\n";

