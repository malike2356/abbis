<?php
/**
 * Looker Studio (Google Data Studio) Integration API
 * Provides data for visualization and reporting in Looker Studio
 * 
 * Supports:
 * - Real-time data queries
 * - Multiple data sources
 * - Custom metrics and dimensions
 * - Filtered data sets
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

// CORS headers for Looker Studio
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * API Key Authentication (optional, can also use session)
 */
function authenticateRequest() {
    // Check for API key
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    
    if (!empty($apiKey)) {
        $pdo = getDBConnection();
        try {
            $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1");
            $stmt->execute([$apiKey]);
            if ($stmt->fetch()) {
                return true;
            }
        } catch (PDOException $e) {
            // API keys table might not exist
        }
    }
    
    // Fallback to session authentication
    global $auth;
    return $auth->isLoggedIn();
}

if (!authenticateRequest()) {
    jsonResponse([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Authentication required'
    ], 401);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'data';
$dataSource = $_GET['data_source'] ?? '';

try {
    switch ($action) {
        case 'data':
            // Main data endpoint for Looker Studio
            $response = getLookerStudioData($pdo, $dataSource);
            jsonResponse($response);
            break;
            
        case 'schema':
            // Schema definition for Looker Studio
            $response = getLookerStudioSchema($dataSource);
            jsonResponse($response);
            break;
            
        case 'metrics':
            // Available metrics
            $response = getAvailableMetrics();
            jsonResponse($response);
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'message' => 'Invalid action',
                'available_actions' => ['data', 'schema', 'metrics']
            ], 400);
    }
} catch (Exception $e) {
    error_log("Looker Studio API error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

/**
 * Get data for Looker Studio
 */
function getLookerStudioData($pdo, $dataSource) {
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $filters = $_GET['filters'] ?? [];
    
    $data = [];
    
    switch ($dataSource) {
        case 'field_reports':
        case 'reports':
            $data = getFieldReportsData($pdo, $startDate, $endDate, $filters);
            break;
            
        case 'financial':
        case 'finance':
            $data = getFinancialData($pdo, $startDate, $endDate, $filters);
            break;
            
        case 'clients':
            $data = getClientsData($pdo, $filters);
            break;
            
        case 'workers':
        case 'payroll':
            $data = getWorkersData($pdo, $startDate, $endDate, $filters);
            break;
            
        case 'materials':
        case 'inventory':
            $data = getMaterialsData($pdo, $filters);
            break;
            
        case 'operational':
        case 'operations':
            $data = getOperationalData($pdo, $startDate, $endDate, $filters);
            break;
            
        default:
            // Return all data sources
            $data = [
                'field_reports' => getFieldReportsData($pdo, $startDate, $endDate, []),
                'financial' => getFinancialData($pdo, $startDate, $endDate, []),
                'clients' => getClientsData($pdo, []),
                'workers' => getWorkersData($pdo, $startDate, $endDate, []),
                'materials' => getMaterialsData($pdo, [])
            ];
    }
    
    return [
        'success' => true,
        'data' => $data,
        'meta' => [
            'data_source' => $dataSource,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'record_count' => is_array($data) ? count($data) : 0
        ]
    ];
}

/**
 * Get field reports data
 */
function getFieldReportsData($pdo, $startDate, $endDate, $filters) {
    $where = ["fr.report_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if (!empty($filters['rig_id'])) {
        $where[] = "fr.rig_id = ?";
        $params[] = $filters['rig_id'];
    }
    
    if (!empty($filters['client_id'])) {
        $where[] = "fr.client_id = ?";
        $params[] = $filters['client_id'];
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            fr.report_id,
            fr.report_date,
            fr.site_name,
            fr.job_type,
            r.rig_name,
            c.client_name,
            fr.total_income,
            fr.total_expenses,
            fr.net_profit,
            fr.total_depth,
            fr.total_duration,
            fr.region,
            fr.created_at
        FROM field_reports fr
        LEFT JOIN rigs r ON fr.rig_id = r.id
        LEFT JOIN clients c ON fr.client_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY fr.report_date DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get financial data
 */
function getFinancialData($pdo, $startDate, $endDate, $filters) {
    $where = ["fr.report_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(fr.report_date, '%Y-%m') as month,
            DATE_FORMAT(fr.report_date, '%Y-%W') as week,
            fr.report_date,
            SUM(fr.total_income) as total_revenue,
            SUM(fr.total_expenses) as total_expenses,
            SUM(fr.net_profit) as total_profit,
            SUM(fr.total_wages) as total_wages,
            COUNT(*) as job_count
        FROM field_reports fr
        WHERE " . implode(' AND ', $where) . "
        GROUP BY DATE_FORMAT(fr.report_date, '%Y-%m'), DATE_FORMAT(fr.report_date, '%Y-%W'), fr.report_date
        ORDER BY fr.report_date
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get clients data
 */
function getClientsData($pdo, $filters) {
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.client_name,
            c.email,
            c.contact_number,
            c.address,
            COUNT(fr.id) as total_jobs,
            SUM(fr.total_income) as total_revenue,
            SUM(fr.net_profit) as total_profit,
            AVG(fr.net_profit) as avg_profit_per_job,
            MIN(fr.report_date) as first_job_date,
            MAX(fr.report_date) as last_job_date
        FROM clients c
        LEFT JOIN field_reports fr ON c.id = fr.client_id
        GROUP BY c.id
        ORDER BY total_revenue DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get workers data
 */
function getWorkersData($pdo, $startDate, $endDate, $filters) {
    $where = ["fr.report_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    $stmt = $pdo->prepare("
        SELECT 
            pe.worker_name,
            pe.role,
            w.default_rate,
            COUNT(DISTINCT fr.id) as jobs_count,
            SUM(pe.amount) as total_earned,
            AVG(pe.amount) as avg_per_job,
            SUM(pe.loan_reclaim) as total_loans_reclaimed
        FROM payroll_entries pe
        JOIN field_reports fr ON pe.report_id = fr.id
        LEFT JOIN workers w ON pe.worker_name = w.worker_name
        WHERE " . implode(' AND ', $where) . "
        GROUP BY pe.worker_name, pe.role
        ORDER BY total_earned DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get materials data
 */
function getMaterialsData($pdo, $filters) {
    $stmt = $pdo->query("
        SELECT 
            material_type,
            quantity_received,
            quantity_used,
            quantity_remaining,
            unit_cost,
            total_value,
            last_updated
        FROM materials_inventory
        ORDER BY material_type
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get operational data
 */
function getOperationalData($pdo, $startDate, $endDate, $filters) {
    $where = ["fr.report_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    $stmt = $pdo->prepare("
        SELECT 
            fr.report_date,
            r.rig_name,
            COUNT(*) as jobs_count,
            SUM(fr.total_depth) as total_depth,
            AVG(fr.total_depth) as avg_depth,
            SUM(fr.total_duration) as total_duration,
            AVG(fr.total_duration) as avg_duration,
            SUM(fr.screen_pipes_used + fr.plain_pipes_used + fr.gravel_used) as total_materials_used
        FROM field_reports fr
        JOIN rigs r ON fr.rig_id = r.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY fr.report_date, r.rig_name
        ORDER BY fr.report_date DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get Looker Studio schema
 */
function getLookerStudioSchema($dataSource) {
    $schemas = [
        'field_reports' => [
            ['name' => 'report_id', 'type' => 'STRING'],
            ['name' => 'report_date', 'type' => 'DATE'],
            ['name' => 'site_name', 'type' => 'STRING'],
            ['name' => 'job_type', 'type' => 'STRING'],
            ['name' => 'rig_name', 'type' => 'STRING'],
            ['name' => 'client_name', 'type' => 'STRING'],
            ['name' => 'total_income', 'type' => 'NUMBER'],
            ['name' => 'total_expenses', 'type' => 'NUMBER'],
            ['name' => 'net_profit', 'type' => 'NUMBER'],
            ['name' => 'total_depth', 'type' => 'NUMBER'],
            ['name' => 'total_duration', 'type' => 'NUMBER'],
            ['name' => 'region', 'type' => 'STRING']
        ],
        'financial' => [
            ['name' => 'month', 'type' => 'STRING'],
            ['name' => 'week', 'type' => 'STRING'],
            ['name' => 'report_date', 'type' => 'DATE'],
            ['name' => 'total_revenue', 'type' => 'NUMBER'],
            ['name' => 'total_expenses', 'type' => 'NUMBER'],
            ['name' => 'total_profit', 'type' => 'NUMBER'],
            ['name' => 'total_wages', 'type' => 'NUMBER'],
            ['name' => 'job_count', 'type' => 'NUMBER']
        ]
    ];
    
    return [
        'success' => true,
        'schema' => $schemas[$dataSource] ?? $schemas
    ];
}

/**
 * Get available metrics
 */
function getAvailableMetrics() {
    return [
        'success' => true,
        'metrics' => [
            'Revenue' => 'total_income',
            'Profit' => 'net_profit',
            'Expenses' => 'total_expenses',
            'Jobs Count' => 'job_count',
            'Total Depth' => 'total_depth',
            'Workers Payroll' => 'total_wages'
        ],
        'dimensions' => [
            'Date' => 'report_date',
            'Client' => 'client_name',
            'Rig' => 'rig_name',
            'Region' => 'region',
            'Job Type' => 'job_type'
        ]
    ];
}
?>

