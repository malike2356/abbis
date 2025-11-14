<?php
/**
 * POS System Health Check Endpoint
 * Returns system status, database connectivity, and table verification
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => APP_VERSION,
    'environment' => APP_ENV,
    'checks' => []
];

try {
    // Check database connection
    $pdo = getDBConnection();
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful'
    ];
} catch (Throwable $e) {
    $health['status'] = 'error';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
    http_response_code(503);
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}

// Check required POS tables
$requiredTables = [
    'pos_sales',
    'pos_sale_items',
    'pos_sale_payments',
    'pos_products',
    'pos_stores',
    'pos_categories',
    'pos_inventory',
    'pos_accounting_queue'
];

$missingTables = [];
$existingTables = [];

foreach ($requiredTables as $table) {
    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        $existingTables[] = $table;
        $health['checks']['tables'][$table] = [
            'status' => 'ok',
            'message' => 'Table exists'
        ];
    } catch (PDOException $e) {
        $missingTables[] = $table;
        $health['checks']['tables'][$table] = [
            'status' => 'missing',
            'message' => 'Table does not exist'
        ];
    }
}

if (!empty($missingTables)) {
    $health['status'] = 'warning';
    $health['checks']['tables']['summary'] = [
        'status' => 'warning',
        'message' => count($missingTables) . ' table(s) missing: ' . implode(', ', $missingTables),
        'missing' => $missingTables,
        'existing' => $existingTables
    ];
}

// Check optional tables
$optionalTables = [
    'pos_cash_drawer_sessions',
    'pos_refunds',
    'pos_refund_items',
    'pos_purchase_orders',
    'pos_goods_receipts'
];

foreach ($optionalTables as $table) {
    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        $health['checks']['optional_tables'][$table] = [
            'status' => 'ok',
            'message' => 'Optional table exists'
        ];
    } catch (PDOException $e) {
        $health['checks']['optional_tables'][$table] = [
            'status' => 'missing',
            'message' => 'Optional table not found (not critical)'
        ];
    }
}

// Check log directory
$logPath = LOG_PATH;
$health['checks']['logs'] = [
    'directory' => $logPath,
    'writable' => is_writable($logPath),
    'exists' => is_dir($logPath)
];

if (!is_dir($logPath) || !is_writable($logPath)) {
    $health['status'] = 'warning';
    $health['checks']['logs']['status'] = 'warning';
    $health['checks']['logs']['message'] = 'Log directory issue: ' . 
        (!is_dir($logPath) ? 'Directory does not exist' : 'Directory not writable');
} else {
    $health['checks']['logs']['status'] = 'ok';
    $health['checks']['logs']['message'] = 'Log directory is accessible';
}

// Check PosRepository initialization
try {
    $repo = new PosRepository($pdo);
    $health['checks']['repository'] = [
        'status' => 'ok',
        'message' => 'PosRepository initialized successfully'
    ];
} catch (Throwable $e) {
    $health['status'] = 'error';
    $health['checks']['repository'] = [
        'status' => 'error',
        'message' => 'PosRepository initialization failed: ' . $e->getMessage()
    ];
}

// Get basic statistics
try {
    $salesCount = $pdo->query("SELECT COUNT(*) FROM pos_sales")->fetchColumn();
    $productsCount = $pdo->query("SELECT COUNT(*) FROM pos_products WHERE is_active = 1")->fetchColumn();
    $storesCount = $pdo->query("SELECT COUNT(*) FROM pos_stores WHERE is_active = 1")->fetchColumn();
    
    $health['statistics'] = [
        'total_sales' => (int)$salesCount,
        'active_products' => (int)$productsCount,
        'active_stores' => (int)$storesCount
    ];
} catch (PDOException $e) {
    // Statistics are optional, don't fail health check
    $health['statistics'] = [
        'error' => 'Could not retrieve statistics'
    ];
}

// Set HTTP status code based on health status
if ($health['status'] === 'error') {
    http_response_code(503);
} elseif ($health['status'] === 'warning') {
    http_response_code(200); // Still OK, just warnings
}

echo json_encode($health, JSON_PRETTY_PRINT);

