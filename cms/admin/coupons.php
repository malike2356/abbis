<?php
/**
 * CMS Admin - Coupons Management (WooCommerce-like)
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure coupons table exists
try {
    $pdo->query("SELECT * FROM cms_coupons LIMIT 1");
} catch (PDOException $e) {
    // Run migration
    $migrationSQL = file_get_contents($rootPath . '/database/cms_ecommerce_enhancement.sql');
    $statements = explode(';', $migrationSQL);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e2) {}
        }
    }
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = null;

// Handle coupon save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountAmount = floatval($_POST['discount_amount'] ?? 0);
    $minimumAmount = !empty($_POST['minimum_amount']) ? floatval($_POST['minimum_amount']) : null;
    $maximumAmount = !empty($_POST['maximum_amount']) ? floatval($_POST['maximum_amount']) : null;
    $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if ($code && $discountAmount > 0) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_coupons SET code=?, description=?, discount_type=?, discount_amount=?, minimum_amount=?, maximum_amount=?, usage_limit=?, expiry_date=?, is_active=? WHERE id=?");
            $stmt->execute([$code, $description, $discountType, $discountAmount, $minimumAmount, $maximumAmount, $usageLimit, $expiryDate, $isActive, $id]);
            $message = 'Coupon updated';
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_coupons (code, description, discount_type, discount_amount, minimum_amount, maximum_amount, usage_limit, expiry_date, is_active) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$code, $description, $discountType, $discountAmount, $minimumAmount, $maximumAmount, $usageLimit, $expiryDate, $isActive]);
            $message = 'Coupon created';
            $id = $pdo->lastInsertId();
        }
    } else {
        $message = 'Error: Code and discount amount are required';
    }
}

// Handle coupon delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $deleteId = $_POST['id'];
    $pdo->prepare("DELETE FROM cms_coupons WHERE id=?")->execute([$deleteId]);
    $message = 'Coupon deleted';
    header('Location: coupons.php');
    exit;
}

$coupon = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_coupons WHERE id=?");
    $stmt->execute([$id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
}

$coupons = $pdo->query("SELECT * FROM cms_coupons ORDER BY created_at DESC")->fetchAll();

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'coupons';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1><?php echo $action === 'edit' ? 'Edit Coupon' : ($action === 'add' ? 'Add New Coupon' : 'Coupons'); ?></h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <form method="post" class="post-form">
                <div class="form-group">
                    <label>Coupon Code <span style="color: red;">*</span></label>
                    <input type="text" name="code" value="<?php echo htmlspecialchars($coupon['code'] ?? ''); ?>" required class="regular-text" style="text-transform: uppercase;">
                    <p class="description">Customers will enter this code at checkout.</p>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="large-text"><?php echo htmlspecialchars($coupon['description'] ?? ''); ?></textarea>
                    <p class="description">Internal description of this coupon.</p>
                </div>
                
                <div class="form-group">
                    <label>Discount Type <span style="color: red;">*</span></label>
                    <select name="discount_type" required>
                        <option value="percentage" <?php echo ($coupon['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : ''; ?>>Percentage discount</option>
                        <option value="fixed" <?php echo ($coupon['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed cart discount</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Discount Amount <span style="color: red;">*</span></label>
                    <input type="number" name="discount_amount" value="<?php echo htmlspecialchars($coupon['discount_amount'] ?? '0'); ?>" step="0.01" min="0" required class="small-text">
                    <p class="description">Enter percentage (e.g., 10 for 10%) or fixed amount (e.g., 50.00).</p>
                </div>
                
                <div class="form-group">
                    <label>Minimum Spend</label>
                    <input type="number" name="minimum_amount" value="<?php echo htmlspecialchars($coupon['minimum_amount'] ?? ''); ?>" step="0.01" min="0" class="small-text">
                    <p class="description">Minimum order amount required to use this coupon.</p>
                </div>
                
                <div class="form-group">
                    <label>Maximum Spend</label>
                    <input type="number" name="maximum_amount" value="<?php echo htmlspecialchars($coupon['maximum_amount'] ?? ''); ?>" step="0.01" min="0" class="small-text">
                    <p class="description">Maximum order amount allowed when using this coupon.</p>
                </div>
                
                <div class="form-group">
                    <label>Usage Limit</label>
                    <input type="number" name="usage_limit" value="<?php echo htmlspecialchars($coupon['usage_limit'] ?? ''); ?>" min="1" class="small-text">
                    <p class="description">How many times this coupon can be used. Leave empty for unlimited.</p>
                </div>
                
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($coupon['expiry_date'] ?? ''); ?>" class="regular-text">
                    <p class="description">Coupon expiry date. Leave empty for no expiry.</p>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo ($coupon['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        Enable this coupon
                    </label>
                </div>
                
                <p class="submit">
                    <input type="submit" name="save_coupon" class="button button-primary" value="Save Coupon">
                    <a href="coupons.php" class="button">Cancel</a>
                </p>
            </form>
        <?php else: ?>
            <a href="?action=add" class="page-title-action">Add New</a>
            
            <?php if (empty($coupons)): ?>
                <p>No coupons found. <a href="?action=add">Create your first coupon</a>.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Usage</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $c): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c['code']); ?></strong></td>
                                <td><?php echo ucfirst($c['discount_type']); ?></td>
                                <td>
                                    <?php if ($c['discount_type'] === 'percentage'): ?>
                                        <?php echo number_format($c['discount_amount'], 0); ?>%
                                    <?php else: ?>
                                        GHS <?php echo number_format($c['discount_amount'], 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $c['used_count']; ?>
                                    <?php if ($c['usage_limit']): ?>
                                        / <?php echo $c['usage_limit']; ?>
                                    <?php else: ?>
                                        / ∞
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['expiry_date']): ?>
                                        <?php echo date('Y/m/d', strtotime($c['expiry_date'])); ?>
                                        <?php if (strtotime($c['expiry_date']) < time()): ?>
                                            <span style="color: #d63638;">(Expired)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Never
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['is_active']): ?>
                                        <span style="color: #00a32a; font-weight: 600;">Active</span>
                                    <?php else: ?>
                                        <span style="color: #646970;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $c['id']; ?>">Edit</a> |
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this coupon?');">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                        <input type="submit" name="delete_coupon" value="Delete" style="background: none; border: none; color: #d63638; cursor: pointer; text-decoration: underline; padding: 0;">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

