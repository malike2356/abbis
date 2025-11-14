<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';
require_once __DIR__ . '/../../includes/pos/PosReportingService.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

$format = $_GET['format'] ?? 'csv';
$reportType = $_GET['report'] ?? 'sales';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$storeId = !empty($_GET['store_id']) ? (int)$_GET['store_id'] : null;

$repo = new PosRepository();
$reporting = new PosReportingService();

try {
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pos_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($reportType === 'sales') {
            $report = $reporting->getSalesReport($startDate, $endDate, $storeId);
            fputcsv($output, ['Sales Report', $startDate, 'to', $endDate]);
            fputcsv($output, []);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Transactions', $report['summary']['total_transactions']]);
            fputcsv($output, ['Total Revenue', $report['summary']['total_revenue']]);
            fputcsv($output, ['Average Transaction', $report['summary']['avg_transaction']]);
            fputcsv($output, ['Total Discounts', $report['summary']['total_discounts']]);
        } elseif ($reportType === 'products') {
            $report = $reporting->getProductPerformanceReport($startDate, $endDate, $storeId, 100);
            fputcsv($output, ['Product', 'SKU', 'Quantity Sold', 'Total Revenue', 'Times Sold']);
            foreach ($report as $product) {
                fputcsv($output, [
                    $product['name'],
                    $product['sku'],
                    $product['total_quantity_sold'],
                    $product['total_revenue'],
                    $product['times_sold']
                ]);
            }
        }
        
        fclose($output);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unsupported format']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[POS Export] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Export failed']);
}

