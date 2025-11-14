<?php
/**
 * POS System - Entry Point
 * Routes to terminal or admin dashboard
 */
session_start();
$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth = new Auth();
$auth->requireAuth();
$auth->requirePermission('pos.access');

// Check if user wants admin, profile, or terminal
$action = $_GET['action'] ?? 'terminal';

if ($action === 'admin') {
    require_once __DIR__ . '/admin/index.php';
    exit;
}

if ($action === 'profile') {
    require_once __DIR__ . '/profile.php';
    exit;
}

// Default to terminal
require_once __DIR__ . '/terminal.php';
