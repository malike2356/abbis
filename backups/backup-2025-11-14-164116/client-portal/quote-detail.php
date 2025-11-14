<?php
/**
 * Client Portal - Quote Detail
 */
require_once __DIR__ . '/auth-check.php';

$quoteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quoteId <= 0) {
    redirect('quotes.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT q.*, c.client_name, c.email AS client_email
        FROM client_quotes q
        JOIN clients c ON c.id = q.client_id
        WHERE q.id = ? AND q.client_id = ?
        LIMIT 1
    ");
    $stmt->execute([$quoteId, $clientId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quote = null;
}

if (!$quote) {
    $_SESSION['client_payment_error'] = 'Quote not found or unavailable.';
    redirect('quotes.php');
}

// Mark as viewed
try {
    $pdo->prepare("UPDATE client_quotes SET client_portal_viewed_at = NOW() WHERE id = ? AND client_id = ?")
        ->execute([$quoteId, $clientId]);
    $quote['client_portal_viewed_at'] = $quote['client_portal_viewed_at'] ?? date('Y-m-d H:i:s');
} catch (PDOException $e) {
    // ignore
}

try {
    $itemsStmt = $pdo->prepare("
        SELECT item_description, quantity, unit_price, total, item_type
        FROM quote_items
        WHERE quote_id = ?
        ORDER BY id ASC
    ");
    $itemsStmt->execute([$quoteId]);
    $quoteItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quoteItems = [];
}

$subtotal = 0.0;
foreach ($quoteItems as $item) {
    $subtotal += floatval($item['total'] ?? 0);
}

$taxAmount = floatval($quote['tax_amount'] ?? 0);
$totalAmount = floatval($quote['total_amount'] ?? $subtotal + $taxAmount);
$createdAt = $quote['created_at'] ? new DateTime($quote['created_at']) : null;
$validUntil = $quote['valid_until'] ? new DateTime($quote['valid_until']) : null;
$status = strtolower($quote['status'] ?? 'draft');
$statusLabel = ucfirst($status);

$responseMessage = $_SESSION['client_quote_notice'] ?? '';
$responseError = $_SESSION['client_quote_error'] ?? '';
unset($_SESSION['client_quote_notice'], $_SESSION['client_quote_error']);

$pageTitle = 'Quote Details';
include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="detail-header">
        <div>
            <h1><?php echo htmlspecialchars($quote['quote_number'] ?? 'Quote #' . $quote['id']); ?></h1>
            <p>Review pricing, services, and expiration details for this quote.</p>
        </div>
        <a href="quotes.php" class="btn-link" style="font-size:14px;">← Back to Quotes</a>
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
                <span class="detail-label">Client Response</span>
                <span class="detail-value">
                    <?php
                    $clientResponse = $quote['client_response'] ?? 'pending';
                    echo ucfirst($clientResponse);
                    if (!empty($quote['client_response_at'])) {
                        echo ' · ' . date('M d, Y g:i A', strtotime($quote['client_response_at']));
                    }
                    ?>
                </span>
                <?php if (!empty($quote['client_signature_name'])): ?>
                    <div style="margin-top:4px; color: var(--text-light); font-size:13px;">
                        Signed by <?php echo htmlspecialchars($quote['client_signature_name']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($quote['client_response_note'])): ?>
                    <p class="detail-notes" style="margin-top:8px;"><?php echo nl2br(htmlspecialchars($quote['client_response_note'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="detail-card-section">
                <span class="detail-label">Issued</span>
                <span class="detail-value">
                    <?php echo $createdAt ? $createdAt->format('M d, Y') : 'N/A'; ?>
                </span>
            </div>
            <div class="detail-card-section">
                <span class="detail-label">Valid Until</span>
                <span class="detail-value">
                    <?php
                    if ($validUntil) {
                        echo $validUntil->format('M d, Y');
                        $today = new DateTime('today');
                        if ($validUntil < $today) {
                            echo ' <span class="badge badge-overdue">Expired</span>';
                        }
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </span>
            </div>
            <?php if (!empty($quote['notes'])): ?>
                <div class="detail-card-section">
                    <span class="detail-label">Notes</span>
                    <p class="detail-notes"><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($quote['quote_request_id'])): ?>
                <div class="detail-card-section">
                    <span class="detail-label">Linked Request</span>
                    <span class="detail-value">Request ID: <?php echo (int)$quote['quote_request_id']; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-card">
            <h2 class="detail-card-title">Summary</h2>
            <div class="summary-row">
                <span>Subtotal</span>
                <span>GHS <?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Tax</span>
                <span>GHS <?php echo number_format($taxAmount, 2); ?></span>
            </div>
            <div class="summary-total">
                <span>Total Quote</span>
                <span>GHS <?php echo number_format($totalAmount, 2); ?></span>
            </div>
        </div>
    </div>

    <div class="table-container" style="margin-top: 32px;">
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
                <?php if (empty($quoteItems)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:24px; color:#64748b;">
                            No line items were included in this quote.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quoteItems as $item): ?>
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

    <?php if (!empty($quote['status']) && in_array($quote['status'], ['accepted', 'converted'], true)): ?>
        <div class="info-banner success">
            <strong>Next Steps:</strong> This quote has been approved. You can track project progress under the Projects tab.
        </div>
    <?php elseif ($validUntil && $validUntil < new DateTime('today')): ?>
        <div class="info-banner warning">
            <strong>Action Needed:</strong> This quote has expired. Please contact our team to renew pricing.
        </div>
    <?php endif; ?>

    <?php if (($quote['client_response'] ?? 'pending') === 'pending' && ($status === 'sent' || $status === 'draft' || $status === 'pending')): ?>
        <div class="detail-card" style="margin-top:32px;">
            <h2 class="detail-card-title">Approve or Decline</h2>
            <p style="color: var(--text-light); font-size:14px; margin-bottom:16px;">
                Ready to proceed? Confirm below and include any notes or clarifications for our team.
            </p>
            <form method="post" action="quote-approve.php" class="approval-form">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="quote_id" value="<?php echo (int)$quote['id']; ?>">
                <div class="form-group">
                    <label class="form-label">Add a note (optional)</label>
                    <textarea name="client_note" class="form-control" rows="3" placeholder="Add any comments or requested changes..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Type your name to sign</label>
                    <input type="text" name="signature_name" class="form-control" placeholder="Full name" required>
                </div>
                <div class="approval-actions">
                    <button type="submit" name="action" value="accept" class="btn-primary">
                        ✅ Accept Quote
                    </button>
                    <button type="submit" name="action" value="decline" class="btn-secondary" style="background:#fee2e2;color:#991b1b;">
                        ✖ Decline Quote
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="detail-actions" style="margin-top:24px;">
        <a href="download.php?type=quote&id=<?php echo (int)$quote['id']; ?>" class="btn-link">
            ⬇️ Download Quote
        </a>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

