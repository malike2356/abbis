<?php
/**
 * Client Portal Login
 * Supports SSO from ABBIS admin
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/config/constants.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php'; // Include helpers for redirect() function
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/sso.php';

$error = '';
$message = '';

// Check SSO token first (for admin access from ABBIS)
$ssoToken = $_GET['token'] ?? '';
if (!empty($ssoToken)) {
    $sso = new SSO();
    $result = $sso->verifyClientPortalSSOToken($ssoToken);
    
    if ($result['success']) {
        // Admin logged in via SSO - set admin mode
        $_SESSION['client_portal_admin_mode'] = true;
        $_SESSION['client_portal_admin_user_id'] = $_SESSION['user_id'];
        $_SESSION['client_portal_admin_username'] = $_SESSION['username'];
        
        // Redirect to dashboard
        $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
        unset($_SESSION['redirect_after_login']);
        redirect($redirect);
    } else {
        $error = $result['message'] ?? 'Invalid SSO token.';
    }
}

// Check if already logged into ABBIS - allow SSO access for all roles
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $userRole = $_SESSION['role'];
    // Allow admins, super admins, and clients to access client portal
    if ($userRole === ROLE_ADMIN || $userRole === ROLE_SUPER_ADMIN) {
        // Admin/Super Admin is logged into ABBIS - enable admin mode for client portal
        $_SESSION['client_portal_admin_mode'] = true;
        $_SESSION['client_portal_admin_user_id'] = $_SESSION['user_id'];
        $_SESSION['client_portal_admin_username'] = $_SESSION['username'];
        redirect('dashboard.php');
    } elseif ($userRole === ROLE_CLIENT) {
        // Client is already logged in - redirect to dashboard
        redirect('dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $auth = new Auth();
            $result = $auth->login($username, $password);
            
            if ($result['success']) {
                $userRole = $_SESSION['role'] ?? null;
                
                // Check if user is a client, admin, or super admin
                if ($userRole === ROLE_CLIENT) {
                    // Client logged in - redirect to client dashboard
                    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect);
                } elseif ($userRole === ROLE_ADMIN || $userRole === ROLE_SUPER_ADMIN) {
                    // Admin/Super Admin logged in - enable admin mode for client portal
                    $_SESSION['client_portal_admin_mode'] = true;
                    $_SESSION['client_portal_admin_user_id'] = $_SESSION['user_id'];
                    $_SESSION['client_portal_admin_username'] = $_SESSION['username'];
                    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect);
                } else {
                    // Not a client, admin, or super admin - logout and show error
                    $auth->logout();
                    $error = 'Access denied. This portal is for clients and administrators only.';
                }
            } else {
                $error = $result['message'] ?? 'Invalid username or password.';
            }
        }
    }
}

// Check for messages
if (isset($_SESSION['client_message'])) {
    $message = $_SESSION['client_message'];
    unset($_SESSION['client_message']);
}

$pageTitle = 'Client Portal Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-header h1 {
            color: #1a202c;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .login-header p {
            color: #718096;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            color: #2d3748;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Client Portal</h1>
            <p>Sign in to access your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo CSRF::getTokenField(); ?>
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="login-footer">
            <a href="<?php echo app_url(''); ?>">← Back to Home</a>
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN): ?>
                <br><br>
                <a href="<?php echo app_url('modules/dashboard.php'); ?>" style="color: #667eea; font-weight: 600;">← Back to ABBIS Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

