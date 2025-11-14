<?php
/**
 * Debug script to check inventory linking
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();

echo "<h2>POS Products to Catalog Items Linking Status</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>POS SKU</th><th>POS Name</th><th>Catalog Item ID (Linked)</th><th>Catalog Stock Qty</th><th>POS Store Qty</th><th>Status</th></tr>";

$stmt = $pdo->query("
    SELECT 
        p.id as pos_id,
        p.sku,
        p.name as pos_name,
        p.catalog_item_id,
        ci.id as catalog_id,
        ci.name as catalog_name,
        ci.stock_quantity,
        COALESCE(SUM(pi.quantity_on_hand), 0) as pos_inventory_total
    FROM pos_products p
    LEFT JOIN catalog_items ci ON p.catalog_item_id = ci.id
    LEFT JOIN pos_inventory pi ON pi.product_id = p.id
    GROUP BY p.id
    ORDER BY p.sku
    LIMIT 20
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $linked = !empty($row['catalog_item_id']);
    $hasStock = !empty($row['stock_quantity']) && $row['stock_quantity'] > 0;
    $status = $linked ? ($hasStock ? '✅ Linked & Has Stock' : '⚠️ Linked but No Stock') : '❌ Not Linked';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['sku']) . "</td>";
    echo "<td>" . htmlspecialchars($row['pos_name']) . "</td>";
    echo "<td>" . ($row['catalog_item_id'] ?: 'NULL') . "</td>";
    echo "<td>" . ($row['stock_quantity'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['pos_inventory_total'] . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Catalog Items with Stock</h2>";
$stmt2 = $pdo->query("
    SELECT id, sku, name, stock_quantity 
    FROM catalog_items 
    WHERE item_type = 'product' AND stock_quantity > 0
    ORDER BY stock_quantity DESC
    LIMIT 10
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Catalog ID</th><th>SKU</th><th>Name</th><th>Stock Qty</th></tr>";
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['sku']) . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . $row['stock_quantity'] . "</td>";
    echo "</tr>";
}
echo "</table>";

