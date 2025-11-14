<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $repo = new PosRepository();
    
    if ($method === 'GET') {
        // Get current drawer session or list sessions
        $storeId = (int)($_GET['store_id'] ?? 0);
        $cashierId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($cashierId <= 0) {
            throw new InvalidArgumentException('Unable to determine cashier account');
        }
        
        if ($storeId > 0) {
            $session = $repo->getCurrentDrawerSession($storeId, $cashierId);
            echo json_encode(['success' => true, 'data' => $session]);
        } else {
            // List recent sessions
            $limit = (int)($_GET['limit'] ?? 20);
            $sessions = $repo->listDrawerSessions($cashierId, $limit);
            echo json_encode(['success' => true, 'data' => $sessions]);
        }
    } else if ($method === 'POST') {
        // Open, close, or count drawer
        $payload = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload');
        }
        
        $action = $payload['action'] ?? '';
        $storeId = (int)($payload['store_id'] ?? 0);
        $cashierId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($cashierId <= 0) {
            throw new InvalidArgumentException('Unable to determine cashier account');
        }
        
        if ($storeId <= 0) {
            throw new InvalidArgumentException('Store ID is required');
        }
        
        switch ($action) {
            case 'open':
                $openingAmount = (float)($payload['opening_amount'] ?? 0);
                $result = $repo->openDrawerSession($storeId, $cashierId, $openingAmount);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'close':
                $countedAmount = isset($payload['counted_amount']) ? (float)$payload['counted_amount'] : null;
                $notes = $payload['notes'] ?? null;
                $result = $repo->closeDrawerSession($storeId, $cashierId, $countedAmount, $notes);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'count':
                $countedAmount = (float)($payload['counted_amount'] ?? 0);
                $result = $repo->countDrawerSession($storeId, $cashierId, $countedAmount);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action. Must be: open, close, or count']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    $message = $e->getMessage();
    error_log('[POS Drawer] Validation error: ' . $message);
    echo json_encode(['success' => false, 'message' => $message]);
} catch (PDOException $e) {
    http_response_code(500);
    $message = 'Database error: ' . $e->getMessage();
    error_log('[POS Drawer] Database error: ' . $e->getMessage());
    error_log('[POS Drawer] SQL State: ' . $e->getCode());
    // Check if it's a table missing error
    if (strpos($e->getMessage(), 'pos_cash_drawer_sessions') !== false || $e->getCode() === '42S02') {
        $message = 'Cash drawer feature is not available. Please run database migrations.';
    }
    echo json_encode(['success' => false, 'message' => $message]);
} catch (Throwable $e) {
    http_response_code(500);
    $message = $e->getMessage();
    error_log('[POS Drawer] Fatal error: ' . $message);
    error_log('[POS Drawer] Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Drawer operation failed: ' . $message]);
}

