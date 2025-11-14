<?php
/**
 * POS Hold/Resume Sales API
 * Save and retrieve held sales
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDBConnection();
$cashierId = (int) ($_SESSION['user_id'] ?? 0);

// Ensure holds table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pos_held_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cashier_id INT NOT NULL,
            store_id INT NOT NULL,
            customer_id INT DEFAULT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            cart_data TEXT NOT NULL,
            discount_type VARCHAR(20) DEFAULT NULL,
            discount_value DECIMAL(10,2) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cashier (cashier_id),
            INDEX idx_store (store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table might already exist
}

try {
    if ($method === 'GET') {
        // List held sales for current cashier
        $stmt = $pdo->prepare("
            SELECT 
                id,
                store_id,
                customer_id,
                customer_name,
                discount_type,
                discount_value,
                notes,
                created_at,
                cart_data
            FROM pos_held_sales
            WHERE cashier_id = :cashier_id
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->bindValue(':cashier_id', $cashierId, PDO::PARAM_INT);
        $stmt->execute();
        
        $holds = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted = array_map(function($hold) {
            $cartData = json_decode($hold['cart_data'], true);
            $itemCount = is_array($cartData) ? count($cartData) : 0;
            $total = 0;
            if (is_array($cartData)) {
                foreach ($cartData as $item) {
                    $total += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0);
                }
            }
            
            return [
                'id' => (int) $hold['id'],
                'store_id' => (int) $hold['store_id'],
                'customer_id' => $hold['customer_id'] ? (int) $hold['customer_id'] : null,
                'customer_name' => $hold['customer_name'],
                'item_count' => $itemCount,
                'total' => $total,
                'discount_type' => $hold['discount_type'],
                'discount_value' => $hold['discount_value'] ? (float) $hold['discount_value'] : null,
                'notes' => $hold['notes'],
                'created_at' => $hold['created_at'],
                'cart_data' => $cartData
            ];
        }, $holds);
        
        echo json_encode(['success' => true, 'data' => $formatted]);
        exit;
    }
    
    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true);
        $action = $payload['action'] ?? '';
        
        if ($action === 'hold') {
            // Save current sale
            $storeId = (int) ($payload['store_id'] ?? 0);
            $cartData = $payload['cart'] ?? [];
            $customerId = !empty($payload['customer_id']) ? (int) $payload['customer_id'] : null;
            $customerName = $payload['customer_name'] ?? null;
            $discountType = $payload['discount_type'] ?? null;
            $discountValue = !empty($payload['discount_value']) ? (float) $payload['discount_value'] : null;
            $notes = $payload['notes'] ?? null;
            
            if (empty($cartData)) {
                throw new InvalidArgumentException('Cannot hold an empty sale');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO pos_held_sales 
                    (cashier_id, store_id, customer_id, customer_name, cart_data, discount_type, discount_value, notes)
                VALUES 
                    (:cashier_id, :store_id, :customer_id, :customer_name, :cart_data, :discount_type, :discount_value, :notes)
            ");
            $stmt->bindValue(':cashier_id', $cashierId, PDO::PARAM_INT);
            $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->bindValue(':customer_name', $customerName, PDO::PARAM_STR);
            $stmt->bindValue(':cart_data', json_encode($cartData), PDO::PARAM_STR);
            $stmt->bindValue(':discount_type', $discountType, PDO::PARAM_STR);
            $stmt->bindValue(':discount_value', $discountValue, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
            $stmt->execute();
            
            $holdId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'data' => ['hold_id' => $holdId]]);
            exit;
        }
        
        if ($action === 'resume') {
            // Get held sale
            $holdId = (int) ($payload['hold_id'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT * FROM pos_held_sales
                WHERE id = :hold_id AND cashier_id = :cashier_id
            ");
            $stmt->bindValue(':hold_id', $holdId, PDO::PARAM_INT);
            $stmt->bindValue(':cashier_id', $cashierId, PDO::PARAM_INT);
            $stmt->execute();
            
            $hold = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$hold) {
                throw new InvalidArgumentException('Held sale not found');
            }
            
            $cartData = json_decode($hold['cart_data'], true);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'cart' => $cartData,
                    'customer_id' => $hold['customer_id'],
                    'customer_name' => $hold['customer_name'],
                    'discount_type' => $hold['discount_type'],
                    'discount_value' => $hold['discount_value'],
                    'notes' => $hold['notes'],
                    'store_id' => (int) $hold['store_id']
                ]
            ]);
            exit;
        }
        
        if ($action === 'delete') {
            // Delete held sale
            $holdId = (int) ($payload['hold_id'] ?? 0);
            
            $stmt = $pdo->prepare("
                DELETE FROM pos_held_sales
                WHERE id = :hold_id AND cashier_id = :cashier_id
            ");
            $stmt->bindValue(':hold_id', $holdId, PDO::PARAM_INT);
            $stmt->bindValue(':cashier_id', $cashierId, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            exit;
        }
        
        throw new InvalidArgumentException('Invalid action');
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[POS Holds API] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Operation failed']);
}

