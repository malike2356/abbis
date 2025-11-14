<?php
/**
 * Reset Password Page
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

$token = sanitizeInput($_GET['token'] ?? '');
$error = null;
$success = null;
$userInfo = null;

if (empty($token)) {
    $error = 'Invalid reset link. Please request a new password reset link.';
} else {
    try {
        // Verify token
        $pdo = getDBConnection();
        
        // Ensure password_reset_tokens table exists
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
        
        $stmt = $pdo->prepare("
            SELECT prt.*, u.email, u.full_name
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0
        ");
        $stmt->execute([$token]);
        $resetToken = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resetToken) {
            $error = 'Invalid or expired reset link. Please request a new password reset link.';
        } else {
            $userInfo = [
                'email' => $resetToken['email'],
                'user_name' => $resetToken['full_name'],
                'token' => $token
            ];
        }
    } catch (Exception $e) {
        error_log('Password reset token verification error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';
        
        if (empty($newPassword)) {
            $error = 'Password is required';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            try {
                $pdo = getDBConnection();
                
                // Verify token again
                $stmt = $pdo->prepare("
                    SELECT * FROM password_reset_tokens
                    WHERE token = ? AND expires_at > NOW() AND used = 0
                ");
                $stmt->execute([$token]);
                $resetToken = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$resetToken) {
                    $error = 'Invalid or expired reset link. Please request a new password reset link.';
                } else {
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
                    
                    $success = 'Password reset successfully! You can now login with your new password.';
                    $userInfo = null; // Clear user info so form doesn't show
                }
            } catch (Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $error = 'An error occurred while resetting your password. Please try again or contact your administrator.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid" style="max-width: 500px; margin: 50px auto;">
    <div class="dashboard-card">
        <h2 style="text-align: center; margin-bottom: 10px;">🔒 Reset Password</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="forgot-password.php" class="btn btn-primary">Request New Link</a>
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        <?php elseif ($userInfo): ?>
            <p style="text-align: center; color: var(--secondary); margin-bottom: 30px;">
                Reset password for: <strong><?php echo e($userInfo['email']); ?></strong>
            </p>
            
            <form method="POST" id="resetForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="password" id="password" class="form-control" required 
                           minlength="8" placeholder="Minimum 8 characters" autofocus>
                    <small class="form-text">Must be at least 8 characters long</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" name="password_confirm" id="password_confirm" class="form-control" required 
                           minlength="8" placeholder="Re-enter your password">
                    <small class="form-text" id="passwordMatch" style="display: none; color: #dc3545;">Passwords do not match</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Reset Password
                    </button>
                </div>
            </form>
            
            <script>
                // Client-side password validation
                document.getElementById('resetForm')?.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const passwordConfirm = document.getElementById('password_confirm').value;
                    const matchMsg = document.getElementById('passwordMatch');
                    
                    if (password !== passwordConfirm) {
                        e.preventDefault();
                        matchMsg.style.display = 'block';
                        return false;
                    } else {
                        matchMsg.style.display = 'none';
                    }
                });
                
                // Real-time password match checking
                document.getElementById('password_confirm')?.addEventListener('input', function() {
                    const password = document.getElementById('password').value;
                    const passwordConfirm = this.value;
                    const matchMsg = document.getElementById('passwordMatch');
                    
                    if (passwordConfirm && password !== passwordConfirm) {
                        matchMsg.style.display = 'block';
                        this.style.borderColor = '#dc3545';
                    } else {
                        matchMsg.style.display = 'none';
                        this.style.borderColor = '';
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

