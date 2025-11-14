<?php
/**
 * Client Portal Logout
 * Handles logout for both clients and admins
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/constants.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php'; // Include helpers for redirect() function
require_once $rootPath . '/includes/functions.php';

$auth = new Auth();
$isAdminMode = isset($_SESSION['client_portal_admin_mode']) && $_SESSION['client_portal_admin_mode'] === true;
$isAdminRole = isset($_SESSION['role']) && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_SUPER_ADMIN);

// If admin/super admin is in admin mode, clear admin mode but keep ABBIS session
if ($isAdminMode && $isAdminRole) {
    // Clear client portal admin mode
    unset($_SESSION['client_portal_admin_mode']);
    unset($_SESSION['client_portal_admin_user_id']);
    unset($_SESSION['client_portal_admin_username']);
    
    // Redirect back to ABBIS if still logged in
    if ($auth->isLoggedIn() && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_SUPER_ADMIN)) {
        redirect(app_url('modules/dashboard.php'));
    }
}

// Full logout for clients or if admin wants to fully log out
$auth->logout();

// Redirect to login page after logout
redirect('login.php');

