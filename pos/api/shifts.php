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
        // Get active shift or list shifts
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $cashierId = (int)($_SESSION['user_id'] ?? 0);
        $shiftId = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : 0;
        
        if ($cashierId <= 0) {
            throw new InvalidArgumentException('Unable to determine cashier account');
        }
        
        if ($shiftId > 0) {
            // Get specific shift
            $shift = $repo->getShift($shiftId);
            if (!$shift) {
                throw new InvalidArgumentException('Shift not found');
            }
            echo json_encode(['success' => true, 'data' => $shift]);
        } else if ($storeId > 0) {
            // Get active shift
            $shift = $repo->getActiveShift($cashierId, $storeId);
            echo json_encode(['success' => true, 'data' => $shift]);
        } else {
            // List recent shifts
            $limit = (int)($_GET['limit'] ?? 20);
            $shifts = $repo->listShifts($cashierId, $limit);
            echo json_encode(['success' => true, 'data' => $shifts]);
        }
    } else if ($method === 'POST') {
        // Start or end shift
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
            case 'start':
                $openingCash = (float)($payload['opening_cash'] ?? 0);
                $result = $repo->startShift($cashierId, $storeId, $openingCash);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'end':
                $closingCash = isset($payload['closing_cash']) ? (float)$payload['closing_cash'] : null;
                $notes = $payload['notes'] ?? null;
                $result = $repo->endShift($cashierId, $storeId, $closingCash, $notes);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action. Must be: start or end']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    $message = $e->getMessage();
    error_log('[POS Shifts] Validation error: ' . $message);
    echo json_encode(['success' => false, 'message' => $message]);
} catch (PDOException $e) {
    http_response_code(500);
    $message = 'Database error: ' . $e->getMessage();
    error_log('[POS Shifts] Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $message]);
} catch (Throwable $e) {
    http_response_code(500);
    $message = $e->getMessage();
    error_log('[POS Shifts] Fatal error: ' . $message);
    error_log('[POS Shifts] Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Shift operation failed: ' . $message]);
}

