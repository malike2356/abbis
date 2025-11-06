<?php
/**
 * Ecommerce Shop - Tied to ABBIS Catalog
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

// Ensure tables exist
try { $pdo->query("SELECT 1 FROM catalog_items LIMIT 1"); }
catch (Throwable $e) {
    @include_once '../../database/run-sql.php';
    @run_sql_file(__DIR__ . '/../../database/catalog_migration.sql');
}

// Get catalog items (products only, active, sellable)
$itemsStmt = $pdo->query("
    SELECT i.*, c.name as category_name 
    FROM catalog_items i 
    LEFT JOIN catalog_categories c ON c.id=i.category_id 
    WHERE i.is_active=1 AND i.is_sellable=1 AND i.item_type='product'
    ORDER BY i.created_at DESC
");
$products = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$catsStmt = $pdo->query("SELECT * FROM catalog_categories ORDER BY name");
$categories = $catsStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCategory = $_GET['category'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

// Filter products
if ($selectedCategory || $searchQuery) {
    $filtered = [];
    foreach ($products as $prod) {
        if ($selectedCategory && (int)$prod['category_id'] !== (int)$selectedCategory) continue;
        if ($searchQuery && stripos($prod['name'], $searchQuery) === false) continue;
        $filtered[] = $prod;
    }
    $products = $filtered;
}

// Cart functionality
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart_id'])) {
    $_SESSION['cart_id'] = bin2hex(random_bytes(16));
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $itemId = (int)$_POST['item_id'];
    $qty = max(1, intval($_POST['quantity'] ?? 1)); // Force whole number
    
    try {
        $item = $pdo->prepare("SELECT * FROM catalog_items WHERE id=? AND is_sellable=1");
        $item->execute([$itemId]);
        $product = $item->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $cart = $pdo->prepare("INSERT INTO cms_cart_items (session_id, catalog_item_id, quantity, unit_price) VALUES (?,?,?,?)");
            $cart->execute([$_SESSION['cart_id'], $itemId, $qty, $product['sell_price']]);
            $message = 'Added to cart!';
        }
    } catch (Throwable $e) {}
}

// Get cart count
$cartCount = 0;
try {
    $cartStmt = $pdo->prepare("SELECT SUM(quantity) FROM cms_cart_items WHERE session_id=?");
    $cartStmt->execute([$_SESSION['cart_id'] ?? '']);
    $cartCount = (int)$cartStmt->fetchColumn();
} catch (Throwable $e) {}

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Store');
$siteTitle = 'Shop - ' . $companyName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .header { background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 1rem 2rem; }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; color: #0ea5e9; text-decoration: none; }
        .cart-badge { background: #ef4444; color: white; border-radius: 50%; padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-left: 0.5rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
        }
        .filters { background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .filters input, .filters select { padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; transition: border-color 0.2s; }
        .filters input:focus, .filters select:focus { outline: none; border-color: #0ea5e9; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }
        .product-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.3s; border: 1px solid rgba(0,0,0,0.05); }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 12px 40px rgba(0,0,0,0.15); }
        .product-card h3 { color: #1e293b; margin-bottom: 0.75rem; font-size: 1.25rem; font-weight: 600; line-height: 1.3; }
        .product-card .price { font-size: 1.75rem; font-weight: 700; color: #0ea5e9; margin: 1rem 0; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .product-card .unit { color: #64748b; font-size: 0.875rem; margin-bottom: 1rem; }
        .btn-add { width: 100%; padding: 0.875rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem; transition: all 0.3s; box-shadow: 0 2px 8px rgba(14,165,233,0.3); }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14,165,233,0.4); }
        .alert { background: #10b981; color: white; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content" style="min-height: 60vh;">
        <div class="container">
        <?php if (isset($message)): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div style="text-align: center; margin-bottom: 3rem;">
            <h1 style="font-size: 3rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem;">Shop - Products & Equipment</h1>
            <p style="font-size: 1.125rem; color: #64748b;">Browse our quality products and equipment</p>
        </div>

        <div class="filters">
            <input type="text" placeholder="Search products..." value="<?php echo htmlspecialchars($searchQuery); ?>" 
                   onchange="window.location.href='?search='+encodeURIComponent(this.value)" style="flex:1; min-width:200px;">
            <select onchange="window.location.href='?category='+this.value">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($selectedCategory == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="products-grid">
            <?php if (empty($products)): ?>
                <p style="grid-column:1/-1; text-align:center; padding:2rem; color:#64748b;">No products found.</p>
            <?php else: foreach ($products as $prod): ?>
                <div class="product-card">
                    <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo $prod['id']; ?>" style="text-decoration: none; color: inherit;">
                        <h3><?php echo htmlspecialchars($prod['name']); ?></h3>
                    </a>
                    <?php if (!empty($prod['category_name'])): ?>
                        <div style="font-size:0.875rem; color:#64748b; margin-bottom:0.5rem;"><?php echo htmlspecialchars($prod['category_name']); ?></div>
                    <?php endif; ?>
                    <div class="price">GHS <?php echo number_format($prod['sell_price'], 2); ?></div>
                    <?php if (!empty($prod['unit'])): ?>
                        <div class="unit">Per <?php echo htmlspecialchars($prod['unit']); ?></div>
                    <?php endif; ?>
                    <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo $prod['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                        <?php if (!empty($prod['image'])): ?>
                            <img src="<?php echo $baseUrl . '/' . htmlspecialchars($prod['image']); ?>" style="width:100%; height:200px; object-fit:cover; border-radius:6px; margin-bottom:1rem; cursor: pointer;" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                        <?php endif; ?>
                    </a>
                    <form method="post" style="margin-top:1rem;">
                        <input type="hidden" name="item_id" value="<?php echo $prod['id']; ?>">
                        <input type="number" name="quantity" value="1" min="1" step="1" pattern="[0-9]+" inputmode="numeric" required style="width:100%; padding:0.5rem; margin-bottom:0.5rem; border:1px solid #e2e8f0; border-radius:6px;" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                        <button type="submit" name="add_to_cart" class="btn-add">Add to Cart</button>
                    </form>
                    <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo $prod['id']; ?>" style="display: block; text-align: center; margin-top: 0.5rem; color: #0ea5e9; text-decoration: none; font-weight: 500;">View Details →</a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

