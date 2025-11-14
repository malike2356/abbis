<?php
/**
 * Client Portal - Invoices
 */
require_once __DIR__ . '/auth-check.php';

$pageTitle = 'My Invoices';

// Get invoices
$invoices = [];
try {
    if ($clientId) {
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, total_amount, balance_due, status, issue_date, due_date
            FROM client_invoices 
            WHERE client_id = ?
            ORDER BY issue_date DESC
        ");
        $stmt->execute([$clientId]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Invoices fetch error: ' . $e->getMessage());
}

include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="page-header">
        <h1><?php echo $pageTitle; ?></h1>
        <p>View and pay your invoices</p>
    </div>

    <?php if (empty($invoices)): ?>
        <div class="empty-state-card">
            <p>No invoices found. Invoices will appear here once they are issued to you.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Total Amount</th>
                        <th>Balance Due</th>
                        <th>Status</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                            <td><?php echo number_format($invoice['total_amount'], 2); ?> GHS</td>
                            <td><?php echo number_format($invoice['balance_due'], 2); ?> GHS</td>
                            <td><span class="badge badge-<?php echo $invoice['status']; ?>"><?php echo ucfirst($invoice['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['issue_date'])); ?></td>
                            <td><?php echo $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></td>
                            <td>
                                <a href="invoice-detail.php?id=<?php echo $invoice['id']; ?>" class="btn-link">View</a>
                                <?php if ($invoice['balance_due'] > 0 && in_array($invoice['status'], ['sent', 'viewed', 'partial', 'overdue'])): ?>
                                    <a href="payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn-link" style="margin-left: 8px;">Pay</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

