<?php
/**
 * CMS Public Homepage
 */
// Use absolute paths based on file location
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';

$pdo = getDBConnection();

// Ensure CMS tables exist (self-init)
try { $pdo->query("SELECT 1 FROM cms_pages LIMIT 1"); }
catch (Throwable $e) {
    @include_once $rootPath . '/database/run-sql.php';
    $path = $rootPath . '/database/cms_migration.sql';
    if (function_exists('run_sql_file')) { @run_sql_file($path); }
    else {
        $sql = @file_get_contents($path);
        if ($sql) {
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt) { try { $pdo->exec($stmt); } catch (Throwable $ignored) {} }
            }
        }
    }
}

// Get active theme
$themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
$theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
$themeConfig = json_decode($theme['config'] ?? '{}', true);

// Base URL helper & public header handle navigation consistently
require_once __DIR__ . '/base-url.php';

// Get CMS settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
$cmsSettings = [];
while ($row = $settingsStmt->fetch()) {
    $cmsSettings[$row['setting_key']] = $row['setting_value'];
}

// Get homepage content
$homepageStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE slug='home' AND status='published' LIMIT 1");
$homepageStmt->execute();
$homepage = $homepageStmt->fetch(PDO::FETCH_ASSOC);

// Get recent posts
$postsStmt = $pdo->query("SELECT * FROM cms_posts WHERE status='published' ORDER BY published_at DESC LIMIT 3");
$recentPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get menu items
require_once __DIR__ . '/menu-functions.php';
$menuItems = getMenuItemsForLocation('primary', $pdo);

// Get company config for header
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$companyConfig = [];
while ($row = $configStmt->fetch()) {
    $companyConfig[$row['config_key']] = $row['config_value'];
}

// Get site name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$siteTitle = getCMSSiteName('Our Company');
$siteTagline = $cmsSettings['site_tagline'] ?? $companyConfig['company_tagline'] ?? '';
if (empty($siteTagline) && isset($companyConfig['company_tagline'])) {
    $siteTagline = $companyConfig['company_tagline'];
}

// Include theme template
$themePath = __DIR__ . '/../themes/' . ($theme['slug'] ?? 'default') . '/index.php';
if (file_exists($themePath)) {
    include $themePath;
} else {
    // Fallback default theme
    include __DIR__ . '/../themes/default/index.php';
}

