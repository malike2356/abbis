<?php
// This is included by admin pages, so variables should already be set
// Only output navigation if not already output
if (!defined('HEADER_OUTPUT')) {
    define('HEADER_OUTPUT', true);
    
    $user = $cmsAuth->getCurrentUser();
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
    $cmsSettings = [];
    while ($row = $settingsStmt->fetch()) {
        $cmsSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get site name from CMS settings (backend) - use consistent helper
    // This ensures consistency with public pages
    require_once dirname(__DIR__) . '/public/get-site-name.php';
    if (!isset($companyName) || empty($companyName)) {
        $companyName = getCMSSiteName('CMS Admin');
    }
    $baseUrl = '/abbis3.2';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '/abbis3.2';
    }
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; color: #3c434a; font-size: 13px; line-height: 1.4; margin-left: 160px; margin-top: 32px; }
        .admin-header { background: #23282d; color: #a7aaad; padding: 0 20px 0 180px; height: 32px; display: flex; align-items: center; justify-content: space-between; font-size: 13px; line-height: 2.46153846; position: fixed; top: 0; left: 0; right: 0; z-index: 999; }
        .admin-header span { color: #f0f0f1; font-weight: 400; }
        .admin-header a { color: #a7aaad; text-decoration: none; margin-left: 15px; transition: color 0.1s ease-in-out; }
        .admin-header a:hover { color: #00a0d2; }
        .admin-sidebar { position: fixed !important; left: 0 !important; top: 32px !important; bottom: 0 !important; width: 160px !important; background: #23282d !important; overflow-y: auto !important; z-index: 998 !important; display: block !important; }
        .admin-sidebar ul { list-style: none; margin: 0; padding: 0; }
        .admin-sidebar li { margin: 0; }
        .admin-sidebar a { display: block; padding: 12px 20px 12px 12px; color: #a7aaad; text-decoration: none; border-left: 4px solid transparent; transition: all 0.1s ease-in-out; font-size: 14px; }
        .admin-sidebar a:hover { background: #32373c; color: #00a0d2; }
        .admin-sidebar a.active { background: #0073aa; color: white; border-left-color: #00a0d2; }
        .admin-sidebar .menu-icon { width: 20px; height: 20px; display: inline-block; margin-right: 8px; vertical-align: middle; opacity: 0.7; text-align: center; font-size: 18px; }
        .admin-nav { display: none; } /* Hide top nav, use sidebar instead */
        
        /* Ensure all admin pages have consistent layout */
        html { overflow-x: hidden; }
        .wrap { max-width: 1600px; margin: 20px auto; padding: 20px; }
        .notice { background: #fff; border-left: 4px solid #2271b1; padding: 1px 12px; margin: 15px 0; box-shadow: 0 1px 1px 0 rgba(0,0,0,0.1); }
        .notice p { margin: 12px 0; }
        .notice-success { border-left-color: #00a32a; }
        .notice-error { border-left-color: #d63638; }
        .button, input[type="submit"].button { padding: 6px 12px; border: 1px solid #2271b1; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; cursor: pointer; display: inline-block; font-size: 13px; line-height: 2.15384615; height: auto; min-height: 30px; }
        .button-primary, input[type="submit"].button-primary { background: #2271b1; border-color: #2271b1; }
        .button:hover, input[type="submit"].button:hover { background: #135e96; border-color: #135e96; }
        .button-delete, input[type="submit"].button-delete { background: #d63638; border-color: #d63638; }
        .button-delete:hover { background: #b32d2e; border-color: #b32d2e; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #1d2327; font-size: 14px; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="email"], .form-group input[type="tel"], .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 0; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: #2271b1; outline: 0; box-shadow: 0 0 0 1px #2271b1; }
        .large-text { width: 100%; max-width: 100%; }
        .wp-list-table { background: white; border: 1px solid #c3c4c7; width: 100%; border-collapse: collapse; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
        .wp-list-table th, .wp-list-table td { padding: 8px 10px; border-bottom: 1px solid #c3c4c7; text-align: left; font-size: 13px; }
        .wp-list-table th { background: #f6f7f7; font-weight: 400; color: #50575e; border-bottom: 1px solid #c3c4c7; }
        .wp-list-table.widefat th, .wp-list-table.widefat td { padding: 11px 10px; }
        .nav-tab-wrapper { margin: 0 0 20px; border-bottom: 1px solid #c3c4c7; }
        .nav-tab { display: inline-block; padding: 8px 12px; margin: 0 0.5em 0 0; border: 1px solid transparent; border-bottom: none; background: transparent; color: #2271b1; text-decoration: none; font-size: 14px; line-height: 2; }
        .nav-tab:hover { color: #135e96; }
        .nav-tab-active { background: #fff; border-color: #c3c4c7; border-bottom-color: #fff; color: #000; position: relative; margin-bottom: -1px; }
        .nav-tab-active:focus { color: #2271b1; box-shadow: none; }
        .wp-list-table.striped tbody tr:nth-child(odd) { background-color: #f6f7f7; }
        .page-title-action { display: inline-block; padding: 4px 8px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; margin-left: 10px; font-size: 13px; line-height: 2.15384615; height: 30px; }
        .page-title-action:hover { background: #135e96; }
        .status-published { color: #00a32a; font-weight: 400; }
        .status-draft { color: #dba617; }
        h1 { font-size: 23px; font-weight: 400; padding: 9px 0 4px 0; margin: 0 0 20px 0; line-height: 1.3; }
        h2 { font-size: 18px; font-weight: 600; margin: 25px 0 15px 0; }
        .submit { margin: 20px 0 0; padding: 10px 0; border-top: 1px solid #c3c4c7; }
        .submit input[type="submit"] { margin-right: 10px; }
        .description { font-size: 13px; font-style: normal; color: #646970; margin-top: 6px; }
    </style>
    <div class="admin-header">
        <span><?php echo htmlspecialchars($companyName); ?></span>
        <div>
            <a href="<?php echo $baseUrl; ?>/cms/admin/profile.php"><?php echo htmlspecialchars($user['username']); ?></a>
            <a href="<?php echo $baseUrl; ?>/cms/" target="_blank">View Site</a>
            <?php 
            if ($cmsAuth->isAdmin()): 
                require_once $rootPath . '/includes/sso.php';
                $sso = new SSO();
                
                // Check if already logged into ABBIS
                if ($sso->isLoggedIntoABBIS()) {
                    $abbisLink = $baseUrl . '/modules/dashboard.php';
                } else {
                    $abbisLink = $sso->getABBISLoginURL($user['id'], $user['username'], $user['role']);
                    if (!$abbisLink) {
                        $abbisLink = $baseUrl . '/login.php';
                    }
                }
            ?>
                <a href="<?php echo htmlspecialchars($abbisLink); ?>" style="color: #00a0d2;">→ ABBIS System</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <nav class="admin-sidebar">
        <ul>
            <li><a href="index.php" class="<?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                <span class="menu-icon">📊</span> Dashboard
            </a></li>
            <li><a href="pages.php" class="<?php echo $currentPage === 'pages' ? 'active' : ''; ?>">
                <span class="menu-icon">📄</span> Pages
            </a></li>
            <li><a href="posts.php" class="<?php echo $currentPage === 'posts' ? 'active' : ''; ?>">
                <span class="menu-icon">✏️</span> Posts
            </a></li>
            <li><a href="media.php" class="<?php echo $currentPage === 'media' ? 'active' : ''; ?>">
                <span class="menu-icon">🖼️</span> Media
            </a></li>
            <li><a href="products.php" class="<?php echo $currentPage === 'products' ? 'active' : ''; ?>">
                <span class="menu-icon">🛍️</span> Products
            </a></li>
            <li><a href="orders.php" class="<?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
                <span class="menu-icon">🛒</span> Orders
            </a></li>
            <li><a href="coupons.php" class="<?php echo $currentPage === 'coupons' ? 'active' : ''; ?>">
                <span class="menu-icon">🎫</span> Coupons
            </a></li>
            <li><a href="quotes.php" class="<?php echo $currentPage === 'quotes' ? 'active' : ''; ?>">
                <span class="menu-icon">💬</span> Quotes
            </a></li>
            <li><a href="comments.php" class="<?php echo $currentPage === 'comments' ? 'active' : ''; ?>">
                <span class="menu-icon">💭</span> Comments
            </a></li>
            <li><a href="appearance.php" class="<?php echo $currentPage === 'appearance' ? 'active' : ''; ?>">
                <span class="menu-icon">🎨</span> Appearance
            </a></li>
            <li><a href="menus.php" class="<?php echo $currentPage === 'menus' ? 'active' : ''; ?>">
                <span class="menu-icon">📋</span> Menus
            </a></li>
            <li><a href="plugins.php" class="<?php echo $currentPage === 'plugins' ? 'active' : ''; ?>">
                <span class="menu-icon">🔌</span> Plugins
            </a></li>
            <li><a href="users.php" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                <span class="menu-icon">👥</span> Users
            </a></li>
            <li><a href="settings.php" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <span class="menu-icon">⚙️</span> Settings
            </a></li>
            <li><a href="legal-documents.php" class="<?php echo $currentPage === 'legal-documents' ? 'active' : ''; ?>">
                <span class="menu-icon">📜</span> Legal Documents
            </a></li>
        </ul>
    </nav>
    
    <nav class="admin-nav">
        <ul>
            <li><a href="index.php" class="<?php echo $currentPage === 'index' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="pages.php" class="<?php echo $currentPage === 'pages' ? 'active' : ''; ?>">Pages</a></li>
            <li><a href="posts.php" class="<?php echo $currentPage === 'posts' ? 'active' : ''; ?>">Posts</a></li>
            <li><a href="media.php" class="<?php echo $currentPage === 'media' ? 'active' : ''; ?>">Media</a></li>
            <li><a href="products.php" class="<?php echo $currentPage === 'products' ? 'active' : ''; ?>">Products</a></li>
            <li><a href="orders.php" class="<?php echo $currentPage === 'orders' ? 'active' : ''; ?>">Orders</a></li>
            <li><a href="coupons.php" class="<?php echo $currentPage === 'coupons' ? 'active' : ''; ?>">Coupons</a></li>
            <li><a href="quotes.php" class="<?php echo $currentPage === 'quotes' ? 'active' : ''; ?>">Quotes</a></li>
            <li><a href="comments.php" class="<?php echo $currentPage === 'comments' ? 'active' : ''; ?>">Comments</a></li>
            <li><a href="appearance.php" class="<?php echo $currentPage === 'appearance' ? 'active' : ''; ?>">Appearance</a></li>
            <li><a href="plugins.php" class="<?php echo $currentPage === 'plugins' ? 'active' : ''; ?>">Plugins</a></li>
            <li><a href="users.php" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>">Users</a></li>
            <li><a href="settings.php" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">Settings</a></li>
        </ul>
    </nav>
<?php } // End HEADER_OUTPUT check ?>
