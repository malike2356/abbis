<?php
/**
 * Check actual stock values in ABBIS catalog
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();
$baseUrl = app_base_path();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABBIS Stock Check - POS Admin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 24px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #1d2327; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th { background: #f0f0f1; padding: 10px; text-align: left; font-weight: 600; border: 1px solid #ddd; }
        td { padding: 10px; border: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .has-stock { background: #e8f5e9 !important; }
        .no-stock { background: #ffebee !important; }
        .summary { background: #f0f0f1; padding: 16px; border-radius: 8px; margin: 16px 0; }
        .summary ul { margin: 8px 0; padding-left: 20px; }
        .btn { display: inline-block; padding: 8px 16px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 8px 8px 8px 0; }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
<div class="container">
<h2>ABBIS Catalog Stock Check</h2>
<p>Checking actual stock values in catalog_items table...</p>
<?php

// Check if columns exist
$columns = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM catalog_items LIKE 'stock_quantity'");
    if ($stmt->rowCount() > 0) {
        $columns[] = 'stock_quantity';
    }
} catch (PDOException $e) {}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM catalog_items LIKE 'inventory_quantity'");
    if ($stmt->rowCount() > 0) {
        $columns[] = 'inventory_quantity';
    }
} catch (PDOException $e) {}

echo "<p><strong>Available columns:</strong> " . implode(", ", $columns ?: ['None found']) . "</p>";

// Get products with their stock values
$sql = "SELECT 
    ci.id,
    ci.sku,
    ci.name,
    ci.item_type,
    ci.is_active";
    
if (in_array('stock_quantity', $columns)) {
    $sql .= ", ci.stock_quantity";
}
if (in_array('inventory_quantity', $columns)) {
    $sql .= ", ci.inventory_quantity";
}

$sql .= " FROM catalog_items ci
    WHERE ci.item_type = 'product'
    ORDER BY ci.sku
    LIMIT 50";

$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Catalog Items Stock Values (First 50 products)</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th><th>SKU</th><th>Name</th><th>Type</th>";
if (in_array('stock_quantity', $columns)) {
    echo "<th>stock_quantity</th>";
}
if (in_array('inventory_quantity', $columns)) {
    echo "<th>inventory_quantity</th>";
}
echo "<th>Combined Stock</th><th>Status</th></tr>";

$totalWithStock = 0;
$totalZero = 0;

foreach ($products as $product) {
    $stockQty = isset($product['stock_quantity']) ? (float) $product['stock_quantity'] : null;
    $invQty = isset($product['inventory_quantity']) ? (float) $product['inventory_quantity'] : null;
    $combined = max($stockQty ?? 0, $invQty ?? 0);
    
    if ($combined > 0) {
        $totalWithStock++;
    } else {
        $totalZero++;
    }
    
    $status = $combined > 0 ? '✅ Has Stock' : '❌ Zero Stock';
    $rowClass = $combined > 0 ? 'has-stock' : 'no-stock';
    
    echo "<tr class='$rowClass'>";
    echo "<td>" . $product['id'] . "</td>";
    echo "<td>" . htmlspecialchars($product['sku'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
    echo "<td>" . htmlspecialchars($product['item_type']) . "</td>";
    if (in_array('stock_quantity', $columns)) {
        echo "<td>" . ($stockQty !== null ? number_format($stockQty, 0) : 'NULL') . "</td>";
    }
    if (in_array('inventory_quantity', $columns)) {
        echo "<td>" . ($invQty !== null ? number_format($invQty, 0) : 'NULL') . "</td>";
    }
    echo "<td><strong>" . number_format($combined, 0) . "</strong></td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<div class='summary'>";
echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li><strong>Products with stock:</strong> $totalWithStock</li>";
echo "<li><strong>Products with zero stock:</strong> $totalZero</li>";
echo "<li><strong>Total checked:</strong> " . count($products) . "</li>";
echo "</ul>";

if ($totalZero > 0) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-top: 16px;'>";
    echo "<h4 style='margin-top: 0; color: #856404;'>⚠️ Action Required: Set Stock Quantities</h4>";
    echo "<p style='color: #856404;'><strong>Most products have zero stock.</strong> You need to set stock quantities in one of these places:</p>";
    echo "<ol style='color: #856404; padding-left: 20px;'>";
    echo "<li><strong>ABBIS Resources Module:</strong> Go to Resources → Catalog, edit each product and set the <code>inventory_quantity</code> field.</li>";
    echo "<li><strong>CMS Products:</strong> Go to CMS Admin → Products, edit each product and set the <code>stock_quantity</code> field.</li>";
    echo "</ol>";
    echo "<p style='color: #856404;'><strong>Note:</strong> Once you set stock in either location, it will automatically sync to POS and CMS shop.</p>";
    echo "</div>";
}
echo "</div>";

// Check POS product linking
echo "<h3>POS Product Linking Status</h3>";
$linkStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_pos_products,
        COUNT(p.catalog_item_id) as linked_products,
        COUNT(*) - COUNT(p.catalog_item_id) as unlinked_products
    FROM pos_products p
");
$linkStatus = $linkStmt->fetch(PDO::FETCH_ASSOC);

echo "<ul>";
echo "<li><strong>Total POS Products:</strong> " . $linkStatus['total_pos_products'] . "</li>";
echo "<li><strong>Linked to Catalog:</strong> " . $linkStatus['linked_products'] . "</li>";
echo "<li><strong>Not Linked:</strong> " . $linkStatus['unlinked_products'] . "</li>";
echo "</ul>";

// Show sample of unlinked products
if ($linkStatus['unlinked_products'] > 0) {
    echo "<h4>Sample Unlinked POS Products (First 10)</h4>";
    $unlinkedStmt = $pdo->query("
        SELECT p.sku, p.name, p.catalog_item_id
        FROM pos_products p
        WHERE p.catalog_item_id IS NULL
        LIMIT 10
    ");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>SKU</th><th>Name</th><th>Catalog Link</th></tr>";
    while ($row = $unlinkedStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['sku']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . ($row['catalog_item_id'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
<a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=inventory" class="btn">← Back to Inventory</a>
</div>
</body>
</html>

