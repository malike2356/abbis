<?php
/**
 * Monitoring API Endpoint for Wazuh and External Systems
 * Provides system metrics, health checks, and performance data
 * 
 * Authentication: API Key via X-API-Key header
 * Rate Limiting: 100 requests per minute per API key
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

// Set JSON response header
header('Content-Type: application/json');

// CORS headers (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * API Key Authentication
 */
function validateApiKey($apiKey) {
    if (empty($apiKey)) {
        return false;
    }
    
    $pdo = getDBConnection();
    
    try {
        // Check if api_keys table exists, if not, create it
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
                UNIQUE KEY api_key (api_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Validate API key
        $stmt = $pdo->prepare("
            SELECT * FROM api_keys 
            WHERE api_key = ? 
            AND is_active = 1 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$apiKey]);
        $keyData = $stmt->fetch();
        
        if ($keyData) {
            // Update last used
            $updateStmt = $pdo->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
            $updateStmt->execute([$keyData['id']]);
            
            return $keyData;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("API Key validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Rate Limiting
 */
function checkRateLimit($apiKeyId) {
    $pdo = getDBConnection();
    
    try {
        // Create rate_limit_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_key_time (api_key_id, request_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Get API key rate limit
        $stmt = $pdo->prepare("SELECT rate_limit FROM api_keys WHERE id = ?");
        $stmt->execute([$apiKeyId]);
        $keyData = $stmt->fetch();
        $rateLimit = $keyData['rate_limit'] ?? 100;
        
        // Count requests in last minute
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM api_rate_limits 
            WHERE api_key_id = ? 
            AND request_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$apiKeyId]);
        $result = $stmt->fetch();
        $requestCount = $result['count'] ?? 0;
        
        if ($requestCount >= $rateLimit) {
            return false;
        }
        
        // Log this request
        $logStmt = $pdo->prepare("INSERT INTO api_rate_limits (api_key_id) VALUES (?)");
        $logStmt->execute([$apiKeyId]);
        
        // Clean old logs (older than 1 hour)
        $pdo->exec("DELETE FROM api_rate_limits WHERE request_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        return true;
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return true; // Allow on error
    }
}

// Get API key from header
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

// Validate API key
$keyData = validateApiKey($apiKey);

if (!$keyData) {
    jsonResponse([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Invalid or missing API key'
    ], 401);
}

// Check rate limit
if (!checkRateLimit($keyData['id'])) {
    jsonResponse([
        'success' => false,
        'error' => 'Rate Limit Exceeded',
        'message' => 'Too many requests. Please try again later.'
    ], 429);
}

// Get endpoint and action
$endpoint = $_GET['endpoint'] ?? 'health';
$action = $_GET['action'] ?? 'status';

try {
    $pdo = getDBConnection();
    $response = [];
    
    switch ($endpoint) {
        case 'health':
            // System health check
            $response = [
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'uptime' => 'online',
                'database' => 'connected',
                'version' => APP_VERSION ?? '3.2.0'
            ];
            break;
            
        case 'metrics':
            // System metrics for Wazuh
            $stats = [];
            
            // Total reports
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM field_reports");
            $stats['total_reports'] = (int)$stmt->fetch()['total'];
            
            // Active users
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
            $stats['active_users'] = (int)$stmt->fetch()['total'];
            
            // Recent activity (last 24 hours)
            $stmt = $pdo->query("
                SELECT COUNT(*) as total 
                FROM field_reports 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stats['reports_24h'] = (int)$stmt->fetch()['total'];
            
            // Total revenue
            $stmt = $pdo->query("SELECT COALESCE(SUM(total_income), 0) as total FROM field_reports");
            $stats['total_revenue'] = (float)$stmt->fetch()['total'];
            
            // Database size
            $stmt = $pdo->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $stats['database_size_mb'] = (float)($stmt->fetch()['size_mb'] ?? 0);
            
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'metrics' => $stats
            ];
            break;
            
        case 'performance':
            // Performance metrics
            $perf = [];
            
            // Response time (PHP execution time)
            $startTime = microtime(true);
            $perf['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
            // Memory usage
            $perf['memory_usage_mb'] = round(memory_get_usage() / 1024 / 1024, 2);
            $perf['memory_peak_mb'] = round(memory_get_peak_usage() / 1024 / 1024, 2);
            
            // Database query time
            $dbStart = microtime(true);
            $pdo->query("SELECT 1");
            $perf['database_query_time_ms'] = round((microtime(true) - $dbStart) * 1000, 2);
            
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'performance' => $perf
            ];
            break;
            
        case 'alerts':
            // System alerts for Wazuh
            $alerts = [];
            
            // Check for failed login attempts
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM login_attempts 
                WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $failedLogins = (int)$stmt->fetch()['count'];
            if ($failedLogins > 10) {
                $alerts[] = [
                    'level' => 'warning',
                    'message' => 'High number of failed login attempts in the last hour',
                    'count' => $failedLogins
                ];
            }
            
            // Check database connectivity
            try {
                $pdo->query("SELECT 1");
            } catch (PDOException $e) {
                $alerts[] = [
                    'level' => 'critical',
                    'message' => 'Database connectivity issue',
                    'error' => $e->getMessage()
                ];
            }
            
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'alerts' => $alerts,
                'alert_count' => count($alerts)
            ];
            break;
            
        case 'logs':
            // System logs (last 100 entries)
            $limit = min((int)($_GET['limit'] ?? 100), 1000);
            
            // This would typically come from a logging system
            // For now, return login attempts as example
            $stmt = $pdo->prepare("
                SELECT * FROM login_attempts 
                ORDER BY attempt_time DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'logs' => $logs,
                'count' => count($logs)
            ];
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'error' => 'Invalid Endpoint',
                'message' => 'Available endpoints: health, metrics, performance, alerts, logs'
            ], 400);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $response,
        'api_key_name' => $keyData['key_name']
    ]);
    
} catch (Exception $e) {
    error_log("Monitoring API error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An error occurred while processing your request'
    ], 500);
}
?>

