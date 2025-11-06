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
$menuStmt = $pdo->query("SELECT * FROM cms_menu_items WHERE menu_type='primary' ORDER BY menu_order");
$menuItems = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

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

// Generate base URL for links
// More robust base URL detection
$baseUrl = '';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '';
    // Remove trailing slash except for root
    if ($baseUrl !== '/' && substr($baseUrl, -1) === '/') {
        $baseUrl = rtrim($baseUrl, '/');
    }
}

// If still empty, try to detect from SCRIPT_NAME
if (!$baseUrl) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($scriptName) {
        // Handle both cases: /abbis3.2/index.php and /abbis3.2/cms/public/index.php
        $parts = explode('/', trim($scriptName, '/'));
        
        // Find the project root (abbis3.2)
        foreach ($parts as $idx => $part) {
            if ($part === 'cms' && $idx > 0) {
                $baseUrl = '/' . $parts[$idx - 1];
                break;
            }
        }
        
        // Fallback: if accessed via root index.php, get first part
        if (!$baseUrl && count($parts) > 0 && $parts[0] !== 'index.php' && $parts[0] !== '') {
            $baseUrl = '/' . $parts[0];
        }
    }
}

// Final fallback: extract from file path
if (!$baseUrl && defined('ROOT_PATH')) {
    $rootParts = explode('/', trim(str_replace('\\', '/', ROOT_PATH), '/'));
    $htdocsIdx = array_search('htdocs', $rootParts);
    if ($htdocsIdx !== false && isset($rootParts[$htdocsIdx + 1])) {
        $baseUrl = '/' . $rootParts[$htdocsIdx + 1];
    }
}

// Ensure baseUrl is set (default to /abbis3.2 if all detection fails)
if (!$baseUrl) {
    $baseUrl = '/abbis3.2'; // Default fallback
}

// Include theme template
$themePath = __DIR__ . '/../themes/' . ($theme['slug'] ?? 'default') . '/index.php';
if (file_exists($themePath)) {
    include $themePath;
} else {
    // Fallback default theme
    include __DIR__ . '/../themes/default/index.php';
}

