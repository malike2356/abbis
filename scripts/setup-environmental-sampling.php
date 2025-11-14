#!/usr/bin/env php
<?php
/**
 * Setup Environmental Sampling tables
 */
require_once __DIR__ . '/../config/database.php';

echo "Setting up Environmental Sampling schema...\n\n";

$pdo = getDBConnection();
$sqlFile = __DIR__ . '/../database/environmental_sampling.sql';

if (!file_exists($sqlFile)) {
    fwrite(STDERR, "Migration file not found: {$sqlFile}\n");
    exit(1);
}

$statements = array_filter(array_map('trim', explode(';', file_get_contents($sqlFile))));

$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    if ($statement === '' || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
        continue;
    }

    try {
        $pdo->exec($statement);
        echo "✓ Executed statement\n";
        $success++;
    } catch (PDOException $e) {
        $message = $e->getMessage();
        if (strpos($message, 'already exists') !== false || strpos($message, 'Duplicate') !== false) {
            echo "⊘ Skipped (already applied)\n";
        } else {
            echo "✗ Error: {$message}\n";
            $errors++;
        }
    }
}

echo "\nCompleted. Success: {$success}, Errors: {$errors}\n";


