<?php
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/pos/UnifiedInventoryService.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order Number', 'Customer Name', 'Customer Email', 'Customer Phone', 'Total Amount', 'Status', 'Date Created']);
    
    // Use same filters as list view
    $statusFilter = $_GET['status'] ?? '';
    $searchQuery = $_GET['search'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($searchQuery)) {
        $whereConditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
        $searchParam = "%{$searchQuery}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $exportStmt = $pdo->prepare("SELECT * FROM cms_orders {$whereClause} ORDER BY created_at DESC");
    $exportStmt->execute($params);
    
    while ($order = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $order['order_number'],
            $order['customer_name'],
            $order['customer_email'],
            $order['customer_phone'] ?? '',
            $order['total_amount'],
            ucfirst($order['status']),
            $order['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// Handle POST actions
$action = $_POST['action'] ?? '';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'update_status') {
            $orderId = $_POST['order_id'];
            $newStatus = $_POST['status'];
            
            // Get old status and order items
            $orderStmt = $pdo->prepare("SELECT status, order_number FROM cms_orders WHERE id = ?");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            $oldStatus = $order['status'] ?? 'pending';
            $orderNumber = $order['order_number'] ?? '';
            
            // Update status
            $pdo->prepare("UPDATE cms_orders SET status=? WHERE id=?")->execute([$newStatus, $orderId]);
            
            // Handle inventory restoration if order is cancelled or refunded
            if (in_array($newStatus, ['cancelled', 'refunded']) && !in_array($oldStatus, ['cancelled', 'refunded'])) {
                try {
                    $inventoryService = new UnifiedInventoryService($pdo);
                    $itemsStmt = $pdo->prepare("SELECT catalog_item_id, quantity FROM cms_order_items WHERE order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($items as $item) {
                        $inventoryService->updateCatalogStock(
                            (int) $item['catalog_item_id'],
                            (float) $item['quantity'],
                            "Order {$orderNumber} {$newStatus} - inventory restored"
                        );
                    }
                } catch (Throwable $e) {
                    error_log('[CMS Orders] Inventory restoration failed: ' . $e->getMessage());
                    // Continue even if inventory restoration fails
                }
            }
            
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
        } elseif ($action === 'link_client') {
            $orderId = $_POST['order_id'];
            $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
            $pdo->prepare("UPDATE cms_orders SET client_id=? WHERE id=?")->execute([$clientId, $orderId]);
            $message = 'Order linked to client successfully';
            header('Location: orders.php?id=' . $orderId);
            exit;
        } elseif ($action === 'link_field_report') {
            $orderId = $_POST['order_id'];
            $fieldReportId = !empty($_POST['field_report_id']) ? intval($_POST['field_report_id']) : null;
            $pdo->prepare("UPDATE cms_orders SET field_report_id=? WHERE id=?")->execute([$fieldReportId, $orderId]);
            $message = 'Order linked to field report successfully';
            header('Location: orders.php?id=' . $orderId);
            exit;
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query with filters
$whereConditions = [];
$params = [];

if (!empty($statusFilter)) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_orders {$whereClause}");
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders with pagination
$ordersStmt = $pdo->prepare("SELECT * FROM cms_orders {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$ordersStmt->execute($allParams);
$orders = $ordersStmt->fetchAll();

// Get order statistics
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(total_amount) as total_revenue,
        SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END) as completed_revenue
    FROM cms_orders
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

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
$baseUrl = app_url();
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
        
        /* Enhanced Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 15px 20px;
            border-radius: 4px;
        }
        .stat-card.pending { border-left-color: #d63638; }
        .stat-card.processing { border-left-color: #dba617; }
        .stat-card.completed { border-left-color: #00a32a; }
        .stat-card.revenue { border-left-color: #0ea5e9; }
        .stat-card h3 {
            margin: 0 0 5px 0;
            font-size: 28px;
            font-weight: 400;
            color: #2271b1;
        }
        .stat-card.pending h3 { color: #d63638; }
        .stat-card.processing h3 { color: #dba617; }
        .stat-card.completed h3 { color: #00a32a; }
        .stat-card.revenue h3 { color: #0ea5e9; }
        .stat-card p {
            margin: 0;
            color: #646970;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filters-panel {
            background: white;
            border: 1px solid #c3c4c7;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .filters-panel h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .filters-grid input,
        .filters-grid select {
            width: 100%;
            padding: 8px;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .order-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            transition: box-shadow 0.2s;
        }
        .order-card:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f1;
        }
        .order-info {
            flex: 1;
        }
        .order-number {
            font-size: 18px;
            font-weight: 600;
            color: #2271b1;
            margin-bottom: 5px;
        }
        .order-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
            font-size: 13px;
            color: #646970;
        }
        .order-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .order-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-badge-pending {
            background: #fef2f2;
            color: #d63638;
        }
        .status-badge-processing {
            background: #fffbeb;
            color: #dba617;
        }
        .status-badge-completed {
            background: #f0fdf4;
            color: #00a32a;
        }
        .status-badge-cancelled {
            background: #f6f7f7;
            color: #646970;
        }
        
        .order-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-card {
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
        }
        .detail-card h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #1e293b;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 8px;
        }
        .detail-card p {
            margin: 8px 0;
            font-size: 14px;
        }
        .detail-card strong {
            color: #1e293b;
            display: inline-block;
            min-width: 120px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            text-decoration: none;
            color: #2271b1;
            background: white;
        }
        .pagination .current {
            background: #2271b1;
            color: white;
            border-color: #2271b1;
        }
        .pagination a:hover {
            background: #f6f7f7;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
            }
            .order-actions {
                width: 100%;
                margin-top: 10px;
            }
            .filters-grid {
                grid-template-columns: 1fr;
            }
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Orders</h1>
            <?php if (!$orderDetail): ?>
                <div>
                    <a href="?export=csv<?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>" class="button">Export CSV</a>
                </div>
            <?php endif; ?>
        </div>
        
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
                <div style="background:white; padding:20px; border:1px solid #c3c4c7; margin-bottom:20px; border-radius: 4px;">
                    <div class="order-detail-grid">
                        <div class="detail-card">
                            <h3>Customer Information</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($orderDetail['customer_name']); ?></p>
                            <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($orderDetail['customer_email']); ?>"><?php echo htmlspecialchars($orderDetail['customer_email']); ?></a></p>
                            <p><strong>Phone:</strong> <?php echo !empty($orderDetail['customer_phone']) ? '<a href="tel:' . htmlspecialchars($orderDetail['customer_phone']) . '">' . htmlspecialchars($orderDetail['customer_phone']) . '</a>' : '-'; ?></p>
                            <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($orderDetail['customer_address'] ?? '-')); ?></p>
                        </div>
                        <div class="detail-card">
                            <h3>Order Information</h3>
                            <p><strong>Order Number:</strong> <span style="color: #2271b1; font-weight: 600;"><?php echo htmlspecialchars($orderDetail['order_number']); ?></span></p>
                            <p><strong>Total Amount:</strong> <span style="font-size: 18px; font-weight: 600; color: #00a32a;">GHS <?php echo number_format($orderDetail['total_amount'], 2); ?></span></p>
                            <p><strong>Status:</strong> <span class="order-status-badge status-badge-<?php echo $orderDetail['status']; ?>"><?php echo ucfirst($orderDetail['status']); ?></span></p>
                            <p><strong>Date Created:</strong> <?php echo date('F j, Y g:i A', strtotime($orderDetail['created_at'])); ?></p>
                            <?php if (isset($orderDetail['updated_at']) && $orderDetail['updated_at'] !== $orderDetail['created_at']): ?>
                                <p><strong>Last Updated:</strong> <?php echo date('F j, Y g:i A', strtotime($orderDetail['updated_at'])); ?></p>
                            <?php endif; ?>
                            
                            <?php
                            // ABBIS Integration: Show linked client
                            if (!empty($orderDetail['client_id'])) {
                                try {
                                    $clientStmt = $pdo->prepare("SELECT id, client_name, email, contact_number FROM clients WHERE id=?");
                                    $clientStmt->execute([$orderDetail['client_id']]);
                                    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($client) {
                                        echo '<div style="margin-top: 16px; padding-top: 16px; border-top: 2px solid #c3c4c7;">';
                                        echo '<p style="margin: 8px 0;"><strong>🔗 Linked to ABBIS Client:</strong></p>';
                                        echo '<p style="margin: 4px 0;"><a href="' . $baseUrl . '/modules/crm.php?action=view&id=' . $client['id'] . '" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">' . htmlspecialchars($client['client_name']) . ' →</a></p>';
                                        echo '<p style="margin: 4px 0; font-size: 12px; color: #646970;">Email: ' . htmlspecialchars($client['email'] ?? 'N/A') . '</p>';
                                        echo '</div>';
                                    }
                                } catch (Exception $e) {}
                            } else {
                                // Allow linking to client
                                echo '<div style="margin-top: 16px; padding-top: 16px; border-top: 2px solid #c3c4c7;">';
                                echo '<p style="margin: 8px 0;"><strong>Link to ABBIS Client:</strong></p>';
                                echo '<form method="post" style="display: flex; gap: 8px; align-items: flex-end;">';
                                echo '<input type="hidden" name="action" value="link_client">';
                                echo '<input type="hidden" name="order_id" value="' . $orderId . '">';
                                echo '<select name="client_id" style="flex: 1; padding: 8px; border: 1px solid #c3c4c7; border-radius: 4px;">';
                                echo '<option value="">Select Client...</option>';
                                try {
                                    $clientsStmt = $pdo->query("SELECT id, client_name, email FROM clients ORDER BY client_name LIMIT 100");
                                    while ($c = $clientsStmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . $c['id'] . '">' . htmlspecialchars($c['client_name']) . ' (' . htmlspecialchars($c['email']) . ')</option>';
                                    }
                                } catch (Exception $e) {}
                                echo '</select>';
                                echo '<button type="submit" class="button button-small">Link</button>';
                                echo '</form>';
                                echo '</div>';
                            }
                            
                            // ABBIS Integration: Show linked field report
                            if (!empty($orderDetail['field_report_id'])) {
                                try {
                                    $reportStmt = $pdo->prepare("SELECT id, report_id, report_date, site_name FROM field_reports WHERE id=?");
                                    $reportStmt->execute([$orderDetail['field_report_id']]);
                                    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($report) {
                                        echo '<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">';
                                        echo '<p style="margin: 8px 0;"><strong>🔗 Linked to Field Report:</strong></p>';
                                        echo '<p style="margin: 4px 0;"><a href="' . $baseUrl . '/modules/field-reports.php?action=view&id=' . $report['id'] . '" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">' . htmlspecialchars($report['report_id']) . ' →</a></p>';
                                        echo '<p style="margin: 4px 0; font-size: 12px; color: #646970;">Site: ' . htmlspecialchars($report['site_name']) . ' | Date: ' . date('M j, Y', strtotime($report['report_date'])) . '</p>';
                                        echo '</div>';
                                    }
                                } catch (Exception $e) {}
                            } else {
                                // Allow linking to field report
                                echo '<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">';
                                echo '<p style="margin: 8px 0;"><strong>Link to Field Report:</strong></p>';
                                echo '<form method="post" style="display: flex; gap: 8px; align-items: flex-end;">';
                                echo '<input type="hidden" name="action" value="link_field_report">';
                                echo '<input type="hidden" name="order_id" value="' . $orderId . '">';
                                echo '<select name="field_report_id" style="flex: 1; padding: 8px; border: 1px solid #c3c4c7; border-radius: 4px;">';
                                echo '<option value="">Select Field Report...</option>';
                                try {
                                    $reportsStmt = $pdo->query("SELECT id, report_id, report_date, site_name FROM field_reports ORDER BY report_date DESC LIMIT 100");
                                    while ($r = $reportsStmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . $r['id'] . '">' . htmlspecialchars($r['report_id']) . ' - ' . htmlspecialchars($r['site_name']) . ' (' . date('M j, Y', strtotime($r['report_date'])) . ')</option>';
                                    }
                                } catch (Exception $e) {}
                                echo '</select>';
                                echo '<button type="submit" class="button button-small">Link</button>';
                                echo '</form>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <?php 
                        // Get payment information
                        try {
                            $paymentStmt = $pdo->prepare("SELECT * FROM cms_payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
                            $paymentStmt->execute([$orderId]);
                            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $payment = null;
                        }
                        if ($payment): ?>
                        <div class="detail-card">
                            <h3>Payment Information</h3>
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($payment['payment_method'] ?? 'N/A')); ?></p>
                            <p><strong>Payment Status:</strong> <span class="order-status-badge status-badge-<?php echo $payment['status'] ?? 'pending'; ?>"><?php echo ucfirst($payment['status'] ?? 'Pending'); ?></span></p>
                            <p><strong>Amount Paid:</strong> GHS <?php echo number_format($payment['amount'] ?? 0, 2); ?></p>
                            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id'] ?? '-'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($orderDetail['notes'])): ?>
                        <div class="detail-card" style="margin-bottom: 20px;">
                            <h3>Internal Notes</h3>
                            <p><?php echo nl2br(htmlspecialchars($orderDetail['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
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
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-card pending">
                    <h3><?php echo number_format($stats['pending'] ?? 0); ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card processing">
                    <h3><?php echo number_format($stats['processing'] ?? 0); ?></h3>
                    <p>Processing</p>
                </div>
                <div class="stat-card completed">
                    <h3><?php echo number_format($stats['completed'] ?? 0); ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card revenue">
                    <h3>GHS <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            
            <!-- Filters Panel -->
            <div class="filters-panel">
                <h3>🔍 Filter Orders</h3>
                <form method="get" action="orders.php" class="filters-form">
                    <div class="filters-grid">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Order #, Name, Email...">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Status</label>
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="button button-primary">Apply Filters</button>
                        <a href="orders.php" class="button">Clear</a>
                        <?php if ($statusFilter || $searchQuery || $dateFrom || $dateTo): ?>
                            <span style="color: #646970; font-size: 13px; margin-left: 10px;">
                                Showing <?php echo $totalOrders; ?> order(s)
                            </span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Orders List -->
            <?php if (empty($orders)): ?>
                <div style="background: white; border: 1px solid #c3c4c7; padding: 40px; text-align: center; border-radius: 4px;">
                    <p style="color: #646970; font-size: 16px; margin: 0;">No orders found.</p>
                    <?php if ($statusFilter || $searchQuery || $dateFrom || $dateTo): ?>
                        <p style="color: #646970; font-size: 14px; margin-top: 10px;">
                            <a href="orders.php">Clear filters to see all orders</a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-meta">
                                    <span><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></span>
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></span>
                                    <span><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                                    <span><strong>Total:</strong> <span style="color: #00a32a; font-weight: 600;">GHS <?php echo number_format($order['total_amount'], 2); ?></span></span>
                                </div>
                            </div>
                            <div class="order-actions">
                                <span class="order-status-badge status-badge-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                                <form method="post" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="action" value="quick_update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 12px; border: 1px solid #c3c4c7; border-radius: 3px;">
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </form>
                                <a href="?id=<?php echo $order['id']; ?>" class="button button-small">View</a>
                                <a href="?id=<?php echo $order['id']; ?>&edit=1" class="button button-small">Edit</a>
                                <form method="post" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_order">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="submit" class="button button-small button-delete" value="Delete">
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $dateFrom ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo $dateTo ? '&date_to=' . urlencode($dateTo) : ''; ?>">« Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $dateFrom ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo $dateTo ? '&date_to=' . urlencode($dateTo) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $dateFrom ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo $dateTo ? '&date_to=' . urlencode($dateTo) : ''; ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                    <p style="text-align: center; color: #646970; font-size: 13px; margin-top: 10px;">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalOrders; ?> total orders)
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

