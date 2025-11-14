<?php
/**
 * Ecommerce Shop - Modern International Standard Layout
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

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Get categories for filter
$catsStmt = $pdo->query("SELECT * FROM catalog_categories ORDER BY name");
$categories = $catsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$selectedCategory = $_GET['category'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest'; // newest, price_low, price_high, name
$priceMin = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? floatval($_GET['price_min']) : null;
$priceMax = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? floatval($_GET['price_max']) : null;
$onSale = isset($_GET['on_sale']) && $_GET['on_sale'] === '1' ? true : false;
// Default to 'product' if no item_type specified (for shop page, we show products by default)
$itemType = $_GET['item_type'] ?? 'product';
$inStock = isset($_GET['in_stock']) && $_GET['in_stock'] === '1' ? true : false;
$newArrivals = isset($_GET['new_arrivals']) && $_GET['new_arrivals'] === '1' ? true : false;
$minRating = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? intval($_GET['min_rating']) : null;

// Check if sale_price column exists (for ecommerce enhancement)
try {
    $pdo->query("SELECT sale_price FROM catalog_items LIMIT 1");
    $hasSalePriceColumn = true;
} catch (Exception $e) {
    $hasSalePriceColumn = false;
}

$priceRangeStmt = $pdo->query("
    SELECT 
        MIN(COALESCE(" . ($hasSalePriceColumn ? "i.sale_price, " : "") . "i.sell_price)) as min_price,
        MAX(COALESCE(" . ($hasSalePriceColumn ? "i.sale_price, " : "") . "i.sell_price)) as max_price
    FROM catalog_items i
    WHERE i.is_active=1 AND i.is_sellable=1
");
$priceRange = $priceRangeStmt->fetch(PDO::FETCH_ASSOC);
$globalMinPrice = $priceRange['min_price'] ?? 0;
$globalMaxPrice = $priceRange['max_price'] ?? 10000;

// Build query
$whereConditions = ["i.is_active=1", "i.is_sellable=1"];
$params = [];

if ($selectedCategory) {
    $whereConditions[] = "i.category_id = ?";
    $params[] = $selectedCategory;
}

if ($searchQuery) {
    $whereConditions[] = "(i.name LIKE ? OR i.description LIKE ?)";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Price range filter
$priceColumn = $hasSalePriceColumn ? "COALESCE(i.sale_price, i.sell_price)" : "i.sell_price";
if ($priceMin !== null && $priceMin > 0) {
    $whereConditions[] = "$priceColumn >= ?";
    $params[] = $priceMin;
}

if ($priceMax !== null && $priceMax > 0) {
    $whereConditions[] = "$priceColumn <= ?";
    $params[] = $priceMax;
}

// On sale filter (only if columns exist)
if ($onSale) {
    try {
        $pdo->query("SELECT on_sale, sale_price FROM catalog_items LIMIT 1");
        $whereConditions[] = "i.on_sale = 1 AND i.sale_price IS NOT NULL";
    } catch (Exception $e) {
        // Columns don't exist, skip this filter
    }
}

// Item type filter
// Always filter by item_type (defaults to 'product' if not specified)
if (in_array($itemType, ['product', 'service'])) {
    $whereConditions[] = "i.item_type = ?";
    $params[] = $itemType;
} else {
    // Fallback: if invalid type, default to products
    $whereConditions[] = "i.item_type = 'product'";
    $itemType = 'product';
}

// New arrivals filter (last 30 days)
if ($newArrivals) {
    $whereConditions[] = "i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Rating filter (if reviews exist)
if ($minRating !== null && $minRating > 0) {
    $whereConditions[] = "(
        SELECT AVG(rating) 
        FROM cms_product_reviews 
        WHERE product_id = i.id AND status = 'approved'
    ) >= ?";
    $params[] = $minRating;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Sorting
$orderBy = "i.created_at DESC";
switch ($sortBy) {
    case 'price_low':
        $orderBy = "COALESCE(i.sale_price, i.sell_price) ASC";
        break;
    case 'price_high':
        $orderBy = "COALESCE(i.sale_price, i.sell_price) DESC";
        break;
    case 'name':
        $orderBy = "i.name ASC";
        break;
}

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM catalog_items i $whereClause");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get products with average rating and sale information
// Check if sale_price column exists
try {
    $pdo->query("SELECT sale_price FROM catalog_items LIMIT 1");
    $hasSalePrice = true;
} catch (Exception $e) {
    $hasSalePrice = false;
}

$itemsStmt = $pdo->prepare("
    SELECT 
        i.*, 
        c.name as category_name,
        COALESCE(
            (SELECT AVG(rating) 
             FROM cms_product_reviews 
             WHERE product_id = i.id AND status = 'approved'), 
            0
        ) as avg_rating,
        (SELECT COUNT(*) 
         FROM cms_product_reviews 
         WHERE product_id = i.id AND status = 'approved') as review_count" . 
        ($hasSalePrice ? ", i.sale_price, i.on_sale" : "") . "
    FROM catalog_items i 
    LEFT JOIN catalog_categories c ON c.id=i.category_id 
    $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$itemsStmt->execute($params);
$products = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Cart functionality
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart_id'])) {
    $_SESSION['cart_id'] = bin2hex(random_bytes(16));
}

// Handle add to cart
$message = null;
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $itemId = (int)$_POST['item_id'];
    $qty = max(1, intval($_POST['quantity'] ?? 1));
    
    try {
        $item = $pdo->prepare("SELECT * FROM catalog_items WHERE id=? AND is_sellable=1");
        $item->execute([$itemId]);
        $product = $item->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Check if item already in cart
            $existing = $pdo->prepare("SELECT id, quantity FROM cms_cart_items WHERE session_id=? AND catalog_item_id=?");
            $existing->execute([$_SESSION['cart_id'], $itemId]);
            $existingItem = $existing->fetch(PDO::FETCH_ASSOC);
            
            if ($existingItem) {
                $update = $pdo->prepare("UPDATE cms_cart_items SET quantity=quantity+?, unit_price=? WHERE id=?");
                $update->execute([$qty, $product['sell_price'], $existingItem['id']]);
            } else {
                $cart = $pdo->prepare("INSERT INTO cms_cart_items (session_id, catalog_item_id, quantity, unit_price) VALUES (?,?,?,?)");
                $cart->execute([$_SESSION['cart_id'], $itemId, $qty, $product['sell_price']]);
            }
            $message = 'Product added to cart!';
        }
    } catch (Throwable $e) {
        $message = 'Error adding to cart. Please try again.';
        $messageType = 'error';
    }
}

// Get cart count
$cartCount = 0;
try {
    $cartStmt = $pdo->prepare("SELECT SUM(quantity) FROM cms_cart_items WHERE session_id=?");
    $cartStmt->execute([$_SESSION['cart_id'] ?? '']);
    $cartCount = (int)$cartStmt->fetchColumn();
} catch (Throwable $e) {}

// Get company name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Store');
$siteTitle = 'Shop - ' . $companyName;

// Get CMS settings for hero banner
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
$cmsSettings = [];
while ($row = $settingsStmt->fetch()) {
    $cmsSettings[$row['setting_key']] = $row['setting_value'];
}

// Check if hero should be displayed
require_once $rootPath . '/cms/includes/hero-banner-helper.php';
$shouldShowHero = shouldDisplayHeroBanner($cmsSettings, 'shop');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            background: #ffffff; 
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .shop-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .shop-header {
            padding: 40px 0 32px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 32px;
        }
        
        .shop-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .shop-header p {
            font-size: 16px;
            color: #6b7280;
        }
        
        .shop-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        
        .shop-filters {
            display: flex;
            gap: 12px;
            flex: 1;
            min-width: 300px;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background: #ffffff;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            pointer-events: none;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 160px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .sort-select {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
            cursor: pointer;
            min-width: 180px;
        }
        
        .products-section {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 32px;
            margin-bottom: 48px;
        }
        
        .sidebar {
            position: sticky;
            top: 24px;
            height: fit-content;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
        }
        
        .sidebar-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .sidebar-section h3 {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            margin-bottom: 8px;
        }
        
        .category-item a {
            display: block;
            padding: 8px 12px;
            color: #4b5563;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .category-item a:hover {
            background: #ffffff;
            color: #3b82f6;
        }
        
        .category-item.active a {
            background: #3b82f6;
            color: #ffffff;
            font-weight: 500;
        }
        
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        
        .filter-checkbox {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            margin-bottom: 4px;
            font-size: 14px;
            color: #4b5563;
        }
        
        .filter-checkbox:hover {
            background: #ffffff;
            color: #111827;
        }
        
        .filter-checkbox input[type="checkbox"],
        .filter-checkbox input[type="radio"] {
            margin-right: 8px;
            cursor: pointer;
            width: 16px;
            height: 16px;
            accent-color: #3b82f6;
        }
        
        .filter-checkbox input[type="radio"]:checked + span,
        .filter-checkbox input[type="checkbox"]:checked + span {
            color: #3b82f6;
            font-weight: 500;
        }
        
        .price-filter {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .price-inputs {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .price-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
        }
        
        .price-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .price-separator {
            color: #6b7280;
            font-weight: 500;
        }
        
        .price-range-display {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        
        .price-apply-btn {
            padding: 8px 16px;
            background: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .price-apply-btn:hover {
            background: #2563eb;
        }
        
        .rating-filter {
            padding: 6px 0;
        }
        
        .rating-stars {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .star {
            color: #d1d5db;
            font-size: 14px;
            line-height: 1;
        }
        
        .star.filled {
            color: #fbbf24;
        }
        
        .rating-text {
            margin-left: 4px;
            font-size: 13px;
        }
        
        .clear-filter-btn {
            margin-top: 8px;
            padding: 6px 12px;
            background: transparent;
            color: #6b7280;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .clear-filter-btn:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }
        
        .clear-all-filters {
            display: block;
            padding: 10px 16px;
            background: #ef4444;
            color: #ffffff;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .clear-all-filters:hover {
            background: #dc2626;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
        }
        
        .product-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }
        
        .product-image-wrapper {
            position: relative;
            width: 100%;
            padding-top: 75%; /* 4:3 aspect ratio */
            background: #f9fafb;
            overflow: hidden;
        }
        
        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .product-image-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            color: #d1d5db;
        }
        
        .product-info {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-category {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-name a {
            color: inherit;
            text-decoration: none;
        }
        
        .product-name a:hover {
            color: #3b82f6;
        }
        
        .product-price-section {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #f3f4f6;
        }
        
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .product-unit {
            font-size: 13px;
            color: #6b7280;
        }
        
        .product-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .btn-add-cart {
            flex: 1;
            padding: 10px 16px;
            background: #111827;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-add-cart:hover {
            background: #1f2937;
            transform: translateY(-1px);
        }
        
        .btn-view {
            padding: 10px 16px;
            background: #ffffff;
            color: #111827;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .btn-view:hover {
            border-color: #111827;
            background: #f9fafb;
        }
        
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid #e5e7eb;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-decoration: none;
            color: #4b5563;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }
        
        .pagination .current {
            background: #111827;
            color: #ffffff;
            border-color: #111827;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 64px 24px;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        @media (max-width: 1024px) {
            .products-section {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 16px;
            }
        }
        
        @media (max-width: 768px) {
            .shop-container {
                padding: 0 16px;
            }
            
            .shop-header {
                padding: 24px 0 20px;
            }
            
            .shop-header h1 {
                font-size: 24px;
            }
            
            .shop-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .shop-filters {
                flex-direction: column;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
            }
            
            .product-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <?php if ($shouldShowHero): ?>
        <?php
        $heroImage = $cmsSettings['hero_banner_image'] ?? '';
        $heroTitle = $cmsSettings['hero_title'] ?? 'Shop';
        $heroSubtitle = $cmsSettings['hero_subtitle'] ?? 'Browse our quality products and equipment';
        $heroButton1Text = $cmsSettings['hero_button1_text'] ?? 'Shop Now';
        $heroButton1Link = $cmsSettings['hero_button1_link'] ?? '#';
        $heroOverlay = $cmsSettings['hero_overlay_opacity'] ?? '0.4';
        $heroImageUrl = $heroImage ? ($baseUrl . '/' . $heroImage) : '';
        ?>
        <section class="hero-banner" style="position: relative; width: 100%; min-height: 400px; display: flex; align-items: center; justify-content: center; text-align: center; color: white; padding: 4rem 2rem; background: <?php echo $heroImageUrl ? 'url(' . htmlspecialchars($heroImageUrl) . ')' : 'linear-gradient(135deg, #111827 0%, #1f2937 100%)'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, <?php echo htmlspecialchars($heroOverlay); ?>); z-index: 1;"></div>
            <div style="position: relative; z-index: 2; max-width: 1200px; margin: 0 auto;">
                <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem; line-height: 1.2;"><?php echo htmlspecialchars($heroTitle); ?></h1>
                <?php if ($heroSubtitle): ?>
                    <p style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.95;"><?php echo htmlspecialchars($heroSubtitle); ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <main style="padding: 40px 0;">
        <div class="shop-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <span><?php echo $messageType === 'success' ? '✓' : '⚠'; ?></span>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!$shouldShowHero): ?>
                <div class="shop-header">
                    <h1>Shop</h1>
                    <p>Browse our quality products and equipment</p>
                </div>
            <?php endif; ?>
            
            <div class="shop-toolbar">
                <div class="shop-filters">
                    <form method="get" class="search-box" style="display: flex; width: 100%;">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="flex: 1;">
                        <?php if ($selectedCategory): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="page" value="1">
                    </form>
                    <select class="filter-select" onchange="window.location.href='?category='+this.value+'<?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>'">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($selectedCategory == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <select class="sort-select" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => ''])); ?>&sort='+this.value">
                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                </select>
            </div>
            
            <div class="results-info">
                <span><?php echo number_format($totalProducts); ?> <?php echo $itemType === 'service' ? 'service' : 'product'; ?><?php echo $totalProducts !== 1 ? 's' : ''; ?> found</span>
                <?php if ($selectedCategory || $searchQuery || $priceMin || $priceMax || $onSale || $itemType !== 'product' || $newArrivals || $minRating): ?>
                    <a href="?<?php echo $searchQuery ? 'search=' . urlencode($searchQuery) . '&' : ''; ?>page=1&item_type=product" style="color: #3b82f6; text-decoration: none; font-size: 14px;">Clear filters</a>
                <?php endif; ?>
            </div>
            
            <div class="products-section">
                <aside class="sidebar">
                    <form method="get" id="filter-form" class="filter-form">
                        <?php if ($searchQuery): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <?php endif; ?>
                        
                        <!-- Categories Filter -->
                        <div class="sidebar-section">
                            <h3>Categories</h3>
                            <ul class="category-list">
                                <li class="category-item <?php echo !$selectedCategory ? 'active' : ''; ?>">
                                    <label class="filter-checkbox">
                                        <input type="radio" name="category" value="" <?php echo !$selectedCategory ? 'checked' : ''; ?> onchange="this.form.submit();">
                                        <span>All Categories</span>
                                    </label>
                                </li>
                                <?php foreach ($categories as $cat): ?>
                                    <li class="category-item <?php echo ($selectedCategory == $cat['id']) ? 'active' : ''; ?>">
                                        <label class="filter-checkbox">
                                            <input type="radio" name="category" value="<?php echo $cat['id']; ?>" <?php echo ($selectedCategory == $cat['id']) ? 'checked' : ''; ?> onchange="this.form.submit();">
                                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <!-- Price Range Filter -->
                        <div class="sidebar-section">
                            <h3>Price Range</h3>
                            <div class="price-filter">
                                <div class="price-inputs">
                                    <input type="number" name="price_min" placeholder="Min" value="<?php echo $priceMin !== null ? $priceMin : ''; ?>" min="0" step="0.01" class="price-input">
                                    <span class="price-separator">-</span>
                                    <input type="number" name="price_max" placeholder="Max" value="<?php echo $priceMax !== null ? $priceMax : ''; ?>" min="0" step="0.01" class="price-input">
                                </div>
                                <div class="price-range-display">
                                    GHS <?php echo number_format($globalMinPrice, 2); ?> - GHS <?php echo number_format($globalMaxPrice, 2); ?>
                                </div>
                                <button type="submit" class="price-apply-btn">Apply</button>
                            </div>
                        </div>
                        
                        <!-- On Sale Filter -->
                        <div class="sidebar-section">
                            <h3>Deals & Offers</h3>
                            <label class="filter-checkbox">
                                <input type="checkbox" name="on_sale" value="1" <?php echo $onSale ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                                <span>On Sale / Discounted</span>
                            </label>
                        </div>
                        
                        <!-- Item Type Filter -->
                        <div class="sidebar-section">
                            <h3>Item Type</h3>
                            <label class="filter-checkbox">
                                <input type="radio" name="item_type" value="product" <?php echo ($itemType === 'product') ? 'checked' : ''; ?> onchange="this.form.submit();">
                                <span>Products</span>
                            </label>
                            <label class="filter-checkbox">
                                <input type="radio" name="item_type" value="service" <?php echo ($itemType === 'service') ? 'checked' : ''; ?> onchange="this.form.submit();">
                                <span>Services</span>
                            </label>
                        </div>
                        
                        <!-- New Arrivals Filter -->
                        <div class="sidebar-section">
                            <h3>New Arrivals</h3>
                            <label class="filter-checkbox">
                                <input type="checkbox" name="new_arrivals" value="1" <?php echo $newArrivals ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                                <span>Last 30 Days</span>
                            </label>
                        </div>
                        
                        <!-- Customer Rating Filter -->
                        <div class="sidebar-section">
                            <h3>Customer Rating</h3>
                            <?php for ($rating = 4; $rating >= 1; $rating--): ?>
                                <label class="filter-checkbox rating-filter">
                                    <input type="radio" name="min_rating" value="<?php echo $rating; ?>" <?php echo ($minRating === $rating) ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                                    <span class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $rating ? 'filled' : ''; ?>">★</span>
                                        <?php endfor; ?>
                                        <span class="rating-text"><?php echo $rating; ?> & Up</span>
                                    </span>
                                </label>
                            <?php endfor; ?>
                            <?php if ($minRating): ?>
                                <button type="button" onclick="document.querySelector('input[name=min_rating]').checked = false; document.getElementById('filter-form').submit();" class="clear-filter-btn">Clear Rating</button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Clear All Filters -->
                        <?php if ($selectedCategory || $priceMin || $priceMax || $onSale || $itemType !== 'product' || $newArrivals || $minRating): ?>
                            <div class="sidebar-section">
                                <a href="?<?php echo $searchQuery ? 'search=' . urlencode($searchQuery) . '&' : ''; ?>page=1&item_type=product" class="clear-all-filters">Clear All Filters</a>
                            </div>
                        <?php endif; ?>
                        
                        <input type="hidden" name="page" value="1">
                    </form>
                </aside>
                
                <div class="products-grid">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📦</div>
                            <h3>No products found</h3>
                            <p><?php echo ($searchQuery || $selectedCategory || $priceMin || $priceMax || $onSale || ($itemType && $itemType !== 'product') || $newArrivals || $minRating) ? 'Try adjusting your search or filters.' : 'No products available at the moment.'; ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $prod): ?>
                            <div class="product-card">
                                <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo $prod['id']; ?>" class="product-image-wrapper">
                                    <?php if (!empty($prod['image'])): ?>
                                        <img src="<?php echo $baseUrl . '/' . htmlspecialchars($prod['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($prod['name']); ?>" 
                                             class="product-image"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div class="product-image-placeholder" style="display: none;">📦</div>
                                    <?php else: ?>
                                        <div class="product-image-placeholder">📦</div>
                                    <?php endif; ?>
                                </a>
                                
                                <div class="product-info">
                                    <?php if (!empty($prod['category_name'])): ?>
                                        <div class="product-category"><?php echo htmlspecialchars($prod['category_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <h3 class="product-name">
                                        <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo $prod['id']; ?>">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="product-price-section">
                                        <?php 
                                        // Check if product is on sale (from query result)
                                        $finalPrice = $prod['sell_price'];
                                        $hasSale = false;
                                        if (isset($prod['on_sale']) && $prod['on_sale'] && !empty($prod['sale_price'])) {
                                            $hasSale = true;
                                            $finalPrice = $prod['sale_price'];
                                            $discount = (($prod['sell_price'] - $prod['sale_price']) / $prod['sell_price']) * 100;
                                        }
                                        ?>
                                        <?php if ($hasSale): ?>
                                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                                                <div class="product-price" style="color: #ef4444;">GHS <?php echo number_format($finalPrice, 2); ?></div>
                                                <div style="text-decoration: line-through; color: #9ca3af; font-size: 14px;">GHS <?php echo number_format($prod['sell_price'], 2); ?></div>
                                                <?php if ($discount > 0): ?>
                                                    <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;">-<?php echo round($discount); ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="product-price">GHS <?php echo number_format($finalPrice, 2); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($prod['unit'])): ?>
                                            <div class="product-unit">Per <?php echo htmlspecialchars($prod['unit']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($prod['avg_rating']) && $prod['avg_rating'] > 0): ?>
                                            <div style="display: flex; align-items: center; gap: 4px; margin-top: 8px; font-size: 13px;">
                                                <span style="color: #fbbf24;"><?php echo str_repeat('★', round($prod['avg_rating'])); ?></span>
                                                <span style="color: #6b7280;">(<?php echo $prod['review_count'] ?? 0; ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <form method="post" style="flex: 1;">
                                            <input type="hidden" name="item_id" value="<?php echo $prod['id']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" name="add_to_cart" class="btn-add-cart">Add to Cart</button>
                                        </form>
                                        <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo $prod['id']; ?>" class="btn-view">View</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">← Previous</a>
                    <?php else: ?>
                        <span style="opacity: 0.5; cursor: not-allowed;">← Previous</span>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next →</a>
                    <?php else: ?>
                        <span style="opacity: 0.5; cursor: not-allowed;">Next →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
    
    <script>
        // Auto-submit search on Enter
        document.querySelector('.search-box input')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    </script>
</body>
</html>
