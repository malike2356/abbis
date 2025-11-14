<?php
/**
 * Forgot Password Page
 */
require_once 'config/app.php';
require_once 'config/security.php';
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

$error = null;
$success = null;
$resetLink = null; // Store reset link separately for development mode
$resetToken = null; // Store token for development mode

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            try {
                // Ensure password_reset_tokens table exists
                $pdo = getDBConnection();
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS password_reset_tokens (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        user_id INT(11) NOT NULL,
                        token VARCHAR(255) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        used TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY token (token),
                        KEY user_id (user_id),
                        KEY expires_at (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Find user by email
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    // Don't reveal if email exists for security
                    $success = 'If the email exists, a password reset link has been sent to your email address.';
                } else {
                    // Generate secure token
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                    
                    // Clean up old unused tokens for this user (optional)
                    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND expires_at < NOW()")->execute([$user['id']]);
                    
                    // Save token
                    $stmt = $pdo->prepare("
                        INSERT INTO password_reset_tokens (user_id, token, expires_at)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $token, $expiresAt]);
                    
                    // Generate reset link
                    $resetLink = app_url('reset-password.php?token=' . $token);
                    
                    // Send email
                    require_once 'includes/email.php';
                    $emailer = new Email();
                    
                    $subject = 'Password Reset Request - ABBIS';
                    $message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                                .button:hover { background: #0056b3; }
                                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h2>Password Reset Request</h2>
                                <p>Hello " . htmlspecialchars($user['full_name'] ?? $user['username']) . ",</p>
                                <p>You requested to reset your password for your ABBIS account.</p>
                                <p>Click the button below to reset your password:</p>
                                <p><a href='{$resetLink}' class='button'>Reset Password</a></p>
                                <p>Or copy and paste this link into your browser:</p>
                                <p><a href='{$resetLink}'>{$resetLink}</a></p>
                                <p><strong>This link will expire in 1 hour.</strong></p>
                                <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                                <div class='footer'>
                                    <p>This is an automated message from ABBIS. Please do not reply to this email.</p>
                                    <p>&copy; " . date('Y') . " ABBIS. All rights reserved.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    try {
                        $emailSent = $emailer->send($user['email'], $subject, $message);
                        
                        if ($emailSent) {
                            $success = 'Password reset link has been sent to your email address. Please check your inbox and click the link to reset your password.';
                        } else {
                            // Log the token for manual recovery if email fails
                            error_log("Password reset token for {$user['email']}: {$token} (Email send failed)");
                            
                            // For development/testing: Show the reset link directly if email fails
                            if (defined('APP_ENV') && APP_ENV === 'development') {
                                $success = 'Password reset link generated. Email sending failed (email system not configured), but you can use the link below:';
                                $resetLink = app_url('reset-password.php?token=' . $token);
                                $resetToken = $token;
                            } else {
                                $error = 'Failed to send email. The reset link has been generated. Please check your email or contact your administrator for assistance.';
                            }
                        }
                    } catch (Exception $emailException) {
                        error_log("Email send exception for password reset: " . $emailException->getMessage());
                        error_log("Password reset token for {$user['email']}: {$token}");
                        
                        // For development: Show the link even if email fails
                        if (defined('APP_ENV') && APP_ENV === 'development') {
                            $success = 'Password reset link generated. Email sending failed (' . htmlspecialchars($emailException->getMessage()) . '), but you can use the link below:';
                            $resetLink = app_url('reset-password.php?token=' . $token);
                            $resetToken = $token;
                        } else {
                            $error = 'An error occurred while sending the email. Please contact your administrator.';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $error = 'An error occurred. Please try again later or contact your administrator.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid" style="max-width: 500px; margin: 50px auto;">
    <div class="dashboard-card">
        <h2 style="text-align: center; margin-bottom: 10px;">🔒 Forgot Password</h2>
        <p style="text-align: center; color: var(--secondary); margin-bottom: 30px;">
            Enter your email address and we'll send you a password reset link
        </p>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo e($success); ?>
                
                <?php if ($resetLink): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #007bff;">
                        <strong style="display: block; margin-bottom: 10px; color: #007bff;">🔗 Password Reset Link (Development Mode):</strong>
                        <a href="<?php echo htmlspecialchars($resetLink); ?>" 
                           style="display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-bottom: 10px; word-break: break-all;"
                           target="_blank">
                            Click here to reset your password
                        </a>
                        <div style="margin-top: 10px;">
                            <small style="color: #666; display: block; margin-bottom: 5px;">Or copy this link:</small>
                            <code style="display: block; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; word-break: break-all;">
                                <?php echo htmlspecialchars($resetLink); ?>
                            </code>
                        </div>
                        <?php if ($resetToken): ?>
                            <div style="margin-top: 10px;">
                                <small style="color: #666; display: block; margin-bottom: 5px;">Token (for reference):</small>
                                <code style="display: block; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 3px; font-size: 11px; word-break: break-all; color: #666;">
                                    <?php echo htmlspecialchars($resetToken); ?>
                                </code>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="btn btn-primary">Back to Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <?php echo CSRF::getTokenField(); ?>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" required 
                           placeholder="your.email@example.com" autofocus>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Send Reset Link
                    </button>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" style="color: var(--primary); text-decoration: none;">
                        ← Back to Login
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

