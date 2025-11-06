<?php
/**
 * Modern Login Page
 */
require_once 'config/app.php';
require_once 'config/security.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('modules/dashboard.php');
}

$error = null;
$success = null;

// Get base URL
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
        } else {
            // Check privacy consent
            if (empty($_POST['privacy_consent'])) {
                $error = 'You must agree to the Privacy Policy to continue';
            } else {
                $username = sanitizeArray($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    $error = 'Please enter both username and password';
                } else {
                    $result = $auth->login($username, $password);
                    if ($result['success']) {
                        // Record privacy policy consent
                        require_once 'includes/consent-manager.php';
                        $consentManager = new ConsentManager();
                        $userId = $_SESSION['user_id'];
                        $userEmail = $_SESSION['email'] ?? '';
                        $consentManager->recordConsent($userId, $userEmail, 'privacy_policy', '1.0', true);
                        
                        // Check user role for routing
                        $userRole = $_SESSION['role'] ?? null;
                        $isAdmin = ($userRole === ROLE_ADMIN);
                        
                        // Check if user wants to go to CMS or ABBIS (for admins)
                        $destination = $_POST['destination'] ?? '';
                        
                        // For admins: check if they want CMS, otherwise go to ABBIS
                        if ($isAdmin && $destination === 'cms') {
                            // Try to find corresponding CMS user and create session
                            require_once 'cms/admin/auth.php';
                            $cmsAuth = new CMSAuth();
                            $pdo = getDBConnection();
                            
                            // Try to find CMS user by username
                            $stmt = $pdo->prepare("SELECT * FROM cms_users WHERE username = ? AND status = 'active'");
                            $stmt->execute([$username]);
                            $cmsUser = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($cmsUser) {
                                // Create CMS session
                                $_SESSION['cms_user_id'] = $cmsUser['id'];
                                $_SESSION['cms_username'] = $cmsUser['username'];
                                $_SESSION['cms_role'] = $cmsUser['role'];
                                
                                // Update last login
                                try {
                                    $pdo->prepare("UPDATE cms_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?")->execute([$cmsUser['id']]);
                                } catch (Exception $e) {}
                                
                                redirect($baseUrl . '/cms/admin/index.php');
                            } else {
                                // No CMS user found, redirect to ABBIS
                                redirect('modules/dashboard.php');
                            }
                        } elseif ($isAdmin) {
                            // Admin going to ABBIS (default)
                            $redirect = $_SESSION['redirect_after_login'] ?? 'modules/dashboard.php';
                            unset($_SESSION['redirect_after_login']);
                            redirect($redirect);
                        } else {
                            // Non-admin user - check if they have CMS account and send there
                            require_once 'cms/admin/auth.php';
                            $cmsAuth = new CMSAuth();
                            $pdo = getDBConnection();
                            
                            // Try to find CMS user by username
                            $stmt = $pdo->prepare("SELECT * FROM cms_users WHERE username = ? AND status = 'active'");
                            $stmt->execute([$username]);
                            $cmsUser = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($cmsUser) {
                                // Create CMS session
                                $_SESSION['cms_user_id'] = $cmsUser['id'];
                                $_SESSION['cms_username'] = $cmsUser['username'];
                                $_SESSION['cms_role'] = $cmsUser['role'];
                                
                                // Update last login
                                try {
                                    $pdo->prepare("UPDATE cms_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?")->execute([$cmsUser['id']]);
                                } catch (Exception $e) {}
                                
                                redirect($baseUrl . '/cms/admin/index.php');
                            } else {
                                // No CMS account, but they have ABBIS account - redirect to ABBIS dashboard
                                $redirect = $_SESSION['redirect_after_login'] ?? 'modules/dashboard.php';
                                unset($_SESSION['redirect_after_login']);
                                redirect($redirect);
                            }
                        }
                    } else {
                        $error = $result['message'];
                    }
                }
            }
        }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ABBIS</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: drift 20s linear infinite;
        }
        
        @keyframes drift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 900px;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            min-height: 500px;
        }
        
        .login-left {
            background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.5;
        }
        
        .login-right {
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-logo {
            position: relative;
            z-index: 1;
        }
        
        .login-logo .logo-mark {
            font-size: 72px;
            margin-bottom: 20px;
            display: block;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }
        
        .login-logo h1 {
            color: white;
            margin: 0 0 12px 0;
            font-size: 36px;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .login-logo p {
            color: rgba(255,255,255,0.9);
            margin: 0 0 24px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .login-features {
            list-style: none;
            padding: 0;
            margin: 24px 0 0 0;
            text-align: left;
            position: relative;
            z-index: 1;
        }
        
        .login-features li {
            padding: 8px 0;
            color: rgba(255,255,255,0.95);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .login-features li::before {
            content: '✓';
            background: rgba(255,255,255,0.2);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            flex-shrink: 0;
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form .form-label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .login-form .form-control {
            padding: 14px 18px;
            font-size: 16px;
        }
        
        .login-form .btn-primary {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            margin-top: 8px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 13px;
        }
        
        .social-login {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .social-login-title {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 16px;
            position: relative;
        }
        
        .social-login-title::before,
        .social-login-title::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: #e5e7eb;
        }
        
        .social-login-title::before {
            left: 0;
        }
        
        .social-login-title::after {
            right: 0;
        }
        
        .social-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            color: #1f2937;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .social-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .social-btn.google {
            color: #ea4335;
        }
        
        .social-btn.facebook {
            color: #1877f2;
        }
        
        .phone-login-toggle {
            text-align: center;
            margin-top: 16px;
        }
        
        .phone-login-toggle a {
            color: #0ea5e9;
            text-decoration: none;
            font-size: 14px;
        }
        
        .phone-login-container {
            display: none;
        }
        
        .phone-login-container.active {
            display: block;
        }
        
        .default-credentials {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 12px;
            margin-top: 16px;
            font-size: 12px;
            color: #0369a1;
        }
        
        .cms-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .cms-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        
        .cms-link a:hover {
            color: #0ea5e9;
        }
        
        @media (max-width: 768px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 440px;
            }
            
            .login-left {
                padding: 32px 24px;
                min-height: 200px;
            }
            
            .login-right {
                padding: 32px 24px;
            }
            
            .login-logo .logo-mark {
                font-size: 48px;
                margin-bottom: 12px;
            }
            
            .login-logo h1 {
                font-size: 28px;
            }
            
            .login-features {
                display: none;
            }
        }
    </style>
</head>
<body data-theme="light">
    <div class="login-container">
        <div class="login-card">
            <!-- Left Column: Logo & Welcome -->
            <div class="login-left">
                <div class="login-logo">
                    <span class="logo-mark">⛏️</span>
                    <h1>ABBIS</h1>
                    <p>Advanced Borehole Business Intelligence System</p>
                    <ul class="login-features">
                        <li>Comprehensive borehole management</li>
                        <li>Real-time field reports</li>
                        <li>Advanced analytics & insights</li>
                        <li>Secure & compliant platform</li>
                    </ul>
                </div>
            </div>
            
            <!-- Right Column: Login Form -->
            <div class="login-right">
                <div style="margin-bottom: 32px;">
                    <h2 style="margin: 0 0 8px 0; color: #1e293b; font-size: 28px; font-weight: 700;">Welcome Back</h2>
                    <p style="margin: 0; color: #64748b; font-size: 14px;">Sign in to access your account</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;"><?php echo e($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo e($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" class="login-form" id="loginForm">
                <?php echo CSRF::getTokenField(); ?>
                
                <!-- Privacy Policy Consent -->
                <div style="margin-bottom: 20px; padding: 12px; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
                    <label style="display: flex; align-items: start; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="privacy_consent" required style="margin-top: 3px;">
                        <span style="font-size: 13px; color: #0369a1;">
                            I agree to the <a href="modules/privacy-policy.php" target="_blank" style="color: #0ea5e9; text-decoration: underline;">Privacy Policy</a> 
                            and consent to the processing of my personal data.
                        </span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        required 
                        autofocus
                        autocomplete="username"
                        placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password">
                </div>
                
                <!-- Destination selector for admins -->
                <div id="destination-selector" class="form-group" style="margin-bottom: 20px;">
                    <label for="destination" class="form-label" style="font-size: 13px;">Login Destination (Admin Only)</label>
                    <select name="destination" id="destination" class="form-control" style="font-size: 14px;">
                        <option value="">ABBIS System (Default)</option>
                        <option value="cms">CMS Admin</option>
                    </select>
                    <small style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">
                        Admins can choose destination. Non-admins auto-routed.
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Sign In
                </button>
                
                <div style="text-align: center; margin-top: 16px;">
                    <a href="forgot-password.php" style="color: #0ea5e9; text-decoration: none; font-size: 14px;">
                        Forgot Password?
                    </a>
                </div>
            </form>
            
                <!-- CMS Link -->
                <div class="cms-link" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <a href="<?php echo $baseUrl; ?>/cms/">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Visit CMS Website</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
                
                <!-- Social Login -->
                <div class="social-login" style="margin-top: 24px;">
                    <div class="social-login-title">Or continue with</div>
                    <div class="social-buttons">
                    <a href="api/social-auth.php?action=google_auth" class="social-btn google">
                        <svg width="18" height="18" viewBox="0 0 24 24">
                            <path fill="#ea4335" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34a853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#fbbc05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#ea4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Google
                    </a>
                    <a href="api/social-auth.php?action=facebook_auth" class="social-btn facebook">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877f2">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        Facebook
                    </a>
                </div>
                
                <!-- Phone Login -->
                <div class="phone-login-toggle">
                    <a href="#" onclick="togglePhoneLogin(); return false;">📱 Login with Phone Number</a>
                </div>
                
                <div class="phone-login-container" id="phoneLoginContainer">
                    <form method="POST" action="api/social-auth.php" id="phoneLoginForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="phone_login_request">
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone_number" class="form-control" 
                                   placeholder="+233 555 123 456" required>
                        </div>
                        
                        <button type="submit" class="btn btn-outline" style="width: 100%;">
                            Send Verification Code
                        </button>
                    </form>
                    
                    <form method="POST" action="api/social-auth.php" id="phoneVerifyForm" style="display: none; margin-top: 16px;">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="phone_verify">
                        <input type="hidden" name="phone_number" id="verifyPhoneNumber">
                        
                        <div class="form-group">
                            <label class="form-label">Verification Code</label>
                            <input type="text" name="code" class="form-control" 
                                   placeholder="Enter 6-digit code" maxlength="6" required>
                            <small class="form-text">Code sent to your phone</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Verify & Login
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> ABBIS. All rights reserved.</p>
            </div>
        </div>
    </div>
    
    <script>
        function togglePhoneLogin() {
            const container = document.getElementById('phoneLoginContainer');
            container.classList.toggle('active');
        }
        
        // Handle phone login form
        document.getElementById('phoneLoginForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const phoneNumber = formData.get('phone_number');
            
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show verification form
                    document.getElementById('phoneVerifyForm').style.display = 'block';
                    document.getElementById('verifyPhoneNumber').value = phoneNumber;
                    this.style.display = 'none';
                    
                    // In development, show code
                    if (result.code) {
                        alert('Verification code (dev only): ' + result.code);
                    }
                } else {
                    alert(result.message || 'Failed to send code');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
        
        // Handle phone verification form
        document.getElementById('phoneVerifyForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = result.redirect || 'index.php';
                } else {
                    alert(result.message || 'Verification failed');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
    </script>
</body>
</html>
