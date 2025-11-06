<?php
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
$cmsAuth->logout();

// Get base URL
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}

// Redirect to CMS homepage
header('Location: ' . $baseUrl . '/cms/');
exit;

