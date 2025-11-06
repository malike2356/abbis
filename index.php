<?php
/**
 * ABBIS v3.2 - Main Entry Point
 * Routes to CMS (if enabled) or Dashboard/Login
 */
require_once 'config/app.php';
require_once 'includes/functions.php';

// Check if CMS is enabled
$cmsEnabled = isFeatureEnabled('cms');

if ($cmsEnabled) {
    // CMS is enabled - redirect to CMS homepage
    $baseUrl = '/abbis3.2';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '/abbis3.2';
    }
    header('Location: ' . $baseUrl . '/cms/');
    exit;
}

// CMS disabled - use original behavior
require_once 'includes/auth.php';

if (isset($auth) && $auth->isLoggedIn()) {
    header('Location: modules/dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
?>

