<?php
/**
 * Client Portal - Payment Gateway Bridge
 */
require_once __DIR__ . '/auth-check.php';
require_once $rootPath . '/cms/public/payment-gateways.php'; // Shared payment gateway functions

$paymentId = isset($_GET['payment']) ? (int)$_GET['payment'] : 0;
if ($paymentId <= 0) {
    redirect('payments.php');
}

$stmt = $pdo->prepare('
    SELECT cp.*, ci.invoice_number, ci.balance_due, ci.total_amount, ci.client_id,
           pm.name AS method_name, pm.provider, pm.config
    FROM client_payments cp
    JOIN client_invoices ci ON ci.id = cp.invoice_id
    LEFT JOIN cms_payment_methods pm ON pm.id = cp.payment_method_id
    WHERE cp.id = ? LIMIT 1
');
$stmt->execute([$paymentId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || (int)$payment['client_id'] !== (int)$clientId) {
    $_SESSION['client_payment_error'] = 'Payment not found or access denied.';
    redirect('payments.php');
}

if ($payment['payment_status'] === 'completed') {
    $_SESSION['client_payment_success'] = 'Payment already completed successfully.';
    redirect('payments.php');
}

$gatewayData = json_decode($payment['gateway_response'] ?? '{}', true);
$provider = strtolower($payment['gateway_provider'] ?? $payment['provider'] ?? '');

if (empty($gatewayData) || !in_array($provider, ['paystack', 'flutterwave'], true)) {
    $_SESSION['client_payment_error'] = 'Payment gateway information unavailable.';
    redirect('payments.php');
}

$pageTitle = 'Complete Secure Payment';
$callbackUrl = app_url('client-portal/payment-callback.php?payment=' . $paymentId . '&gateway=' . urlencode($provider));
include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="page-header">
        <h1><?php echo $pageTitle; ?></h1>
        <p>Please wait while we redirect you to complete your payment securely.</p>
    </div>

    <div class="payment-card" style="margin-top: 24px;">
        <div class="card-content" style="text-align:center; padding: 60px 30px;">
            <div class="spinner"></div>
            <h2 style="margin-top:20px;">Preparing payment...</h2>
            <p style="color:#64748b; margin-top: 12px;">Do not close this window. You will be redirected shortly.</p>
        </div>
    </div>
</div>

<style>
.spinner {
    width: 64px;
    height: 64px;
    border: 6px solid rgba(102,126,234,0.2);
    border-top-color: #667eea;
    border-radius: 50%;
    margin: 0 auto;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<?php if ($provider === 'paystack'): ?>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var handler = PaystackPop.setup({
                key: '<?php echo htmlspecialchars($gatewayData['public_key']); ?>',
                email: '<?php echo htmlspecialchars($gatewayData['email'] ?? $gatewayData['customer']['email'] ?? ''); ?>',
                amount: <?php echo (int)($gatewayData['amount'] ?? 0); ?>,
                currency: 'GHS',
                ref: '<?php echo htmlspecialchars($gatewayData['reference']); ?>',
                callback: function(response) {
                    window.location.href = '<?php echo $callbackUrl; ?>&reference=' + encodeURIComponent(response.reference);
                },
                onClose: function() {
                    window.location.href = '<?php echo app_url('client-portal/payments.php?cancelled=1'); ?>';
                }
            });
            handler.openIframe();
        });
    </script>
<?php elseif ($provider === 'flutterwave'): ?>
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            FlutterwaveCheckout({
                public_key: '<?php echo htmlspecialchars($gatewayData['public_key']); ?>',
                tx_ref: '<?php echo htmlspecialchars($gatewayData['tx_ref']); ?>',
                amount: <?php echo json_encode($gatewayData['amount']); ?>,
                currency: '<?php echo htmlspecialchars($gatewayData['currency'] ?? 'GHS'); ?>',
                customer: <?php echo json_encode($gatewayData['customer'] ?? []); ?>,
                callback: function (data) {
                    var url = '<?php echo $callbackUrl; ?>&tx_ref=' + encodeURIComponent(data.tx_ref);
                    if (data.flw_ref) {
                        url += '&flw_ref=' + encodeURIComponent(data.flw_ref);
                    }
                    window.location.href = url;
                },
                onclose: function() {
                    window.location.href = '<?php echo app_url('client-portal/payments.php?cancelled=1'); ?>';
                },
                customizations: {
                    title: '<?php echo addslashes(APP_NAME); ?>',
                    description: 'Invoice payment <?php echo addslashes($payment['invoice_number']); ?>'
                }
            });
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
