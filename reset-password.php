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

$token = $_GET['token'] ?? '';
$error = null;
$success = null;
$userInfo = null;

if (empty($token)) {
    $error = 'Invalid reset link';
} else {
    // Verify token
    $pdo = getDBConnection();
    $ch = curl_init(APP_URL . '/api/password-recovery.php?action=verify_token&token=' . urlencode($token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        $userInfo = $result;
    } else {
        $error = $result['message'] ?? 'Invalid or expired reset link';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userInfo) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';
        
        if (empty($newPassword) || $newPassword !== $confirmPassword) {
            $error = 'Passwords do not match or are empty';
        } else {
            $ch = curl_init(APP_URL . '/api/password-recovery.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'action' => 'reset_password',
                'token' => $token,
                'password' => $newPassword,
                'password_confirm' => $confirmPassword,
                'csrf_token' => $_POST['csrf_token']
            ]));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'] ?? 'Failed to reset password';
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
            
            <form method="POST">
                <?php echo CSRF::getTokenField(); ?>
                
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="password" class="form-control" required 
                           minlength="8" placeholder="Minimum 8 characters" autofocus>
                    <small class="form-text">Must be at least 8 characters long</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" name="password_confirm" class="form-control" required 
                           minlength="8" placeholder="Re-enter your password">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

