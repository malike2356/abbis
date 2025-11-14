#!/usr/bin/env php
<?php
/**
 * Setup Client Portal - Run Migration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$pdo = getDBConnection();

echo "Setting up Client Portal...\n\n";

// Read migration file
$migrationFile = __DIR__ . '/../database/client_portal_migration.sql';
if (!file_exists($migrationFile)) {
    die("Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    try {
        $pdo->exec($statement);
        $successCount++;
        echo "✓ Executed statement\n";
    } catch (PDOException $e) {
        $message = $e->getMessage();
        // Ignore idempotent errors
        if (strpos($message, 'already exists') === false &&
            strpos($message, 'Duplicate column') === false &&
            strpos($message, 'Duplicate key name') === false &&
            strpos($message, 'Duplicate entry') === false &&
            strpos($message, 'errno: 1826') === false) {
            echo "✗ Error: " . $message . "\n";
            $errorCount++;
        } else {
            echo "⊘ Skipped (already exists)\n";
        }
    }
}

$fkCheckSql = "
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_payment_method'
      AND TABLE_NAME = 'client_payments'
";

try {
    $fkExists = $pdo->query($fkCheckSql)->fetchColumn();
    if ((int)$fkExists === 0) {
        $pdo->exec("ALTER TABLE client_payments ADD CONSTRAINT fk_payment_method FOREIGN KEY (payment_method_id) REFERENCES cms_payment_methods(id) ON DELETE SET NULL");
        $successCount++;
        echo "✓ Added foreign key fk_payment_method\n";
    } else {
        echo "⊘ Foreign key fk_payment_method already present\n";
    }
} catch (PDOException $e) {
    echo "✗ Error ensuring fk_payment_method: " . $e->getMessage() . "\n";
    $errorCount++;
}

echo "\n";
echo "Migration complete!\n";
echo "Success: $successCount statements\n";
if ($errorCount > 0) {
    echo "Errors: $errorCount statements\n";
}

echo "\n";
echo "Next steps:\n";
echo "1. Create client user accounts with role='client' in the users table\n";
echo "2. Link users to clients by setting client_id in users table\n";
echo "3. Access portal at: " . app_url('client-portal/login.php') . "\n";
echo "\n";

