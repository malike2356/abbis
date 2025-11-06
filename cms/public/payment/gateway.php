<?php
/**
 * Payment Gateway Page - Paystack & Flutterwave
 */
$rootPath = dirname(dirname(dirname(__DIR__)));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once dirname(__DIR__) . '/base-url.php';
require_once dirname(__DIR__) . '/payment-gateways.php';

$pdo = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderNumber = $_GET['order'] ?? '';
$gateway = $_GET['gateway'] ?? '';

if (!$orderNumber || !in_array($gateway, ['paystack', 'flutterwave'])) {
    header('Location: ' . $baseUrl . '/cms/cart');
    exit;
}

// Get order
$orderStmt = $pdo->prepare("SELECT * FROM cms_orders WHERE order_number=?");
$orderStmt->execute([$orderNumber]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ' . $baseUrl . '/cms/cart');
    exit;
}

// Get payment
$paymentStmt = $pdo->prepare("SELECT p.*, pm.name as method_name, pm.provider, pm.config FROM cms_payments p JOIN cms_payment_methods pm ON pm.id=p.payment_method_id WHERE p.order_id=? LIMIT 1");
$paymentStmt->execute([$order['id']]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || empty($payment['payment_data'])) {
    header('Location: ' . $baseUrl . '/cms/payment?order=' . urlencode($orderNumber));
    exit;
}

$gatewayData = json_decode($payment['payment_data'], true);

// Get company name
require_once dirname(__DIR__) . '/get-site-name.php';
$companyName = getCMSSiteName('Our Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - <?php echo htmlspecialchars($companyName); ?></title>
    <?php include dirname(__DIR__) . '/header.php'; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .container { max-width: 600px; margin: 2rem auto; padding: 0 2rem; }
        .payment-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .loading { text-align: center; padding: 3rem; }
        .spinner { border: 4px solid #f3f4f6; border-top: 4px solid #0ea5e9; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/header.php'; ?>
    
    <main class="cms-content" style="min-height: 60vh; padding: 4rem 0;">
        <div class="container">
            <div class="payment-card">
                <div class="loading">
                    <div class="spinner"></div>
                    <h2>Redirecting to Payment Gateway...</h2>
                    <p style="color: #64748b; margin-top: 1rem;">Please wait while we redirect you to complete your payment securely.</p>
                </div>
            </div>
        </div>
    </main>
    
    <?php if ($gateway === 'paystack'): ?>
        <script src="https://js.paystack.co/v1/inline.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var handler = PaystackPop.setup({
                key: '<?php echo htmlspecialchars($gatewayData['public_key']); ?>',
                email: '<?php echo htmlspecialchars($gatewayData['email']); ?>',
                amount: <?php echo $gatewayData['amount']; ?>,
                ref: '<?php echo htmlspecialchars($gatewayData['reference']); ?>',
                currency: 'GHS',
                callback: function(response) {
                    window.location.href = '<?php echo $baseUrl; ?>/cms/payment/callback?gateway=paystack&reference=' + response.reference + '&order=<?php echo urlencode($orderNumber); ?>';
                },
                onClose: function() {
                    window.location.href = '<?php echo $baseUrl; ?>/cms/payment?order=<?php echo urlencode($orderNumber); ?>&cancelled=1';
                }
            });
            handler.openIframe();
        });
        </script>
    <?php elseif ($gateway === 'flutterwave'): ?>
        <script src="https://checkout.flutterwave.com/v3.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            FlutterwaveCheckout({
                public_key: '<?php echo htmlspecialchars($gatewayData['public_key']); ?>',
                tx_ref: '<?php echo htmlspecialchars($gatewayData['tx_ref']); ?>',
                amount: <?php echo $gatewayData['amount']; ?>,
                currency: '<?php echo $gatewayData['currency']; ?>',
                payment_options: 'card,account,banktransfer,ussd,mobilemoney',
                customer: <?php echo json_encode($gatewayData['customer']); ?>,
                meta: {
                    order_number: '<?php echo htmlspecialchars($orderNumber); ?>'
                },
                callback: function(response) {
                    window.location.href = '<?php echo $baseUrl; ?>/cms/payment/callback?gateway=flutterwave&tx_ref=' + response.tx_ref + '&order=<?php echo urlencode($orderNumber); ?>';
                },
                onclose: function() {
                    window.location.href = '<?php echo $baseUrl; ?>/cms/payment?order=<?php echo urlencode($orderNumber); ?>&cancelled=1';
                }
            });
        });
        </script>
    <?php endif; ?>
    
    <?php include dirname(__DIR__) . '/footer.php'; ?>
</body>
</html>

