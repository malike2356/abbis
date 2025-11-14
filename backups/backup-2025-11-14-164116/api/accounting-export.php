<?php
/**
 * Accounting Export API
 * Exports journal entries to QuickBooks and Zoho Books
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
 * Get valid access token
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
 * Refresh token (simplified - reuse from oauth handler)
 */
function refreshToken($provider, $refreshToken) {
    global $pdo;
    $config = getIntegrationConfig($provider);
    
    if (!$config || !$config['client_id'] || !$config['client_secret']) {
        return null;
    }
    
    if ($provider === 'QuickBooks') {
        $url = "https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer";
        $data = ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken];
        
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
                $expiresAt = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 3600) - 60);
                $newRefreshToken = $result['refresh_token'] ?? $refreshToken;
                $stmt = $pdo->prepare("UPDATE accounting_integrations SET access_token = ?, refresh_token = ?, token_expires_at = ? WHERE provider = ?");
                $stmt->execute([
                    Crypto::encrypt($result['access_token']),
                    $newRefreshToken ? Crypto::encrypt($newRefreshToken) : null,
                    $expiresAt,
                    $provider
                ]);
                return $result['access_token'];
            }
        }
    } elseif ($provider === 'ZohoBooks') {
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
                $expiresAt = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 3600) - 60);
                $stmt = $pdo->prepare("UPDATE accounting_integrations SET access_token = ?, token_expires_at = ? WHERE provider = ?");
                $stmt->execute([Crypto::encrypt($result['access_token']), $expiresAt, $provider]);
                return $result['access_token'];
            }
        }
    }
    
    return null;
}

/**
 * Get company/realm ID from QuickBooks
 */
function getQuickBooksCompanyId($accessToken) {
    global $pdo;
    
    // Check if we stored the company ID in the database
    $config = getIntegrationConfig('QuickBooks');
    if ($config && !empty($config['company_id'])) {
        return $config['company_id'];
    }
    
    // QuickBooks requires company/realm ID
    // First, get the realm ID from the access token (it's in the token response)
    // If not available, query the API to get company info
    // Determine if using sandbox or production based on token or config
    $isProduction = true; // Default to production
    if ($config) {
        // Check if client_id indicates sandbox
        $isProduction = strpos($config['client_id'] ?? '', 'sandbox') === false;
    }
    
    $baseUrl = $isProduction 
        ? "https://quickbooks.api.intuit.com"
        : "https://sandbox-quickbooks.api.intuit.com";
    
    // Query for company info - this requires the realm ID
    // For QuickBooks, we need to get the company ID from the user's connected companies
    // The realm ID is usually in the token response, but we'll query the API
    
    // Alternative: Query the discovery API or company info
    // Note: QuickBooks API requires realm ID in the URL
    // We'll need to store this during initial connection or query it here
    
    // For now, try to get it from the API using the query endpoint
    $url = "$baseUrl/v3/company/info";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $companyId = $result['QueryResponse']['CompanyInfo'][0]['Id'] ?? null;
        
        // Store company ID for future use
        if ($companyId) {
            try {
                // Check if column exists first
                $stmt = $pdo->prepare("UPDATE accounting_integrations SET company_id = ? WHERE provider = 'QuickBooks'");
                $stmt->execute([$companyId]);
            } catch (PDOException $e) {
                // Column might not exist, try to add it
                try {
                    $pdo->exec("ALTER TABLE accounting_integrations ADD COLUMN company_id VARCHAR(100) DEFAULT NULL AFTER redirect_uri");
                    $stmt = $pdo->prepare("UPDATE accounting_integrations SET company_id = ? WHERE provider = 'QuickBooks'");
                    $stmt->execute([$companyId]);
                } catch (PDOException $e2) {
                    // Ignore
                }
            }
        }
        
        return $companyId;
    }
    
    // If company info query fails, return null
    // User may need to manually set company ID
    return null;
}

/**
 * Export to QuickBooks
 */
function exportToQuickBooks($entryIds = []) {
    global $pdo;
    
    $accessToken = getValidAccessToken('QuickBooks');
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Not connected to QuickBooks'];
    }
    
    // Get company ID (should be stored, but for now we'll try to get it)
    $companyId = getQuickBooksCompanyId($accessToken);
    if (!$companyId) {
        return ['success' => false, 'message' => 'Unable to determine QuickBooks company ID'];
    }
    
    // Build query to get journal entries
    if (!empty($entryIds)) {
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $sql = "SELECT je.* FROM journal_entries je WHERE je.id IN ($placeholders) ORDER BY je.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($entryIds);
    } else {
        // Export last 100 entries by default
        $sql = "SELECT je.* FROM journal_entries je ORDER BY je.created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    $entries = $stmt->fetchAll();
    
    $synced = 0;
    $errors = [];
    
    foreach ($entries as $entry) {
        // Get journal entry lines properly
        $stmt = $pdo->prepare("
            SELECT jel.*, coa.account_code, coa.account_name
            FROM journal_entry_lines jel
            JOIN chart_of_accounts coa ON jel.account_id = coa.id
            WHERE jel.journal_entry_id = ?
            ORDER BY jel.id
        ");
        $stmt->execute([$entry['id']]);
        $linesData = $stmt->fetchAll();
        
        // Build QuickBooks journal entry lines
        $lines = [];
        foreach ($linesData as $line) {
            if ($line['debit'] > 0) {
                $lines[] = [
                    'Id' => count($lines) + 1,
                    'Description' => $line['memo'] ?: $entry['description'],
                    'Amount' => (float)$line['debit'],
                    'DetailType' => 'JournalEntryLineDetail',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Debit',
                        'AccountRef' => [
                            'value' => $line['account_code'],
                            'name' => $line['account_name']
                        ]
                    ]
                ];
            }
            if ($line['credit'] > 0) {
                $lines[] = [
                    'Id' => count($lines) + 1,
                    'Description' => $line['memo'] ?: $entry['description'],
                    'Amount' => (float)$line['credit'],
                    'DetailType' => 'JournalEntryLineDetail',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Credit',
                        'AccountRef' => [
                            'value' => $line['account_code'],
                            'name' => $line['account_name']
                        ]
                    ]
                ];
            }
        }
        
        // Create JournalEntry in QuickBooks format
        $journalEntry = [
            'DocNumber' => $entry['entry_number'],
            'TxnDate' => $entry['entry_date'],
            'Line' => $lines
        ];
        
        if (!empty($entry['description'])) {
            $journalEntry['PrivateNote'] = $entry['description'];
        }
        
        // Determine if using sandbox or production
        $config = getIntegrationConfig('QuickBooks');
        $isProduction = strpos($config['client_id'] ?? '', 'sandbox') === false;
        $baseUrl = $isProduction 
            ? "https://quickbooks.api.intuit.com"
            : "https://sandbox-quickbooks.api.intuit.com";
        
        // Send to QuickBooks API
        // Note: QuickBooks API URL format: /v3/company/{realmId}/journalentry
        $url = "$baseUrl/v3/company/$companyId/journalentry";
        
        // QuickBooks requires the request body in a specific format
        $requestBody = [
            'JournalEntry' => $journalEntry
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $synced++;
        } else {
            $errorMsg = $response;
            try {
                $errorData = json_decode($response, true);
                if ($errorData) {
                    if (isset($errorData['Fault']['Error'][0]['Message'])) {
                        $errorMsg = $errorData['Fault']['Error'][0]['Message'];
                    } elseif (isset($errorData['Fault']['Error'][0]['Detail'])) {
                        $errorMsg = $errorData['Fault']['Error'][0]['Detail'];
                    } elseif (isset($errorData['message'])) {
                        $errorMsg = $errorData['message'];
                    }
                }
            } catch (Exception $e) {
                // Keep original error message
            }
            $errors[] = "Entry {$entry['entry_number']}: " . $errorMsg;
        }
    }
    
    return [
        'success' => $synced > 0,
        'synced' => $synced,
        'total' => count($entries),
        'errors' => $errors
    ];
}

/**
 * Export to Zoho Books
 */
function exportToZohoBooks($entryIds = []) {
    global $pdo;
    
    $accessToken = getValidAccessToken('ZohoBooks');
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Not connected to Zoho Books'];
    }
    
    // Get organization ID (should be stored during connection)
    // For Zoho Books, we need to get it from the API first
    $orgUrl = "https://books.zoho.com/api/v3/organizations";
    $ch = curl_init($orgUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Zoho-oauthtoken ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Unable to access Zoho Books organization'];
    }
    
    $orgs = json_decode($response, true);
    $organizationId = $orgs['organizations'][0]['organization_id'] ?? null;
    
    if (!$organizationId) {
        return ['success' => false, 'message' => 'Organization ID not found'];
    }
    
    // Build query to get journal entries
    if (!empty($entryIds)) {
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $sql = "SELECT je.* FROM journal_entries je WHERE je.id IN ($placeholders) ORDER BY je.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($entryIds);
    } else {
        $sql = "SELECT je.* FROM journal_entries je ORDER BY je.created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    $entries = $stmt->fetchAll();
    
    $synced = 0;
    $errors = [];
    
    foreach ($entries as $entry) {
        // Get journal entry lines properly
        $stmt = $pdo->prepare("
            SELECT jel.*, coa.account_code, coa.account_name
            FROM journal_entry_lines jel
            JOIN chart_of_accounts coa ON jel.account_id = coa.id
            WHERE jel.journal_entry_id = ?
            ORDER BY jel.id
        ");
        $stmt->execute([$entry['id']]);
        $linesData = $stmt->fetchAll();
        
        if (empty($linesData)) {
            $errors[] = "Entry {$entry['entry_number']}: No line items found";
            continue;
        }
        
        // Build Zoho Books journal entry line items
        // Note: Zoho Books requires account_id (internal ID), not account_code
        // For now, we'll use account_code and let Zoho map it, or create accounts first
        $lineItems = [];
        foreach ($linesData as $line) {
            $lineItems[] = [
                'account_id' => $line['account_code'], // Will need to map to Zoho account ID
                'account_name' => $line['account_name'],
                'debit_amount' => (float)$line['debit'],
                'credit_amount' => (float)$line['credit'],
                'description' => $line['memo'] ?: $entry['description']
            ];
        }
        
        // Create journal entry in Zoho Books format
        $journalEntry = [
            'journal_date' => $entry['entry_date'],
            'reference_number' => $entry['entry_number'],
            'notes' => $entry['description'] ?: 'Journal entry from ABBIS',
            'line_items' => $lineItems
        ];
        
        // Send to Zoho Books API
        $url = "https://books.zoho.com/api/v3/books/$organizationId/journalentries";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Zoho-oauthtoken ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($journalEntry));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $synced++;
        } else {
            $errorMsg = $response;
            try {
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['message'])) {
                    $errorMsg = $errorData['message'];
                }
            } catch (Exception $e) {
                // Keep original error message
            }
            $errors[] = "Entry {$entry['entry_number']}: " . $errorMsg;
        }
    }
    
    return [
        'success' => $synced > 0,
        'synced' => $synced,
        'total' => count($entries),
        'errors' => $errors
    ];
}

try {
    switch ($action) {
        case 'export':
            if (empty($provider)) {
                jsonResponse(['success' => false, 'message' => 'Provider required'], 400);
            }
            
            $entryIds = $_POST['entry_ids'] ?? [];
            if (is_string($entryIds)) {
                $entryIds = json_decode($entryIds, true) ?: [];
            }
            
            if ($provider === 'QuickBooks') {
                $result = exportToQuickBooks($entryIds);
            } elseif ($provider === 'ZohoBooks') {
                $result = exportToZohoBooks($entryIds);
            } else {
                jsonResponse(['success' => false, 'message' => 'Unsupported provider'], 400);
            }
            
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    error_log("Accounting Export Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

