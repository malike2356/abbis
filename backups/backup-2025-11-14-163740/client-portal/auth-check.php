<?php
/**
 * Client Portal Authentication Check
 * Include this at the top of protected client portal pages
 * Supports SSO from ABBIS admin
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/config/constants.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php'; // Include helpers for redirect() function
require_once $rootPath . '/includes/functions.php';

$auth = new Auth();
$isAdminMode = isset($_SESSION['client_portal_admin_mode']) && $_SESSION['client_portal_admin_mode'] === true;
$isClientRole = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_CLIENT;
$isAdminRole = isset($_SESSION['role']) && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_SUPER_ADMIN);

// Allow access if:
// 1. User is logged in as client, OR
// 2. User is logged in as admin/super admin and admin mode is enabled
if (!$auth->isLoggedIn() || (!$isClientRole && !($isAdminRole && $isAdminMode))) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    
    // If admin/super admin is logged into ABBIS, enable admin mode
    if ($isAdminRole && !$isAdminMode) {
        $_SESSION['client_portal_admin_mode'] = true;
        $_SESSION['client_portal_admin_user_id'] = $_SESSION['user_id'];
        $_SESSION['client_portal_admin_username'] = $_SESSION['username'];
        // Continue with page load
    } else {
        redirect('login.php');
    }
}

// Get client information
$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$clientId = null;
$viewingClientId = null; // For admin mode - which client they're viewing

try {
    $client = null; // Initialize client variable
    if ($isAdminMode && $isAdminRole) {
        // Admin mode - allow viewing any client or all clients
        // Check if a specific client is selected via query parameter
        $viewingClientId = $_GET['client_id'] ?? null;
        if ($viewingClientId) {
            $viewingClientId = (int)$viewingClientId;
            // Verify client exists
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$viewingClientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $clientId = $viewingClientId; // Use this client for data display
            }
        }
        // If no specific client selected, admin can see overview/all clients
        // $client remains null in this case
    } else {
        // Client mode - get client linked to user
        $stmt = $pdo->prepare("SELECT client_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $clientId = $user['client_id'] ?? null;
        
        if (!$clientId) {
            // Try to find client by email
            $email = $_SESSION['username'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $clientId = $stmt->fetchColumn();
            
            if ($clientId) {
                // Link user to client
                $updateStmt = $pdo->prepare("UPDATE users SET client_id = ? WHERE id = ?");
                $updateStmt->execute([$clientId, $userId]);
            }
        }
        
        // Get client details
        $client = null;
        if ($clientId) {
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log('Client portal auth check error: ' . $e->getMessage());
}

// Log portal activity
if ($clientId || $isAdminMode) {
    try {
        // Ensure client_portal_activities table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `client_portal_activities` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `client_id` INT(11) DEFAULT NULL,
                `user_id` INT(11) NOT NULL,
                `activity_type` VARCHAR(50) DEFAULT 'page_view',
                `activity_description` TEXT DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `client_id` (`client_id`),
                KEY `user_id` (`user_id`),
                KEY `activity_type` (`activity_type`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $activityClientId = $clientId ?? 0; // Use 0 for admin viewing all clients
        $activityDescription = 'Viewed: ' . basename($_SERVER['PHP_SELF'] ?? '');
        if ($isAdminMode) {
            $activityDescription = '[ADMIN] ' . $activityDescription;
            if ($viewingClientId) {
                $activityDescription .= ' (Viewing Client ID: ' . $viewingClientId . ')';
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO client_portal_activities (client_id, user_id, activity_type, activity_description, ip_address, user_agent)
            VALUES (?, ?, 'page_view', ?, ?, ?)
        ");
        $stmt->execute([
            $activityClientId,
            $userId,
            $activityDescription,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Log silently - table creation or insert might fail, but don't break the page
        error_log('Client portal activity log error: ' . $e->getMessage());
    }
}

