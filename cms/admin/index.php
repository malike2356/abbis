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
$baseUrl = app_base_path();

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
$baseUrl = app_base_path();

// Get stats
$pagesCount = $pdo->query("SELECT COUNT(*) FROM cms_pages")->fetchColumn();
$postsCount = $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='published'")->fetchColumn();
$quotesCount = 0;
try {
    $quotesCount = $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='new'")->fetchColumn();
} catch (Throwable $e) {}
$rigRequestsCount = 0;
try {
    $rigRequestsCount = $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='new'")->fetchColumn();
} catch (Throwable $e) {}
$contactSubmissionsCount = 0;
try {
    $contactSubmissionsCount = $pdo->query("SELECT COUNT(*) FROM contact_submissions WHERE status='new'")->fetchColumn();
} catch (Throwable $e) {}
$ordersCount = 0;
try {
    $ordersCount = $pdo->query("SELECT COUNT(*) FROM cms_orders WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {}
$productsCount = 0;
try {
    $productsCount = $pdo->query("SELECT COUNT(*) FROM catalog_items")->fetchColumn();
} catch (Throwable $e) {}
$categoriesCount = $pdo->query("SELECT COUNT(*) FROM cms_categories")->fetchColumn();
$commentsCount = 0;
try {
    $commentsCount = $pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {}

// Get additional stats for missing modules
$portfolioCount = 0;
try {
    $portfolioCount = $pdo->query("SELECT COUNT(*) FROM cms_portfolio WHERE status='published'")->fetchColumn();
} catch (Throwable $e) {}

$mediaCount = 0;
$mediaSize = 0;
try {
    $mediaCount = $pdo->query("SELECT COUNT(*) FROM cms_media")->fetchColumn();
    $mediaSize = $pdo->query("SELECT COALESCE(SUM(file_size), 0) FROM cms_media")->fetchColumn() ?: 0;
} catch (Throwable $e) {}
$mediaSizeMb = $mediaSize ? number_format($mediaSize / (1024 * 1024), 1) : '0.0';

$couponsCount = 0;
try {
    $couponsCount = $pdo->query("SELECT COUNT(*) FROM cms_coupons WHERE status='active'")->fetchColumn();
} catch (Throwable $e) {}

$usersCount = 0;
try {
    $usersCount = $pdo->query("SELECT COUNT(*) FROM cms_users WHERE status='active'")->fetchColumn();
} catch (Throwable $e) {}

$paymentMethodsCount = 0;
try {
    $paymentMethodsCount = $pdo->query("SELECT COUNT(*) FROM cms_payment_methods WHERE is_active=1")->fetchColumn();
} catch (Throwable $e) {}

$widgetsCount = 0;
try {
    $widgetsCount = $pdo->query("SELECT COUNT(*) FROM cms_widgets WHERE is_active=1")->fetchColumn();
} catch (Throwable $e) {}

$legalDocsCount = 0;
try {
    $legalDocsCount = $pdo->query("SELECT COUNT(*) FROM cms_legal_documents WHERE is_active=1")->fetchColumn();
} catch (Throwable $e) {}

$themesCount = 0;
try {
    $themesCount = $pdo->query("SELECT COUNT(*) FROM cms_themes")->fetchColumn();
} catch (Throwable $e) {}

// Get recent orders
$recentOrders = [];
try {
    $recentOrders = $pdo->query("SELECT * FROM cms_orders ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

// Get recent posts
$recentPosts = $pdo->query("SELECT * FROM cms_posts ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent rig requests
$recentRigRequests = [];
try {
    $recentRigRequests = $pdo->query("SELECT * FROM rig_requests ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

// Get recent contact submissions
$recentContactSubmissions = [];
try {
    $recentContactSubmissions = $pdo->query("SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

// Get recent portfolio items
$recentPortfolios = [];
try {
    $recentPortfolios = $pdo->query("SELECT * FROM cms_portfolio ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

// Get sales stats (last 30 days)
$salesStats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value
    FROM cms_orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

$salesTotalOrders = (int)($salesStats['total_orders'] ?? 0);
$salesTotalRevenue = (float)($salesStats['total_revenue'] ?? 0);
$salesAvgOrder = (float)($salesStats['avg_order_value'] ?? 0);

$moduleShortcuts = [
    [
        'title' => 'Menus',
        'icon' => '📋',
        'description' => 'Update site navigation and assign menus to locations.',
        'href' => 'menus.php'
    ],
    [
        'title' => 'Widgets',
        'icon' => '🧩',
        'description' => 'Place content in widget-ready areas across the site.',
        'href' => 'widgets.php'
    ],
    [
        'title' => 'Appearance',
        'icon' => '🎨',
        'description' => 'Activate themes, customize styles, and manage templates.',
        'href' => 'appearance.php'
    ],
    [
        'title' => 'CMS Settings',
        'icon' => '⚙️',
        'description' => 'Configure site identity, contact info, and global options.',
        'href' => 'settings.php'
    ],
    [
        'title' => 'Plugins',
        'icon' => '🔌',
        'description' => 'Enable or disable extensions that add CMS capabilities.',
        'href' => 'plugins.php'
    ],
    [
        'title' => 'Languages',
        'icon' => '🌐',
        'description' => 'Manage localization, translations, and regional settings.',
        'href' => 'languages.php'
    ],
    [
        'title' => 'Access Control',
        'icon' => '🛡️',
        'description' => 'Set user roles, permissions, and content restrictions.',
        'href' => 'access-control.php'
    ],
    [
        'title' => 'API Keys',
        'icon' => '🔑',
        'description' => 'Generate keys for integrations and external services.',
        'href' => 'api-keys.php'
    ],
    [
        'title' => 'CRM & Leads',
        'icon' => '🤝',
        'description' => 'Follow up on inquiries from the CRM workspace.',
        'href' => $baseUrl . '/modules/crm.php?action=dashboard'
    ],
    [
        'title' => 'Module Positions',
        'icon' => '🧱',
        'description' => 'Assign modules to layout positions across the CMS theme.',
        'href' => 'module-positions.php'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?> - CMS Admin</title>
    <style>
        /* ============================================
           WORLD-CLASS CMS ADMIN DASHBOARD
           Combining WordPress, Drupal, and Joomla
           ============================================ */
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --wp-blue: #2563eb;
            --wp-blue-hover: #1e40af;
            --wp-blue-light: #3b82f6;
            --wp-blue-dark: #1e3a8a;
            --wp-gray-50: #f6f7f7;
            --wp-gray-100: #f0f0f1;
            --wp-gray-200: #c3c4c7;
            --wp-gray-600: #646970;
            --wp-gray-900: #1d2327;
            --drupal-blue: #2563eb;
            --success-green: #00a32a;
            --warning-orange: #dba617;
            --danger-red: #d63638;
            --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e40af 100%);
            --gradient-success: linear-gradient(135deg, #00a32a 0%, #008a20 100%);
            --gradient-warning: linear-gradient(135deg, #dba617 0%, #c19b00 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-blue: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
            background: linear-gradient(135deg, #f0f0f1 0%, #e5e7eb 100%);
            color: #1d2327;
            line-height: 1.6;
        }
        
        .wrap { 
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        
        /* Enhanced Welcome Panel (WordPress + Drupal style) */
        .welcome-panel { 
            background: linear-gradient(135deg, #ffffff 0%, #f6f7f7 100%);
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 32px 28px;
            margin: 20px 0 30px 0;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-blue);
        }
        
        .welcome-panel h2 { 
            margin: 0 0 12px 0; 
            font-size: 24px; 
            font-weight: 700;
            background: var(--gradient-blue);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        
        .welcome-panel p { 
            margin: 0 0 20px 0; 
            color: #646970; 
            font-size: 14px; 
            line-height: 1.7; 
        }
        
        .welcome-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .welcome-btn {
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }
        
        .welcome-btn-primary {
            background: var(--gradient-success);
            color: white;
        }
        
        .welcome-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-btn-secondary {
            background: var(--gradient-blue);
            color: white;
        }
        
        .welcome-btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Modern Stats Grid (WordPress-style cards) - Fully Fluid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); 
            gap: 20px; 
            margin: 30px 0; 
            width: 100%;
        }
        
        .stat-box { 
            background: white;
            border: 1px solid #c3c4c7;
            border-left: 4px solid var(--wp-blue);
            border-radius: 12px;
            padding: 24px;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-blue);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg), var(--shadow-blue);
            border-left-color: var(--wp-blue-light);
        }
        
        .stat-box:hover::before {
            opacity: 1;
        }
        
        .stat-box.clickable { 
            cursor: pointer; 
        }
        
        .stat-box-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.08) 100%);
            transition: transform 0.3s ease;
        }
        
        .stat-box:hover .stat-icon {
            transform: scale(1.1);
        }
        
        .stat-box h3 { 
            font-size: 32px; 
            margin: 0 0 8px 0; 
            background: var(--gradient-blue);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -1px;
        }
        
        .stat-box p { 
            margin: 0 0 8px 0; 
            color: #646970; 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 0.8px;
            font-weight: 600;
        }
        
        .stat-link { 
            font-size: 13px; 
            color: var(--wp-blue); 
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        
        .stat-link:hover { 
            color: var(--wp-blue-hover);
            transform: translateX(4px);
        }
        
        /* Dashboard Cards (Drupal-style blocks) */
        .dashboard-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .dashboard-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1d2327;
            margin: 0 0 20px 0;
            padding-bottom: 14px;
            border-bottom: 2px solid #c3c4c7;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-card h2::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--gradient-blue);
            border-radius: 2px;
        }
        
        /* Quick Actions Grid (WordPress-style) - Fully Fluid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            width: 100%;
        }
        
        /* Enhanced Stats Grid - Let it flow naturally based on available space */
        @media (min-width: 1600px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            }
        }
        
        @media (min-width: 2000px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }
        
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 10px;
            text-decoration: none;
            color: #1d2327;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.15), transparent);
            transition: left 0.5s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md), var(--shadow-blue);
            border-color: var(--wp-blue);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
        }
        
        .quick-action-btn:hover::before {
            left: 100%;
        }
        
        .quick-action-icon {
            font-size: 32px;
            margin-bottom: 10px;
            transition: transform 0.3s ease;
        }
        
        .quick-action-btn:hover .quick-action-icon {
            transform: scale(1.15) rotate(5deg);
        }
        
        .quick-actions-card h3 {
            font-size: 16px;
            margin: 0 0 12px 0;
            color: #1d2327;
            font-weight: 600;
        }
        
        .quick-actions-section + .quick-actions-section {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
        }
        
        .dashboard-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin: 28px 0;
        }
        
        .overview-card h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #1d2327;
        }
        
        .overview-card p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 13px;
        }
        
        .overview-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        
        .overview-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.05) 100%);
            font-size: 22px;
        }
        
        .overview-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
        }
        
        .overview-metric {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .overview-metric:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--wp-blue-light);
        }
        
        .overview-metric .metric-count {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }
        
        .overview-metric .metric-label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        
        .overview-metric .metric-sub {
            font-size: 12px;
            color: #64748b;
        }
        
        .overview-summary {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }
        
        .overview-summary div {
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: #f1f5f9;
            border-radius: 10px;
            padding: 12px;
        }
        
        .overview-summary .label {
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
        }
        
        .overview-summary strong {
            font-size: 15px;
            color: #0f172a;
        }
        
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        
        .module-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        
        .module-card:hover {
            transform: translateY(-2px);
            border-color: var(--wp-blue-light);
            box-shadow: var(--shadow-md);
        }
        
        .module-icon {
            font-size: 22px;
            background: rgba(37, 99, 235, 0.08);
            border-radius: 10px;
            padding: 10px;
            flex: 0 0 auto;
        }
        
        .module-card strong {
            font-size: 15px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .module-card .module-copy span {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .dashboard-overview-grid {
                grid-template-columns: 1fr;
            }
            .overview-metrics {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
            .overview-summary {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
        }
        
        /* Status Badges */
        .status-pending { color: #d63638; font-weight: 600; }
        .status-processing { color: #dba617; font-weight: 600; }
        .status-completed { color: #00a32a; font-weight: 600; }
        .status-cancelled { color: #646970; font-weight: 600; }
        
        /* Enhanced Tables */
        .wp-list-table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .wp-list-table tbody tr {
            transition: background 0.2s ease;
        }
        
        .wp-list-table tbody tr:hover {
            background: #f6f7f7;
        }
        
        /* Special Pages Cards */
        .special-page-card {
            border: 1px solid #c3c4c7;
            border-radius: 10px;
            padding: 20px;
            background: linear-gradient(135deg, #f6f7f7 0%, #ffffff 100%);
            transition: all 0.3s ease;
        }
        
        .special-page-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md), var(--shadow-blue);
            border-color: var(--wp-blue);
        }
        
        .special-page-card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Mobile-First Responsive Design */
        @media (max-width: 768px) {
            .wrap {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
            }
            
            .welcome-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .welcome-btn {
                width: 100%;
                justify-content: center;
            }
            
            .welcome-panel {
                padding: 20px;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            /* Make two-column layouts single column on mobile */
            div[style*="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr))"] {
                grid-template-columns: 1fr !important;
            }
            
            /* Adjust stat boxes for mobile */
            .stat-box {
                padding: 18px;
            }
            
            .stat-box h3 {
                font-size: 28px;
            }
        }
        
        @media (max-width: 480px) {
            .wrap {
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-box h3 {
                font-size: 24px;
            }
            
            .welcome-panel h2 {
                font-size: 20px;
            }
            
            .dashboard-card h2 {
                font-size: 18px;
            }
        }
        
        /* Tablet adjustments */
        @media (min-width: 769px) and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        /* Smooth Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-box, .dashboard-card {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Focus States for Accessibility */
        .stat-box:focus,
        .quick-action-btn:focus {
            outline: 2px solid var(--wp-blue);
            outline-offset: 2px;
        }
    </style>
    <?php 
    $currentPage = 'index';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    <div class="wrap">
        <h1 style="font-size: 32px; font-weight: 700; margin: 0 0 24px 0; background: var(--gradient-blue); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -0.5px;">Dashboard</h1>
        
        <div class="welcome-panel">
            <h2>Welcome to <?php echo htmlspecialchars($siteTitle); ?> CMS Admin</h2>
            <p>Thank you for using our content management system. Use the navigation menu to manage your website content, pages, posts, products, and settings.</p>
            <div class="welcome-actions">
                <a href="<?php echo $baseUrl; ?>/cms/" target="_blank" class="welcome-btn welcome-btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    View CMS Website
                </a>
                <?php if ($isAdmin && $abbisLink): ?>
                    <a href="<?php echo htmlspecialchars($abbisLink); ?>" class="welcome-btn welcome-btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                        </svg>
                        Access ABBIS
                    </a>
                <?php elseif ($isAdmin && $abbisLoginRequired): ?>
                    <span style="color: #d63638; font-size: 13px; padding: 12px 20px; background: rgba(214, 54, 56, 0.1); border-radius: 8px; border-left: 4px solid #d63638;">
                        <strong>Note:</strong> To access the ABBIS system, please log in with your ABBIS credentials.
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card quick-actions-card">
            <h2>⚡ Quick Actions</h2>
            <div class="quick-actions-section">
                <h3>Publish</h3>
                <div class="quick-actions-grid">
                    <a href="pages.php?action=add" class="quick-action-btn">
                        <div class="quick-action-icon">📄</div>
                        <div class="quick-action-label">Add New Page</div>
                    </a>
                    <a href="posts.php?action=add" class="quick-action-btn">
                        <div class="quick-action-icon">✏️</div>
                        <div class="quick-action-label">Write Blog Post</div>
                    </a>
                    <a href="media.php" class="quick-action-btn">
                        <div class="quick-action-icon">🖼️</div>
                        <div class="quick-action-label">Upload Media</div>
                    </a>
                    <a href="portfolio.php?action=add" class="quick-action-btn">
                        <div class="quick-action-icon">📸</div>
                        <div class="quick-action-label">Add Portfolio Item</div>
                    </a>
                </div>
            </div>
            <div class="quick-actions-section">
                <h3>Commerce & Services</h3>
                <div class="quick-actions-grid">
                    <a href="products.php?action=add" class="quick-action-btn">
                        <div class="quick-action-icon">🛍️</div>
                        <div class="quick-action-label">Create Product</div>
                    </a>
                    <a href="orders.php" class="quick-action-btn">
                        <div class="quick-action-icon">🧾</div>
                        <div class="quick-action-label">Review Orders</div>
                    </a>
                    <a href="coupons.php?action=add" class="quick-action-btn">
                        <div class="quick-action-icon">🎫</div>
                        <div class="quick-action-label">Create Coupon</div>
                    </a>
                    <a href="payment-methods.php" class="quick-action-btn">
                        <div class="quick-action-icon">💳</div>
                        <div class="quick-action-label">Payment Methods</div>
                    </a>
                </div>
            </div>
            <div class="quick-actions-section">
                <h3>Engagement & Operations</h3>
                <div class="quick-actions-grid">
                    <a href="<?php echo $baseUrl; ?>/modules/crm.php?action=dashboard" class="quick-action-btn">
                        <div class="quick-action-icon">🤝</div>
                        <div class="quick-action-label">Open CRM Workspace</div>
                    </a>
                    <a href="quotes.php" class="quick-action-btn">
                        <div class="quick-action-icon">📄</div>
                        <div class="quick-action-label">Estimate Requests</div>
                    </a>
                    <a href="rig-requests.php" class="quick-action-btn">
                        <div class="quick-action-icon">🚛</div>
                        <div class="quick-action-label">Rig Requests</div>
                    </a>
                    <a href="comments.php" class="quick-action-btn">
                        <div class="quick-action-icon">💬</div>
                        <div class="quick-action-label">Moderate Comments</div>
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-overview-grid">
            <section class="dashboard-card overview-card">
                <div class="overview-header">
                    <span class="overview-icon">📚</span>
                    <div>
                        <h3>Content & Publishing</h3>
                        <p>Keep pages, posts, and media accurate.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <a class="overview-metric" href="pages.php">
                        <span class="metric-count"><?php echo number_format($pagesCount); ?></span>
                        <span class="metric-label">Pages</span>
                        <span class="metric-sub">Published</span>
                    </a>
                    <a class="overview-metric" href="posts.php">
                        <span class="metric-count"><?php echo number_format($postsCount); ?></span>
                        <span class="metric-label">Posts</span>
                        <span class="metric-sub">Live articles</span>
                    </a>
                    <a class="overview-metric" href="portfolio.php">
                        <span class="metric-count"><?php echo number_format($portfolioCount); ?></span>
                        <span class="metric-label">Portfolio</span>
                        <span class="metric-sub">Published projects</span>
                    </a>
                    <a class="overview-metric" href="media.php">
                        <span class="metric-count"><?php echo number_format($mediaCount); ?></span>
                        <span class="metric-label">Media Files</span>
                        <span class="metric-sub"><?php echo $mediaSizeMb; ?> MB stored</span>
                    </a>
                    <a class="overview-metric" href="categories.php">
                        <span class="metric-count"><?php echo number_format($categoriesCount); ?></span>
                        <span class="metric-label">Categories</span>
                        <span class="metric-sub">Content taxonomy</span>
                    </a>
                </div>
            </section>

            <section class="dashboard-card overview-card">
                <div class="overview-header">
                    <span class="overview-icon">💰</span>
                    <div>
                        <h3>Commerce & Monetization</h3>
                        <p>Monitor store performance and checkout.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <a class="overview-metric" href="products.php">
                        <span class="metric-count"><?php echo number_format($productsCount); ?></span>
                        <span class="metric-label">Products</span>
                        <span class="metric-sub">Catalog items</span>
                    </a>
                    <a class="overview-metric" href="orders.php">
                        <span class="metric-count"><?php echo number_format($ordersCount); ?></span>
                        <span class="metric-label">Pending Orders</span>
                        <span class="metric-sub">Awaiting fulfilment</span>
                    </a>
                    <a class="overview-metric" href="coupons.php">
                        <span class="metric-count"><?php echo number_format($couponsCount); ?></span>
                        <span class="metric-label">Active Coupons</span>
                        <span class="metric-sub">Live promotions</span>
                    </a>
                    <a class="overview-metric" href="payment-methods.php">
                        <span class="metric-count"><?php echo number_format($paymentMethodsCount); ?></span>
                        <span class="metric-label">Payment Methods</span>
                        <span class="metric-sub">Enabled gateways</span>
                    </a>
                </div>
                <div class="overview-summary">
                    <div>
                        <span class="label">30-day Orders</span>
                        <strong><?php echo number_format($salesTotalOrders); ?></strong>
                    </div>
                    <div>
                        <span class="label">30-day Revenue</span>
                        <strong>GHS <?php echo number_format($salesTotalRevenue, 2); ?></strong>
                    </div>
                    <div>
                        <span class="label">Avg Order Value</span>
                        <strong>GHS <?php echo number_format($salesAvgOrder, 2); ?></strong>
                    </div>
                </div>
            </section>

            <section class="dashboard-card overview-card">
                <div class="overview-header">
                    <span class="overview-icon">📞</span>
                    <div>
                        <h3>Leads & Requests</h3>
                        <p>Track inbound opportunities and support.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <a class="overview-metric" href="quotes.php">
                        <span class="metric-count"><?php echo number_format($quotesCount); ?></span>
                        <span class="metric-label">Estimate Requests</span>
                        <span class="metric-sub">Awaiting response</span>
                    </a>
                    <a class="overview-metric" href="rig-requests.php">
                        <span class="metric-count"><?php echo number_format($rigRequestsCount); ?></span>
                        <span class="metric-label">Rig Requests</span>
                        <span class="metric-sub">New submissions</span>
                    </a>
                    <a class="overview-metric" href="<?php echo $baseUrl; ?>/modules/crm.php?action=dashboard">
                        <span class="metric-count"><?php echo number_format($contactSubmissionsCount); ?></span>
                        <span class="metric-label">Contact Forms</span>
                        <span class="metric-sub">Open conversations</span>
                    </a>
                    <a class="overview-metric" href="comments.php">
                        <span class="metric-count"><?php echo number_format($commentsCount); ?></span>
                        <span class="metric-label">Comments</span>
                        <span class="metric-sub">Pending moderation</span>
                    </a>
                </div>
            </section>

            <section class="dashboard-card overview-card">
                <div class="overview-header">
                    <span class="overview-icon">🛠️</span>
                    <div>
                        <h3>Site Experience & Access</h3>
                        <p>Shape the front-end and manage your team.</p>
                    </div>
                </div>
                <div class="overview-metrics">
                    <a class="overview-metric" href="appearance.php">
                        <span class="metric-count"><?php echo number_format($themesCount); ?></span>
                        <span class="metric-label">Themes</span>
                        <span class="metric-sub">Installed</span>
                    </a>
                    <a class="overview-metric" href="widgets.php">
                        <span class="metric-count"><?php echo number_format($widgetsCount); ?></span>
                        <span class="metric-label">Widgets</span>
                        <span class="metric-sub">Active placements</span>
                    </a>
                    <a class="overview-metric" href="users.php">
                        <span class="metric-count"><?php echo number_format($usersCount); ?></span>
                        <span class="metric-label">Users</span>
                        <span class="metric-sub">Active accounts</span>
                    </a>
                    <a class="overview-metric" href="legal-documents.php">
                        <span class="metric-count"><?php echo number_format($legalDocsCount); ?></span>
                        <span class="metric-label">Legal Docs</span>
                        <span class="metric-sub">Published policies</span>
                    </a>
                </div>
            </section>
        </div>

        <!-- Special Pages Section -->
        <div class="dashboard-card">
            <h2>📄 Special Pages & Frontend</h2>
            <p style="color: #646970; margin-bottom: 20px; font-size: 14px;">These are special functional pages that can be edited via file or settings.</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; width: 100%;">
                <div class="special-page-card">
                    <h3>🏠 Homepage</h3>
                    <p style="margin: 0 0 15px 0; color: #646970; font-size: 13px; line-height: 1.6;">Main landing page with portfolio slider</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo $baseUrl; ?>/cms/" target="_blank" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px;">View Page</a>
                        <a href="pages.php?action=edit&id=home" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px; background: #646970; border-color: #646970;">Edit</a>
                    </div>
                </div>
                <div class="special-page-card">
                    <h3>📸 Portfolio Gallery</h3>
                    <p style="margin: 0 0 15px 0; color: #646970; font-size: 13px; line-height: 1.6;">Showcase your borehole projects</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo $baseUrl; ?>/cms/portfolio" target="_blank" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px;">View Page</a>
                        <a href="portfolio.php" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px; background: #646970; border-color: #646970;">Manage</a>
                    </div>
                </div>
                <div class="special-page-card">
                    <h3>📋 Estimates</h3>
                    <p style="margin: 0 0 15px 0; color: #646970; font-size: 13px; line-height: 1.6;">Estimate request form page</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo $baseUrl; ?>/cms/quote" target="_blank" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px;">View Page</a>
                        <a href="quotes.php" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px; background: #646970; border-color: #646970;">View Requests</a>
                    </div>
                </div>
                <div class="special-page-card">
                    <h3>🚛 Request Rig</h3>
                    <p style="margin: 0 0 15px 0; color: #646970; font-size: 13px; line-height: 1.6;">Rig rental request form</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo $baseUrl; ?>/cms/rig-request" target="_blank" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px;">View Page</a>
                        <a href="rig-requests.php" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px; background: #646970; border-color: #646970;">View Requests</a>
                    </div>
                </div>
                <div class="special-page-card">
                    <h3>🛍️ Shop</h3>
                    <p style="margin: 0 0 15px 0; color: #646970; font-size: 13px; line-height: 1.6;">E-commerce product catalog</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo $baseUrl; ?>/cms/shop" target="_blank" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px;">View Page</a>
                        <a href="products.php" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px; background: #646970; border-color: #646970;">Manage Products</a>
                    </div>
                </div>
                <div class="special-page-card">
                    <h3>📧 Contact Us</h3>
                    <p style="margin: 0 0 15px 0; color: #646970; font-size: 13px; line-height: 1.6;">Contact form and information</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="<?php echo $baseUrl; ?>/cms/contact" target="_blank" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px;">View Page</a>
                        <a href="settings.php#contact" class="button button-small" style="padding: 8px 16px; font-size: 13px; border-radius: 6px; background: #646970; border-color: #646970;">Edit Info</a>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
            <!-- Recent Orders -->
            <div class="dashboard-card">
                <h2>🛒 Recent Orders</h2>
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
            <div class="dashboard-card">
                <h2>✏️ Recent Posts</h2>
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
        
        <!-- Recent Portfolio Items -->
        <?php if (!empty($recentPortfolios)): ?>
        <div class="dashboard-card" style="margin-top: 24px;">
            <h2>📸 Recent Portfolio Items</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-top: 20px;">
                <?php foreach ($recentPortfolios as $item): ?>
                    <div style="background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; transition: all 0.3s;">
                        <?php if (!empty($item['featured_image'])): ?>
                            <div style="width: 100%; height: 120px; overflow: hidden; background: #e2e8f0;">
                                <img src="<?php echo htmlspecialchars($baseUrl . '/' . $item['featured_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php else: ?>
                            <div style="width: 100%; height: 120px; background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%); display: flex; align-items: center; justify-content: center; font-size: 48px; color: #94a3b8;">🖼️</div>
                        <?php endif; ?>
                        <div style="padding: 12px;">
                            <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: #1d2327;">
                                <a href="portfolio.php?action=edit&id=<?php echo $item['id']; ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars(substr($item['title'], 0, 30)); ?><?php echo strlen($item['title']) > 30 ? '...' : ''; ?>
                                </a>
                            </h4>
                            <p style="margin: 0; font-size: 12px; color: #646970;">
                                <span class="status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span>
                                <?php if ($item['location']): ?>
                                    • <?php echo htmlspecialchars(substr($item['location'], 0, 15)); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="portfolio.php" class="button" style="margin-top: 20px; display: inline-block;">View All Portfolio Items →</a>
        </div>
        <?php endif; ?>

        <!-- Recent Submissions Section -->
        <?php if (!empty($recentRigRequests) || !empty($recentContactSubmissions)): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
            <!-- Recent Rig Requests -->
            <?php if (!empty($recentRigRequests)): ?>
            <div class="dashboard-card">
                <h2>🚛 Recent Rig Requests</h2>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Requester</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRigRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($request['location_address'] ?? 'N/A', 0, 30)) . (strlen($request['location_address'] ?? '') > 30 ? '...' : ''); ?></td>
                                <td><?php echo isset($request['created_at']) ? date('Y/m/d', strtotime($request['created_at'])) : '-'; ?></td>
                                <td><span class="status-<?php echo $request['status'] ?? 'new'; ?>"><?php echo ucfirst($request['status'] ?? 'New'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="rig-requests.php" class="button" style="margin-top: 15px; display: inline-block;">View All Requests →</a>
            </div>
            <?php endif; ?>

            <!-- Recent Contact Submissions -->
            <?php if (!empty($recentContactSubmissions)): ?>
            <div class="dashboard-card">
                <h2>📧 Recent Contact Submissions</h2>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentContactSubmissions as $submission): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($submission['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($submission['subject'] ?? 'General Inquiry', 0, 20)) . (strlen($submission['subject'] ?? '') > 20 ? '...' : ''); ?></td>
                                <td><?php echo isset($submission['created_at']) ? date('Y/m/d', strtotime($submission['created_at'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="<?php echo $baseUrl; ?>/modules/crm.php?action=dashboard" class="button" style="margin-top: 15px; display: inline-block;">View All Submissions →</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h2>🔧 System Modules & Tools</h2>
            <p style="color: #646970; margin-bottom: 20px; font-size: 14px;">Key administrative areas for configuring the CMS experience.</p>
            <div class="module-grid">
                <?php foreach ($moduleShortcuts as $tool): ?>
                    <a href="<?php echo htmlspecialchars($tool['href']); ?>" class="module-card">
                        <span class="module-icon"><?php echo $tool['icon']; ?></span>
                        <div class="module-copy">
                            <strong><?php echo htmlspecialchars($tool['title']); ?></strong>
                            <span><?php echo htmlspecialchars($tool['description']); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

