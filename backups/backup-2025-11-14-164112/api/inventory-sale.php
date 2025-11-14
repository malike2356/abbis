<?php
/**
 * API Endpoint: Reduce inventory when items are sold via CMS shop
 * Call this from CMS when an order is completed
 */
header('Content-Type: application/json');

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$pdo = getDBConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order_items']) || !is_array($input['order_items'])) {
        throw new Exception('Invalid order items');
    }
    
    $orderNumber = $input['order_number'] ?? 'CMS-' . time();
    $customerName = $input['customer_name'] ?? 'Customer';
    $customerEmail = $input['customer_email'] ?? null;
    $cmsOrderId = $input['cms_order_id'] ?? null;
    
    $pdo->beginTransaction();
    
    // Create sale order record
    $orderStmt = $pdo->prepare("INSERT INTO sale_orders (order_number, customer_name, customer_email, status, cms_order_id, created_by) VALUES (?,?,?,'completed',?,?)");
    $orderStmt->execute([$orderNumber, $customerName, $customerEmail, $cmsOrderId, $_SESSION['user_id']]);
    $orderId = $pdo->lastInsertId();
    
    $totalAmount = 0;
    $results = [];
    
    foreach ($input['order_items'] as $item) {
        $itemType = $item['item_type'] ?? 'catalog'; // catalog or material
        $itemId = intval($item['item_id'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);
        $unitPrice = floatval($item['unit_price'] ?? 0);
        
        if (!$itemId || !$quantity) continue;
        
        // Create sale order item
        $itemStmt = $pdo->prepare("INSERT INTO sale_order_items (order_id, item_type, item_id, item_name, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?,?)");
        $itemName = $item['item_name'] ?? 'Item';
        $totalPrice = $quantity * $unitPrice;
        $itemStmt->execute([$orderId, $itemType, $itemId, $itemName, $quantity, $unitPrice, $totalPrice]);
        
        // Update inventory
        if ($itemType === 'material') {
            $updateStmt = $pdo->prepare("UPDATE materials_inventory SET quantity_remaining = quantity_remaining - ?, quantity_used = quantity_used + ? WHERE id = ?");
            $updateStmt->execute([$quantity, $quantity, $itemId]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE catalog_items SET inventory_quantity = inventory_quantity - ? WHERE id = ?");
            $updateStmt->execute([$quantity, $itemId]);
        }
        
        // Record transaction
        $transStmt = $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, quantity, unit_cost, total_cost, reference_type, reference_id, notes, created_by) VALUES ('sale',?,?,?,?,?,'sale_order',?,?,?)");
        $transStmt->execute([$itemType, $itemId, $quantity, $unitPrice, $totalPrice, $orderId, 'Sale via CMS shop', $_SESSION['user_id']]);
        
        $totalAmount += $totalPrice;
        $results[] = ['item_id' => $itemId, 'quantity' => $quantity, 'status' => 'updated'];
    }
    
    // Update order total
    $pdo->prepare("UPDATE sale_orders SET total_amount = ? WHERE id = ?")->execute([$totalAmount, $orderId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Inventory updated successfully',
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'total_amount' => $totalAmount,
        'items' => $results
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
