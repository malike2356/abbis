<?php
/**
 * Client Portal - Document download handler
 */
require_once __DIR__ . '/auth-check.php';

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!in_array($type, ['quote', 'invoice'], true) || $id <= 0) {
    redirect('dashboard.php');
}

$filename = '';
$content = '';

if ($type === 'quote') {
    $stmt = $pdo->prepare("
        SELECT q.*, c.client_name, c.email AS client_email
        FROM client_quotes q
        JOIN clients c ON c.id = q.client_id
        WHERE q.id = ? AND q.client_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $clientId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        $_SESSION['client_quote_error'] = 'Quote not found.';
        redirect('quotes.php');
    }

    $itemsStmt = $pdo->prepare("
        SELECT item_description, quantity, unit_price, total, item_type
        FROM quote_items
        WHERE quote_id = ?
        ORDER BY id ASC
    ");
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += floatval($item['total'] ?? 0);
    }
    $tax = floatval($quote['tax_amount'] ?? 0);
    $total = floatval($quote['total_amount'] ?? ($subtotal + $tax));

    $filename = 'quote-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $quote['quote_number'] ?? $id) . '.html';
    $content = renderQuoteDocument($quote, $items, $subtotal, $tax, $total);

    // Track download
    try {
        $pdo->prepare("UPDATE client_quotes SET client_portal_downloaded_at = NOW() WHERE id = ?")->execute([$id]);
        logPortalActivity($pdo, $clientId, $userId, 'quote_download', 'Downloaded quote ' . ($quote['quote_number'] ?? $id));
    } catch (PDOException $e) {
        // ignore
    }
} else {
    $stmt = $pdo->prepare("
        SELECT i.*, c.client_name, c.email AS client_email, q.quote_number
        FROM client_invoices i
        JOIN clients c ON c.id = i.client_id
        LEFT JOIN client_quotes q ON q.id = i.quote_id
        WHERE i.id = ? AND i.client_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $clientId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        $_SESSION['client_invoice_error'] = 'Invoice not found.';
        redirect('invoices.php');
    }

    $itemsStmt = $pdo->prepare("
        SELECT item_description, quantity, unit_price, total, item_type
        FROM invoice_items
        WHERE invoice_id = ?
        ORDER BY id ASC
    ");
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentsStmt = $pdo->prepare("
        SELECT payment_reference, amount, payment_method, payment_status, payment_date
        FROM client_payments
        WHERE invoice_id = ?
        ORDER BY created_at ASC
    ");
    $paymentsStmt->execute([$id]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += floatval($item['total'] ?? 0);
    }
    $tax = floatval($invoice['tax_amount'] ?? 0);
    $total = floatval($invoice['total_amount'] ?? ($subtotal + $tax));
    $paid = floatval($invoice['amount_paid'] ?? 0);
    $balance = floatval($invoice['balance_due'] ?? ($total - $paid));

    $filename = 'invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $invoice['invoice_number'] ?? $id) . '.html';
    $content = renderInvoiceDocument($invoice, $items, $payments, $subtotal, $tax, $total, $paid, $balance);

    try {
        $pdo->prepare("UPDATE client_invoices SET client_portal_downloaded_at = NOW() WHERE id = ?")->execute([$id]);
        logPortalActivity($pdo, $clientId, $userId, 'invoice_download', 'Downloaded invoice ' . ($invoice['invoice_number'] ?? $id));
    } catch (PDOException $e) {
        // ignore
    }
}

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $content;
exit;

function getCompanyDetails(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('company_name','company_address','company_contact','company_email')");
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[$row['config_key']] = $row['config_value'];
        }
        return $data;
    } catch (PDOException $e) {
        return [];
    }
}

function renderQuoteDocument(array $quote, array $items, float $subtotal, float $tax, float $total): string
{
    global $pdo;
    $company = getCompanyDetails($pdo);
    $quoteNumber = $quote['quote_number'] ?? ('Quote #' . $quote['id']);
    $issued = $quote['created_at'] ? date('M d, Y', strtotime($quote['created_at'])) : 'N/A';
    $validUntil = $quote['valid_until'] ? date('M d, Y', strtotime($quote['valid_until'])) : 'N/A';
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($quoteNumber); ?> - <?php echo htmlspecialchars($company['company_name'] ?? APP_NAME); ?></title>
    <style>
        body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; margin:0; padding:32px; background:#f8fafc; color:#0f172a; }
        .document { max-width: 780px; margin: 0 auto; background:#fff; border-radius:16px; padding:32px; border:1px solid #e2e8f0; box-shadow:0 20px 45px rgba(15,23,42,0.08); }
        h1 { margin-top:0; font-size:28px; }
        .company { margin-bottom:24px; }
        .meta { display:flex; justify-content:space-between; flex-wrap:wrap; margin-bottom:24px; }
        table { width:100%; border-collapse:collapse; margin-top:16px; }
        th, td { padding:12px 14px; border-bottom:1px solid #e2e8f0; font-size:14px; }
        th { text-transform:uppercase; font-size:12px; color:#64748b; letter-spacing:0.08em; }
        .totals { margin-top:24px; width:280px; float:right; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; text-transform:uppercase; letter-spacing:0.08em; background:#e2e8f0; color:#1e293b; }
        .notes { margin-top:24px; background:#f1f5f9; padding:16px; border-radius:12px; }
    </style>
</head>
<body>
    <div class="document">
        <div class="company">
            <h1><?php echo htmlspecialchars($company['company_name'] ?? APP_NAME); ?></h1>
            <?php if (!empty($company['company_address'])): ?>
                <div><?php echo nl2br(htmlspecialchars($company['company_address'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($company['company_contact'])): ?>
                <div><?php echo htmlspecialchars($company['company_contact']); ?></div>
            <?php endif; ?>
            <?php if (!empty($company['company_email'])): ?>
                <div><?php echo htmlspecialchars($company['company_email']); ?></div>
            <?php endif; ?>
        </div>
        <div class="meta">
            <div>
                <strong>Quote</strong><br>
                <?php echo htmlspecialchars($quoteNumber); ?><br>
                <span class="badge"><?php echo htmlspecialchars(ucfirst($quote['status'] ?? 'draft')); ?></span>
            </div>
            <div>
                <strong>Issued</strong><br>
                <?php echo htmlspecialchars($issued); ?>
            </div>
            <div>
                <strong>Valid Until</strong><br>
                <?php echo htmlspecialchars($validUntil); ?>
            </div>
        </div>
        <div>
            <strong>Prepared For</strong><br>
            <?php echo htmlspecialchars($quote['client_name'] ?? ''); ?><br>
            <?php if (!empty($quote['client_email'])): ?>
                <?php echo htmlspecialchars($quote['client_email']); ?><br>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Unit Price (GHS)</th>
                    <th style="text-align:right;">Total (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:24px; color:#475569;">No line items have been added to this quote.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($item['item_type'] ?? 'service')); ?></td>
                            <td style="text-align:right;"><?php echo number_format(floatval($item['quantity'] ?? 0), 2); ?></td>
                            <td style="text-align:right;"><?php echo number_format(floatval($item['unit_price'] ?? 0), 2); ?></td>
                            <td style="text-align:right;"><?php echo number_format(floatval($item['total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="totals">
            <table>
                <tr><td>Subtotal</td><td style="text-align:right;">GHS <?php echo number_format($subtotal, 2); ?></td></tr>
                <tr><td>Tax</td><td style="text-align:right;">GHS <?php echo number_format($tax, 2); ?></td></tr>
                <tr><th>Total</th><th style="text-align:right;">GHS <?php echo number_format($total, 2); ?></th></tr>
            </table>
        </div>
        <div style="clear:both;"></div>
        <?php if (!empty($quote['notes'])): ?>
            <div class="notes">
                <strong>Notes</strong>
                <div><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

function renderInvoiceDocument(array $invoice, array $items, array $payments, float $subtotal, float $tax, float $total, float $paid, float $balance): string
{
    global $pdo;
    $company = getCompanyDetails($pdo);
    $invoiceNumber = $invoice['invoice_number'] ?? ('Invoice #' . $invoice['id']);
    $issueDate = $invoice['issue_date'] ? date('M d, Y', strtotime($invoice['issue_date'])) : 'N/A';
    $dueDate = $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A';
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($invoiceNumber); ?> - <?php echo htmlspecialchars($company['company_name'] ?? APP_NAME); ?></title>
    <style>
        body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; margin:0; padding:32px; background:#f8fafc; color:#0f172a; }
        .document { max-width: 780px; margin: 0 auto; background:#fff; border-radius:16px; padding:32px; border:1px solid #e2e8f0; box-shadow:0 20px 45px rgba(15,23,42,0.08); }
        h1 { margin-top:0; font-size:28px; }
        .company { margin-bottom:24px; }
        .meta { display:flex; justify-content:space-between; flex-wrap:wrap; margin-bottom:24px; }
        table { width:100%; border-collapse:collapse; margin-top:16px; }
        th, td { padding:12px 14px; border-bottom:1px solid #e2e8f0; font-size:14px; }
        th { text-transform:uppercase; font-size:12px; color:#64748b; letter-spacing:0.08em; }
        .totals { margin-top:24px; width:280px; float:right; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; text-transform:uppercase; letter-spacing:0.08em; background:#e2e8f0; color:#1e293b; }
        .notes { margin-top:24px; background:#f1f5f9; padding:16px; border-radius:12px; }
        .payments { margin-top:32px; }
        .payments h2 { font-size:18px; margin:0 0 12px; }
    </style>
</head>
<body>
    <div class="document">
        <div class="company">
            <h1><?php echo htmlspecialchars($company['company_name'] ?? APP_NAME); ?></h1>
            <?php if (!empty($company['company_address'])): ?>
                <div><?php echo nl2br(htmlspecialchars($company['company_address'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($company['company_contact'])): ?>
                <div><?php echo htmlspecialchars($company['company_contact']); ?></div>
            <?php endif; ?>
            <?php if (!empty($company['company_email'])): ?>
                <div><?php echo htmlspecialchars($company['company_email']); ?></div>
            <?php endif; ?>
        </div>
        <div class="meta">
            <div>
                <strong>Invoice</strong><br>
                <?php echo htmlspecialchars($invoiceNumber); ?><br>
                <span class="badge"><?php echo htmlspecialchars(ucfirst($invoice['status'] ?? 'draft')); ?></span>
            </div>
            <div>
                <strong>Issued</strong><br>
                <?php echo htmlspecialchars($issueDate); ?>
            </div>
            <div>
                <strong>Due Date</strong><br>
                <?php echo htmlspecialchars($dueDate); ?>
            </div>
        </div>
        <div>
            <strong>Bill To</strong><br>
            <?php echo htmlspecialchars($invoice['client_name'] ?? ''); ?><br>
            <?php if (!empty($invoice['client_email'])): ?>
                <?php echo htmlspecialchars($invoice['client_email']); ?><br>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Unit Price (GHS)</th>
                    <th style="text-align:right;">Total (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:24px; color:#475569;">No line items have been added to this invoice.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($item['item_type'] ?? 'service')); ?></td>
                            <td style="text-align:right;"><?php echo number_format(floatval($item['quantity'] ?? 0), 2); ?></td>
                            <td style="text-align:right;"><?php echo number_format(floatval($item['unit_price'] ?? 0), 2); ?></td>
                            <td style="text-align:right;"><?php echo number_format(floatval($item['total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="totals">
            <table>
                <tr><td>Subtotal</td><td style="text-align:right;">GHS <?php echo number_format($subtotal, 2); ?></td></tr>
                <tr><td>Tax</td><td style="text-align:right;">GHS <?php echo number_format($tax, 2); ?></td></tr>
                <tr><td>Payments</td><td style="text-align:right;">GHS <?php echo number_format($paid, 2); ?></td></tr>
                <tr><th>Balance Due</th><th style="text-align:right;">GHS <?php echo number_format($balance, 2); ?></th></tr>
            </table>
        </div>
        <div style="clear:both;"></div>
        <?php if (!empty($invoice['notes'])): ?>
            <div class="notes">
                <strong>Notes</strong>
                <div><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></div>
            </div>
        <?php endif; ?>

        <div class="payments">
            <h2>Payment History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th style="text-align:right;">Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:16px; color:#64748b;">No payments have been recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : 'Pending'; ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_reference']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($payment['payment_status'])); ?></td>
                                <td style="text-align:right;"><?php echo number_format(floatval($payment['amount'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

function logPortalActivity(PDO $pdo, int $clientId, int $userId, string $type, string $description): void
{
    try {
        $pdo->prepare("
            INSERT INTO client_portal_activities (client_id, user_id, activity_type, activity_description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $clientId,
            $userId,
            $type,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // ignore
    }
}

