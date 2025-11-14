<?php
$page_title = 'POS Terminal';

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';
require_once $rootPath . '/includes/pos/PosRepository.php';

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

$baseUrl = app_base_path();

require_once __DIR__ . '/includes/header.php';

if (empty($stores)) {
    echo '<div class="pos-card"><div class="alert alert-warning">Please configure at least one POS store before using the terminal. Go to Admin → Stores to add a store.</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<style>
.pos-workspace {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 24px;
    margin-top: 24px;
}
.pos-catalog,
.pos-checkout {
    background: var(--pos-card-bg);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 24px;
    box-shadow: var(--pos-shadow);
}
.pos-checkout {
    position: sticky;
    top: 24px;
    align-self: start;
    max-height: calc(100vh - 48px);
    overflow-y: auto;
}
.pos-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.pos-search {
    flex: 1;
    min-width: 200px;
}
.pos-search input {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid var(--pos-border);
    font-size: 14px;
}
.pos-products {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}
.pos-product-card {
    border: 1px solid var(--pos-border);
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pos-product-card:hover {
    border-color: var(--pos-primary);
    box-shadow: var(--pos-shadow);
}
.cart-list {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 16px;
}
.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--pos-border);
}
.cart-summary {
    border-top: 1px solid var(--pos-border);
    padding-top: 12px;
    margin-top: 12px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}
.summary-row.total {
    font-weight: 700;
    font-size: 18px;
    border-top: 1px solid var(--pos-border);
    padding-top: 8px;
    margin-top: 8px;
}
@media (max-width: 1200px) {
    .pos-workspace {
        grid-template-columns: 1fr;
    }
    .pos-checkout {
        position: static;
        max-height: none;
    }
}
.customer-dropdown-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid var(--pos-border);
    transition: background 0.2s;
}
.customer-dropdown-item:hover {
    background: var(--pos-input-bg);
}
.customer-dropdown-item:last-child {
    border-bottom: none;
}
.customer-name {
    font-weight: 600;
    color: var(--pos-text);
}
.customer-meta {
    font-size: 12px;
    color: var(--pos-secondary);
    margin-top: 2px;
}
</style>

<div class="pos-card">
    <div class="pos-card-header">
        <h1 class="pos-card-title">POS Terminal</h1>
        <?php if ($canManageInventory): ?>
            <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin" class="btn btn-outline">Admin</a>
        <?php endif; ?>
    </div>
    
    <div id="posApp" class="pos-workspace"
         data-company-name="<?php echo e($companyName); ?>"
         data-api-catalog="<?php echo $baseUrl; ?>/pos/api/catalog.php"
         data-api-inventory="<?php echo $baseUrl; ?>/pos/api/inventory.php"
         data-api-sales="<?php echo $baseUrl; ?>/pos/api/sales.php"
         data-api-settings="<?php echo $baseUrl; ?>/pos/api/settings.php"
         data-api-customers="<?php echo $baseUrl; ?>/pos/api/customers.php"
         data-api-holds="<?php echo $baseUrl; ?>/pos/api/holds.php"
         data-api-refunds="<?php echo $baseUrl; ?>/pos/api/refunds.php"
         data-api-drawer="<?php echo $baseUrl; ?>/pos/api/drawer.php"
         data-api-promotions="<?php echo $baseUrl; ?>/pos/api/promotions.php"
         data-api-gift-cards="<?php echo $baseUrl; ?>/pos/api/gift-cards.php"
         data-api-loyalty="<?php echo $baseUrl; ?>/pos/api/loyalty.php"
         data-api-receipt="<?php echo $baseUrl; ?>/pos/api/receipt.php"
         data-receipt-url="<?php echo $baseUrl; ?>/pos/api/receipt.php"
         data-cashier-id="<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>"
         data-default-store="<?php echo $defaultStoreId; ?>"
         data-default-store-name="<?php echo e($defaultStoreName); ?>">
        
        <section class="pos-catalog">
            <div class="pos-toolbar">
                <div class="pos-search" style="position: relative;">
                    <input type="search" id="posSearch" placeholder="Search products or scan barcode...">
                    <div style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--pos-secondary);">📷</div>
                </div>
                <select id="posCategoryFilter" class="form-control">
                    <option value="">All categories</option>
                </select>
                <select id="posStoreSelect" class="form-control">
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo (int) $store['id']; ?>" <?php echo $store['id'] == $defaultStoreId ? 'selected' : ''; ?>>
                            <?php echo e($store['store_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="posProductGrid" class="pos-products"></div>
        </section>
        
        <section class="pos-checkout">
            <h2 style="margin: 0 0 8px 0; font-size: 18px;">Current Sale</h2>
            <div id="posCart" class="cart-list">
                <div style="text-align: center; color: #64748b; padding: 20px;">No items yet</div>
            </div>
            <div class="cart-summary">
                <div class="summary-row"><span>Items</span><strong id="cartItemCount">0</strong></div>
                <div class="summary-row"><span>Subtotal</span><strong id="cartSubtotal">GHS 0.00</strong></div>
                <div class="summary-row" id="discountRow" style="display: none;">
                    <span>Discount</span><strong id="cartDiscount" style="color: var(--pos-success);">-GHS 0.00</strong>
                </div>
                <div class="summary-row"><span>Tax</span><strong id="cartTax">GHS 0.00</strong></div>
                <div class="summary-row total"><span>Total</span><strong id="cartTotal">GHS 0.00</strong></div>
            </div>
            <div style="margin-top: 16px;">
                <!-- Customer Search -->
                <div style="position: relative; margin-bottom: 8px;">
                    <input type="text" id="posCustomerSearch" class="form-control" placeholder="Search customer..." autocomplete="off">
                    <div id="customerDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--pos-border); border-radius: 8px; max-height: 200px; overflow-y: auto; z-index: 1000; margin-top: 4px; box-shadow: var(--pos-shadow);"></div>
                </div>
                <input type="hidden" id="posCustomerId">
                <input type="text" id="posCustomerName" class="form-control" placeholder="Customer name (or walk-in)" style="margin-bottom: 8px;" autocomplete="off">
                <button type="button" id="clearCustomerBtn" class="btn btn-outline" style="width: 100%; margin-bottom: 8px; font-size: 12px; padding: 6px;" title="Clear customer selection">Clear Customer</button>
                
                <!-- Discount & Promotion Section -->
                <div style="margin-bottom: 8px;">
                    <div style="display: flex; gap: 8px; margin-bottom: 4px;">
                        <select id="posDiscountType" class="form-control" style="flex: 0 0 100px;">
                            <option value="">Discount</option>
                            <option value="percent">%</option>
                            <option value="fixed">GHS</option>
                            <option value="coupon">Coupon</option>
                        </select>
                        <input type="text" id="posDiscountValue" class="form-control" placeholder="Amount/Code" style="flex: 1;" disabled>
                        <button type="button" id="applyDiscountBtn" class="btn btn-outline" style="flex: 0 0 auto;" disabled>Apply</button>
                    </div>
                    <input type="text" id="posPromotionCode" class="form-control" placeholder="Enter promotion code..." style="font-size: 12px;">
                </div>
                
                <!-- Loyalty Points Display -->
                <div id="loyaltyPointsDisplay" style="display: none; background: var(--pos-input-bg); padding: 8px; border-radius: 8px; margin-bottom: 8px; font-size: 12px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Available Points:</span>
                        <strong id="loyaltyPointsBalance">0</strong>
                    </div>
                    <button type="button" id="redeemLoyaltyPointsBtn" class="btn btn-outline" style="width: 100%; margin-top: 4px; font-size: 11px;">Redeem Points</button>
                </div>
                
                <!-- Gift Card Input -->
                <div id="giftCardSection" style="display: none; margin-bottom: 8px;">
                    <input type="text" id="posGiftCardNumber" class="form-control" placeholder="Gift Card Number" style="margin-bottom: 4px; font-size: 12px;">
                    <button type="button" id="applyGiftCardBtn" class="btn btn-outline" style="width: 100%; font-size: 11px;">Apply Gift Card</button>
                </div>
                
                <!-- Split Payments Section -->
                <div id="splitPaymentsSection" style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-weight: 600; font-size: 14px;">Payment Methods</label>
                        <button type="button" id="toggleSplitPaymentsBtn" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">Split</button>
                    </div>
                    <div id="paymentsList" style="margin-bottom: 8px;">
                        <!-- Single payment (default) -->
                        <div class="payment-entry" data-payment-id="0">
                            <div style="display: flex; gap: 8px; margin-bottom: 4px;">
                    <select class="payment-method form-control" style="flex: 1;">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="gift_card">Gift Card</option>
                        <option value="store_credit">Store Credit</option>
                    </select>
                                <input type="number" class="payment-amount form-control" placeholder="Amount" step="0.01" style="flex: 1;">
                                <button type="button" class="remove-payment btn btn-outline" style="display: none; padding: 4px 8px;">×</button>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 600; padding-top: 8px; border-top: 1px solid var(--pos-border);">
                        <span>Total Paid:</span>
                        <span id="totalPaidAmount">GHS 0.00</span>
                    </div>
                </div>
                
                <!-- Cash Drawer Controls -->
                <div style="display: flex; gap: 8px; margin-bottom: 8px; padding: 8px; background: var(--pos-input-bg); border-radius: 8px;">
                    <button class="btn btn-outline" id="openDrawerBtn" style="flex: 1; font-size: 12px;" title="Open Cash Drawer (F5)">Open Drawer</button>
                    <button class="btn btn-outline" id="closeDrawerBtn" style="flex: 1; font-size: 12px;" title="Close Cash Drawer (F6)">Close Drawer</button>
                </div>
                
                <textarea id="posNotes" class="form-control" placeholder="Notes (optional)" rows="2" style="margin-bottom: 12px; resize: vertical;"></textarea>
                
                <!-- Paper Receipt Number (for matching with existing paper system) -->
                <input type="text" id="posPaperReceiptNumber" class="form-control" placeholder="Paper Receipt Number (optional)" style="margin-bottom: 12px; font-size: 12px;">
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <button class="btn btn-outline" id="holdSaleBtn" style="flex: 1;" title="Hold this sale for later (Ctrl+H)">Hold</button>
                    <button class="btn btn-outline" id="resumeSaleBtn" style="flex: 1;" title="Resume a held sale (Ctrl+R)">Resume</button>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-outline" id="clearSaleBtn" style="flex: 1;" title="Clear sale (Ctrl+C)">Clear</button>
                    <button class="btn btn-primary" id="completeSaleBtn" style="flex: 1;" title="Complete sale (F3)">Complete Sale</button>
                </div>
            </div>
        </section>
    </div>
</div>

<?php
$baseUrl = app_base_path();
$additional_js = $additional_js ?? [];
$additional_js[] = $baseUrl . '/pos/assets/js/pos-terminal.js?v=' . time();
require_once __DIR__ . '/includes/footer.php';
?>
