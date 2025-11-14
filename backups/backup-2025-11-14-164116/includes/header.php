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

// Ensure constants are loaded (including AI_PERMISSION_KEY)
if (!defined('AI_PERMISSION_KEY')) {
    require_once __DIR__ . '/../config/constants.php';
}

require_once __DIR__ . '/navigation-tracker.php';

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
$assetBase = $is_module ? '../' : '';
$_SESSION['is_module'] = $is_module; // Store for footer
$modulePrefix = $is_module ? '' : 'modules/';

$accessControl = $auth->getAccessControl();
$userRole = $_SESSION['role'] ?? null;

if (isset($_SESSION['user_id'])) {
    $auth->enforcePageAccess($current_page);
    NavigationTracker::recordCurrentPage((int) $_SESSION['user_id']);
}

if (!function_exists('abbis_nav_is_active')) {
    function abbis_nav_is_active(array $matchers, string $currentPage, string $currentDir): bool
    {
        if (empty($matchers)) {
            return false;
        }

        foreach ($matchers as $matcher) {
            if (is_array($matcher)) {
                $type = $matcher['type'] ?? 'exact';
                $value = $matcher['value'] ?? '';

                if ($value === '') {
                    continue;
                }

                switch ($type) {
                    case 'exact':
                        if ($currentPage === $value) {
                            return true;
                        }
                        break;
                    case 'dir':
                        if ($currentDir === $value) {
                            return true;
                        }
                        break;
                    case 'contains':
                        if (strpos($currentPage, $value) !== false) {
                            return true;
                        }
                        break;
                    case 'regex':
                        if (@preg_match($value, $currentPage)) {
                            return true;
                        }
                        break;
                }
            } else {
                $value = (string)$matcher;

                if ($currentPage === $value || $currentDir === $value) {
                    return true;
                }

                if ($value !== '' && strpos($currentPage, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('abbis_render_nav_icon')) {
    function abbis_render_nav_icon(?string $name): string
    {
        if (!$name) {
            return '';
        }

        switch ($name) {
            case 'dashboard':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
            case 'reports':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>';
            case 'crm':
            case 'hr':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
            case 'resources':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line></svg>';
            case 'finance':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>';
            case 'ai':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3a4 4 0 0 0-4 4v1H6a2 2 0 0 0-2 2v2h2v1a4 4 0 0 0 8 0v-1h2v-2a2 2 0 0 0-2-2h-2V7a1 1 0 0 1 2 0"></path><path d="M7 17a4 4 0 0 0 10 0"></path></svg>';
            case 'help':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
            case 'pos':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="6" y1="10" x2="10" y2="10"></line><line x1="6" y1="14" x2="8" y2="14"></line><line x1="14" y1="14" x2="18" y2="14"></line></svg>';
            case 'client':
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
            default:
                return '<svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"></circle></svg>';
        }
    }
}

$navItems = [
    [
        'permission' => 'dashboard.view',
        'label' => 'Dashboard',
        'href' => $modulePrefix . 'dashboard.php',
        'icon' => 'dashboard',
        'match' => ['dashboard.php'],
    ],
    [
        'permission' => 'field_reports.manage',
        'label' => 'Field Reports',
        'href' => $modulePrefix . 'field-reports.php',
        'icon' => 'reports',
        'match' => [
            ['type' => 'contains', 'value' => 'field-report'],
            'field-reports.php',
            'reports.php',
        ],
    ],
    [
        'permission' => 'crm.access',
        'label' => 'CRM',
        'href' => $modulePrefix . 'crm.php?action=dashboard',
        'icon' => 'crm',
        'match' => ['clients.php', 'crm.php'],
    ],
    [
        'permission' => 'hr.access',
        'label' => 'HR',
        'href' => $modulePrefix . 'hr.php',
        'icon' => 'hr',
        'match' => ['hr.php'],
    ],
    [
        'permission' => 'resources.access',
        'label' => 'Resources',
        'href' => $modulePrefix . 'resources.php',
        'icon' => 'resources',
        'match' => ['resources.php', 'materials.php', 'catalog.php', 'inventory-advanced.php', 'assets.php', 'maintenance.php'],
    ],
    [
        'permission' => 'finance.access',
        'label' => 'Finance',
        'href' => $modulePrefix . 'financial.php',
        'icon' => 'finance',
        'match' => ['financial.php', 'finance.php', 'payroll.php', 'loans.php', 'accounting.php'],
    ],
    [
        'permission' => 'dashboard.view',
        'label' => 'Help',
        'href' => $modulePrefix . 'help.php',
        'icon' => 'help',
        'match' => ['help.php'],
    ],
];
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
    <script src="<?php echo $assetBase; ?>assets/js/polyfills.js?v=<?php echo time(); ?>"></script>
    <link rel="stylesheet" href="<?php echo $assetBase; ?>assets/css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $assetBase; ?>assets/css/mobile-enhancements.css?v=<?php echo time(); ?>">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo e($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php
    // Include JavaScript URL helper for frontend use
    if (function_exists('url_js_helper')) {
        echo url_js_helper();
    }
    ?>
</head>
<body 
    data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>"
    data-app-root="<?php echo e($assetBase); ?>"
    data-ai-enabled="<?php echo (isset($_SESSION['user_id']) && $auth->isLoggedIn()) ? '1' : '0'; ?>">
<?php 
// Include cookie consent banner (if not logged in or hasn't consented)
if (!isset($_COOKIE['cookie_consent']) || $_COOKIE['cookie_consent'] !== 'accepted') {
    require_once __DIR__ . '/cookie-consent.php';
}
?>
    <!-- App Header -->
    <header class="app-header header">
        <div class="container-fluid app-header__top">
            <div class="app-header__brand">
                <button class="app-header__menu mobile-menu-toggle" aria-label="Toggle main navigation" aria-haspopup="true" aria-expanded="false" aria-controls="mainNav" id="mobileMenuToggle">
                    <span class="app-header__menu-icon hamburger-icon" aria-hidden="true"></span>
                    <span class="sr-only">Open menu</span>
                </button>
                <a href="<?php echo e($modulePrefix . 'dashboard.php'); ?>" class="app-header__logo" aria-label="ABBIS home">
                    <?php
                    $logoDisplay = false;
                    if ($logoUrl && !empty($logoUrl)) {
                        $logoCheckPath = str_replace('../', '', $logoUrl);
                        if (file_exists($logoCheckPath)) {
                            $logoDisplay = true;
                        }
                    }
                    ?>
                    <?php if ($logoDisplay): ?>
                        <img src="<?php echo e($logoUrl); ?>?v=<?php echo time(); ?>" alt="Company logo" class="app-header__logo-image">
                    <?php else: ?>
                        <span class="app-header__logo-mark">💦</span>
                    <?php endif; ?>
                    <span class="app-header__identity">
                        <span class="app-header__title">ABBIS</span>
                        <span class="app-header__subtitle"><?php echo e($config['company_tagline'] ?? 'Advanced Borehole Business Intelligence System'); ?></span>
                    </span>
                </a>
            </div>

            <div class="app-header__search">
                <label for="globalSearch" class="sr-only">Search across ABBIS</label>
                <input type="search"
                       id="globalSearch"
                       placeholder="Search global data…"
                       autocomplete="off"
                       onkeydown="if(event.key==='Enter') handleGlobalSearch(this.value)">
                <button type="button"
                        class="app-header__search-advanced"
                        onclick="openAdvancedSearch()"
                        title="Open advanced search">
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <path d="M11 8a3 3 0 0 1 3 3"></path>
                    </svg>
                    <span class="sr-only">Advanced search</span>
                </button>
            </div>

            <div class="app-header__actions">
                <!-- Primary Actions: Most frequently used actions -->
                <?php if ($accessControl->shouldDisplayNav('field_reports.manage')): ?>
                    <a href="<?php echo e($modulePrefix . 'field-reports.php'); ?>"
                       class="app-header__icon-btn app-header__icon-btn--primary"
                       title="Create a new field report">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span class="sr-only">Create new field report</span>
                    </a>
                <?php endif; ?>

                <?php if ($accessControl->shouldDisplayNav('pos.access')): ?>
                    <a href="<?php echo e(($is_module ? '../' : '') . 'pos/index.php'); ?>"
                       class="app-header__icon-btn"
                       title="Point of Sale"
                       target="_blank"
                       rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="6" y1="10" x2="10" y2="10"></line>
                            <line x1="6" y1="14" x2="8" y2="14"></line>
                            <line x1="14" y1="14" x2="18" y2="14"></line>
                        </svg>
                        <span class="sr-only">Point of Sale</span>
                    </a>
                <?php endif; ?>

                <!-- External Links: Portal and external systems -->
                <?php
                // Add Client Portal to top header if user has access (admin, super admin, or client)
                if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
                    $userRole = $_SESSION['role'];
                    if ($userRole === ROLE_ADMIN || $userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_CLIENT) {
                        require_once __DIR__ . '/sso.php';
                        $sso = new SSO();
                        $clientPortalUrl = $sso->getClientPortalLoginURL(
                            $_SESSION['user_id'],
                            $_SESSION['username'],
                            $userRole
                        );
                        if (!$clientPortalUrl) {
                            $clientPortalUrl = app_url('client-portal/login.php');
                        }
                        ?>
                        <a href="<?php echo e($clientPortalUrl); ?>"
                           class="app-header__icon-btn"
                           title="Client Portal"
                           target="_blank"
                           rel="noopener noreferrer">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span class="sr-only">Client Portal</span>
                        </a>
                        <?php
                    }
                }
                ?>

                <?php if ($accessControl->shouldDisplayNav('system.admin')): ?>
                    <a href="<?php echo e(($is_module ? '../' : '') . 'cms/admin/index.php'); ?>"
                       class="app-header__icon-btn"
                       title="CMS Admin"
                       target="_blank"
                       rel="noopener noreferrer">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="3" x2="9" y2="21"></line>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                        </svg>
                        <span class="sr-only">CMS Admin</span>
                    </a>
                <?php endif; ?>

                <!-- System Preferences: Theme, Settings, Help -->
                <button class="theme-toggle app-header__icon-btn" type="button" aria-label="Toggle theme">
                    <span class="theme-icon">🌙</span>
                </button>

                <?php if ($accessControl->shouldDisplayNav('system.admin')): ?>
                    <a href="<?php echo e($modulePrefix . 'system.php'); ?>"
                       class="app-header__icon-btn"
                       title="Settings">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </a>
                <?php endif; ?>

                <a href="<?php echo e($modulePrefix . 'help.php'); ?>"
                   class="app-header__icon-btn"
                   title="Help & Guide">
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span class="sr-only">Help</span>
                </a>

                <?php
                $profilePhoto = '';
                try {
                    $pdo = getDBConnection();
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
                    if ($checkStmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'] ?? null]);
                        $result = $stmt->fetch();
                        $profilePhoto = $result['profile_photo'] ?? '';
                    }
                } catch (PDOException $e) {
                    $profilePhoto = '';
                }

                $profileName = trim($_SESSION['full_name'] ?? 'User');
                $profileInitials = '';
                $userRole = $_SESSION['role'] ?? '';
                if ($userRole === ROLE_SUPER_ADMIN) {
                    $profileName = 'Super Admin (Dev)';
                } elseif ($userRole === ROLE_ADMIN) {
                    $profileName = 'Admin';
                } elseif ($profileName === '') {
                    $profileName = 'User';
                }
                $canUseMb = function_exists('mb_substr') && function_exists('mb_strtoupper');
                if ($profileName !== '') {
                    $parts = preg_split('/\s+/', $profileName);
                    if ($parts && count($parts) > 0) {
                        $firstPart = array_shift($parts);
                        $lastPart = count($parts) ? array_pop($parts) : '';
                        $initialFirst = $canUseMb ? mb_strtoupper(mb_substr($firstPart, 0, 1)) : strtoupper(substr($firstPart, 0, 1));
                        $initialSecond = '';
                        if ($lastPart !== '') {
                            $initialSecond = $canUseMb ? mb_strtoupper(mb_substr($lastPart, 0, 1)) : strtoupper(substr($lastPart, 0, 1));
                        } else {
                            $firstLength = function_exists('mb_strlen') && $canUseMb ? mb_strlen($firstPart) : strlen($firstPart);
                            if ($firstLength > 1) {
                                $initialSecond = $canUseMb ? mb_strtoupper(mb_substr($firstPart, 1, 1)) : strtoupper(substr($firstPart, 1, 1));
                            }
                        }
                        $profileInitials = $initialFirst . $initialSecond;
                    }
                }
                if ($profileInitials === '') {
                    $profileInitials = 'U';
                }
                ?>
                <a href="<?php echo e($modulePrefix . 'profile.php'); ?>" class="app-header__profile" title="View profile">
                    <span class="app-header__avatar">
                        <?php 
                        $profilePhotoPath = '';
                        if ($profilePhoto) {
                            // Build the full path to check if file exists
                            $basePath = $is_module ? dirname(__DIR__) : __DIR__;
                            $fullPhotoPath = $basePath . '/' . ltrim($profilePhoto, '/');
                            if (file_exists($fullPhotoPath)) {
                                $profilePhotoPath = ($is_module ? '../' : '') . $profilePhoto;
                            }
                        }
                        ?>
                        <?php if ($profilePhotoPath): ?>
                            <img src="<?php echo e($profilePhotoPath); ?>" alt="Profile photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <span class="app-header__avatar--initials"><?php echo e($profileInitials); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="app-header__profile-meta">
                        <span class="app-header__profile-name"><?php echo e($profileName); ?></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN): ?>
                            <span style="display: block; font-size: 10px; color: #f59e0b; font-weight: 600; text-transform: uppercase; margin-top: 2px;">
                                ⚠️ Dev Mode
                            </span>
                        <?php endif; ?>
                    </span>
                </a>

                <a href="<?php echo e($is_module ? '../' : ''); ?>logout.php"
                   class="app-header__icon-btn app-header__icon-btn--danger app-header__icon-btn--sm"
                   title="Sign out">
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span class="sr-only">Logout</span>
                </a>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="main-nav app-header__nav" id="mainNav" role="navigation" aria-label="Primary" aria-hidden="true">
            <div class="container-fluid">
                <div class="nav-container app-header__nav-items">
                    <?php foreach ($navItems as $item): ?>
                        <?php if (!$accessControl->shouldDisplayNav($item['permission'])) {
                            continue;
                        } ?>
                        <?php $isActive = abbis_nav_is_active($item['match'] ?? [], $current_page, $current_dir); ?>
                        <?php $iconMarkup = abbis_render_nav_icon($item['icon'] ?? null); ?>
                        <?php $isExternal = isset($item['external']) && $item['external']; ?>
                        <a href="<?php echo e($item['href']); ?>"
                           class="nav-item<?php echo $isActive ? ' active' : ''; ?><?php echo $iconMarkup ? '' : ' nav-item--text'; ?>"
                           <?php if ($isExternal): ?>
                               target="_blank"
                               rel="noopener noreferrer"
                           <?php endif; ?>>
                            <?php if ($iconMarkup): ?>
                                <span class="nav-item__icon"><?php echo $iconMarkup; ?></span>
                            <?php endif; ?>
                            <span class="nav-item__label"><?php echo e($item['label']); ?></span>
                            <?php if ($isExternal): ?>
                                <svg aria-hidden="true" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-left: 4px; opacity: 0.6;">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </nav>
    </header>
    <div class="mobile-nav-backdrop" id="mobileNavBackdrop" aria-hidden="true"></div>

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

    <!-- AI Assistant Panel (available to all authenticated users) -->
    <?php if (isset($_SESSION['user_id']) && $auth->isLoggedIn()): ?>
        <?php
        // Set default context if not already set by the page
        if (!isset($aiContext)) {
            $aiContext = [
                'entity_type' => '',
                'entity_id' => '',
                'entity_label' => 'ABBIS System',
            ];
        }
        
        // Set default quick prompts if not already set by the page
        if (!isset($aiQuickPrompts)) {
            $aiQuickPrompts = [
                'Give me today\'s top three priorities',
                'Explain anomalies in the latest field reports',
                'What should I brief leadership about tomorrow?',
            ];
        }
        
        require_once __DIR__ . '/ai-assistant-panel.php';
        // Don't unset - let pages override if needed
        ?>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="main-content">
        <div class="container-fluid">
