<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pos/PosAccountingSync.php';

$pdo = getDBConnection();
$sync = new PosAccountingSync($pdo);
$result = $sync->syncPendingSales(50);

echo sprintf(
    "POS Accounting Sync: processed=%d synced=%d failed=%d\n",
    $result['processed'],
    $result['synced'],
    $result['failed']
);

if (!empty($result['errors'])) {
    foreach ($result['errors'] as $error) {
        echo sprintf(" - Sale %d: %s\n", $error['sale_id'], $error['message']);
    }
}


