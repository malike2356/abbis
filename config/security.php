<?php
/**
 * Security Configuration and CSRF Protection
 */

// CSRF Token Management
class CSRF {
    public static function generateToken() {
        // Initialize session if not already started (for CLI compatibility)
        if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function getToken() {
        return self::generateToken();
    }
    
    public static function validateToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

// Secure Session Configuration
function initSecureSession() {
    // Skip session initialization in CLI mode
    if (php_sapi_name() === 'cli') {
        return;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        // Only set ini settings if headers haven't been sent
        if (!headers_sent()) {
            // Secure session configuration
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            @ini_set('session.cookie_samesite', 'Strict');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_lifetime', '0'); // Session cookie
        }
        
        session_start();
        
        // Regenerate session ID periodically (every 30 requests)
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// Initialize secure session on load (only in web mode)
if (php_sapi_name() !== 'cli') {
    initSecureSession();
}
?>

