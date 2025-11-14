<?php
/**
 * Check Lockout Status API
 * Returns lockout status for a username
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Get username from query parameter
$username = trim($_GET['username'] ?? '');

if (empty($username)) {
    jsonResponse(['success' => false, 'message' => 'Username is required'], 400);
}

// Get lockout status
$auth = new Auth();
$lockoutInfo = $auth->getLockoutStatus($username);

jsonResponse([
    'success' => true,
    'is_locked' => $lockoutInfo['is_locked'] ?? false,
    'attempts' => $lockoutInfo['attempts'] ?? 0,
    'max_attempts' => $lockoutInfo['max_attempts'] ?? 5,
    'remaining_attempts' => $lockoutInfo['remaining_attempts'] ?? 5,
    'time_until_unlock' => $lockoutInfo['time_until_unlock'] ?? null,
    'unlock_time' => $lockoutInfo['unlock_time'] ?? null,
    'last_attempt' => $lockoutInfo['last_attempt'] ?? null
]);

