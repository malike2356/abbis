<?php
/**
 * POS Inventory Sync API
 * Syncs catalog_items.stock_quantity to POS stores
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/UnifiedInventoryService.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $inventoryService = new UnifiedInventoryService($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sync all inventory
        $results = $inventoryService->syncAllInventory();
        
        echo json_encode([
            'success' => true,
            'message' => "Synced {$results['synced']} products" . (count($results['errors']) > 0 ? " with " . count($results['errors']) . " errors" : ""),
            'synced' => $results['synced'],
            'errors' => $results['errors']
        ]);
        exit;
    }
    
    // GET - Check sync status
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT ci.id) as total_products,
            COUNT(DISTINCT CASE WHEN ci.stock_quantity > 0 THEN ci.id END) as products_with_stock,
            COUNT(DISTINCT p.id) as pos_products_linked,
            COUNT(DISTINCT CASE WHEN p.catalog_item_id IS NOT NULL THEN p.id END) as pos_products_synced
        FROM catalog_items ci
        LEFT JOIN pos_products p ON p.catalog_item_id = ci.id
        WHERE ci.item_type = 'product' AND ci.is_active = 1
    ");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

