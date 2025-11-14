<?php
/**
 * Super Admin Bypass Configuration
 * DEVELOPMENT AND MAINTENANCE ONLY
 * 
 * ⚠️ WARNING: This bypass allows full system access without authentication.
 * ONLY enable this in development environments. NEVER enable in production.
 */

// Super Admin Bypass - Control via environment variables
// Enable/Disable: Set SUPER_ADMIN_ENABLED=true or SUPER_ADMIN_ENABLED=1 to enable
// Disable: Set SUPER_ADMIN_ENABLED=false or SUPER_ADMIN_ENABLED=0 to disable (or unset)
// 
// To enable:
//   export SUPER_ADMIN_ENABLED=true
//   export SUPER_ADMIN_SECRET="your-secret-key"
//   export SUPER_ADMIN_USERNAME="your-username"
//   export SUPER_ADMIN_PASSWORD="your-password"
//
// To disable:
//   unset SUPER_ADMIN_ENABLED
//   or export SUPER_ADMIN_ENABLED=false

$superAdminEnabled = getenv('SUPER_ADMIN_ENABLED');
$isEnabled = ($superAdminEnabled === 'true' || $superAdminEnabled === '1' || $superAdminEnabled === 'yes');

define('SUPER_ADMIN_BYPASS_ENABLED', 
    defined('APP_ENV') && APP_ENV === 'development' && 
    $isEnabled &&
    getenv('SUPER_ADMIN_SECRET') !== false && 
    getenv('SUPER_ADMIN_USERNAME') !== false && 
    getenv('SUPER_ADMIN_PASSWORD') !== false);

// Super Admin Secret Key - REQUIRED from environment variable
// This is used to authenticate super admin access
// Set via: export SUPER_ADMIN_SECRET="your-secret-key"
define('SUPER_ADMIN_SECRET', getenv('SUPER_ADMIN_SECRET') ?: '');

// Super Admin Username - REQUIRED from environment variable
// Set via: export SUPER_ADMIN_USERNAME="your-username"
define('SUPER_ADMIN_USERNAME', getenv('SUPER_ADMIN_USERNAME') ?: '');

// Super Admin Password - REQUIRED from environment variable
// Set via: export SUPER_ADMIN_PASSWORD="your-password"
// NEVER commit this to version control - use environment variables only
define('SUPER_ADMIN_PASSWORD', getenv('SUPER_ADMIN_PASSWORD') ?: '');

// Super Admin IP Whitelist (optional - leave empty to allow all IPs in dev)
// Format: array of IP addresses or CIDR ranges
define('SUPER_ADMIN_IP_WHITELIST', []); // Empty = allow all in dev mode

// Super Admin Bypass Token Lifetime (seconds)
define('SUPER_ADMIN_TOKEN_LIFETIME', 3600); // 1 hour

/**
 * Check if Super Admin Bypass is enabled
 */
function isSuperAdminBypassEnabled(): bool {
    // Only enable in development mode
    if (!defined('APP_ENV') || APP_ENV !== 'development') {
        return false;
    }
    
    return SUPER_ADMIN_BYPASS_ENABLED;
}

/**
 * Check if current IP is whitelisted for Super Admin access
 */
function isSuperAdminIPWhitelisted(): bool {
    if (empty(SUPER_ADMIN_IP_WHITELIST)) {
        // No whitelist = allow all IPs in dev mode
        return true;
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($clientIP)) {
        return false;
    }
    
    foreach (SUPER_ADMIN_IP_WHITELIST as $allowedIP) {
        if ($clientIP === $allowedIP) {
            return true;
        }
        
        // Check CIDR range
        if (strpos($allowedIP, '/') !== false) {
            if (ipInRange($clientIP, $allowedIP)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if IP is in CIDR range
 */
function ipInRange($ip, $range): bool {
    list($subnet, $mask) = explode('/', $range);
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = -1 << (32 - (int)$mask);
    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

/**
 * Validate Super Admin credentials
 */
function validateSuperAdminCredentials($username, $password): bool {
    if (!isSuperAdminBypassEnabled()) {
        return false;
    }
    
    if (!isSuperAdminIPWhitelisted()) {
        error_log("Super Admin bypass attempt from non-whitelisted IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
    
    return $username === SUPER_ADMIN_USERNAME && $password === SUPER_ADMIN_PASSWORD;
}

/**
 * Generate Super Admin bypass token
 */
function generateSuperAdminToken(): string {
    $tokenData = [
        'type' => 'super_admin',
        'username' => SUPER_ADMIN_USERNAME,
        'timestamp' => time(),
        'expires' => time() + SUPER_ADMIN_TOKEN_LIFETIME,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'secret' => hash('sha256', SUPER_ADMIN_SECRET . time())
    ];
    
    return base64_encode(json_encode($tokenData)) . '.' . hash_hmac('sha256', json_encode($tokenData), SUPER_ADMIN_SECRET);
}

/**
 * Verify Super Admin bypass token
 */
function verifySuperAdminToken($token): bool {
    if (!isSuperAdminBypassEnabled()) {
        return false;
    }
    
    if (empty($token)) {
        return false;
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    $tokenData = json_decode(base64_decode($parts[0]), true);
    $signature = $parts[1];
    
    if (!$tokenData || $tokenData['type'] !== 'super_admin') {
        return false;
    }
    
    // Verify signature
    $expectedSignature = hash_hmac('sha256', json_encode($tokenData), SUPER_ADMIN_SECRET);
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }
    
    // Check expiration
    if (isset($tokenData['expires']) && $tokenData['expires'] < time()) {
        return false;
    }
    
    // Check IP (optional - can be disabled for convenience in dev)
    // if (isset($tokenData['ip']) && $tokenData['ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
    //     return false;
    // }
    
    return true;
}

