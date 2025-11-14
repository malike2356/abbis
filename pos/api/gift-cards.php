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
        $cardNumber = $_GET['card_number'] ?? '';
        if ($cardNumber) {
            $card = $repo->getGiftCardByNumber($cardNumber);
            if ($card) {
                // Don't return PIN for security
                unset($card['pin']);
                echo json_encode(['success' => true, 'data' => $card]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gift card not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Card number required']);
        }
    } else if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'redeem') {
            $cardNumber = $_POST['card_number'] ?? '';
            $amount = (float)($_POST['amount'] ?? 0);
            $saleId = !empty($_POST['sale_id']) ? (int)$_POST['sale_id'] : null;
            
            if (!$cardNumber || $amount <= 0) {
                throw new InvalidArgumentException('Invalid card number or amount');
            }
            
            $card = $repo->redeemGiftCard($cardNumber, $amount, $saleId);
            echo json_encode(['success' => true, 'data' => $card]);
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
    error_log('[POS Gift Card] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gift card operation failed']);
}

