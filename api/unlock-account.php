<?php
/**
 * Unlock Account API
 * Unlocks a user account by clearing login attempts
 * Requires admin authentication
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check authentication
$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Validate CSRF token
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

// Get username
$username = trim($_POST['username'] ?? '');

if (empty($username)) {
    jsonResponse(['success' => false, 'message' => 'Username is required'], 400);
}

// Unlock account
$result = $auth->unlockAccount($username);

if ($result['success']) {
    jsonResponse([
        'success' => true,
        'message' => $result['message'],
        'username' => $username,
        'deleted_count' => $result['deleted_count']
    ]);
} else {
    jsonResponse(['success' => false, 'message' => $result['message']], 500);
}

