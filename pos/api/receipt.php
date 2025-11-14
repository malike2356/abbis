<?php
/**
 * POS Receipt API
 * Get receipt data for printing/reprinting and send email receipts
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new PosRepository();

try {
    if ($method === 'GET') {
        $saleId = isset($_GET['sale_id']) ? (int) $_GET['sale_id'] : 0;
        $saleNumber = $_GET['sale_number'] ?? '';
        $action = $_GET['action'] ?? 'get';
        
        if ($action === 'email_status') {
            // Get email receipt status
            $saleId = isset($_GET['sale_id']) ? (int) $_GET['sale_id'] : 0;
            if (!$saleId) {
                throw new InvalidArgumentException('Sale ID required');
            }
            
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                SELECT id, email_address, status, sent_at, error_message, created_at
                FROM pos_email_receipts
                WHERE sale_id = :sale_id
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([':sale_id' => $saleId]);
            $emailReceipt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $emailReceipt ?: null]);
            exit;
        }
        
        if (!$saleId && !$saleNumber) {
            throw new InvalidArgumentException('Sale ID or sale number required');
        }
        
        $pdo = getDBConnection();
        // Get sale data with items
        $sql = "SELECT s.*, st.store_name 
                FROM pos_sales s
                LEFT JOIN pos_stores st ON s.store_id = st.id
                WHERE ";
        $params = [];
        
        if ($saleId) {
            $sql .= "s.id = :sale_id";
            $params[':sale_id'] = $saleId;
        } else {
            $sql .= "s.sale_number = :sale_number";
            $params[':sale_number'] = $saleNumber;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            throw new InvalidArgumentException('Sale not found');
        }
        
        // Get sale items
        $itemsStmt = $pdo->prepare("
            SELECT si.*, p.name as product_name, p.sku
            FROM pos_sale_items si
            LEFT JOIN pos_products p ON si.product_id = p.id
            WHERE si.sale_id = :sale_id
        ");
        $itemsStmt->execute([':sale_id' => $sale['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payments
        $paymentsStmt = $pdo->prepare("
            SELECT * FROM pos_sale_payments WHERE sale_id = :sale_id
        ");
        $paymentsStmt->execute([':sale_id' => $sale['id']]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $receipt = [
            'sale' => $sale,
            'items' => $items,
            'payments' => $payments
        ];
        
        echo json_encode(['success' => true, 'data' => $receipt]);
        exit;
    } else if ($method === 'POST') {
        // Send email receipt
        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg());
        }
        
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Payload must be a JSON object');
        }
        
        $saleId = (int)($payload['sale_id'] ?? 0);
        $emailAddress = trim($payload['email_address'] ?? '');
        $customerId = !empty($payload['customer_id']) ? (int)$payload['customer_id'] : null;
        
        if (!$saleId || $saleId <= 0) {
            throw new InvalidArgumentException('Sale ID is required and must be a positive integer');
        }
        
        if (empty($emailAddress)) {
            throw new InvalidArgumentException('Email address is required');
        }
        
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address format: ' . $emailAddress);
        }
        
        // Get template options if provided
        $templateOptions = !empty($payload['template_options']) ? $payload['template_options'] : null;
        
        try {
            $result = $repo->sendEmailReceipt($saleId, $emailAddress, $customerId, $templateOptions);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Email receipt sent successfully',
                    'data' => $result
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to send email receipt',
                    'data' => $result
                ]);
            }
        } catch (Throwable $e) {
            error_log('[POS Receipt API] Error sending email: ' . $e->getMessage());
            error_log('[POS Receipt API] Trace: ' . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send email receipt: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    error_log('[POS Receipt API] Validation Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => defined('APP_ENV') && APP_ENV === 'development' ? [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    error_log('[POS Receipt API] Error: ' . $errorMessage);
    error_log('[POS Receipt API] Trace: ' . $errorTrace);
    
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo json_encode([
            'success' => false, 
            'message' => 'Operation failed: ' . $errorMessage,
            'trace' => $errorTrace
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Operation failed. Please try again.']);
    }
}


