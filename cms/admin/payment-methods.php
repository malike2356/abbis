<?php
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure payment methods table exists
try {
    $pdo->query("SELECT 1 FROM cms_payment_methods LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        provider VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 0,
        config JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert default payment methods
    $defaults = [
        ['Mobile Money', 'mobile_money', '{"phone":"+233 XX XXX XXXX"}'],
        ['Bank Transfer', 'bank_transfer', '{"account":"XXXXXXXX","bank":"[Bank Name]"}'],
        ['Cash on Delivery', 'cod', '{}'],
        ['Paystack', 'paystack', '{"public_key":"","secret_key":""}'],
        ['Flutterwave', 'flutterwave', '{"public_key":"","secret_key":""}']
    ];
    foreach ($defaults as $pm) {
        $pdo->prepare("INSERT INTO cms_payment_methods (name, provider, config, is_active) VALUES (?,?,?,1)")
            ->execute([$pm[0], $pm[1], $pm[2]]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_method'])) {
        $name = trim($_POST['name'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $config = json_encode($_POST['config'] ?? []);
        
        $pdo->prepare("INSERT INTO cms_payment_methods (name, provider, config, is_active) VALUES (?,?,?,?)")
            ->execute([$name, $provider, $config, $isActive]);
        $message = 'Payment method added';
    }
    if (isset($_POST['toggle_method'])) {
        $id = $_POST['method_id'];
        $isActive = $_POST['is_active'];
        $pdo->prepare("UPDATE cms_payment_methods SET is_active=? WHERE id=?")->execute([$isActive, $id]);
    }
}

$methods = $pdo->query("SELECT * FROM cms_payment_methods ORDER BY is_active DESC, name")->fetchAll();

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
    <title>Payment Methods - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'payment-methods';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Payment Methods</h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Name</th><th>Provider</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($methods as $m): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($m['provider']); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="method_id" value="<?php echo $m['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $m['is_active'] ? 0 : 1; ?>">
                                <input type="submit" name="toggle_method" value="<?php echo $m['is_active'] ? 'Deactivate' : 'Activate'; ?>" class="button" style="padding:4px 8px; font-size:12px;">
                            </form>
                            <?php if ($m['is_active']): ?>
                                <span class="status-published" style="margin-left:10px;">Active</span>
                            <?php else: ?>
                                <span class="status-draft" style="margin-left:10px;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="?edit=<?php echo $m['id']; ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

