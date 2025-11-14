<?php
$page_title = 'Point of Sale';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

$pdo = getDBConnection();
$repo = new PosRepository($pdo);
$stores = $repo->getStores();
$defaultStoreId = $stores[0]['id'] ?? null;
$defaultStoreName = $stores[0]['store_name'] ?? 'Store';
$canManageInventory = $auth->userHasPermission('pos.inventory.manage');
$companyStmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_name'");
$companyStmt->execute();
$companyName = $companyStmt->fetchColumn() ?: 'ABBIS';

require_once '../includes/header.php';

if (empty($stores)) {
    echo '<div class="alert alert-warning">Please configure at least one POS store before using the terminal. Go to System → POS & Store to add a store.</div>';
    require_once '../includes/footer.php';
    exit;
}
?>

<style>
.pos-page {
    display: flex;
    flex-direction: column;
    gap: 32px;
    margin-top: 12px;
}
.pos-hero {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 24px;
    padding: 24px 28px;
    background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(59,130,246,0.08));
    border: 1px solid rgba(148,163,184,0.25);
    border-radius: 26px;
    box-shadow: 0 24px 40px rgba(15,23,42,0.08);
}
.pos-hero h1 {
    margin: 0 0 8px 0;
    font-size: 1.9rem;
    letter-spacing: -0.02em;
}
.pos-hero p {
    margin: 0;
    color: var(--secondary);
    font-size: 0.95rem;
    max-width: 520px;
    line-height: 1.55;
}
.pos-hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.pos-hero-actions .btn {
    min-width: 180px;
}

.pos-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}
.summary-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 20px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.summary-card span.kpi-label {
    font-size: 0.8rem;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.summary-card strong {
    font-size: 1.7rem;
    font-weight: 700;
    color: var(--text);
}
.summary-card small {
    font-size: 0.8rem;
    color: var(--secondary);
}

.pos-analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    align-items: stretch;
}
.pos-analytics-grid .cashier-widget {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.pos-analytics-grid .cashier-widget .pos-summary-grid {
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}
.pos-analytics-grid .cashier-widget #cashierRecentSales {
    list-style: none;
    margin: 12px 0 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.pos-analytics-grid .cashier-widget #cashierRecentSales li {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--secondary);
}
.pos-analytics-grid .cashier-widget #cashierRecentSales li.empty {
    display: block;
    text-align: left;
}
.pos-analytics-grid .cashier-widget #cashierRecentSales li span:last-child {
    text-align: right;
    font-weight: 600;
    color: var(--text);
}
@media (max-width: 768px) {
    .pos-analytics-grid {
        grid-template-columns: 1fr;
    }
    .pos-analytics-grid .cashier-widget #cashierRecentSales li {
        grid-template-columns: 1fr;
        text-align: left;
    }
    .pos-analytics-grid .cashier-widget #cashierRecentSales li span:last-child {
        text-align: left;
    }
}
.analytics-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 20px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.analytics-card h3 {
    margin: 0;
    font-size: 1.05rem;
    color: var(--text);
}
.analytics-card table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.analytics-card thead th {
    text-align: left;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
    color: var(--secondary);
    font-weight: 600;
}
.analytics-card tbody td {
    padding: 8px 0;
    border-bottom: 1px solid rgba(148,163,184,0.2);
}
.analytics-card tbody tr:last-child td {
    border-bottom: none;
}
.analytics-card tbody tr.highlight {
    background: rgba(14,165,233,0.08);
}
.cashier-widget { display: none; }
.pos-dashboard.has-cashier-insights .cashier-widget { display: flex; flex-direction: column; }
.cashier-widget ul {
    list-style: none;
    padding: 0;
    margin: 0;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--bg);
}
.cashier-widget ul li {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 12px;
    padding: 10px 14px;
    border-bottom: 1px solid rgba(148,163,184,0.2);
    font-size: 0.85rem;
}
.cashier-widget ul li:last-child {
    border-bottom: none;
}
.cashier-widget ul li span:last-child {
    font-weight: 600;
    color: var(--text);
}

.pos-workspace {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
}
.pos-catalog,
.pos-checkout {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 24px;
    box-shadow: var(--shadow);
}
.pos-checkout {
    position: sticky;
    top: calc(90px + 16px);
    align-self: start;
    min-width: 320px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.pos-checkout .panel-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pos-checkout h2 {
    margin: 0;
    font-size: 1.25rem;
}
.pos-checkout p {
    margin: 0;
    color: var(--secondary);
    font-size: 0.88rem;
}

.pos-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    margin-bottom: 20px;
}
.pos-search {
    flex: 1 1 240px;
    position: relative;
}
.pos-search input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border-radius: 18px;
    border: 1px solid var(--border);
    background: var(--input);
    font-size: 0.95rem;
}
.pos-search::before {
    content: '🔍';
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    opacity: 0.6;
}
.pos-toolbar select,
.pos-toolbar .btn-icon {
    min-width: 150px;
}

.pos-products {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 14px;
}
.pos-product-card {
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 16px;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pos-product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 34px rgba(15,23,42,0.15);
    border-color: rgba(14,165,233,0.35);
}
.pos-product-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text);
}
.pos-product-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.82rem;
    color: var(--secondary);
}
.pos-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.pos-chip {
    padding: 4px 10px;
    background: rgba(14,165,233,0.12);
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #0ea5e9;
}

.cart-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 360px;
    overflow-y: auto;
    padding-right: 4px;
}
.cart-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 70px 100px 32px;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 12px;
    background: var(--bg);
}
.cart-item input {
    width: 70px;
    padding: 7px;
    border-radius: 10px;
    border: 1px solid var(--border);
    text-align: center;
}

.cart-summary {
    border-top: 1px dashed var(--border);
    padding-top: 12px;
    display: grid;
    gap: 6px;
    font-size: 0.95rem;
}
.cart-summary .summary-row {
    display: flex;
    justify-content: space-between;
    color: var(--secondary);
}
.cart-summary .summary-row.total {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
}

.payment-section {
    display: grid;
    gap: 12px;
}
.pos-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.pos-meta-row input,
.pos-meta-row select {
    flex: 1 1 160px;
    min-width: 140px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: var(--input);
}

.pos-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.pos-actions .btn {
    flex: 1 1 160px;
}

.pos-empty {
    border: 1px dashed var(--border);
    border-radius: 16px;
    padding: 22px;
    text-align: center;
    color: var(--secondary);
    font-size: 0.95rem;
}

.receipt-preview {
    border-radius: 14px;
    border: 1px solid var(--border);
    padding: 18px;
    background: var(--bg);
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    max-height: 240px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.badge-warning {
    background: rgba(251, 191, 36, 0.15);
    color: #92400e;
}
.badge-danger {
    background: rgba(248, 113, 113, 0.15);
    color: #991b1b;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
    z-index: 2050;
}
.modal-backdrop.active {
    display: flex;
}
.modal-panel {
    background: var(--card);
    border-radius: 20px;
    width: min(540px, 95vw);
    padding: 24px 26px;
    box-shadow: var(--shadow-lg);
}
.modal-backdrop.active .modal-panel {
    animation: modalFadeIn 0.18s ease;
}
body.modal-open {
    overflow: hidden;
}
@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.modal-body.modal-form-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.modal-form-columns {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}
.modal-body .form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.modal-body .form-field label {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--secondary);
}
.modal-body .form-field .form-control,
.modal-body .form-field select,
.modal-body .form-field input,
.modal-body .form-field textarea {
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 10px 12px;
    background: var(--input);
    font-size: 0.95rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.modal-body .form-field textarea {
    min-height: 96px;
    resize: vertical;
}
.modal-body .form-field .form-control:focus,
.modal-body .form-field select:focus,
.modal-body .form-field input:focus,
.modal-body .form-field textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18);
}
.modal-description {
    color: var(--secondary);
    font-size: 0.85rem;
    line-height: 1.55;
}
.modal-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 4px;
}
.modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 18px;
}
.modal-grid .modal-panel {
    width: 100%;
}
.field-hint {
    font-size: 0.78rem;
    color: var(--secondary);
    line-height: 1.4;
}

@media (max-width: 1080px) {
    .pos-workspace {
        grid-template-columns: 1fr;
    }
    .pos-checkout {
        position: static;
    }
}
@media (max-width: 768px) {
    .pos-hero {
        flex-direction: column;
    }
    .pos-hero-actions {
        width: 100%;
    }
    .pos-hero-actions .btn {
        flex: 1 1 auto;
        min-width: 0;
    }
    .pos-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="pos-page">
    <div class="pos-hero">
        <div>
            <h1>Point of Sale</h1>
            <p>Process walk-in sales, adjust inventory, and sync revenue to accounting from a single, streamlined workspace.</p>
        </div>
        <?php if ($auth->userHasPermission('pos.inventory.manage') || $auth->userHasPermission('system.admin')): ?>
        <div class="pos-hero-actions">
            <?php if ($auth->userHasPermission('pos.inventory.manage')): ?>
                <a href="pos-admin.php" class="btn btn-outline">POS Management</a>
            <?php endif; ?>
            <?php if ($auth->userHasPermission('system.admin')): ?>
                <button class="btn btn-outline" id="openHardwareSettings">Printer &amp; Hardware Settings</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <section class="pos-dashboard" id="posDashboard"
         data-endpoint="<?php echo $is_module ? '../pos/api/reports.php?action=dashboard' : 'pos/api/reports.php?action=dashboard'; ?>"
         data-current-user="<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>">
        <div class="pos-summary-grid">
            <article class="summary-card">
                <span class="kpi-label">Today&apos;s Sales</span>
                <strong data-metric="today-sales">GHS 0.00</strong>
                <small>Transactions: <span data-metric="today-transactions">0</span></small>
            </article>
            <article class="summary-card">
                <span class="kpi-label">Average Ticket</span>
                <strong data-metric="today-average">GHS 0.00</strong>
                <small>Average per transaction</small>
            </article>
            <article class="summary-card">
                <span class="kpi-label">Week-to-date</span>
                <strong data-metric="week-sales">GHS 0.00</strong>
                <small>Total completed sales</small>
            </article>
            <article class="summary-card">
                <span class="kpi-label">Month-to-date</span>
                <strong data-metric="month-sales">GHS 0.00</strong>
                <small>Since first day of month</small>
            </article>
        </div>

        <div class="pos-analytics-grid">
            <article class="analytics-card">
                <h3>Top Products (7 days)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th style="text-align:right;">Qty</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="posTopProducts">
                        <tr><td colspan="4" style="text-align:center;">Loading…</td></tr>
                    </tbody>
                </table>
            </article>

            <article class="analytics-card">
                <h3>Top Cashiers (7 days)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cashier</th>
                            <th style="text-align:right;">Sales</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="posTopCashiers">
                        <tr><td colspan="4" style="text-align:center;">Loading…</td></tr>
                    </tbody>
                </table>
            </article>

            <article class="analytics-card">
                <h3>Low Inventory Alerts</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Store</th>
                            <th style="text-align:right;">Qty</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="posInventoryAlerts">
                        <tr><td colspan="4" style="text-align:center;">Loading…</td></tr>
                    </tbody>
                </table>
            </article>

            <article class="analytics-card">
                <h3>Recent Sales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Sale</th>
                            <th>Store</th>
                            <th>Cashier</th>
                            <th>Time</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="posRecentSales">
                        <tr><td colspan="5" style="text-align:center;">Loading…</td></tr>
                    </tbody>
                </table>
            </article>

            <article class="analytics-card cashier-widget">
                <h3>Your Performance Today</h3>
                <div class="pos-summary-grid">
                    <article class="summary-card" style="box-shadow:none; border-radius:16px;">
                        <span class="kpi-label">Today</span>
                        <strong data-cashier="today-sales">GHS 0.00</strong>
                        <small>Transactions: <span data-cashier="today-transactions">0</span></small>
                    </article>
                    <article class="summary-card" style="box-shadow:none; border-radius:16px;">
                        <span class="kpi-label">This Month</span>
                        <strong data-cashier="month-sales">GHS 0.00</strong>
                        <small>Transactions: <span data-cashier="month-transactions">0</span></small>
                    </article>
                </div>
                <div>
                    <h4 style="margin:0; font-size:0.9rem; color:var(--secondary);">Recent Tickets</h4>
                    <ul id="cashierRecentSales">
                        <li>No recent sales recorded.</li>
                    </ul>
                </div>
            </article>
        </div>
    </section>

    <div id="posApp"
         class="pos-workspace"
         data-company-name="<?php echo e($companyName); ?>"
         data-api-catalog="<?php echo $is_module ? '../pos/api/catalog.php' : 'pos/api/catalog.php'; ?>"
         data-api-inventory="<?php echo $is_module ? '../pos/api/inventory.php' : 'pos/api/inventory.php'; ?>"
         data-api-sales="<?php echo $is_module ? '../pos/api/sales.php' : 'pos/api/sales.php'; ?>"
         data-api-settings="<?php echo $is_module ? '../pos/api/settings.php' : 'pos/api/settings.php'; ?>"
         data-cashier-id="<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>"
         data-default-store="<?php echo $defaultStoreId; ?>"
         data-default-store-name="<?php echo e($defaultStoreName); ?>">
        <section class="pos-catalog">
            <div class="pos-toolbar">
                <div class="pos-search">
                    <input type="search" id="posSearch" placeholder="Search items by name or SKU...">
                </div>
                <select id="posCategoryFilter">
                    <option value="">All categories</option>
                </select>
                <select id="posStoreSelect">
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo (int) $store['id']; ?>" <?php echo $store['id'] == $defaultStoreId ? 'selected' : ''; ?>>
                            <?php echo e($store['store_name']); ?> (<?php echo e($store['store_code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-icon" id="refreshCatalogBtn">🔄 Refresh</button>
                <button class="btn btn-outline btn-icon" id="openInventoryModalBtn">🛠 Inventory</button>
                <?php if ($canManageInventory): ?>
                    <a class="btn btn-outline btn-icon" href="<?php echo $is_module ? 'pos-admin.php?tab=catalog' : 'modules/pos-admin.php?tab=catalog'; ?>">📦 Catalog</a>
                <?php endif; ?>
            </div>
            <div id="posProductGrid" class="pos-products" aria-live="polite"></div>
        </section>

        <section class="pos-checkout" aria-live="polite">
            <div class="panel-header">
                <h2>Current Sale</h2>
                <p>Scan or tap products to build the cart.</p>
            </div>
            <div id="posCart" class="cart-list">
                <div class="pos-empty">No items yet. Add products from the list.</div>
            </div>
            <div class="cart-summary">
                <div class="summary-row"><span>Items</span><strong id="cartItemCount">0</strong></div>
                <div class="summary-row"><span>Subtotal</span><strong id="cartSubtotal">GHS 0.00</strong></div>
                <div class="summary-row"><span>Discounts</span><strong id="cartDiscount">GHS 0.00</strong></div>
                <div class="summary-row"><span>Tax</span><strong id="cartTax">GHS 0.00</strong></div>
                <div class="summary-row total"><span>Total</span><strong id="cartTotal">GHS 0.00</strong></div>
            </div>
            <div class="payment-section">
                <div class="pos-meta-row">
                    <input type="text" id="posCustomerName" placeholder="Customer name (optional)">
                    <select id="posPaymentMethod">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="voucher">Voucher</option>
                    </select>
                    <input type="number" id="posAmountReceived" placeholder="Amount received" step="0.01" min="0">
                </div>
                <textarea id="posNotes" rows="2" style="border-radius: 12px; border:1px solid var(--border); background: var(--input);" placeholder="Order notes (optional)"></textarea>
            </div>
            <div class="pos-actions">
                <button class="btn btn-outline" id="clearSaleBtn">Clear</button>
                <button class="btn btn-primary" id="completeSaleBtn">Complete Sale</button>
            </div>
            <div>
                <h3 style="margin: 10px 0 6px 0; font-size: 0.9rem; color: var(--secondary);">Receipt preview</h3>
                <div id="receiptPreview" class="receipt-preview">Add items to generate a receipt preview.</div>
            </div>
        </section>
    </div>
</div>

<!-- Inventory Adjustment Modal -->
<div class="modal-backdrop" id="inventoryModal" aria-hidden="true">
    <div class="modal-grid">
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="inventoryModalTitle">
        <div class="modal-header">
            <h2 id="inventoryModalTitle">Adjust Inventory</h2>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-sm" data-close-modal="inventoryModal">Close</button>
            </div>
        </div>
        <div class="modal-body modal-form-grid">
            <p class="modal-description">
                Record restocks, transfers, or corrections. Use positive quantities to add stock and negative values to deduct.
            </p>

            <div class="form-field">
                <label for="adjustProductSelect">Product</label>
                <select id="adjustProductSelect" class="form-control"></select>
            </div>

            <div class="modal-form-columns">
                <div class="form-field">
                    <label for="adjustStoreSelect">Store</label>
                    <select id="adjustStoreSelect" class="form-control">
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo (int) $store['id']; ?>"><?php echo e($store['store_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="adjustType">Adjustment type</label>
                    <select id="adjustType" class="form-control">
                        <option value="purchase">Purchase / Restock</option>
                        <option value="adjustment">Manual Adjustment</option>
                        <option value="transfer_in">Transfer In</option>
                        <option value="transfer_out">Transfer Out</option>
                        <option value="return_in">Return In</option>
                        <option value="return_out">Return Out</option>
                    </select>
                </div>
            </div>

            <div class="modal-form-columns">
                <div class="form-field">
                    <label for="adjustQuantity">Quantity (+/-)</label>
                    <input type="number" id="adjustQuantity" class="form-control" placeholder="Quantity (+/-)" step="0.01">
                </div>
                <div class="form-field">
                    <label for="adjustCost">Unit cost (optional)</label>
                    <input type="number" id="adjustCost" class="form-control" placeholder="Unit cost (optional)" step="0.01" min="0">
                </div>
            </div>

            <div class="form-field">
                <label for="adjustRemarks">Remarks</label>
                <textarea id="adjustRemarks" class="form-control" placeholder="Notes for audit trail"></textarea>
            </div>

            <div class="modal-form-actions">
                <button class="btn btn-primary" id="adjustSubmitBtn">Record Adjustment</button>
            </div>
        </div>
    </div>
    </div>
</div>

<?php if ($auth->userHasPermission('system.admin')): ?>
<!-- Hardware Settings Modal -->
<div class="modal-backdrop" id="hardwareSettingsModal" aria-hidden="true">
    <div class="modal-grid">
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="hardwareSettingsTitle">
        <div class="modal-header">
            <h2 id="hardwareSettingsTitle">Printer &amp; Hardware Settings</h2>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-sm" data-close-modal="hardwareSettingsModal">Close</button>
            </div>
        </div>
        <div class="modal-body modal-form-grid">
            <p class="modal-description">
                Configure receipt printing and barcode defaults for this terminal. These settings apply to all POS users on this device.
            </p>

            <div class="modal-form-columns">
                <div class="form-field">
                    <label for="posPrinterMode">Printer mode</label>
                    <select id="posPrinterMode" class="form-control">
                        <option value="browser">Browser Print (Default)</option>
                        <option value="escpos">ESC/POS Thermal Printer</option>
                        <option value="network">Network / Cloud Endpoint</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="posPrinterWidth">Receipt width (mm)</label>
                    <input type="number" id="posPrinterWidth" class="form-control" min="48" max="120" step="1" placeholder="80">
                </div>
            </div>

            <div class="form-field">
                <label for="posPrinterEndpoint">Printer endpoint / device path</label>
                <input type="text" id="posPrinterEndpoint" class="form-control" placeholder="e.g. usb://printer or https://printer-endpoint">
                <span class="field-hint">Required for ESC/POS or Network modes. Leave blank for browser printing.</span>
            </div>

            <div class="modal-form-columns">
                <div class="form-field">
                    <label for="posBarcodePrefix">Barcode prefix</label>
                    <input type="text" id="posBarcodePrefix" class="form-control" placeholder="e.g. ABBIS-">
                </div>
                <div class="form-field">
                    <label for="posReceiptFooter">Receipt footer notes</label>
                    <textarea id="posReceiptFooter" rows="3" class="form-control" placeholder="Thank you for shopping with us!"></textarea>
                </div>
            </div>

            <div class="modal-form-actions">
                <button class="btn btn-outline" id="testHardwarePrintBtn">Test Print</button>
                <button class="btn btn-primary" id="saveHardwareSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>
    </div>
</div>
<?php endif; ?>

<?php
$additional_js = $additional_js ?? [];
$additional_js[] = $assetBase . 'assets/js/pos-dashboard.js?v=' . time();
$additional_js[] = $assetBase . 'assets/js/pos-terminal.js?v=' . time();
require_once '../includes/footer.php';
?>

