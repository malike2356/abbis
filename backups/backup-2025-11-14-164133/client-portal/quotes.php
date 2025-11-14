<?php
/**
 * Client Portal - Quotes
 */
require_once __DIR__ . '/auth-check.php';

$pageTitle = 'My Quotes';

// Get quotes
$quotes = [];
try {
    if ($clientId) {
        $stmt = $pdo->prepare("
            SELECT id, quote_number, total_amount, status, created_at, valid_until
            FROM client_quotes 
            WHERE client_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$clientId]);
        $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Quotes fetch error: ' . $e->getMessage());
}

include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="page-header">
        <h1><?php echo $pageTitle; ?></h1>
        <p>View and manage your quotes</p>
    </div>

    <?php if (empty($quotes)): ?>
        <div class="empty-state-card">
            <p>No quotes found. Quotes will appear here once they are sent to you.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Quote Number</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Valid Until</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($quote['quote_number']); ?></strong></td>
                            <td><?php echo number_format($quote['total_amount'], 2); ?> GHS</td>
                            <td><span class="badge badge-<?php echo $quote['status']; ?>"><?php echo ucfirst($quote['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($quote['created_at'])); ?></td>
                            <td><?php echo $quote['valid_until'] ? date('M d, Y', strtotime($quote['valid_until'])) : 'N/A'; ?></td>
                            <td><a href="quote-detail.php?id=<?php echo $quote['id']; ?>" class="btn-link">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.table-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: var(--bg);
}

.data-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--text-light);
    text-transform: uppercase;
}

.data-table td {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
}

.empty-state-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 48px;
    text-align: center;
    color: var(--text-light);
}
</style>

<?php include __DIR__ . '/footer.php'; ?>

