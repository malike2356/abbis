<?php
/**
 * Zoho Integration API
 * Handles OAuth2 authentication and data synchronization with Zoho services
 * 
 * Supports:
 * - Zoho CRM (clients, contacts, deals)
 * - Zoho Inventory (materials, products)
 * - Zoho Books (invoices, payments, expenses)
 * - Zoho Payroll (employee data, salary)
 * - Zoho HR (workers, attendance)
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

// CORS headers for Zoho callbacks
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getDBConnection();

// Ensure zoho_integration table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoho_integration (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_name VARCHAR(50) NOT NULL,
            access_token TEXT,
            refresh_token TEXT,
            token_expires_at TIMESTAMP NULL,
            client_id VARCHAR(255),
            client_secret VARCHAR(255),
            redirect_uri VARCHAR(500),
            api_domain VARCHAR(100) DEFAULT 'https://www.zohoapis.com',
            is_active TINYINT(1) DEFAULT 0,
            last_sync TIMESTAMP NULL,
            sync_settings TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY service_name (service_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Zoho integration table creation error: " . $e->getMessage());
}

/**
 * Get Zoho service configuration
 */
function getZohoConfig($serviceName) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM zoho_integration WHERE service_name = ?");
    $stmt->execute([$serviceName]);
    return $stmt->fetch();
}

/**
 * Save Zoho tokens
 */
function saveZohoTokens($serviceName, $accessToken, $refreshToken, $expiresIn = 3600) {
    global $pdo;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn - 60); // Subtract 1 minute buffer
    
    $stmt = $pdo->prepare("
        INSERT INTO zoho_integration 
        (service_name, access_token, refresh_token, token_expires_at, is_active) 
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
        access_token = VALUES(access_token),
        refresh_token = VALUES(refresh_token),
        token_expires_at = VALUES(token_expires_at),
        is_active = 1,
        updated_at = NOW()
    ");
    return $stmt->execute([$serviceName, $accessToken, $refreshToken, $expiresAt]);
}

/**
 * Get valid access token (refresh if expired)
 */
function getValidAccessToken($serviceName) {
    $config = getZohoConfig($serviceName);
    
    if (!$config || !$config['is_active']) {
        return null;
    }
    
    // Check if token expired
    if ($config['token_expires_at'] && strtotime($config['token_expires_at']) <= time()) {
        // Refresh token
        return refreshZohoToken($serviceName, $config['refresh_token']);
    }
    
    return $config['access_token'];
}

/**
 * Refresh Zoho access token
 */
function refreshZohoToken($serviceName, $refreshToken) {
    $config = getZohoConfig($serviceName);
    
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
            saveZohoTokens($serviceName, $result['access_token'], $refreshToken, $result['expires_in'] ?? 3600);
            return $result['access_token'];
        }
    }
    
    error_log("Zoho token refresh failed: " . $response);
    return null;
}

/**
 * Make Zoho API request
 */
function zohoApiRequest($serviceName, $endpoint, $method = 'GET', $data = null) {
    $accessToken = getValidAccessToken($serviceName);
    
    if (!$accessToken) {
        return ['success' => false, 'error' => 'No valid access token'];
    }
    
    $config = getZohoConfig($serviceName);
    $apiDomain = $config['api_domain'] ?? 'https://www.zohoapis.com';
    
    // Map service names to API domains
    $domainMap = [
        'crm' => 'https://www.zohoapis.com',
        'inventory' => 'https://inventory.zoho.com',
        'books' => 'https://books.zoho.com',
        'payroll' => 'https://payroll.zoho.com',
        'hr' => 'https://people.zoho.com'
    ];
    
    $baseUrl = $domainMap[strtolower($serviceName)] ?? $apiDomain;
    $url = $baseUrl . '/api/v1/' . ltrim($endpoint, '/');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Zoho-oauthtoken ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $result];
    } else {
        return ['success' => false, 'error' => $result['message'] ?? 'API request failed', 'code' => $httpCode];
    }
}

// Handle requests
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$service = $_GET['service'] ?? $_POST['service'] ?? '';

try {
    switch ($action) {
        case 'oauth_callback':
            // Handle OAuth2 callback from Zoho
            $code = $_GET['code'] ?? '';
            $serviceName = $_GET['state'] ?? '';
            
            if (empty($code) || empty($serviceName)) {
                jsonResponse(['success' => false, 'message' => 'Missing code or service'], 400);
            }
            
            $config = getZohoConfig($serviceName);
            if (!$config || !$config['client_id'] || !$config['client_secret']) {
                jsonResponse(['success' => false, 'message' => 'Service not configured'], 400);
            }
            
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
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['access_token']) && isset($result['refresh_token'])) {
                    saveZohoTokens(
                        $serviceName,
                        $result['access_token'],
                        $result['refresh_token'],
                        $result['expires_in'] ?? 3600
                    );
                    
                    jsonResponse([
                        'success' => true,
                        'message' => ucfirst($serviceName) . ' connected successfully',
                        'service' => $serviceName
                    ]);
                }
            }
            
            jsonResponse(['success' => false, 'message' => 'Failed to obtain tokens'], 400);
            break;
            
        case 'sync_crm':
            // Sync clients to Zoho CRM
            $accessToken = getValidAccessToken('crm');
            if (!$accessToken) {
                jsonResponse(['success' => false, 'message' => 'CRM not connected'], 401);
            }
            
            // Get ABBIS clients
            $stmt = $pdo->query("SELECT * FROM clients ORDER BY created_at DESC LIMIT 100");
            $clients = $stmt->fetchAll();
            
            $synced = 0;
            foreach ($clients as $client) {
                $contactData = [
                    'First_Name' => explode(' ', $client['client_name'])[0] ?? $client['client_name'],
                    'Last_Name' => implode(' ', array_slice(explode(' ', $client['client_name']), 1)) ?: '',
                    'Email' => $client['email'] ?? '',
                    'Phone' => $client['contact_number'] ?? '',
                    'Mailing_Street' => $client['address'] ?? '',
                    'Description' => 'Synced from ABBIS'
                ];
                
                $result = zohoApiRequest('crm', '/Contacts', 'POST', ['data' => [$contactData]]);
                if ($result['success']) {
                    $synced++;
                }
            }
            
            // Update last sync
            $stmt = $pdo->prepare("UPDATE zoho_integration SET last_sync = NOW() WHERE service_name = 'crm'");
            $stmt->execute();
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} clients to Zoho CRM",
                'synced' => $synced,
                'total' => count($clients)
            ]);
            break;
            
        case 'sync_inventory':
            // Sync materials to Zoho Inventory
            $accessToken = getValidAccessToken('inventory');
            if (!$accessToken) {
                jsonResponse(['success' => false, 'message' => 'Inventory not connected'], 401);
            }
            
            $stmt = $pdo->query("SELECT * FROM materials_inventory");
            $materials = $stmt->fetchAll();
            
            $synced = 0;
            foreach ($materials as $material) {
                $productData = [
                    'name' => ucfirst(str_replace('_', ' ', $material['material_type'])),
                    'sku' => 'ABBIS-' . strtoupper($material['material_type']),
                    'rate' => (float)$material['unit_cost'],
                    'quantity' => (int)$material['quantity_remaining'],
                    'description' => 'Material synced from ABBIS'
                ];
                
                $result = zohoApiRequest('inventory', '/items', 'POST', $productData);
                if ($result['success']) {
                    $synced++;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE zoho_integration SET last_sync = NOW() WHERE service_name = 'inventory'");
            $stmt->execute();
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} materials to Zoho Inventory",
                'synced' => $synced
            ]);
            break;
            
        case 'sync_books':
            // Sync invoices to Zoho Books
            $accessToken = getValidAccessToken('books');
            if (!$accessToken) {
                jsonResponse(['success' => false, 'message' => 'Books not connected'], 401);
            }
            
            // Get recent field reports with income
            $stmt = $pdo->query("
                SELECT fr.*, c.client_name, c.email, c.contact_number 
                FROM field_reports fr 
                LEFT JOIN clients c ON fr.client_id = c.id 
                WHERE fr.total_income > 0 
                ORDER BY fr.report_date DESC 
                LIMIT 50
            ");
            $reports = $stmt->fetchAll();
            
            $synced = 0;
            foreach ($reports as $report) {
                $invoiceData = [
                    'customer_name' => $report['client_name'] ?? 'Direct Client',
                    'line_items' => [[
                        'name' => 'Borehole Drilling Service - ' . $report['site_name'],
                        'quantity' => 1,
                        'rate' => (float)$report['total_income']
                    ]],
                    'date' => $report['report_date'],
                    'due_date' => date('Y-m-d', strtotime($report['report_date'] . ' +30 days')),
                    'reference_number' => $report['report_id']
                ];
                
                $result = zohoApiRequest('books', '/invoices', 'POST', $invoiceData);
                if ($result['success']) {
                    $synced++;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE zoho_integration SET last_sync = NOW() WHERE service_name = 'books'");
            $stmt->execute();
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} invoices to Zoho Books",
                'synced' => $synced
            ]);
            break;
            
        case 'sync_payroll':
            // Sync workers to Zoho Payroll
            $accessToken = getValidAccessToken('payroll');
            if (!$accessToken) {
                jsonResponse(['success' => false, 'message' => 'Payroll not connected'], 401);
            }
            
            $stmt = $pdo->query("SELECT * FROM workers WHERE status = 'active'");
            $workers = $stmt->fetchAll();
            
            $synced = 0;
            foreach ($workers as $worker) {
                $employeeData = [
                    'employee_name' => $worker['worker_name'],
                    'employee_id' => 'ABBIS-' . $worker['id'],
                    'contact_number' => $worker['contact_number'] ?? '',
                    'designation' => $worker['role'],
                    'pay_rate' => (float)$worker['default_rate']
                ];
                
                $result = zohoApiRequest('payroll', '/employees', 'POST', $employeeData);
                if ($result['success']) {
                    $synced++;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE zoho_integration SET last_sync = NOW() WHERE service_name = 'payroll'");
            $stmt->execute();
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} workers to Zoho Payroll",
                'synced' => $synced
            ]);
            break;
            
        case 'sync_hr':
            // Sync workers to Zoho HR
            $accessToken = getValidAccessToken('hr');
            if (!$accessToken) {
                jsonResponse(['success' => false, 'message' => 'HR not connected'], 401);
            }
            
            $stmt = $pdo->query("SELECT * FROM workers WHERE status = 'active'");
            $workers = $stmt->fetchAll();
            
            $synced = 0;
            foreach ($workers as $worker) {
                $employeeData = [
                    'first_name' => explode(' ', $worker['worker_name'])[0] ?? $worker['worker_name'],
                    'last_name' => implode(' ', array_slice(explode(' ', $worker['worker_name']), 1)) ?: '',
                    'employee_id' => 'ABBIS-' . $worker['id'],
                    'phone_number' => $worker['contact_number'] ?? '',
                    'job_title' => $worker['role']
                ];
                
                $result = zohoApiRequest('hr', '/people', 'POST', $employeeData);
                if ($result['success']) {
                    $synced++;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE zoho_integration SET last_sync = NOW() WHERE service_name = 'hr'");
            $stmt->execute();
            
            jsonResponse([
                'success' => true,
                'message' => "Synced {$synced} workers to Zoho HR",
                'synced' => $synced
            ]);
            break;
            
        case 'get_status':
            // Get integration status for all services
            $services = ['crm', 'inventory', 'books', 'payroll', 'hr'];
            $status = [];
            
            foreach ($services as $service) {
                $config = getZohoConfig($service);
                $status[$service] = [
                    'connected' => $config && $config['is_active'] && getValidAccessToken($service) !== null,
                    'last_sync' => $config['last_sync'] ?? null,
                    'configured' => !empty($config['client_id'])
                ];
            }
            
            jsonResponse(['success' => true, 'status' => $status]);
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'message' => 'Invalid action',
                'available_actions' => [
                    'oauth_callback',
                    'sync_crm',
                    'sync_inventory',
                    'sync_books',
                    'sync_payroll',
                    'sync_hr',
                    'get_status'
                ]
            ], 400);
    }
    
} catch (Exception $e) {
    error_log("Zoho integration error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}
?>

