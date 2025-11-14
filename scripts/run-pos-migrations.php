<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$migrationsDir = __DIR__ . '/../database/migrations/pos';

if (!is_dir($migrationsDir)) {
    echo "No POS migrations directory found.\n";
    exit(0);
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    echo ">> Running POS migration: " . basename($file) . PHP_EOL;
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "   !! Failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo "All POS migrations executed successfully.\n";


