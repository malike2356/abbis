<?php
/**
 * Social Authentication Configuration
 * Configure Google OAuth and Facebook OAuth credentials
 */
$page_title = 'Social Authentication Configuration';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

$message = null;
$messageType = 'success';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        try {
            $provider = $_POST['provider'] ?? '';
            
            if ($provider === 'google') {
                $clientId = trim($_POST['google_client_id'] ?? '');
                $clientSecret = trim($_POST['google_client_secret'] ?? '');
                $redirectUri = trim($_POST['google_redirect_uri'] ?? '');
                
                // Auto-generate redirect URI if not provided
                if (empty($redirectUri)) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $baseUrl = '/abbis3.2';
                    if (defined('APP_URL')) {
                        $parsed = parse_url(APP_URL);
                        $baseUrl = $parsed['path'] ?? '/abbis3.2';
                    }
                    $redirectUri = "{$protocol}://{$host}{$baseUrl}/api/social-auth.php?action=google_auth";
                }
                
                // Save to system_config
                $stmt = $pdo->prepare("
                    INSERT INTO system_config (config_key, config_value, description) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE config_value = ?, description = ?
                ");
                
                $stmt->execute(['google_client_id', $clientId, 'Google OAuth Client ID', $clientId, 'Google OAuth Client ID']);
                $stmt->execute(['google_client_secret', $clientSecret, 'Google OAuth Client Secret', $clientSecret, 'Google OAuth Client Secret']);
                $stmt->execute(['google_redirect_uri', $redirectUri, 'Google OAuth Redirect URI', $redirectUri, 'Google OAuth Redirect URI']);
                
                $message = 'Google OAuth configuration saved successfully!';
                
            } elseif ($provider === 'facebook') {
                $appId = trim($_POST['facebook_app_id'] ?? '');
                $appSecret = trim($_POST['facebook_app_secret'] ?? '');
                $redirectUri = trim($_POST['facebook_redirect_uri'] ?? '');
                
                // Auto-generate redirect URI if not provided
                if (empty($redirectUri)) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $baseUrl = '/abbis3.2';
                    if (defined('APP_URL')) {
                        $parsed = parse_url(APP_URL);
                        $baseUrl = $parsed['path'] ?? '/abbis3.2';
                    }
                    $redirectUri = "{$protocol}://{$host}{$baseUrl}/api/social-auth.php?action=facebook_auth";
                }
                
                // Save to system_config
                $stmt = $pdo->prepare("
                    INSERT INTO system_config (config_key, config_value, description) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE config_value = ?, description = ?
                ");
                
                $stmt->execute(['facebook_app_id', $appId, 'Facebook App ID', $appId, 'Facebook App ID']);
                $stmt->execute(['facebook_app_secret', $appSecret, 'Facebook App Secret', $appSecret, 'Facebook App Secret']);
                $stmt->execute(['facebook_redirect_uri', $redirectUri, 'Facebook OAuth Redirect URI', $redirectUri, 'Facebook OAuth Redirect URI']);
                
                $message = 'Facebook OAuth configuration saved successfully!';
            }
            
        } catch (PDOException $e) {
            $message = 'Error saving configuration: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current configuration
function getConfigValue($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['config_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

$googleClientId = getConfigValue($pdo, 'google_client_id');
$googleClientSecret = getConfigValue($pdo, 'google_client_secret');
$googleRedirectUri = getConfigValue($pdo, 'google_redirect_uri', '');

$facebookAppId = getConfigValue($pdo, 'facebook_app_id');
$facebookAppSecret = getConfigValue($pdo, 'facebook_app_secret');
$facebookRedirectUri = getConfigValue($pdo, 'facebook_redirect_uri', '');

// Auto-generate redirect URIs if not set
if (empty($googleRedirectUri)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = '/abbis3.2';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '/abbis3.2';
    }
    $googleRedirectUri = "{$protocol}://{$host}{$baseUrl}/api/social-auth.php?action=google_auth";
}

if (empty($facebookRedirectUri)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = '/abbis3.2';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '/abbis3.2';
    }
    $facebookRedirectUri = "{$protocol}://{$host}{$baseUrl}/api/social-auth.php?action=facebook_auth";
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="page-header">
                <i class="fas fa-share-alt"></i> Social Authentication Configuration
            </h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="panel panel-info" style="margin-bottom: 30px;">
                <div class="panel-heading">
                    <h3><i class="fas fa-info-circle"></i> About Social Authentication</h3>
                </div>
                <div class="panel-body">
                    <p>Configure OAuth credentials to enable social login options (Google, Facebook) on your login page. Users will be able to log in using their social media accounts.</p>
                    <p><strong>Note:</strong> You must create OAuth applications with Google and/or Facebook before configuring them here.</p>
                </div>
            </div>

            <!-- Google OAuth Configuration -->
            <div class="panel panel-default" style="margin-bottom: 30px;">
                <div class="panel-heading">
                    <h3><i class="fab fa-google" style="color: #ea4335;"></i> Google OAuth Configuration</h3>
                </div>
                <div class="panel-body">
                    <div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ff9800; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Setup Instructions:</h4>
                        <ol style="margin-bottom: 0; padding-left: 20px;">
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                            <li>Create a new project or select an existing one</li>
                            <li>Enable the <strong>Google+ API</strong> or <strong>Google Identity API</strong></li>
                            <li>Navigate to <strong>Credentials</strong> → <strong>Create Credentials</strong> → <strong>OAuth 2.0 Client ID</strong></li>
                            <li>Application type: <strong>Web application</strong></li>
                            <li>Add authorized redirect URI: <code><?php echo htmlspecialchars($googleRedirectUri); ?></code></li>
                            <li>Copy your <strong>Client ID</strong> and <strong>Client Secret</strong> and enter them below</li>
                        </ol>
                    </div>

                    <form method="POST">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="provider" value="google">
                        
                        <div class="form-group">
                            <label for="google_client_id" class="form-label">Google Client ID <span style="color: red;">*</span></label>
                            <input 
                                type="text" 
                                id="google_client_id" 
                                name="google_client_id" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($googleClientId); ?>" 
                                placeholder="xxxxx.apps.googleusercontent.com"
                                required>
                            <small class="form-text text-muted">Get this from Google Cloud Console → Credentials</small>
                        </div>

                        <div class="form-group">
                            <label for="google_client_secret" class="form-label">Google Client Secret <span style="color: red;">*</span></label>
                            <input 
                                type="password" 
                                id="google_client_secret" 
                                name="google_client_secret" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($googleClientSecret); ?>" 
                                placeholder="GOCSPX-xxxxxxxxxxxxx"
                                required>
                            <small class="form-text text-muted">Get this from Google Cloud Console → Credentials</small>
                        </div>

                        <div class="form-group">
                            <label for="google_redirect_uri" class="form-label">Redirect URI</label>
                            <input 
                                type="text" 
                                id="google_redirect_uri" 
                                name="google_redirect_uri" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($googleRedirectUri); ?>" 
                                readonly
                                style="background: #f8f9fa;">
                            <small class="form-text text-muted">Copy this URL and add it as an authorized redirect URI in Google Cloud Console</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Google Configuration
                            </button>
                            <?php if (!empty($googleClientId)): ?>
                                <span class="text-success" style="margin-left: 15px;">
                                    <i class="fas fa-check-circle"></i> Google OAuth is configured
                                </span>
                            <?php else: ?>
                                <span class="text-warning" style="margin-left: 15px;">
                                    <i class="fas fa-exclamation-triangle"></i> Google OAuth is not configured
                                </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Facebook OAuth Configuration -->
            <div class="panel panel-default" style="margin-bottom: 30px;">
                <div class="panel-heading">
                    <h3><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook OAuth Configuration</h3>
                </div>
                <div class="panel-body">
                    <div style="background: #e3f2fd; border: 1px solid #2196f3; border-left: 4px solid #1976d2; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Setup Instructions:</h4>
                        <ol style="margin-bottom: 0; padding-left: 20px;">
                            <li>Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
                            <li>Click <strong>My Apps</strong> → <strong>Create App</strong></li>
                            <li>Choose <strong>Consumer</strong> or <strong>Business</strong> app type</li>
                            <li>Add <strong>Facebook Login</strong> product to your app</li>
                            <li>Go to <strong>Settings</strong> → <strong>Basic</strong> to find your App ID and App Secret</li>
                            <li>In <strong>Facebook Login</strong> → <strong>Settings</strong>, add redirect URI: <code><?php echo htmlspecialchars($facebookRedirectUri); ?></code></li>
                            <li>Enter your <strong>App ID</strong> and <strong>App Secret</strong> below</li>
                        </ol>
                    </div>

                    <form method="POST">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="provider" value="facebook">
                        
                        <div class="form-group">
                            <label for="facebook_app_id" class="form-label">Facebook App ID <span style="color: red;">*</span></label>
                            <input 
                                type="text" 
                                id="facebook_app_id" 
                                name="facebook_app_id" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($facebookAppId); ?>" 
                                placeholder="1234567890123456"
                                required>
                            <small class="form-text text-muted">Get this from Facebook Developers → App Settings</small>
                        </div>

                        <div class="form-group">
                            <label for="facebook_app_secret" class="form-label">Facebook App Secret <span style="color: red;">*</span></label>
                            <input 
                                type="password" 
                                id="facebook_app_secret" 
                                name="facebook_app_secret" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($facebookAppSecret); ?>" 
                                placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                required>
                            <small class="form-text text-muted">Get this from Facebook Developers → App Settings</small>
                        </div>

                        <div class="form-group">
                            <label for="facebook_redirect_uri" class="form-label">Redirect URI</label>
                            <input 
                                type="text" 
                                id="facebook_redirect_uri" 
                                name="facebook_redirect_uri" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($facebookRedirectUri); ?>" 
                                readonly
                                style="background: #f8f9fa;">
                            <small class="form-text text-muted">Copy this URL and add it as a valid OAuth redirect URI in Facebook App Settings</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Facebook Configuration
                            </button>
                            <?php if (!empty($facebookAppId)): ?>
                                <span class="text-success" style="margin-left: 15px;">
                                    <i class="fas fa-check-circle"></i> Facebook OAuth is configured
                                </span>
                            <?php else: ?>
                                <span class="text-warning" style="margin-left: 15px;">
                                    <i class="fas fa-exclamation-triangle"></i> Facebook OAuth is not configured
                                </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Testing Section -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3><i class="fas fa-vial"></i> Test Configuration</h3>
                </div>
                <div class="panel-body">
                    <p>After configuring your OAuth credentials, test the login functionality:</p>
                    <ol>
                        <li>Go to the <a href="../login.php" target="_blank">login page</a></li>
                        <li>Click the <strong>Google</strong> or <strong>Facebook</strong> button</li>
                        <li>You should be redirected to the provider's login page</li>
                        <li>After logging in, you'll be redirected back to ABBIS</li>
                    </ol>
                    <p class="text-muted"><small><strong>Note:</strong> Make sure you've added the redirect URIs to your OAuth applications, otherwise the authentication will fail.</small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
