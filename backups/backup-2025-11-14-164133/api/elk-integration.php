<?php
/**
 * ELK Stack (Elasticsearch, Logstash, Kibana) Integration API
 * Sends system data and logs to Elasticsearch for analysis in Kibana
 * 
 * Supports:
 * - Real-time log shipping
 * - Structured data indexing
 * - Custom metric collection
 * - Audit trail logging
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getDBConnection();

// Ensure elk_config table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS elk_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            elasticsearch_url VARCHAR(500) NOT NULL DEFAULT 'http://localhost:9200',
            username VARCHAR(255),
            password VARCHAR(255),
            index_prefix VARCHAR(100) DEFAULT 'abbis',
            is_active TINYINT(1) DEFAULT 0,
            last_sync TIMESTAMP NULL,
            sync_settings TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default config if none exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM elk_config");
    if ($stmt->fetch()['count'] == 0) {
        $pdo->exec("INSERT INTO elk_config (elasticsearch_url, index_prefix) VALUES ('http://localhost:9200', 'abbis')");
    }
} catch (PDOException $e) {
    error_log("ELK config table creation error: " . $e->getMessage());
}

/**
 * Get ELK configuration
 */
function getElkConfig() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM elk_config LIMIT 1");
    return $stmt->fetch();
}

/**
 * Send data to Elasticsearch
 */
function sendToElasticsearch($index, $data, $documentId = null) {
    $config = getElkConfig();
    
    if (!$config || !$config['is_active']) {
        return ['success' => false, 'message' => 'ELK integration not active'];
    }
    
    $url = rtrim($config['elasticsearch_url'], '/') . '/' . $config['index_prefix'] . '-' . $index;
    
    if ($documentId) {
        $url .= '/_doc/' . $documentId;
    } else {
        $url .= '/_doc';
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // Add authentication if configured
    if (!empty($config['username']) && !empty($config['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . $config['password']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'response' => json_decode($response, true)];
    }
    
    return ['success' => false, 'error' => 'Elasticsearch error', 'code' => $httpCode, 'response' => $response];
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'index';
$indexType = $_GET['index'] ?? $_POST['index'] ?? '';

try {
    switch ($action) {
        case 'index':
            // Index data to Elasticsearch
            $indexType = $indexType ?: 'events';
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            if (empty($data)) {
                jsonResponse(['success' => false, 'message' => 'No data provided'], 400);
            }
            
            // Add timestamp
            $data['@timestamp'] = date('c'); // ISO 8601 format
            $data['source'] = 'abbis';
            
            // Generate document ID
            $docId = $data['id'] ?? md5(json_encode($data) . time());
            
            $result = sendToElasticsearch($indexType, $data, $docId);
            jsonResponse($result);
            break;
            
        case 'sync_field_reports':
            // Sync field reports to Elasticsearch
            $config = getElkConfig();
            if (!$config || !$config['is_active']) {
                jsonResponse(['success' => false, 'message' => 'ELK not configured'], 400);
            }
            
            $limit = (int)($_GET['limit'] ?? 100);
            $stmt = $pdo->prepare("
                SELECT 
                    fr.*,
                    r.rig_name,
                    c.client_name,
                    c.email as client_email
                FROM field_reports fr
                LEFT JOIN rigs r ON fr.rig_id = r.id
                LEFT JOIN clients c ON fr.client_id = c.id
                ORDER BY fr.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $synced = 0;
            $errors = 0;
            
            foreach ($reports as $report) {
                $result = sendToElasticsearch('field-reports', $report, 'report-' . $report['id']);
                if ($result['success']) {
                    $synced++;
                } else {
                    $errors++;
                }
            }
            
            // Update last sync
            $updateStmt = $pdo->prepare("UPDATE elk_config SET last_sync = NOW() WHERE id = ?");
            $updateStmt->execute([$config['id']]);
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} field reports to Elasticsearch",
                'synced' => $synced,
                'errors' => $errors
            ]);
            break;
            
        case 'sync_logs':
            // Sync system logs to Elasticsearch
            $config = getElkConfig();
            if (!$config || !$config['is_active']) {
                jsonResponse(['success' => false, 'message' => 'ELK not configured'], 400);
            }
            
            // Get login attempts as example logs
            $stmt = $pdo->query("
                SELECT * FROM login_attempts 
                ORDER BY attempt_time DESC 
                LIMIT 1000
            ");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $synced = 0;
            foreach ($logs as $log) {
                $logData = [
                    'event_type' => 'login_attempt',
                    'username' => $log['username'],
                    'ip_address' => $log['ip_address'],
                    'timestamp' => $log['attempt_time'],
                    'status' => 'failed'
                ];
                
                $result = sendToElasticsearch('logs', $logData, 'log-' . $log['id']);
                if ($result['success']) {
                    $synced++;
                }
            }
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} log entries to Elasticsearch",
                'synced' => $synced
            ]);
            break;
            
        case 'sync_metrics':
            // Sync system metrics to Elasticsearch
            $config = getElkConfig();
            if (!$config || !$config['is_active']) {
                jsonResponse(['success' => false, 'message' => 'ELK not configured'], 400);
            }
            
            // Collect system metrics
            $metrics = [
                '@timestamp' => date('c'),
                'source' => 'abbis',
                'event_type' => 'system_metrics',
                'total_reports' => 0,
                'total_users' => 0,
                'total_revenue' => 0,
                'database_size_mb' => 0
            ];
            
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM field_reports");
                $metrics['total_reports'] = (int)$stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
                $metrics['total_users'] = (int)$stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_income), 0) as total FROM field_reports");
                $metrics['total_revenue'] = (float)$stmt->fetch()['total'];
                
                $stmt = $pdo->query("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()
                ");
                $metrics['database_size_mb'] = (float)($stmt->fetch()['size_mb'] ?? 0);
            } catch (PDOException $e) {
                error_log("Metrics collection error: " . $e->getMessage());
            }
            
            $result = sendToElasticsearch('metrics', $metrics);
            
            jsonResponse([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Metrics synced to Elasticsearch' : 'Sync failed',
                'metrics' => $metrics
            ]);
            break;
            
        case 'test_connection':
            // Test Elasticsearch connection
            $config = getElkConfig();
            if (!$config) {
                jsonResponse(['success' => false, 'message' => 'ELK not configured'], 400);
            }
            
            $url = rtrim($config['elasticsearch_url'], '/') . '/_cluster/health';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            if (!empty($config['username']) && !empty($config['password'])) {
                curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . $config['password']);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $health = json_decode($response, true);
                jsonResponse([
                    'success' => true,
                    'message' => 'Connection successful',
                    'cluster_status' => $health['status'] ?? 'unknown',
                    'elasticsearch_url' => $config['elasticsearch_url']
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Connection failed',
                    'code' => $httpCode,
                    'response' => $response
                ]);
            }
            break;
            
        case 'get_config':
            // Get ELK configuration
            $config = getElkConfig();
            jsonResponse([
                'success' => true,
                'config' => [
                    'elasticsearch_url' => $config['elasticsearch_url'] ?? '',
                    'index_prefix' => $config['index_prefix'] ?? 'abbis',
                    'is_active' => (bool)($config['is_active'] ?? false),
                    'last_sync' => $config['last_sync'] ?? null
                ]
            ]);
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'message' => 'Invalid action',
                'available_actions' => [
                    'index',
                    'sync_field_reports',
                    'sync_logs',
                    'sync_metrics',
                    'test_connection',
                    'get_config'
                ]
            ], 400);
    }
    
} catch (Exception $e) {
    error_log("ELK integration error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}
?>

