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
    
    $logoPath = $cmsSettings['site_logo'] ?? '';
    $logoUrl = '';
    if (!empty($logoPath)) {
        if (preg_match('#^https?://#i', $logoPath)) {
            $logoUrl = $logoPath;
        } else {
            $logoUrl = rtrim(app_url(), '/') . '/' . ltrim($logoPath, '/');
        }
    }
    
    // Get theme colors from settings
    $themeColorKey = $cmsSettings['cms_theme_color'] ?? 'blue';
    $customColor = $cmsSettings['cms_custom_primary_color'] ?? '';
    
    // Theme color definitions
    $themeColors = [
        'blue' => '#2563eb',
        'red' => '#dc2626',
        'green' => '#16a34a',
        'purple' => '#9333ea',
        'orange' => '#ea580c',
        'teal' => '#0d9488',
        'pink' => '#db2777',
        'indigo' => '#4f46e5',
    ];
    
    // Get primary color
    $primaryColor = !empty($customColor) ? $customColor : ($themeColors[$themeColorKey] ?? '#2563eb');
    
    // Helper function to darken color
    function darkenColor($hex, $percent = 20) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    
    // Helper function to lighten color
    function lightenColor($hex, $percent = 20) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = min(255, $r + (255 - $r) * $percent / 100);
        $g = min(255, $g + (255 - $g) * $percent / 100);
        $b = min(255, $b + (255 - $b) * $percent / 100);
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    
    // Calculate color variations
    $primaryDark = darkenColor($primaryColor, 25);
    $primaryDarker = darkenColor($primaryColor, 40);
    $primaryLight = lightenColor($primaryColor, 30);
    $primaryLighter = lightenColor($primaryColor, 50);
    
    // Helper function to convert hex to rgba
    function hexToRgba($hex, $alpha = 1) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }
    
    // Calculate rgba versions for shadows
    $primaryColorRgba40 = hexToRgba($primaryColor, 0.4);
    $primaryColorRgba10 = hexToRgba($primaryColor, 0.1);
    $primaryColorRgba05 = hexToRgba($primaryColor, 0.05);
    
    // Get site name from CMS settings (backend) - use consistent helper
    // This ensures consistency with public pages
    require_once dirname(__DIR__) . '/public/get-site-name.php';
    if (!isset($companyName) || empty($companyName)) {
        $companyName = getCMSSiteName('CMS Admin');
    }
    $baseUrl = app_base_path();
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');

    if (!function_exists('cms_nav_icon')) {
        function cms_nav_icon(?string $name): string
        {
            if (!$name) {
                return '';
            }

            $commonAttributes = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';

            switch ($name) {
                case 'dashboard':
                    return '<svg ' . $commonAttributes . '><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
                case 'pages':
                    return '<svg ' . $commonAttributes . '><path d="M6 3h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><polyline points="13 3 13 9 19 9"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="14" y2="17"></line></svg>';
                case 'posts':
                    return '<svg ' . $commonAttributes . '><path d="M11 4h9v16l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2h5z"></path><path d="M8 13h10"></path><path d="M8 9h10"></path></svg>';
                case 'categories':
                    return '<svg ' . $commonAttributes . '><path d="M7 7h.01"></path><path d="M3 7l9-4 9 4-9 4-9-4z"></path><path d="M21 7v10l-9 4-9-4V7"></path><path d="M3 17l9-4 9 4"></path></svg>';
                case 'media':
                    return '<svg ' . $commonAttributes . '><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="M21 15l-3.5-3.5a2 2 0 0 0-3 0L9 17"></path></svg>';
                case 'portfolio':
                    return '<svg ' . $commonAttributes . '><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>';
                case 'products':
                    return '<svg ' . $commonAttributes . '><path d="M12 2l8 4-8 4-8-4 8-4z"></path><path d="M4 6v10l8 4 8-4V6"></path><path d="M12 10v12"></path></svg>';
                case 'orders':
                    return '<svg ' . $commonAttributes . '><path d="M4 3h16v18l-3-2-3 2-3-2-3 2-3-2z"></path><path d="M8 8h8"></path><path d="M8 12h8"></path></svg>';
                case 'coupons':
                    return '<svg ' . $commonAttributes . '><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a3 3 0 0 0 0 6v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a3 3 0 0 0 0-6z"></path><path d="M8 6h.01"></path><path d="M16 18h.01"></path></svg>';
                case 'estimates':
                    return '<svg ' . $commonAttributes . '><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M9 10h6"></path><path d="M9 14h6"></path></svg>';
                case 'rig':
                    return '<svg ' . $commonAttributes . '><path d="M3 17V6a2 2 0 0 1 2-2h8v13"></path><path d="M13 8h4l3 3v6"></path><circle cx="7.5" cy="17.5" r="1.5"></circle><circle cx="17.5" cy="17.5" r="1.5"></circle></svg>';
                case 'complaints':
                    return '<svg ' . $commonAttributes . '><path d="M3 11V6a2 2 0 0 1 2-2h1"></path><path d="M11 4l9-2v20l-9-2V4z"></path><path d="M11 12a3 3 0 0 0 0 6"></path></svg>';
                case 'recruitment':
                    return '<svg ' . $commonAttributes . '><path d="M4 7h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"></path><path d="M9 7V5a3 3 0 0 1 3-3h0a3 3 0 0 1 3 3v2"></path><path d="M2 11h20"></path></svg>';
                case 'comments':
                    return '<svg ' . $commonAttributes . '><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
                case 'legal':
                    return '<svg ' . $commonAttributes . '><path d="M6 3L3 9h6L6 3z"></path><path d="M18 3l-3 6h6l-3-6z"></path><path d="M6 9c0 4 3 7 6 7s6-3 6-7"></path><path d="M12 5v17"></path></svg>';
                case 'appearance':
                    return '<svg ' . $commonAttributes . '><path d="M13.9 4.1A9 9 0 0 0 3 12a9 9 0 0 0 9 9h1a2 2 0 0 0 2-2 1.5 1.5 0 0 1 1.5-1.5c.83 0 1.5-.67 1.5-1.5v-.5a2 2 0 0 0-2-2h-1.5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h.4z"></path><circle cx="6.5" cy="11.5" r="1"></circle><circle cx="9.5" cy="7.5" r="1"></circle><circle cx="15.5" cy="7.5" r="1"></circle><circle cx="12.5" cy="13.5" r="1"></circle></svg>';
                case 'menus':
                    return '<svg ' . $commonAttributes . '><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg>';
                case 'widgets':
                    return '<svg ' . $commonAttributes . '><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>';
                case 'views':
                    return '<svg ' . $commonAttributes . '><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                case 'users':
                    return '<svg ' . $commonAttributes . '><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                case 'settings':
                    return '<svg ' . $commonAttributes . '><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
                case 'plugins':
                    return '<svg ' . $commonAttributes . '><path d="M14 4h6v6"></path><path d="M8 20H2v-6"></path><path d="M14 10l-8 8"></path><path d="M6 4l4 4"></path></svg>';
                case 'languages':
                    return '<svg ' . $commonAttributes . '><circle cx="12" cy="12" r="9"></circle><path d="M3.6 9h16.8"></path><path d="M3.6 15h16.8"></path><path d="M12 3a15.3 15.3 0 0 1 0 18"></path><path d="M12 3a15.3 15.3 0 0 0 0 18"></path></svg>';
                case 'acl':
                    return '<svg ' . $commonAttributes . '><path d="M12 22s8-4 8-10V8a8 8 0 0 0-16 0v4c0 6 8 10 8 10z"></path><path d="M12 11v4"></path><path d="M12 7h.01"></path></svg>';
                case 'content':
                    return '<svg ' . $commonAttributes . '><path d="M8 17l4 4 4-4"></path><path d="M12 12v9"></path><path d="M20 8l-8-4-8 4 8 4 8-4z"></path><path d="M4 12l8 4 8-4"></path></svg>';
                case 'taxonomy':
                    return '<svg ' . $commonAttributes . '><circle cx="12" cy="8" r="3"></circle><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="18" r="3"></circle><path d="M12 11v5"></path><path d="M6 15l2-2"></path><path d="M18 15l-2-2"></path></svg>';
                case 'module':
                    return '<svg ' . $commonAttributes . '><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>';
                case 'api':
                    return '<svg ' . $commonAttributes . '><path d="M3 21v-2a4 4 0 0 1 4-4h3"></path><circle cx="13" cy="7" r="3"></circle><path d="M13 10v4"></path><path d="M21 3l-7 7"></path></svg>';
                default:
                    return '<svg ' . $commonAttributes . '><circle cx="12" cy="12" r="4"></circle></svg>';
            }
        }
    }

    $navSections = [
        'Overview' => [
            ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'dashboard', 'match' => ['index']],
        ],
        'Content' => [
            ['href' => 'pages.php', 'label' => 'Pages', 'icon' => 'pages', 'match' => ['pages']],
            ['href' => 'posts.php', 'label' => 'Posts', 'icon' => 'posts', 'match' => ['posts']],
            ['href' => 'categories.php', 'label' => 'Categories', 'icon' => 'categories', 'match' => ['categories']],
            ['href' => 'media.php', 'label' => 'Media Library', 'icon' => 'media', 'match' => ['media']],
            ['href' => 'portfolio.php', 'label' => 'Portfolio', 'icon' => 'portfolio', 'match' => ['portfolio']],
        ],
        'Commerce & Service' => [
            ['href' => 'products.php', 'label' => 'Products', 'icon' => 'products', 'match' => ['products']],
            ['href' => 'orders.php', 'label' => 'Orders', 'icon' => 'orders', 'match' => ['orders']],
            ['href' => 'coupons.php', 'label' => 'Coupons', 'icon' => 'coupons', 'match' => ['coupons']],
            ['href' => 'quotes.php', 'label' => 'Estimates', 'icon' => 'estimates', 'match' => ['quotes']],
            ['href' => 'rig-requests.php', 'label' => 'Rig Requests', 'icon' => 'rig', 'match' => ['rig-requests']],
            ['href' => 'complaints.php', 'label' => 'Complaints', 'icon' => 'complaints', 'match' => ['complaints']],
        ],
        'People & Engagement' => [
            ['href' => 'recruitment.php', 'label' => 'Recruitment', 'icon' => 'recruitment', 'match' => ['recruitment']],
            ['href' => 'comments.php', 'label' => 'Comments', 'icon' => 'comments', 'match' => ['comments']],
            ['href' => 'legal-documents.php', 'label' => 'Legal Docs', 'icon' => 'legal', 'match' => ['legal-documents']],
        ],
        'Experience' => [
            ['href' => 'appearance.php', 'label' => 'Appearance', 'icon' => 'appearance', 'match' => ['appearance']],
            ['href' => 'menus.php', 'label' => 'Menus', 'icon' => 'menus', 'match' => ['menus']],
            ['href' => 'widgets.php', 'label' => 'Widgets', 'icon' => 'widgets', 'match' => ['widgets']],
            ['href' => 'views.php', 'label' => 'Views', 'icon' => 'views', 'match' => ['views']],
        ],
        'Administration' => [
            ['href' => 'users.php', 'label' => 'Users', 'icon' => 'users', 'match' => ['users']],
            ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings', 'match' => ['settings']],
            ['href' => 'plugins.php', 'label' => 'Plugins', 'icon' => 'plugins', 'match' => ['plugins']],
            ['href' => 'languages.php', 'label' => 'Languages', 'icon' => 'languages', 'match' => ['languages']],
            ['href' => 'acl.php', 'label' => 'Access Control', 'icon' => 'acl', 'match' => ['acl']],
            ['href' => 'content-types.php', 'label' => 'Content Types', 'icon' => 'content', 'match' => ['content-types']],
            ['href' => 'taxonomy.php', 'label' => 'Taxonomy', 'icon' => 'taxonomy', 'match' => ['taxonomy']],
            ['href' => 'module-positions.php', 'label' => 'Module Positions', 'icon' => 'module', 'match' => ['module-positions']],
            ['href' => 'api-keys.php', 'label' => 'API Keys', 'icon' => 'api', 'match' => ['api-keys']],
        ],
    ];

    $abbisLink = null;
    $abbisLoginRequired = false;
    if ($cmsAuth->isAdmin()) {
        require_once $rootPath . '/includes/sso.php';
        $sso = new SSO();

        if ($sso->isLoggedIntoABBIS()) {
            $abbisLink = app_url('modules/dashboard.php');
        } else {
            $candidate = $sso->getABBISLoginURL($user['id'], $user['username'], $user['role']);
            if ($candidate) {
                $abbisLink = $candidate;
            } else {
                $abbisLoginRequired = true;
            }
        }
    }
?>
<style>
    :root {
        --cms-header-height: 64px;
        --cms-sidebar-width: 236px;
        --cms-primary: <?php echo $primaryColor; ?>;
        --cms-primary-dark: <?php echo $primaryDark; ?>;
        --cms-primary-light: <?php echo $primaryLight; ?>;
        --cms-surface: #ffffff;
        --cms-surface-muted: #f8fafc;
        --cms-border: #e2e8f0;
        --cms-text: #1f2937;
        --cms-text-muted: #64748b;
    }
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    html, body {
        height: 100%;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: #f6f7fb;
        color: var(--cms-text);
        margin: 0;
        margin-left: var(--cms-sidebar-width);
        padding-top: var(--cms-header-height);
        padding-bottom: 32px;
        transition: margin 0.25s ease, padding 0.25s ease;
    }
    body.sidebar-open {
        overflow: hidden;
    }
    .wrap {
        max-width: none;
        margin: 0;
        padding: 32px 32px 48px;
    }
    @media (max-width: 1024px) {
        body {
            margin-left: 0;
            padding-top: calc(var(--cms-header-height) + 16px);
        }
        .wrap {
            padding: 28px 24px 40px;
        }
    }
    @media (max-width: 640px) {
        .wrap {
            padding: 24px 16px 32px;
        }
    }
    .mobile-menu-toggle {
        position: fixed;
        top: 14px;
        left: 16px;
        z-index: 1101;
        display: none;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid var(--cms-border);
        background: var(--cms-surface);
        color: var(--cms-text);
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
        transition: all 0.2s ease;
    }
    .mobile-menu-toggle:hover,
    .mobile-menu-toggle:focus-visible {
        border-color: var(--cms-primary);
        color: var(--cms-primary);
        outline: none;
    }
    .mobile-sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
        z-index: 1100;
    }
    .mobile-sidebar-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }
    .admin-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--cms-header-height);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 28px 0 32px;
        background: var(--cms-surface);
        border-bottom: 1px solid var(--cms-border);
        z-index: 1102;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .admin-header__brand {
        display: flex;
        align-items: center;
        gap: 14px;
        font-weight: 700;
        color: var(--cms-text);
        letter-spacing: -0.01em;
        font-size: 18px;
    }
    .admin-header__logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        max-height: 40px;
    }
    .admin-header__logo img {
        max-height: 40px;
        width: auto;
        display: block;
    }
    .admin-header__badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.1);
        color: var(--cms-primary-dark);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .admin-header__actions {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: var(--cms-surface);
        padding: 6px;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        font-size: 13px;
    }
    .admin-header__pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 999px;
        text-decoration: none;
        transition: all 0.2s ease;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .admin-header__pill svg {
        width: 16px;
        height: 16px;
    }
    .admin-header__pill--muted {
        background: rgba(37, 99, 235, 0.08);
        color: var(--cms-primary-dark);
    }
    .admin-header__pill--outline {
        background: white;
        color: var(--cms-primary);
        border-color: rgba(37, 99, 235, 0.18);
        box-shadow: 0 6px 18px rgba(37, 99, 235, 0.15);
    }
    .admin-header__pill--neutral {
        background: rgba(148, 163, 184, 0.12);
        color: #334155;
    }
    .admin-header__pill--danger {
        background: rgba(239, 68, 68, 0.12);
        color: #b91c1c;
        border-color: rgba(239, 68, 68, 0.2);
    }
    .admin-header__pill:hover,
    .admin-header__pill:focus-visible {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.15);
    }
    .admin-header__pill--danger:hover,
    .admin-header__pill--danger:focus-visible {
        box-shadow: 0 10px 24px rgba(239, 68, 68, 0.18);
    }
    .admin-header__pill--muted:hover,
    .admin-header__pill--muted:focus-visible {
        color: white;
        background: var(--cms-primary);
    }
    .admin-header__pill--outline:hover,
    .admin-header__pill--outline:focus-visible {
        background: var(--cms-primary);
        color: white;
    }
    .admin-header__pill--neutral:hover,
    .admin-header__pill--neutral:focus-visible {
        background: rgba(37, 99, 235, 0.12);
        color: var(--cms-primary);
    }
    .admin-header__user {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        background: rgba(37, 99, 235, 0.08);
        color: var(--cms-primary-dark);
        border-radius: 999px;
        font-weight: 600;
    }
    .admin-header__logout {
        background: rgba(239, 68, 68, 0.12) !important;
        color: #b91c1c !important;
        border-color: rgba(239, 68, 68, 0.15) !important;
    }
    .admin-header__note {
        font-size: 12px;
        padding: 7px 12px;
        border-radius: 8px;
        border: 1px solid rgba(239, 68, 68, 0.25);
        background: rgba(239, 68, 68, 0.1);
        color: #b91c1c;
        font-weight: 500;
    }
    .admin-sidebar {
        position: fixed;
        top: var(--cms-header-height);
        left: 0;
        bottom: 0;
        width: var(--cms-sidebar-width);
        background: #0f172a;
        color: #e2e8f0;
        padding: 28px 18px 32px;
        overflow-y: auto;
        box-shadow: 12px 0 24px rgba(15, 23, 42, 0.18);
        transition: transform 0.3s ease;
        z-index: 1101;
    }
    .admin-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .admin-sidebar::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 999px;
    }
    .admin-sidebar__group + .admin-sidebar__group {
        margin-top: 24px;
    }
    .admin-sidebar__group-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 12px;
        color: rgba(226, 232, 240, 0.6);
        padding-left: 6px;
    }
    .admin-sidebar__link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        margin-bottom: 6px;
        border-radius: 10px;
        color: rgba(226, 232, 240, 0.85);
        text-decoration: none;
        transition: all 0.2s ease;
        font-weight: 500;
    }
    .admin-sidebar__link:hover,
    .admin-sidebar__link:focus-visible {
        background: rgba(148, 163, 184, 0.22);
        color: #ffffff;
        outline: none;
        transform: translateX(4px);
    }
    .admin-sidebar__link-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .admin-sidebar__link-icon svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
    }
    .admin-sidebar__link.active {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.28) 0%, rgba(37, 99, 235, 0.52) 100%);
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
    }
    .admin-sidebar__link.active .admin-sidebar__link-icon {
        color: #ffffff;
    }
    .admin-sidebar__link span {
        font-size: 13px;
    }
    @media (max-width: 1200px) {
        .admin-header {
            padding: 0 20px 0 68px;
        }
    }
    @media (max-width: 1024px) {
        .mobile-menu-toggle {
            display: inline-flex;
        }
        .admin-sidebar {
            transform: translateX(-100%);
        }
        .admin-sidebar.is-open {
            transform: translateX(0);
        }
    }
    @media (min-width: 1025px) {
        .mobile-sidebar-overlay {
            display: none;
        }
    }
</style>
    <link rel="stylesheet" href="admin-styles.css">
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle navigation">☰</button>
    <div class="mobile-sidebar-overlay" id="mobile-sidebar-overlay" onclick="toggleMobileMenu()"></div>
    <header class="admin-header">
        <div class="admin-header__brand">
            <?php if (!empty($logoUrl)): ?>
                <span class="admin-header__logo">
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($companyName); ?> logo">
                </span>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($companyName); ?></span>
            <span class="admin-header__badge">CMS</span>
        </div>
        <div class="admin-header__actions">
            <span class="admin-header__pill admin-header__pill--muted admin-header__user">
                <span><?php echo htmlspecialchars($user['username']); ?></span>
            </span>
            <a href="profile.php" class="admin-header__pill admin-header__pill--neutral">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>My Profile</span>
            </a>
            <a href="<?php echo $baseUrl; ?>/cms/" target="_blank" rel="noopener" class="admin-header__pill admin-header__pill--outline">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12V5a2 2 0 0 1 2-2h7"></path><path d="M12 12l10-10"></path><path d="M15 3h7v7"></path></svg>
                <span>View Site</span>
            </a>
            <?php if ($cmsAuth->isAdmin()): ?>
                <?php if ($abbisLink): ?>
                    <a href="<?php echo htmlspecialchars($abbisLink); ?>" target="_blank" rel="noopener" class="admin-header__pill admin-header__pill--neutral">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.5 15.5 0 0 0 0 20"></path><path d="M12 2a15.5 15.5 0 0 1 0 20"></path></svg>
                        <span>ABBIS</span>
                    </a>
                <?php elseif ($abbisLoginRequired): ?>
                    <span class="admin-header__note">Log in to ABBIS to sync</span>
                <?php endif; ?>
            <?php endif; ?>
            <a href="logout.php" class="admin-header__pill admin-header__pill--danger admin-header__logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path></svg>
                <span>Logout</span>
            </a>
        </div>
    </header>
    <aside class="admin-sidebar" id="admin-sidebar">
        <?php foreach ($navSections as $sectionLabel => $links): ?>
            <div class="admin-sidebar__group">
                <p class="admin-sidebar__group-title"><?php echo htmlspecialchars($sectionLabel); ?></p>
                <?php foreach ($links as $link): ?>
                    <?php
                        $matchList = $link['match'] ?? [];
                        $isActive = in_array($currentPage, $matchList, true);
                        $isExternal = !empty($link['external']);
                        $linkTarget = $isExternal ? ' target="_blank" rel="noopener"' : '';
                    ?>
                    <a href="<?php echo htmlspecialchars($link['href']); ?>"
                       class="admin-sidebar__link<?php echo $isActive ? ' active' : ''; ?>"
                       <?php echo $linkTarget; ?>>
                        <span class="admin-sidebar__link-icon"><?php echo cms_nav_icon($link['icon'] ?? null); ?></span>
                        <span><?php echo htmlspecialchars($link['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </aside>
    
    <!-- Media Picker Script -->
    <script src="<?php echo $baseUrl; ?>/cms/admin/media-picker.js"></script>
<?php } // End HEADER_OUTPUT check ?>
