<?php
/**
 * Client Portal - Process Payment
 */
require_once __DIR__ . '/auth-check.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/ClientPortal/ClientPaymentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('payments.php');
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['client_payment_error'] = 'Invalid security token. Please try again.';
    redirect('payments.php');
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($invoiceId <= 0 || $paymentMethodId <= 0 || $amount <= 0) {
    $_SESSION['client_payment_error'] = 'Please select an invoice, payment method, and enter a valid amount.';
    redirect('payments.php');
}

try {
    $service = new ClientPaymentService($pdo);
    $portalBaseUrl = app_url('client-portal');
    $result = $service->initiatePayment($clientId, $invoiceId, $amount, $paymentMethodId, $notes, $userId, $portalBaseUrl);

    if ($result['status'] === 'gateway' && !empty($result['redirect'])) {
        $_SESSION['client_payment_success'] = 'Redirecting to secure payment page...';
        header('Location: ' . $result['redirect']);
        exit;
    }

    if ($result['status'] === 'pending') {
        $_SESSION['client_payment_success'] = $result['message'] ?? 'Payment recorded successfully.';
    } else {
        $_SESSION['client_payment_error'] = $result['message'] ?? 'Unable to initiate payment.';
    }
} catch (Throwable $e) {
    error_log('Client portal payment error: ' . $e->getMessage());
    $_SESSION['client_payment_error'] = 'An unexpected error occurred while processing your payment.';
}

redirect('payments.php');
