<?php
/**
 * View Purchase Order
 */
$page_title = 'Purchase Order';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$pdo = getDBConnection();

$poId = intval($_GET['id'] ?? 0);

if (!$poId) {
    header('Location: suppliers.php');
    exit;
}

// Get purchase order
try {
    $po = $pdo->prepare("
        SELECT po.*, u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN users u ON u.id = po.created_by
        WHERE po.id = ?
    ");
    $po->execute([$poId]);
    $purchaseOrder = $po->fetch(PDO::FETCH_ASSOC);
    
    if (!$purchaseOrder) {
        header('Location: suppliers.php');
        exit;
    }
    
    // Get items
    $items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id");
    $items->execute([$poId]);
    $orderItems = $items->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: suppliers.php');
    exit;
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>📋 Purchase Order #<?php echo htmlspecialchars($purchaseOrder['po_number']); ?></h1>
                <p>Status: <strong style="color: <?php 
                    $statusColors = [
                        'draft' => '#64748b',
                        'pending' => '#f59e0b',
                        'approved' => '#10b981',
                        'ordered' => '#0ea5e9',
                        'received' => '#10b981',
                        'cancelled' => '#ef4444'
                    ];
                    echo $statusColors[$purchaseOrder['status']] ?? '#64748b';
                ?>;"><?php echo strtoupper($purchaseOrder['status']); ?></strong></p>
            </div>
            <div>
                <a href="purchase-orders.php" class="btn btn-outline">← Back to List</a>
                <a href="purchase-order-draft.php?supplier=<?php echo urlencode($purchaseOrder['supplier_name']); ?>" class="btn btn-primary">Create Another PO</a>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h2>Supplier Information</h2>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
            <div>
                <strong>Supplier Name:</strong><br>
                <?php echo htmlspecialchars($purchaseOrder['supplier_name']); ?>
            </div>
            <?php if ($purchaseOrder['supplier_contact']): ?>
            <div>
                <strong>Contact:</strong><br>
                <?php echo htmlspecialchars($purchaseOrder['supplier_contact']); ?>
            </div>
            <?php endif; ?>
            <?php if ($purchaseOrder['supplier_email']): ?>
            <div>
                <strong>Email:</strong><br>
                <a href="mailto:<?php echo htmlspecialchars($purchaseOrder['supplier_email']); ?>">
                    <?php echo htmlspecialchars($purchaseOrder['supplier_email']); ?>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($purchaseOrder['supplier_address']): ?>
            <div>
                <strong>Address:</strong><br>
                <?php echo nl2br(htmlspecialchars($purchaseOrder['supplier_address'])); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-card">
        <h2>Order Items</h2>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></td>
                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                            <td>GHS <?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><strong>GHS <?php echo number_format($item['total_price'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: var(--bg); font-weight: 600;">
                        <td colspan="4" style="text-align: right;">Total Amount:</td>
                        <td style="font-size: 1.25rem; color: var(--primary);">
                            GHS <?php echo number_format($purchaseOrder['total_amount'], 2); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($purchaseOrder['notes']): ?>
    <div class="dashboard-card">
        <h2>Notes</h2>
        <p><?php echo nl2br(htmlspecialchars($purchaseOrder['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="dashboard-card">
        <h2>Order Information</h2>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
            <div>
                <strong>PO Number:</strong><br>
                <?php echo htmlspecialchars($purchaseOrder['po_number']); ?>
            </div>
            <div>
                <strong>Created By:</strong><br>
                <?php echo htmlspecialchars($purchaseOrder['created_by_name'] ?? 'System'); ?>
            </div>
            <div>
                <strong>Created Date:</strong><br>
                <?php echo date('F j, Y g:i A', strtotime($purchaseOrder['created_at'])); ?>
            </div>
            <div>
                <strong>Last Updated:</strong><br>
                <?php echo date('F j, Y g:i A', strtotime($purchaseOrder['updated_at'])); ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
