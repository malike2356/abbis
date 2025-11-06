<?php
/**
 * Dashboard Data Export API
 * Exports dashboard metrics and KPIs in various formats
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAuth();

$format = $_GET['format'] ?? 'csv';
$section = $_GET['section'] ?? 'overview'; // overview, financial, operational, all
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

$pdo = getDBConnection();
$abbis = new ABBISFunctions($pdo);

try {
    $stats = $abbis->getDashboardStats(false);
    
    // Prepare data based on section
    $exportData = [];
    $filename = 'dashboard_export_' . date('Y-m-d');
    
    switch ($section) {
        case 'financial':
            $exportData = [
                'Financial Health' => $stats['financial_health'] ?? [],
                'Overall Financials' => $stats['overall'] ?? [],
                'Balance Sheet' => $stats['balance_sheet'] ?? [],
                'Cash Flow (30 days)' => $stats['cash_flow'] ?? [],
                'Today' => $stats['today'] ?? [],
                'This Month' => $stats['this_month'] ?? [],
                'This Year' => $stats['this_year'] ?? [],
                'Growth' => $stats['growth'] ?? [],
            ];
            $filename = 'dashboard_financial_' . date('Y-m-d');
            break;
            
        case 'operational':
            $exportData = [
                'Operational Metrics' => $stats['operational'] ?? [],
                'Top Clients' => $stats['top_clients'] ?? [],
                'Top Rigs' => $stats['top_rigs'] ?? [],
                'Job Types' => $stats['job_types'] ?? [],
            ];
            $filename = 'dashboard_operational_' . date('Y-m-d');
            break;
            
        case 'all':
        default:
            $exportData = [
                'Financial Health' => $stats['financial_health'] ?? [],
                'Overall Financials' => $stats['overall'] ?? [],
                'Balance Sheet' => $stats['balance_sheet'] ?? [],
                'Cash Flow' => $stats['cash_flow'] ?? [],
                'Today' => $stats['today'] ?? [],
                'This Month' => $stats['this_month'] ?? [],
                'This Year' => $stats['this_year'] ?? [],
                'Growth' => $stats['growth'] ?? [],
                'Operational' => $stats['operational'] ?? [],
                'Loans' => $stats['loans'] ?? [],
                'Materials' => $stats['materials'] ?? [],
            ];
            break;
    }
    
    // Export based on format
    switch (strtolower($format)) {
        case 'csv':
            exportAsCSV($exportData, $filename);
            break;
            
        case 'json':
            exportAsJSON($exportData, $filename);
            break;
            
        case 'excel':
        case 'xlsx':
            exportAsExcel($exportData, $filename);
            break;
            
        case 'pdf':
            exportAsPDF($exportData, $filename);
            break;
            
        default:
            throw new Exception("Unsupported format: $format");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function exportAsCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    foreach ($data as $section => $values) {
        fputcsv($output, [$section]);
        
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    // If it's an array of records
                    if (!empty($value)) {
                        // Write headers
                        fputcsv($output, array_keys($value[0]));
                        // Write data
                        foreach ($value as $row) {
                            fputcsv($output, array_values($row));
                        }
                    }
                } else {
                    fputcsv($output, [ucwords(str_replace('_', ' ', $key)), formatNumber($value)]);
                }
            }
        }
        
        fputcsv($output, []); // Empty row between sections
    }
    
    fclose($output);
}

function exportAsJSON($data, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exportAsExcel($data, $filename) {
    // Simple Excel export using CSV with Excel-compatible format
    // For full Excel support, you'd need PhpSpreadsheet library
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    exportAsCSV($data, $filename);
}

function exportAsPDF($data, $filename) {
    // Simple PDF export - for full PDF support, use TCPDF or FPDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Dashboard Export</title>';
    echo '<style>body{font-family:Arial;padding:20px;}table{border-collapse:collapse;width:100%;margin-bottom:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f2f2f2;}</style></head><body>';
    echo '<h1>Dashboard Export - ' . date('Y-m-d H:i:s') . '</h1>';
    
    foreach ($data as $section => $values) {
        echo '<h2>' . htmlspecialchars($section) . '</h2>';
        
        if (is_array($values)) {
            echo '<table>';
            
            if (!empty($values) && is_array($values[0] ?? null)) {
                // Array of records
                echo '<tr>';
                foreach (array_keys($values[0]) as $header) {
                    echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>';
                }
                echo '</tr>';
                
                foreach ($values as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . htmlspecialchars(formatNumber($cell)) . '</td>';
                    }
                    echo '</tr>';
                }
            } else {
                // Key-value pairs
                echo '<tr><th>Metric</th><th>Value</th></tr>';
                foreach ($values as $key => $value) {
                    echo '<tr><td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . '</td>';
                    echo '<td>' . htmlspecialchars(formatNumber($value)) . '</td></tr>';
                }
            }
            
            echo '</table>';
        }
    }
    
    echo '</body></html>';
}

function formatNumber($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2);
    }
    return $value;
}

