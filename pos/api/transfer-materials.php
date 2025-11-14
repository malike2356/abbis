<?php
/**
 * API Endpoint: Get POS items available for transfer to Materials Store
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

header('Content-Type: application/json');

$pdo = getDBConnection();

try {
    // Check if pos_material_mappings table exists
    $tableExists = false;
    $hasCatalogItemId = false;
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'pos_material_mappings'");
        $tableExists = $tableCheck->rowCount() > 0;
        
        if ($tableExists) {
            // Check if catalog_item_id column exists
            $checkStmt = $pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'catalog_item_id'");
            $hasCatalogItemId = $checkStmt->rowCount() > 0;
        }
    } catch (PDOException $e) {
        // Table might not exist, that's okay - we'll skip the join
    }
    
    // Build JOIN condition for pos_material_mappings based on available columns
    if ($tableExists && $hasCatalogItemId) {
        $mappingJoin = "LEFT JOIN pos_material_mappings mm ON (ci.id = mm.catalog_item_id OR pp.id = mm.pos_product_id)";
    } elseif ($tableExists) {
        // Fallback: only join on pos_product_id if catalog_item_id doesn't exist
        $mappingJoin = "LEFT JOIN pos_material_mappings mm ON pp.id = mm.pos_product_id";
    } else {
        // Table doesn't exist, skip the join entirely
        $mappingJoin = "";
    }
    
    // Get POS inventory items that have stock and can be transferred to materials
    // Aggregate by product to get total available quantity across all stores
    $materialTypeSelect = $tableExists ? ", mm.material_type" : ", NULL as material_type";
    $materialTypeGroupBy = $tableExists ? ", mm.material_type" : "";
    
    $sql = "
        SELECT 
            pp.id as product_id,
            pp.name as product_name,
            pp.sku,
            COALESCE(ci.unit, 'pcs') as unit,
            SUM(COALESCE(pi.quantity_on_hand, 0)) as available_quantity,
            ci.id as catalog_item_id
            {$materialTypeSelect},
            GROUP_CONCAT(DISTINCT ps.store_name ORDER BY ps.store_name SEPARATOR ', ') as store_names,
            GROUP_CONCAT(DISTINCT pi.store_id ORDER BY pi.store_id SEPARATOR ',') as store_ids
        FROM pos_products pp
        LEFT JOIN pos_inventory pi ON pp.id = pi.product_id
        LEFT JOIN pos_stores ps ON pi.store_id = ps.id AND ps.is_active = 1
        LEFT JOIN catalog_items ci ON pp.catalog_item_id = ci.id
        " . ($mappingJoin ? $mappingJoin : "") . "
        WHERE pp.is_active = 1
        GROUP BY pp.id, pp.name, pp.sku, ci.unit, ci.id{$materialTypeGroupBy}
        HAVING available_quantity > 0
        ORDER BY pp.name ASC
    ";
    
    $stmt = $pdo->query($sql);
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter to only include items that are material-like (pipes, gravel, etc.)
    // or items that are already mapped to materials
    $materialItems = [];
    foreach ($items as $item) {
        $name = strtolower($item['product_name'] ?? '');
        $hasStock = ($item['available_quantity'] ?? 0) > 0;
        $isMapped = !empty($item['material_type']);
        
        // Check if name contains material keywords
        $materialKeywords = ['pipe', 'gravel', 'rod', 'screen', 'plain', 'pvc', 'material'];
        $isMaterialLike = false;
        foreach ($materialKeywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                $isMaterialLike = true;
                break;
            }
        }
        
        if ($hasStock && ($isMapped || $isMaterialLike)) {
            // Use first store ID if multiple stores
            $storeIds = !empty($item['store_ids']) ? explode(',', $item['store_ids']) : [];
            $item['store_id'] = !empty($storeIds) ? intval($storeIds[0]) : null;
            $item['store_name'] = $item['store_names'] ?? 'Multiple Stores';
            $item['name'] = $item['product_name'];
            $materialItems[] = $item;
        }
    }
    
    echo json_encode([
        'success' => true,
        'items' => array_values($materialItems),
        'count' => count($materialItems)
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching POS transfer items: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading POS inventory: ' . $e->getMessage(),
        'items' => []
    ]);
} catch (Exception $e) {
    error_log("Error in transfer-materials API: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'items' => []
    ]);
}

