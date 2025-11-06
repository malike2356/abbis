<?php
/**
 * WooCommerce-like Checkout Page
 * Multi-step checkout process
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
    
    foreach ($cartItems as &$item) {
        $item['quantity'] = max(1, intval($item['quantity']));
    }
} catch (Throwable $e) {
    $cartItems = [];
}

// Redirect if cart is empty
if (empty($cartItems)) {
    header('Location: ' . $baseUrl . '/cms/cart');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['quantity'] * $item['unit_price'];
}

// Get shipping methods
$shippingMethods = [];
try {
    $shippingMethods = $pdo->query("SELECT * FROM cms_shipping_methods WHERE is_active=1 ORDER BY cost")->fetchAll();
} catch (PDOException $e) {
    // Create default shipping methods
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_shipping_methods (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(100) NOT NULL,
          method_type VARCHAR(50) NOT NULL,
          cost DECIMAL(10,2) DEFAULT 0.00,
          is_active TINYINT(1) DEFAULT 1,
          config JSON DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $pdo->exec("INSERT IGNORE INTO cms_shipping_methods (name, method_type, cost, is_active) VALUES
          ('Free Shipping', 'free', 0.00, 1),
          ('Standard Shipping', 'standard', 0.00, 1),
          ('Express Shipping', 'express', 50.00, 1)");
        
        $shippingMethods = $pdo->query("SELECT * FROM cms_shipping_methods WHERE is_active=1 ORDER BY cost")->fetchAll();
    } catch (PDOException $e2) {}
}

$selectedShipping = intval($_POST['shipping_method'] ?? $shippingMethods[0]['id'] ?? 0);
$shippingCost = 0;
if ($selectedShipping > 0) {
    foreach ($shippingMethods as $method) {
        if ($method['id'] == $selectedShipping) {
            $shippingCost = floatval($method['cost']);
            break;
        }
    }
}

// Get tax (if enabled)
$taxRate = 0;
$taxAmount = 0;
try {
    $taxStmt = $pdo->query("SELECT * FROM cms_tax_rates WHERE is_active=1 LIMIT 1");
    $tax = $taxStmt->fetch(PDO::FETCH_ASSOC);
    if ($tax) {
        $taxRate = floatval($tax['rate']);
        $taxAmount = ($subtotal + $shippingCost) * ($taxRate / 100);
    }
} catch (PDOException $e) {}

$total = $subtotal + $shippingCost + $taxAmount;

// Ensure cms_orders table has all required columns
try {
    // Check if order_key column exists
    $colCheck = $pdo->query("SHOW COLUMNS FROM cms_orders LIKE 'order_key'");
    if ($colCheck->rowCount() === 0) {
        // Add order_key column and other WooCommerce-like columns if they don't exist
        try {
            $pdo->exec("ALTER TABLE cms_orders 
                ADD COLUMN IF NOT EXISTS order_key VARCHAR(100) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS shipping_method_id INT DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10,2) DEFAULT 0.00,
                ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00,
                ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0.00,
                ADD COLUMN IF NOT EXISTS customer_notes TEXT DEFAULT NULL");
            
            // Add index for order_key
            try {
                $pdo->exec("ALTER TABLE cms_orders ADD INDEX idx_order_key (order_key)");
            } catch (PDOException $e) {
                // Index might already exist, ignore
            }
        } catch (PDOException $e) {
            // Some columns might already exist, try adding individually
            $columnsToAdd = [
                "order_key VARCHAR(100) DEFAULT NULL",
                "shipping_method_id INT DEFAULT NULL",
                "shipping_cost DECIMAL(10,2) DEFAULT 0.00",
                "tax_amount DECIMAL(10,2) DEFAULT 0.00",
                "subtotal DECIMAL(10,2) DEFAULT 0.00",
                "customer_notes TEXT DEFAULT NULL"
            ];
            
            foreach ($columnsToAdd as $colDef) {
                $colName = preg_match('/^(\w+)/', $colDef, $matches) ? $matches[1] : null;
                if ($colName) {
                    try {
                        $colCheck = $pdo->query("SHOW COLUMNS FROM cms_orders LIKE '$colName'");
                        if ($colCheck->rowCount() === 0) {
                            $pdo->exec("ALTER TABLE cms_orders ADD COLUMN $colDef");
                        }
                    } catch (PDOException $e2) {
                        // Column might already exist or other error, continue
                    }
                }
            }
            
            // Try to add index
            try {
                $pdo->exec("ALTER TABLE cms_orders ADD INDEX idx_order_key (order_key)");
            } catch (PDOException $e3) {
                // Index might already exist, ignore
            }
        }
    }
} catch (PDOException $e) {
    // Table might not exist or other error, but cms_orders should exist from migration
}

// Handle checkout form submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $paymentMethodId = intval($_POST['payment_method'] ?? 0);
    $customerNotes = trim($_POST['customer_notes'] ?? '');
    
    if ($customerName && $customerEmail && $customerPhone && $customerAddress && $paymentMethodId > 0) {
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
        $orderKey = bin2hex(random_bytes(16));
        
        try {
            $pdo->beginTransaction();
            
            // Create order
            $orderStmt = $pdo->prepare("
                INSERT INTO cms_orders (
                    order_number, order_key, customer_name, customer_email, customer_phone, 
                    customer_address, subtotal, shipping_cost, tax_amount, total_amount, 
                    shipping_method_id, customer_notes, status
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pending')
            ");
            $orderStmt->execute([
                $orderNumber, $orderKey, $customerName, $customerEmail, $customerPhone,
                $customerAddress, $subtotal, $shippingCost, $taxAmount, $total,
                $selectedShipping, $customerNotes
            ]);
            $orderId = $pdo->lastInsertId();
            
            // Add order items
            $itemStmt = $pdo->prepare("
                INSERT INTO cms_order_items (order_id, catalog_item_id, item_name, quantity, unit_price, total)
                VALUES (?,?,?,?,?,?)
            ");
            foreach ($cartItems as $item) {
                $itemTotal = intval($item['quantity']) * $item['unit_price'];
                $itemStmt->execute([
                    $orderId, $item['catalog_item_id'], $item['item_name'], 
                    intval($item['quantity']), $item['unit_price'], $itemTotal
                ]);
            }
            
            // Create payment record
            try {
                $paymentStmt = $pdo->prepare("
                    INSERT INTO cms_payments (order_id, payment_method_id, amount, status)
                    VALUES (?,?,?,'pending')
                ");
                $paymentStmt->execute([$orderId, $paymentMethodId, $total]);
            } catch (PDOException $e) {
                // Payment table might need to be created
            }
            
            $pdo->commit();
            
            // Clear cart
            $pdo->prepare("DELETE FROM cms_cart_items WHERE session_id=?")->execute([$cartId]);
            
            // Redirect to payment page
            header('Location: ' . $baseUrl . '/cms/payment?order=' . urlencode($orderNumber));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Failed to process order: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Ensure payment methods exist and are active
try {
    $pdo->query("SELECT 1 FROM cms_payment_methods LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        provider VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 0,
        config JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure payment methods are created and active
$defaultMethods = [
    ['Mobile Money', 'mobile_money', '{"phone":"+233 XX XXX XXXX","instructions":"Send payment to the mobile money number above."}'],
    ['Bank Transfer', 'bank_transfer', '{"account":"XXXXXXXX","bank":"[Bank Name]","instructions":"Transfer to the account above."}'],
    ['Cash on Delivery', 'cash', '{"instructions":"Pay when your order is delivered."}'],
    ['Paystack', 'paystack', '{"public_key":"","secret_key":"","instructions":"Pay securely with your card."}'],
    ['Flutterwave', 'flutterwave', '{"public_key":"","secret_key":"","instructions":"Pay securely with your card."}']
];

foreach ($defaultMethods as $method) {
    $checkStmt = $pdo->prepare("SELECT id FROM cms_payment_methods WHERE provider=? LIMIT 1");
    $checkStmt->execute([$method[1]]);
    if (!$checkStmt->fetch()) {
        $pdo->prepare("INSERT INTO cms_payment_methods (name, provider, config, is_active) VALUES (?,?,?,1)")
            ->execute([$method[0], $method[1], $method[2]]);
    } else {
        // Ensure it's active
        $pdo->prepare("UPDATE cms_payment_methods SET is_active=1 WHERE provider=?")->execute([$method[1]]);
    }
}

// Get payment methods
$paymentMethods = $pdo->query("SELECT * FROM cms_payment_methods WHERE is_active=1 ORDER BY 
    CASE provider 
        WHEN 'mobile_money' THEN 1 
        WHEN 'cash' THEN 2 
        WHEN 'bank_transfer' THEN 3 
        WHEN 'paystack' THEN 4 
        WHEN 'flutterwave' THEN 5 
        ELSE 6 
    END, name")->fetchAll();

// Get company name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($companyName); ?></title>
    <?php include __DIR__ . '/header.php'; ?>
    <style>
        .checkout-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .checkout-steps { display: flex; justify-content: space-between; margin-bottom: 3rem; padding: 0; list-style: none; }
        .checkout-step { flex: 1; text-align: center; position: relative; }
        .checkout-step:not(:last-child)::after { content: ''; position: absolute; top: 20px; right: -50%; width: 100%; height: 2px; background: #e2e8f0; z-index: 0; }
        .checkout-step.active:not(:last-child)::after { background: #0ea5e9; }
        .step-number { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; color: #64748b; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; margin-bottom: 0.5rem; position: relative; z-index: 1; }
        .checkout-step.active .step-number { background: #0ea5e9; color: white; }
        .step-label { font-size: 0.875rem; color: #64748b; }
        .checkout-step.active .step-label { color: #0ea5e9; font-weight: 600; }
        
        .checkout-content { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; }
        .checkout-form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .checkout-summary { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: sticky; top: 20px; height: fit-content; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e293b; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
        
        .payment-method { padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .payment-method:hover { border-color: #0ea5e9; }
        .payment-method input[type="radio"]:checked + .payment-label { border-color: #0ea5e9; background: #f0f9ff; }
        .payment-label { display: block; font-weight: 600; color: #1e293b; }
        
        .order-summary-item { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e2e8f0; }
        .order-summary-item:last-child { border-bottom: none; }
        .order-summary-total { margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #0ea5e9; font-size: 1.25rem; font-weight: 700; }
        
        .btn-checkout { width: 100%; padding: 1rem; background: #0ea5e9; color: white; border: none; border-radius: 6px; font-size: 1.125rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-checkout:hover { background: #0284c7; }
        
        @media (max-width: 968px) {
            .checkout-content { grid-template-columns: 1fr; }
            .checkout-summary { position: static; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content" style="padding: 2rem 0;">
        <div class="checkout-container">
            <!-- Checkout Steps -->
            <ul class="checkout-steps">
                <li class="checkout-step active">
                    <div class="step-number">1</div>
                    <div class="step-label">Cart</div>
                </li>
                <li class="checkout-step active">
                    <div class="step-number">2</div>
                    <div class="step-label">Checkout</div>
                </li>
                <li class="checkout-step">
                    <div class="step-number">3</div>
                    <div class="step-label">Payment</div>
                </li>
                <li class="checkout-step">
                    <div class="step-number">4</div>
                    <div class="step-label">Complete</div>
                </li>
            </ul>
            
            <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 6px; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="checkout-content">
                <form method="post" class="checkout-form">
                    <h2 style="margin-bottom: 2rem; color: #1e293b;">Billing Details</h2>
                    
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" required value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Delivery Address *</label>
                        <textarea name="customer_address" required rows="3"><?php echo htmlspecialchars($_POST['customer_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <?php if (!empty($shippingMethods)): ?>
                    <div class="form-group">
                        <label>Shipping Method *</label>
                        <?php foreach ($shippingMethods as $method): ?>
                            <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 0.5rem; cursor: pointer;">
                                <input type="radio" name="shipping_method" value="<?php echo $method['id']; ?>" required <?php echo $selectedShipping == $method['id'] ? 'checked' : ''; ?> style="margin-right: 0.75rem;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($method['name']); ?></div>
                                </div>
                                <div style="font-weight: 600; color: #0ea5e9;">
                                    GHS <?php echo number_format($method['cost'], 2); ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <h2 style="margin: 2rem 0 1rem 0; color: #1e293b;">Payment Method</h2>
                    
                    <?php if (empty($paymentMethods)): ?>
                        <div style="background: #fef3c7; color: #92400e; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                            No payment methods available. Please contact support.
                        </div>
                    <?php else: ?>
                        <?php foreach ($paymentMethods as $pm): ?>
                            <div class="payment-method">
                                <input type="radio" name="payment_method" value="<?php echo $pm['id']; ?>" id="pm_<?php echo $pm['id']; ?>" required>
                                <label for="pm_<?php echo $pm['id']; ?>" class="payment-label">
                                    <?php echo htmlspecialchars($pm['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="form-group" style="margin-top: 2rem;">
                        <label>Order Notes (Optional)</label>
                        <textarea name="customer_notes" rows="3" placeholder="Notes about your order..."><?php echo htmlspecialchars($_POST['customer_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="place_order" class="btn-checkout">Place Order</button>
                </form>
                
                <div class="checkout-summary">
                    <h2 style="margin-bottom: 1.5rem; color: #1e293b;">Order Summary</h2>
                    
                    <?php foreach ($cartItems as $item): ?>
                        <div class="order-summary-item">
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div style="font-size: 0.875rem; color: #64748b;">
                                    Qty: <?php echo intval($item['quantity']); ?>
                                </div>
                            </div>
                            <div style="font-weight: 600;">
                                GHS <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="order-summary-item">
                        <span>Subtotal</span>
                        <span>GHS <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if ($shippingCost > 0): ?>
                    <div class="order-summary-item">
                        <span>Shipping</span>
                        <span>GHS <?php echo number_format($shippingCost, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($taxAmount > 0): ?>
                    <div class="order-summary-item">
                        <span>Tax (<?php echo $taxRate; ?>%)</span>
                        <span>GHS <?php echo number_format($taxAmount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-summary-total">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Total</span>
                            <span>GHS <?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

