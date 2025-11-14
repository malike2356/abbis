<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/pos/PosCatalogSync.php';

$pdo = getDBConnection();
$sync = new PosCatalogSync($pdo);

$productId = isset($argv[1]) ? (int) $argv[1] : null;

try {
    if ($productId) {
        $result = $sync->syncProduct($productId);
        echo "Synced product {$result['product_id']} with catalog item {$result['catalog_item_id']}" . PHP_EOL;
    } else {
        $results = $sync->syncAll();
        foreach ($results as $res) {
            if (($res['status'] ?? '') === 'synced') {
                echo "Synced product {$res['product_id']} → catalog item {$res['catalog_item_id']}" . PHP_EOL;
            } elseif (($res['status'] ?? '') === 'disabled') {
                echo "Disabled catalog link for product {$res['product_id']} (item {$res['catalog_item_id']})" . PHP_EOL;
            } else {
                echo "Skipped product {$res['product_id']} ({$res['reason'] ?? 'no action'})" . PHP_EOL;
            }
        }
    }
    echo "Catalog sync completed successfully." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Catalog sync failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}


