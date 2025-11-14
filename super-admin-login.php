<?php
/**
 * Super Admin Login (Development/Maintenance Only)
 * 
 * ⚠️ WARNING: This is a development/maintenance bypass.
 * ONLY works when APP_ENV = 'development'
 * NEVER enable this in production!
 */

require_once 'config/app.php';
require_once 'config/security.php';
require_once 'config/super-admin.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Check if Super Admin bypass is enabled
if (!isSuperAdminBypassEnabled()) {
    $env = defined('APP_ENV') ? APP_ENV : 'unknown';
    $superAdminEnabled = getenv('SUPER_ADMIN_ENABLED');
    $hasSecret = getenv('SUPER_ADMIN_SECRET') !== false;
    $hasUsername = getenv('SUPER_ADMIN_USERNAME') !== false;
    $hasPassword = getenv('SUPER_ADMIN_PASSWORD') !== false;
    
    $reasons = [];
    if ($env !== 'development') {
        $reasons[] = "Environment is not 'development' (current: {$env})";
    }
    if ($superAdminEnabled !== 'true' && $superAdminEnabled !== '1' && $superAdminEnabled !== 'yes') {
        $reasons[] = "SUPER_ADMIN_ENABLED is not set to 'true' (current: " . ($superAdminEnabled ?: 'not set') . ")";
    }
    if (!$hasSecret) {
        $reasons[] = "SUPER_ADMIN_SECRET environment variable is not set";
    }
    if (!$hasUsername) {
        $reasons[] = "SUPER_ADMIN_USERNAME environment variable is not set";
    }
    if (!$hasPassword) {
        $reasons[] = "SUPER_ADMIN_PASSWORD environment variable is not set";
    }
    
    http_response_code(403);
    die('<!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; max-width: 600px; margin: 0 auto; }
            h1 { color: #dc3545; }
            p { color: #666; }
            .reasons { text-align: left; background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .reasons ul { margin: 10px 0 0 20px; }
            .instructions { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left; }
            .instructions code { background: #fff; padding: 2px 6px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h1>⚠️ Super Admin Bypass Disabled</h1>
        <p>Super Admin bypass is currently disabled.</p>
        <p><strong>Current environment:</strong> ' . $env . '</p>
        
        <div class="reasons">
            <strong>Why it\'s disabled:</strong>
            <ul>
                ' . implode('', array_map(function($r) { return '<li>' . htmlspecialchars($r) . '</li>'; }, $reasons)) . '
            </ul>
        </div>
        
        <div class="instructions">
            <strong>To enable Super Admin bypass:</strong>
            <ol style="margin: 10px 0 0 20px;">
                <li>Set environment variables:
                    <pre style="background: #fff; padding: 10px; border-radius: 4px; margin-top: 5px; font-size: 12px;">export SUPER_ADMIN_ENABLED=true
export SUPER_ADMIN_SECRET="your-secret-key"
export SUPER_ADMIN_USERNAME="your-username"
export SUPER_ADMIN_PASSWORD="your-password"</pre>
                </li>
                <li>Restart your web server/PHP-FPM</li>
                <li>Refresh this page</li>
            </ol>
            <p style="margin-top: 15px; font-size: 12px;">
                <strong>To disable:</strong> Unset <code>SUPER_ADMIN_ENABLED</code> or set it to <code>false</code>
            </p>
        </div>
        
        <p><a href="login.php">Go to Regular Login</a></p>
    </body>
    </html>');
}

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('modules/dashboard.php');
}

$error = null;
$success = null;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password';
        } else {
            $result = $auth->login($username, $password);
            if ($result['success']) {
                if (isset($result['super_admin']) && $result['super_admin']) {
                    // Super Admin login successful
                    $redirect = $_SESSION['redirect_after_login'] ?? 'modules/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect);
                } else {
                    // Regular login
                    redirect('modules/dashboard.php');
                }
            } else {
                $error = $result['message'] ?? 'Invalid username or password';
            }
        }
    }
}

// Super Admin credentials are only available via environment variables
// No hints are provided - admins must configure environment variables
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - ABBIS (Development)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        .warning-banner {
            background: #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ff9800;
        }
        .warning-banner strong {
            display: block;
            margin-bottom: 5px;
            font-size: 16px;
        }
        .warning-banner p {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #0369a1;
        }
        .info-box strong {
            display: block;
            margin-bottom: 8px;
        }
        .info-box code {
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .footer-links a {
            color: #667eea;
            text-decoration: none;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="warning-banner">
            <strong>⚠️ DEVELOPMENT MODE ONLY</strong>
            <p>This is a Super Admin bypass for development and maintenance. This feature is disabled in production.</p>
        </div>
        
        <h1>🔑 Super Admin Login</h1>
        <p class="subtitle">Development & Maintenance Access</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="form-group">
                <label for="username">Super Admin Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    autofocus
                    placeholder="Enter super admin username">
            </div>
            
            <div class="form-group">
                <label for="password">Super Admin Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="Enter super admin password">
            </div>
            
            <button type="submit" class="btn">Login as Super Admin</button>
        </form>
        
        <div class="info-box">
            <strong>ℹ️ Super Admin Access:</strong>
            <p style="margin-top: 8px; font-size: 13px;">
                Super Admin credentials are configured via environment variables only.
                Contact your system administrator for access.
            </p>
            <p style="margin-top: 8px; font-size: 12px; color: #666;">
                <strong>Environment Variables Required:</strong><br>
                <code>SUPER_ADMIN_SECRET</code>, <code>SUPER_ADMIN_USERNAME</code>, <code>SUPER_ADMIN_PASSWORD</code>
            </p>
        </div>
        
        <div class="footer-links">
            <a href="login.php">← Back to Regular Login</a>
        </div>
    </div>
</body>
</html>

