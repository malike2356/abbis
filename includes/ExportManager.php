<?php
/**
 * Unified Export Manager
 * Handles all export operations across the system
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

class ExportManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Export data based on type and format
     * 
     * @param string $module Module name (reports, payroll, materials, system, etc.)
     * @param string $format Export format (csv, json, sql)
     * @param array $filters Optional filters for the export
     * @return void Outputs directly to browser
     */
    public function export($module, $format = 'csv', $filters = []) {
        switch ($module) {
            case 'reports':
            case 'field_reports':
                $this->exportReports($format, $filters);
                break;
            case 'payroll':
                $this->exportPayroll($format, $filters);
                break;
            case 'materials':
                $this->exportMaterials($format, $filters);
                break;
            case 'system':
            case 'all':
                $this->exportSystem($format, $filters);
                break;
            case 'clients':
                $this->exportClients($format, $filters);
                break;
            case 'workers':
                $this->exportWorkers($format, $filters);
                break;
            case 'analytics':
            case 'financial':
            case 'financial_overview':
                $this->exportAnalytics($format, $filters);
                break;
            default:
                throw new Exception("Unknown export module: $module");
        }
    }
    
    /**
     * Export field reports
     */
    private function exportReports($format, $filters) {
        $where = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(fr.report_date) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(fr.report_date) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['rig_id'])) {
            $where[] = "fr.rig_id = ?";
            $params[] = $filters['rig_id'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = "fr.client_id = ?";
            $params[] = $filters['client_id'];
        }
        
        $query = "SELECT 
            fr.report_id,
            fr.report_date,
            r.rig_name,
            fr.job_type,
            fr.site_name,
            c.client_name,
            fr.total_depth,
            fr.total_income,
            fr.total_expenses,
            fr.net_profit,
            fr.total_wages,
            fr.total_money_banked,
            fr.days_balance
            FROM field_reports fr
            LEFT JOIN rigs r ON fr.rig_id = r.id
            LEFT JOIN clients c ON fr.client_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fr.report_date DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Headers match the SELECT columns
        $headers = [
            'report_id', 'report_date', 'rig_name', 'job_type', 'site_name', 'client_name',
            'total_depth', 'total_income', 'total_expenses', 'net_profit',
            'total_wages', 'total_money_banked', 'days_balance'
        ];
        
        $this->output($data, $headers, 'reports', $format);
    }
    
    /**
     * Export payroll entries
     */
    private function exportPayroll($format, $filters) {
        $where = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(fr.report_date) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(fr.report_date) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['worker'])) {
            $where[] = "pe.worker_name LIKE ?";
            $params[] = "%{$filters['worker']}%";
        }
        if (!empty($filters['role'])) {
            $where[] = "pe.role = ?";
            $params[] = $filters['role'];
        }
        if (isset($filters['payment_status']) && $filters['payment_status'] !== '') {
            $where[] = "pe.paid_today = ?";
            $params[] = $filters['payment_status'];
        }
        if (!empty($filters['report_id'])) {
            $where[] = "fr.report_id = ?";
            $params[] = $filters['report_id'];
        }
        
        $query = "SELECT 
            pe.id,
            pe.worker_name,
            pe.role,
            pe.wage_type,
            pe.units,
            pe.rate,
            pe.total_amount,
            pe.paid_today,
            pe.payment_method,
            fr.report_id,
            fr.report_date,
            fr.site_name
            FROM payroll_entries pe
            LEFT JOIN field_reports fr ON pe.report_id = fr.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fr.report_date DESC, pe.worker_name";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Headers match the SELECT columns
        $headers = [
            'id', 'worker_name', 'role', 'wage_type', 'units', 'rate',
            'total_amount', 'paid_today', 'payment_method', 'report_id',
            'report_date', 'site_name'
        ];
        
        $this->output($data, $headers, 'payroll', $format);
    }
    
    /**
     * Export materials inventory
     */
    private function exportMaterials($format, $filters) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['material_type'])) {
            $where[] = "material_type LIKE ?";
            $params[] = "%{$filters['material_type']}%";
        }
        
        $query = "SELECT 
            id,
            material_type,
            material_name,
            quantity_received,
            quantity_used,
            quantity_remaining,
            unit_cost,
            total_value,
            unit_of_measure,
            supplier,
            last_updated
            FROM materials_inventory
            WHERE " . implode(' AND ', $where) . "
            ORDER BY material_name";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Headers match the SELECT columns
        $headers = [
            'id', 'material_type', 'material_name', 'quantity_received',
            'quantity_used', 'quantity_remaining', 'unit_cost', 'total_value',
            'unit_of_measure', 'supplier', 'last_updated'
        ];
        
        $this->output($data, $headers, 'materials', $format);
    }
    
    /**
     * Export system data (all tables)
     */
    private function exportSystem($format, $filters) {
        $tables = [
            'users', 'rigs', 'workers', 'clients', 'materials_inventory',
            'field_reports', 'payroll_entries', 'expense_entries',
            'rig_fee_debts', 'worker_loans', 'system_config',
            'login_attempts', 'cache_stats'
        ];
        
        $exportData = [
            'version' => '1.0',
            'export_date' => date('Y-m-d H:i:s'),
            'system_name' => 'ABBIS',
            'tables' => []
        ];
        
        foreach ($tables as $tableName) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM `$tableName`");
                $exportData['tables'][$tableName] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Table might not exist, skip it
                $exportData['tables'][$tableName] = [];
            }
        }
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="abbis_system_export_' . date('Y-m-d') . '.json"');
            echo json_encode($exportData, JSON_PRETTY_PRINT);
        } elseif ($format === 'sql') {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="abbis_system_export_' . date('Y-m-d') . '.sql"');
            echo "-- ABBIS System Export\n";
            echo "-- Export Date: " . date('Y-m-d H:i:s') . "\n\n";
            foreach ($exportData['tables'] as $tableName => $rows) {
                if (empty($rows)) continue;
                echo "-- Table: $tableName\n";
                foreach ($rows as $row) {
                    $columns = implode('`, `', array_keys($row));
                    $values = implode("', '", array_map(function($v) {
                        return addslashes($v);
                    }, array_values($row)));
                    echo "INSERT INTO `$tableName` (`$columns`) VALUES ('$values');\n";
                }
                echo "\n";
            }
        } else {
            // CSV format - export as single CSV with table name column
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="abbis_system_export_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Table', 'Data (JSON)']);
            foreach ($exportData['tables'] as $tableName => $rows) {
                fputcsv($output, [$tableName, json_encode($rows)]);
            }
            fclose($output);
        }
    }
    
    /**
     * Export clients
     */
    private function exportClients($format, $filters) {
        $query = "SELECT * FROM clients ORDER BY client_name";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $headers = array_keys($data[0] ?? []);
        $this->output($data, $headers, 'clients', $format);
    }
    
    /**
     * Export workers
     */
    private function exportWorkers($format, $filters) {
        $query = "SELECT * FROM workers ORDER BY worker_name";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $headers = array_keys($data[0] ?? []);
        $this->output($data, $headers, 'workers', $format);
    }
    
    /**
     * Export analytics data
     */
    private function exportAnalytics($format, $filters) {
        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $dateTo = $filters['date_to'] ?? date('Y-m-t');
        $groupBy = $filters['group_by'] ?? 'month';
        $rigId = $filters['rig_id'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $jobType = $filters['job_type'] ?? null;
        
        // Build WHERE clause
        $where = ["fr.report_date BETWEEN ? AND ?"];
        $params = [$dateFrom, $dateTo];
        
        if ($rigId) {
            $where[] = "fr.rig_id = ?";
            $params[] = $rigId;
        }
        if ($clientId) {
            $where[] = "fr.client_id = ?";
            $params[] = $clientId;
        }
        if ($jobType) {
            $where[] = "fr.job_type = ?";
            $params[] = $jobType;
        }
        
        // Format date group based on group_by parameter
        $dateGroup = $this->formatDateGroup($groupBy, 'fr.report_date');
        
        // Query for time series data grouped by period
        $query = "SELECT 
            $dateGroup as period,
            COUNT(*) as job_count,
            COALESCE(SUM(fr.total_income), 0) as total_revenue,
            COALESCE(SUM(fr.total_expenses), 0) as total_expenses,
            COALESCE(SUM(fr.net_profit), 0) as net_profit,
            COALESCE(SUM(fr.total_wages), 0) as total_wages,
            COALESCE(SUM(fr.materials_cost), 0) as materials_cost,
            COALESCE(SUM(fr.materials_income), 0) as materials_income,
            COALESCE(SUM(fr.total_depth), 0) as total_depth,
            COALESCE(AVG(fr.total_depth), 0) as avg_depth,
            COALESCE(SUM(fr.total_duration), 0) as total_duration,
            COALESCE(AVG(fr.total_duration), 0) as avg_duration,
            COALESCE(SUM(fr.total_rpm), 0) as total_rpm,
            COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job,
            COALESCE(AVG(fr.total_income), 0) as avg_revenue_per_job,
            (COALESCE(SUM(fr.net_profit), 0) / NULLIF(SUM(fr.total_income), 0)) * 100 as profit_margin
            FROM field_reports fr
            WHERE " . implode(' AND ', $where) . "
            GROUP BY $dateGroup
            ORDER BY period ASC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no grouped data, get overall summary
        if (empty($data)) {
            $summaryQuery = "SELECT 
                COUNT(*) as job_count,
                COALESCE(SUM(fr.total_income), 0) as total_revenue,
                COALESCE(SUM(fr.total_expenses), 0) as total_expenses,
                COALESCE(SUM(fr.net_profit), 0) as net_profit,
                COALESCE(SUM(fr.total_wages), 0) as total_wages,
                COALESCE(SUM(fr.materials_cost), 0) as materials_cost,
                COALESCE(SUM(fr.materials_income), 0) as materials_income,
                COALESCE(SUM(fr.total_depth), 0) as total_depth,
                COALESCE(AVG(fr.total_depth), 0) as avg_depth,
                COALESCE(SUM(fr.total_duration), 0) as total_duration,
                COALESCE(AVG(fr.total_duration), 0) as avg_duration,
                COALESCE(SUM(fr.total_rpm), 0) as total_rpm,
                COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job,
                COALESCE(AVG(fr.total_income), 0) as avg_revenue_per_job,
                (COALESCE(SUM(fr.net_profit), 0) / NULLIF(SUM(fr.total_income), 0)) * 100 as profit_margin
                FROM field_reports fr
                WHERE " . implode(' AND ', $where);
            
            $summaryStmt = $this->pdo->prepare($summaryQuery);
            $summaryStmt->execute($params);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($summary) {
                $summary['period'] = $dateFrom . ' to ' . $dateTo;
                $data = [$summary];
            }
        }
        
        $headers = [
            'period', 'job_count', 'total_revenue', 'total_expenses', 'net_profit',
            'total_wages', 'materials_cost', 'materials_income', 'total_depth',
            'avg_depth', 'total_duration', 'avg_duration', 'total_rpm',
            'avg_profit_per_job', 'avg_revenue_per_job', 'profit_margin'
        ];
        
        $this->output($data, $headers, 'analytics', $format);
    }
    
    /**
     * Format date group for SQL
     */
    private function formatDateGroup($groupBy, $dateField) {
        switch($groupBy) {
            case 'day':
                return "DATE_FORMAT($dateField, '%Y-%m-%d')";
            case 'week':
                return "DATE_FORMAT($dateField, '%Y-%u')";
            case 'month':
                return "DATE_FORMAT($dateField, '%Y-%m')";
            case 'quarter':
                return "CONCAT(YEAR($dateField), '-Q', QUARTER($dateField))";
            case 'year':
                return "YEAR($dateField)";
            default:
                return "DATE_FORMAT($dateField, '%Y-%m')";
        }
    }
    
    /**
     * Output data in requested format
     */
    private function output($data, $headers, $module, $format) {
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="abbis_' . $module . '_' . date('Y-m-d') . '.json"');
                echo json_encode($data, JSON_PRETTY_PRINT);
                break;
                
            case 'pdf':
                $this->outputPDF($data, $headers, $module);
                break;
                
            case 'excel':
            case 'xlsx':
                $this->outputExcel($data, $headers, $module);
                break;
                
            case 'csv':
            default:
                $this->outputCSV($data, $headers, $module);
                break;
        }
    }
    
    /**
     * Output CSV format
     */
    private function outputCSV($data, $headers, $module) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="abbis_' . $module . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers - convert snake_case to Title Case for display
        $displayHeaders = array_map(function($h) {
            return ucwords(str_replace('_', ' ', $h));
        }, $headers);
        fputcsv($output, $displayHeaders);
        
        // Data rows
        if (!empty($data)) {
            foreach ($data as $row) {
                $csvRow = [];
                foreach ($headers as $key) {
                    $value = $row[$key] ?? '';
                    // Format currency values
                    if (in_array($key, ['total_income', 'total_expenses', 'net_profit', 'total_wages', 'total_amount', 'rate', 'unit_cost', 'total_value', 'days_balance', 'total_money_banked', 'total_revenue', 'materials_cost', 'materials_income', 'avg_profit_per_job', 'avg_revenue_per_job'])) {
                        $value = is_numeric($value) ? number_format((float)$value, 2) : $value;
                    }
                    // Format percentage values
                    if (in_array($key, ['profit_margin']) && is_numeric($value)) {
                        $value = number_format((float)$value, 2) . '%';
                    }
                    $csvRow[] = $value;
                }
                fputcsv($output, $csvRow);
            }
        }
        
        fclose($output);
    }
    
    /**
     * Output Excel format (Excel-compatible CSV with proper formatting)
     */
    private function outputExcel($data, $headers, $module) {
        // Excel can read CSV files, so we use CSV with Excel MIME type
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="abbis_' . $module . '_' . date('Y-m-d') . '.xls"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        $displayHeaders = array_map(function($h) {
            return ucwords(str_replace('_', ' ', $h));
        }, $headers);
        fputcsv($output, $displayHeaders, "\t"); // Tab-separated for better Excel compatibility
        
        // Data rows
        if (!empty($data)) {
            foreach ($data as $row) {
                $excelRow = [];
                foreach ($headers as $key) {
                    $value = $row[$key] ?? '';
                    // Format currency values
                    if (in_array($key, ['total_income', 'total_expenses', 'net_profit', 'total_wages', 'total_amount', 'rate', 'unit_cost', 'total_value', 'days_balance', 'total_money_banked', 'total_revenue', 'materials_cost', 'materials_income', 'avg_profit_per_job', 'avg_revenue_per_job'])) {
                        $value = is_numeric($value) ? number_format((float)$value, 2) : $value;
                    }
                    // Format percentage values
                    if (in_array($key, ['profit_margin']) && is_numeric($value)) {
                        $value = number_format((float)$value, 2) . '%';
                    }
                    // Escape formulas that start with =, +, -, @
                    if (is_string($value) && in_array(substr($value, 0, 1), ['=', '+', '-', '@'])) {
                        $value = "'" . $value; // Prepend quote to prevent formula execution
                    }
                    $excelRow[] = $value;
                }
                fputcsv($output, $excelRow, "\t");
            }
        }
        
        fclose($output);
    }
    
    /**
     * Output PDF format (HTML-based PDF)
     */
    private function outputPDF($data, $headers, $module) {
        // Get company info for header
        $companyName = 'ABBIS';
        $companyLogo = '';
        try {
            $configStmt = $this->pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
            $company = $configStmt->fetch();
            if ($company) {
                $companyName = $company['config_value'] ?: 'ABBIS';
            }
        } catch (PDOException $e) {
            // Use default
        }
        
        // Convert headers to display format
        $displayHeaders = array_map(function($h) {
            return ucwords(str_replace('_', ' ', $h));
        }, $headers);
        
        // Generate HTML
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($module); ?> Report - <?php echo date('Y-m-d'); ?></title>
    <style>
        @page {
            margin: 20mm;
            size: A4;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            font-size: 12px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }
        th {
            background-color: #0ea5e9;
            color: white;
            padding: 8px 4px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        td {
            padding: 6px 4px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .currency {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($companyName); ?></h1>
        <p><?php echo ucwords(str_replace('_', ' ', $module)); ?> Report</p>
        <p>Generated: <?php echo date('F d, Y H:i:s'); ?></p>
    </div>
    
    <?php if (!empty($data)): ?>
    <table>
        <thead>
            <tr>
                <?php foreach ($displayHeaders as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($headers as $key): ?>
                        <td class="<?php echo in_array($key, ['total_income', 'total_expenses', 'net_profit', 'total_wages', 'total_amount', 'rate', 'unit_cost', 'total_value', 'days_balance', 'total_money_banked']) ? 'currency' : ''; ?>">
                            <?php 
                            $value = $row[$key] ?? '';
                            if (in_array($key, ['total_income', 'total_expenses', 'net_profit', 'total_wages', 'total_amount', 'rate', 'unit_cost', 'total_value', 'days_balance', 'total_money_banked', 'total_revenue', 'materials_cost', 'materials_income', 'avg_profit_per_job', 'avg_revenue_per_job']) && is_numeric($value)) {
                                echo 'GHS ' . number_format((float)$value, 2);
                            } elseif (in_array($key, ['profit_margin']) && is_numeric($value)) {
                                echo number_format((float)$value, 2) . '%';
                            } else {
                                echo htmlspecialchars($value);
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php 
    // Calculate summary for financial data
    $financialFields = ['total_income', 'total_expenses', 'net_profit', 'total_wages', 'total_amount', 'total_revenue', 'materials_cost', 'materials_income'];
    $hasFinancial = false;
    $totals = [];
    foreach ($financialFields as $field) {
        if (in_array($field, $headers)) {
            $hasFinancial = true;
            $totals[$field] = 0;
            foreach ($data as $row) {
                $totals[$field] += (float)($row[$field] ?? 0);
            }
        }
    }
    if ($hasFinancial && !empty($totals)):
    ?>
    <div class="summary">
        <h3 style="margin-top: 0;">Summary</h3>
        <?php foreach ($totals as $field => $total): ?>
            <div class="summary-row">
                <span><?php echo ucwords(str_replace('_', ' ', $field)); ?>:</span>
                <strong>GHS <?php echo number_format($total, 2); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
        <p style="text-align: center; padding: 20px;">No data available for export.</p>
    <?php endif; ?>
    
    <div class="footer">
        <p>This report was generated by ABBIS System on <?php echo date('F d, Y \a\t H:i:s'); ?></p>
        <p>Total Records: <?php echo count($data); ?></p>
    </div>
</body>
</html>
        <?php
        $html = ob_get_clean();
        
        // Output as PDF using browser's print-to-PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="abbis_' . $module . '_' . date('Y-m-d') . '.html"');
        
        // Add JavaScript to trigger print dialog
        echo $html;
        echo '<script>
            window.onload = function() {
                window.print();
            };
        </script>';
    }
}
