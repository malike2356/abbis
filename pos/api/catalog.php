<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';
require_once __DIR__ . '/../../includes/pos/PosValidator.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new PosRepository();

try {
    if ($method === 'GET') {
        if (isset($_GET['mode']) && $_GET['mode'] === 'categories') {
            $categories = $repo->listCategories(false);
            echo json_encode(['success' => true, 'data' => $categories]);
            exit;
        }

        $filters = [
            'search' => $_GET['search'] ?? null,
            'category_id' => $_GET['category_id'] ?? null,
        ];
        // Default to active products only unless explicitly specified
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int) $_GET['is_active'];
        } else {
            // Default to active products only for POS terminal
            $filters['is_active'] = 1;
        }
        $limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 100) : 50;
        $offset = isset($_GET['offset']) ? max((int) $_GET['offset'], 0) : 0;

        $products = $repo->listProducts($filters, $limit, $offset);
        echo json_encode(['success' => true, 'data' => $products]);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid JSON payload.');
    }

    if ($method === 'POST') {
        $auth->requirePermission('pos.inventory.manage');
        $validated = PosValidator::validateProductPayload($payload);
        $productId = $repo->createProduct($validated);
        echo json_encode(['success' => true, 'product_id' => $productId]);
        exit;
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $auth->requirePermission('pos.inventory.manage');
        if (empty($_GET['id'])) {
            throw new InvalidArgumentException('Product id is required.');
        }
        $validated = PosValidator::validateProductPayload($payload, true);
        $repo->updateProduct((int) $_GET['id'], $validated);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    if (APP_ENV === 'development') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'POS catalog error.']);
    }
}


