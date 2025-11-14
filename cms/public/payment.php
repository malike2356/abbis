<?php
/**
 * Payment Processing Page - WooCommerce-like Design
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure payment tables exist
try {
    $pdo->query("SELECT 1 FROM cms_payment_methods LIMIT 1");
} catch (PDOException $e) {
    // Create payment_methods table
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        provider VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 0,
        config JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Create default payment methods with proper config
    $defaultMethods = [
        ['Mobile Money', 'mobile_money', '{"phone":"+233 XX XXX XXXX","instructions":"Send payment to the mobile money number above with order number as reference."}'],
        ['Bank Transfer', 'bank_transfer', '{"account":"XXXXXXXX","bank":"[Bank Name]","instructions":"Transfer amount to the bank account above with order number as reference."}'],
        ['Cash on Delivery', 'cash', '{"instructions":"Pay when your order is delivered."}'],
        ['Paystack', 'paystack', '{"public_key":"","secret_key":"","instructions":"Pay securely with your card via Paystack."}'],
        ['Flutterwave', 'flutterwave', '{"public_key":"","secret_key":"","instructions":"Pay securely with your card via Flutterwave."}']
    ];
    
    foreach ($defaultMethods as $method) {
        $checkStmt = $pdo->prepare("SELECT id FROM cms_payment_methods WHERE provider=? LIMIT 1");
        $checkStmt->execute([$method[1]]);
        if (!$checkStmt->fetch()) {
            $pdo->prepare("INSERT INTO cms_payment_methods (name, provider, config, is_active) VALUES (?,?,?,1)")
                ->execute([$method[0], $method[1], $method[2]]);
        }
    }
}

try {
    $pdo->query("SELECT 1 FROM cms_payments LIMIT 1");
} catch (PDOException $e) {
    // Create payments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        payment_method_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        transaction_id VARCHAR(255) DEFAULT NULL,
        status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
        payment_data JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES cms_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (payment_method_id) REFERENCES cms_payment_methods(id),
        INDEX idx_order (order_id),
        INDEX idx_status (status),
        INDEX idx_transaction (transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$orderNumber = $_GET['order'] ?? '';
if (!$orderNumber) {
    header('Location: ' . $baseUrl . '/cms/cart');
    exit;
}

// Get order details
$orderStmt = $pdo->prepare("SELECT * FROM cms_orders WHERE order_number=?");
$orderStmt->execute([$orderNumber]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ' . $baseUrl . '/cms/cart');
    exit;
}

// Get order items
$orderItemsStmt = $pdo->prepare("SELECT * FROM cms_order_items WHERE order_id=?");
$orderItemsStmt->execute([$order['id']]);
$orderItems = $orderItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment method (if exists)
$payment = null;
try {
    $paymentStmt = $pdo->prepare("SELECT p.*, pm.name as method_name, pm.provider FROM cms_payments p JOIN cms_payment_methods pm ON pm.id=p.payment_method_id WHERE p.order_id=? LIMIT 1");
    $paymentStmt->execute([$order['id']]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payment = null;
}

// Ensure payment methods exist and are active
$defaultMethods = [
    ['Mobile Money', 'mobile_money', '{"phone":"+233 XX XXX XXXX","instructions":"Send payment to the mobile money number above with order number as reference."}'],
    ['Bank Transfer', 'bank_transfer', '{"account":"XXXXXXXX","bank":"[Bank Name]","instructions":"Transfer amount to the bank account above with order number as reference."}'],
    ['Cash on Delivery', 'cash', '{"instructions":"Pay when your order is delivered."}'],
    ['Paystack', 'paystack', '{"public_key":"","secret_key":"","instructions":"Pay securely with your card via Paystack."}'],
    ['Flutterwave', 'flutterwave', '{"public_key":"","secret_key":"","instructions":"Pay securely with your card via Flutterwave."}']
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

// Get available payment methods
$paymentMethods = [];
try {
    $paymentMethods = $pdo->query("SELECT * FROM cms_payment_methods WHERE is_active=1 ORDER BY 
        CASE provider 
            WHEN 'mobile_money' THEN 1 
            WHEN 'cash' THEN 2 
            WHEN 'bank_transfer' THEN 3 
            WHEN 'paystack' THEN 4 
            WHEN 'flutterwave' THEN 5 
            ELSE 6 
        END, name")->fetchAll();
} catch (PDOException $e) {
    $paymentMethods = [];
}

// Handle payment method selection/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_payment_method'])) {
    $selectedMethodId = intval($_POST['payment_method_id'] ?? 0);
    
    if ($selectedMethodId > 0) {
        try {
            if ($payment) {
                // Update existing payment
                $pdo->prepare("UPDATE cms_payments SET payment_method_id=?, status='pending' WHERE order_id=?")
                    ->execute([$selectedMethodId, $order['id']]);
            } else {
                // Create payment record
                $pdo->prepare("INSERT INTO cms_payments (order_id, payment_method_id, amount, status) VALUES (?, ?, ?, 'pending')")
                    ->execute([$order['id'], $selectedMethodId, $order['total_amount']]);
            }
            header('Location: ' . $baseUrl . '/cms/payment?order=' . urlencode($orderNumber));
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update payment method.';
        }
    }
}

// Handle payment processing
require_once __DIR__ . '/payment-gateways.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        if (!$payment) {
            // Create payment if it doesn't exist
            $defaultMethod = $paymentMethods[0] ?? null;
            if ($defaultMethod) {
                $pdo->prepare("INSERT INTO cms_payments (order_id, payment_method_id, amount, status) VALUES (?, ?, ?, 'pending')")
                    ->execute([$order['id'], $defaultMethod['id'], $order['total_amount']]);
                
                // Reload payment
                $paymentStmt = $pdo->prepare("SELECT p.*, pm.name as method_name, pm.provider, pm.config FROM cms_payments p JOIN cms_payment_methods pm ON pm.id=p.payment_method_id WHERE p.order_id=? LIMIT 1");
                $paymentStmt->execute([$order['id']]);
                $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        if ($payment) {
            // Get payment method details
            $methodStmt = $pdo->prepare("SELECT * FROM cms_payment_methods WHERE id=?");
            $methodStmt->execute([$payment['payment_method_id']]);
            $paymentMethod = $methodStmt->fetch(PDO::FETCH_ASSOC);
            
            // Handle gateway payments
            if ($paymentMethod['provider'] === 'paystack') {
                $initResult = initPaystackPayment($order, $paymentMethod, $baseUrl, $pdo);
                if (!isset($initResult['error'])) {
                    // Store payment gateway data
                    $gatewayData = json_encode($initResult);
                    $pdo->prepare("UPDATE cms_payments SET payment_data=? WHERE id=?")
                        ->execute([$gatewayData, $payment['id']]);
                    
                    // Redirect to payment gateway page
                    header('Location: ' . $baseUrl . '/cms/payment/gateway?order=' . urlencode($orderNumber) . '&gateway=paystack');
                    exit;
                } else {
                    $error = $initResult['error'];
                }
            } elseif ($paymentMethod['provider'] === 'flutterwave') {
                $initResult = initFlutterwavePayment($order, $paymentMethod, $baseUrl, $pdo);
                if (!isset($initResult['error'])) {
                    // Store payment gateway data
                    $gatewayData = json_encode($initResult);
                    $pdo->prepare("UPDATE cms_payments SET payment_data=? WHERE id=?")
                        ->execute([$gatewayData, $payment['id']]);
                    
                    // Redirect to payment gateway page
                    header('Location: ' . $baseUrl . '/cms/payment/gateway?order=' . urlencode($orderNumber) . '&gateway=flutterwave');
                    exit;
                } else {
                    $error = $initResult['error'];
                }
            } else {
                // For non-gateway payments (mobile money, cash, bank transfer)
                // Mark as completed (in production, verify with external system)
                $transactionId = 'TXN-' . time();
                $pdo->prepare("UPDATE cms_payments SET status='completed', transaction_id=? WHERE order_id=?")
                    ->execute([$transactionId, $order['id']]);
                
                $pdo->prepare("UPDATE cms_orders SET status='processing' WHERE id=?")
                    ->execute([$order['id']]);
                
                // Automatically track CMS payment in accounting
                try {
                    require_once $rootPath . '/includes/AccountingAutoTracker.php';
                    $accountingTracker = new AccountingAutoTracker($pdo);
                    $accountingTracker->trackCMSPayment($payment['id'], [
                        'order_number' => $orderNumber,
                        'amount' => floatval($payment['amount'] ?? $order['total_amount']),
                        'payment_method' => $payment['method_name'] ?? $payment['provider'] ?? 'cash',
                        'payment_date' => date('Y-m-d'),
                        'created_by' => $_SESSION['user_id'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log("Accounting auto-tracking error for CMS payment: " . $e->getMessage());
                }
                
                header('Location: ' . $baseUrl . '/cms/order-confirmation?order=' . urlencode($orderNumber));
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = 'Payment processing failed. Please try again.';
    }
}

// Get company name
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
    <title>Payment - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        
        .checkout-progress {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .progress-steps {
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 800px;
            margin: 0 auto;
            gap: 1rem;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }
        .step.active {
            color: #0ea5e9;
            font-weight: 600;
        }
        .step.completed {
            color: #10b981;
        }
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #64748b;
        }
        .step.active .step-number {
            background: #0ea5e9;
            color: white;
        }
        .step.completed .step-number {
            background: #10b981;
            color: white;
        }
        .step-line {
            width: 60px;
            height: 2px;
            background: #e2e8f0;
        }
        .step.completed + .step-line {
            background: #10b981;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .checkout-wrapper {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        .checkout-main {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .checkout-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }
        
        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .order-summary h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .order-item-name {
            color: #475569;
        }
        .order-item-total {
            font-weight: 600;
            color: #1e293b;
        }
        
        .order-total {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-total-label {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }
        .order-total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0ea5e9;
        }
        
        .payment-section {
            margin-bottom: 2rem;
        }
        
        .payment-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }
        
        .payment-methods {
            display: grid;
            gap: 1rem;
        }
        
        .payment-method {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .payment-method:hover {
            border-color: #0ea5e9;
            background: #f0f9ff;
        }
        .payment-method.selected {
            border-color: #0ea5e9;
            background: #f0f9ff;
        }
        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .payment-method-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
        }
        .payment-method-icon {
            font-size: 1.5rem;
        }
        .payment-method-desc {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(14,165,233,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(14,165,233,0.4);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .success-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        .success-icon {
            font-size: 5rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        .success-state h2 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 1rem;
        }
        .success-state p {
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .customer-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .customer-info h3 {
            font-size: 1.125rem;
            margin-bottom: 1rem;
            color: #1e293b;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #64748b;
            font-size: 0.875rem;
        }
        .info-value {
            color: #1e293b;
            font-weight: 500;
        }
        
        @media (max-width: 968px) {
            .checkout-wrapper {
                grid-template-columns: 1fr;
            }
            .checkout-sidebar {
                position: static;
            }
            .progress-steps {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <div class="checkout-progress">
        <div class="progress-steps">
            <div class="step completed">
                <div class="step-number">1</div>
                <span>Cart</span>
            </div>
            <div class="step-line"></div>
            <div class="step completed">
                <div class="step-number">2</div>
                <span>Checkout</span>
            </div>
            <div class="step-line"></div>
            <div class="step active">
                <div class="step-number">3</div>
                <span>Payment</span>
            </div>
            <div class="step-line"></div>
            <div class="step <?php echo isset($_GET['success']) ? 'completed' : ''; ?>">
                <div class="step-number">4</div>
                <span>Complete</span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="checkout-main success-state">
                <div class="success-icon">✓</div>
                <h2>Payment Successful!</h2>
                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($orderNumber); ?></p>
                <p>Thank you for your order. We'll process it and contact you soon.</p>
                <div style="margin-top: 2rem;">
                    <a href="<?php echo $baseUrl; ?>/" class="btn btn-primary">Return to Home</a>
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($_GET['cancelled'])): ?>
                <div class="alert alert-error">Payment was cancelled. Please try again.</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="checkout-wrapper">
                <div class="checkout-main">
                    <div class="payment-section">
                        <h2>Select Payment Method</h2>
                        
                        <?php if (empty($paymentMethods)): ?>
                            <div class="alert alert-error">
                                No payment methods available. Please contact support.
                            </div>
                        <?php else: ?>
                            <form method="post" id="payment-form">
                                <div class="payment-methods">
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <label class="payment-method <?php echo ($payment && $payment['payment_method_id'] == $method['id']) ? 'selected' : ''; ?>">
                                            <input type="radio" name="payment_method_id" value="<?php echo $method['id']; ?>" 
                                                <?php echo ($payment && $payment['payment_method_id'] == $method['id']) ? 'checked' : ''; ?> 
                                                required>
                                            <div class="payment-method-label">
                                                <span class="payment-method-icon">
                                                    <?php
                                                    $icons = [
                                                        'mobile_money' => '📱',
                                                        'bank_transfer' => '🏦',
                                                        'cash' => '💵',
                                                        'paystack' => '💳',
                                                        'flutterwave' => '💳'
                                                    ];
                                                    echo $icons[$method['provider']] ?? '💳';
                                                    ?>
                                                </span>
                                                <span><?php echo htmlspecialchars($method['name']); ?></span>
                                            </div>
                                            <?php if ($method['provider'] === 'mobile_money'): ?>
                                                <div class="payment-method-desc">Pay via MTN Mobile Money, Vodafone Cash, or AirtelTigo Money</div>
                                            <?php elseif ($method['provider'] === 'bank_transfer'): ?>
                                                <div class="payment-method-desc">Direct bank transfer to our account</div>
                                            <?php elseif ($method['provider'] === 'cash'): ?>
                                                <div class="payment-method-desc">Pay when your order is delivered</div>
                                            <?php elseif ($method['provider'] === 'paystack' || $method['provider'] === 'flutterwave'): ?>
                                                <div class="payment-method-desc">Secure online payment with credit/debit card</div>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="submit" name="select_payment_method" class="btn btn-primary" style="margin-top: 1.5rem;">
                                    Continue with Selected Payment Method
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($payment): ?>
                        <div class="payment-section" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                            <h2>Complete Payment</h2>
                            <p style="color: #64748b; margin-bottom: 1.5rem;">
                                Selected: <strong><?php echo htmlspecialchars($payment['method_name']); ?></strong>
                            </p>
                            
                            <?php if ($payment['provider'] === 'mobile_money'): ?>
                                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 8px; border: 2px solid #0ea5e9; margin-bottom: 1.5rem;">
                                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Pay via Mobile Money</h3>
                                    <p style="margin-bottom: 0.5rem;"><strong>Amount:</strong> GHS <?php echo number_format($order['total_amount'], 2); ?></p>
                                    <p style="margin-bottom: 0.5rem;"><strong>Send to:</strong> +233 XX XXX XXXX</p>
                                    <p style="margin-bottom: 0.5rem; font-size: 0.875rem; color: #64748b;"><strong>Reference:</strong> <?php echo htmlspecialchars($orderNumber); ?></p>
                                    <p style="margin-top: 1rem; font-size: 0.875rem; color: #64748b;">After sending the payment, click the button below to confirm.</p>
                                </div>
                            <?php elseif ($payment['provider'] === 'bank_transfer'): ?>
                                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 8px; border: 2px solid #0ea5e9; margin-bottom: 1.5rem;">
                                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Bank Transfer Details</h3>
                                    <p style="margin-bottom: 0.5rem;"><strong>Account Name:</strong> [Your Company Name]</p>
                                    <p style="margin-bottom: 0.5rem;"><strong>Account Number:</strong> XXXXXXXXX</p>
                                    <p style="margin-bottom: 0.5rem;"><strong>Bank:</strong> [Bank Name]</p>
                                    <p style="margin-bottom: 0.5rem;"><strong>Amount:</strong> GHS <?php echo number_format($order['total_amount'], 2); ?></p>
                                    <p style="margin-top: 1rem; font-size: 0.875rem; color: #64748b;"><strong>Reference:</strong> <?php echo htmlspecialchars($orderNumber); ?></p>
                                </div>
                            <?php elseif ($payment['provider'] === 'cash'): ?>
                                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 8px; border: 2px solid #0ea5e9; margin-bottom: 1.5rem;">
                                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Cash on Delivery</h3>
                                    <p style="color: #64748b;">You will pay GHS <?php echo number_format($order['total_amount'], 2); ?> when your order is delivered.</p>
                                </div>
                            <?php elseif ($payment['provider'] === 'paystack' || $payment['provider'] === 'flutterwave'): ?>
                                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 8px; border: 2px solid #0ea5e9; margin-bottom: 1.5rem;">
                                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Secure Online Payment</h3>
                                    <p style="color: #64748b; margin-bottom: 1rem;">You will be redirected to a secure payment gateway to complete your payment.</p>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <button type="submit" name="process_payment" class="btn btn-primary">
                                    Complete Payment
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="checkout-sidebar">
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <div class="customer-info">
                            <h3>Customer Information</h3>
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                            </div>
                            <?php if ($order['customer_phone']): ?>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($order['customer_address']): ?>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_address']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <h3 style="font-size: 1rem; margin-bottom: 0.75rem; color: #64748b;">Order Items</h3>
                            <?php foreach ($orderItems as $item): ?>
                                <div class="order-item">
                                    <span class="order-item-name"><?php echo htmlspecialchars($item['item_name']); ?> × <?php echo intval($item['quantity']); ?></span>
                                    <span class="order-item-total">GHS <?php echo number_format($item['total'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-total">
                            <span class="order-total-label">Total:</span>
                            <span class="order-total-amount">GHS <?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include __DIR__ . '/footer.php'; ?>
    
    <script>
    // Make payment method selection interactive
    document.querySelectorAll('.payment-method input[type="radio"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-method').forEach(function(method) {
                method.classList.remove('selected');
            });
            if (this.checked) {
                this.closest('.payment-method').classList.add('selected');
            }
        });
    });
    </script>
</body>
</html>
