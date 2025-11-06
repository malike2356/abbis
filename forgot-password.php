<?php
/**
 * Forgot Password Page
 */
require_once 'config/app.php';
require_once 'config/security.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            $ch = curl_init(APP_URL . '/api/password-recovery.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'action' => 'request_reset',
                'email' => $email,
                'csrf_token' => $_POST['csrf_token']
            ]));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'] ?? 'Failed to send reset email';
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
            <div class="alert alert-success"><?php echo e($success); ?></div>
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

