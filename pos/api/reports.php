<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';
require_once __DIR__ . '/../../includes/pos/PosReportingService.php';

header('Content-Type: application/json');

try {
    $auth->requireAuth();
    $auth->requirePermission('pos.access');
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'dashboard';
$repo = new PosRepository();
$reportingService = new PosReportingService();

try {
    if ($method === 'GET') {
    switch ($action) {
        case 'dashboard':
                // Real-time dashboard data
                $userId = (int)($_SESSION['user_id'] ?? 0);
                $data = $reportingService->getDashboardSnapshot($userId);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'sales':
                // Sales reports
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $cashierId = isset($_GET['cashier_id']) ? (int)$_GET['cashier_id'] : null;
                $data = $reportingService->getSalesReport($startDate, $endDate, $storeId, $cashierId);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'shift_report':
                // Shift report
                $shiftId = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : 0;
                if (!$shiftId) {
                    throw new InvalidArgumentException('Shift ID is required');
                }
                $data = $repo->getShiftReport($shiftId);
                if (!$data) {
                    throw new InvalidArgumentException('Shift not found');
                }
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'cashier_performance':
                // Cashier performance report
                $cashierId = isset($_GET['cashier_id']) ? (int)$_GET['cashier_id'] : 0;
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                if (!$cashierId) {
                    throw new InvalidArgumentException('Cashier ID is required');
                }
                // Use repository method for detailed performance
                $data = $repo->getCashierPerformance($cashierId, $startDate, $endDate);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'cashier_performance_summary':
                // Cashier performance summary (all cashiers)
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $data = $reportingService->getCashierPerformanceReport($startDate, $endDate, $storeId);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'product_performance':
                // Product performance report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $limit = min((int)($_GET['limit'] ?? 20), 100);
                $data = $reportingService->getProductPerformanceReport($startDate, $endDate, $storeId, $limit);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'payment_methods':
                // Payment method breakdown (uses chart data)
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $data = $reportingService->getChartData('payment_methods', $storeId, (int)((strtotime($endDate) - strtotime($startDate)) / 86400));
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'hourly_sales':
                // Hourly sales chart
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $days = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
                $data = $reportingService->getChartData('hourly_sales', $storeId, $days);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'daily_sales':
                // Daily sales chart
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $days = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
                $data = $reportingService->getChartData('daily_sales', $storeId, $days);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'top_products':
                // Top products chart
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $days = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
                $data = $reportingService->getChartData('top_products_chart', $storeId, $days);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'refunds':
                // Refund report
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $data = $reportingService->getRefundReport($startDate, $endDate, $storeId);
                jsonResponse(['success' => true, 'data' => $data]);
                break;
                
            case 'inventory_alerts':
                // Inventory alerts
                $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
                $data = $reportingService->getInventoryAlertsDetailed($storeId);
            jsonResponse(['success' => true, 'data' => $data]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
    } else {
        http_response_code(405);
        jsonResponse(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[POS Reports] ' . $e->getMessage());
    error_log('[POS Reports] Stack trace: ' . $e->getTraceAsString());
    jsonResponse(['success' => false, 'message' => 'Failed to load POS reports: ' . $e->getMessage()], 500);
}



