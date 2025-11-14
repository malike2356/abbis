<?php
/**
 * CMS Admin Login - Redirects to Unified Login
 * Users should use the main ABBIS login.php which handles routing
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';

// Set redirect after login to CMS
$_SESSION['redirect_after_login'] = app_url('cms/admin/index.php');
$_SESSION['cms_login_destination'] = true;

// Redirect to unified login
header('Location: ' . app_url('login.php?redirect=cms'));
exit;

