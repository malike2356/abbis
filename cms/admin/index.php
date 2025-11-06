<?php
/**
 * CMS Admin Dashboard (WordPress-style)
 * Separate from ABBIS system
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/constants.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';
require_once $rootPath . '/includes/sso.php';

$cmsAuth = new CMSAuth();

// Check if logged in
if (!$cmsAuth->isLoggedIn()) {
    // Check if user is logged into ABBIS as admin - auto-create CMS session
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN) {
        $pdo = getDBConnection();
        $abbisUsername = $_SESSION['username'] ?? '';
        
        // Try to find corresponding CMS user
        $stmt = $pdo->prepare("SELECT * FROM cms_users WHERE username = ? AND status = 'active'");
        $stmt->execute([$abbisUsername]);
        $cmsUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cmsUser) {
            // Auto-create CMS session
            $_SESSION['cms_user_id'] = $cmsUser['id'];
            $_SESSION['cms_username'] = $cmsUser['username'];
            $_SESSION['cms_role'] = $cmsUser['role'];
            
            // Update last login
            try {
                $pdo->prepare("UPDATE cms_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?")->execute([$cmsUser['id']]);
            } catch (Exception $e) {}
        } else {
            // No CMS account - redirect to login which will handle account creation
            header('Location: login.php');
            exit;
        }
    } else {
        // Not logged into ABBIS either - redirect to login
        header('Location: login.php');
        exit;
    }
}

// Get base URL
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}

$sso = new SSO();
$isAdmin = $cmsAuth->isAdmin();
$abbisLink = null;
$abbisLoginRequired = false;

// Generate SSO link for admins
if ($isAdmin) {
    $user = $cmsAuth->getCurrentUser();
    $abbisLink = $sso->getABBISLoginURL($user['id'], $user['username'], $user['role']);
    
    // If already logged into ABBIS, use direct link
    if ($sso->isLoggedIntoABBIS()) {
        $abbisLink = $baseUrl . '/modules/dashboard.php';
    } elseif (!$abbisLink) {
        // Admin but no SSO link - might need separate login
        $abbisLoginRequired = true;
    }
}

$pdo = getDBConnection();
$user = $cmsAuth->getCurrentUser();

// Get CMS settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
$cmsSettings = [];
while ($row = $settingsStmt->fetch()) {
    $cmsSettings[$row['setting_key']] = $row['setting_value'];
}

// Get company name
$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$siteTitle = $cmsSettings['site_title'] ?? $companyName;

// Get base URL
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}

// Get stats
$pagesCount = $pdo->query("SELECT COUNT(*) FROM cms_pages")->fetchColumn();
$postsCount = $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='published'")->fetchColumn();
$quotesCount = $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='new'")->fetchColumn();
$ordersCount = $pdo->query("SELECT COUNT(*) FROM cms_orders WHERE status='pending'")->fetchColumn();
$productsCount = $pdo->query("SELECT COUNT(*) FROM catalog_items")->fetchColumn();
$categoriesCount = $pdo->query("SELECT COUNT(*) FROM cms_categories")->fetchColumn();
$commentsCount = $pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status='pending'")->fetchColumn();

// Get recent orders
$recentOrders = $pdo->query("SELECT * FROM cms_orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent posts
$recentPosts = $pdo->query("SELECT * FROM cms_posts ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get sales stats (last 30 days)
$salesStats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value
    FROM cms_orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?> - CMS Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f0f1; }
        .admin-header { background: #23282d; color: white; padding: 0 20px; height: 32px; display: flex; align-items: center; justify-content: space-between; }
        .admin-header a { color: white; text-decoration: none; font-size: 13px; }
        .admin-nav { background: #2271b1; color: white; padding: 0; }
        .admin-nav ul { list-style: none; display: flex; margin: 0; }
        .admin-nav li { margin: 0; }
        .admin-nav a { display: block; padding: 10px 15px; color: white; text-decoration: none; }
        .admin-nav a:hover { background: #135e96; }
        .wrap { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .admin-body { background: white; padding: 0; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
        .welcome-panel { background: linear-gradient(to bottom, #f7f7f7 0%, #fff 100%); border: 1px solid #c3c4c7; padding: 23px 20px 24px 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
        .welcome-panel h2 { margin: 0 0 12px 0; font-size: 18px; font-weight: 400; }
        .welcome-panel p { margin: 0; color: #646970; font-size: 13px; line-height: 1.6; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0; margin: 20px 0; border-top: 1px solid #c3c4c7; }
        .stat-box { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 15px 20px; border-right: none; position: relative; }
        .stat-box:last-child { border-right: 1px solid #c3c4c7; }
        .stat-box h3 { font-size: 28px; margin: 0 0 8px 0; color: #2271b1; font-weight: 400; line-height: 1.2; }
        .stat-box p { margin: 0 0 5px 0; color: #646970; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-box.clickable { cursor: pointer; transition: background 0.2s; }
        .stat-box.clickable:hover { background: #f6f7f7; }
        .stat-link { font-size: 12px; color: #2271b1; text-decoration: none; }
        .stat-link:hover { color: #135e96; }
        .status-pending { color: #d63638; font-weight: 600; }
        .status-processing { color: #dba617; font-weight: 600; }
        .status-completed { color: #00a32a; font-weight: 600; }
        .status-cancelled { color: #646970; font-weight: 600; }
    </style>
    <?php 
    $currentPage = 'index';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    <div class="wrap">
        <h1>Dashboard</h1>
        
        <div class="welcome-panel">
            <h2>Welcome to <?php echo htmlspecialchars($siteTitle); ?> CMS Admin</h2>
            <p>Thank you for using our content management system. Use the navigation menu to manage your website content, pages, posts, products, and settings.</p>
            <div style="margin-top: 15px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <a href="<?php echo $baseUrl; ?>/cms/" target="_blank" style="background: #00a32a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-flex; align-items: center; gap: 8px; font-weight: 500; transition: background 0.2s;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    View CMS Website
                </a>
                <?php if ($isAdmin && $abbisLink): ?>
                    <a href="<?php echo htmlspecialchars($abbisLink); ?>" style="background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-flex; align-items: center; gap: 8px; font-weight: 500; transition: background 0.2s;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                        </svg>
                        Access ABBIS
                    </a>
                <?php elseif ($isAdmin && $abbisLoginRequired): ?>
                    <span style="color: #d63638; font-size: 13px;">
                        <strong>Note:</strong> To access the ABBIS system, please log in with your ABBIS credentials.
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-box clickable" onclick="window.location='pages.php'">
                <h3><?php echo $pagesCount; ?></h3>
                <p>Total Pages</p>
                <a href="pages.php" class="stat-link">Manage Pages →</a>
            </div>
            <div class="stat-box clickable" onclick="window.location='posts.php'">
                <h3><?php echo $postsCount; ?></h3>
                <p>Published Posts</p>
                <a href="posts.php" class="stat-link">Manage Posts →</a>
            </div>
            <div class="stat-box clickable" onclick="window.location='products.php'">
                <h3><?php echo $productsCount; ?></h3>
                <p>Products</p>
                <a href="products.php" class="stat-link">Manage Products →</a>
            </div>
            <div class="stat-box clickable" onclick="window.location='orders.php'">
                <h3><?php echo $ordersCount; ?></h3>
                <p>Pending Orders</p>
                <a href="orders.php" class="stat-link">View Orders →</a>
            </div>
            <div class="stat-box clickable" onclick="window.location='quotes.php'">
                <h3><?php echo $quotesCount; ?></h3>
                <p>New Quote Requests</p>
                <a href="quotes.php" class="stat-link">View Quotes →</a>
            </div>
            <div class="stat-box clickable" onclick="window.location='comments.php'">
                <h3><?php echo $commentsCount; ?></h3>
                <p>Pending Comments</p>
                <a href="comments.php" class="stat-link">Moderate →</a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
            <!-- Sales Overview -->
            <div style="background: white; border: 1px solid #c3c4c7; padding: 20px;">
                <h2 style="margin-top: 0; font-size: 18px; font-weight: 400;">Sales Overview (Last 30 Days)</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <p style="color: #646970; font-size: 12px; text-transform: uppercase; margin: 0;">Total Orders</p>
                        <h3 style="font-size: 28px; margin: 5px 0; color: #2271b1; font-weight: 400;"><?php echo $salesStats['total_orders'] ?? 0; ?></h3>
                    </div>
                    <div>
                        <p style="color: #646970; font-size: 12px; text-transform: uppercase; margin: 0;">Total Revenue</p>
                        <h3 style="font-size: 28px; margin: 5px 0; color: #00a32a; font-weight: 400;">GHS <?php echo number_format($salesStats['total_revenue'] ?? 0, 2); ?></h3>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <p style="color: #646970; font-size: 12px; text-transform: uppercase; margin: 0;">Average Order Value</p>
                    <h3 style="font-size: 24px; margin: 5px 0; color: #1e293b; font-weight: 400;">GHS <?php echo number_format($salesStats['avg_order_value'] ?? 0, 2); ?></h3>
                </div>
                <a href="orders.php" class="button" style="margin-top: 15px; display: inline-block;">View All Orders →</a>
            </div>

            <!-- Quick Actions -->
            <div style="background: white; border: 1px solid #c3c4c7; padding: 20px;">
                <h2 style="margin-top: 0; font-size: 18px; font-weight: 400;">Quick Actions</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                    <a href="pages.php?action=add" class="button" style="text-align: center; padding: 10px;">Add New Page</a>
                    <a href="posts.php?action=add" class="button" style="text-align: center; padding: 10px;">Add New Post</a>
                    <a href="products.php?action=add" class="button" style="text-align: center; padding: 10px;">Add New Product</a>
                    <a href="media.php" class="button" style="text-align: center; padding: 10px;">Media Library</a>
                    <a href="menus.php" class="button" style="text-align: center; padding: 10px;">Manage Menus</a>
                    <a href="settings.php" class="button" style="text-align: center; padding: 10px;">Settings</a>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <!-- Recent Orders -->
            <div style="background: white; border: 1px solid #c3c4c7; padding: 20px;">
                <h2 style="margin-top: 0; font-size: 18px; font-weight: 400;">Recent Orders</h2>
                <?php if (empty($recentOrders)): ?>
                    <p style="color: #646970;">No orders yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><a href="orders.php?id=<?php echo $order['id']; ?>"><?php echo htmlspecialchars($order['order_number']); ?></a></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>GHS <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="orders.php" class="button" style="margin-top: 15px; display: inline-block;">View All Orders →</a>
                <?php endif; ?>
            </div>

            <!-- Recent Posts -->
            <div style="background: white; border: 1px solid #c3c4c7; padding: 20px;">
                <h2 style="margin-top: 0; font-size: 18px; font-weight: 400;">Recent Posts</h2>
                <?php if (empty($recentPosts)): ?>
                    <p style="color: #646970;">No posts yet.</p>
                <?php else: ?>
                    <ul style="list-style: none; margin: 15px 0 0 0; padding: 0;">
                        <?php foreach ($recentPosts as $post): ?>
                            <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;">
                                <strong><a href="posts.php?action=edit&id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></strong>
                                <br>
                                <small style="color: #646970;">
                                    <?php echo ucfirst($post['status']); ?> • <?php echo date('Y/m/d', strtotime($post['created_at'])); ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="posts.php" class="button" style="margin-top: 15px; display: inline-block;">View All Posts →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

