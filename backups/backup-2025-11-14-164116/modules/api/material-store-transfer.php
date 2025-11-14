<?php
/**
 * Material Store Transfer API
 * Handles transfers between POS (Material Shop) and Material Store
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/MaterialStoreService.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // For GET requests (get_inventory), skip CSRF check
    if ($action !== 'get_inventory') {
        $input = file_get_contents('php://input');
        $data = [];
        if (!empty($input)) {
            $data = json_decode($input, true) ?? [];
        }
        $requestData = array_merge($_POST, $data);
        
        if (!CSRF::validateToken($requestData['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
    }
    
    $materialStoreService = new MaterialStoreService($pdo);
    
    switch ($action) {
        case 'transfer_from_pos':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $requestData = array_merge($_POST, $data);
            
            $materialType = $requestData['material_type'] ?? '';
            $quantity = floatval($requestData['quantity'] ?? 0);
            $userId = $_SESSION['user_id'] ?? 0;
            
            if (empty($materialType) || $quantity <= 0) {
                throw new Exception('Material type and quantity are required');
            }
            
            $result = $materialStoreService->transferFromPos($materialType, $quantity, $userId, [
                'remarks' => $requestData['remarks'] ?? null
            ]);
            
            echo json_encode($result);
            break;
            
        case 'return_to_pos':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $requestData = array_merge($_POST, $data);
            
            $materialType = $requestData['material_type'] ?? '';
            $quantity = floatval($requestData['quantity'] ?? 0);
            $userId = $_SESSION['user_id'] ?? 0;
            
            if (empty($materialType) || $quantity <= 0) {
                throw new Exception('Material type and quantity are required');
            }
            
            $result = $materialStoreService->returnToPos($materialType, $quantity, $userId, [
                'remarks' => $requestData['remarks'] ?? null
            ]);
            
            echo json_encode($result);
            break;
            
        case 'get_inventory':
            $inventory = $materialStoreService->getStoreInventory();
            echo json_encode([
                'success' => true,
                'inventory' => $inventory
            ]);
            break;
            
        case 'bulk_transfer':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $requestData = array_merge($_POST, $data);
            
            $transfers = $requestData['transfers'] ?? [];
            if (empty($transfers) || !is_array($transfers)) {
                throw new Exception('Transfers array is required');
            }
            
            $result = $materialStoreService->bulkTransferFromPos($transfers, $_SESSION['user_id'] ?? 0);
            echo json_encode($result);
            break;
            
        case 'get_low_stock':
            $threshold = floatval($_GET['threshold'] ?? 20.0);
            $alerts = $materialStoreService->getLowStockAlerts($threshold);
            echo json_encode([
                'success' => true,
                'alerts' => $alerts
            ]);
            break;
            
        case 'get_analytics':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $requestData = array_merge($_POST, $data);
            
            $analytics = $materialStoreService->getUsageAnalytics([
                'date_from' => $requestData['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
                'date_to' => $requestData['date_to'] ?? date('Y-m-d')
            ]);
            echo json_encode([
                'success' => true,
                'analytics' => $analytics
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

