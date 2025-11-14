<?php
/**
 * Client Portal - Payments
 */
require_once __DIR__ . '/auth-check.php';

$pageTitle = 'Make Payment';

// Get outstanding invoices for payment
$outstandingInvoices = [];
try {
    if ($clientId) {
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, balance_due, due_date
            FROM client_invoices 
            WHERE client_id = ? AND balance_due > 0 AND status IN ('sent', 'viewed', 'partial', 'overdue')
            ORDER BY due_date ASC
        ");
        $stmt->execute([$clientId]);
        $outstandingInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Outstanding invoices error: ' . $e->getMessage());
}

// Get payment history
$paymentHistory = [];
try {
    if ($clientId) {
        $stmt = $pdo->prepare("
            SELECT cp.id, cp.payment_reference, cp.amount, cp.payment_method, cp.payment_status,
                   cp.payment_date, cp.invoice_id, ci.invoice_number
            FROM client_payments cp
            LEFT JOIN client_invoices ci ON ci.id = cp.invoice_id
            WHERE cp.client_id = ?
            ORDER BY cp.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$clientId]);
        $paymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Payment history error: ' . $e->getMessage());
}

$paymentMethods = [];
try {
    $allowedProviders = ['paystack', 'flutterwave', 'mobile_money', 'bank_transfer', 'cash'];
    $stmt = $pdo->query("
        SELECT id, name, provider
        FROM cms_payment_methods
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $method) {
        $providerKey = strtolower($method['provider'] ?? '');
        if (in_array($providerKey, $allowedProviders, true)) {
            $paymentMethods[] = $method;
        }
    }
} catch (PDOException $e) {
    error_log('Client portal payment methods error: ' . $e->getMessage());
}

$selectedInvoiceId = $_GET['invoice_id'] ?? null;
$successMessage = $_SESSION['client_payment_success'] ?? '';
$errorMessage = $_SESSION['client_payment_error'] ?? '';
unset($_SESSION['client_payment_success'], $_SESSION['client_payment_error']);

if (isset($_GET['cancelled'])) {
    $errorMessage = 'Payment was cancelled before completion.';
}

include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="page-header">
        <h1><?php echo $pageTitle; ?></h1>
        <p>Pay your outstanding invoices online</p>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <div class="payment-grid">
        <div class="payment-card">
            <div class="card-header">
                <h2>Pay Invoice</h2>
            </div>
            <div class="card-content">
                <?php if (empty($outstandingInvoices)): ?>
                    <p class="empty-state">No outstanding invoices to pay.</p>
                <?php elseif (empty($paymentMethods)): ?>
                    <p class="empty-state">
                        Online payments are not available right now. Please contact support to arrange payment.
                    </p>
                <?php else: ?>
                    <form method="POST" action="process-payment.php" id="paymentForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <div class="form-group">
                            <label class="form-label">Select Invoice</label>
                            <select name="invoice_id" class="form-control" required>
                                <option value="">-- Select Invoice --</option>
                                <?php foreach ($outstandingInvoices as $inv): ?>
                                    <option value="<?php echo $inv['id']; ?>" <?php echo $selectedInvoiceId == $inv['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($inv['invoice_number']); ?> - 
                                        <?php echo number_format($inv['balance_due'], 2); ?> GHS
                                        <?php if ($inv['due_date']): ?>
                                            (Due: <?php echo date('M d, Y', strtotime($inv['due_date'])); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Amount (GHS)</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                            <small class="form-text">Enter the amount you wish to pay</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method_id" class="form-control" required>
                                <option value="">-- Select Method --</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <?php $providerLabel = ucfirst(str_replace('_', ' ', $method['provider'])); ?>
                                    <option value="<?php echo (int)$method['id']; ?>">
                                        <?php echo htmlspecialchars($method['name'] ?: $providerLabel); ?>
                                        (<?php echo htmlspecialchars($providerLabel); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text">Secure online payments are handled by our trusted partners.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Add reference or instructions if needed"></textarea>
                        </div>

                        <button type="submit" class="btn-primary">Proceed to Payment</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="payment-card">
            <div class="card-header">
                <h2>Payment History</h2>
            </div>
            <div class="card-content">
                <?php if (empty($paymentHistory)): ?>
                    <p class="empty-state">No payment history yet.</p>
                <?php else: ?>
                    <div class="list-items">
                        <?php foreach ($paymentHistory as $payment): ?>
                            <div class="list-item">
                                <div class="item-main">
                                    <strong><?php echo htmlspecialchars($payment['payment_reference']); ?></strong>
                                    <span class="badge badge-<?php echo $payment['payment_status']; ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </div>
                                <div class="item-meta">
                                    <span><?php echo number_format($payment['amount'], 2); ?> GHS</span>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                                    <?php if (!empty($payment['invoice_number'])): ?>
                                        <span>Invoice <?php echo htmlspecialchars($payment['invoice_number']); ?></span>
                                    <?php elseif (!empty($payment['invoice_id'])): ?>
                                        <span>Invoice #<?php echo (int)$payment['invoice_id']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_date']): ?>
                                        <span><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.payment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
}
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
}
.alert-success {
    background: #c6f6d5;
    color: #22543d;
    border: 1px solid #9ae6b4;
}
.alert-error {
    background: #fed7d7;
    color: #c53030;
    border: 1px solid #fc8181;
}
</style>

<?php include __DIR__ . '/footer.php'; ?>

