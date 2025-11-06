<?php
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Handle POST actions
$action = $_POST['action'] ?? '';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'update_status') {
            $orderId = $_POST['order_id'];
            $status = $_POST['status'];
            $pdo->prepare("UPDATE cms_orders SET status=? WHERE id=?")->execute([$status, $orderId]);
            $message = 'Order status updated successfully';
            header('Location: orders.php?id=' . $orderId);
            exit;
        } elseif ($action === 'update_order') {
            $orderId = $_POST['order_id'];
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerEmail = trim($_POST['customer_email'] ?? '');
            $customerPhone = trim($_POST['customer_phone'] ?? '');
            $customerAddress = trim($_POST['customer_address'] ?? '');
            $totalAmount = floatval($_POST['total_amount'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($customerName) || empty($customerEmail)) {
                $error = 'Customer name and email are required';
                // Stay in edit mode and preserve POST values
                $editMode = true;
                // Load order to get order_number, then update with POST values
                $tempStmt = $pdo->prepare("SELECT order_number FROM cms_orders WHERE id=?");
                $tempStmt->execute([$orderId]);
                $tempOrder = $tempStmt->fetch(PDO::FETCH_ASSOC);
                // Update orderDetail with POST values for form display
                $orderDetail = [
                    'id' => $orderId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'customer_address' => $customerAddress,
                    'total_amount' => $totalAmount,
                    'status' => $status,
                    'notes' => $notes,
                    'order_number' => $tempOrder['order_number'] ?? ''
                ];
            } else {
                $stmt = $pdo->prepare("UPDATE cms_orders SET customer_name=?, customer_email=?, customer_phone=?, customer_address=?, total_amount=?, status=?, notes=? WHERE id=?");
                $stmt->execute([$customerName, $customerEmail, $customerPhone, $customerAddress, $totalAmount, $status, $notes, $orderId]);
                $message = 'Order updated successfully';
                header('Location: orders.php?id=' . $orderId);
                exit;
            }
        } elseif ($action === 'delete_order') {
            $orderId = $_POST['order_id'];
            
            try {
                $pdo->beginTransaction();
                
                // Delete order items first (cascade should handle this, but being explicit)
                $pdo->prepare("DELETE FROM cms_order_items WHERE order_id=?")->execute([$orderId]);
                // Delete payments
                try {
                    $pdo->prepare("DELETE FROM cms_payments WHERE order_id=?")->execute([$orderId]);
                } catch (Exception $e) {
                    // Payments table might not exist or already deleted
                }
                // Delete order
                $pdo->prepare("DELETE FROM cms_orders WHERE id=?")->execute([$orderId]);
                
                $pdo->commit();
                $message = 'Order deleted successfully';
                header('Location: orders.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to delete order: ' . $e->getMessage();
            }
        } elseif ($action === 'quick_update_status') {
            $orderId = $_POST['order_id'];
            $status = $_POST['status'];
            $pdo->prepare("UPDATE cms_orders SET status=? WHERE id=?")->execute([$status, $orderId]);
            $message = 'Order status updated successfully';
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}

$orders = $pdo->query("SELECT * FROM cms_orders ORDER BY created_at DESC")->fetchAll();
$orderId = $_GET['id'] ?? null;
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';
// Only load orderDetail if not already set (e.g., from error handling above)
if ($orderId && !isset($orderDetail)) {
    $stmt = $pdo->prepare("SELECT * FROM cms_orders WHERE id=?");
    $stmt->execute([$orderId]);
    $orderDetail = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!$orderId) {
    $orderDetail = null;
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <style>
        .button-small {
            padding: 4px 8px !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
            height: auto !important;
            min-height: auto !important;
        }
        .button-delete {
            background: #d63638 !important;
            color: white !important;
            border: none !important;
        }
        .button-delete:hover {
            background: #b52729 !important;
        }
        .status-pending { color: #d63638; font-weight: 600; }
        .status-processing { color: #dba617; font-weight: 600; }
        .status-completed { color: #00a32a; font-weight: 600; }
        .status-cancelled { color: #646970; font-weight: 600; }
        select[name="status"] {
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            padding: 4px 8px;
        }
        .post-form .form-group {
            margin-bottom: 20px;
        }
        .post-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1e293b;
        }
    </style>
    <?php 
    $currentPage = 'orders';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Orders</h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <?php if ($orderDetail): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Order #<?php echo htmlspecialchars($orderDetail['order_number']); ?></h2>
                <div>
                    <?php if (!$editMode): ?>
                        <a href="?id=<?php echo $orderId; ?>&edit=1" class="button button-primary">Edit Order</a>
                    <?php endif; ?>
                    <a href="orders.php" class="button">← Back to Orders</a>
                </div>
            </div>
            
            <?php if ($editMode): ?>
                <!-- Edit Form -->
                <?php if ($error): ?>
                    <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <form method="post" class="post-form" style="background:white; padding:20px; border:1px solid #c3c4c7; margin-bottom:20px;">
                    <input type="hidden" name="action" value="update_order">
                    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                    
                    <div class="form-group">
                        <label>Customer Name <span style="color: red;">*</span></label>
                        <input type="text" name="customer_name" value="<?php echo htmlspecialchars($orderDetail['customer_name']); ?>" required class="large-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Email <span style="color: red;">*</span></label>
                        <input type="email" name="customer_email" value="<?php echo htmlspecialchars($orderDetail['customer_email']); ?>" required class="large-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Phone</label>
                        <input type="text" name="customer_phone" value="<?php echo htmlspecialchars($orderDetail['customer_phone'] ?? ''); ?>" class="large-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Address</label>
                        <textarea name="customer_address" rows="3" class="large-text"><?php echo htmlspecialchars($orderDetail['customer_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Amount (GHS) <span style="color: red;">*</span></label>
                        <input type="number" name="total_amount" value="<?php echo number_format($orderDetail['total_amount'], 2, '.', ''); ?>" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status <span style="color: red;">*</span></label>
                        <select name="status" required>
                            <option value="pending" <?php echo $orderDetail['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $orderDetail['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $orderDetail['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $orderDetail['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="4" class="large-text"><?php echo htmlspecialchars($orderDetail['notes'] ?? ''); ?></textarea>
                        <p class="description">Internal notes about this order (not visible to customer)</p>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Save Changes">
                        <a href="?id=<?php echo $orderId; ?>" class="button">Cancel</a>
                    </p>
                </form>
            <?php else: ?>
                <!-- View Mode -->
                <div style="background:white; padding:20px; border:1px solid #c3c4c7; margin-bottom:20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <h3>Customer Information</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($orderDetail['customer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($orderDetail['customer_email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($orderDetail['customer_phone'] ?? '-'); ?></p>
                            <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($orderDetail['customer_address'] ?? '-')); ?></p>
                        </div>
                        <div>
                            <h3>Order Information</h3>
                            <p><strong>Order Number:</strong> <?php echo htmlspecialchars($orderDetail['order_number']); ?></p>
                            <p><strong>Total Amount:</strong> GHS <?php echo number_format($orderDetail['total_amount'], 2); ?></p>
                            <p><strong>Status:</strong> <span class="status-<?php echo $orderDetail['status']; ?>"><?php echo ucfirst($orderDetail['status']); ?></span></p>
                            <p><strong>Date Created:</strong> <?php echo date('Y-m-d H:i:s', strtotime($orderDetail['created_at'])); ?></p>
                            <?php if (!empty($orderDetail['notes'])): ?>
                                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($orderDetail['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h3>Order Items</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $items = $pdo->prepare("SELECT * FROM cms_order_items WHERE order_id=?");
                            $items->execute([$orderId]);
                            $orderItems = $items->fetchAll();
                            $itemsTotal = 0;
                            foreach ($orderItems as $item): 
                                $itemsTotal += $item['total'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>GHS <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>GHS <?php echo number_format($item['total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight: bold; background: #f6f7f7;">
                                <td colspan="3" style="text-align: right;">Total:</td>
                                <td>GHS <?php echo number_format($orderDetail['total_amount'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
                        <form method="post" style="display: inline-block;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <label><strong>Quick Status Update:</strong></label>
                            <select name="status" style="margin: 0 10px;">
                                <option value="pending" <?php echo $orderDetail['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $orderDetail['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="completed" <?php echo $orderDetail['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $orderDetail['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <input type="submit" class="button button-primary" value="Update Status">
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #646970;">
                                No orders found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                    <small style="color: #646970;"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                </td>
                                <td>GHS <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <form method="post" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="action" value="quick_update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 12px;">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="?id=<?php echo $order['id']; ?>" class="button button-small" style="padding: 4px 8px; font-size: 12px; margin-right: 5px;">View</a>
                                    <a href="?id=<?php echo $order['id']; ?>&edit=1" class="button button-small" style="padding: 4px 8px; font-size: 12px; margin-right: 5px;">Edit</a>
                                    <form method="post" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_order">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="submit" class="button button-small button-delete" style="padding: 4px 8px; font-size: 12px; background: #d63638; color: white; border: none; cursor: pointer;" value="Delete">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

