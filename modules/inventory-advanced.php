<?php
/**
 * Advanced Inventory Management
 * Enhanced inventory tracking with transactions, stock levels, and analytics
 */
$page_title = 'Advanced Inventory';

// Only load dependencies if not already loaded (e.g., when included via router)
if (!isset($auth)) {
    require_once '../config/app.php';
    require_once '../config/security.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';
    $auth->requireAuth();
}

if (!isset($pdo)) {
    $pdo = getDBConnection();
}
$action = $_GET['action'] ?? 'dashboard';
$materialId = intval($_GET['material_id'] ?? 0);

// Check for required tables
require_once '../includes/migration-helpers.php';
$requiredTables = ['inventory_transactions', 'materials', 'materials_inventory'];
$missingTables = checkTablesExist($pdo, $requiredTables);
$tableMissing = !empty($missingTables);

// Check if advanced inventory feature is enabled
try {
    $stmt = $pdo->query("SELECT is_enabled FROM feature_toggles WHERE feature_key = 'inventory_advanced'");
    $feature = $stmt->fetch();
    if (!$feature || !$feature['is_enabled']) {
        die('Advanced Inventory feature is currently disabled. Enable it in System → Feature Management.');
    }
} catch (PDOException $e) {
    // Feature toggle table might not exist yet, allow access
}

$currentUserId = $_SESSION['user_id'];

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>📋 Advanced Inventory Management</h1>
        <p>Comprehensive inventory tracking, transactions, and analytics</p>
    </div>
    
    <?php if ($tableMissing): ?>
        <?php echo showMigrationWarning($missingTables, getMigrationFileForModule('inventory'), 'Advanced Inventory'); ?>
    <?php endif; ?>
    
    <!-- Inventory Navigation Tabs -->
    <div class="config-tabs" style="margin-bottom: 30px;">
        <div class="tabs">
            <button type="button" class="tab <?php echo $action === 'dashboard' ? 'active' : ''; ?>" onclick="window.location.href='?action=dashboard'">
                <span>📊 Dashboard</span>
            </button>
            <button type="button" class="tab <?php echo $action === 'stock' ? 'active' : ''; ?>" onclick="window.location.href='?action=stock'">
                <span>📦 Stock Levels</span>
            </button>
            <button type="button" class="tab <?php echo $action === 'transactions' ? 'active' : ''; ?>" onclick="window.location.href='?action=transactions'">
                <span>💳 Transactions</span>
            </button>
            <button type="button" class="tab <?php echo $action === 'reorder' ? 'active' : ''; ?>" onclick="window.location.href='?action=reorder'">
                <span>⚠️ Reorder Alerts</span>
            </button>
            <button type="button" class="tab <?php echo $action === 'analytics' ? 'active' : ''; ?>" onclick="window.location.href='?action=analytics'">
                <span>📈 Analytics</span>
            </button>
        </div>
    </div>
    
    <?php
    // Include appropriate view based on action
    try {
        switch ($action) {
            case 'dashboard':
                include 'inventory-dashboard.php';
                break;
            case 'stock':
                include 'inventory-stock.php';
                break;
            case 'transactions':
                include 'inventory-transactions.php';
                break;
            case 'reorder':
                include 'inventory-reorder.php';
                break;
            case 'analytics':
                include 'inventory-analytics.php';
                break;
            default:
                include 'inventory-dashboard.php';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error loading view: ' . e($e->getMessage()) . '</div>';
    }
    ?>
</div>

<?php require_once '../includes/footer.php'; ?>

