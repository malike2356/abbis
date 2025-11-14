<?php
/**
 * Diagnostic script to check Gravels product sync
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();
$repo = new PosRepository($pdo);

echo "<h2>Gravels Product Diagnostic</h2>";

// Check catalog item
echo "<h3>1. Catalog Item (catalog_items table)</h3>";
$catalogStmt = $pdo->query("
    SELECT id, name, sku, 
           stock_quantity, 
           inventory_quantity,
           COALESCE(inventory_quantity, stock_quantity, 0) as combined_stock
    FROM catalog_items 
    WHERE name LIKE '%Gravel%' OR sku LIKE '%047%'
");
$catalogItem = $catalogStmt->fetch(PDO::FETCH_ASSOC);
if ($catalogItem) {
    echo "<pre>";
    print_r($catalogItem);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ No catalog item found for Gravels</p>";
}

// Check POS product
echo "<h3>2. POS Product (pos_products table)</h3>";
$posStmt = $pdo->query("
    SELECT id, name, sku, catalog_item_id, is_active
    FROM pos_products 
    WHERE name LIKE '%Gravel%' OR sku LIKE '%047%'
");
$posProduct = $posStmt->fetch(PDO::FETCH_ASSOC);
if ($posProduct) {
    echo "<pre>";
    print_r($posProduct);
    echo "</pre>";
    
    // Check if linked
    if (empty($posProduct['catalog_item_id'])) {
        echo "<p style='color: orange;'>⚠️ POS product is NOT linked to catalog_item (catalog_item_id is NULL)</p>";
        echo "<p>This is why stock is showing as 0. The product needs to be linked.</p>";
    } else {
        echo "<p style='color: green;'>✅ POS product IS linked to catalog_item ID: {$posProduct['catalog_item_id']}</p>";
        
        // Check if catalog_item_id matches
        if ($catalogItem && $posProduct['catalog_item_id'] != $catalogItem['id']) {
            echo "<p style='color: orange;'>⚠️ Linked to different catalog item (POS: {$posProduct['catalog_item_id']}, Catalog: {$catalogItem['id']})</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ No POS product found for Gravels</p>";
}

// Check what listProducts() returns
echo "<h3>3. What listProducts() Returns</h3>";
$products = $repo->listProducts([], 100, 0);
$gravelsProduct = null;
foreach ($products as $p) {
    if (stripos($p['name'], 'Gravel') !== false || stripos($p['sku'], '047') !== false) {
        $gravelsProduct = $p;
        break;
    }
}

if ($gravelsProduct) {
    echo "<pre>";
    print_r($gravelsProduct);
    echo "</pre>";
    echo "<p><strong>Stock Quantity Shown:</strong> " . ($gravelsProduct['stock_quantity'] ?? 'NULL') . "</p>";
} else {
    echo "<p style='color: red;'>❌ Gravels not found in listProducts() results</p>";
}

// Fix suggestion
echo "<h3>4. Fix Suggestion</h3>";
if ($catalogItem && $posProduct) {
    if (empty($posProduct['catalog_item_id'])) {
        echo "<p>To fix: Link the POS product to the catalog item:</p>";
        echo "<pre>";
        echo "UPDATE pos_products SET catalog_item_id = {$catalogItem['id']} WHERE id = {$posProduct['id']};\n";
        echo "</pre>";
        
        // Auto-fix button
        if (isset($_GET['fix']) && $_GET['fix'] === 'link') {
            try {
                $pdo->prepare("UPDATE pos_products SET catalog_item_id = ? WHERE id = ?")
                    ->execute([$catalogItem['id'], $posProduct['id']]);
                echo "<p style='color: green;'>✅ Fixed! Product linked. Refresh the catalog page.</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p><a href='?fix=link' style='background: #2271b1; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>🔧 Auto-Fix: Link Product</a></p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Products are linked. Stock should show: " . ($catalogItem['combined_stock'] ?? 0) . "</p>";
        echo "<p>If stock is still 0, try refreshing the page or clearing cache.</p>";
    }
}

echo "<hr>";
echo "<p><a href='index.php?action=admin&tab=catalog'>← Back to Catalog</a></p>";

