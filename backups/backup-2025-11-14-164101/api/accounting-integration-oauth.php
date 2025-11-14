<?php
/**
 * Accounting Integration OAuth Handler
 * Handles OAuth flow for QuickBooks and Zoho Books
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';
require_once '../includes/crypto.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$provider = $_GET['provider'] ?? $_POST['provider'] ?? '';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get integration config
 */
function getIntegrationConfig($provider) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM accounting_integrations WHERE provider = ? LIMIT 1");
    $stmt->execute([$provider]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        foreach (['client_secret', 'access_token', 'refresh_token'] as $field) {
            if (!empty($config[$field]) && Crypto::isEncrypted($config[$field])) {
                try {
                    $config[$field] = Crypto::decrypt($config[$field]);
                } catch (RuntimeException $e) {
                    error_log('Failed to decrypt ' . $field . ' for provider ' . $provider . ': ' . $e->getMessage());
                    $config[$field] = null;
                }
            }
        }
    }
    return $config;
}

/**
 * Save tokens
 */
function saveTokens($provider, $accessToken, $refreshToken = null, $expiresIn = 3600) {
    global $pdo;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn - 60); // Subtract 1 minute buffer
    $storedAccessToken = $accessToken ? Crypto::encrypt($accessToken) : null;
    $storedRefreshToken = $refreshToken ? Crypto::encrypt($refreshToken) : null;
    
    // Check if updated_at column exists, if not, don't use it
    try {
        $stmt = $pdo->prepare("
            UPDATE accounting_integrations 
            SET access_token = ?, 
                refresh_token = ?, 
                token_expires_at = ?, 
                is_active = 1
            WHERE provider = ?
        ");
        return $stmt->execute([$storedAccessToken, $storedRefreshToken, $expiresAt, $provider]);
    } catch (PDOException $e) {
        // Try with updated_at if column exists
        $stmt = $pdo->prepare("
            UPDATE accounting_integrations 
            SET access_token = ?, 
                refresh_token = ?, 
                token_expires_at = ?, 
                is_active = 1,
                updated_at = NOW()
            WHERE provider = ?
        ");
        return $stmt->execute([$storedAccessToken, $storedRefreshToken, $expiresAt, $provider]);
    }
}

/**
 * Get valid access token (refresh if expired)
 */
function getValidAccessToken($provider) {
    $config = getIntegrationConfig($provider);
    
    if (!$config || !$config['is_active'] || !$config['access_token']) {
        return null;
    }
    
    // Check if token expired
    if ($config['token_expires_at'] && strtotime($config['token_expires_at']) <= time()) {
        if ($config['refresh_token']) {
            return refreshToken($provider, $config['refresh_token']);
        }
        return null;
    }
    
    return $config['access_token'];
}

/**
 * Refresh QuickBooks token
 */
function refreshQuickBooksToken($provider, $refreshToken) {
    $config = getIntegrationConfig($provider);
    
    if (!$config || !$config['client_id'] || !$config['client_secret']) {
        return null;
    }
    
    // QuickBooks uses OAuth 2.0 token refresh
    $url = "https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer";
    $data = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['access_token'])) {
            $expiresIn = $result['expires_in'] ?? 3600;
            saveTokens($provider, $result['access_token'], $result['refresh_token'] ?? $refreshToken, $expiresIn);
            return $result['access_token'];
        }
    }
    
    error_log("QuickBooks token refresh failed: " . $response);
    return null;
}

/**
 * Refresh Zoho Books token
 */
function refreshZohoBooksToken($provider, $refreshToken) {
    $config = getIntegrationConfig($provider);
    
    if (!$config || !$config['client_id'] || !$config['client_secret']) {
        return null;
    }
    
    $url = "https://accounts.zoho.com/oauth/v2/token";
    $data = [
        'refresh_token' => $refreshToken,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['access_token'])) {
            saveTokens($provider, $result['access_token'], $refreshToken, $result['expires_in'] ?? 3600);
            return $result['access_token'];
        }
    }
    
    error_log("Zoho Books token refresh failed: " . $response);
    return null;
}

/**
 * Refresh token based on provider
 */
function refreshToken($provider, $refreshToken) {
    if ($provider === 'QuickBooks') {
        return refreshQuickBooksToken($provider, $refreshToken);
    } elseif ($provider === 'ZohoBooks') {
        return refreshZohoBooksToken($provider, $refreshToken);
    }
    return null;
}

try {
    switch ($action) {
        case 'get_auth_url':
            // Generate OAuth authorization URL
            if (empty($provider)) {
                jsonResponse(['success' => false, 'message' => 'Provider required'], 400);
            }
            
            $config = getIntegrationConfig($provider);
            if (!$config || !$config['client_id'] || !$config['redirect_uri']) {
                jsonResponse(['success' => false, 'message' => 'Integration not configured'], 400);
            }
            
            if ($provider === 'QuickBooks') {
                // QuickBooks OAuth 2.0
                // Determine if using sandbox or production
                $isProduction = strpos($config['client_id'], 'sandbox') === false;
                $scopes = 'com.intuit.quickbooks.accounting';
                $state = base64_encode(json_encode(['provider' => $provider, 'user_id' => $_SESSION['user_id']]));
                
                $authUrlBase = $isProduction 
                    ? "https://appcenter.intuit.com/connect/oauth2"
                    : "https://appcenter.intuit.com/connect/oauth2"; // Same URL for both
                
                $authUrl = $authUrlBase . "?" . http_build_query([
                    'client_id' => $config['client_id'],
                    'response_type' => 'code',
                    'scope' => $scopes,
                    'redirect_uri' => $config['redirect_uri'],
                    'state' => $state
                ]);
            } elseif ($provider === 'ZohoBooks') {
                // Zoho Books OAuth 2.0
                $scopes = 'ZohoBooks.fullaccess.all';
                $state = base64_encode(json_encode(['provider' => $provider, 'user_id' => $_SESSION['user_id']]));
                $authUrl = "https://accounts.zoho.com/oauth/v2/auth?" . http_build_query([
                    'client_id' => $config['client_id'],
                    'response_type' => 'code',
                    'scope' => $scopes,
                    'redirect_uri' => $config['redirect_uri'],
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                    'state' => $state
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Unsupported provider'], 400);
            }
            
            jsonResponse(['success' => true, 'auth_url' => $authUrl]);
            break;
            
        case 'oauth_callback':
            // Handle OAuth callback
            $code = $_GET['code'] ?? '';
            $state = $_GET['state'] ?? '';
            $error = $_GET['error'] ?? '';
            $errorDescription = $_GET['error_description'] ?? '';
            
            // Handle user denial or errors
            if ($error) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Authorization Failed</title>
                    <script>
                        if (window.opener) {
                            window.opener.postMessage({
                                type: 'oauth_error', 
                                message: 'Authorization failed: <?php echo addslashes($errorDescription ?: $error); ?>'
                            }, '*');
                            window.close();
                        } else {
                            window.location.href = '<?php echo app_url('modules/accounting.php?action=integrations&oauth_error=' . urlencode($errorDescription ?: $error)); ?>';
                        }
                    </script>
                </head>
                <body>
                    <p>Authorization failed: <?php echo htmlspecialchars($errorDescription ?: $error); ?></p>
                </body>
                </html>
                <?php
                exit;
            }
            
            if (empty($code) || empty($state)) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Authorization Failed</title>
                    <script>
                        if (window.opener) {
                            window.opener.postMessage({type: 'oauth_error', message: 'Missing authorization code'}, '*');
                            window.close();
                        } else {
                            window.location.href = '<?php echo app_url('modules/accounting.php?action=integrations&oauth_error=Missing authorization code'); ?>';
                        }
                    </script>
                </head>
                <body>
                    <p>Authorization failed: Missing authorization code</p>
                </body>
                </html>
                <?php
                exit;
            }
            
            $stateData = json_decode(base64_decode($state), true);
            $provider = $stateData['provider'] ?? '';
            
            if (empty($provider)) {
                jsonResponse(['success' => false, 'message' => 'Invalid state'], 400);
            }
            
            $config = getIntegrationConfig($provider);
            if (!$config || !$config['client_id'] || !$config['client_secret'] || !$config['redirect_uri']) {
                jsonResponse(['success' => false, 'message' => 'Integration not configured'], 400);
            }
            
            if ($provider === 'QuickBooks') {
                // Exchange code for tokens
                // QuickBooks uses the same token endpoint for both sandbox and production
                $tokenUrl = "https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer";
                
                $data = [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $config['redirect_uri']
                ];
                
                $ch = curl_init($tokenUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if (isset($result['access_token'])) {
                        $expiresIn = $result['expires_in'] ?? 3600;
                        saveTokens($provider, $result['access_token'], $result['refresh_token'] ?? null, $expiresIn);
                        
                        // Return success page that closes popup and notifies parent
                        ?>
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Authorization Successful</title>
                            <script>
                                // Notify parent window and close
                                if (window.opener) {
                                    window.opener.postMessage({type: 'oauth_success', provider: '<?php echo $provider; ?>'}, '*');
                                    window.close();
                                } else {
                                    window.location.href = '<?php echo app_url('modules/accounting.php?action=integrations&oauth_callback=success&provider=' . urlencode($provider)); ?>';
                                }
                            </script>
                        </head>
                        <body>
                            <p>Authorization successful! This window will close automatically.</p>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                }
                
                // Error - return error page
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Authorization Failed</title>
                    <script>
                        if (window.opener) {
                            window.opener.postMessage({type: 'oauth_error', message: 'Failed to obtain tokens'}, '*');
                            window.close();
                        } else {
                            window.location.href = '<?php echo app_url('modules/accounting.php?action=integrations&oauth_error=Failed%20to%20obtain%20tokens'); ?>';
                        }
                    </script>
                </head>
                <body>
                    <p>Authorization failed. This window will close automatically.</p>
                </body>
                </html>
                <?php
                exit;
                
            } elseif ($provider === 'ZohoBooks') {
                // Exchange code for tokens
                $url = "https://accounts.zoho.com/oauth/v2/token";
                $data = [
                    'code' => $code,
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                    'redirect_uri' => $config['redirect_uri'],
                    'grant_type' => 'authorization_code'
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if (isset($result['access_token']) && isset($result['refresh_token'])) {
                        saveTokens($provider, $result['access_token'], $result['refresh_token'], $result['expires_in'] ?? 3600);
                        
                        // Return success page that closes popup and notifies parent
                        ?>
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Authorization Successful</title>
                            <script>
                                // Notify parent window and close
                                if (window.opener) {
                                    window.opener.postMessage({type: 'oauth_success', provider: '<?php echo $provider; ?>'}, '*');
                                    window.close();
                                } else {
                                    window.location.href = '<?php echo app_url('modules/accounting.php?action=integrations&oauth_callback=success&provider=' . urlencode($provider)); ?>';
                                }
                            </script>
                        </head>
                        <body>
                            <p>Authorization successful! This window will close automatically.</p>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                }
                
                // Error - return error page
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Authorization Failed</title>
                    <script>
                        if (window.opener) {
                            window.opener.postMessage({type: 'oauth_error', message: 'Failed to obtain tokens'}, '*');
                            window.close();
                        } else {
                            window.location.href = '<?php echo app_url('modules/accounting.php?action=integrations&oauth_error=Failed%20to%20obtain%20tokens'); ?>';
                        }
                    </script>
                </head>
                <body>
                    <p>Authorization failed. This window will close automatically.</p>
                </body>
                </html>
                <?php
                exit;
            }
            
            jsonResponse(['success' => false, 'message' => 'Unsupported provider'], 400);
            break;
            
        case 'disconnect':
            // Disconnect integration
            if (empty($provider)) {
                jsonResponse(['success' => false, 'message' => 'Provider required'], 400);
            }
            
            $stmt = $pdo->prepare("
                UPDATE accounting_integrations 
                SET access_token = NULL, 
                    refresh_token = NULL, 
                    token_expires_at = NULL, 
                    is_active = 0
                WHERE provider = ?
            ");
            $stmt->execute([$provider]);
            
            jsonResponse(['success' => true, 'message' => 'Disconnected successfully']);
            break;
            
        case 'get_status':
            // Get integration status
            if (empty($provider)) {
                jsonResponse(['success' => false, 'message' => 'Provider required'], 400);
            }
            
            $config = getIntegrationConfig($provider);
            if (!$config) {
                jsonResponse(['success' => false, 'message' => 'Integration not configured'], 404);
            }
            
            $isConnected = !empty($config['access_token']);
            $tokenExpired = false;
            if ($isConnected && $config['token_expires_at']) {
                $tokenExpired = strtotime($config['token_expires_at']) <= time();
            }
            
            jsonResponse([
                'success' => true,
                'configured' => !empty($config['client_id']),
                'connected' => $isConnected && !$tokenExpired,
                'token_expires_at' => $config['token_expires_at'],
                'is_active' => $config['is_active'] ? true : false
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    error_log("Accounting Integration OAuth Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

