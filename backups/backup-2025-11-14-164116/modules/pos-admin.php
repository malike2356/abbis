<?php
$page_title = 'POS Management & Catalog';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/pos/PosRepository.php';
require_once '../includes/pos/PosValidator.php';
require_once '../includes/pos/PosAccountingSync.php';
require_once '../includes/pos/PosCatalogSync.php'; // Added for catalog sync

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();
$repo = new PosRepository($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedTabs = ['stores', 'catalog', 'inventory', 'sales', 'accounting'];

function pos_admin_redirect(string $tab, string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['pos_admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
    $tab = in_array($tab, ['stores', 'catalog', 'inventory', 'sales', 'accounting'], true) ? $tab : 'stores';
    header('Location: pos-admin.php?tab=' . urlencode($tab));
    exit;
}

function pos_admin_format_money(float $amount): string
{
    return 'GHS ' . number_format($amount, 2);
}

function pos_admin_build_catalog_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params['tab'] = 'catalog';
    return 'pos-admin.php?' . http_build_query($params);
}

function pos_admin_build_inventory_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params['tab'] = 'inventory';
    return 'pos-admin.php?' . http_build_query($params);
}

function pos_admin_render_receipt(array $sale): string
{
    $storeName = htmlspecialchars($sale['store_name'] ?? 'Store');
    $storeCode = htmlspecialchars($sale['store_code'] ?? '');
    $saleNumber = htmlspecialchars($sale['sale_number'] ?? '');
    $date = htmlspecialchars(date('Y-m-d H:i', strtotime($sale['sale_timestamp'] ?? 'now')));
    $cashier = htmlspecialchars((string) ($sale['cashier_id'] ?? ''));
    $customer = htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer');

    $itemsHtml = '';
    foreach ($sale['items'] ?? [] as $item) {
        $name = htmlspecialchars($item['name'] ?? $item['description'] ?? 'Item');
        $sku = htmlspecialchars($item['sku'] ?? '');
        $qty = number_format((float) ($item['quantity'] ?? 0), 2);
        $unit = pos_admin_format_money((float) ($item['unit_price'] ?? 0));
        $total = pos_admin_format_money((float) ($item['line_total'] ?? 0));
        $itemsHtml .= "<tr><td><strong>{$name}</strong><br><small>{$sku}</small></td><td>{$qty}</td><td>{$unit}</td><td>{$total}</td></tr>";
    }

    $paymentsHtml = '';
    foreach ($sale['payments'] ?? [] as $payment) {
        $method = htmlspecialchars(strtoupper(str_replace('_', ' ', $payment['payment_method'] ?? 'unknown')));
        $amount = pos_admin_format_money((float) ($payment['amount'] ?? 0));
        $reference = htmlspecialchars($payment['reference'] ?? '');
        $paymentsHtml .= "<tr><td>{$method}</td><td>{$amount}</td><td>{$reference}</td></tr>";
    }

    $subtotal = pos_admin_format_money((float) ($sale['subtotal_amount'] ?? 0));
    $discount = pos_admin_format_money((float) ($sale['discount_total'] ?? 0));
    $tax = pos_admin_format_money((float) ($sale['tax_total'] ?? 0));
    $total = pos_admin_format_money((float) ($sale['total_amount'] ?? 0));
    $amountPaid = pos_admin_format_money((float) ($sale['amount_paid'] ?? 0));
    $changeDue = pos_admin_format_money((float) ($sale['change_due'] ?? max(($sale['amount_paid'] ?? 0) - ($sale['total_amount'] ?? 0), 0)));

    $notes = htmlspecialchars($sale['notes'] ?? '');
    $notesHtml = $notes !== '' ? "<div class='notes'><strong>Notes:</strong> {$notes}</div>" : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {$saleNumber}</title>
    <style>
        body { font-family: 'Courier New', monospace; margin: 0; padding: 16px; background: #f8fafc; color: #0f172a; }
        .receipt { max-width: 420px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 12px 24px rgba(15,23,42,0.1); }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        .subhead { text-align: center; font-size: 12px; color: #475569; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { padding: 6px 4px; font-size: 13px; }
        th { text-align: left; border-bottom: 1px dashed #cbd5f5; }
        td { border-bottom: 1px dashed rgba(203,213,225,0.5); }
        .totals td { border: none; }
        .totals td:last-child { text-align: right; }
        .footer { font-size: 12px; text-align: center; color: #475569; margin-top: 16px; }
        .notes { font-size: 12px; color: #0f172a; background: #f1f5f9; padding: 8px; border-radius: 8px; }
        @media print { body { background: #fff; } .receipt { box-shadow: none; margin: 0; } }
    </style>
</head>
<body>
    <div class="receipt">
        <h1>{$storeName}</h1>
        <div class="subhead">{$storeCode}</div>
        <div class="subhead">Sale #: {$saleNumber} &nbsp;|&nbsp; {$date}</div>
        <div class="subhead">Cashier: {$cashier} &nbsp;|&nbsp; Customer: {$customer}</div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHtml}
            </tbody>
        </table>

        <table class="totals">
            <tr><td>Subtotal</td><td>{$subtotal}</td></tr>
            <tr><td>Discount</td><td>{$discount}</td></tr>
            <tr><td>Tax</td><td>{$tax}</td></tr>
            <tr><td><strong>Grand Total</strong></td><td><strong>{$total}</strong></td></tr>
            <tr><td>Amount Paid</td><td>{$amountPaid}</td></tr>
            <tr><td>Change Due</td><td>{$changeDue}</td></tr>
        </table>

        <h2 style="font-size: 14px; margin-bottom: 8px;">Payments</h2>
        <table>
            <thead><tr><th>Method</th><th>Amount</th><th>Reference</th></tr></thead>
            <tbody>{$paymentsHtml}</tbody>
        </table>

        {$notesHtml}

        <div class="footer">Thank you for shopping with us!</div>
    </div>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
HTML;
}

$flash = $_SESSION['pos_admin_flash'] ?? null;
unset($_SESSION['pos_admin_flash']);

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'print_receipt' && isset($_GET['sale_id'])) {
    $saleId = max(0, (int) $_GET['sale_id']);
    $sale = $repo->getSaleForAccounting($saleId);
    if (!$sale) {
        http_response_code(404);
        echo 'Sale not found.';
        exit;
    }
    $receiptHtml = pos_admin_render_receipt($sale);
    $printedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $repo->recordReceipt($saleId, 'thermal', $receiptHtml, $printedBy);
    echo $receiptHtml;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'stores';
    try {
        switch ($action) {
            case 'create_store':
                $payload = PosValidator::validateStorePayload($_POST);
                $repo->createStore($payload);
                pos_admin_redirect($tab, 'success', 'Store created successfully.');
                break;
            case 'update_store':
                $storeId = (int) ($_POST['store_id'] ?? 0);
                if ($storeId <= 0) {
                    throw new InvalidArgumentException('Invalid store selected.');
                }
                $payload = PosValidator::validateStorePayload($_POST, true);
                $repo->updateStore($storeId, $payload);
                pos_admin_redirect($tab, 'success', 'Store updated successfully.');
                break;
            case 'toggle_store':
                $storeId = (int) ($_POST['store_id'] ?? 0);
                if ($storeId <= 0) {
                    throw new InvalidArgumentException('Invalid store selected.');
                }
                $target = $_POST['target_status'] ?? 'inactive';
                $repo->toggleStoreActive($storeId, $target === 'active');
                pos_admin_redirect($tab, 'success', 'Store status updated.');
                break;
            case 'set_primary_store':
                $storeId = (int) ($_POST['store_id'] ?? 0);
                if ($storeId <= 0) {
                    throw new InvalidArgumentException('Invalid store selected.');
                }
                $repo->setPrimaryStore($storeId);
                pos_admin_redirect($tab, 'success', 'Primary store updated.');
                break;
            case 'create_category':
                $payload = PosValidator::validateCategoryPayload($_POST);
                $repo->createCategory($payload);
                pos_admin_redirect($tab, 'success', 'Category created successfully.');
                break;
            case 'update_category':
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                if ($categoryId <= 0) {
                    throw new InvalidArgumentException('Invalid category selected.');
                }
                $payload = PosValidator::validateCategoryPayload($_POST);
                $repo->updateCategory($categoryId, $payload);
                pos_admin_redirect($tab, 'success', 'Category updated successfully.');
                break;
            case 'toggle_category':
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                if ($categoryId <= 0) {
                    throw new InvalidArgumentException('Invalid category selected.');
                }
                $target = $_POST['target_status'] ?? 'inactive';
                $repo->toggleCategoryActive($categoryId, $target === 'active');
                pos_admin_redirect($tab, 'success', 'Category status updated.');
                break;
            case 'save_product':
                $payload = PosValidator::validateProductPayload($_POST, !empty($_POST['product_id']));
                if (!empty($_POST['product_id'])) {
                    $repo->updateProduct((int) $_POST['product_id'], $payload);
                    pos_admin_redirect($tab, 'success', 'Product updated successfully.');
                } else {
                    $repo->createProduct($payload);
                    pos_admin_redirect($tab, 'success', 'Product added successfully.');
                }
                break;
            case 'sync_catalog':
                try {
                    $catalogStmt = $pdo->query("SELECT id FROM catalog_items WHERE item_type = 'product'");
                    $catalogIds = $catalogStmt->fetchAll(PDO::FETCH_COLUMN, 0);

                    if (empty($catalogIds)) {
                        pos_admin_redirect($tab, 'warning', 'No catalog products found to sync.');
                    }

                    $synced = 0;
                    $skipped = 0;
                    $errors = 0;

                    foreach ($catalogIds as $catalogId) {
                        try {
                            $productId = $repo->upsertProductFromCatalog((int) $catalogId);
                            if ($productId) {
                                $synced++;
                            } else {
                                $skipped++;
                            }
                        } catch (Throwable $inner) {
                            $errors++;
                            error_log('[POS Catalog Sync] Failed to import catalog item ' . $catalogId . ': ' . $inner->getMessage());
                        }
                    }

                    $messageParts = [];
                    if ($synced) {
                        $messageParts[] = $synced . ' imported/updated';
                    }
                    if ($skipped) {
                        $messageParts[] = $skipped . ' skipped';
                    }
                    if ($errors) {
                        $messageParts[] = $errors . ' failed';
                    }
                    $summary = 'Catalog sync complete. ' . implode(', ', $messageParts) . '.';
                    pos_admin_redirect($tab, $errors ? 'warning' : 'success', $summary);
                } catch (Throwable $e) {
                    pos_admin_redirect($tab, 'error', 'Catalog sync failed: ' . $e->getMessage());
                }
                break;
            case 'inventory_adjust':
                $payload = PosValidator::validateInventoryAdjustment($_POST);
                if (abs($payload['quantity_delta']) <= 0) {
                    throw new InvalidArgumentException('Quantity delta cannot be zero.');
                }
                $payload['performed_by'] = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $payload['reference_type'] = $payload['reference_type'] ?? 'manual_adjustment';
                $payload['reference_id'] = $payload['reference_id'] ?? null;
                $repo->adjustStock($payload);
                pos_admin_redirect($tab, 'success', 'Inventory adjustment recorded.');
                break;
            case 'sync_accounting':
                $limit = max(1, min(100, (int) ($_POST['sync_limit'] ?? 25)));
                $sync = (new PosAccountingSync($pdo))->syncPendingSales($limit);
                $message = sprintf('Processed %d sale(s): %d synced, %d failed.', $sync['processed'], $sync['synced'], $sync['failed']);
                $type = $sync['failed'] > 0 ? 'warning' : 'success';
                if (!empty($sync['errors'])) {
                    $firstError = $sync['errors'][0]['message'] ?? '';
                    if ($firstError !== '') {
                        $message .= ' Last error: ' . $firstError;
                    }
                }
                pos_admin_redirect($tab, $type, $message);
                break;
            default:
                pos_admin_redirect($tab, 'error', 'Unknown action.');
        }
    } catch (Throwable $e) {
        pos_admin_redirect($tab, 'error', $e->getMessage());
    }
}

$activeTab = $_GET['tab'] ?? 'stores';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'stores';
}

$catalogAutoSyncNotice = null;

if ($activeTab === 'catalog' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $unsyncedStmt = $pdo->query("
            SELECT ci.id, ci.name, ci.sku
            FROM catalog_items ci
            WHERE ci.item_type = 'product'
              AND NOT EXISTS (
                    SELECT 1
                    FROM pos_products pp
                    WHERE pp.catalog_item_id = ci.id
                       OR (ci.sku IS NOT NULL AND ci.sku <> '' AND pp.sku = ci.sku)
                )
        ");
        $unsyncedItems = $unsyncedStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($unsyncedItems)) {
            $syncedCount = 0;
            $failedItems = [];

            foreach ($unsyncedItems as $unsyncedItem) {
                try {
                    $repo->upsertProductFromCatalog((int) $unsyncedItem['id']);
                    $syncedCount++;
                } catch (Throwable $syncException) {
                    $failedItems[] = $unsyncedItem['name'] ?: ('ID ' . $unsyncedItem['id']);
                    error_log('[POS Catalog AutoSync] Failed to sync catalog item ' . $unsyncedItem['id'] . ': ' . $syncException->getMessage());
                }
            }

            $catalogAutoSyncNotice = [
                'synced' => $syncedCount,
                'total' => count($unsyncedItems),
                'failed' => $failedItems,
            ];
        }
    } catch (Throwable $autoSyncError) {
        error_log('[POS Catalog AutoSync] ' . $autoSyncError->getMessage());
        $catalogAutoSyncNotice = [
            'synced' => 0,
            'total' => 0,
            'failed' => [$autoSyncError->getMessage()],
            'error' => true,
        ];
    }
}

$stores = $repo->getStores(false);
$categories = $repo->listCategories(true);
$categoryMap = [];
foreach ($categories as $category) {
    if (isset($category['id'])) {
        $categoryMap[$category['id']] = $category;
    }
}

$editStoreId = isset($_GET['edit_store']) ? (int) $_GET['edit_store'] : null;
$editStore = $editStoreId ? $repo->getStore($editStoreId) : null;

$editCategoryId = isset($_GET['edit_category']) ? (int) $_GET['edit_category'] : null;
$editCategory = $editCategoryId ? $repo->getCategory($editCategoryId) : null;

$productSearch = trim($_GET['product_search'] ?? '');
$productStatus = $_GET['product_status'] ?? 'all';
$catalogPage = max(1, (int) ($_GET['catalog_page'] ?? 1));
$pageSize = 5;

$productFilters = [];
if ($productSearch !== '') {
    $productFilters['search'] = $productSearch;
}
if ($productStatus === 'active') {
    $productFilters['is_active'] = 1;
} elseif ($productStatus === 'inactive') {
    $productFilters['is_active'] = 0;
}

$totalProducts = $repo->countProducts($productFilters);
$totalPages = max(1, (int) ceil($totalProducts / $pageSize));
if ($catalogPage > $totalPages) {
    $catalogPage = $totalPages;
}
$catalogOffset = ($catalogPage - 1) * $pageSize;
$products = $repo->listProducts($productFilters, $pageSize, $catalogOffset);

$editProductId = isset($_GET['edit_product']) ? (int) $_GET['edit_product'] : null;
$editProduct = $editProductId ? $repo->getProduct($editProductId) : null;

$primaryStoreId = null;
foreach ($stores as $store) {
    if (!empty($store['is_primary'])) {
        $primaryStoreId = (int) $store['id'];
        break;
    }
}

$inventoryStoreId = isset($_GET['inventory_store']) ? (int) $_GET['inventory_store'] : ($primaryStoreId ?? ($stores[0]['id'] ?? 0));
$inventoryPage = max(1, (int) ($_GET['inventory_page'] ?? 1));
$inventoryPageSize = 5;
$inventoryTotalItems = 0;
$inventoryTotalPages = 1;
$inventoryRecords = [];

if ($inventoryStoreId > 0) {
    $inventoryTotalItems = $repo->countInventoryByStore($inventoryStoreId);
    $inventoryTotalPages = max(1, (int) ceil($inventoryTotalItems / $inventoryPageSize));
    if ($inventoryPage > $inventoryTotalPages) {
        $inventoryPage = $inventoryTotalPages;
    }
    $inventoryOffset = ($inventoryPage - 1) * $inventoryPageSize;
    $inventoryRecords = $repo->listInventoryByStore($inventoryStoreId, $inventoryPageSize, $inventoryOffset);
}

$activeProductsForAdjustments = $repo->listProducts(['is_active' => 1], 500, 0);
$inventoryProductOptions = [];
foreach ($activeProductsForAdjustments as $option) {
    $inventoryProductOptions[] = [
        'id' => $option['id'],
        'name' => $option['name'],
        'sku' => $option['sku'] ?? '',
        'track_inventory' => (bool) $option['track_inventory'],
    ];
}

$salesStoreId = isset($_GET['sales_store']) ? (int) $_GET['sales_store'] : 0;
$sales = $repo->listRecentSales($salesStoreId ?: null, 25);

$accountingQueue = $repo->listAccountingQueue(25);

require_once '../includes/header.php';
?>

<style>
.pos-admin-tabs { display:flex; gap:10px; margin: 20px 0; flex-wrap: wrap; }
.pos-admin-tabs a { padding:8px 16px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text); background: var(--bg); transition: all 0.2s ease; font-weight:600; }
.pos-admin-tabs a:hover { border-color: var(--primary); color: var(--primary); }
.pos-admin-tabs a.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 8px 20px rgba(14,165,233,0.25); }
.pos-admin-section { display:none; margin-bottom: 32px; }
.pos-admin-section.active { display:block; }
.pos-grid { display:grid; gap:16px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
.small-form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
.catalog-pagination { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:16px; flex-wrap:wrap; }
.catalog-pagination .pager-buttons { display:flex; gap:8px; flex-wrap:wrap; }
.catalog-pagination .pager-info { color: var(--secondary); font-size:0.9rem; }
.small-form-group label { font-weight:600; font-size:0.9rem; }
.small-form-group input,
.small-form-group select,
.small-form-group textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); background: var(--input); font-size:0.95rem; }
.form-check { display:flex; align-items:center; gap:8px; margin:4px 0; font-size:0.9rem; }
.form-check input { width:16px; height:16px; }
.table-responsive { overflow-x:auto; }
.pos-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:0.75rem; font-weight:600; background: rgba(15,23,42,0.08); color: var(--secondary); }
.pos-status.primary { background: rgba(14,165,233,0.15); color:#0369a1; }
.pos-status.active { background: rgba(34,197,94,0.15); color:#15803d; }
.pos-status.inactive { background: rgba(239,68,68,0.15); color:#b91c1c; }
.badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:0.75rem; background: rgba(15,23,42,0.08); color: var(--secondary); font-weight:600; }
.badge-success { background: rgba(34,197,94,0.15); color:#15803d; }
.badge-warning { background: rgba(245,158,11,0.18); color:#b45309; }
.badge-danger { background: rgba(239,68,68,0.18); color:#b91c1c; }
.pos-inline-actions form { display:inline-block; margin:2px; }
.pos-inline-actions a.btn { margin:2px; }
</style>

<?php if ($flash): ?>
    <div class="alert <?php echo $flash['type'] === 'error' ? 'alert-danger' : ($flash['type'] === 'warning' ? 'alert-warning' : 'alert-success'); ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<?php if ($catalogAutoSyncNotice && $activeTab === 'catalog'): ?>
    <?php
        $hasErrors = !empty($catalogAutoSyncNotice['failed']);
        $syncedCount = (int) ($catalogAutoSyncNotice['synced'] ?? 0);
        $totalCount = (int) ($catalogAutoSyncNotice['total'] ?? 0);
        $failedNames = array_slice($catalogAutoSyncNotice['failed'] ?? [], 0, 5);
    ?>
    <div class="alert <?php echo $hasErrors ? 'alert-warning' : 'alert-info'; ?>">
        <?php if ($totalCount > 0): ?>
            <?php echo htmlspecialchars($syncedCount); ?> of <?php echo htmlspecialchars($totalCount); ?> catalog item<?php echo $totalCount !== 1 ? 's' : ''; ?> auto-synced from Resources.
        <?php endif; ?>
        <?php if ($hasErrors): ?>
            <br>
            <strong>Could not sync:</strong> <?php echo htmlspecialchars(implode(', ', $failedNames)); ?>
            <?php if (count($failedNames) < count($catalogAutoSyncNotice['failed'])): ?>
                <?php echo '…'; ?>
            <?php endif; ?>
        <?php elseif ($totalCount === 0): ?>
            Catalog is up to date with Resources.
        <?php endif; ?>
    </div>
<?php endif; ?>

<nav class="pos-admin-tabs">
    <a href="?tab=stores" class="<?php echo $activeTab === 'stores' ? 'active' : ''; ?>">Stores</a>
    <a href="?tab=catalog" class="<?php echo $activeTab === 'catalog' ? 'active' : ''; ?>">Catalog</a>
    <a href="?tab=inventory" class="<?php echo $activeTab === 'inventory' ? 'active' : ''; ?>">Inventory</a>
    <a href="?tab=sales" class="<?php echo $activeTab === 'sales' ? 'active' : ''; ?>">Sales</a>
    <a href="?tab=accounting" class="<?php echo $activeTab === 'accounting' ? 'active' : ''; ?>">Accounting</a>
</nav>

<section class="pos-admin-section <?php echo $activeTab === 'stores' ? 'active' : ''; ?>" id="pos-tab-stores">
    <div class="pos-grid">
        <div class="dashboard-card">
            <h2 style="margin-top:0;"><?php echo $editStore ? 'Edit Store' : 'Add Store'; ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editStore ? 'update_store' : 'create_store'; ?>">
                <input type="hidden" name="tab" value="stores">
                <?php if ($editStore): ?>
                    <input type="hidden" name="store_id" value="<?php echo (int) $editStore['id']; ?>">
                <?php endif; ?>
                <div class="small-form-group">
                    <label>Store Code</label>
                    <input type="text" name="store_code" value="<?php echo htmlspecialchars($editStore['store_code'] ?? ''); ?>" required>
                </div>
                <div class="small-form-group">
                    <label>Store Name</label>
                    <input type="text" name="store_name" value="<?php echo htmlspecialchars($editStore['store_name'] ?? ''); ?>" required>
                </div>
                <div class="small-form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($editStore['location'] ?? ''); ?>">
                </div>
                <div class="small-form-group">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($editStore['contact_phone'] ?? ''); ?>">
                </div>
                <div class="small-form-group">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" value="<?php echo htmlspecialchars($editStore['contact_email'] ?? ''); ?>">
                </div>
                <input type="hidden" name="is_primary" value="0">
                <label class="form-check">
                    <input type="checkbox" name="is_primary" value="1" <?php echo !empty($editStore['is_primary']) ? 'checked' : ''; ?>>
                    <span>Make primary store</span>
                </label>
                <input type="hidden" name="is_active" value="0">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" <?php echo !isset($editStore) || !empty($editStore['is_active']) ? 'checked' : ''; ?>>
                    <span>Store is active</span>
                </label>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button type="submit" class="btn btn-primary"><?php echo $editStore ? 'Update Store' : 'Add Store'; ?></button>
                    <?php if ($editStore): ?>
                        <a href="pos-admin.php?tab=stores" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="dashboard-card">
            <h2 style="margin-top:0;">Stores</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Primary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stores)): ?>
                            <tr><td colspan="7" style="text-align:center; color: var(--secondary);">No stores configured yet.</td></tr>
                        <?php else: foreach ($stores as $store): ?>
                            <?php $isPrimary = !empty($store['is_primary']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($store['store_code']); ?></td>
                                <td><?php echo htmlspecialchars($store['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($store['location'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($store['contact_phone'])): ?>
                                        <div><?php echo htmlspecialchars($store['contact_phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($store['contact_email'])): ?>
                                        <div><?php echo htmlspecialchars($store['contact_email']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $isPrimary ? '<span class="pos-status primary">Primary</span>' : '<span class="pos-status">—</span>'; ?></td>
                                <td><?php echo !empty($store['is_active']) ? '<span class="pos-status active">Active</span>' : '<span class="pos-status inactive">Inactive</span>'; ?></td>
                                <td class="pos-inline-actions">
                                    <a href="pos-admin.php?tab=stores&amp;edit_store=<?php echo (int) $store['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                    <?php if (!$isPrimary): ?>
                                        <form method="post">
                                            <input type="hidden" name="action" value="set_primary_store">
                                            <input type="hidden" name="tab" value="stores">
                                            <input type="hidden" name="store_id" value="<?php echo (int) $store['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline">Set Primary</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="toggle_store">
                                        <input type="hidden" name="tab" value="stores">
                                        <input type="hidden" name="store_id" value="<?php echo (int) $store['id']; ?>">
                                        <input type="hidden" name="target_status" value="<?php echo !empty($store['is_active']) ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" <?php echo !empty($store['is_active']) ? 'onclick="return confirm(\'Deactivate this store?\');"' : ''; ?>>
                                            <?php echo !empty($store['is_active']) ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="pos-admin-section <?php echo $activeTab === 'catalog' ? 'active' : ''; ?>" id="pos-tab-catalog">
    <div class="pos-grid">
        <div class="dashboard-card">
            <h2 style="margin-top:0;"><?php echo $editCategory ? 'Edit Category' : 'Add Category'; ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editCategory ? 'update_category' : 'create_category'; ?>">
                <input type="hidden" name="tab" value="catalog">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?php echo (int) $editCategory['id']; ?>">
                <?php endif; ?>
                <div class="small-form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>" required>
                </div>
                <div class="small-form-group">
                    <label>Parent Category</label>
                    <select name="parent_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $category): ?>
                            <?php if ($editCategory && $category['id'] == $editCategory['id']) { continue; } ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo isset($editCategory['parent_id']) && (int) $editCategory['parent_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
                </div>
                <input type="hidden" name="is_active" value="0">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" <?php echo !isset($editCategory) || !empty($editCategory['is_active']) ? 'checked' : ''; ?>>
                    <span>Category is active</span>
                </label>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button type="submit" class="btn btn-primary"><?php echo $editCategory ? 'Update Category' : 'Add Category'; ?></button>
                    <?php if ($editCategory): ?>
                        <a href="pos-admin.php?tab=catalog" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="dashboard-card">
            <h2 style="margin-top:0;">Categories</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Parent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="4" style="text-align:center; color: var(--secondary);">No categories yet.</td></tr>
                        <?php else: foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['parent_name'] ?? '—'); ?></td>
                                <td><?php echo !empty($category['is_active']) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                <td class="pos-inline-actions">
                                    <a href="pos-admin.php?tab=catalog&amp;edit_category=<?php echo (int) $category['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                    <form method="post">
                                        <input type="hidden" name="action" value="toggle_category">
                                        <input type="hidden" name="tab" value="catalog">
                                        <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                        <input type="hidden" name="target_status" value="<?php echo !empty($category['is_active']) ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline"><?php echo !empty($category['is_active']) ? 'Deactivate' : 'Activate'; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="pos-grid">
        <div class="dashboard-card">
            <h2 style="margin-top:0;"><?php echo $editProduct ? 'Edit Product' : 'Add Product'; ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="tab" value="catalog">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?php echo (int) $editProduct['id']; ?>">
                <?php endif; ?>
                <div class="small-form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" value="<?php echo htmlspecialchars($editProduct['sku'] ?? ''); ?>" required>
                </div>
                <div class="small-form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editProduct['name'] ?? ''); ?>" required>
                </div>
                <div class="small-form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo isset($editProduct['category_id']) && (int) $editProduct['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-form-group">
                    <label>Unit Price</label>
                    <input type="number" name="unit_price" step="0.01" min="0" value="<?php echo htmlspecialchars($editProduct['unit_price'] ?? ''); ?>" required>
                </div>
                <div class="small-form-group">
                    <label>Cost Price (optional)</label>
                    <input type="number" name="cost_price" step="0.01" min="0" value="<?php echo htmlspecialchars($editProduct['cost_price'] ?? ''); ?>">
                </div>
                <div class="small-form-group">
                    <label>Tax Rate % (optional)</label>
                    <input type="number" name="tax_rate" step="0.01" min="0" value="<?php echo htmlspecialchars($editProduct['tax_rate'] ?? ''); ?>">
                </div>
                <div class="small-form-group">
                    <label>Barcode (optional)</label>
                    <input type="text" name="barcode" value="<?php echo htmlspecialchars($editProduct['barcode'] ?? ''); ?>">
                </div>
                <input type="hidden" name="track_inventory" value="0">
                <label class="form-check">
                    <input type="checkbox" name="track_inventory" value="1" <?php echo !empty($editProduct['track_inventory']) ? 'checked' : ''; ?>>
                    <span>Track inventory for this product</span>
                </label>
                <input type="hidden" name="is_active" value="0">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" <?php echo !isset($editProduct) || !empty($editProduct['is_active']) ? 'checked' : ''; ?>>
                    <span>Product is active</span>
                </label>
                <div class="small-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                </div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button type="submit" class="btn btn-primary"><?php echo $editProduct ? 'Update Product' : 'Add Product'; ?></button>
                    <?php if ($editProduct): ?>
                        <a href="pos-admin.php?tab=catalog" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="dashboard-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px;">
                <h2 style="margin:0;">Catalog</h2>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="action" value="sync_catalog">
                    <input type="hidden" name="tab" value="catalog">
                    <button type="submit" class="btn btn-outline">🔁 Sync Existing Catalog</button>
                </form>
            </div>
            <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                <input type="hidden" name="tab" value="catalog">
                <input type="text" name="product_search" value="<?php echo htmlspecialchars($productSearch); ?>" placeholder="Search by name or SKU..." style="flex:1; min-width:220px;">
                <select name="product_status" style="min-width:140px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                    <option value="all" <?php echo $productStatus === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="active" <?php echo $productStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $productStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-outline">Filter</button>
            </form>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Unit Price</th>
                            <th>Inventory</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="7" style="text-align:center; color: var(--secondary);">No products found.</td></tr>
                        <?php else: foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($categoryMap[$product['category_id']]['name'] ?? '—'); ?></td>
                                <td><?php echo pos_admin_format_money((float) ($product['unit_price'] ?? 0)); ?></td>
                                <td><?php echo !empty($product['track_inventory']) ? '<span class="badge badge-success">Tracked</span>' : '<span class="badge">Not tracked</span>'; ?></td>
                                <td><?php echo !empty($product['is_active']) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                <td class="pos-inline-actions">
                                    <a href="<?php echo htmlspecialchars(pos_admin_build_catalog_url(['edit_product' => (int) $product['id']])); ?>" class="btn btn-sm btn-outline">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="catalog-pagination">
                    <span class="pager-info">Page <?php echo $catalogPage; ?> of <?php echo $totalPages; ?></span>
                    <div class="pager-buttons">
                        <?php if ($catalogPage > 1): ?>
                            <a class="btn btn-outline" href="<?php echo htmlspecialchars(pos_admin_build_catalog_url(['catalog_page' => $catalogPage - 1])); ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($catalogPage < $totalPages): ?>
                            <a class="btn btn-outline" href="<?php echo htmlspecialchars(pos_admin_build_catalog_url(['catalog_page' => $catalogPage + 1])); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="pos-admin-section <?php echo $activeTab === 'inventory' ? 'active' : ''; ?>" id="pos-tab-inventory">
    <div class="pos-grid">
        <div class="dashboard-card">
            <h2 style="margin-top:0;">Inventory Snapshot</h2>
            <?php if (empty($stores)): ?>
                <p style="color: var(--secondary);">Configure at least one store to begin tracking inventory.</p>
            <?php else: ?>
            <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                <input type="hidden" name="tab" value="inventory">
                <input type="hidden" name="inventory_page" value="1">
                <select name="inventory_store" style="min-width:220px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo (int) $store['id']; ?>" <?php echo (int) $store['id'] === $inventoryStoreId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($store['store_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline">Load</button>
            </form>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Inventory</th>
                            <th>On Hand</th>
                            <th>Unit Price</th>
                            <th>Value</th>
                            <th>Avg Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventoryRecords)): ?>
                            <tr><td colspan="7" style="text-align:center; color: var(--secondary);">No inventory records yet.</td></tr>
                        <?php else: foreach ($inventoryRecords as $record): ?>
                            <?php
                                $qty = (float) ($record['quantity_on_hand'] ?? 0);
                                $unitPrice = (float) ($record['unit_price'] ?? 0);
                                $value = $qty * $unitPrice;
                                $avgCost = isset($record['average_cost']) ? pos_admin_format_money((float) $record['average_cost']) : '—';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['name']); ?></td>
                                <td><?php echo htmlspecialchars($record['sku'] ?? ''); ?></td>
                                <td><?php echo !empty($record['track_inventory']) ? '<span class="badge badge-success">Tracked</span>' : '<span class="badge">Not tracked</span>'; ?></td>
                                <td><?php echo number_format($qty, 2); ?></td>
                                <td><?php echo pos_admin_format_money($unitPrice); ?></td>
                                <td><?php echo pos_admin_format_money($value); ?></td>
                                <td><?php echo $avgCost; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($inventoryTotalPages > 1): ?>
                <div class="catalog-pagination">
                    <span class="pager-info">Page <?php echo $inventoryPage; ?> of <?php echo $inventoryTotalPages; ?></span>
                    <div class="pager-buttons">
                        <?php if ($inventoryPage > 1): ?>
                            <a class="btn btn-outline" href="<?php echo htmlspecialchars(pos_admin_build_inventory_url(['inventory_page' => $inventoryPage - 1])); ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($inventoryPage < $inventoryTotalPages): ?>
                            <a class="btn btn-outline" href="<?php echo htmlspecialchars(pos_admin_build_inventory_url(['inventory_page' => $inventoryPage + 1])); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="dashboard-card">
            <h2 style="margin-top:0;">Record Adjustment</h2>
            <?php if (empty($stores) || empty($inventoryProductOptions)): ?>
                <p style="color: var(--secondary);">Add stores and active products before recording adjustments.</p>
            <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="inventory_adjust">
                <input type="hidden" name="tab" value="inventory">
                <div class="small-form-group">
                    <label>Store</label>
                    <select name="store_id" required>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo (int) $store['id']; ?>" <?php echo (int) $store['id'] === $inventoryStoreId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($store['store_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-form-group">
                    <label>Product</label>
                    <select name="product_id" required>
                        <?php foreach ($inventoryProductOptions as $option): ?>
                            <option value="<?php echo (int) $option['id']; ?>">
                                <?php echo htmlspecialchars($option['name'] . ' (' . ($option['sku'] ?: 'SKU') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-form-group">
                    <label>Transaction Type</label>
                    <select name="transaction_type" required>
                        <option value="purchase">Purchase / Restock</option>
                        <option value="adjustment">Manual Adjustment</option>
                        <option value="transfer_in">Transfer In</option>
                        <option value="transfer_out">Transfer Out</option>
                        <option value="return_in">Return In</option>
                        <option value="return_out">Return Out</option>
                        <option value="sale">Sale</option>
                    </select>
                </div>
                <div class="small-form-group">
                    <label>Quantity (+/-)</label>
                    <input type="number" step="0.01" name="quantity_delta" required>
                </div>
                <div class="small-form-group">
                    <label>Unit Cost (optional)</label>
                    <input type="number" step="0.01" min="0" name="unit_cost">
                </div>
                <div class="small-form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="2" placeholder="Reason or reference..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Record Adjustment</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="pos-admin-section <?php echo $activeTab === 'sales' ? 'active' : ''; ?>" id="pos-tab-sales">
    <div class="dashboard-card">
        <h2 style="margin-top:0;">Recent Sales</h2>
        <?php if (empty($stores)): ?>
            <p style="color: var(--secondary);">Configure at least one store to begin tracking POS sales.</p>
        <?php else: ?>
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <input type="hidden" name="tab" value="sales">
            <select name="sales_store" style="min-width:220px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                <option value="0">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?php echo (int) $store['id']; ?>" <?php echo (int) $store['id'] === $salesStoreId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($store['store_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline">Filter</button>
        </form>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Store</th>
                        <th>Total</th>
                        <th>Payment Status</th>
                        <th>Accounting</th>
                        <th>Date</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="7" style="text-align:center; color: var(--secondary);">No sales recorded yet.</td></tr>
                    <?php else: foreach ($sales as $sale): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sale['sale_number']); ?></td>
                            <td><?php echo htmlspecialchars($sale['store_name']); ?></td>
                            <td><?php echo pos_admin_format_money((float) ($sale['total_amount'] ?? 0)); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars(ucfirst($sale['payment_status'] ?? 'paid')); ?></span></td>
                            <td><?php echo !empty($sale['synced_to_accounting']) ? '<span class="badge badge-success">Synced</span>' : '<span class="badge badge-warning">Pending</span>'; ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($sale['sale_timestamp']))); ?></td>
                            <td>
                                <a href="pos-admin.php?action=print_receipt&amp;sale_id=<?php echo (int) $sale['id']; ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="pos-admin-section <?php echo $activeTab === 'accounting' ? 'active' : ''; ?>" id="pos-tab-accounting">
    <div class="pos-grid">
        <div class="dashboard-card">
            <h2 style="margin-top:0;">Accounting Sync</h2>
            <p style="color: var(--secondary); font-size:0.9rem;">Queue and sync POS sales into ABBIS accounting journals.</p>
            <form method="post" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px;">
                <input type="hidden" name="action" value="sync_accounting">
                <input type="hidden" name="tab" value="accounting">
                <label style="display:flex; flex-direction:column; gap:4px; font-size:0.85rem;">
                    Process Limit
                    <input type="number" name="sync_limit" value="25" min="1" max="100" style="padding:10px 12px; border-radius:10px; border:1px solid var(--border); width:120px;">
                </label>
                <button type="submit" class="btn btn-primary">Sync Pending Sales</button>
            </form>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Store</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Last Error</th>
                            <th>Queued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accountingQueue)): ?>
                            <tr><td colspan="6" style="text-align:center; color: var(--secondary);">Queue is clear. All sales are synced.</td></tr>
                        <?php else: foreach ($accountingQueue as $queueRow): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($queueRow['sale_number']); ?></td>
                                <td><?php echo htmlspecialchars($queueRow['store_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($queueRow['status'])); ?></td>
                                <td><?php echo (int) $queueRow['attempts']; ?></td>
                                <td><?php echo htmlspecialchars($queueRow['last_error'] ? mb_strimwidth($queueRow['last_error'], 0, 80, '…') : '—'); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($queueRow['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>

