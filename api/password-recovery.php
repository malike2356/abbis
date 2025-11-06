<?php
/**
 * Password Recovery API
 * Handles forgot password and password reset
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'request_reset':
            handlePasswordResetRequest();
            break;
            
        case 'verify_token':
            handleTokenVerification();
            break;
            
        case 'reset_password':
            handlePasswordReset();
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("Password recovery error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

/**
 * Request password reset
 */
function handlePasswordResetRequest() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $email = sanitizeInput($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Valid email address required'], 400);
    }
    
    // Find user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists for security
        jsonResponse([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent'
        ]);
        return;
    }
    
    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Save token
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    
    // Generate reset link
    $resetLink = APP_URL . '/reset-password.php?token=' . $token;
    
    // Send email
    $subject = 'Password Reset Request - ABBIS';
    $message = "
        <h2>Password Reset Request</h2>
        <p>Hello {$user['full_name']},</p>
        <p>You requested to reset your password. Click the link below to reset it:</p>
        <p><a href='{$resetLink}' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
        <p>Or copy this link: {$resetLink}</p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this, please ignore this email.</p>
    ";
    
    sendEmail($user['email'], $subject, $message);
    
    jsonResponse([
        'success' => true,
        'message' => 'Password reset link sent to your email'
    ]);
}

/**
 * Verify reset token
 */
function handleTokenVerification() {
    global $pdo;
    
    $token = sanitizeInput($_GET['token'] ?? $_POST['token'] ?? '');
    if (empty($token)) {
        jsonResponse(['success' => false, 'message' => 'Token required'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT prt.*, u.email, u.full_name
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0
    ");
    $stmt->execute([$token]);
    $resetToken = $stmt->fetch();
    
    if (!$resetToken) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired token'], 400);
    }
    
    jsonResponse([
        'success' => true,
        'email' => $resetToken['email'],
        'user_name' => $resetToken['full_name']
    ]);
}

/**
 * Reset password with token
 */
function handlePasswordReset() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $token = sanitizeInput($_POST['token'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password_confirm'] ?? '';
    
    if (empty($token) || empty($newPassword)) {
        jsonResponse(['success' => false, 'message' => 'Token and password required'], 400);
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['success' => false, 'message' => 'Passwords do not match'], 400);
    }
    
    if (strlen($newPassword) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
    }
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens
        WHERE token = ? AND expires_at > NOW() AND used = 0
    ");
    $stmt->execute([$token]);
    $resetToken = $stmt->fetch();
    
    if (!$resetToken) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired token'], 400);
    }
    
    // Update password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$passwordHash, $resetToken['user_id']]);
    
    // Mark token as used
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
    $stmt->execute([$resetToken['id']]);
    
    // Invalidate all other reset tokens for this user
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->execute([$resetToken['user_id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Password reset successfully. You can now login with your new password.'
    ]);
}

/**
 * Send email helper
 */
function sendEmail($to, $subject, $message) {
    // Use your existing email system
    require_once __DIR__ . '/../includes/email.php';
    $emailer = new Email();
    return $emailer->send($to, $subject, $message);
}

