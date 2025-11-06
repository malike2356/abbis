<?php
/**
 * Single Sign-On (SSO) System for ABBIS and CMS Integration
 * Enables seamless authentication between CMS and ABBIS systems
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/constants.php';

class SSO {
    private $pdo;
    private $secretKey;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->secretKey = defined('SSO_SECRET_KEY') ? SSO_SECRET_KEY : 'abbis-sso-secret-key-change-in-production';
    }
    
    /**
     * Generate SSO token for CMS admin to access ABBIS
     */
    public function generateSSOToken($cmsUserId, $cmsUsername, $cmsRole) {
        // Check if CMS user is admin
        if ($cmsRole !== 'admin') {
            return ['success' => false, 'message' => 'Only CMS admins can access ABBIS system'];
        }
        
        // Find corresponding ABBIS user by username or email
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$cmsUsername]);
        $abbisUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$abbisUser) {
            return ['success' => false, 'message' => 'No corresponding ABBIS user found. Please contact administrator.'];
        }
        
        // Check if ABBIS user is admin
        if ($abbisUser['role'] !== ROLE_ADMIN) {
            return ['success' => false, 'message' => 'Your ABBIS account does not have admin privileges'];
        }
        
        // Generate SSO token
        $tokenData = [
            'cms_user_id' => $cmsUserId,
            'cms_username' => $cmsUsername,
            'abbis_user_id' => $abbisUser['id'],
            'abbis_username' => $abbisUser['username'],
            'timestamp' => time(),
            'expires' => time() + 300 // 5 minutes
        ];
        
        $token = base64_encode(json_encode($tokenData)) . '.' . hash_hmac('sha256', json_encode($tokenData), $this->secretKey);
        
        return [
            'success' => true,
            'token' => $token,
            'abbis_user_id' => $abbisUser['id']
        ];
    }
    
    /**
     * Verify and process SSO token to log into ABBIS
     */
    public function verifySSOToken($token) {
        if (empty($token)) {
            return ['success' => false, 'message' => 'Invalid SSO token'];
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return ['success' => false, 'message' => 'Invalid token format'];
        }
        
        $tokenData = json_decode(base64_decode($parts[0]), true);
        $signature = $parts[1];
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Invalid token data'];
        }
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', json_encode($tokenData), $this->secretKey);
        if (!hash_equals($expectedSignature, $signature)) {
            return ['success' => false, 'message' => 'Token signature verification failed'];
        }
        
        // Check expiration
        if (isset($tokenData['expires']) && $tokenData['expires'] < time()) {
            return ['success' => false, 'message' => 'SSO token has expired'];
        }
        
        // Get ABBIS user
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$tokenData['abbis_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found or inactive'];
        }
        
        // Verify user is admin
        if ($user['role'] !== ROLE_ADMIN) {
            return ['success' => false, 'message' => 'User does not have admin privileges'];
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set ABBIS session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['sso'] = true; // Mark as SSO login
        $_SESSION['cms_user_id'] = $tokenData['cms_user_id']; // Store CMS user ID for reference
        
        // Update last login
        $updateStmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Check if user is logged into ABBIS (for seamless navigation)
     */
    public function isLoggedIntoABBIS() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN;
    }
    
    /**
     * Get ABBIS login URL with SSO token
     */
    public function getABBISLoginURL($cmsUserId, $cmsUsername, $cmsRole) {
        $result = $this->generateSSOToken($cmsUserId, $cmsUsername, $cmsRole);
        
        if (!$result['success']) {
            return null;
        }
        
        $baseUrl = '/abbis3.2';
        if (defined('APP_URL')) {
            $parsed = parse_url(APP_URL);
            $baseUrl = $parsed['path'] ?? '/abbis3.2';
        }
        
        return $baseUrl . '/sso.php?token=' . urlencode($result['token']);
    }
}

