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
        $code = $_GET['code'] ?? '';
        $subtotal = (float)($_GET['subtotal'] ?? 0);
        $customerId = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
        
        if ($code) {
            $promotion = $repo->validatePromotion($code, $subtotal, $customerId);
            if ($promotion) {
                $discount = $repo->applyPromotion($promotion['id'], $subtotal);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'promotion' => $promotion,
                        'discount_amount' => $discount,
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired promotion code']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Promotion code required']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[POS Promotion] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Promotion validation failed']);
}

