<?php
/**
 * Redirect old client portal URLs to new location
 * Client portal has been moved from cms/client/ to client-portal/
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/app.php';
require_once dirname(dirname(dirname(__DIR__))) . '/includes/functions.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$baseUrl = app_url('');

// Replace /cms/client/ with /client-portal/
$newUri = str_replace('/cms/client/', '/client-portal/', $requestUri);

// If no replacement happened, redirect to client-portal dashboard
if ($newUri === $requestUri) {
    $newUri = $baseUrl . 'client-portal/dashboard.php';
}

header('Location: ' . $newUri, true, 301);
exit;
