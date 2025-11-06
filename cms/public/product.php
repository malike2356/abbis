<?php
/**
 * Single Product View Page
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

// Get product ID from URL
$productId = intval($_GET['id'] ?? 0);
$slug = $_GET['slug'] ?? '';

if (!$productId && $slug) {
    // Try to get product by slug if implemented
    $slugStmt = $pdo->prepare("SELECT id FROM catalog_items WHERE slug=? AND item_type='product' LIMIT 1");
    $slugStmt->execute([$slug]);
    $productId = $slugStmt->fetchColumn();
}

if (!$productId) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Product Not Found</h1><a href="' . $baseUrl . '/cms/shop">← Back to Shop</a>';
    exit;
}

// Get product details
$productStmt = $pdo->prepare("
    SELECT i.*, c.name as category_name 
    FROM catalog_items i 
    LEFT JOIN catalog_categories c ON c.id=i.category_id 
    WHERE i.id=? AND i.item_type='product'
    LIMIT 1
");
$productStmt->execute([$productId]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Product Not Found</h1><a href="' . $baseUrl . '/cms/shop">← Back to Shop</a>';
    exit;
}

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Store');
$siteTitle = htmlspecialchars($product['name']) . ' - ' . $companyName;

// Cart functionality
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart_id'])) {
    $_SESSION['cart_id'] = bin2hex(random_bytes(16));
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $qty = max(1, intval($_POST['quantity'] ?? 1));
    try {
        $cart = $pdo->prepare("INSERT INTO cms_cart_items (session_id, catalog_item_id, quantity, unit_price) VALUES (?,?,?,?)");
        $cart->execute([$_SESSION['cart_id'], $productId, $qty, $product['sell_price']]);
        $message = 'Added to cart!';
    } catch (Throwable $e) {
        $error = 'Failed to add to cart.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteTitle; ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .product-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }
        .product-detail {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .product-image-section {
            background: #f8fafc;
            padding: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-image-section img {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .product-info-section {
            padding: 3rem;
        }
        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        .product-category {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .product-price {
            font-size: 3rem;
            font-weight: 700;
            color: #0ea5e9;
            margin: 2rem 0;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .product-unit {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        .product-description {
            color: #475569;
            line-height: 1.8;
            font-size: 1.0625rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .product-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        .product-meta-item {
            display: flex;
            flex-direction: column;
        }
        .product-meta-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .product-meta-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }
        .add-to-cart-form {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        .quantity-input {
            width: 100px;
            padding: 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            text-align: center;
        }
        .btn-add-cart {
            flex: 1;
            padding: 0.875rem 1.75rem;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(14,165,233,0.3);
        }
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14,165,233,0.4);
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .alert-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .alert-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 2rem;
            padding: 0.75rem 1.5rem;
            background: white;
            color: #0ea5e9;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
            }
            .product-image-section {
                padding: 2rem;
            }
            .product-info-section {
                padding: 2rem;
            }
            .product-title {
                font-size: 2rem;
            }
            .product-price {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="product-content">
        <div class="container">
            <a href="<?php echo $baseUrl; ?>/cms/shop" class="btn-back">← Back to Shop</a>
            
            <div class="product-detail">
                <div class="product-image-section">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?php echo $baseUrl . '/' . htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <div style="width: 100%; height: 400px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; border-radius: 12px; color: #64748b; font-size: 1.5rem;">
                            No Image Available
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="product-info-section">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <?php if (!empty($product['category_name'])): ?>
                        <div class="product-category">
                            <span>🏷️</span>
                            <span><?php echo htmlspecialchars($product['category_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-price">GHS <?php echo number_format($product['sell_price'], 2); ?></div>
                    
                    <?php if (!empty($product['unit'])): ?>
                        <div class="product-unit">Per <?php echo htmlspecialchars($product['unit']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['description'])): ?>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-meta">
                        <?php if (!empty($product['sku'])): ?>
                            <div class="product-meta-item">
                                <span class="product-meta-label">SKU</span>
                                <span class="product-meta-value"><?php echo htmlspecialchars($product['sku']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="product-meta-item">
                            <span class="product-meta-label">Stock</span>
                            <span class="product-meta-value"><?php echo intval($product['stock_quantity'] ?? 0); ?> available</span>
                        </div>
                        <?php if (!empty($product['cost_price'])): ?>
                            <div class="product-meta-item">
                                <span class="product-meta-label">Cost Price</span>
                                <span class="product-meta-value">GHS <?php echo number_format($product['cost_price'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="product-meta-item">
                            <span class="product-meta-label">Status</span>
                            <span class="product-meta-value">
                                <?php if ($product['is_active'] && $product['is_sellable']): ?>
                                    <span style="color: #10b981;">✓ Available</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">✗ Unavailable</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (isset($message)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($product['is_active'] && $product['is_sellable']): ?>
                        <form method="post" class="add-to-cart-form">
                            <input type="number" name="quantity" value="1" min="1" step="1" pattern="[0-9]+" inputmode="numeric" required class="quantity-input" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                            <button type="submit" name="add_to_cart" class="btn-add-cart">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error">This product is currently unavailable.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

