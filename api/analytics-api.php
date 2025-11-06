<?php
/**
 * Advanced Analytics API
 * Provides comprehensive data for analytics dashboard
 */

// Suppress warnings that might break JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', '0');

// Clear output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();

// Set JSON header early
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
                
                // Ensure all fields exist with defaults
                $data = array_merge([
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
                ], $data ?: []);
                
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'data' => $data]);
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
            
            echo json_encode(['success' => true, 'historical' => $historical, 'forecast' => $forecast]);
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
            
            echo json_encode(['success' => true, 'data' => $comparison]);
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

