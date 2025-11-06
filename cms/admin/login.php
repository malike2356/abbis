<?php
/**
 * CMS Admin Login - Redirects to Unified Login
 * Users should use the main ABBIS login.php which handles routing
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';

// Get base URL
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}

// Set redirect after login to CMS
$_SESSION['redirect_after_login'] = $baseUrl . '/cms/admin/index.php';
$_SESSION['cms_login_destination'] = true;

// Redirect to unified login
header('Location: ' . $baseUrl . '/login.php?redirect=cms');
exit;

