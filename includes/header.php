<?php
/**
 * Modern Header Component
 */
// Include functions first for isFeatureEnabled()
require_once __DIR__ . '/functions.php';

if (!isset($auth)) {
    require_once __DIR__ . '/auth.php';
}
if (!function_exists('e')) {
    require_once __DIR__ . '/helpers.php';
}

// Get company config for header
$pdo = getDBConnection();
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $configStmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_module = ($current_dir === 'modules');
$_SESSION['is_module'] = $is_module; // Store for footer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ABBIS - Advanced Borehole Business Intelligence System">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo e($page_title ?? 'ABBIS'); ?></title>
    <?php
    // Get company logo for favicon and header
    $pdo = getDBConnection();
    $logoStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_logo'");
    $logoResult = $logoStmt->fetch();
    $logoPath = $logoResult ? $logoResult['config_value'] : '';
    $logoUrl = '';
    $faviconUrl = '';
    
    if ($logoPath && !empty($logoPath)) {
        $basePath = $is_module ? '../' : '';
        $fullLogoPath = $basePath . $logoPath;
        $fullLogoPathCheck = str_replace($basePath, '', $fullLogoPath);
        
        // Check if logo file exists
        if (file_exists($fullLogoPathCheck)) {
            $logoUrl = $fullLogoPath;
            $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
            
            // Set favicon
            $faviconPath = $basePath . 'assets/images/favicon.' . $extension;
            $faviconPathCheck = str_replace($basePath, '', $faviconPath);
            if (file_exists($faviconPathCheck)) {
                $faviconUrl = $faviconPath;
            } else {
                // Fallback to logo as favicon
                $faviconUrl = $logoUrl;
            }
        }
    }
    
    // Set favicon
    if ($faviconUrl) {
        $faviconExtension = pathinfo($faviconUrl, PATHINFO_EXTENSION);
        $mimeType = in_array($faviconExtension, ['png', 'jpg', 'jpeg']) ? 'image/' . ($faviconExtension === 'jpg' ? 'jpeg' : $faviconExtension) : 'image/png';
        echo '<link rel="icon" type="' . $mimeType . '" href="' . e($faviconUrl) . '?v=' . time() . '">' . "\n";
        echo '<link rel="shortcut icon" type="' . $mimeType . '" href="' . e($faviconUrl) . '?v=' . time() . '">' . "\n";
    } else {
        // Fallback to water droplets PNG favicon (better browser support)
        $faviconPath = $is_module ? '../' : '';
        $emojiFaviconPng = $faviconPath . 'assets/images/favicon.png';
        $emojiFaviconPngCheck = str_replace('../', '', $emojiFaviconPng);
        
        if (file_exists($emojiFaviconPngCheck)) {
            echo '<link rel="icon" type="image/png" sizes="32x32" href="' . e($emojiFaviconPng) . '?v=' . time() . '">' . "\n";
            echo '<link rel="shortcut icon" type="image/png" href="' . e($emojiFaviconPng) . '?v=' . time() . '">' . "\n";
        } else {
            // Fallback to SVG
            $emojiFaviconSvg = $faviconPath . 'assets/images/favicon.svg';
            $emojiFaviconSvgCheck = str_replace('../', '', $emojiFaviconSvg);
            if (file_exists($emojiFaviconSvgCheck)) {
                echo '<link rel="icon" type="image/svg+xml" href="' . e($emojiFaviconSvg) . '?v=' . time() . '">' . "\n";
            }
        }
    }
    ?>
    <!-- Browser compatibility polyfills (load early) -->
    <script src="<?php echo $is_module ? '../' : ''; ?>assets/js/polyfills.js?v=<?php echo time(); ?>"></script>
    <link rel="stylesheet" href="<?php echo $is_module ? '../' : ''; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $is_module ? '../' : ''; ?>assets/css/mobile-enhancements.css?v=<?php echo time(); ?>">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo e($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
<?php 
// Include cookie consent banner (if not logged in or hasn't consented)
if (!isset($_COOKIE['cookie_consent']) || $_COOKIE['cookie_consent'] !== 'accepted') {
    require_once __DIR__ . '/cookie-consent.php';
}
?>
    <!-- Header -->
    <header class="header">
        <div class="container-fluid">
            <div class="header-content">
                <div class="logo">
                    <?php 
                    $logoDisplay = false;
                    if ($logoUrl && !empty($logoUrl)) {
                        $logoCheckPath = str_replace('../', '', $logoUrl);
                        if (file_exists($logoCheckPath)) {
                            $logoDisplay = true;
                        }
                    }
                    if ($logoDisplay): ?>
                        <img src="<?php echo e($logoUrl); ?>?v=<?php echo time(); ?>" alt="Company Logo" class="logo-image" style="height: 40px; max-width: 200px; object-fit: contain; margin-right: 12px; display: inline-block;">
                    <?php else: ?>
                        <span class="logo-mark" style="font-size: 32px; margin-right: 12px; display: inline-block;">💦</span>
                    <?php endif; ?>
                    <div style="display: inline-block;">
                        <h1>ABBIS</h1>
                        <span class="logo-subtitle"><?php echo e($config['company_tagline'] ?? 'Advanced Borehole Business Intelligence System'); ?></span>
                    </div>
                </div>
                
                <div class="header-actions">
                    <button class="mobile-menu-toggle" aria-label="Toggle menu" id="mobileMenuToggle">
                        <span class="hamburger-icon">☰</span>
                    </button>
                    <a href="<?php echo $is_module ? '' : 'modules/'; ?>profile.php" class="user-info" style="text-decoration: none; color: inherit;">
                        <?php
                        // Safely check for profile photo (handle if column doesn't exist yet)
                        $profilePhoto = '';
                        try {
                            $pdo = getDBConnection();
                            // Check if profile_photo column exists
                            $checkStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
                            if ($checkStmt->rowCount() > 0) {
                                $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $result = $stmt->fetch();
                                $profilePhoto = $result['profile_photo'] ?? '';
                            }
                        } catch (PDOException $e) {
                            // Column doesn't exist yet, ignore
                            $profilePhoto = '';
                        }
                        ?>
                        <?php if ($profilePhoto && file_exists(str_replace('../', '', $profilePhoto))): ?>
                            <img src="<?php echo e($is_module ? '../' : '') . $profilePhoto; ?>" 
                                 alt="Profile" 
                                 style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle;">
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        <?php endif; ?>
                        <span><?php echo e($_SESSION['full_name'] ?? 'User'); ?></span>
                        <span class="user-role"><?php echo e($_SESSION['role'] ?? 'Guest'); ?></span>
                    </a>
                    <button class="theme-toggle" aria-label="Toggle theme">
                        <span class="theme-icon">🌙</span>
                    </button>
                    <a href="<?php echo $is_module ? '../' : ''; ?>modules/field-reports.php" class="btn btn-primary btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        New Report
                    </a>
                    <a href="<?php echo $is_module ? '' : 'modules/'; ?>help.php" 
                       class="btn btn-outline btn-icon" 
                       title="Help & User Guide"
                       style="margin-right: 10px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        Help
                    </a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN): ?>
                        <?php
                        // Get base URL for CMS link
                        $baseUrl = '/abbis3.2';
                        if (defined('APP_URL')) {
                            $parsed = parse_url(APP_URL);
                            $baseUrl = $parsed['path'] ?? '/abbis3.2';
                        }
                        ?>
                        <a href="<?php echo $baseUrl; ?>/cms/admin/" 
                           class="btn btn-outline btn-icon" 
                           title="CMS Admin"
                           style="margin-right: 10px; color: #2271b1;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="9" y1="3" x2="9" y2="21"></line>
                                <line x1="15" y1="3" x2="15" y2="21"></line>
                                <line x1="3" y1="9" x2="21" y2="9"></line>
                                <line x1="3" y1="15" x2="21" y2="15"></line>
                            </svg>
                            CMS
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo $is_module ? '../' : ''; ?>logout.php" class="btn btn-outline btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="main-nav" id="mainNav">
        <div class="container-fluid">
            <div class="nav-container">
                <!-- 1. Dashboard - Overview & Home -->
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>dashboard.php" 
                   class="nav-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Dashboard
                </a>
                <!-- 2. Field Reports - Core Operations -->
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>field-reports.php" 
                   class="nav-item <?php echo (strpos($current_page, 'field-report') !== false) ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    Field Reports
                </a>
                <!-- 3. Clients - Top level list -->
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>crm.php?action=clients" 
                   class="nav-item <?php echo (in_array($current_page, ['clients.php', 'crm.php'])) ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    Clients
                </a>
                <!-- 4. HR - Human Resources (Staff, Workers, Stakeholders) - Moved up for better workflow -->
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>hr.php" 
                   class="nav-item <?php echo ($current_page === 'hr.php') ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    HR
                </a>
                <!-- 5. Resources - Materials, Inventory & Assets Hub -->
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>resources.php" 
                   class="nav-item <?php echo (in_array($current_page, ['resources.php', 'materials.php', 'catalog.php', 'inventory-advanced.php', 'assets.php', 'maintenance.php'])) ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                        <line x1="15" y1="3" x2="15" y2="21"></line>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="3" y1="15" x2="21" y2="15"></line>
                    </svg>
                    Resources
                </a>
                <!-- 6. Finance - Financial Management Hub (groups Finance, Payroll, Loans, Accounting) -->
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>financial.php" 
                   class="nav-item <?php echo (in_array($current_page, ['financial.php', 'finance.php', 'payroll.php', 'loans.php', 'accounting.php'])) ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    Finance
                </a>
                <!-- 6. CMS Website - Content Management (before System) -->
                <!-- 7. System - Administration (before search) -->
                <?php if ($auth->getUserRole() === ROLE_ADMIN): ?>
                <a href="<?php echo $is_module ? '' : 'modules/'; ?>system.php" 
                   class="nav-item <?php echo (in_array($current_page, ['system.php', 'config.php', 'data-management.php', 'api-keys.php', 'users.php', 'zoho-integration.php', 'looker-studio-integration.php', 'elk-integration.php', 'feature-management.php', 'database-migrations.php'])) ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                        <line x1="15" y1="3" x2="15" y2="21"></line>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="3" y1="15" x2="21" y2="15"></line>
                    </svg>
                    System
                </a>
                <?php endif; ?>
                <!-- 7. Global Search (last) -->
                <div class="nav-item" style="position: relative; margin-left: auto; display: flex; align-items: center;">
                    <input type="text" 
                           id="globalSearch" 
                           placeholder="🔍 Search..." 
                           style="
                               padding: 8px 35px 8px 12px;
                               border: 1px solid var(--border);
                               border-radius: 20px;
                               background: var(--input);
                               color: var(--text);
                               font-size: 13px;
                               width: 200px;
                               outline: none;
                               transition: all 0.2s ease;
                           "
                           onfocus="this.style.width='300px'; this.style.borderColor='var(--primary)';"
                           onblur="if(this.value==='') this.style.width='200px'; this.style.borderColor='var(--border)';"
                           onkeydown="if(event.key==='Enter') handleGlobalSearch(this.value)"
                    >
                    <button onclick="openAdvancedSearch()" 
                            style="
                                position: absolute;
                                right: 8px;
                                top: 50%;
                                transform: translateY(-50%);
                                background: none;
                                border: none;
                                color: var(--secondary);
                                cursor: pointer;
                                font-size: 12px;
                                padding: 4px;
                            "
                            title="Advanced Search">
                        ⚙️
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php 
    $flash = getFlash();
    if ($flash): 
    ?>
    <div class="container-fluid">
        <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible">
            <span><?php echo e($flash['message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="main-content">
        <div class="container-fluid">
