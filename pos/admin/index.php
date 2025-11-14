<?php
$page_title = 'POS Admin';

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';
require_once $rootPath . '/includes/pos/PosRepository.php';
require_once $rootPath . '/includes/pos/PosValidator.php';
require_once $rootPath . '/includes/pos/PosAccountingSync.php';
require_once $rootPath . '/includes/pos/PosCatalogSync.php';
require_once $rootPath . '/includes/pos/UnifiedInventoryService.php';
require_once $rootPath . '/includes/pos/PosReportingService.php';
require_once $rootPath . '/includes/pos/MaterialsService.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();
$repo = new PosRepository($pdo);
$baseUrl = app_base_path();

$activeTab = $_GET['tab'] ?? 'dashboard';
$allowedTabs = ['dashboard', 'stores', 'catalog', 'inventory', 'sales', 'accounting', 'reports', 'cash', 'shifts', 'performance', 'material-returns'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'dashboard';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tab = $_POST['tab'] ?? 'stores';
    
    try {
        switch ($action) {
            case 'create_store':
                $payload = PosValidator::validateStorePayload($_POST);
                $repo->createStore($payload);
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=' . urlencode($tab) . '&success=Store created');
                exit;
            case 'update_store':
                $storeId = (int) ($_POST['store_id'] ?? 0);
                $payload = PosValidator::validateStorePayload($_POST);
                $repo->updateStore($storeId, $payload);
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=' . urlencode($tab) . '&success=Store updated');
                exit;
            case 'create_product':
                $payload = PosValidator::validateProductPayload($_POST);
                $productId = $repo->createProduct($payload);
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=catalog&success=Product created');
                exit;
            case 'update_product':
                $productId = (int) ($_POST['product_id'] ?? 0);
                $payload = PosValidator::validateProductPayload($_POST, true);
                $repo->updateProduct($productId, $payload);
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=catalog&success=Product updated');
                exit;
            case 'create_category':
                $categoryData = [
                    'name' => trim($_POST['category_name'] ?? ''),
                    'description' => trim($_POST['category_description'] ?? ''),
                ];
                if (empty($categoryData['name'])) {
                    throw new InvalidArgumentException('Category name is required');
                }
                $repo->createCategory($categoryData);
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=catalog&success=Category created');
                exit;
            case 'adjust_inventory':
                $adjustmentData = [
                    'store_id' => (int) ($_POST['store_id'] ?? 0),
                    'product_id' => (int) ($_POST['product_id'] ?? 0),
                    'quantity_delta' => (float) ($_POST['quantity_delta'] ?? 0),
                    'transaction_type' => $_POST['transaction_type'] ?? 'adjustment',
                    'cost_per_unit' => !empty($_POST['cost_per_unit']) ? (float) $_POST['cost_per_unit'] : null,
                    'remarks' => trim($_POST['remarks'] ?? ''),
                ];
                $validated = PosValidator::validateInventoryAdjustment($adjustmentData);
                $repo->adjustStock($validated);
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=inventory&success=Inventory adjusted');
                exit;
            case 'sync_accounting':
                $limit = max(1, min(100, (int) ($_POST['sync_limit'] ?? 25)));
                $syncType = $_POST['sync_type'] ?? 'sales';
                $syncService = new PosAccountingSync($pdo);
                
                if ($syncType === 'refunds') {
                    $sync = $syncService->syncRefunds($limit);
                    header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=accounting&success=Synced ' . $sync['synced'] . ' refunds');
                } else {
                    $sync = $syncService->syncPendingSales($limit);
                    header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=accounting&success=Synced ' . $sync['synced'] . ' sales');
                }
                exit;
            case 'sync_catalog':
                // Sync FROM catalog_items TO pos_products
                $catalogItems = $pdo->query("SELECT id FROM catalog_items WHERE item_type = 'product' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
                $synced = 0;
                $failed = 0;
                $errors = [];
                
                foreach ($catalogItems as $catalogItemId) {
                    try {
                        $repo->upsertProductFromCatalog((int) $catalogItemId);
                        $synced++;
                    } catch (Throwable $e) {
                        $failed++;
                        $errors[] = $e->getMessage();
                        error_log('[POS Catalog Sync] Failed to sync catalog item ' . $catalogItemId . ': ' . $e->getMessage());
                    }
                }
                
                $message = "Synced {$synced} products from ABBIS catalog to POS";
                if ($failed > 0) {
                    $message .= " ({$failed} failed)";
                }
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=catalog&success=' . urlencode($message));
                exit;
            case 'sync_inventory':
                $inventoryService = new UnifiedInventoryService($pdo);
                $results = $inventoryService->syncAllInventory();
                $message = "Synced {$results['synced']} products";
                if (!empty($results['linked'])) {
                    $message .= ", linked {$results['linked']} products to catalog";
                }
                if (count($results['errors']) > 0) {
                    $message .= " (" . count($results['errors']) . " errors)";
                }
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=inventory&success=' . urlencode($message));
                exit;
            case 'sync_all_systems':
                require_once $rootPath . '/includes/pos/UnifiedCatalogSyncService.php';
                $syncService = new UnifiedCatalogSyncService($pdo);
                $inventoryService = new UnifiedInventoryService($pdo);
                $repo = new PosRepository($pdo);
                
                $results = [
                    'catalog_synced' => 0, // Catalog items (includes both ABBIS and CMS products)
                    'pos_synced' => 0,
                    'linked' => 0,
                    'inventory_synced' => 0,
                    'errors' => []
                ];
                
                try {
                    // Step 1: Sync from Catalog (catalog_items) to POS
                    // Catalog items include products from:
                    // - ABBIS Resources/Materials module
                    // - CMS Products module
                    // - Any other system that uses catalog_items
                    $catalogItems = $pdo->query("
                        SELECT id FROM catalog_items 
                        WHERE item_type = 'product' AND is_active = 1
                    ")->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($catalogItems as $catalogItemId) {
                        try {
                            $repo->upsertProductFromCatalog((int) $catalogItemId);
                            $syncService->syncCatalogToPos((int) $catalogItemId);
                            $results['catalog_synced']++;
                        } catch (Throwable $e) {
                            $results['errors'][] = "Catalog item {$catalogItemId}: " . $e->getMessage();
                            error_log('[Comprehensive Sync] Error syncing catalog item ' . $catalogItemId . ': ' . $e->getMessage());
                        }
                    }
                    
                    // Step 2: Sync from POS to catalog (for POS-only products)
                    $posProducts = $pdo->query("
                        SELECT id FROM pos_products WHERE is_active = 1
                    ")->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($posProducts as $posProductId) {
                        try {
                            $syncService->syncPosToCatalog((int) $posProductId);
                            $results['pos_synced']++;
                        } catch (Throwable $e) {
                            $results['errors'][] = "POS product {$posProductId}: " . $e->getMessage();
                            error_log('[Comprehensive Sync] Error syncing POS product ' . $posProductId . ': ' . $e->getMessage());
                        }
                    }
                    
                    // Step 3: Link products by SKU across all systems
                    $linkStmt = $pdo->query("
                        UPDATE pos_products p
                        INNER JOIN catalog_items ci ON ci.sku = p.sku AND ci.item_type = 'product'
                        SET p.catalog_item_id = ci.id
                        WHERE p.catalog_item_id IS NULL AND p.sku IS NOT NULL AND p.sku != ''
                    ");
                    $results['linked'] = $linkStmt->rowCount();
                    
                    // Step 4: Sync inventory quantities from catalog to POS stores
                    $inventoryResults = $inventoryService->syncAllInventory();
                    $results['inventory_synced'] = $inventoryResults['synced'];
                    if ($inventoryResults['linked'] > 0) {
                        $results['linked'] += $inventoryResults['linked'];
                    }
                    if (!empty($inventoryResults['errors'])) {
                        $results['errors'] = array_merge($results['errors'], $inventoryResults['errors']);
                    }
                    
                    // Build success message
                    $message = "Comprehensive sync completed: ";
                    $parts = [];
                    if ($results['catalog_synced'] > 0) {
                        $parts[] = "{$results['catalog_synced']} from Catalog (ABBIS & CMS)";
                    }
                    if ($results['pos_synced'] > 0) {
                        $parts[] = "{$results['pos_synced']} from POS";
                    }
                    if ($results['linked'] > 0) {
                        $parts[] = "{$results['linked']} products linked";
                    }
                    if ($results['inventory_synced'] > 0) {
                        $parts[] = "{$results['inventory_synced']} inventory synced";
                    }
                    $message .= implode(", ", $parts);
                    
                    if (count($results['errors']) > 0) {
                        $message .= " (" . count($results['errors']) . " errors)";
                    }
                    
                } catch (Throwable $e) {
                    error_log('[Comprehensive Sync] Fatal error: ' . $e->getMessage());
                    $message = "Sync completed with errors: " . $e->getMessage();
                    if (count($results['errors']) > 0) {
                        $message .= " (" . count($results['errors']) . " additional errors)";
                    }
                }
                
                header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=inventory&success=' . urlencode($message));
                exit;
        }
    } catch (Throwable $e) {
        header('Location: ' . $baseUrl . '/pos/index.php?action=admin&tab=' . urlencode($tab) . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Load data for tabs
$stores = $repo->getStores(false);
$sales = $repo->listRecentSales(null, 50);
$accountingQueue = $repo->listAccountingQueue(25);

// Get company name for receipt printing
$companyStmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_name'");
$companyStmt->execute();
$companyName = $companyStmt->fetchColumn() ?: 'ABBIS';
$categories = $repo->listCategories(true);
$products = $repo->listProducts([], 100, 0);

// Get inventory data for selected store
$selectedStoreId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : ($stores[0]['id'] ?? null);
$inventoryData = [];
if ($selectedStoreId) {
    $inventoryData = $repo->listInventoryByStore($selectedStoreId, 200, 0);
}

// Get pending material returns
$materialsService = new MaterialsService($pdo);
$pendingReturns = $materialsService->getPendingReturns();
$pendingReturnsCount = count($pendingReturns);

// Get all material return history (accepted, rejected, etc.) with pagination
$returnsPerPage = 14;
$returnsPage = isset($_GET['returns_page']) ? max(1, (int)$_GET['returns_page']) : 1;
$returnsOffset = ($returnsPage - 1) * $returnsPerPage;

$allReturns = [];
$totalReturns = 0;
$totalReturnsPages = 0;

try {
    // Get total count
    $countStmt = $pdo->query("SELECT COUNT(*) FROM pos_material_returns");
    $totalReturns = (int)$countStmt->fetchColumn();
    $totalReturnsPages = (int)ceil($totalReturns / $returnsPerPage);
    
    // Get paginated results
    $returnsStmt = $pdo->prepare("
        SELECT mr.*, 
               u1.full_name as requested_by_name,
               u2.full_name as accepted_by_name,
               u3.full_name as rejected_by_name
        FROM pos_material_returns mr
        LEFT JOIN users u1 ON mr.requested_by = u1.id
        LEFT JOIN users u2 ON mr.accepted_by = u2.id
        LEFT JOIN users u3 ON mr.rejected_by = u3.id
        ORDER BY mr.requested_at DESC
        LIMIT ? OFFSET ?
    ");
    $returnsStmt->execute([$returnsPerPage, $returnsOffset]);
    $allReturns = $returnsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
}

// Debug: Check linking status (only show in development)
$debugInfo = [];
if (APP_ENV === 'development' && isset($_GET['debug'])) {
    $debugStmt = $pdo->query("
        SELECT 
            p.id as pos_product_id,
            p.sku,
            p.name as pos_name,
            p.catalog_item_id,
            ci.id as catalog_id,
            ci.name as catalog_name,
            ci.stock_quantity,
            COALESCE(SUM(pi.quantity_on_hand), 0) as pos_inventory_total
        FROM pos_products p
        LEFT JOIN catalog_items ci ON p.catalog_item_id = ci.id OR (p.sku = ci.sku AND ci.item_type = 'product')
        LEFT JOIN pos_inventory pi ON pi.product_id = p.id
        GROUP BY p.id
        LIMIT 10
    ");
    $debugInfo = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.pos-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}
.pos-table th,
.pos-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--pos-border);
}
.pos-table th {
    background: var(--pos-input-bg);
    font-weight: 600;
    color: var(--pos-text);
}
.pos-table tr:hover {
    background: var(--pos-input-bg);
}
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}
.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}
.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    margin-bottom: 4px;
    font-weight: 600;
    color: var(--pos-text);
}
.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--pos-border);
    border-radius: 8px;
    font-size: 14px;
    background: var(--pos-card-bg);
    color: var(--pos-text);
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: var(--pos-card-bg);
    border-radius: 12px;
    padding: 24px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}
.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.btn-group {
    display: flex;
    gap: 8px;
}
</style>

<div class="pos-card">
    <div class="pos-card-header">
        <h1 class="pos-card-title">POS Administration</h1>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo e($_GET['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo e($_GET['error']); ?></div>
    <?php endif; ?>
    
    <nav style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--pos-border); flex-wrap: wrap;">
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=dashboard" 
           class="btn <?php echo $activeTab === 'dashboard' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Dashboard
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=sales" 
           class="btn <?php echo $activeTab === 'sales' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Sales
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=catalog" 
           class="btn <?php echo $activeTab === 'catalog' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Catalog
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=inventory" 
           class="btn <?php echo $activeTab === 'inventory' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Inventory
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=cash" 
           class="btn <?php echo $activeTab === 'cash' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            💰 Cash Management
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=shifts" 
           class="btn <?php echo $activeTab === 'shifts' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            🕐 Shifts
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=material-returns" 
           class="btn <?php echo $activeTab === 'material-returns' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            🔄 Material Returns
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=reports" 
           class="btn <?php echo $activeTab === 'reports' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Reports
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=accounting" 
           class="btn <?php echo $activeTab === 'accounting' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Accounting
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=performance" 
           class="btn <?php echo $activeTab === 'performance' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            📊 Performance
        </a>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=stores" 
           class="btn <?php echo $activeTab === 'stores' ? 'btn-primary' : 'btn-outline'; ?>" 
           style="border-radius: 8px 8px 0 0; margin-bottom: -1px;">
            Stores
        </a>
    </nav>
    
    <?php if ($activeTab === 'dashboard'): ?>
        <?php
        $reporting = new PosReportingService();
        $dashboard = $reporting->getDashboardSnapshot((int)($_SESSION['user_id'] ?? 0));
        $today = $dashboard['summary']['today'];
        $week = $dashboard['summary']['week'];
        $month = $dashboard['summary']['month'];
        $returnsToday = $dashboard['material_returns']['today'] ?? ['total_returns' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'cancelled' => 0, 'total_quantity' => 0, 'total_quantity_received' => 0];
        $returnsWeek = $dashboard['material_returns']['week'] ?? ['total_returns' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'cancelled' => 0, 'total_quantity' => 0, 'total_quantity_received' => 0];
        $returnsMonth = $dashboard['material_returns']['month'] ?? ['total_returns' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'cancelled' => 0, 'total_quantity' => 0, 'total_quantity_received' => 0];
        $chartsApiUrl = $baseUrl . '/pos/api/charts.php';
        
        // Check if user is admin or manager (higher-level access)
        $userRole = $auth->getUserRole();
        $isAdminOrManager = in_array($userRole, ['admin', 'manager'], true);
        ?>
        <!-- Chart.js Library -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        
        <!-- Auto-refresh for Material Returns KPIs -->
        <script>
        (function() {
            let refreshInterval = null;
            const REFRESH_INTERVAL_MS = 30000; // 30 seconds
            
            async function refreshMaterialReturnsKPIs() {
                try {
                    const response = await fetch('<?php echo $baseUrl; ?>/pos/api/reports.php?action=dashboard');
                    if (!response.ok) return;
                    
                    const data = await response.json();
                    if (!data.success || !data.data || !data.data.material_returns) return;
                    
                    const returns = data.data.material_returns;
                    
                    // Update Today's Returns card
                    const todayCard = document.querySelector('[data-kpi="returns-today"]');
                    if (todayCard) {
                        const countEl = todayCard.querySelector('.kpi-value');
                        const qtyEl = todayCard.querySelector('.kpi-quantity');
                        const statusEl = todayCard.querySelector('.kpi-status');
                        if (countEl) countEl.textContent = parseInt(returns.today.total_returns || 0).toLocaleString();
                        if (qtyEl) qtyEl.textContent = parseFloat(returns.today.total_quantity || 0).toFixed(1) + ' units';
                        if (statusEl) {
                            statusEl.innerHTML = `⏳ ${returns.today.pending || 0} pending | ✅ ${returns.today.accepted || 0} accepted`;
                        }
                    }
                    
                    // Update Week's Returns card
                    const weekCard = document.querySelector('[data-kpi="returns-week"]');
                    if (weekCard) {
                        const countEl = weekCard.querySelector('.kpi-value');
                        const qtyEl = weekCard.querySelector('.kpi-quantity');
                        const statusEl = weekCard.querySelector('.kpi-status');
                        if (countEl) countEl.textContent = parseInt(returns.week.total_returns || 0).toLocaleString();
                        if (qtyEl) qtyEl.textContent = parseFloat(returns.week.total_quantity || 0).toFixed(1) + ' units';
                        if (statusEl) {
                            statusEl.innerHTML = `⏳ ${returns.week.pending || 0} pending | ✅ ${returns.week.accepted || 0} accepted`;
                        }
                    }
                    
                    // Update Month's Returns card
                    const monthCard = document.querySelector('[data-kpi="returns-month"]');
                    if (monthCard) {
                        const countEl = monthCard.querySelector('.kpi-value');
                        const qtyEl = monthCard.querySelector('.kpi-quantity');
                        const statusEl = monthCard.querySelector('.kpi-status');
                        if (countEl) countEl.textContent = parseInt(returns.month.total_returns || 0).toLocaleString();
                        if (qtyEl) qtyEl.textContent = parseFloat(returns.month.total_quantity || 0).toFixed(1) + ' units';
                        if (statusEl) {
                            statusEl.innerHTML = `⏳ ${returns.month.pending || 0} pending | ✅ ${returns.month.accepted || 0} accepted`;
                        }
                    }
                    
                    // Update Status Summary card
                    const statusCard = document.querySelector('[data-kpi="returns-status"]');
                    if (statusCard) {
                        const pendingEl = statusCard.querySelector('.kpi-value');
                        const summaryEl = statusCard.querySelector('.kpi-status');
                        if (pendingEl) pendingEl.textContent = parseInt(returns.month.pending || 0).toLocaleString();
                        if (summaryEl) {
                            summaryEl.innerHTML = `✅ ${returns.month.accepted || 0} accepted | ❌ ${returns.month.rejected || 0} rejected`;
                        }
                    }
                } catch (error) {
                    console.error('Failed to refresh material returns KPIs:', error);
                }
            }
            
            // Start auto-refresh when page loads
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    refreshInterval = setInterval(refreshMaterialReturnsKPIs, REFRESH_INTERVAL_MS);
                    // Initial refresh after 2 seconds
                    setTimeout(refreshMaterialReturnsKPIs, 2000);
                });
            } else {
                refreshInterval = setInterval(refreshMaterialReturnsKPIs, REFRESH_INTERVAL_MS);
                // Initial refresh after 2 seconds
                setTimeout(refreshMaterialReturnsKPIs, 2000);
            }
            
            // Stop refresh when page is hidden (save resources)
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    if (refreshInterval) {
                        clearInterval(refreshInterval);
                        refreshInterval = null;
                    }
                } else {
                    if (!refreshInterval) {
                        refreshInterval = setInterval(refreshMaterialReturnsKPIs, REFRESH_INTERVAL_MS);
                        refreshMaterialReturnsKPIs(); // Refresh immediately when page becomes visible
                    }
                }
            });
        })();
        </script>
        
        <!-- Admin Tools Section (Only visible to admins/managers) -->
        <?php if ($isAdminOrManager): ?>
        <div class="dashboard-card" style="margin-bottom: 30px; background: linear-gradient(135deg, rgba(14, 165, 233, 0.05) 0%, rgba(14, 165, 233, 0.02) 100%); border: 1px solid rgba(14, 165, 233, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; color: var(--pos-text);">
                        <span style="font-size: 20px;">⚙️</span>
                        <span>Admin Tools</span>
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: var(--pos-secondary);">
                        Advanced diagnostic tools for system administrators
                    </p>
                </div>
                <span style="background: var(--pos-primary); color: white; font-size: 11px; padding: 4px 10px; border-radius: 12px; font-weight: 600; letter-spacing: 0.5px;">
                    ADMIN ONLY
                </span>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px;">
                <a href="<?php echo $baseUrl; ?>/pos/admin/check-stock.php" 
                   class="btn btn-primary" 
                   target="_blank"
                   style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 500; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2); transition: all 0.2s;">
                    <span style="font-size: 16px;">🔍</span>
                    <span>Check ABBIS Stock</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Material Returns Notification -->
        <?php if ($pendingReturnsCount > 0): ?>
        <div class="alert alert-warning" style="margin-bottom: 20px; border-left: 4px solid var(--pos-warning); background: rgba(245, 158, 11, 0.1); padding: 16px; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 24px;">🔔</span>
                    <div>
                        <strong style="color: var(--pos-warning); font-size: 16px;">
                            <?php echo $pendingReturnsCount; ?> Pending Material Return<?php echo $pendingReturnsCount > 1 ? 's' : ''; ?>
                        </strong>
                        <div style="font-size: 14px; color: var(--pos-secondary); margin-top: 4px;">
                            Materials are waiting to be returned from operations. Please review and accept or reject.
                        </div>
                    </div>
                </div>
                <button onclick="document.getElementById('materialReturnsSection').scrollIntoView({behavior: 'smooth'})" 
                        class="btn btn-primary" style="background: var(--pos-warning); border: none;">
                    Review Returns
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Material Returns Section -->
        <?php if ($pendingReturnsCount > 0): ?>
        <div id="materialReturnsSection" class="dashboard-card" style="margin-bottom: 30px; border: 2px solid var(--pos-warning);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <span>🔄 Material Returns</span>
                    <span class="badge badge-warning" style="font-size: 14px; padding: 4px 12px;">
                        <?php echo $pendingReturnsCount; ?> Pending
                    </span>
                </h3>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Material</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Requested By</th>
                            <th>Date</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingReturns as $return): ?>
                        <tr>
                            <td><strong><?php echo e($return['request_number']); ?></strong></td>
                            <td><?php echo e($return['material_name']); ?></td>
                            <td>
                                <span class="badge" style="background: var(--pos-primary); color: white;">
                                    <?php echo ucfirst(str_replace('_', ' ', $return['material_type'])); ?>
                                </span>
                            </td>
                            <td><strong><?php echo number_format($return['quantity'], 2); ?> <?php echo e($return['unit_of_measure'] ?: 'pcs'); ?></strong></td>
                            <td><?php echo e($return['requested_by_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($return['requested_at'])); ?></td>
                            <td style="max-width: 200px; font-size: 12px; color: var(--pos-secondary);">
                                <?php echo e($return['remarks'] ?: '—'); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button onclick="openAcceptReturnModal(<?php echo htmlspecialchars(json_encode($return, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" 
                                            class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                        ✓ Accept
                                    </button>
                                    <button onclick="openRejectReturnModal(<?php echo $return['id']; ?>, '<?php echo e($return['request_number']); ?>')" 
                                            class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-color: var(--pos-danger); color: var(--pos-danger);">
                                        ✗ Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <!-- Today's Sales -->
            <div class="pos-card">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">Today's Sales</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--pos-primary); margin-bottom: 4px;">
                    GHS <?php echo number_format($today['total_amount'], 2); ?>
                </div>
                <div style="font-size: 12px; color: var(--pos-secondary);">
                    <?php echo number_format($today['transactions']); ?> transactions
                </div>
            </div>
            
            <!-- This Week -->
            <div class="pos-card">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">This Week</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--pos-primary); margin-bottom: 4px;">
                    GHS <?php echo number_format($week['total_amount'], 2); ?>
                </div>
                <div style="font-size: 12px; color: var(--pos-secondary);">
                    <?php echo number_format($week['transactions']); ?> transactions
                </div>
            </div>
            
            <!-- This Month -->
            <div class="pos-card">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">This Month</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--pos-primary); margin-bottom: 4px;">
                    GHS <?php echo number_format($month['total_amount'], 2); ?>
                </div>
                <div style="font-size: 12px; color: var(--pos-secondary);">
                    <?php echo number_format($month['transactions']); ?> transactions
                </div>
            </div>
            
            <!-- Average Transaction -->
            <div class="pos-card">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">Avg Transaction</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--pos-primary); margin-bottom: 4px;">
                    GHS <?php echo number_format($today['transactions'] > 0 ? ($today['total_amount'] / $today['transactions']) : 0, 2); ?>
                </div>
                <div style="font-size: 12px; color: var(--pos-secondary);">
                    Today
                </div>
            </div>
        </div>
        
        <!-- Material Returns KPI Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <!-- Today's Returns -->
            <div class="pos-card" style="border-left: 4px solid #10b981;" data-kpi="returns-today">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">🔄 Material Returns (Today)</div>
                <div class="kpi-value" style="font-size: 24px; font-weight: 700; color: #10b981; margin-bottom: 4px;">
                    <?php echo number_format($returnsToday['total_returns']); ?>
                </div>
                <div class="kpi-quantity" style="font-size: 12px; color: var(--pos-secondary);">
                    <?php echo number_format($returnsToday['total_quantity'], 1); ?> units
                </div>
                <div class="kpi-status" style="font-size: 11px; color: var(--pos-secondary); margin-top: 4px;">
                    ⏳ <?php echo $returnsToday['pending']; ?> pending | 
                    ✅ <?php echo $returnsToday['accepted']; ?> accepted
                </div>
            </div>
            
            <!-- This Week Returns -->
            <div class="pos-card" style="border-left: 4px solid #3b82f6;" data-kpi="returns-week">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">🔄 Material Returns (Week)</div>
                <div class="kpi-value" style="font-size: 24px; font-weight: 700; color: #3b82f6; margin-bottom: 4px;">
                    <?php echo number_format($returnsWeek['total_returns']); ?>
                </div>
                <div class="kpi-quantity" style="font-size: 12px; color: var(--pos-secondary);">
                    <?php echo number_format($returnsWeek['total_quantity'], 1); ?> units
                </div>
                <div class="kpi-status" style="font-size: 11px; color: var(--pos-secondary); margin-top: 4px;">
                    ⏳ <?php echo $returnsWeek['pending']; ?> pending | 
                    ✅ <?php echo $returnsWeek['accepted']; ?> accepted
                </div>
            </div>
            
            <!-- This Month Returns -->
            <div class="pos-card" style="border-left: 4px solid #8b5cf6;" data-kpi="returns-month">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">🔄 Material Returns (Month)</div>
                <div class="kpi-value" style="font-size: 24px; font-weight: 700; color: #8b5cf6; margin-bottom: 4px;">
                    <?php echo number_format($returnsMonth['total_returns']); ?>
                </div>
                <div class="kpi-quantity" style="font-size: 12px; color: var(--pos-secondary);">
                    <?php echo number_format($returnsMonth['total_quantity'], 1); ?> units
                </div>
                <div class="kpi-status" style="font-size: 11px; color: var(--pos-secondary); margin-top: 4px;">
                    ⏳ <?php echo $returnsMonth['pending']; ?> pending | 
                    ✅ <?php echo $returnsMonth['accepted']; ?> accepted
                </div>
            </div>
            
            <!-- Returns Status Summary -->
            <div class="pos-card" style="border-left: 4px solid #f59e0b;" data-kpi="returns-status">
                <div style="font-size: 14px; color: var(--pos-secondary); margin-bottom: 8px;">📊 Returns Status</div>
                <div class="kpi-value" style="font-size: 20px; font-weight: 700; color: #f59e0b; margin-bottom: 4px;">
                    <?php 
                    $totalPending = $returnsMonth['pending'];
                    echo $totalPending > 0 ? number_format($totalPending) : '0';
                    ?>
                </div>
                <div style="font-size: 12px; color: var(--pos-secondary);">
                    Pending Review
                </div>
                <div class="kpi-status" style="font-size: 11px; color: var(--pos-secondary); margin-top: 4px;">
                    ✅ <?php echo $returnsMonth['accepted']; ?> accepted | 
                    ❌ <?php echo $returnsMonth['rejected']; ?> rejected
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <!-- Daily Sales Trend -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Daily Sales Trend (Last 30 Days)</h3>
                <canvas id="dailySalesChart" style="max-height: 300px;"></canvas>
            </div>
            
            <!-- Payment Methods -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Revenue by Payment Method</h3>
                <canvas id="paymentMethodsChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <!-- Hourly Sales Pattern -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Hourly Sales Pattern</h3>
                <canvas id="hourlySalesChart" style="max-height: 300px;"></canvas>
            </div>
            
            <!-- Top Products Chart -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Top Products by Revenue</h3>
                <canvas id="topProductsChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <!-- Top Products Table -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Top Products (This Week)</h3>
                <?php if (empty($dashboard['top_products'])): ?>
                    <p style="color: var(--pos-secondary);">No sales data</p>
                <?php else: ?>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard['top_products'] as $product): ?>
                                <tr>
                                    <td><?php echo e($product['name']); ?></td>
                                    <td><?php echo number_format($product['quantity_sold'], 0); ?></td>
                                    <td>GHS <?php echo number_format($product['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Top Cashiers -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Top Cashiers (This Week)</h3>
                <?php if (empty($dashboard['top_cashiers'])): ?>
                    <p style="color: var(--pos-secondary);">No sales data</p>
                <?php else: ?>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard['top_cashiers'] as $cashier): ?>
                                <tr>
                                    <td><?php echo e($cashier['name']); ?></td>
                                    <td><?php echo number_format($cashier['sales_count']); ?></td>
                                    <td>GHS <?php echo number_format($cashier['revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            <!-- Inventory Alerts -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Inventory Alerts</h3>
                <?php if (empty($dashboard['inventory_alerts'])): ?>
                    <p style="color: var(--pos-secondary);">No inventory alerts</p>
                <?php else: ?>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Store</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard['inventory_alerts'] as $alert): ?>
                                <tr>
                                    <td><?php echo e($alert['product_name']); ?></td>
                                    <td><?php echo e($alert['store_name']); ?></td>
                                    <td><?php echo number_format($alert['quantity_on_hand'], 0); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $alert['alert_level'] === 'out_of_stock' ? 'badge-danger' : 
                                                ($alert['alert_level'] === 'critical' ? 'badge-warning' : 'badge-success'); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $alert['alert_level'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Sales -->
            <div class="pos-card">
                <h3 style="margin: 0 0 16px 0;">Recent Sales</h3>
                <?php if (empty($dashboard['recent_sales'])): ?>
                    <p style="color: var(--pos-secondary);">No recent sales</p>
                <?php else: ?>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Sale #</th>
                                <th>Amount</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard['recent_sales'] as $sale): ?>
                                <tr>
                                    <td><?php echo e($sale['sale_number']); ?></td>
                                    <td>GHS <?php echo number_format($sale['total_amount'], 2); ?></td>
                                    <td><?php echo date('H:i', strtotime($sale['sale_timestamp'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($activeTab === 'stores'): ?>
        <div class="pos-card" style="margin-top: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Stores</h2>
                <button class="btn btn-primary" onclick="openStoreModal()">+ Add Store</button>
            </div>
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stores)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">No stores configured</td></tr>
                    <?php else: ?>
                        <?php foreach ($stores as $store): ?>
                            <tr>
                                <td><?php echo e($store['store_code']); ?></td>
                                <td><?php echo e($store['store_name']); ?></td>
                                <td><?php echo e($store['location'] ?? '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo !empty($store['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo !empty($store['is_active']) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="editStore(<?php echo (int) $store['id']; ?>, '<?php echo e($store['store_code']); ?>', '<?php echo e($store['store_name']); ?>', '<?php echo e($store['location'] ?? ''); ?>', <?php echo !empty($store['is_active']) ? '1' : '0'; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Store Modal -->
        <div id="storeModal" class="modal">
            <div class="modal-content">
                <h3 id="storeModalTitle">Add Store</h3>
                <form method="post" id="storeForm">
                    <input type="hidden" name="action" id="storeAction" value="create_store">
                    <input type="hidden" name="tab" value="stores">
                    <input type="hidden" name="store_id" id="storeId">
                    <div class="form-group">
                        <label>Store Code *</label>
                        <input type="text" name="store_code" id="storeCode" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Store Name *</label>
                        <input type="text" name="store_name" id="storeName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="storeLocation" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="storeActive" value="1" checked> Active
                        </label>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <button type="button" class="btn btn-outline" onclick="closeStoreModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($activeTab === 'catalog'): ?>
        <div class="pos-card" style="margin-top: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <h2 style="margin: 0;">Product Catalog</h2>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-outline" onclick="openCategoryModal()">+ Category</button>
                    <button class="btn btn-primary" onclick="openProductModal()">+ Product</button>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <input type="text" id="catalogSearch" class="form-control" placeholder="Search products..." style="max-width: 300px;" onkeyup="filterCatalog()">
            </div>
            
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="catalogTableBody">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 20px;">No products found</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr data-product-name="<?php echo strtolower(e($product['name'])); ?>" data-product-sku="<?php echo strtolower(e($product['sku'])); ?>">
                                <td><?php echo e($product['sku']); ?></td>
                                <td><?php echo e($product['name']); ?></td>
                                <td><?php echo e($product['category_name'] ?? '—'); ?></td>
                                <td>GHS <?php echo number_format((float) ($product['unit_price'] ?? 0), 2); ?></td>
                                <td>
                                    <?php 
                                    // Use stock_quantity from catalog_items (synced from CMS/ABBIS)
                                    // This is already included in the product data from listProducts()
                                    $stockQty = (float) ($product['stock_quantity'] ?? 0);
                                    $stockDisplay = number_format($stockQty, 0);
                                    $stockColor = $stockQty > 0 ? '#00a32a' : ($stockQty == 0 ? '#d63638' : '#646970');
                                    ?>
                                    <span style="color: <?php echo $stockColor; ?>; font-weight: 600;">
                                        <?php echo $stockDisplay; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo !empty($product['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo !empty($product['is_active']) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Product Modal -->
        <div id="productModal" class="modal">
            <div class="modal-content">
                <h3 id="productModalTitle">Add Product</h3>
                <form method="post" id="productForm">
                    <input type="hidden" name="action" id="productAction" value="create_product">
                    <input type="hidden" name="tab" value="catalog">
                    <input type="hidden" name="product_id" id="productId">
                    <div class="form-group">
                        <label>SKU *</label>
                        <input type="text" name="sku" id="productSku" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" id="productName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="productDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="productCategory" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Barcode</label>
                        <input type="text" name="barcode" id="productBarcode" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Unit Price (GHS) *</label>
                        <input type="number" name="unit_price" id="productUnitPrice" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Cost Price (GHS)</label>
                        <input type="number" name="cost_price" id="productCostPrice" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="productTaxRate" class="form-control" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="track_inventory" id="productTrackInventory" value="1" checked> Track Inventory
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="productIsActive" value="1" checked> Active
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="expose_to_shop" id="productExposeToShop" value="1"> Expose to Shop
                        </label>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <button type="button" class="btn btn-outline" onclick="closeProductModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Category Modal -->
        <div id="categoryModal" class="modal">
            <div class="modal-content">
                <h3>Add Category</h3>
                <form method="post">
                    <input type="hidden" name="action" value="create_category">
                    <input type="hidden" name="tab" value="catalog">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="category_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <button type="button" class="btn btn-outline" onclick="closeCategoryModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($activeTab === 'inventory'): ?>
        <div class="pos-card" style="margin-top: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <h2 style="margin: 0;">Inventory Management</h2>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <form method="post" style="margin: 0;" onsubmit="return confirm('This will perform a comprehensive sync from all systems:\n\n1. Sync from Catalog (ABBIS Resources & CMS Products) to POS\n2. Sync from POS to Catalog (for POS-only products)\n3. Link products by SKU across all systems\n4. Sync inventory quantities\n\nThis may take a few moments. Continue?');">
                        <input type="hidden" name="action" value="sync_all_systems">
                        <input type="hidden" name="tab" value="inventory">
                        <button type="submit" class="btn btn-primary">🔄 Sync All Systems</button>
                    </form>
                    <button class="btn btn-primary" onclick="openInventoryModal()">+ Adjust Stock</button>
                </div>
            </div>
            
            <?php
            // Show diagnostic info
            $diagStmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT ci.id) as catalog_products,
                    COUNT(DISTINCT CASE WHEN COALESCE(ci.inventory_quantity, ci.stock_quantity, 0) > 0 THEN ci.id END) as catalog_with_stock,
                    COUNT(DISTINCT p.id) as pos_products,
                    COUNT(DISTINCT p.catalog_item_id) as pos_linked
                FROM catalog_items ci
                LEFT JOIN pos_products p ON p.catalog_item_id = ci.id
                WHERE ci.item_type = 'product' AND ci.is_active = 1
            ");
            $diag = $diagStmt->fetch(PDO::FETCH_ASSOC);
            if ($diag && ($diag['catalog_with_stock'] == 0 || $diag['pos_linked'] < $diag['pos_products'])): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                    <strong>⚠️ Diagnostic Info:</strong>
                    <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                        <li>Catalog products: <?php echo $diag['catalog_products']; ?></li>
                        <li>Catalog products with stock: <?php echo $diag['catalog_with_stock']; ?></li>
                        <li>POS products: <?php echo $diag['pos_products']; ?></li>
                        <li>POS products linked: <?php echo $diag['pos_linked']; ?></li>
                    </ul>
                    <?php if ($diag['catalog_with_stock'] == 0): ?>
                        <p style="margin: 8px 0 0 0; color: #856404;"><strong>Issue:</strong> Catalog items have zero stock. You need to set stock quantities in ABBIS Resources or CMS Products first.</p>
                    <?php endif; ?>
                    <?php if ($diag['pos_linked'] < $diag['pos_products']): ?>
                        <p style="margin: 8px 0 0 0; color: #856404;"><strong>Issue:</strong> Some POS products are not linked to catalog items. Click "Sync from ABBIS" to link them.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group" style="max-width: 300px;">
                <label>Select Store</label>
                <select id="inventoryStoreSelect" class="form-control" onchange="loadInventory()">
                    <option value="">All Stores</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo (int) $store['id']; ?>" <?php echo $store['id'] == $selectedStoreId ? 'selected' : ''; ?>>
                            <?php echo e($store['store_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Store</th>
                        <th>Quantity</th>
                        <th>Low Stock</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventoryData)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">No inventory data</td></tr>
                    <?php else: ?>
                        <?php 
                        $storeMap = [];
                        foreach ($stores as $s) {
                            $storeMap[$s['id']] = $s['store_name'];
                        }
                        $productMap = [];
                        foreach ($products as $p) {
                            $productMap[$p['id']] = $p;
                        }
                        foreach ($inventoryData as $inv): 
                            $productId = $inv['id'] ?? null;
                            $product = $productId ? ($productMap[$productId] ?? null) : null;
                            if (!$product) continue;
                            // Use quantity_on_hand which now comes from catalog_items.inventory_quantity (prioritized over stock_quantity)
                            $quantity = (float) ($inv['quantity_on_hand'] ?? 0);
                            $posStoreQty = (float) ($inv['pos_store_quantity'] ?? 0);
                            $lowStock = $quantity < 10;
                        ?>
                            <tr>
                                <td><?php echo e($product['name']); ?></td>
                                <td><?php echo e($product['sku']); ?></td>
                                <td><?php echo e($storeMap[$selectedStoreId] ?? '—'); ?></td>
                                <td>
                                    <?php echo number_format($quantity, 0); ?>
                                    <?php if (APP_ENV === 'development' && $posStoreQty != $quantity): ?>
                                        <small style="color: #999;">(POS: <?php echo number_format($posStoreQty, 0); ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lowStock): ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $inv['updated_at'] ? date('Y-m-d H:i', strtotime($inv['updated_at'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Inventory Adjustment Modal -->
        <div id="inventoryModal" class="modal">
            <div class="modal-content">
                <h3>Adjust Stock</h3>
                <form method="post">
                    <input type="hidden" name="action" value="adjust_inventory">
                    <input type="hidden" name="tab" value="inventory">
                    <div class="form-group">
                        <label>Store *</label>
                        <select name="store_id" id="adjustStoreId" class="form-control" required>
                            <option value="">Select Store</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo (int) $store['id']; ?>"><?php echo e($store['store_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product *</label>
                        <select name="product_id" id="adjustProductId" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo (int) $product['id']; ?>"><?php echo e($product['name'] . ' (' . $product['sku'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Transaction Type *</label>
                        <select name="transaction_type" id="adjustType" class="form-control" required>
                            <option value="adjustment">Adjustment</option>
                            <option value="receipt">Goods Receipt</option>
                            <option value="sale">Sale</option>
                            <option value="return">Return</option>
                            <option value="damage">Damage/Loss</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity Change *</label>
                        <input type="number" name="quantity_delta" id="adjustQuantity" class="form-control" step="0.01" required>
                        <small style="color: var(--pos-secondary);">Positive for increase, negative for decrease</small>
                    </div>
                    <div class="form-group">
                        <label>Cost Per Unit (GHS)</label>
                        <input type="number" name="cost_per_unit" id="adjustCost" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="adjustRemarks" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Adjust Stock</button>
                        <button type="button" class="btn btn-outline" onclick="closeInventoryModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($activeTab === 'sales'): ?>
        <div class="pos-card" style="margin-top: 0;">
            <h2>Sales History</h2>
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Store</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>Tax</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="12" style="text-align: center; padding: 20px;">No sales recorded</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo e($sale['sale_number']); ?></td>
                                <td><?php echo e($sale['store_name'] ?? '—'); ?></td>
                                <td><?php echo e($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                <td><?php echo (int) ($sale['item_count'] ?? 0); ?></td>
                                <td>GHS <?php echo number_format((float) ($sale['subtotal_amount'] ?? 0), 2); ?></td>
                                <td><?php if (($sale['discount_total'] ?? 0) > 0): ?>
                                    <span style="color: var(--pos-success);">-GHS <?php echo number_format((float) $sale['discount_total'], 2); ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?></td>
                                <td>GHS <?php echo number_format((float) ($sale['tax_total'] ?? 0), 2); ?></td>
                                <td><strong>GHS <?php echo number_format((float) ($sale['total_amount'] ?? 0), 2); ?></strong></td>
                                <td><?php echo e(ucfirst($sale['payment_status'] ?? 'paid')); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($sale['sale_timestamp'])); ?></td>
                                <td>
                                    <span class="badge <?php echo ($sale['sale_status'] ?? 'completed') === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo e(ucfirst($sale['sale_status'] ?? 'completed')); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="reprintReceipt('<?php echo e($sale['sale_number']); ?>')" title="Reprint Receipt">🖨️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        async function reprintReceipt(saleNumber) {
            try {
                const response = await fetch('<?php echo $baseUrl; ?>/pos/api/receipt.php?sale_number=' + encodeURIComponent(saleNumber));
                const json = await response.json();
                
                if (!json.success || !json.data) {
                    alert('Receipt not found');
                    return;
                }
                
                const receiptData = json.data;
                const sale = receiptData.sale;
                const items = receiptData.items || [];
                const payments = receiptData.payments || [];
                
                // Format receipt
                const lineWidth = 42;
                const divider = '-'.repeat(lineWidth);
                
                const center = (text = '') => {
                    const trimmed = String(text).slice(0, lineWidth);
                    const padding = Math.max(0, Math.floor((lineWidth - trimmed.length) / 2));
                    return ' '.repeat(padding) + trimmed;
                };
                
                const labelValue = (label, value) => {
                    const left = (label || '').toUpperCase();
                    const right = String(value || '');
                    const spacing = Math.max(1, lineWidth - left.length - right.length);
                    return `${left}${' '.repeat(spacing)}${right}`;
                };
                
                const lines = [
                    center('<?php echo e($companyName); ?>'),
                    center('POINT OF SALE RECEIPT'),
                    divider,
                    labelValue('Sale #', sale.sale_number || 'N/A'),
                    labelValue('Store', sale.store_name || '—'),
                    labelValue('Customer', sale.customer_name || 'Walk-in'),
                    labelValue('Date', new Date(sale.sale_timestamp).toLocaleString()),
                    divider,
                ];
                
                items.forEach(item => {
                    const nameLine = (item.product_name || item.description || 'Item').length > lineWidth 
                        ? (item.product_name || item.description || 'Item').slice(0, lineWidth - 1) + '…' 
                        : (item.product_name || item.description || 'Item');
                    lines.push(nameLine);
                    const lineTotal = parseFloat(item.line_total || 0);
                    lines.push(labelValue(`  x${item.quantity || 0} @ ${parseFloat(item.unit_price || 0).toFixed(2)}`, 'GHS ' + lineTotal.toFixed(2)));
                });
                
                lines.push(divider);
                lines.push(labelValue('Subtotal', parseFloat(sale.subtotal_amount || 0).toFixed(2)));
                if (parseFloat(sale.discount_total || 0) > 0) {
                    lines.push(labelValue('Discount', '-' + parseFloat(sale.discount_total || 0).toFixed(2)));
                }
                if (parseFloat(sale.tax_total || 0) > 0) {
                    lines.push(labelValue('Tax', parseFloat(sale.tax_total || 0).toFixed(2)));
                }
                lines.push(labelValue('Total', parseFloat(sale.total_amount || 0).toFixed(2)));
                lines.push(labelValue('Paid', parseFloat(sale.amount_paid || 0).toFixed(2)));
                if (parseFloat(sale.change_due || 0) > 0) {
                    lines.push(labelValue('Change', parseFloat(sale.change_due || 0).toFixed(2)));
                }
                lines.push(divider);
                if (payments.length > 0) {
                    lines.push(labelValue('Payment', payments[0].payment_method || 'N/A'));
                }
                if (sale.notes) {
                    lines.push('Note: ' + String(sale.notes).substring(0, lineWidth - 6));
                }
                lines.push(center('Thank you for your business!'));
                lines.push('');
                
                const receiptContent = lines.join('\n');
                
                // Open print window
                const printWindow = window.open('', '_blank', 'width=400,height=600');
                if (printWindow) {
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Receipt - ${sale.sale_number}</title>
                            <style>
                                @media print {
                                    body { margin: 0; }
                                }
                                pre {
                                    font-family: 'Courier New', monospace;
                                    font-size: 12px;
                                    line-height: 1.4;
                                    margin: 0;
                                    padding: 20px;
                                }
                            </style>
                        </head>
                        <body>
                            <pre>${receiptContent}</pre>
                            <script>
                                window.onload = function() {
                                    window.print();
                                    setTimeout(function() { window.close(); }, 1000);
                                };
                            <\/script>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                }
            } catch (error) {
                alert('Failed to load receipt: ' + error.message);
            }
        }
        </script>
        
    <?php elseif ($activeTab === 'accounting'): ?>
        <?php
        // Get accounting queue status
        $accountingQueueStmt = $pdo->query("
            SELECT q.*, s.sale_number, s.sale_timestamp, s.total_amount
            FROM pos_accounting_queue q
            LEFT JOIN pos_sales s ON q.sale_id = s.id
            WHERE q.status IN ('pending', 'error', 'processing')
            ORDER BY q.created_at DESC
            LIMIT 50
        ");
        $accountingQueue = $accountingQueueStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get sync statistics
        $statsStmt = $pdo->query("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(attempts) as total_attempts
            FROM pos_accounting_queue
            GROUP BY status
        ");
        $syncStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
        $stats = [];
        foreach ($syncStats as $stat) {
            $stats[$stat['status']] = $stat;
        }
        ?>
        <div class="pos-card" style="margin-top: 0;">
            <h2>Accounting Sync</h2>
            
            <!-- Sync Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Pending</div>
                    <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($stats['pending']['count'] ?? 0); ?></div>
                </div>
                <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Synced</div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--pos-success);"><?php echo number_format($stats['synced']['count'] ?? 0); ?></div>
                </div>
                <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Errors</div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--pos-danger);"><?php echo number_format($stats['error']['count'] ?? 0); ?></div>
                </div>
                <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Processing</div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--pos-warning);"><?php echo number_format($stats['processing']['count'] ?? 0); ?></div>
                </div>
            </div>
            
            <!-- Sync Actions -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <form method="post" style="background: var(--pos-input-bg); padding: 20px; border-radius: 8px;">
                    <input type="hidden" name="action" value="sync_accounting">
                    <input type="hidden" name="tab" value="accounting">
                    <input type="hidden" name="sync_type" value="sales">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px;">Sync Sales</h3>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Sync Limit</label>
                        <input type="number" name="sync_limit" class="form-control" value="25" min="1" max="100">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Sync Pending Sales</button>
                </form>
                
                <form method="post" style="background: var(--pos-input-bg); padding: 20px; border-radius: 8px;">
                    <input type="hidden" name="action" value="sync_accounting">
                    <input type="hidden" name="tab" value="accounting">
                    <input type="hidden" name="sync_type" value="refunds">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px;">Sync Refunds</h3>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Sync Limit</label>
                        <input type="number" name="sync_limit" class="form-control" value="25" min="1" max="100">
                    </div>
                    <button type="submit" class="btn btn-outline" style="width: 100%;">Sync Completed Refunds</button>
                </form>
            </div>
            
            <!-- Accounting Queue -->
            <h3>Accounting Queue</h3>
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Sale Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Queued</th>
                        <th>Last Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accountingQueue)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 20px;">Queue is clear</td></tr>
                    <?php else: ?>
                        <?php foreach ($accountingQueue as $queueRow): ?>
                            <tr>
                                <td><strong><?php echo e($queueRow['sale_number'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo $queueRow['sale_timestamp'] ? date('Y-m-d H:i', strtotime($queueRow['sale_timestamp'])) : '—'; ?></td>
                                <td><?php echo formatCurrency($queueRow['total_amount'] ?? 0); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $queueRow['status'] === 'synced' ? 'badge-success' : 
                                            ($queueRow['status'] === 'error' ? 'badge-danger' : 
                                            ($queueRow['status'] === 'processing' ? 'badge-warning' : 'badge-info')); 
                                    ?>">
                                        <?php echo e(ucfirst($queueRow['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo (int) ($queueRow['attempts'] ?? 0); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($queueRow['created_at'])); ?></td>
                                <td style="font-size: 12px; color: var(--pos-danger); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo e($queueRow['last_error'] ?? ''); ?>">
                                    <?php echo e(substr($queueRow['last_error'] ?? '', 0, 50)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Enhanced Accounting Features Info -->
            <div style="margin-top: 24px; padding: 16px; background: var(--pos-info-bg, #e3f2fd); border-radius: 8px; border-left: 4px solid var(--pos-info, #2196f3);">
                <h4 style="margin: 0 0 8px 0;">📊 Enhanced Accounting Features</h4>
                <ul style="margin: 0; padding-left: 20px; color: var(--pos-secondary);">
                    <li>Automatic double-entry bookkeeping for all POS sales</li>
                    <li>Payment method tracking (Cash, Card, Mobile Money, etc.)</li>
                    <li>COGS (Cost of Goods Sold) tracking with inventory reduction</li>
                    <li>Discount expense tracking</li>
                    <li>Payment processing fees tracking</li>
                    <li>Tax liability tracking</li>
                    <li>Accounts receivable for credit sales</li>
                    <li>Refund reversals</li>
                    <li>Customer and store context in journal entries</li>
                    <li>Product-level breakdown in memos</li>
                </ul>
            </div>
        </div>
        
    <?php elseif ($activeTab === 'material-returns'): ?>
        <div style="margin-bottom: 20px;">
            <h2 style="margin: 0 0 8px 0;">🔄 Material Return History</h2>
            <p style="color: var(--pos-secondary); margin: 0;">Complete history of all material return requests, acceptances, and rejections</p>
        </div>
        
        <?php if (empty($allReturns)): ?>
            <div class="dashboard-card">
                <div style="text-align: center; padding: 40px; color: var(--pos-secondary);">
                    <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
                    <h3 style="margin: 0 0 8px 0;">No Return History</h3>
                    <p style="margin: 0;">No material returns have been processed yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-card" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0;">All Material Returns</h3>
                        <p style="margin: 0; font-size: 13px; color: var(--pos-secondary);">
                            Showing <?php echo count($allReturns); ?> of <?php echo $totalReturns; ?> returns
                            <?php if ($totalReturnsPages > 1): ?>
                                (Page <?php echo $returnsPage; ?> of <?php echo $totalReturnsPages; ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Material</th>
                                <th>Type</th>
                                <th>Requested Qty</th>
                                <th>Actual Qty</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th>Processed By</th>
                                <th>Date</th>
                                <th>Quality Check</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allReturns as $return): ?>
                            <tr>
                                <td><strong><?php echo e($return['request_number']); ?></strong></td>
                                <td><?php echo e($return['material_name']); ?></td>
                                <td>
                                    <span class="badge" style="background: var(--pos-primary); color: white;">
                                        <?php echo ucfirst(str_replace('_', ' ', $return['material_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($return['quantity'], 2); ?> <?php echo e($return['unit_of_measure'] ?: 'pcs'); ?></td>
                                <td>
                                    <?php if ($return['actual_quantity_received'] !== null): ?>
                                        <strong><?php echo number_format($return['actual_quantity_received'], 2); ?></strong>
                                    <?php else: ?>
                                        <span style="color: var(--pos-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'pending' => 'var(--pos-warning)',
                                        'accepted' => 'var(--pos-success)',
                                        'rejected' => 'var(--pos-danger)',
                                        'cancelled' => 'var(--pos-secondary)'
                                    ];
                                    $statusLabels = [
                                        'pending' => '⏳ Pending',
                                        'accepted' => '✅ Accepted',
                                        'rejected' => '❌ Rejected',
                                        'cancelled' => '🚫 Cancelled'
                                    ];
                                    $color = $statusColors[$return['status']] ?? 'var(--pos-secondary)';
                                    $label = $statusLabels[$return['status']] ?? ucfirst($return['status']);
                                    ?>
                                    <span class="badge" style="background: <?php echo $color; ?>; color: white;">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                                <td><?php echo e($return['requested_by_name'] ?? 'Unknown'); ?></td>
                                <td>
                                    <?php 
                                    if ($return['accepted_by_name']) {
                                        echo '✅ ' . e($return['accepted_by_name']);
                                    } elseif ($return['rejected_by_name']) {
                                        echo '❌ ' . e($return['rejected_by_name']);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($return['accepted_at']) {
                                        echo date('M j, Y H:i', strtotime($return['accepted_at']));
                                    } elseif ($return['rejected_at']) {
                                        echo date('M j, Y H:i', strtotime($return['rejected_at']));
                                    } else {
                                        echo date('M j, Y H:i', strtotime($return['requested_at']));
                                    }
                                    ?>
                                </td>
                                <td style="max-width: 200px; font-size: 12px; color: var(--pos-secondary);">
                                    <?php echo e($return['quality_check'] ?: '—'); ?>
                                </td>
                                <td style="max-width: 200px; font-size: 12px; color: var(--pos-secondary);">
                                    <?php echo e($return['remarks'] ?: '—'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalReturnsPages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--pos-border);">
                    <?php if ($returnsPage > 1): ?>
                        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=material-returns&returns_page=<?php echo $returnsPage - 1; ?>" 
                           class="btn btn-outline" style="padding: 8px 16px; min-width: 100px;">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline" style="padding: 8px 16px; min-width: 100px; opacity: 0.5; cursor: not-allowed; pointer-events: none;">← Previous</span>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 4px; align-items: center;">
                        <?php
                        $startPage = max(1, $returnsPage - 2);
                        $endPage = min($totalReturnsPages, $returnsPage + 2);
                        
                        if ($startPage > 1): ?>
                            <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=material-returns&returns_page=1" 
                               class="btn btn-outline" style="padding: 8px 12px; min-width: 40px; text-align: center;">1</a>
                            <?php if ($startPage > 2): ?>
                                <span style="padding: 8px 4px; color: var(--pos-secondary);">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $returnsPage): ?>
                                <span class="btn btn-primary" style="padding: 8px 12px; min-width: 40px; text-align: center; cursor: default;"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=material-returns&returns_page=<?php echo $i; ?>" 
                                   class="btn btn-outline" style="padding: 8px 12px; min-width: 40px; text-align: center;"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalReturnsPages): ?>
                            <?php if ($endPage < $totalReturnsPages - 1): ?>
                                <span style="padding: 8px 4px; color: var(--pos-secondary);">...</span>
                            <?php endif; ?>
                            <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=material-returns&returns_page=<?php echo $totalReturnsPages; ?>" 
                               class="btn btn-outline" style="padding: 8px 12px; min-width: 40px; text-align: center;"><?php echo $totalReturnsPages; ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($returnsPage < $totalReturnsPages): ?>
                        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=material-returns&returns_page=<?php echo $returnsPage + 1; ?>" 
                           class="btn btn-outline" style="padding: 8px 16px; min-width: 100px;">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline" style="padding: 8px 16px; min-width: 100px; opacity: 0.5; cursor: not-allowed; pointer-events: none;">Next →</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    
    <?php elseif ($activeTab === 'reports'): ?>
        <?php
        $reportingService = new PosReportingService($pdo);
        $reportType = $_GET['report'] ?? 'sales';
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $selectedStoreId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
        
        $salesReport = null;
        $productReport = null;
        $cashierReport = null;
        $inventoryAlerts = null;
        $refundReport = null;
        $materialReturnsReport = null;
        
        if ($reportType === 'sales' || $reportType === 'all') {
            $salesReport = $reportingService->getSalesReport($startDate, $endDate, $selectedStoreId);
        }
        if ($reportType === 'products' || $reportType === 'all') {
            $productReport = $reportingService->getProductPerformanceReport($startDate, $endDate, $selectedStoreId, 50);
        }
        if ($reportType === 'cashiers' || $reportType === 'all') {
            $cashierReport = $reportingService->getCashierPerformanceReport($startDate, $endDate, $selectedStoreId);
        }
        if ($reportType === 'inventory' || $reportType === 'all') {
            $inventoryAlerts = $reportingService->getInventoryAlertsDetailed($selectedStoreId);
        }
        if ($reportType === 'refunds' || $reportType === 'all') {
            $refundReport = $reportingService->getRefundReport($startDate, $endDate, $selectedStoreId);
        }
        if ($reportType === 'material_returns' || $reportType === 'all') {
            $materialReturnsReport = $reportingService->getMaterialReturnsReport($startDate, $endDate);
        }
        ?>
        
        <div class="pos-card" style="margin-top: 0;">
            <h2>Reports & Analytics</h2>
            
            <!-- Report Filters -->
            <div style="background: var(--pos-input-bg); padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <form method="get" action="<?php echo $baseUrl; ?>/pos/index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <input type="hidden" name="action" value="admin">
                    <input type="hidden" name="tab" value="reports">
                    
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report" class="form-control">
                            <option value="all" <?php echo $reportType === 'all' ? 'selected' : ''; ?>>All Reports</option>
                            <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Product Performance</option>
                            <option value="cashiers" <?php echo $reportType === 'cashiers' ? 'selected' : ''; ?>>Cashier Performance</option>
                            <option value="inventory" <?php echo $reportType === 'inventory' ? 'selected' : ''; ?>>Inventory Alerts</option>
                            <option value="refunds" <?php echo $reportType === 'refunds' ? 'selected' : ''; ?>>Refunds Report</option>
                            <option value="material_returns" <?php echo $reportType === 'material_returns' ? 'selected' : ''; ?>>Material Returns</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Store</label>
                        <select name="store_id" class="form-control">
                            <option value="">All Stores</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo (int)$store['id']; ?>" <?php echo $selectedStoreId === $store['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($store['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo e($endDate); ?>">
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Generate Report</button>
                        <a href="<?php echo $baseUrl; ?>/pos/api/export.php?format=csv&report=<?php echo urlencode($reportType); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&store_id=<?php echo $selectedStoreId ?? ''; ?>" 
                           class="btn btn-outline" 
                           style="flex: 0 0 auto;" 
                           target="_blank">Export CSV</a>
                    </div>
                </form>
            </div>
            
            <!-- Sales Report -->
            <?php if ($salesReport): ?>
                <div class="pos-card" style="margin-bottom: 24px;">
                    <h3>Sales Report</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Revenue</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($salesReport['summary']['total_revenue'], 2); ?></div>
                        </div>
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Transactions</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($salesReport['summary']['total_transactions']); ?></div>
                        </div>
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Avg Transaction</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($salesReport['summary']['avg_transaction'], 2); ?></div>
                        </div>
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Discounts</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($salesReport['summary']['total_discounts'], 2); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($salesReport['payment_methods'])): ?>
                        <h4>Payment Methods</h4>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesReport['payment_methods'] as $method): ?>
                                    <tr>
                                        <td><?php echo e(ucfirst(str_replace('_', ' ', $method['payment_method']))); ?></td>
                                        <td><?php echo number_format($method['transaction_count']); ?></td>
                                        <td><?php echo number_format($method['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Product Performance Report -->
            <?php if ($productReport): ?>
                <div class="pos-card" style="margin-bottom: 24px;">
                    <h3>Product Performance</h3>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity Sold</th>
                                <th>Total Revenue</th>
                                <th>Times Sold</th>
                                <th>Avg Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productReport as $product): ?>
                                <tr>
                                    <td><?php echo e($product['name']); ?></td>
                                    <td><?php echo e($product['sku']); ?></td>
                                    <td><?php echo number_format($product['total_quantity_sold'], 2); ?></td>
                                    <td><?php echo number_format($product['total_revenue'], 2); ?></td>
                                    <td><?php echo number_format($product['times_sold']); ?></td>
                                    <td><?php echo number_format($product['avg_selling_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Cashier Performance Report -->
            <?php if ($cashierReport): ?>
                <div class="pos-card" style="margin-bottom: 24px;">
                    <h3>Cashier Performance</h3>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th>Transactions</th>
                                <th>Total Revenue</th>
                                <th>Avg Transaction</th>
                                <th>Total Discounts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cashierReport as $cashier): ?>
                                <tr>
                                    <td><?php echo e($cashier['cashier_name']); ?></td>
                                    <td><?php echo number_format($cashier['total_transactions']); ?></td>
                                    <td><?php echo number_format($cashier['total_revenue'], 2); ?></td>
                                    <td><?php echo number_format($cashier['avg_transaction'], 2); ?></td>
                                    <td><?php echo number_format($cashier['total_discounts'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Inventory Alerts -->
            <?php if ($inventoryAlerts): ?>
                <div class="pos-card" style="margin-bottom: 24px;">
                    <h3>Inventory Alerts</h3>
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>On Hand</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventoryAlerts as $alert): ?>
                                <tr>
                                    <td><?php echo e($alert['store_name']); ?></td>
                                    <td><?php echo e($alert['product_name']); ?></td>
                                    <td><?php echo e($alert['sku']); ?></td>
                                    <td><?php echo number_format($alert['quantity_on_hand'], 2); ?></td>
                                    <td><?php echo number_format($alert['reorder_level'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $alert['alert_level'] === 'out_of_stock' ? 'badge-danger' : 
                                                ($alert['alert_level'] === 'critical' ? 'badge-warning' : 'badge-info'); 
                                        ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $alert['alert_level']))); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Refunds Report -->
            <?php if ($refundReport): ?>
                <div class="pos-card">
                    <h3>Refunds Report</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Refunds</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($refundReport['summary']['total_refunds']); ?></div>
                        </div>
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Refunded</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($refundReport['summary']['total_refunded'], 2); ?></div>
                        </div>
                        <div style="background: var(--pos-input-bg); padding: 16px; border-radius: 8px;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Avg Refund</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($refundReport['summary']['avg_refund'], 2); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($refundReport['by_method'])): ?>
                        <h4>By Refund Method</h4>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Count</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refundReport['by_method'] as $method): ?>
                                    <tr>
                                        <td><?php echo e(ucfirst(str_replace('_', ' ', $method['refund_method']))); ?></td>
                                        <td><?php echo number_format($method['count']); ?></td>
                                        <td><?php echo number_format($method['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Material Returns Report -->
            <?php if ($materialReturnsReport && $materialReturnsReport['summary']['total_returns'] > 0): ?>
                <div class="pos-card" style="margin-bottom: 24px;">
                    <h3>Material Returns Report</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div style="padding: 16px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border-left: 4px solid #10b981;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Returns</div>
                            <div style="font-size: 24px; font-weight: 700; color: #10b981;">
                                <?php echo number_format($materialReturnsReport['summary']['total_returns']); ?>
                            </div>
                        </div>
                        <div style="padding: 16px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border-left: 4px solid #f59e0b;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Pending</div>
                            <div style="font-size: 24px; font-weight: 700; color: #f59e0b;">
                                <?php echo number_format($materialReturnsReport['summary']['pending']); ?>
                            </div>
                        </div>
                        <div style="padding: 16px; background: rgba(34, 197, 94, 0.1); border-radius: 8px; border-left: 4px solid #22c55e;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Accepted</div>
                            <div style="font-size: 24px; font-weight: 700; color: #22c55e;">
                                <?php echo number_format($materialReturnsReport['summary']['accepted']); ?>
                            </div>
                        </div>
                        <div style="padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border-left: 4px solid #ef4444;">
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Rejected</div>
                            <div style="font-size: 24px; font-weight: 700; color: #ef4444;">
                                <?php echo number_format($materialReturnsReport['summary']['rejected']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($materialReturnsReport['by_type'])): ?>
                        <h4 style="margin: 20px 0 12px 0;">Returns by Material Type</h4>
                        <table class="pos-table" style="margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th>Material Type</th>
                                    <th>Total Returns</th>
                                    <th>Total Quantity</th>
                                    <th>Accepted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materialReturnsReport['by_type'] as $type): ?>
                                    <tr>
                                        <td><?php echo e(ucfirst(str_replace('_', ' ', $type['material_type']))); ?></td>
                                        <td><?php echo number_format($type['count']); ?></td>
                                        <td><?php echo number_format($type['total_quantity'], 2); ?></td>
                                        <td><?php echo number_format($type['accepted_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <?php if (!empty($materialReturnsReport['by_status'])): ?>
                        <h4 style="margin: 20px 0 12px 0;">Returns by Status</h4>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Total Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materialReturnsReport['by_status'] as $status): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo $status['status'] === 'accepted' ? 'badge-success' : 
                                                    ($status['status'] === 'pending' ? 'badge-warning' : 
                                                    ($status['status'] === 'rejected' ? 'badge-danger' : 'badge-secondary')); 
                                            ?>">
                                                <?php echo e(ucfirst($status['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($status['count']); ?></td>
                                        <td><?php echo number_format($status['total_quantity'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($activeTab === 'cash'): ?>
        <?php
        // Cash Management Tab
        $stores = $repo->getStores(false);
        $selectedStoreId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : ($stores[0]['id'] ?? null);
        $selectedCashierId = isset($_GET['cashier_id']) ? (int)$_GET['cashier_id'] : (int)($_SESSION['user_id'] ?? 0);
        
        // Get current drawer session if store and cashier selected
        $currentSession = null;
        if ($selectedStoreId && $selectedCashierId) {
            try {
                $currentSession = $repo->getCurrentDrawerSession($selectedStoreId, $selectedCashierId);
            } catch (Throwable $e) {
                // Session might not exist, continue
            }
        }
        
        // Get recent drawer sessions
        $recentSessions = [];
        if ($selectedCashierId) {
            try {
                $recentSessions = $repo->listDrawerSessions($selectedCashierId, 20);
            } catch (Throwable $e) {
                // Continue without sessions
            }
        }
        
        // Get all cashiers
        $cashiersStmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('cashier', 'admin', 'manager') ORDER BY full_name");
        $cashiers = $cashiersStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="dashboard-card">
            <h2>💰 Cash Drawer Management</h2>
            
            <!-- Store and Cashier Selection -->
            <div style="background: var(--pos-input-bg); padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <form method="get" action="<?php echo $baseUrl; ?>/pos/index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <input type="hidden" name="action" value="admin">
                    <input type="hidden" name="tab" value="cash">
                    
                    <div class="form-group">
                        <label>Store</label>
                        <select name="store_id" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['id']; ?>" <?php echo $selectedStoreId == $store['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($store['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Cashier</label>
                        <select name="cashier_id" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($cashiers as $cashier): ?>
                                <option value="<?php echo $cashier['id']; ?>" <?php echo $selectedCashierId == $cashier['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cashier['full_name']); ?> (<?php echo e($cashier['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Current Drawer Session -->
            <?php if ($currentSession): ?>
                <div class="dashboard-card" style="border-left: 4px solid var(--pos-success); margin-bottom: 24px;">
                    <h3>Current Drawer Session</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Status</div>
                            <div style="font-size: 18px; font-weight: 700;">
                                <span class="badge badge-success"><?php echo strtoupper($currentSession['status']); ?></span>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Opening Amount</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo formatCurrency($currentSession['opening_amount'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Expected Cash</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo formatCurrency($currentSession['expected_amount'] ?? $currentSession['opening_amount'] ?? 0); ?></div>
                        </div>
                        <?php if ($currentSession['counted_amount'] !== null): ?>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Counted Amount</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo formatCurrency($currentSession['counted_amount']); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Difference</div>
                            <div style="font-size: 18px; font-weight: 700; color: <?php echo ($currentSession['difference'] ?? 0) < 0 ? 'var(--pos-danger)' : 'var(--pos-success)'; ?>">
                                <?php echo formatCurrency($currentSession['difference'] ?? 0); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Opened At</div>
                            <div style="font-size: 14px;"><?php echo date('Y-m-d H:i:s', strtotime($currentSession['opened_at'])); ?></div>
                        </div>
                        <?php if (!empty($currentSession['cash_sales'])): ?>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Cash Sales</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo formatCurrency($currentSession['cash_sales']); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Non-Cash Sales</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo formatCurrency($currentSession['non_cash_sales'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Transactions</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo number_format($currentSession['total_transactions'] ?? 0); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <?php if ($currentSession['status'] === 'open'): ?>
                            <button onclick="countDrawer()" class="btn btn-primary">Count Drawer</button>
                            <button onclick="closeDrawer()" class="btn btn-outline">Close Drawer</button>
                        <?php elseif ($currentSession['status'] === 'counted'): ?>
                            <button onclick="closeDrawer()" class="btn btn-primary">Close Drawer</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="dashboard-card" style="border-left: 4px solid var(--pos-info); margin-bottom: 24px;">
                    <h3>No Active Drawer Session</h3>
                    <p style="color: var(--pos-secondary);">There is no open drawer session for the selected store and cashier.</p>
                    <?php if ($selectedStoreId && $selectedCashierId): ?>
                        <button onclick="openDrawer()" class="btn btn-primary" style="margin-top: 12px;">Open New Drawer Session</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Recent Drawer Sessions -->
            <?php if (!empty($recentSessions)): ?>
                <div class="dashboard-card">
                    <h3>Recent Drawer Sessions</h3>
                    <div style="overflow-x: auto;">
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Opened At</th>
                                    <th>Closed At</th>
                                    <th>Opening Amount</th>
                                    <th>Expected Amount</th>
                                    <th>Counted Amount</th>
                                    <th>Difference</th>
                                    <th>Cash Sales</th>
                                    <th>Non-Cash Sales</th>
                                    <th>Transactions</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSessions as $session): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($session['opened_at'])); ?></td>
                                        <td><?php echo $session['closed_at'] ? date('Y-m-d H:i', strtotime($session['closed_at'])) : '-'; ?></td>
                                        <td><?php echo formatCurrency($session['opening_amount'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($session['expected_amount'] ?? $session['opening_amount'] ?? 0); ?></td>
                                        <td><?php echo $session['counted_amount'] !== null ? formatCurrency($session['counted_amount']) : '-'; ?></td>
                                        <td style="color: <?php echo ($session['difference'] ?? 0) < 0 ? 'var(--pos-danger)' : (($session['difference'] ?? 0) > 0 ? 'var(--pos-success)' : 'inherit'); ?>">
                                            <?php echo $session['difference'] !== null ? formatCurrency($session['difference']) : '-'; ?>
                                        </td>
                                        <td><?php echo formatCurrency($session['cash_sales'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($session['non_cash_sales'] ?? 0); ?></td>
                                        <td><?php echo number_format($session['total_transactions'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $session['status'] === 'closed' ? 'success' : 
                                                    ($session['status'] === 'counted' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo strtoupper($session['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        const drawerApiUrl = '<?php echo $baseUrl; ?>/pos/api/drawer.php';
        const currentStoreId = <?php echo $selectedStoreId ?? 'null'; ?>;
        const currentCashierId = <?php echo $selectedCashierId ?? 'null'; ?>;
        
        function openDrawer() {
            const openingAmount = parseFloat(prompt('Enter opening cash amount:', '0')) || 0;
            if (openingAmount < 0) {
                alert('Opening amount cannot be negative');
                return;
            }
            
            fetch(drawerApiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'open',
                    store_id: currentStoreId,
                    opening_amount: openingAmount
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Drawer opened successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to open drawer');
                }
            })
            .catch(e => {
                console.error(e);
                alert('Failed to open drawer');
            });
        }
        
        function countDrawer() {
            const countedAmount = parseFloat(prompt('Enter counted cash amount:', '')) || 0;
            if (countedAmount < 0) {
                alert('Counted amount cannot be negative');
                return;
            }
            
            fetch(drawerApiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'count',
                    store_id: currentStoreId,
                    counted_amount: countedAmount
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Drawer counted successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to count drawer');
                }
            })
            .catch(e => {
                console.error(e);
                alert('Failed to count drawer');
            });
        }
        
        function closeDrawer() {
            const countedAmount = prompt('Enter counted cash amount (leave empty to use previous count):', '');
            const counted = countedAmount !== '' && countedAmount !== null ? parseFloat(countedAmount) : null;
            const notes = prompt('Enter notes (optional):', '') || null;
            
            if (counted !== null && counted < 0) {
                alert('Counted amount cannot be negative');
                return;
            }
            
            fetch(drawerApiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'close',
                    store_id: currentStoreId,
                    counted_amount: counted,
                    notes: notes
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const diff = data.data.difference || 0;
                    const message = diff === 0 
                        ? 'Drawer closed successfully. No difference.'
                        : `Drawer closed. Difference: ${formatCurrency(diff)}`;
                    alert(message);
                    location.reload();
                } else {
                    alert(data.message || 'Failed to close drawer');
                }
            })
            .catch(e => {
                console.error(e);
                alert('Failed to close drawer');
            });
        }
        
        function formatCurrency(amount) {
            return '<?php echo getCurrency(); ?> ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        </script>
        
    <?php elseif ($activeTab === 'shifts'): ?>
        <?php
        // Shifts Tab
        $stores = $repo->getStores(false);
        $selectedStoreId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : ($stores[0]['id'] ?? null);
        $selectedCashierId = isset($_GET['cashier_id']) ? (int)$_GET['cashier_id'] : (int)($_SESSION['user_id'] ?? 0);
        $selectedShiftId = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
        
        // Get active shift
        $activeShift = null;
        if ($selectedStoreId && $selectedCashierId) {
            try {
                $activeShift = $repo->getActiveShift($selectedCashierId, $selectedStoreId);
            } catch (Throwable $e) {
                // Continue without active shift
            }
        }
        
        // Get shift report if shift ID selected
        $shiftReport = null;
        if ($selectedShiftId) {
            try {
                $shiftReport = $repo->getShiftReport($selectedShiftId);
            } catch (Throwable $e) {
                // Continue without report
            }
        }
        
        // Get recent shifts
        $recentShifts = [];
        if ($selectedCashierId) {
            try {
                $recentShifts = $repo->listShifts($selectedCashierId, 20);
            } catch (Throwable $e) {
                // Continue without shifts
            }
        }
        
        // Get all cashiers
        $cashiersStmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('cashier', 'admin', 'manager') ORDER BY full_name");
        $cashiers = $cashiersStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="dashboard-card">
            <h2>🕐 Shift Management</h2>
            
            <!-- Store and Cashier Selection -->
            <div style="background: var(--pos-input-bg); padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <form method="get" action="<?php echo $baseUrl; ?>/pos/index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <input type="hidden" name="action" value="admin">
                    <input type="hidden" name="tab" value="shifts">
                    
                    <div class="form-group">
                        <label>Store</label>
                        <select name="store_id" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['id']; ?>" <?php echo $selectedStoreId == $store['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($store['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Cashier</label>
                        <select name="cashier_id" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($cashiers as $cashier): ?>
                                <option value="<?php echo $cashier['id']; ?>" <?php echo $selectedCashierId == $cashier['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cashier['full_name']); ?> (<?php echo e($cashier['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Active Shift -->
            <?php if ($activeShift): ?>
                <div class="dashboard-card" style="border-left: 4px solid var(--pos-success); margin-bottom: 24px;">
                    <h3>Active Shift</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Status</div>
                            <div style="font-size: 18px; font-weight: 700;">
                                <span class="badge badge-success">ACTIVE</span>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Started At</div>
                            <div style="font-size: 14px;"><?php echo date('Y-m-d H:i:s', strtotime($activeShift['shift_start'])); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Opening Cash</div>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo formatCurrency($activeShift['opening_cash'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <button onclick="endShift()" class="btn btn-primary">End Shift</button>
                </div>
            <?php else: ?>
                <div class="dashboard-card" style="border-left: 4px solid var(--pos-info); margin-bottom: 24px;">
                    <h3>No Active Shift</h3>
                    <p style="color: var(--pos-secondary);">There is no active shift for the selected store and cashier.</p>
                    <?php if ($selectedStoreId && $selectedCashierId): ?>
                        <button onclick="startShift()" class="btn btn-primary" style="margin-top: 12px;">Start New Shift</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Shift Report -->
            <?php if ($shiftReport): ?>
                <div class="dashboard-card" style="margin-bottom: 24px;">
                    <h3>Shift Report #<?php echo $shiftReport['shift']['id']; ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Sales</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($shiftReport['summary']['total_sales'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Transactions</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($shiftReport['summary']['total_transactions'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Avg Sale</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($shiftReport['summary']['avg_sale_amount'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Cash Sales</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($shiftReport['summary']['cash_sales'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Card Sales</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($shiftReport['summary']['card_sales'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Mobile Money</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($shiftReport['summary']['mobile_money_sales'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($shiftReport['top_products'])): ?>
                        <h4>Top Products</h4>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shiftReport['top_products'] as $product): ?>
                                    <tr>
                                        <td><?php echo e($product['name']); ?></td>
                                        <td><?php echo e($product['sku']); ?></td>
                                        <td><?php echo number_format($product['total_quantity']); ?></td>
                                        <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Recent Shifts -->
            <?php if (!empty($recentShifts)): ?>
                <div class="dashboard-card">
                    <h3>Recent Shifts</h3>
                    <div style="overflow-x: auto;">
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Started</th>
                                    <th>Ended</th>
                                    <th>Store</th>
                                    <th>Opening Cash</th>
                                    <th>Total Sales</th>
                                    <th>Transactions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentShifts as $shift): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($shift['shift_start'])); ?></td>
                                        <td><?php echo $shift['shift_end'] ? date('Y-m-d H:i', strtotime($shift['shift_end'])) : '-'; ?></td>
                                        <td><?php echo e($shift['store_name'] ?? '-'); ?></td>
                                        <td><?php echo formatCurrency($shift['opening_cash'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($shift['total_sales'] ?? 0); ?></td>
                                        <td><?php echo number_format($shift['total_transactions'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $shift['status'] === 'completed' ? 'success' : 'info'; ?>">
                                                <?php echo strtoupper($shift['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=admin&tab=shifts&shift_id=<?php echo $shift['id']; ?>" class="btn btn-sm btn-outline">View Report</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        const shiftsApiUrl = '<?php echo $baseUrl; ?>/pos/api/shifts.php';
        const currentStoreIdForShift = <?php echo $selectedStoreId ?? 'null'; ?>;
        
        function startShift() {
            const openingCash = parseFloat(prompt('Enter opening cash amount:', '0')) || 0;
            if (openingCash < 0) {
                alert('Opening cash cannot be negative');
                return;
            }
            
            fetch(shiftsApiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'start',
                    store_id: currentStoreIdForShift,
                    opening_cash: openingCash
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Shift started successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to start shift');
                }
            })
            .catch(e => {
                console.error(e);
                alert('Failed to start shift');
            });
        }
        
        function endShift() {
            const closingCash = prompt('Enter closing cash amount (optional):', '');
            const cash = closingCash !== '' && closingCash !== null ? parseFloat(closingCash) : null;
            const notes = prompt('Enter notes (optional):', '') || null;
            
            if (cash !== null && cash < 0) {
                alert('Closing cash cannot be negative');
                return;
            }
            
            fetch(shiftsApiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'end',
                    store_id: currentStoreIdForShift,
                    closing_cash: cash,
                    notes: notes
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Shift ended successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to end shift');
                }
            })
            .catch(e => {
                console.error(e);
                alert('Failed to end shift');
            });
        }
        
        function formatCurrency(amount) {
            return '<?php echo getCurrency(); ?> ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        </script>
        
    <?php elseif ($activeTab === 'performance'): ?>
        <?php
        // Performance Tab - Real-time Dashboard and Sales Reports
        $reportingService = new PosReportingService();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $dashboard = $reportingService->getDashboardSnapshot($userId);
        $stores = $repo->getStores(false);
        $selectedStoreId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
        $reportType = $_GET['report'] ?? 'dashboard';
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        // Get cashiers for performance report
        $cashiersStmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('cashier', 'admin', 'manager') ORDER BY full_name");
        $cashiers = $cashiersStmt->fetchAll(PDO::FETCH_ASSOC);
        $selectedCashierId = isset($_GET['cashier_id']) ? (int)$_GET['cashier_id'] : null;
        
        // Load report data based on type
        $performanceData = null;
        $salesReportData = null;
        if ($reportType === 'performance' && $selectedCashierId) {
            try {
                $performanceData = $repo->getCashierPerformance($selectedCashierId, $startDate, $endDate);
            } catch (Throwable $e) {
                // Continue without data
            }
        } elseif ($reportType === 'sales') {
            try {
                $salesReportData = $reportingService->getSalesReport($startDate, $endDate, $selectedStoreId);
            } catch (Throwable $e) {
                // Continue without data
            }
        }
        ?>
        
        <div class="dashboard-card">
            <h2>📊 Performance & Analytics</h2>
            
            <!-- Report Type Selection -->
            <div style="background: var(--pos-input-bg); padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                <form method="get" action="<?php echo $baseUrl; ?>/pos/index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <input type="hidden" name="action" value="admin">
                    <input type="hidden" name="tab" value="performance">
                    
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report" class="form-control" onchange="this.form.submit()">
                            <option value="dashboard" <?php echo $reportType === 'dashboard' ? 'selected' : ''; ?>>Real-time Dashboard</option>
                            <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="performance" <?php echo $reportType === 'performance' ? 'selected' : ''; ?>>Cashier Performance</option>
                        </select>
                    </div>
                    
                    <?php if ($reportType !== 'dashboard'): ?>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Store</label>
                            <select name="store_id" class="form-control">
                                <option value="">All Stores</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>" <?php echo $selectedStoreId == $store['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($store['store_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($reportType === 'performance'): ?>
                            <div class="form-group">
                                <label>Cashier</label>
                                <select name="cashier_id" class="form-control" required>
                                    <option value="">Select Cashier</option>
                                    <?php foreach ($cashiers as $cashier): ?>
                                        <option value="<?php echo $cashier['id']; ?>" <?php echo $selectedCashierId == $cashier['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($cashier['full_name']); ?> (<?php echo e($cashier['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Real-time Dashboard -->
            <?php if ($reportType === 'dashboard'): ?>
                <?php
                $today = $dashboard['summary']['today'] ?? [];
                $week = $dashboard['summary']['week'] ?? [];
                $month = $dashboard['summary']['month'] ?? [];
                ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="dashboard-card">
                        <h3>Today</h3>
                        <div style="font-size: 32px; font-weight: 700; margin: 12px 0;"><?php echo formatCurrency($today['total_sales'] ?? 0); ?></div>
                        <div style="color: var(--pos-secondary);"><?php echo number_format($today['total_transactions'] ?? 0); ?> transactions</div>
                    </div>
                    <div class="dashboard-card">
                        <h3>This Week</h3>
                        <div style="font-size: 32px; font-weight: 700; margin: 12px 0;"><?php echo formatCurrency($week['total_sales'] ?? 0); ?></div>
                        <div style="color: var(--pos-secondary);"><?php echo number_format($week['total_transactions'] ?? 0); ?> transactions</div>
                    </div>
                    <div class="dashboard-card">
                        <h3>This Month</h3>
                        <div style="font-size: 32px; font-weight: 700; margin: 12px 0;"><?php echo formatCurrency($month['total_sales'] ?? 0); ?></div>
                        <div style="color: var(--pos-secondary);"><?php echo number_format($month['total_transactions'] ?? 0); ?> transactions</div>
                    </div>
                </div>
                
                <!-- Charts would go here -->
                <div class="dashboard-card">
                    <h3>Sales Trends</h3>
                    <p style="color: var(--pos-secondary);">Chart visualization would be implemented here using Chart.js or similar library.</p>
                </div>
            <?php endif; ?>
            
            <!-- Sales Report -->
            <?php if ($reportType === 'sales' && $salesReportData): ?>
                <div class="dashboard-card">
                    <h3>Sales Report</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Sales</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($salesReportData['summary']['total_sales'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Transactions</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($salesReportData['summary']['total_transactions'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Avg Sale</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($salesReportData['summary']['avg_sale_amount'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($salesReportData['daily_data'])): ?>
                        <h4>Daily Breakdown</h4>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Total Sales</th>
                                    <th>Discounts</th>
                                    <th>Tax</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesReportData['daily_data'] as $day): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($day['sale_date'])); ?></td>
                                        <td><?php echo number_format($day['transaction_count']); ?></td>
                                        <td><?php echo formatCurrency($day['total_sales']); ?></td>
                                        <td><?php echo formatCurrency($day['total_discounts'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($day['total_tax'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Cashier Performance Report -->
            <?php if ($reportType === 'performance' && $performanceData): ?>
                <div class="dashboard-card">
                    <h3>Cashier Performance Report</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Transactions</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($performanceData['performance']['total_transactions'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Total Sales</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($performanceData['performance']['total_sales'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Avg Sale</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo formatCurrency($performanceData['performance']['avg_sale_amount'] ?? 0); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--pos-secondary); margin-bottom: 4px;">Days Worked</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($performanceData['performance']['days_worked'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($performanceData['payment_methods'])): ?>
                        <h4>Payment Methods</h4>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performanceData['payment_methods'] as $method): ?>
                                    <tr>
                                        <td><?php echo e(ucfirst(str_replace('_', ' ', $method['payment_method']))); ?></td>
                                        <td><?php echo number_format($method['transaction_count']); ?></td>
                                        <td><?php echo formatCurrency($method['total_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function openStoreModal() {
    document.getElementById('storeModal').classList.add('active');
    document.getElementById('storeAction').value = 'create_store';
    document.getElementById('storeId').value = '';
    document.getElementById('storeForm').reset();
    document.getElementById('storeModalTitle').textContent = 'Add Store';
}

function editStore(id, code, name, location, active) {
    document.getElementById('storeModal').classList.add('active');
    document.getElementById('storeAction').value = 'update_store';
    document.getElementById('storeId').value = id;
    document.getElementById('storeCode').value = code;
    document.getElementById('storeName').value = name;
    document.getElementById('storeLocation').value = location || '';
    document.getElementById('storeActive').checked = active == 1;
    document.getElementById('storeModalTitle').textContent = 'Edit Store';
}

function closeStoreModal() {
    document.getElementById('storeModal').classList.remove('active');
}

function openProductModal() {
    document.getElementById('productModal').classList.add('active');
    document.getElementById('productAction').value = 'create_product';
    document.getElementById('productId').value = '';
    document.getElementById('productForm').reset();
    document.getElementById('productModalTitle').textContent = 'Add Product';
}

function editProduct(product) {
    document.getElementById('productModal').classList.add('active');
    document.getElementById('productAction').value = 'update_product';
    document.getElementById('productId').value = product.id;
    document.getElementById('productSku').value = product.sku || '';
    document.getElementById('productName').value = product.name || '';
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('productCategory').value = product.category_id || '';
    document.getElementById('productBarcode').value = product.barcode || '';
    document.getElementById('productUnitPrice').value = product.unit_price || '';
    document.getElementById('productCostPrice').value = product.cost_price || '';
    document.getElementById('productTaxRate').value = product.tax_rate || '0';
    document.getElementById('productTrackInventory').checked = product.track_inventory == 1;
    document.getElementById('productIsActive').checked = product.is_active == 1;
    document.getElementById('productExposeToShop').checked = product.expose_to_shop == 1;
    document.getElementById('productModalTitle').textContent = 'Edit Product';
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('active');
}

function openCategoryModal() {
    document.getElementById('categoryModal').classList.add('active');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
}

function openInventoryModal() {
    document.getElementById('inventoryModal').classList.add('active');
}

function closeInventoryModal() {
    document.getElementById('inventoryModal').classList.remove('active');
}

function filterCatalog() {
    const search = document.getElementById('catalogSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#catalogTableBody tr');
    rows.forEach(row => {
        const name = row.getAttribute('data-product-name') || '';
        const sku = row.getAttribute('data-product-sku') || '';
        if (name.includes(search) || sku.includes(search)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function loadInventory() {
    const storeId = document.getElementById('inventoryStoreSelect').value;
    window.location.href = '<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=inventory&store_id=' + storeId;
}

// Close modals on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Material Returns Functions
function openAcceptReturnModal(returnData) {
    document.getElementById('acceptReturnId').value = returnData.id;
    document.getElementById('acceptReturnNumber').textContent = returnData.request_number;
    document.getElementById('acceptReturnMaterial').textContent = returnData.material_name + ' (' + returnData.material_type + ')';
    document.getElementById('acceptReturnRequestedQty').textContent = returnData.quantity + ' ' + (returnData.unit_of_measure || 'pcs');
    document.getElementById('acceptReturnActualQty').value = returnData.quantity;
    document.getElementById('acceptReturnActualQty').max = returnData.quantity * 2; // Allow up to 2x for verification
    document.getElementById('acceptReturnQualityCheck').value = '';
    document.getElementById('acceptReturnModal').classList.add('active');
}

function closeAcceptReturnModal() {
    document.getElementById('acceptReturnModal').classList.remove('active');
}

function submitAcceptReturn() {
    const returnId = document.getElementById('acceptReturnId').value;
    const actualQty = parseFloat(document.getElementById('acceptReturnActualQty').value);
    const qualityCheck = document.getElementById('acceptReturnQualityCheck').value;
    
    if (!actualQty || actualQty <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'accept');
    formData.append('return_id', returnId);
    formData.append('actual_quantity', actualQty);
    formData.append('quality_check', qualityCheck);
    
    fetch('<?php echo $baseUrl; ?>/pos/api/material-returns.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Return accepted successfully! Materials have been added back to inventory.');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to accept return'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function openRejectReturnModal(returnId, requestNumber) {
    document.getElementById('rejectReturnId').value = returnId;
    document.getElementById('rejectReturnNumber').textContent = requestNumber;
    document.getElementById('rejectReturnReason').value = '';
    document.getElementById('rejectReturnModal').classList.add('active');
}

function closeRejectReturnModal() {
    document.getElementById('rejectReturnModal').classList.remove('active');
}

function submitRejectReturn() {
    const returnId = document.getElementById('rejectReturnId').value;
    const reason = document.getElementById('rejectReturnReason').value.trim();
    
    if (!reason) {
        alert('Please provide a reason for rejection');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reject');
    formData.append('return_id', returnId);
    formData.append('rejection_reason', reason);
    
    fetch('<?php echo $baseUrl; ?>/pos/api/material-returns.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Return rejected successfully.');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to reject return'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<!-- Accept Return Modal -->
<div id="acceptReturnModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">✓ Accept Material Return</h3>
            <button onclick="closeAcceptReturnModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--pos-secondary);">&times;</button>
        </div>
        
        <input type="hidden" id="acceptReturnId">
        
        <div class="form-group">
            <label><strong>Request Number:</strong></label>
            <div id="acceptReturnNumber" style="padding: 8px; background: var(--pos-input-bg); border-radius: 6px; margin-top: 5px;"></div>
        </div>
        
        <div class="form-group">
            <label><strong>Material:</strong></label>
            <div id="acceptReturnMaterial" style="padding: 8px; background: var(--pos-input-bg); border-radius: 6px; margin-top: 5px;"></div>
        </div>
        
        <div class="form-group">
            <label><strong>Requested Quantity:</strong></label>
            <div id="acceptReturnRequestedQty" style="padding: 8px; background: var(--pos-input-bg); border-radius: 6px; margin-top: 5px;"></div>
        </div>
        
        <div class="form-group">
            <label for="acceptReturnActualQty"><strong>Actual Quantity Received:</strong> <span style="color: var(--pos-danger);">*</span></label>
            <input type="number" id="acceptReturnActualQty" class="form-control" 
                   min="0.01" step="0.01" required 
                   placeholder="Enter actual quantity received">
            <small style="color: var(--pos-secondary); font-size: 12px;">Verify the actual quantity received and enter it here</small>
        </div>
        
        <div class="form-group">
            <label for="acceptReturnQualityCheck"><strong>Quality Check Notes:</strong></label>
            <textarea id="acceptReturnQualityCheck" class="form-control" rows="3" 
                      placeholder="Enter quality verification notes (e.g., 'All items in good condition', 'Some items damaged', etc.)"></textarea>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button onclick="closeAcceptReturnModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
            <button onclick="submitAcceptReturn()" class="btn btn-primary" style="flex: 1; background: var(--pos-success);">Accept Return</button>
        </div>
    </div>
</div>

<!-- Reject Return Modal -->
<div id="rejectReturnModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">✗ Reject Material Return</h3>
            <button onclick="closeRejectReturnModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--pos-secondary);">&times;</button>
        </div>
        
        <input type="hidden" id="rejectReturnId">
        
        <div class="form-group">
            <label><strong>Request Number:</strong></label>
            <div id="rejectReturnNumber" style="padding: 8px; background: var(--pos-input-bg); border-radius: 6px; margin-top: 5px;"></div>
        </div>
        
        <div class="form-group">
            <label for="rejectReturnReason"><strong>Reason for Rejection:</strong> <span style="color: var(--pos-danger);">*</span></label>
            <textarea id="rejectReturnReason" class="form-control" rows="4" required 
                      placeholder="Please provide a reason for rejecting this return request..."></textarea>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button onclick="closeRejectReturnModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
            <button onclick="submitRejectReturn()" class="btn btn-outline" style="flex: 1; border-color: var(--pos-danger); color: var(--pos-danger);">Reject Return</button>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
