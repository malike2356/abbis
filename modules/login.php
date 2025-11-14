<?php
/**
 * Redirect to main login page
 * This file redirects requests for modules/login.php to the root login.php
 */
require_once __DIR__ . '/../config/app.php';

// Preserve query string and redirect parameters
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$redirect = $_GET['redirect'] ?? '';

// Build redirect URL
$loginUrl = app_url('login.php');
if ($queryString) {
    $loginUrl .= '?' . $queryString;
} elseif ($redirect) {
    $loginUrl .= '?redirect=' . urlencode($redirect);
}

// Perform redirect
header('Location: ' . $loginUrl, true, 301);
exit;
?>

