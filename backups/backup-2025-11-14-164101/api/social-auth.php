<?php
/**
 * Social Authentication API
 * Handles Google, Facebook, and Phone number login
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/crypto.php';
require_once '../includes/helpers.php';

// CORS headers for social login redirects
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper function to show HTML error page
function showConfigError($provider, $configPage) {
    // Check if this is an AJAX request (expect JSON)
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => ucfirst($provider) . ' authentication not configured'], 400);
        return;
    }
    
    // Show HTML error page for browser requests
    $baseUrl = app_url();
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo ucfirst($provider); ?> Authentication Not Configured</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 600px;
                width: 100%;
                padding: 40px;
                text-align: center;
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                color: #1a202c;
                font-size: 28px;
                margin-bottom: 16px;
            }
            p {
                color: #4a5568;
                line-height: 1.6;
                margin-bottom: 24px;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                transition: background 0.2s;
                margin: 8px;
            }
            .btn:hover {
                background: #5568d3;
            }
            .btn-secondary {
                background: #e2e8f0;
                color: #1a202c;
            }
            .btn-secondary:hover {
                background: #cbd5e0;
            }
            .instructions {
                background: #f7fafc;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin: 24px 0;
                text-align: left;
                border-radius: 4px;
            }
            .instructions h3 {
                color: #1a202c;
                margin-bottom: 12px;
                font-size: 18px;
            }
            .instructions ol {
                margin-left: 20px;
                color: #4a5568;
            }
            .instructions li {
                margin-bottom: 8px;
                line-height: 1.6;
            }
            .instructions code {
                background: #edf2f7;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1><?php echo ucfirst($provider); ?> Authentication Not Configured</h1>
            <p>To use <?php echo ucfirst($provider); ?> login, you need to configure OAuth credentials in the system settings.</p>
            
            <div class="instructions">
                <h3>Setup Instructions:</h3>
                <ol>
                    <?php if ($provider === 'google'): ?>
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li>Create a new project or select an existing one</li>
                        <li>Enable the Google+ API</li>
                        <li>Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"</li>
                        <li>Add authorized redirect URI: <code><?php echo app_url('api/social-auth.php?action=google_auth'); ?></code></li>
                        <li>Copy your Client ID and Client Secret</li>
                        <li>Configure them in ABBIS System Settings</li>
                    <?php elseif ($provider === 'facebook'): ?>
                        <li>Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
                        <li>Create a new app</li>
                        <li>Add "Facebook Login" product</li>
                        <li>Configure OAuth redirect URI: <code><?php echo app_url('api/social-auth.php?action=facebook_auth'); ?></code></li>
                        <li>Copy your App ID and App Secret</li>
                        <li>Configure them in ABBIS System Settings</li>
                    <?php endif; ?>
                </ol>
            </div>
            
            <a href="<?php echo app_url(ltrim($configPage, '/')); ?>" class="btn">Go to System Configuration →</a>
            <a href="<?php echo app_url('login.php'); ?>" class="btn btn-secondary">← Back to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    switch ($action) {
        case 'google_auth':
            handleGoogleAuth();
            break;
            
        case 'facebook_auth':
            handleFacebookAuth();
            break;
            
        case 'phone_login_request':
            handlePhoneLoginRequest();
            break;
            
        case 'phone_verify':
            handlePhoneVerify();
            break;
            
        case 'disconnect_social':
            handleDisconnectSocial();
            break;
            
        default:
            header('Content-Type: application/json');
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("Social auth error: " . $e->getMessage());
    header('Content-Type: application/json');
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

/**
 * Handle Google OAuth
 */
function handleGoogleAuth() {
    global $pdo, $auth;
    
    $code = $_GET['code'] ?? $_POST['code'] ?? '';
    if (empty($code)) {
        // Redirect to Google OAuth
        $clientId = getConfigValue('google_client_id');
        $redirectUri = getConfigValue('google_redirect_uri', app_url('api/social-auth.php?action=google_auth'));
        
        if (empty($clientId)) {
            showConfigError('google', '/modules/social-auth-config.php');
            return;
        }
        
        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);
        
        header('Location: ' . $authUrl);
        exit;
    }
    
    // Exchange code for tokens
    $clientId = getConfigValue('google_client_id');
    $clientSecret = getConfigValue('google_client_secret');
    $redirectUri = getConfigValue('google_redirect_uri', app_url('api/social-auth.php?action=google_auth'));
    
    if (empty($clientId) || empty($clientSecret)) {
        showConfigError('google', '/modules/social-auth-config.php');
        return;
    }
    
    $tokenResponse = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ])
        ]
    ]));
    
    if (!$tokenResponse) {
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => 'Failed to get Google tokens'], 400);
        return;
    }
    
    $tokens = json_decode($tokenResponse, true);
    if (!isset($tokens['access_token'])) {
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => 'Invalid token response'], 400);
        return;
    }
    
    // Get user info from Google
    $userInfoResponse = @file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokens['access_token']);
    if (!$userInfoResponse) {
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => 'Failed to get user info'], 400);
        return;
    }
    
    $userInfo = json_decode($userInfoResponse, true);
    
    // Find or create user
    $stmt = $pdo->prepare("
        SELECT u.* FROM users u
        JOIN user_social_auth usa ON u.id = usa.user_id
        WHERE usa.provider = 'google' AND usa.provider_user_id = ?
    ");
    $stmt->execute([$userInfo['id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$userInfo['email']]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // Link Google to existing account
            $stmt = $pdo->prepare("
                INSERT INTO user_social_auth (user_id, provider, provider_user_id, access_token, refresh_token, token_expires_at)
                VALUES (?, 'google', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $existingUser['id'],
                $userInfo['id'],
                $tokens['access_token'],
                $tokens['refresh_token'] ?? null,
                isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + $tokens['expires_in']) : null
            ]);
            $user = $existingUser;
        } else {
            // Create new user
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userInfo['email']));
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                $username .= '_' . time();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, email_verified, role)
                VALUES (?, ?, ?, ?, 1, 'clerk')
            ");
            $stmt->execute([
                $username,
                $userInfo['email'],
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Random password
                $userInfo['name'] ?? $userInfo['email']
            ]);
            
            $userId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO user_social_auth (user_id, provider, provider_user_id, access_token, refresh_token, token_expires_at)
                VALUES (?, 'google', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $userInfo['id'],
                $tokens['access_token'],
                $tokens['refresh_token'] ?? null,
                isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + $tokens['expires_in']) : null
            ]);
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    } else {
        // Update tokens
        $stmt = $pdo->prepare("
            UPDATE user_social_auth 
            SET access_token = ?, refresh_token = ?, token_expires_at = ?, updated_at = NOW()
            WHERE user_id = ? AND provider = 'google'
        ");
        $stmt->execute([
            $tokens['access_token'],
            $tokens['refresh_token'] ?? null,
            isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + $tokens['expires_in']) : null,
            $user['id']
        ]);
    }
    
    // Login user
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Redirect to dashboard after successful login
    header('Location: ' . app_url('modules/dashboard.php'));
    exit;
}

/**
 * Handle Facebook OAuth
 */
function handleFacebookAuth() {
    global $pdo, $auth;
    
    $code = $_GET['code'] ?? $_POST['code'] ?? '';
    if (empty($code)) {
        $appId = getConfigValue('facebook_app_id');
        $redirectUri = getConfigValue('facebook_redirect_uri', app_url('api/social-auth.php?action=facebook_auth'));
        
        if (empty($appId)) {
            showConfigError('facebook', '/modules/social-auth-config.php');
            return;
        }
        
        $authUrl = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => 'email',
            'response_type' => 'code'
        ]);
        
        header('Location: ' . $authUrl);
        exit;
    }
    
    // Exchange code for token and get user info
    $appId = getConfigValue('facebook_app_id');
    $appSecret = getConfigValue('facebook_app_secret');
    $redirectUri = getConfigValue('facebook_redirect_uri', app_url('api/social-auth.php?action=facebook_auth'));
    
    if (empty($appId) || empty($appSecret)) {
        showConfigError('facebook', '/modules/social-auth-config.php');
        return;
    }
    
    // Similar implementation to Google...
    // (Implementation would follow same pattern)
    
    header('Content-Type: application/json');
    jsonResponse(['success' => false, 'message' => 'Facebook auth not fully implemented'], 501);
}

/**
 * Request phone login code
 */
function handlePhoneLoginRequest() {
    global $pdo;
    
    header('Content-Type: application/json');
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');
    if (empty($phoneNumber)) {
        jsonResponse(['success' => false, 'message' => 'Phone number required'], 400);
    }
    
    // Normalize phone number
    $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Check if user exists with this phone
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ? AND is_active = 1");
    $stmt->execute([$phoneNumber]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Phone number not registered'], 404);
    }
    
    // Generate 6-digit code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
    
    // Save code
    $stmt = $pdo->prepare("
        INSERT INTO phone_verification_codes (phone_number, code, expires_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        code = VALUES(code),
        expires_at = VALUES(expires_at),
        verified = 0,
        attempts = 0
    ");
    $stmt->execute([$phoneNumber, $code, $expiresAt]);
    
    // In production, send SMS here using SMS gateway
    // For now, we'll return the code in development
    if (APP_ENV === 'development') {
        jsonResponse([
            'success' => true,
            'message' => 'Verification code sent',
            'code' => $code, // Only in development
            'expires_in' => 600
        ]);
    } else {
        // Send SMS via gateway
        sendSMS($phoneNumber, "Your ABBIS verification code is: $code. Valid for 10 minutes.");
        jsonResponse([
            'success' => true,
            'message' => 'Verification code sent to your phone',
            'expires_in' => 600
        ]);
    }
}

/**
 * Verify phone code and login
 */
function handlePhoneVerify() {
    global $pdo, $auth;
    
    header('Content-Type: application/json');
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');
    $code = sanitizeInput($_POST['code'] ?? '');
    
    if (empty($phoneNumber) || empty($code)) {
        jsonResponse(['success' => false, 'message' => 'Phone number and code required'], 400);
    }
    
    // Check code
    $stmt = $pdo->prepare("
        SELECT * FROM phone_verification_codes
        WHERE phone_number = ? AND code = ? AND expires_at > NOW() AND verified = 0
    ");
    $stmt->execute([$phoneNumber, $code]);
    $verification = $stmt->fetch();
    
    if (!$verification) {
        // Increment attempts
        $stmt = $pdo->prepare("
            UPDATE phone_verification_codes 
            SET attempts = attempts + 1
            WHERE phone_number = ?
        ");
        $stmt->execute([$phoneNumber]);
        
        jsonResponse(['success' => false, 'message' => 'Invalid or expired code'], 400);
    }
    
    // Mark as verified
    $stmt = $pdo->prepare("
        UPDATE phone_verification_codes 
        SET verified = 1
        WHERE id = ?
    ");
    $stmt->execute([$verification['id']]);
    
    // Get user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ? AND is_active = 1");
    $stmt->execute([$phoneNumber]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // Login user
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Link phone auth if not exists
    $stmt = $pdo->prepare("
        INSERT INTO user_social_auth (user_id, provider, provider_user_id)
        VALUES (?, 'phone', ?)
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    $stmt->execute([$user['id'], $phoneNumber]);
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    $redirectUrl = $_SESSION['redirect_after_login'] ?? '../index.php';
    unset($_SESSION['redirect_after_login']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => $redirectUrl
    ]);
}

/**
 * Disconnect social account
 */
function handleDisconnectSocial() {
    global $pdo, $auth;
    
    header('Content-Type: application/json');
    
    $auth->requireAuth();
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $provider = sanitizeInput($_POST['provider'] ?? '');
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("DELETE FROM user_social_auth WHERE user_id = ? AND provider = ?");
    $stmt->execute([$userId, $provider]);
    
    jsonResponse(['success' => true, 'message' => 'Social account disconnected']);
}

/**
 * Send SMS (placeholder - integrate with SMS gateway)
 */
function sendSMS($phoneNumber, $message) {
    // TODO: Integrate with SMS gateway (Twilio, Africa's Talking, etc.)
    error_log("SMS to {$phoneNumber}: {$message}");
    return true;
}

/**
 * Get config value
 */
function getConfigValue($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        if (!$result) {
            return $default;
        }

        $value = $result['config_value'];
        if (Crypto::isEncrypted($value)) {
            try {
                $value = Crypto::decrypt($value);
            } catch (RuntimeException $e) {
                error_log('Config decrypt failed for key ' . $key . ': ' . $e->getMessage());
                return $default;
            }
        }

        return $value !== '' ? $value : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

