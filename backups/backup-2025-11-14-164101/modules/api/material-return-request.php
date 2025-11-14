<?php
/**
 * Material Return Request API (from Materials side)
 * Create return requests that will be accepted in POS
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/MaterialsService.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
$pdo = getDBConnection();
$materialsService = new MaterialsService($pdo);
$userId = $_SESSION['user_id'] ?? 0;

try {
    if ($method === 'POST') {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        $materialType = sanitizeInput($_POST['material_type'] ?? '');
        $quantity = floatval($_POST['quantity'] ?? 0);
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        
        if (empty($materialType)) {
            throw new InvalidArgumentException('material_type is required');
        }
        
        if ($quantity <= 0) {
            throw new InvalidArgumentException('quantity must be greater than 0');
        }
        
        // Get material name
        $stmt = $pdo->prepare("SELECT material_name FROM materials_inventory WHERE material_type = ?");
        $stmt->execute([$materialType]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        $materialName = $material['material_name'] ?? $materialType;
        
        // Check if table exists, if not, return helpful error
        try {
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'pos_material_returns'");
            if ($checkStmt->rowCount() === 0) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database tables not initialized. Please run the migration: ' . app_base_path() . '/pos/admin/run-materials-migration.php'
                ]);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error. Please run the migration: ' . app_base_path() . '/pos/admin/run-materials-migration.php'
            ]);
            exit;
        }
        
        $result = $materialsService->createReturnRequest([
            'material_type' => $materialType,
            'material_name' => $materialName,
            'quantity' => $quantity,
            'unit_of_measure' => 'pcs',
            'remarks' => $remarks
        ], $userId);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Return request created successfully. POS will be notified.',
                'data' => $result
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create return request'
            ]);
        }
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[Material Return Request API] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

