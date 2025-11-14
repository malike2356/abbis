<?php
/**
 * Client Portal - Invoice Detail
 */
require_once __DIR__ . '/auth-check.php';

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoiceId <= 0) {
    redirect('invoices.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT i.*, c.client_name, c.email AS client_email, q.quote_number
        FROM client_invoices i
        JOIN clients c ON c.id = i.client_id
        LEFT JOIN client_quotes q ON q.id = i.quote_id
        WHERE i.id = ? AND i.client_id = ?
        LIMIT 1
    ");
    $stmt->execute([$invoiceId, $clientId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoice = null;
}

if (!$invoice) {
    $_SESSION['client_payment_error'] = 'Invoice not found or unavailable.';
    redirect('invoices.php');
}

// Mark as viewed
try {
    $pdo->prepare("UPDATE client_invoices SET client_portal_viewed_at = NOW() WHERE id = ? AND client_id = ?")
        ->execute([$invoiceId, $clientId]);
    $invoice['client_portal_viewed_at'] = $invoice['client_portal_viewed_at'] ?? date('Y-m-d H:i:s');
} catch (PDOException $e) {
    // ignore
}

try {
    $itemsStmt = $pdo->prepare("
        SELECT item_description, quantity, unit_price, total, item_type
        FROM invoice_items
        WHERE invoice_id = ?
        ORDER BY id ASC
    ");
    $itemsStmt->execute([$invoiceId]);
    $invoiceItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoiceItems = [];
}

try {
    $paymentsStmt = $pdo->prepare("
        SELECT id, payment_reference, amount, payment_method, payment_status, payment_date, gateway_provider
        FROM client_payments
        WHERE invoice_id = ?
        ORDER BY created_at DESC
    ");
    $paymentsStmt->execute([$invoiceId]);
    $invoicePayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoicePayments = [];
}

$subtotal = 0.0;
foreach ($invoiceItems as $item) {
    $subtotal += floatval($item['total'] ?? 0);
}

$taxAmount = floatval($invoice['tax_amount'] ?? 0);
$totalAmount = floatval($invoice['total_amount'] ?? $subtotal + $taxAmount);
$amountPaid = floatval($invoice['amount_paid'] ?? 0);
$balanceDue = floatval($invoice['balance_due'] ?? ($totalAmount - $amountPaid));
$status = strtolower($invoice['status'] ?? 'draft');
$statusLabel = ucfirst($status);
$issueDate = $invoice['issue_date'] ? new DateTime($invoice['issue_date']) : null;
$dueDate = $invoice['due_date'] ? new DateTime($invoice['due_date']) : null;

$responseMessage = $_SESSION['client_invoice_notice'] ?? '';
$responseError = $_SESSION['client_invoice_error'] ?? '';
unset($_SESSION['client_invoice_notice'], $_SESSION['client_invoice_error']);

$pageTitle = 'Invoice Details';
include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="detail-header">
        <div>
            <h1><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'Invoice #' . $invoice['id']); ?></h1>
            <p>See charges, payments, and outstanding balances for this invoice.</p>
        </div>
        <div class="detail-actions">
            <a href="invoices.php" class="btn-link" style="font-size:14px;">← Back to Invoices</a>
            <?php if ($balanceDue > 0.01): ?>
                <a href="payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn-primary" style="padding:10px 18px; font-size:14px;">
                    Pay Invoice
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($responseMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($responseMessage); ?></div>
    <?php endif; ?>
    <?php if ($responseError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($responseError); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-section">
                <span class="detail-label">Status</span>
                <span class="badge badge-<?php echo $status; ?>"><?php echo $statusLabel; ?></span>
            </div>
            <div class="detail-card-section">
                <span class="detail-label">Issued</span>
                <span class="detail-value">
                    <?php echo $issueDate ? $issueDate->format('M d, Y') : 'N/A'; ?>
                </span>
            </div>
            <div class="detail-card-section">
                <span class="detail-label">Due Date</span>
                <span class="detail-value">
                    <?php
                    if ($dueDate) {
                        echo $dueDate->format('M d, Y');
                        $today = new DateTime('today');
                        if ($balanceDue > 0.01 && $dueDate < $today) {
                            echo ' <span class="badge badge-overdue">Overdue</span>';
                        }
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </span>
            </div>
            <?php if (!empty($invoice['quote_number'])): ?>
                <div class="detail-card-section">
                    <span class="detail-label">Quote Reference</span>
                    <span class="detail-value">
                        <a href="quote-detail.php?id=<?php echo (int)$invoice['quote_id']; ?>" class="btn-link">
                            <?php echo htmlspecialchars($invoice['quote_number']); ?>
                        </a>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (!empty($invoice['field_report_id'])): ?>
                <div class="detail-card-section">
                    <span class="detail-label">Project Report</span>
                    <span class="detail-value">Report ID: <?php echo (int)$invoice['field_report_id']; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($invoice['notes'])): ?>
                <div class="detail-card-section">
                    <span class="detail-label">Notes</span>
                    <p class="detail-notes"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-card">
            <h2 class="detail-card-title">Balances</h2>
            <div class="summary-row">
                <span>Subtotal</span>
                <span>GHS <?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Tax</span>
                <span>GHS <?php echo number_format($taxAmount, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Payments Received</span>
                <span>GHS <?php echo number_format($amountPaid, 2); ?></span>
            </div>
            <div class="summary-total">
                <span>Balance Due</span>
                <span>GHS <?php echo number_format(max($balanceDue, 0), 2); ?></span>
            </div>
        </div>
    </div>

    <div class="table-container" style="margin-top: 32px;">
        <h2 style="margin:0 0 16px; font-size:18px; font-weight:600;">Invoice Items</h2>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price (GHS)</th>
                    <th class="text-right">Line Total (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoiceItems)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:24px; color:#64748b;">
                            Invoice line items have not been attached.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoiceItems as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($item['item_type'] ?? 'service')); ?></td>
                            <td class="text-right"><?php echo number_format(floatval($item['quantity'] ?? 0), 2); ?></td>
                            <td class="text-right"><?php echo number_format(floatval($item['unit_price'] ?? 0), 2); ?></td>
                            <td class="text-right"><?php echo number_format(floatval($item['total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container" style="margin-top: 32px;">
        <h2 style="margin:0 0 16px; font-size:18px; font-weight:600;">Payment History</h2>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th class="text-right">Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoicePayments)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:24px; color:#64748b;">
                            No payments recorded for this invoice yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoicePayments as $payment): ?>
                        <tr>
                            <td>
                                <?php
                                if (!empty($payment['payment_date'])) {
                                    echo date('M d, Y', strtotime($payment['payment_date']));
                                } else {
                                    echo 'Pending';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($payment['payment_reference']); ?></td>
                            <td>
                                <?php
                                $methodLabel = ucfirst(str_replace('_', ' ', $payment['payment_method']));
                                if (!empty($payment['gateway_provider']) && $payment['payment_status'] !== 'completed') {
                                    $methodLabel .= ' (' . ucfirst($payment['gateway_provider']) . ')';
                                }
                                echo htmlspecialchars($methodLabel);
                                ?>
                            </td>
                            <td><span class="badge badge-<?php echo $payment['payment_status']; ?>"><?php echo ucfirst($payment['payment_status']); ?></span></td>
                            <td class="text-right"><?php echo number_format(floatval($payment['amount'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($balanceDue > 0.01 && $status !== 'cancelled'): ?>
        <div class="info-banner warning">
            <strong>Reminder:</strong> An outstanding balance of GHS <?php echo number_format($balanceDue, 2); ?> remains on this invoice.
            <?php if ($dueDate && $dueDate < new DateTime('today')): ?>
                Please pay as soon as possible to avoid service delays.
            <?php endif; ?>
        </div>
    <?php elseif ($balanceDue <= 0.01): ?>
        <div class="info-banner success">
            <strong>Thank you!</strong> This invoice is fully paid. A receipt has been recorded in your payment history.
        </div>
    <?php endif; ?>

    <div class="detail-actions" style="margin-top:24px;">
        <a href="download.php?type=invoice&id=<?php echo (int)$invoice['id']; ?>" class="btn-link">
            ⬇️ Download Invoice
        </a>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

