<?php
/**
 * Client Portal - Payment Gateway Callback
 */
require_once __DIR__ . '/auth-check.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/cms/public/payment-gateways.php'; // Shared payment gateway functions
require_once $rootPath . '/includes/ClientPortal/ClientPaymentService.php';

$paymentId = isset($_GET['payment']) ? (int)$_GET['payment'] : 0;
$gateway = strtolower($_GET['gateway'] ?? '');

if ($paymentId <= 0 || !in_array($gateway, ['paystack', 'flutterwave'], true)) {
    $_SESSION['client_payment_error'] = 'Invalid payment response.';
    redirect('payments.php');
}

try {
    $service = new ClientPaymentService($pdo);

    if ($gateway === 'paystack') {
        $reference = $_GET['reference'] ?? '';
        if (!$reference) {
            throw new RuntimeException('Missing payment reference.');
        }
        $verification = verifyPaystackPayment($reference, null, $pdo);
        if (empty($verification['success'])) {
            throw new RuntimeException($verification['error'] ?? 'Unable to verify Paystack payment.');
        }
        $service->completeGatewayPayment($paymentId, 'paystack', $verification['transaction_id'] ?? $reference, $verification, $userId);
        $_SESSION['client_payment_success'] = 'Payment completed successfully.';
    } elseif ($gateway === 'flutterwave') {
        $txRef = $_GET['tx_ref'] ?? '';
        if (!$txRef) {
            throw new RuntimeException('Missing transaction reference.');
        }
        $verification = verifyFlutterwavePayment($txRef, null, $pdo);
        if (empty($verification['success'])) {
            throw new RuntimeException($verification['error'] ?? 'Unable to verify Flutterwave payment.');
        }
        $service->completeGatewayPayment($paymentId, 'flutterwave', $verification['transaction_id'] ?? $txRef, $verification, $userId);
        $_SESSION['client_payment_success'] = 'Payment completed successfully.';
    }
} catch (Throwable $e) {
    error_log('Client payment callback error: ' . $e->getMessage());
    $_SESSION['client_payment_error'] = $e->getMessage() ?: 'Payment verification failed.';
    try {
        if (isset($service) && $paymentId > 0) {
            $service->markGatewayPaymentFailed($paymentId, $e->getMessage());
        }
    } catch (Throwable $inner) {
        // Ignore secondary failures
    }
}

redirect('payments.php');
