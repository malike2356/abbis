<?php
/**
 * POS Store Stock API
 * Returns available stock for a specific POS store
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';

$auth->requireAuth();

header('Content-Type: application/json');

$storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

if ($storeId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Store ID is required'], 400);
}

try {
    $repo = new PosRepository();
    $inventory = $repo->listInventoryByStore($storeId);
    
    $stock = [];
    foreach ($inventory as $item) {
        $stock[] = [
            'product_id' => (int)$item['id'],
            'product_name' => $item['product_name'] ?? 'Unknown',
            'sku' => $item['sku'] ?? '',
            'quantity_on_hand' => floatval($item['quantity_on_hand'] ?? 0)
        ];
    }
    
    jsonResponse([
        'success' => true,
        'store_id' => $storeId,
        'stock' => $stock
    ]);
} catch (Throwable $e) {
    error_log('[Store Stock API] Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to load store stock'], 500);
}

