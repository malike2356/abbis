<?php
/**
 * Payment Gateway Callback Handler
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

if (!$orderNumber) {
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

if (!$payment) {
    header('Location: ' . $baseUrl . '/cms/payment?order=' . urlencode($orderNumber));
    exit;
}

$paymentConfig = json_decode($payment['config'] ?? '{}', true);

$success = false;
$error = null;

if ($gateway === 'paystack') {
    $reference = $_GET['reference'] ?? '';
    if ($reference) {
        $secretKey = $paymentConfig['secret_key'] ?? '';
        // Pass secretKey if available, otherwise let function get it from config/settings
        $verification = verifyPaystackPayment($reference, $secretKey ?: null, $pdo);
        if ($verification['success']) {
            // Update payment
            $pdo->prepare("UPDATE cms_payments SET status='completed', transaction_id=? WHERE order_id=?")
                ->execute([$verification['transaction_id'], $order['id']]);
            
            $pdo->prepare("UPDATE cms_orders SET status='processing' WHERE id=?")
                ->execute([$order['id']]);
            
            $success = true;
        } else {
            $error = $verification['error'] ?? 'Payment verification failed';
        }
    } else {
        $error = 'Payment reference not found';
    }
} elseif ($gateway === 'flutterwave') {
    $txRef = $_GET['tx_ref'] ?? '';
    if ($txRef) {
        $secretKey = $paymentConfig['secret_key'] ?? '';
        // Pass secretKey if available, otherwise let function get it from config/settings
        $verification = verifyFlutterwavePayment($txRef, $secretKey ?: null, $pdo);
        if ($verification['success']) {
            // Update payment
            $pdo->prepare("UPDATE cms_payments SET status='completed', transaction_id=? WHERE order_id=?")
                ->execute([$verification['transaction_id'], $order['id']]);
            
            $pdo->prepare("UPDATE cms_orders SET status='processing' WHERE id=?")
                ->execute([$order['id']]);
            
            $success = true;
        } else {
            $error = $verification['error'] ?? 'Payment verification failed';
        }
    } else {
        $error = 'Transaction reference not found';
    }
}

// Redirect based on result
if ($success) {
    header('Location: ' . $baseUrl . '/cms/payment?order=' . urlencode($orderNumber) . '&success=1');
} else {
    header('Location: ' . $baseUrl . '/cms/payment?order=' . urlencode($orderNumber) . '&error=' . urlencode($error ?? 'Payment failed'));
}
exit;

