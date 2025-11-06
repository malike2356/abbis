<?php
/**
 * Cart & Checkout
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart_id'])) {
    $_SESSION['cart_id'] = bin2hex(random_bytes(16));
}

$cartId = $_SESSION['cart_id'];

// Get cart items
try {
    $cartStmt = $pdo->prepare("
        SELECT ci.*, cat.name as item_name, cat.sell_price, cat.unit, cat.image
        FROM cms_cart_items ci
        JOIN catalog_items cat ON cat.id=ci.catalog_item_id
        WHERE ci.session_id=?
    ");
    $cartStmt->execute([$cartId]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure quantities are whole numbers
    foreach ($cartItems as &$item) {
        $item['quantity'] = max(1, intval($item['quantity']));
    }
} catch (Throwable $e) {
    $cartItems = [];
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $pdo->prepare("DELETE FROM cms_cart_items WHERE id=? AND session_id=?")
        ->execute([$_GET['remove'], $cartId]);
    header('Location: ' . $baseUrl . '/cms/cart');
    exit;
}

// Cart page - no checkout processing here (moved to checkout.php)

$total = 0;
foreach ($cartItems as $item) {
    $total += $item['quantity'] * $item['unit_price'];
}

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
if (!isset($companyName) || empty($companyName)) {
    $companyName = getCMSSiteName('Our Store');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        /* Enhanced Cart Page Styling - Beautiful WordPress-like design */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
        }
        .cart-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
        }
        .cart-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 2rem; }
        .cart-section {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 0;
            border-bottom: 2px solid #f1f5f9;
            transition: background 0.2s;
        }
        .cart-item:hover { background: #f8fafc; padding-left: 1rem; padding-right: 1rem; margin: 0 -1rem; border-radius: 8px; }
        .cart-item:last-child { border-bottom: none; }
        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            border: none;
            box-shadow: 0 2px 8px rgba(14,165,233,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14,165,233,0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239,68,68,0.4);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #1e293b;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .alert {
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 2rem;
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
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <?php if (empty($cartItems) && !isset($_GET['success'])): ?>
            <div class="cart-hero">
                <div class="container">
                    <h1>Shopping Cart</h1>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="cart-section" style="text-align:center;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">✓</div>
                <h2 style="font-size: 2.5rem; color: #10b981; margin-bottom: 1rem;">Order Placed!</h2>
                <p style="font-size: 1.125rem; color: #475569; margin-bottom: 0.5rem;"><strong>Order Number:</strong> <?php echo htmlspecialchars($_GET['success']); ?></p>
                <p style="color: #64748b;">We'll contact you soon.</p>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems) && !isset($_GET['success'])): ?>
            <div class="cart-section" style="text-align:center; padding:5rem 2rem;">
                <div style="font-size: 5rem; margin-bottom: 1.5rem;">🛒</div>
                <h2 style="font-size: 2rem; color: #1e293b; margin-bottom: 1rem;">Your cart is empty</h2>
                <p style="color: #64748b; margin-bottom: 2rem;">Start adding products to your cart!</p>
                <a href="<?php echo $baseUrl; ?>/cms/shop" class="btn btn-primary">Browse Products</a>
            </div>
        <?php elseif (!empty($cartItems)): ?>
            <div class="cart-hero">
                <div class="container">
                    <h1>Shopping Cart</h1>
                    <p><?php echo count($cartItems); ?> item<?php echo count($cartItems) > 1 ? 's' : ''; ?> in your cart</p>
                </div>
            </div>
            
            <div class="cart-section">
                <h2 style="font-size: 1.75rem; color: #1e293b; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem;">Cart Items</h2>
                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item">
                        <div style="display:flex; gap:15px; align-items:center;">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($item['image']); ?>" style="width:80px; height:80px; object-fit:cover; border-radius:6px;">
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <div style="color:#64748b; font-size:0.875rem;">
                                    GHS <?php echo number_format($item['unit_price'], 2); ?> × <?php echo intval($item['quantity']); ?> <?php echo htmlspecialchars($item['unit'] ?? ''); ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <strong>GHS <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></strong>
                            <a href="?remove=<?php echo $item['id']; ?>" class="btn btn-danger" style="margin-left:1rem; padding:0.5rem 1rem; font-size:0.875rem;">Remove</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:2rem; padding-top:1.5rem; border-top:3px solid #0ea5e9; text-align:right; background: #f8fafc; padding: 1.5rem; border-radius: 8px;">
                    <h2 style="font-size: 2rem; color: #1e293b; margin-bottom: 0.5rem;">Total: <span style="color: #0ea5e9;">GHS <?php echo number_format($total, 2); ?></span></h2>
                    <a href="<?php echo $baseUrl; ?>/cms/checkout" class="btn btn-primary" style="margin-top: 1rem; display: inline-block; padding: 1rem 2.5rem; font-size: 1.125rem; font-weight: 600;">Proceed to Checkout →</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

