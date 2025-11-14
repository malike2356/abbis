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
$repo = new PosRepository();

try {
    if ($method === 'GET') {
        $customerId = (int)($_GET['customer_id'] ?? 0);
        if ($customerId) {
            $loyalty = $repo->getCustomerLoyalty($customerId);
            if ($loyalty) {
                echo json_encode(['success' => true, 'data' => $loyalty]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No loyalty account found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
        }
    } else if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        $customerId = (int)($_POST['customer_id'] ?? 0);
        
        if (!$customerId) {
            throw new InvalidArgumentException('Customer ID required');
        }
        
        $program = $repo->getActiveLoyaltyProgram();
        if (!$program) {
            throw new InvalidArgumentException('No active loyalty program');
        }
        
        if ($action === 'redeem') {
            $points = (int)($_POST['points'] ?? 0);
            if ($points <= 0) {
                throw new InvalidArgumentException('Invalid points amount');
            }
            
            $currencyValue = $repo->redeemLoyaltyPoints($customerId, $program['id'], $points);
            echo json_encode(['success' => true, 'data' => ['currency_value' => $currencyValue]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[POS Loyalty] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Loyalty operation failed']);
}

