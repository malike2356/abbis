<?php
/**
 * Advanced Analytics API
 * Provides comprehensive data for analytics dashboard
 */

// Suppress all output and errors that might break JSON
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start output buffering to catch any unwanted output
ob_start();

// Clear any existing output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Start fresh output buffer
ob_start();

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();

// Clear any output that might have been generated during includes
ob_clean();

// Set JSON header early
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
}

$pdo = getDBConnection();
$type = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$groupBy = $_GET['group_by'] ?? 'month'; // day, week, month, quarter, year
$rigId = $_GET['rig_id'] ?? null;
$clientId = $_GET['client_id'] ?? null;
$jobType = $_GET['job_type'] ?? null;

function formatDateGroup($groupBy, $dateField) {
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

function buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType) {
    $conditions = ["fr.report_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($rigId) {
        $conditions[] = "fr.rig_id = ?";
        $params[] = $rigId;
    }
    
    if ($clientId) {
        $conditions[] = "fr.client_id = ?";
        $params[] = $clientId;
    }
    
    if ($jobType) {
        $conditions[] = "fr.job_type = ?";
        $params[] = $jobType;
    }
    
    return ['WHERE ' . implode(' AND ', $conditions), $params];
}

try {
    switch($type) {
        case 'time_series':
            // Time series data with multiple metrics
            $dateGroup = formatDateGroup($groupBy, 'fr.report_date');
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            $stmt = $pdo->prepare("
                SELECT 
                    $dateGroup as period,
                    COALESCE(SUM(fr.total_income), 0) as revenue,
                    COALESCE(SUM(fr.total_expenses), 0) as expenses,
                    COALESCE(SUM(fr.net_profit), 0) as profit,
                    COALESCE(SUM(fr.total_wages), 0) as wages,
                    COALESCE(SUM(fr.materials_cost), 0) as materials_cost,
                    COALESCE(SUM(fr.materials_income), 0) as materials_income,
                    COUNT(*) as job_count,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job,
                    COALESCE(SUM(fr.total_depth), 0) as total_depth,
                    COALESCE(AVG(fr.total_duration), 0) as avg_duration
                FROM field_reports fr
                $whereClause
                GROUP BY $dateGroup
                ORDER BY period ASC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'financial_overview':
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(fr.total_income), 0) as total_revenue,
                        COALESCE(SUM(fr.total_expenses), 0) as total_expenses,
                        COALESCE(SUM(fr.net_profit), 0) as total_profit,
                        COALESCE(SUM(fr.total_wages), 0) as total_wages,
                        COALESCE(SUM(fr.materials_cost), 0) as total_materials_cost,
                        COALESCE(SUM(fr.materials_income), 0) as total_materials_income,
                        COALESCE(SUM(fr.bank_deposit), 0) as total_deposits,
                        COALESCE(SUM(fr.cash_received), 0) as total_cash_received,
                        COALESCE(SUM(fr.outstanding_rig_fee), 0) as total_outstanding_fees,
                        COUNT(*) as total_jobs,
                        COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job,
                        COALESCE(AVG(fr.total_income), 0) as avg_revenue_per_job,
                        (COALESCE(SUM(fr.net_profit), 0) / NULLIF(SUM(fr.total_income), 0)) * 100 as profit_margin
                    FROM field_reports fr
                    $whereClause
                ");
                $stmt->execute($params);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Ensure all fields exist with defaults and are properly typed
                $defaults = [
                    'total_revenue' => 0,
                    'total_expenses' => 0,
                    'total_profit' => 0,
                    'total_wages' => 0,
                    'total_materials_cost' => 0,
                    'total_materials_income' => 0,
                    'total_deposits' => 0,
                    'total_cash_received' => 0,
                    'total_outstanding_fees' => 0,
                    'total_jobs' => 0,
                    'avg_profit_per_job' => 0,
                    'avg_revenue_per_job' => 0,
                    'profit_margin' => 0
                ];
                
                if (!$data || !is_array($data)) {
                    $data = $defaults;
                } else {
                    // Merge and ensure numeric types
                    $data = array_merge($defaults, $data);
                    foreach ($data as $key => $value) {
                        if (in_array($key, ['total_jobs'])) {
                            $data[$key] = (int)$value;
                        } else {
                            $data[$key] = (float)$value;
                        }
                    }
                }
                
                // Clear any output
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
                exit;
            } catch (PDOException $e) {
                error_log('Financial overview query error: ' . $e->getMessage());
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                echo json_encode([
                    'success' => false,
                    'message' => 'Database query error: ' . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            break;
            
        case 'rig_performance':
            // Build WHERE clause without rig filter (we want all rigs)
            // We need to manually build conditions for the LEFT JOIN
            $conditions = [];
            $params = [];
            
            // Date filter
            $conditions[] = "fr.report_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            
            // Client filter
            if ($clientId) {
                $conditions[] = "fr.client_id = ?";
                $params[] = $clientId;
            }
            
            // Job type filter
            if ($jobType) {
                $conditions[] = "fr.job_type = ?";
                $params[] = $jobType;
            }
            
            // Build JOIN condition
            $joinCondition = !empty($conditions) ? "AND " . implode(' AND ', $conditions) : "";
            
            $stmt = $pdo->prepare("
                SELECT 
                    r.id,
                    r.rig_name,
                    r.rig_code,
                    r.status,
                    COUNT(fr.id) as job_count,
                    COALESCE(SUM(fr.total_income), 0) as total_revenue,
                    COALESCE(SUM(fr.net_profit), 0) as total_profit,
                    COALESCE(SUM(fr.total_expenses), 0) as total_expenses,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit,
                    COALESCE(AVG(fr.total_income), 0) as avg_revenue,
                    COALESCE(SUM(fr.total_depth), 0) as total_depth,
                    COALESCE(AVG(fr.total_depth), 0) as avg_depth,
                    COALESCE(SUM(fr.total_duration), 0) as total_duration,
                    COALESCE(AVG(fr.total_duration), 0) as avg_duration,
                    COALESCE(SUM(fr.total_rpm), 0) as total_rpm,
                    (COALESCE(SUM(fr.net_profit), 0) / NULLIF(SUM(fr.total_income), 0)) * 100 as profit_margin,
                    (COALESCE(SUM(fr.net_profit), 0) / NULLIF(COUNT(fr.id), 0)) as profit_per_job
                FROM rigs r
                LEFT JOIN field_reports fr ON r.id = fr.rig_id $joinCondition
                WHERE r.status = 'active'
                GROUP BY r.id, r.rig_name, r.rig_code, r.status
                ORDER BY total_profit DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'client_analysis':
            // Build conditions manually for LEFT JOIN
            $conditions = [];
            $params = [];
            
            // Date filter
            $conditions[] = "fr.report_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            
            // Rig filter
            if ($rigId) {
                $conditions[] = "fr.rig_id = ?";
                $params[] = $rigId;
            }
            
            // Job type filter
            if ($jobType) {
                $conditions[] = "fr.job_type = ?";
                $params[] = $jobType;
            }
            
            // Build WHERE clause for JOIN
            $joinCondition = !empty($conditions) ? "AND " . implode(' AND ', $conditions) : "";
            
            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    c.client_name,
                    COUNT(fr.id) as job_count,
                    COALESCE(SUM(fr.total_income), 0) as total_revenue,
                    COALESCE(SUM(fr.net_profit), 0) as total_profit,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit,
                    COALESCE(AVG(fr.total_income), 0) as avg_revenue,
                    MIN(fr.report_date) as first_job_date,
                    MAX(fr.report_date) as last_job_date,
                    (COALESCE(SUM(fr.net_profit), 0) / NULLIF(SUM(fr.total_income), 0)) * 100 as profit_margin
                FROM clients c
                LEFT JOIN field_reports fr ON c.id = fr.client_id $joinCondition
                GROUP BY c.id, c.client_name
                HAVING job_count > 0
                ORDER BY total_revenue DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'job_type_analysis':
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, null);
            
            $stmt = $pdo->prepare("
                SELECT 
                    job_type,
                    COUNT(*) as job_count,
                    SUM(total_income) as total_revenue,
                    SUM(net_profit) as total_profit,
                    AVG(net_profit) as avg_profit,
                    AVG(total_income) as avg_revenue,
                    (SUM(net_profit) / NULLIF(SUM(total_income), 0)) * 100 as profit_margin
                FROM field_reports fr
                $whereClause
                GROUP BY job_type
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'worker_productivity':
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            $stmt = $pdo->prepare("
                SELECT 
                    pe.worker_name,
                    pe.role,
                    COUNT(DISTINCT pe.report_id) as jobs_worked,
                    COALESCE(SUM(pe.amount), 0) as total_earnings,
                    COALESCE(AVG(pe.amount), 0) as avg_earnings_per_job,
                    COALESCE(SUM(pe.benefits), 0) as total_benefits,
                    COALESCE(SUM(pe.loan_reclaim), 0) as total_loans_reclaimed
                FROM payroll_entries pe
                INNER JOIN field_reports fr ON pe.report_id = fr.id
                $whereClause
                GROUP BY pe.worker_name, pe.role
                ORDER BY total_earnings DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'materials_analysis':
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(fr.materials_provided_by, 'Unknown') as materials_provided_by,
                    COUNT(*) as job_count,
                    COALESCE(SUM(fr.materials_cost), 0) as total_cost,
                    COALESCE(SUM(fr.materials_income), 0) as total_income,
                    COALESCE(AVG(fr.materials_cost), 0) as avg_cost,
                    COALESCE(AVG(fr.materials_income), 0) as avg_income,
                    COALESCE(SUM(fr.screen_pipes_used), 0) as total_screen_pipes,
                    COALESCE(SUM(fr.plain_pipes_used), 0) as total_plain_pipes,
                    COALESCE(SUM(fr.gravel_used), 0) as total_gravel
                FROM field_reports fr
                $whereClause
                GROUP BY fr.materials_provided_by
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'operational_metrics':
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(AVG(fr.total_duration), 0) as avg_job_duration_minutes,
                    COALESCE(AVG(fr.total_depth), 0) as avg_depth,
                    COALESCE(SUM(fr.total_depth), 0) as total_depth,
                    COALESCE(AVG(fr.rods_used), 0) as avg_rods_per_job,
                    COALESCE(SUM(fr.rods_used), 0) as total_rods_used,
                    COALESCE(AVG(fr.total_workers), 0) as avg_workers_per_job,
                    COUNT(DISTINCT fr.rig_id) as active_rigs,
                    COUNT(DISTINCT DATE(fr.report_date)) as working_days,
                    COUNT(*) as total_jobs,
                    COUNT(*) / NULLIF(COUNT(DISTINCT DATE(fr.report_date)), 0) as jobs_per_day
                FROM field_reports fr
                $whereClause
            ");
            $stmt->execute($params);
            $data = $stmt->fetch();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'regional_analysis':
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(fr.region, 'Unknown') as region,
                    COUNT(*) as job_count,
                    COALESCE(SUM(fr.total_income), 0) as total_revenue,
                    COALESCE(SUM(fr.net_profit), 0) as total_profit,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit,
                    COALESCE(AVG(fr.total_depth), 0) as avg_depth
                FROM field_reports fr
                $whereClause
                GROUP BY fr.region
                ORDER BY total_revenue DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'trend_forecast':
            // Simple linear regression for forecasting
            list($whereClause, $params) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            
            $dateGroup = formatDateGroup($groupBy, 'fr.report_date');
            $stmt = $pdo->prepare("
                SELECT 
                    $dateGroup as period,
                    SUM(fr.net_profit) as profit,
                    COUNT(*) as jobs
                FROM field_reports fr
                $whereClause
                GROUP BY $dateGroup
                ORDER BY period ASC
            ");
            $stmt->execute($params);
            $historical = $stmt->fetchAll();
            
            // Simple linear regression
            $n = count($historical);
            $forecast = [];
            
            if ($n >= 3) {
                $x = range(1, $n);
                $y = array_column($historical, 'profit');
                
                $sumX = array_sum($x);
                $sumY = array_sum($y);
                $sumXY = 0;
                $sumX2 = 0;
                
                for ($i = 0; $i < $n; $i++) {
                    $sumXY += $x[$i] * $y[$i];
                    $sumX2 += $x[$i] * $x[$i];
                }
                
                $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
                $intercept = ($sumY - $slope * $sumX) / $n;
                
                // Forecast next 3 periods
                for ($i = 1; $i <= 3; $i++) {
                    $forecast[] = [
                        'period' => 'Forecast ' . $i,
                        'profit' => $intercept + $slope * ($n + $i),
                        'is_forecast' => true
                    ];
                }
            }
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'historical' => $historical, 'forecast' => $forecast], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'comparative_analysis':
            // Compare current period vs previous period
            $dateGroup = formatDateGroup($groupBy, 'fr.report_date');
            
            // Current period
            list($whereCurrent, $paramsCurrent) = buildWhereClause($startDate, $endDate, $rigId, $clientId, $jobType);
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(fr.total_income), 0) as revenue,
                    COALESCE(SUM(fr.net_profit), 0) as profit,
                    COUNT(*) as jobs,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit
                FROM field_reports fr
                $whereCurrent
            ");
            $stmt->execute($paramsCurrent);
            $current = $stmt->fetch();
            
            // Previous period (same duration before)
            $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
            $startDatePrev = date('Y-m-d', strtotime($startDate . " -$daysDiff days"));
            $endDatePrev = date('Y-m-d', strtotime($startDate . ' -1 day'));
            
            list($wherePrev, $paramsPrev) = buildWhereClause($startDatePrev, $endDatePrev, $rigId, $clientId, $jobType);
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(fr.total_income), 0) as revenue,
                    COALESCE(SUM(fr.net_profit), 0) as profit,
                    COUNT(*) as jobs,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit
                FROM field_reports fr
                $wherePrev
            ");
            $stmt->execute($paramsPrev);
            $previous = $stmt->fetch();
            
            // Calculate changes
            $comparison = [
                'current' => $current,
                'previous' => $previous,
                'changes' => [
                    'revenue_change' => $previous['revenue'] > 0 
                        ? (($current['revenue'] - $previous['revenue']) / $previous['revenue']) * 100 
                        : 0,
                    'profit_change' => $previous['profit'] != 0 
                        ? (($current['profit'] - $previous['profit']) / abs($previous['profit'])) * 100 
                        : 0,
                    'jobs_change' => $previous['jobs'] > 0 
                        ? (($current['jobs'] - $previous['jobs']) / $previous['jobs']) * 100 
                        : 0
                ]
            ];
            
            // Clear any output before JSON
            ob_clean();
            echo json_encode(['success' => true, 'data' => $comparison], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'pos_sales_trend':
            // POS Sales Revenue Trend
            $dateGroup = formatDateGroup($groupBy, 's.sale_timestamp');
            $conditions = ["s.sale_timestamp BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            $stmt = $pdo->prepare("
                SELECT 
                    $dateGroup as period,
                    COALESCE(SUM(s.total_amount), 0) as total_sales,
                    COALESCE(SUM(s.discount_amount), 0) as total_discounts,
                    COALESCE(SUM(s.tax_amount), 0) as total_tax,
                    COUNT(DISTINCT s.id) as transaction_count,
                    COALESCE(AVG(s.total_amount), 0) as avg_transaction_value,
                    COALESCE(SUM(CASE WHEN s.payment_method = 'cash' THEN s.total_amount ELSE 0 END), 0) as cash_sales,
                    COALESCE(SUM(CASE WHEN s.payment_method = 'card' THEN s.total_amount ELSE 0 END), 0) as card_sales,
                    COALESCE(SUM(CASE WHEN s.payment_method = 'momo' THEN s.total_amount ELSE 0 END), 0) as momo_sales
                FROM pos_sales s
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY $dateGroup
                ORDER BY period ASC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'pos_payment_methods':
            // POS Payment Methods Breakdown
            $conditions = ["s.sale_timestamp BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(s.payment_method, 'unknown') as payment_method,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(s.total_amount), 0) as total_amount,
                    COALESCE(AVG(s.total_amount), 0) as avg_amount
                FROM pos_sales s
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY s.payment_method
                ORDER BY total_amount DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'pos_top_products':
            // Top Selling Products
            $conditions = ["s.sale_timestamp BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.sku,
                    COALESCE(SUM(si.quantity), 0) as total_quantity_sold,
                    COALESCE(SUM(si.line_total), 0) as total_revenue,
                    COALESCE(AVG(si.unit_price), 0) as avg_price,
                    COUNT(DISTINCT si.sale_id) as times_sold
                FROM pos_products p
                INNER JOIN pos_sale_items si ON p.id = si.product_id
                INNER JOIN pos_sales s ON si.sale_id = s.id
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY p.id, p.name, p.sku
                ORDER BY total_revenue DESC
                LIMIT 20
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'pos_cashier_performance':
            // Cashier Performance
            $conditions = ["s.sale_timestamp BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    COALESCE(u.full_name, u.username) as cashier_name,
                    COUNT(DISTINCT s.id) as transaction_count,
                    COALESCE(SUM(s.total_amount), 0) as total_sales,
                    COALESCE(AVG(s.total_amount), 0) as avg_transaction,
                    COALESCE(SUM(s.discount_amount), 0) as total_discounts,
                    COALESCE(SUM(CASE WHEN r.id IS NOT NULL THEN r.refund_amount ELSE 0 END), 0) as total_refunds
                FROM pos_sales s
                INNER JOIN users u ON s.cashier_id = u.id
                LEFT JOIN pos_refunds r ON r.sale_id = s.id AND r.refund_status = 'completed'
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY u.id, u.full_name, u.username
                ORDER BY total_sales DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'pos_store_performance':
            // Store Performance
            $conditions = ["s.sale_timestamp BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            $stmt = $pdo->prepare("
                SELECT 
                    st.id,
                    st.store_name,
                    COUNT(DISTINCT s.id) as transaction_count,
                    COALESCE(SUM(s.total_amount), 0) as total_sales,
                    COALESCE(AVG(s.total_amount), 0) as avg_transaction,
                    COUNT(DISTINCT s.cashier_id) as cashiers_count
                FROM pos_stores st
                LEFT JOIN pos_sales s ON st.id = s.store_id AND s.sale_timestamp BETWEEN ? AND ?
                WHERE st.is_active = 1
                GROUP BY st.id, st.store_name
                ORDER BY total_sales DESC
            ");
            $stmt->execute(array_merge($params, $params));
            $data = $stmt->fetchAll();
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'cms_orders_trend':
            // CMS Orders Trend
            $dateGroup = formatDateGroup($groupBy, 'o.created_at');
            $conditions = ["o.created_at BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        $dateGroup as period,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        COALESCE(AVG(o.total_amount), 0) as avg_order_value,
                        COUNT(DISTINCT o.customer_id) as unique_customers
                    FROM cms_orders o
                    WHERE " . implode(' AND ', $conditions) . "
                    GROUP BY $dateGroup
                    ORDER BY period ASC
                ");
                $stmt->execute($params);
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                // Table might not exist
                $data = [];
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'cms_quote_requests':
            // CMS Quote Requests
            $dateGroup = formatDateGroup($groupBy, 'qr.created_at');
            $conditions = ["qr.created_at BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        $dateGroup as period,
                        COUNT(*) as request_count,
                        COUNT(CASE WHEN qr.status = 'converted' THEN 1 END) as converted_count,
                        COUNT(CASE WHEN qr.status = 'pending' THEN 1 END) as pending_count
                    FROM cms_quote_requests qr
                    WHERE " . implode(' AND ', $conditions) . "
                    GROUP BY $dateGroup
                    ORDER BY period ASC
                ");
                $stmt->execute($params);
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                $data = [];
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'inventory_value_trend':
            // Inventory Value Trend
            $dateGroup = formatDateGroup($groupBy, 'it.created_at');
            $conditions = ["it.created_at BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        $dateGroup as period,
                        COALESCE(SUM(mi.total_value), 0) as total_inventory_value,
                        COUNT(DISTINCT mi.material_type) as material_types_count,
                        COALESCE(SUM(CASE WHEN it.transaction_type = 'in' THEN it.total_cost ELSE 0 END), 0) as inventory_added,
                        COALESCE(SUM(CASE WHEN it.transaction_type = 'out' THEN it.total_cost ELSE 0 END), 0) as inventory_used
                    FROM materials_inventory mi
                    LEFT JOIN inventory_transactions it ON DATE(it.created_at) = DATE(mi.last_updated)
                    WHERE it.created_at BETWEEN ? AND ? OR it.created_at IS NULL
                    GROUP BY $dateGroup
                    ORDER BY period ASC
                ");
                $stmt->execute(array_merge($params, $params));
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                // Fallback to simpler query
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            DATE_FORMAT(CURDATE(), '%Y-%m') as period,
                            COALESCE(SUM(total_value), 0) as total_inventory_value,
                            COUNT(*) as material_types_count
                        FROM materials_inventory
                    ");
                    $stmt->execute();
                    $data = $stmt->fetchAll();
                } catch (PDOException $e2) {
                    $data = [];
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'inventory_material_usage':
            // Material Usage by Type
            $conditions = ["fr.report_date BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            $stmt = $pdo->prepare("
                SELECT 
                    'Screen Pipes' as material_type,
                    COALESCE(SUM(fr.screen_pipes_used), 0) as quantity_used,
                    COALESCE(SUM(fr.materials_cost), 0) as total_cost
                FROM field_reports fr
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY 'Screen Pipes'
                
                UNION ALL
                
                SELECT 
                    'Plain Pipes' as material_type,
                    COALESCE(SUM(fr.plain_pipes_used), 0) as quantity_used,
                    COALESCE(SUM(fr.materials_cost), 0) as total_cost
                FROM field_reports fr
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY 'Plain Pipes'
                
                UNION ALL
                
                SELECT 
                    'Gravel' as material_type,
                    COALESCE(SUM(fr.gravel_used), 0) as quantity_used,
                    COALESCE(SUM(fr.materials_cost), 0) as total_cost
                FROM field_reports fr
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY 'Gravel'
            ");
            $stmt->execute(array_merge($params, $params, $params));
            $data = $stmt->fetchAll();
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'accounting_journal_entries':
            // Accounting Journal Entries Trend
            $dateGroup = formatDateGroup($groupBy, 'je.entry_date');
            $conditions = ["je.entry_date BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        $dateGroup as period,
                        COUNT(DISTINCT je.id) as entry_count,
                        COALESCE(SUM(jl.debit), 0) as total_debits,
                        COALESCE(SUM(jl.credit), 0) as total_credits,
                        COALESCE(SUM(jl.debit - jl.credit), 0) as net_amount
                    FROM journal_entries je
                    INNER JOIN journal_entry_lines jl ON je.id = jl.journal_entry_id
                    WHERE " . implode(' AND ', $conditions) . "
                    GROUP BY $dateGroup
                    ORDER BY period ASC
                ");
                $stmt->execute($params);
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                $data = [];
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'accounting_account_balances':
            // Account Balances by Type
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        co.account_type,
                        COUNT(DISTINCT co.id) as account_count,
                        COALESCE(SUM(
                            CASE 
                                WHEN co.account_type IN ('Asset', 'Expense') THEN (jl.debit - jl.credit)
                                ELSE (jl.credit - jl.debit)
                            END
                        ), 0) as total_balance
                    FROM chart_of_accounts co
                    LEFT JOIN journal_entry_lines jl ON co.id = jl.account_id
                    WHERE co.is_active = 1
                    GROUP BY co.account_type
                    ORDER BY total_balance DESC
                ");
                $stmt->execute();
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                $data = [];
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        case 'crm_followups':
            // CRM Follow-ups
            $dateGroup = formatDateGroup($groupBy, 'cf.scheduled_date');
            $conditions = ["cf.scheduled_date BETWEEN ? AND ?"];
            $params = [$startDate, $endDate];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        $dateGroup as period,
                        COUNT(*) as followup_count,
                        COUNT(CASE WHEN cf.status = 'completed' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN cf.status = 'scheduled' THEN 1 END) as scheduled_count,
                        COUNT(CASE WHEN cf.status = 'overdue' THEN 1 END) as overdue_count
                    FROM client_followups cf
                    WHERE " . implode(' AND ', $conditions) . "
                    GROUP BY $dateGroup
                    ORDER BY period ASC
                ");
                $stmt->execute($params);
                $data = $stmt->fetchAll();
            } catch (PDOException $e) {
                $data = [];
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
            break;
            
        default:
            // Clear any output
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo json_encode(['success' => false, 'message' => 'Invalid analytics type: ' . htmlspecialchars($type)]);
            exit;
    }
} catch (PDOException $e) {
    // Clear any output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Analytics API Database Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please check server logs or try again.',
        'error' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    exit;
} catch (Exception $e) {
    // Clear any output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Analytics API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching analytics data. Please check server logs.',
        'error' => $e->getMessage()
    ]);
    exit;
}

