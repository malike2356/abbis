<?php
/**
 * Generate API Key for External Systems
 * Admin-only endpoint to create API keys for integrations
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

try {
    $keyName = sanitizeInput($_POST['key_name'] ?? '');
    $rateLimit = (int)($_POST['rate_limit'] ?? 100);
    $expiresInDays = !empty($_POST['expires_in_days']) ? (int)$_POST['expires_in_days'] : null;
    
    if (empty($keyName)) {
        jsonResponse(['success' => false, 'message' => 'Key name is required'], 400);
    }
    
    $pdo = getDBConnection();
    
    // Ensure api_keys table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(100) NOT NULL,
            api_key VARCHAR(255) NOT NULL,
            api_secret VARCHAR(255) NOT NULL,
            permissions TEXT,
            rate_limit INT DEFAULT 100,
            is_active TINYINT(1) DEFAULT 1,
            last_used TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            UNIQUE KEY api_key (api_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Generate secure API key and secret
    $apiKey = 'abbis_' . bin2hex(random_bytes(24)); // 48 character key
    $apiSecret = bin2hex(random_bytes(32)); // 64 character secret (for HMAC if needed)
    
    // Set expiration if provided
    $expiresAt = null;
    if ($expiresInDays) {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));
    }
    
    // Insert API key
    $stmt = $pdo->prepare("
        INSERT INTO api_keys 
        (key_name, api_key, api_secret, rate_limit, expires_at, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $keyName,
        $apiKey,
        $apiSecret,
        $rateLimit,
        $expiresAt,
        $_SESSION['user_id']
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'API key generated successfully',
        'data' => [
            'key_name' => $keyName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret, // Only shown once!
            'rate_limit' => $rateLimit,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ],
        'warning' => 'Store this API secret securely. It will not be shown again.'
    ]);
    
} catch (PDOException $e) {
    error_log("API key generation error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to generate API key'], 500);
} catch (Exception $e) {
    error_log("API key generation error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>

