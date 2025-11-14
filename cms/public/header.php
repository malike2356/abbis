<?php
/**
 * CMS Public Header - shared across all public-facing pages
 */
if (!defined('CMS_PUBLIC_HEADER_BOOTSTRAPPED')) {
    define('CMS_PUBLIC_HEADER_BOOTSTRAPPED', true);

    $rootPath = dirname(dirname(__DIR__));
    require_once $rootPath . '/config/app.php';
    require_once $rootPath . '/includes/functions.php';
    require_once __DIR__ . '/base-url.php';
    require_once __DIR__ . '/menu-functions.php';
    require_once __DIR__ . '/get-site-name.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = getDBConnection();
    }

    // Determine active theme
    $activeThemeSlug = 'default';
    $activeThemeBaseUrl = '';
    $activeThemeDir = dirname(__DIR__) . '/themes';
    try {
        $themeStmt = $pdo->query("SELECT slug FROM cms_themes WHERE is_active=1 LIMIT 1");
        $activeTheme = $themeStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($activeTheme['slug'])) {
            $activeThemeSlug = $activeTheme['slug'];
        }
    } catch (Throwable $e) {
        // ignore
    }

    $baseThemeDir = $activeThemeDir . '/' . $activeThemeSlug;
    $activeThemeAssetsUrl = '';

    // Load CMS settings (site title, logo, tagline, contact info, CTA)
    $cmsSettings = $cmsSettings ?? [];
    try {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            $cmsSettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        // Ignore and continue with defaults
    }

    // Load system config fallbacks (company name, logo, contact)
    $systemConfig = [];
    try {
        $configStmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('company_name','company_logo','company_phone','company_email','company_tagline')");
        while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
            $systemConfig[$row['config_key']] = $row['config_value'];
        }
    } catch (Throwable $e) {
        // Ignore if table missing
    }

    $baseUrl = rtrim($baseUrl ?? app_base_path(), '/');
    $companyName = getCMSSiteName($systemConfig['company_name'] ?? 'ABBIS');
    $tagline = $cmsSettings['site_tagline'] ?? ($systemConfig['company_tagline'] ?? 'Advanced Borehole Business Intelligence System');

    // Determine logo URL (prefer CMS setting, fallback to system config)
    $logoCandidate = $cmsSettings['site_logo'] ?? ($systemConfig['company_logo'] ?? '');
    $logoUrl = '';
    if (!empty($logoCandidate)) {
        if (filter_var($logoCandidate, FILTER_VALIDATE_URL)) {
            $logoUrl = $logoCandidate;
        } else {
            $logoUrl = $baseUrl . '/' . ltrim($logoCandidate, '/');
        }
    }

    $contactPhone = $cmsSettings['contact_phone'] ?? ($systemConfig['company_phone'] ?? '');
    $contactEmail = $cmsSettings['contact_email'] ?? ($systemConfig['company_email'] ?? '');
    $ctaLabelSetting = trim($cmsSettings['header_cta_label'] ?? '');
    $ctaUrlSetting = trim($cmsSettings['header_cta_url'] ?? '');
    $ctaLabel = $ctaLabelSetting !== '' ? $ctaLabelSetting : null;
    $ctaUrl = $ctaUrlSetting !== '' ? $ctaUrlSetting : null;

    $profileUrl = normaliseMenuUrl('/cms/profile', $baseUrl);
    $adminLoginUrl = normaliseMenuUrl('/cms/admin/', $baseUrl);
    $cartUrl = normaliseMenuUrl('/cms/cart', $baseUrl);

    if ($activeThemeSlug !== '') {
        $activeThemeAssetsUrl = rtrim($baseUrl, '/') . '/cms/themes/' . $activeThemeSlug . '/assets';
    }

    // Theme color handling for frontend
    $themeColorKey = $cmsSettings['cms_theme_color'] ?? 'blue';
    $customColor = trim($cmsSettings['cms_custom_primary_color'] ?? '');
    $applyThemeFrontend = ($cmsSettings['cms_apply_theme_frontend'] ?? '1') === '1';
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

    if (!function_exists('cms_lighten_color')) {
        function cms_lighten_color(string $hex, int $percent = 20): string
        {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $percent = max(0, min(100, $percent));
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $r = (int) min(255, $r + (255 - $r) * $percent / 100);
            $g = (int) min(255, $g + (255 - $g) * $percent / 100);
            $b = (int) min(255, $b + (255 - $b) * $percent / 100);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }
    }

    if (!function_exists('cms_darken_color')) {
        function cms_darken_color(string $hex, int $percent = 20): string
        {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $percent = max(0, min(100, $percent));
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $r = (int) max(0, $r - ($r * $percent / 100));
            $g = (int) max(0, $g - ($g * $percent / 100));
            $b = (int) max(0, $b - ($b * $percent / 100));
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }
    }

    if (!function_exists('cms_hex_to_rgba')) {
        function cms_hex_to_rgba(string $hex, float $alpha = 1.0): string
        {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $alpha = max(0, min(1, $alpha));
            return sprintf('rgba(%d, %d, %d, %.3f)', $r, $g, $b, $alpha);
        }
    }

    $primaryColor = $customColor !== '' ? $customColor : ($themeColors[$themeColorKey] ?? '#2563eb');
    $primaryDark = cms_darken_color($primaryColor, 25);
    $primaryDarker = cms_darken_color($primaryColor, 40);
    $primaryLight = cms_lighten_color($primaryColor, 25);
    $primaryLighter = cms_lighten_color($primaryColor, 50);
    $primaryGradient = 'linear-gradient(90deg, ' . $primaryDarker . ' 0%, ' . $primaryLight . ' 100%)';
    $primaryTint = cms_hex_to_rgba($primaryColor, 0.12);

    // Primary navigation menu (location: primary)
    $primaryMenuItems = [];
    try {
        $primaryMenuItems = getMenuItemsForLocation('primary', $pdo);
    } catch (Throwable $e) {
        $primaryMenuItems = [];
    }

    // Fallback menu if none configured
    if (empty($primaryMenuItems)) {
        $primaryMenuItems = [
            ['id' => 'menu-home', 'parent_id' => null, 'label' => 'Home', 'url' => '/', 'css_class' => ''],
            ['id' => 'menu-shop', 'parent_id' => null, 'label' => 'Shop', 'url' => '/cms/shop', 'css_class' => ''],
            ['id' => 'menu-blog', 'parent_id' => null, 'label' => 'Blog', 'url' => '/cms/blog', 'css_class' => ''],
            ['id' => 'menu-contact', 'parent_id' => null, 'label' => 'Contact', 'url' => '/cms/contact', 'css_class' => '']
        ];
    }

    $GLOBALS['cmsPublicHeaderData'] = [
        'companyName' => $companyName,
        'tagline' => $tagline,
        'logoUrl' => $logoUrl,
        'baseUrl' => $baseUrl,
        'contactPhone' => $contactPhone,
        'contactEmail' => $contactEmail,
        'ctaLabel' => $ctaLabel,
        'ctaUrl' => $ctaUrl,
        'menuItems' => $primaryMenuItems
    ];
}

$headerData = $GLOBALS['cmsPublicHeaderData'] ?? [];
$companyName = $headerData['companyName'] ?? 'ABBIS';
$tagline = $headerData['tagline'] ?? '';
$logoUrl = $headerData['logoUrl'] ?? '';
$baseUrl = $headerData['baseUrl'] ?? app_base_path();
$contactPhone = $headerData['contactPhone'] ?? '';
$contactEmail = $headerData['contactEmail'] ?? '';
$ctaLabel = trim($headerData['ctaLabel'] ?? '');
$ctaUrl = trim($headerData['ctaUrl'] ?? '');
$ctaText = $ctaLabel;
$ctaHref = $ctaUrl !== '' ? normaliseMenuUrl($ctaUrl, $baseUrl) : '';
$menuItems = $headerData['menuItems'] ?? [];

$assetBaseUrl = rtrim($baseUrl, '/') . '/cms/public/assets';
$headerCssPath = __DIR__ . '/assets/css/global-header.css';
$headerJsPath = __DIR__ . '/assets/js/global-header.js';
$headerInitial = 'A';
$sanitisedName = preg_replace('/[^A-Za-z0-9]/', '', $companyName);
if (!empty($sanitisedName)) {
    $headerInitial = strtoupper(substr($sanitisedName, 0, 1));
}

if (!defined('CMS_PUBLIC_HEADER_STYLES_OUTPUT')) {
    define('CMS_PUBLIC_HEADER_STYLES_OUTPUT', true);
    if (is_file($headerCssPath)) {
        $css = @file_get_contents($headerCssPath);
        if ($css !== false) {
            echo '<style data-origin="cms-global-header">';
            echo $css;
            echo '</style>';
        }
    }

    $themeHeaderCssPath = $baseThemeDir . '/assets/css/header.css';
    if (is_file($themeHeaderCssPath) && $activeThemeAssetsUrl !== '') {
        $headerCssVersion = (string)@filemtime($themeHeaderCssPath);
        echo '<link rel="stylesheet" href="' . htmlspecialchars($activeThemeAssetsUrl . '/css/header.css?v=' . $headerCssVersion) . '" data-origin="cms-theme-header">';
    }

    if ($applyThemeFrontend) {
        echo '<style data-origin="cms-theme-colors">';
        echo ':root{';
        echo '--cms-header-accent:' . htmlspecialchars($primaryColor) . ';';
        echo '--cms-header-accent-dark:' . htmlspecialchars($primaryDark) . ';';
        echo '--cms-header-topbar-bg:' . htmlspecialchars($primaryGradient) . ';';
        echo '--cms-header-shadow:0 18px 40px ' . htmlspecialchars(cms_hex_to_rgba($primaryColor, 0.18)) . ';';
        echo '--cms-header-border:' . htmlspecialchars(cms_hex_to_rgba($primaryColor, 0.22)) . ';';
        echo '}';
        echo '.cms-btn-primary, .btn-primary, button.primary, .primary-button{background:' . htmlspecialchars($primaryColor) . ';border-color:' . htmlspecialchars($primaryDark) . ';color:#fff;}';
        echo '.cms-btn-primary:hover, .btn-primary:hover, button.primary:hover, .primary-button:hover{background:' . htmlspecialchars($primaryDark) . ';border-color:' . htmlspecialchars($primaryDarker) . ';}';
        echo 'a, .link-primary{color:' . htmlspecialchars($primaryColor) . ';}';
        echo 'a:hover, a:focus, .link-primary:hover{color:' . htmlspecialchars($primaryDark) . ';}';
        echo '.cms-brand__logo{background:' . htmlspecialchars($primaryTint) . ';}';
        echo '.cms-header-topbar__cta a{background:' . htmlspecialchars(cms_hex_to_rgba($primaryColor, 0.18)) . ';}';
        echo '.cms-header-topbar__cta a:hover{background:' . htmlspecialchars(cms_hex_to_rgba($primaryColor, 0.28)) . ';}';
        echo '.cms-hero-button, .cms-cta-button{background:' . htmlspecialchars($primaryColor) . '; border-color:' . htmlspecialchars($primaryDark) . '; color:#fff;}';
        echo '.cms-hero-button:hover, .cms-cta-button:hover{background:' . htmlspecialchars($primaryDark) . '; border-color:' . htmlspecialchars($primaryDarker) . ';}';
        echo '</style>';
    }
}

if (!defined('CMS_PUBLIC_HEADER_SCRIPTS_OUTPUT')) {
    define('CMS_PUBLIC_HEADER_SCRIPTS_OUTPUT', true);
    if (is_file($headerJsPath)) {
        $version = (string)@filemtime($headerJsPath);
        echo '<script defer src="' . htmlspecialchars($assetBaseUrl . '/js/global-header.js?v=' . $version) . '"></script>';
    }

    $themeHeaderJsPath = $baseThemeDir . '/assets/js/header.js';
    if (is_file($themeHeaderJsPath) && $activeThemeAssetsUrl !== '') {
        $headerJsVersion = (string)@filemtime($themeHeaderJsPath);
        echo '<script defer src="' . htmlspecialchars($activeThemeAssetsUrl . '/js/header.js?v=' . $headerJsVersion) . '"></script>';
    }
}

if (!defined('CMS_PUBLIC_HEADER_RENDERED')) {
    define('CMS_PUBLIC_HEADER_RENDERED', true);
    ?>
    <script>
    (function () {
        function resolveExtra(main) {
            if (!main) {
                return window.innerWidth < 768 ? 24 : 16;
            }
            if (main.dataset && main.dataset.offsetExtra !== undefined) {
                var parsed = parseInt(main.dataset.offsetExtra, 10);
                if (!isNaN(parsed)) {
                    return parsed;
                }
            }
            return window.innerWidth < 768 ? 24 : 16;
        }

        function updateLayoutOffset() {
            var header = document.querySelector('.cms-global-header');
            if (!header) {
                return;
            }

            var main = document.querySelector('main.cms-site-main, main[role=\"main\"], main');
            var headerHeight = Math.ceil(header.getBoundingClientRect().height);
            var totalOffset = headerHeight + resolveExtra(main);

            document.documentElement.style.setProperty('--cms-body-offset', totalOffset + 'px');
            document.body.style.removeProperty('padding-top');

            if (main) {
                main.style.setProperty('scroll-margin-top', (totalOffset + 16) + 'px');
                if (!main.hasAttribute('data-cms-offset-manual')) {
                    main.style.setProperty('margin-top', totalOffset + 'px');
                } else {
                    main.style.removeProperty('margin-top');
                }
            } else {
                document.body.style.setProperty('padding-top', totalOffset + 'px');
            }
        }

        window.addEventListener('load', updateLayoutOffset);
        window.addEventListener('resize', updateLayoutOffset);
        document.addEventListener('DOMContentLoaded', updateLayoutOffset);
        updateLayoutOffset();
    })();
    </script>
    <header class="cms-global-header" role="banner" data-header data-base="<?php echo htmlspecialchars($baseUrl); ?>">
        <div class="cms-header-inner">
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/" class="cms-brand">
                <span class="cms-brand__logo" aria-hidden="true">
                    <?php if ($logoUrl): ?>
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="">
                    <?php else: ?>
                        <span><?php echo htmlspecialchars($headerInitial); ?></span>
                    <?php endif; ?>
                </span>
                <span class="cms-brand__identity">
                    <span class="cms-brand__name"><?php echo htmlspecialchars($companyName); ?></span>
                    <?php if ($tagline): ?>
                        <span class="cms-brand__tagline"><?php echo htmlspecialchars($tagline); ?></span>
                    <?php endif; ?>
                </span>
            </a>
            <nav class="cms-nav" id="cmsPrimaryNav" role="navigation" aria-label="Primary navigation" tabindex="-1">
                <div class="cms-nav-menu">
                    <?php echo renderMenuItems($menuItems, null, 0, 'header'); ?>
                </div>
                <?php if ($contactPhone || $contactEmail): ?>
                <div class="cms-header-actions__contact">
                    <?php if ($contactPhone): ?>
                        <a class="cms-header-contact-link" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $contactPhone)); ?>">
                            <span aria-hidden="true">📞</span>
                            <span><?php echo htmlspecialchars($contactPhone); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($contactEmail): ?>
                        <a class="cms-header-contact-link" href="mailto:<?php echo htmlspecialchars($contactEmail); ?>">
                            <span aria-hidden="true">✉️</span>
                            <span><?php echo htmlspecialchars($contactEmail); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </nav>
            <div class="cms-header-actions">
                <?php if ($ctaText !== '' && $ctaHref !== ''): ?>
                <a class="cms-header-actions__cta" href="<?php echo htmlspecialchars($ctaHref); ?>">
                    <span><?php echo htmlspecialchars($ctaText); ?></span>
                </a>
                <?php endif; ?>
                <a class="cms-header-icon" href="<?php echo htmlspecialchars($profileUrl); ?>" aria-label="Customer portal">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zm0 2c-3.866 0-7 2.239-7 5v1h14v-1c0-2.761-3.134-5-7-5z"/>
                    </svg>
                </a>
                <a class="cms-header-icon" href="<?php echo htmlspecialchars($adminLoginUrl); ?>" aria-label="Admin login">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1s-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v3h2V5h14v3h2V5c0-1.1-.9-2-2-2zm-7 4c-3.31 0-6 2.69-6 6s2.69 6 6 6c1.17 0 2.25-.34 3.18-.92l3.37 3.37 1.41-1.41-3.37-3.37c.58-.93.92-2.01.92-3.18 0-3.31-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/>
                    </svg>
                </a>
                <a class="cms-header-icon" href="<?php echo htmlspecialchars($cartUrl); ?>" aria-label="View cart">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm0 2h-.01H7zm10-2c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 2-2-.9-2-2-2zm-9.83-3h9.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 20.58 6H6.21l-.94-2H1v2h2l3.6 7.59-.95 1.72A2 2 0 0 0 5.98 18H19v-2H7.42l.75-1.5z"/>
                    </svg>
                </a>
            </div>
            <button class="cms-nav-toggle" id="cmsNavToggle" aria-expanded="false" aria-controls="cmsPrimaryNav">
                <span></span>
                <span></span>
                <span></span>
                <span class="sr-only">Toggle navigation</span>
            </button>
        </div>
        <div class="cms-nav-backdrop" id="cmsNavBackdrop" aria-hidden="true"></div>
    </header>
    <?php
}

