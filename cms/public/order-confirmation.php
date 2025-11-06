<?php
/**
 * Order Confirmation Page - WooCommerce-like Thank You Page
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

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

// Get payment method
$payment = null;
try {
    $paymentStmt = $pdo->prepare("SELECT p.*, pm.name as method_name, pm.provider FROM cms_payments p JOIN cms_payment_methods pm ON pm.id=p.payment_method_id WHERE p.order_id=? LIMIT 1");
    $paymentStmt->execute([$order['id']]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payment = null;
}

// Get company name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?php echo htmlspecialchars($companyName); ?></title>
    <?php include __DIR__ . '/header.php'; ?>
    <style>
        .confirmation-container { max-width: 900px; margin: 0 auto; padding: 3rem 2rem; }
        .confirmation-header { text-align: center; margin-bottom: 3rem; }
        .success-icon { font-size: 5rem; color: #10b981; margin-bottom: 1rem; }
        .confirmation-header h1 { font-size: 2.5rem; color: #1e293b; margin-bottom: 0.5rem; }
        .confirmation-header p { font-size: 1.125rem; color: #64748b; }
        .order-details { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .order-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .info-box h3 { font-size: 1rem; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600; }
        .info-box p { font-size: 1.125rem; color: #1e293b; font-weight: 600; }
        .order-items { margin-top: 2rem; }
        .order-item-row { display: flex; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid #e2e8f0; }
        .order-item-row:last-child { border-bottom: none; }
        .order-total-row { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #0ea5e9; display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700; color: #1e293b; }
        .payment-info { background: #f0f9ff; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #0ea5e9; margin-top: 2rem; }
        .action-buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
        .btn { padding: 1rem 2rem; border-radius: 8px; font-weight: 600; text-decoration: none; transition: all 0.2s; display: inline-block; }
        .btn-primary { background: #0ea5e9; color: white; }
        .btn-primary:hover { background: #0284c7; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14,165,233,0.3); }
        .btn-outline { background: white; color: #0ea5e9; border: 2px solid #0ea5e9; }
        .btn-outline:hover { background: #f0f9ff; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content" style="padding: 3rem 0;">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="success-icon">✓</div>
                <h1>Thank You for Your Order!</h1>
                <p>Your order has been received and is being processed.</p>
            </div>
            
            <div class="order-details">
                <div class="order-info-grid">
                    <div class="info-box">
                        <h3>Order Number</h3>
                        <p><?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                    <div class="info-box">
                        <h3>Order Date</h3>
                        <p><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="info-box">
                        <h3>Order Status</h3>
                        <p style="color: #0ea5e9; text-transform: capitalize;"><?php echo htmlspecialchars($order['status']); ?></p>
                    </div>
                    <div class="info-box">
                        <h3>Total Amount</h3>
                        <p style="color: #0ea5e9; font-size: 1.5rem;">GHS <?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                </div>
                
                <div class="order-items">
                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Order Items</h3>
                    <?php foreach ($orderItems as $item): ?>
                        <div class="order-item-row">
                            <div>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <div style="color: #64748b; font-size: 0.875rem; margin-top: 0.25rem;">
                                    Quantity: <?php echo intval($item['quantity']); ?>
                                </div>
                            </div>
                            <div style="font-weight: 600;">
                                GHS <?php echo number_format($item['total'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="order-total-row">
                        <span>Total</span>
                        <span>GHS <?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
                
                <div class="payment-info">
                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Payment Information</h3>
                    <?php if ($payment): ?>
                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['method_name']); ?></p>
                        <p><strong>Payment Status:</strong> <span style="text-transform: capitalize; color: <?php echo $payment['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>;"><?php echo htmlspecialchars($payment['status']); ?></span></p>
                    <?php else: ?>
                        <p>Payment information will be updated shortly.</p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                    <h3 style="margin-bottom: 1rem; color: #1e293b;">Customer Information</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong style="color: #64748b; font-size: 0.875rem;">Name</strong>
                            <p style="margin-top: 0.25rem; color: #1e293b;"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                        </div>
                        <div>
                            <strong style="color: #64748b; font-size: 0.875rem;">Email</strong>
                            <p style="margin-top: 0.25rem; color: #1e293b;"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                        </div>
                        <?php if ($order['customer_phone']): ?>
                        <div>
                            <strong style="color: #64748b; font-size: 0.875rem;">Phone</strong>
                            <p style="margin-top: 0.25rem; color: #1e293b;"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['customer_address']): ?>
                        <div style="grid-column: 1 / -1;">
                            <strong style="color: #64748b; font-size: 0.875rem;">Delivery Address</strong>
                            <p style="margin-top: 0.25rem; color: #1e293b;"><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo $baseUrl; ?>/cms/shop" class="btn btn-primary">Continue Shopping</a>
                <a href="<?php echo $baseUrl; ?>/" class="btn btn-outline">Return to Home</a>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

