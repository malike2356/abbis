<?php
/**
 * CMS CRUD and Integration Audit Script
 * Checks all admin and public pages for CRUD completeness and ABBIS integration
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$issues = [];
$recommendations = [];

echo "🔍 CMS CRUD and Integration Audit\n";
echo str_repeat("=", 80) . "\n\n";

// 1. Check Admin Pages for CRUD Operations
echo "📋 ADMIN PAGES CRUD AUDIT\n";
echo str_repeat("-", 80) . "\n";

$adminPages = [
    'pages.php' => ['table' => 'cms_pages', 'required_ops' => ['create', 'read', 'update', 'delete']],
    'posts.php' => ['table' => 'cms_posts', 'required_ops' => ['create', 'read', 'update', 'delete']],
    'products.php' => ['table' => 'catalog_items', 'required_ops' => ['create', 'read', 'update', 'delete']],
    'orders.php' => ['table' => 'cms_orders', 'required_ops' => ['read', 'update']],
    'quotes.php' => ['table' => 'cms_quote_requests', 'required_ops' => ['read', 'update']],
    'rig-requests.php' => ['table' => 'rig_requests', 'required_ops' => ['read', 'update']],
    'users.php' => ['table' => 'cms_users', 'required_ops' => ['create', 'read', 'update', 'delete']],
    'menus.php' => ['table' => 'cms_menu_items', 'required_ops' => ['create', 'read', 'update', 'delete']],
    'coupons.php' => ['table' => 'cms_coupons', 'required_ops' => ['create', 'read', 'update', 'delete']],
    'comments.php' => ['table' => 'cms_comments', 'required_ops' => ['read', 'update', 'delete']],
    'categories.php' => ['table' => 'cms_categories', 'required_ops' => ['create', 'read', 'update', 'delete']],
];

foreach ($adminPages as $page => $config) {
    $filePath = __DIR__ . '/../cms/admin/' . $page;
    if (!file_exists($filePath)) {
        $issues[] = "❌ Missing: $page";
        continue;
    }
    
    $content = file_get_contents($filePath);
    $table = $config['table'];
    
    // Check if table exists
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
    } catch (PDOException $e) {
        $issues[] = "❌ Table missing: $table (required by $page)";
        continue;
    }
    
    // Check CRUD operations
    $hasCreate = strpos($content, 'INSERT INTO') !== false || strpos($content, 'save_') !== false;
    $hasRead = strpos($content, 'SELECT') !== false;
    $hasUpdate = strpos($content, 'UPDATE') !== false || strpos($content, 'update_') !== false;
    $hasDelete = strpos($content, 'DELETE FROM') !== false || strpos($content, 'delete_') !== false;
    
    $missing = [];
    if (in_array('create', $config['required_ops']) && !$hasCreate) $missing[] = 'Create';
    if (in_array('read', $config['required_ops']) && !$hasRead) $missing[] = 'Read';
    if (in_array('update', $config['required_ops']) && !$hasUpdate) $missing[] = 'Update';
    if (in_array('delete', $config['required_ops']) && !$hasDelete) $missing[] = 'Delete';
    
    if (empty($missing)) {
        echo "✅ $page: All CRUD operations present\n";
    } else {
        $issues[] = "⚠️  $page: Missing operations: " . implode(', ', $missing);
        echo "⚠️  $page: Missing operations: " . implode(', ', $missing) . "\n";
    }
}

echo "\n";

// 2. Check ABBIS Integration Points
echo "🔗 ABBIS INTEGRATION AUDIT\n";
echo str_repeat("-", 80) . "\n";

// Check orders integration
try {
    $ordersStmt = $pdo->query("SHOW COLUMNS FROM cms_orders LIKE 'client_id'");
    if ($ordersStmt->rowCount() > 0) {
        echo "✅ Orders have client_id field\n";
    } else {
        $issues[] = "❌ Orders missing client_id field for ABBIS integration";
        echo "❌ Orders missing client_id field\n";
    }
    
    $ordersStmt = $pdo->query("SHOW COLUMNS FROM cms_orders LIKE 'field_report_id'");
    if ($ordersStmt->rowCount() > 0) {
        echo "✅ Orders have field_report_id field\n";
    } else {
        $issues[] = "❌ Orders missing field_report_id field for ABBIS integration";
        echo "❌ Orders missing field_report_id field\n";
    }
} catch (PDOException $e) {
    $issues[] = "❌ Cannot check orders table: " . $e->getMessage();
}

// Check products/catalog integration
try {
    $productsStmt = $pdo->query("SELECT COUNT(*) FROM catalog_items WHERE is_sellable = 1");
    $sellableCount = $productsStmt->fetchColumn();
    echo "✅ Catalog integration: $sellableCount sellable products\n";
} catch (PDOException $e) {
    $issues[] = "❌ Cannot check catalog_items table";
}

// Check quote requests CRM integration
try {
    $quotesStmt = $pdo->query("SHOW COLUMNS FROM cms_quote_requests LIKE 'converted_to_client_id'");
    if ($quotesStmt->rowCount() > 0) {
        echo "✅ Quote requests have converted_to_client_id field\n";
    } else {
        $issues[] = "❌ Quote requests missing converted_to_client_id field";
        echo "❌ Quote requests missing converted_to_client_id field\n";
    }
} catch (PDOException $e) {
    $issues[] = "❌ Cannot check cms_quote_requests table";
}

// Check if categories CRUD exists
$categoriesFile = __DIR__ . '/../cms/admin/categories.php';
if (!file_exists($categoriesFile)) {
    $issues[] = "❌ Missing categories.php admin page";
    echo "❌ Missing categories.php admin page\n";
    $recommendations[] = "Create categories.php with full CRUD operations";
} else {
    echo "✅ Categories admin page exists\n";
}

echo "\n";

// 3. Check Public Pages
echo "🌐 PUBLIC PAGES AUDIT\n";
echo str_repeat("-", 80) . "\n";

$publicPages = [
    'shop.php' => 'Should display products from catalog_items',
    'cart.php' => 'Should manage cart items',
    'checkout.php' => 'Should create orders and link to clients',
    'quote.php' => 'Should create quote requests and link to CRM',
    'rig-request.php' => 'Should create rig requests',
    'blog.php' => 'Should display published posts',
    'post.php' => 'Should display individual posts',
    'page.php' => 'Should display CMS pages',
];

foreach ($publicPages as $page => $description) {
    $filePath = __DIR__ . '/../cms/public/' . $page;
    if (file_exists($filePath)) {
        echo "✅ $page exists\n";
    } else {
        $issues[] = "❌ Missing public page: $page";
        echo "❌ Missing: $page - $description\n";
    }
}

echo "\n";

// 4. Summary
echo "📊 SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "Total Issues Found: " . count($issues) . "\n";
echo "Total Recommendations: " . count($recommendations) . "\n\n";

if (!empty($issues)) {
    echo "ISSUES TO FIX:\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
    echo "\n";
}

if (!empty($recommendations)) {
    echo "RECOMMENDATIONS:\n";
    foreach ($recommendations as $rec) {
        echo "  • $rec\n";
    }
    echo "\n";
}

if (empty($issues) && empty($recommendations)) {
    echo "✅ All checks passed! CMS is fully functional with complete CRUD operations.\n";
}

