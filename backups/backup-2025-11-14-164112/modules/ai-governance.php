<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/AI/bootstrap.php';

$auth->requireAuth();
$auth->requirePermission('system.admin');

$page_title = 'AI Governance & Audit';
$pdo = getDBConnection();
$errors = [];
$messages = [];
$action = $_GET['action'] ?? '';

// Handle log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logs'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        try {
            $deleteType = $_POST['delete_type'] ?? 'selected';
            $logIds = $_POST['log_ids'] ?? [];
            
            if ($deleteType === 'selected' && !empty($logIds)) {
                // Delete selected logs
                $placeholders = implode(',', array_fill(0, count($logIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM ai_usage_logs WHERE id IN ($placeholders)");
                $stmt->execute($logIds);
                $deletedCount = $stmt->rowCount();
                $messages[] = "Successfully deleted {$deletedCount} log(s).";
            } elseif ($deleteType === 'old') {
                // Delete logs older than 30 days
                $stmt = $pdo->prepare("DELETE FROM ai_usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $deletedCount = $stmt->rowCount();
                $messages[] = "Successfully deleted {$deletedCount} log(s) older than 30 days.";
            } elseif ($deleteType === 'zero_tokens') {
                // Delete logs with zero tokens
                $stmt = $pdo->prepare("DELETE FROM ai_usage_logs WHERE total_tokens = 0 OR total_tokens IS NULL");
                $stmt->execute();
                $deletedCount = $stmt->rowCount();
                $messages[] = "Successfully deleted {$deletedCount} log(s) with zero tokens.";
            } elseif ($deleteType === 'all') {
                // Delete all logs (with confirmation)
                if (isset($_POST['confirm_delete_all']) && $_POST['confirm_delete_all'] === 'yes') {
                    $stmt = $pdo->query("DELETE FROM ai_usage_logs");
                    $deletedCount = $stmt->rowCount();
                    $messages[] = "Successfully deleted all {$deletedCount} log(s).";
                } else {
                    $errors[] = 'Please confirm deletion of all logs.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete logs: ' . $e->getMessage();
            error_log('AI Logs Deletion Error: ' . $e->getMessage());
        }
    }
}

// Auto-delete logs older than 30 days (runs automatically on page load, but only once per day)
if ($action === 'logs') {
    try {
        // Check if we've already run cleanup today
        $cleanupKey = 'ai_logs_cleanup_' . date('Y-m-d');
        $lastCleanup = $_SESSION[$cleanupKey] ?? null;
        
        if (!$lastCleanup) {
            $stmt = $pdo->prepare("DELETE FROM ai_usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $autoDeleted = $stmt->rowCount();
            $_SESSION[$cleanupKey] = true;
            
            if ($autoDeleted > 0) {
                error_log("[AI Logs] Auto-deleted {$autoDeleted} log(s) older than 30 days.");
            }
        }
    } catch (PDOException $e) {
        error_log('AI Logs Auto-Cleanup Error: ' . $e->getMessage());
    }
}

// Handle CSV export
if ($action === 'logs' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ai-usage-logs-' . date('Y-m-d') . '.csv"');
    
    $filters = [
        'provider' => trim($_GET['provider'] ?? ''),
        'feature' => trim($_GET['feature'] ?? ''),
        'status' => $_GET['status'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'user' => trim($_GET['user'] ?? ''),
    ];
    
    $where = [];
    $params = [];
    
    if ($filters['provider'] !== '') {
        $where[] = 'provider = :provider';
        $params[':provider'] = $filters['provider'];
    }
    if ($filters['feature'] !== '') {
        $where[] = 'action = :feature';
        $params[':feature'] = $filters['feature'];
    }
    if ($filters['status'] === 'success') {
        $where[] = 'is_success = 1';
    } elseif ($filters['status'] === 'error') {
        $where[] = 'is_success = 0';
    }
    if ($filters['user'] !== '') {
        $where[] = '(role LIKE :user OR user_id IN (SELECT id FROM users WHERE username LIKE :user))';
        $params[':user'] = '%' . $filters['user'] . '%';
    }
    if ($filters['date_from'] !== '') {
        $where[] = 'created_at >= :date_from';
        $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if ($filters['date_to'] !== '') {
        $where[] = 'created_at <= :date_to';
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    $whereSql = $where ? implode(' AND ', $where) : '1=1';
    
    try {
        $exportStmt = $pdo->prepare("
            SELECT 
                l.created_at,
                u.username,
                u.full_name,
                l.role,
                l.action,
                l.provider,
                l.prompt_tokens,
                l.completion_tokens,
                l.total_tokens,
                l.latency_ms,
                l.is_success,
                l.error_code,
                l.metadata_json
            FROM ai_usage_logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE {$whereSql}
            ORDER BY l.created_at DESC
        ");
        foreach ($params as $key => $value) {
            $exportStmt->bindValue($key, $value);
        }
        $exportStmt->execute();
        $exportLogs = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Timestamp', 'Username', 'Full Name', 'Role', 'Feature', 'Provider',
            'Prompt Tokens', 'Completion Tokens', 'Total Tokens', 'Latency (ms)',
            'Status', 'Error Code', 'Metadata'
        ]);
        
        foreach ($exportLogs as $log) {
            fputcsv($output, [
                $log['created_at'], $log['username'] ?? '', $log['full_name'] ?? '',
                $log['role'] ?? '', $log['action'], $log['provider'] ?? '',
                $log['prompt_tokens'] ?? 0, $log['completion_tokens'] ?? 0,
                $log['total_tokens'] ?? 0, $log['latency_ms'] ?? '',
                $log['is_success'] ? 'Success' : 'Error', $log['error_code'] ?? '',
                $log['metadata_json'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: text/html');
        die('Error exporting logs: ' . $e->getMessage());
    }
}

$availableProviders = AI_DEFAULT_PROVIDER_FAILOVER;
$envFailover = getenv('AI_PROVIDER_FAILOVER');
if ($envFailover) {
    $availableProviders = array_values(array_filter(array_map('trim', explode(',', $envFailover))));
}
$availableProviders = array_values(array_unique(array_map('strtolower', $availableProviders)));

$providerConfigs = ai_load_provider_configs();
$selectedProviderKey = strtolower(trim($_POST['provider_key'] ?? ''));

// Handle encryption key generation (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_encryption_key'])) {
    header('Content-Type: application/json');
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    $newKey = base64_encode(random_bytes(32));
    echo json_encode(['success' => true, 'key' => $newKey]);
    exit;
}

// Handle save encryption key (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_encryption_key'])) {
    header('Content-Type: application/json');
    try {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        $keyToSave = trim($_POST['encryption_key'] ?? '');
        if (empty($keyToSave)) {
            echo json_encode(['success' => false, 'message' => 'Encryption key is required']);
            exit;
        }
        
        $rootPath = dirname(__DIR__);
        $secretsDir = $rootPath . '/config/secrets';
        $keyFile = $secretsDir . '/encryption.key';
        
        // Ensure directory exists
        if (!is_dir($secretsDir)) {
            $umask = umask(0);
            $dirCreated = @mkdir($secretsDir, 0777, true);
            umask($umask);
            if (!$dirCreated) {
                $lastError = error_get_last();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to create secrets directory: ' . ($lastError['message'] ?? 'Unknown error') . '. Please run: mkdir -p ' . $secretsDir . ' && chmod 777 ' . $secretsDir
                ]);
                exit;
            }
        }
        
        // Ensure directory is writable
        @chmod($secretsDir, 0777);
        if (!is_writable($secretsDir)) {
            $dirPerms = fileperms($secretsDir);
            $dirOwner = fileowner($secretsDir);
            $dirGroup = filegroup($secretsDir);
            echo json_encode([
                'success' => false, 
                'message' => 'Secrets directory is not writable. Permissions: ' . substr(sprintf('%o', $dirPerms), -4) . ', Owner: ' . $dirOwner . '. Please run: chmod 777 ' . $secretsDir . ' or: sudo chown daemon:daemon ' . $secretsDir
            ]);
            exit;
        }
        
        // Strategy: Try to write directly first, if that fails, try temp file approach
        $writeSuccess = false;
        $lastError = null;
        
        // First, try to handle existing file
        if (file_exists($keyFile)) {
            // Try to make it writable
            @chmod($keyFile, 0666);
            
            // If still not writable, try to delete it
            if (!is_writable($keyFile)) {
                @unlink($keyFile);
            }
        }
        
        // Attempt 1: Write directly to the file
        $writeResult = @file_put_contents($keyFile, $keyToSave, LOCK_EX);
        if ($writeResult !== false && file_exists($keyFile) && filesize($keyFile) > 0) {
            $writeSuccess = true;
        } else {
            $lastError = error_get_last();
            
            // Attempt 2: Write to temp file, then rename
            $tempFile = $secretsDir . '/.encryption.key.' . time() . '.tmp';
            $tempWriteResult = @file_put_contents($tempFile, $keyToSave, LOCK_EX);
            
            if ($tempWriteResult !== false && file_exists($tempFile)) {
                // Remove old file if it exists
                if (file_exists($keyFile)) {
                    @unlink($keyFile);
                }
                
                // Rename temp to final
                $renamed = @rename($tempFile, $keyFile);
                if ($renamed && file_exists($keyFile)) {
                    $writeSuccess = true;
                } else {
                    // Clean up temp file
                    @unlink($tempFile);
                }
            }
        }
        
        // If both attempts failed, provide detailed error
        if (!$writeSuccess) {
            $fileExists = file_exists($keyFile);
            $dirWritable = is_writable($secretsDir);
            $fileWritable = $fileExists ? is_writable($keyFile) : false;
            
            $errorDetails = [];
            $errorDetails[] = 'Directory writable: ' . ($dirWritable ? 'Yes' : 'No');
            if ($fileExists) {
                $errorDetails[] = 'File exists: Yes';
                $errorDetails[] = 'File writable: ' . ($fileWritable ? 'Yes' : 'No');
                $filePerms = substr(sprintf('%o', fileperms($keyFile)), -4);
                $errorDetails[] = 'File permissions: ' . $filePerms;
                try {
                    $fileOwner = fileowner($keyFile);
                    if (function_exists('posix_getpwuid')) {
                        $ownerInfo = @posix_getpwuid($fileOwner);
                        $errorDetails[] = 'File owner: ' . ($ownerInfo ? $ownerInfo['name'] : "UID $fileOwner");
                    } else {
                        $errorDetails[] = 'File owner: UID ' . $fileOwner;
                    }
                } catch (Exception $e) {
                    // posix functions might not be available or might throw
                }
            } else {
                $errorDetails[] = 'File exists: No';
            }
            
            if ($lastError) {
                $errorDetails[] = 'PHP Error: ' . $lastError['message'];
            }
            
            $errorMsg = 'Failed to write encryption key file. ';
            $errorMsg .= implode('. ', $errorDetails) . '. ';
            $errorMsg .= 'Please run: sudo chmod 777 ' . $secretsDir . ' && sudo chown daemon:daemon ' . $secretsDir . ' or delete ' . $keyFile . ' if it exists.';
            
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        
        // Set restrictive permissions on the file
        @chmod($keyFile, 0600);
        
        // Verify the file was written correctly
        if (!file_exists($keyFile) || filesize($keyFile) === 0) {
            echo json_encode(['success' => false, 'message' => 'File was created but appears to be empty. Please try again.']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Encryption key saved successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle save encryption key (regular form submission - fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_encryption_key_form'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $keyToSave = trim($_POST['encryption_key'] ?? '');
        if (empty($keyToSave)) {
            $errors[] = 'Encryption key is required.';
        } else {
            $rootPath = dirname(__DIR__);
            $secretsDir = $rootPath . '/config/secrets';
            $keyFile = $secretsDir . '/encryption.key';
            
            if (!is_dir($secretsDir)) {
                $umask = umask(0);
                $dirCreated = @mkdir($secretsDir, 0777, true);
                umask($umask);
                if (!$dirCreated) {
                    $errors[] = 'Failed to create secrets directory. Please run: <code>mkdir -p ' . $secretsDir . ' && chmod 777 ' . $secretsDir . '</code>';
                } else {
                    @chmod($secretsDir, 0777);
                }
            } else {
                @chmod($secretsDir, 0777);
            }
            
            if (empty($errors) && is_writable($secretsDir)) {
                $writeResult = @file_put_contents($keyFile, $keyToSave);
                if ($writeResult !== false) {
                    @chmod($keyFile, 0600);
                    $messages[] = 'Encryption key saved successfully! The system is now ready to encrypt API keys.';
                } else {
                    $errors[] = 'Failed to write encryption key file. Check permissions.';
                }
            } elseif (empty($errors)) {
                $errors[] = 'Secrets directory is not writable. Please run: <code>chmod 777 ' . $secretsDir . '</code>';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_provider'])) {
    $providerKey = strtolower(trim($_POST['provider_key'] ?? ''));
    if ($providerKey === '') {
        $errors[] = 'Provider key is required.';
    }

    if (empty($errors)) {
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $dailyLimit = $_POST['daily_limit'] === '' ? null : (int) $_POST['daily_limit'];
        $monthlyLimit = $_POST['monthly_limit'] === '' ? null : (int) $_POST['monthly_limit'];
        $priority = $_POST['failover_priority'] === '' ? 100 : (int) $_POST['failover_priority'];
        $settingsInput = $_POST['settings'] ?? [];
        $apiKeyRaw = trim($settingsInput['api_key'] ?? '');
        unset($settingsInput['api_key']);

        $currentConfig = $providerConfigs[$providerKey] ?? null;
        $existingSettings = $currentConfig['settings'] ?? [];
        $existingEncryptedKey = $currentConfig['api_key_encrypted'] ?? null;

        $normalisedSettings = [];

        $normaliseCommon = static function (array &$target, array $input, array $fields): void {
            foreach ($fields as $field) {
                $value = trim((string) ($input[$field] ?? ''));
                if ($value !== '') {
                    if ($field === 'timeout') {
                        $target[$field] = max(5, (int) $value);
                    } else {
                        $target[$field] = $value;
                    }
                }
            }
        };

        switch ($providerKey) {
            case 'openai':
            case 'deepseek':
            case 'gemini':
                $normaliseCommon($normalisedSettings, $settingsInput, ['model', 'base_url', 'timeout']);
                break;
            case 'ollama':
                $normaliseCommon($normalisedSettings, $settingsInput, ['base_url', 'model', 'timeout']);
                break;
            default:
                foreach ($settingsInput as $field => $value) {
                    $field = trim((string) $field);
                    if ($field === '') continue;
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $normalisedSettings[$field] = $value;
                    }
                }
                break;
        }

        if ($apiKeyRaw !== '') {
            try {
                $normalisedSettings['api_key'] = Crypto::encrypt($apiKeyRaw);
            } catch (Throwable $e) {
                $errors[] = 'Unable to encrypt and store the API key. The encryption key is not configured.';
                $errors[] = '<a href="admin/setup-encryption-key.php" style="color: #0ea5e9; text-decoration: underline;">👉 Click here to set up the encryption key</a>';
            }
        } elseif ($existingEncryptedKey) {
            $normalisedSettings['api_key'] = $existingEncryptedKey;
        }

        $settingsJson = null;
        if (empty($errors) && !empty($normalisedSettings)) {
            $settingsJson = json_encode($normalisedSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                INSERT INTO ai_provider_config (provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json)
                VALUES (:provider_key, :is_enabled, :daily_limit, :monthly_limit, :priority, :settings_json)
                ON DUPLICATE KEY UPDATE
                    is_enabled = VALUES(is_enabled),
                    daily_limit = VALUES(daily_limit),
                    monthly_limit = VALUES(monthly_limit),
                    failover_priority = VALUES(failover_priority),
                    settings_json = VALUES(settings_json),
                    updated_at = CURRENT_TIMESTAMP
            ");
                $stmt->execute([
                    ':provider_key' => $providerKey,
                    ':is_enabled' => $isEnabled,
                    ':daily_limit' => $dailyLimit,
                    ':monthly_limit' => $monthlyLimit,
                    ':priority' => $priority,
                    ':settings_json' => $settingsJson,
                ]);
                $selectedProviderKey = $providerKey;
                $providerConfigs = ai_load_provider_configs(true);
                $messages[] = sprintf(
                    'Provider %s settings saved%s.',
                    strtoupper($providerKey),
                    $apiKeyRaw !== '' ? ' (API key updated)' : ''
                );
            } catch (PDOException $e) {
                $errors[] = 'Failed to save provider configuration: ' . $e->getMessage();
            }
        }
    }
}

$providerSettingsForJs = [];
foreach ($providerConfigs as $key => $configRow) {
    $settings = $configRow['settings'] ?? [];
    $providerSettingsForJs[$key] = [
        'model' => $settings['model'] ?? '',
        'base_url' => $settings['base_url'] ?? '',
        'timeout' => isset($settings['timeout']) ? (int) $settings['timeout'] : '',
        'has_api_key' => !empty($configRow['has_api_key']),
    ];
}

// Calculate summary stats
$hasEncryptionKey = false;
try {
    $testKey = getenv('ABBIS_ENCRYPTION_KEY');
    if (!$testKey) {
        $keyFile = __DIR__ . '/../config/secrets/encryption.key';
        $hasEncryptionKey = file_exists($keyFile) && is_readable($keyFile);
    } else {
        $hasEncryptionKey = true;
    }
} catch (Throwable $e) {
    $hasEncryptionKey = false;
}

$providerConfigList = array_values($providerConfigs);
usort($providerConfigList, static function (array $a, array $b): int {
    $priorityCompare = ($a['failover_priority'] ?? 100) <=> ($b['failover_priority'] ?? 100);
    if ($priorityCompare !== 0) {
        return $priorityCompare;
    }
    return strcmp($a['provider_key'] ?? '', $b['provider_key'] ?? '');
});

$enabledProviders = array_filter($providerConfigList, function($p) { return (int)($p['is_enabled'] ?? 0) === 1; });
$totalProviders = count($providerConfigList);
$enabledCount = count($enabledProviders);
$totalDailyLimit = array_sum(array_map(function($p) { return (int)($p['daily_limit'] ?? 0); }, $enabledProviders));
$totalMonthlyLimit = array_sum(array_map(function($p) { return (int)($p['monthly_limit'] ?? 0); }, $enabledProviders));

// Determine primary provider (lowest priority number among enabled providers)
$primaryProvider = 'N/A';
if (!empty($enabledProviders)) {
    // Get the first enabled provider (lowest priority = highest priority)
    $primaryProviderConfig = reset($enabledProviders);
    $primaryProvider = strtoupper($primaryProviderConfig['provider_key'] ?? 'N/A');
}

require_once '../includes/header.php';
?>

<style>
.help-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    font-size: 11px;
    font-weight: 600;
    cursor: help;
    margin-left: 6px;
    vertical-align: middle;
    transition: all 0.2s;
    border: 1px solid var(--primary);
}

.help-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.help-tooltip {
    position: absolute;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    z-index: 1000;
    min-width: 280px;
    max-width: 420px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--text);
    display: none;
    margin-top: 10px;
    margin-left: -15px;
    white-space: normal;
}

.help-tooltip.show {
    display: block;
}

.help-tooltip strong {
    display: block;
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
}

.help-tooltip::before {
    content: '';
    position: absolute;
    top: -7px;
    left: 25px;
    width: 14px;
    height: 14px;
    background: var(--card);
    border-left: 1px solid var(--border);
    border-top: 1px solid var(--border);
    transform: rotate(45deg);
}

.help-trigger {
    position: relative;
    display: inline-block;
}

.system-settings-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    align-items: start;
}

@media (max-width: 1200px) {
    .system-settings-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .system-settings-grid {
        grid-template-columns: 1fr;
    }
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: var(--card);
    border-radius: 16px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--card);
    z-index: 10;
    border-radius: 16px 16px 0 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: var(--secondary);
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--hover);
    color: var(--text);
}

.modal-body {
    padding: 24px;
}

.key-display {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 16px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    line-height: 1.6;
    word-break: break-all;
    margin: 16px 0;
    position: relative;
}

.key-display::before {
    content: '🔑';
    position: absolute;
    top: -10px;
    left: 12px;
    background: #1e293b;
    padding: 0 6px;
    font-size: 16px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(14,165,233,0.1) 0%, rgba(14,165,233,0.05) 100%);
    border: 1px solid rgba(14,165,233,0.2);
    border-left: 4px solid #0ea5e9;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(14,165,233,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(14,165,233,0.15);
}

.provider-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid #10b981;
    transition: transform 0.2s, box-shadow 0.2s;
}

.provider-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.quick-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.quick-action-btn-primary {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(14,165,233,0.3);
}

.quick-action-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(14,165,233,0.4);
}

.quick-action-btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(16,185,129,0.3);
}

.quick-action-btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,185,129,0.4);
}
</style>

<div class="container-fluid" style="padding: 24px; max-width: 1400px; margin: 0 auto;">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin: 0 0 8px 0; font-size: 32px; font-weight: 700;">🛡️ AI Governance & Audit</h1>
                <p class="text-muted" style="margin: 0; font-size: 16px;">
                    Manage AI providers, usage limits, and monitor AI activity across ABBIS
                </p>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <a href="ai-governance.php?action=logs" class="quick-action-btn quick-action-btn-primary">
                    📋 View Logs
                </a>
                <a href="admin/run-ai-migration.php" class="quick-action-btn quick-action-btn-success">
                    🔄 Run Migration
                </a>
                <div class="help-trigger">
                    <span class="help-icon" onclick="toggleHelp('page-overview')" title="Click for help">?</span>
                    <div class="help-tooltip" id="help-page-overview">
                        <strong>AI Governance Dashboard</strong>
                        Control which AI services ABBIS uses (OpenAI, DeepSeek, etc.), set spending limits, and monitor AI usage across the system.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" style="margin-bottom: 24px;">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($messages): ?>
        <div class="alert alert-success" style="margin-bottom: 24px;">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?php echo e($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Summary Dashboard -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px;">
        <div class="stat-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 2px 6px rgba(14,165,233,0.3);">
                    🤖
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #0ea5e9; line-height: 1;">
                        <?php echo $enabledCount; ?>/<?php echo $totalProviders; ?>
                    </div>
                    <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">
                        Providers Active
                    </div>
                </div>
            </div>
        </div>

        <div class="stat-card" style="border-left-color: <?php echo $hasEncryptionKey ? '#10b981' : '#ef4444'; ?>; background: linear-gradient(135deg, <?php echo $hasEncryptionKey ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?> 0%, <?php echo $hasEncryptionKey ? 'rgba(16,185,129,0.05)' : 'rgba(239,68,68,0.05)'; ?> 100%);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, <?php echo $hasEncryptionKey ? '#10b981' : '#ef4444'; ?> 0%, <?php echo $hasEncryptionKey ? '#059669' : '#dc2626'; ?> 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 2px 6px <?php echo $hasEncryptionKey ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)'; ?>;">
                    <?php echo $hasEncryptionKey ? '🔒' : '⚠️'; ?>
                </div>
                <div>
                    <div style="font-size: 18px; font-weight: 700; color: <?php echo $hasEncryptionKey ? '#10b981' : '#ef4444'; ?>; line-height: 1;">
                        <?php echo $hasEncryptionKey ? 'Configured' : 'Required'; ?>
                    </div>
                    <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">
                        Encryption Key
                    </div>
                </div>
            </div>
            <?php if (!$hasEncryptionKey): ?>
                <button onclick="openEncryptionKeyModal()" class="quick-action-btn" style="width: 100%; margin-top: 8px; background: #ef4444; color: white; justify-content: center;">
                    🔐 Set up now
                </button>
            <?php endif; ?>
        </div>

        <div class="stat-card" style="border-left-color: #eab308; background: linear-gradient(135deg, rgba(234,179,8,0.1) 0%, rgba(234,179,8,0.05) 100%);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 2px 6px rgba(234,179,8,0.3);">
                    ⏱️
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #eab308; line-height: 1;">
                        <?php echo $totalDailyLimit > 0 ? number_format($totalDailyLimit) : '∞'; ?>
                    </div>
                    <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">
                        Daily Limit
                    </div>
                </div>
            </div>
        </div>

        <div class="stat-card" style="border-left-color: #10b981; background: linear-gradient(135deg, rgba(16,185,129,0.1) 0%, rgba(16,185,129,0.05) 100%);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 2px 6px rgba(16,185,129,0.3);">
                    🔄
                </div>
                <div>
                    <div style="font-size: 18px; font-weight: 700; color: #10b981; line-height: 1;">
                        <?php echo $primaryProvider; ?>
                    </div>
                    <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">
                        Primary Provider
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-2" style="gap: 28px; margin-bottom: 32px;">
        <!-- AI Provider Setup -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h2 style="margin: 0; font-size: 24px;">🤖 AI Provider Setup</h2>
                    <div class="help-trigger">
                        <span class="help-icon" onclick="toggleHelp('provider-setup')" title="Click for help">?</span>
                        <div class="help-tooltip" id="help-provider-setup">
                            <strong>AI Provider Setup</strong>
                            Configure AI services (OpenAI, DeepSeek, etc.) and set spending limits. Different providers have different costs and capabilities. You can set up multiple providers as backups.
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!$hasEncryptionKey): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <strong style="display: block; margin-bottom: 8px; color: #ef4444;">⚠️ Encryption Key Not Configured!</strong>
                    <p style="margin: 0 0 12px 0; color: var(--text); font-size: 14px;">
                        API keys cannot be saved without an encryption key.
                    </p>
                    <button onclick="openEncryptionKeyModal()" class="quick-action-btn" style="background: #ef4444; color: white;">
                        🔐 Set up encryption key now
                    </button>
                </div>
            <?php else: ?>
                <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <strong>🔒 Security:</strong> API keys are encrypted and stored securely.
                </div>
            <?php endif; ?>
            
            <form method="post" class="form-grid-compact" autocomplete="off" id="providerForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="save_provider" value="1">
                
                <div class="form-group">
                    <label class="form-label"><strong>Which AI Service?</strong></label>
                    <select name="provider_key" class="form-control" required id="providerSelect">
                        <option value="">Choose an AI provider...</option>
                        <?php foreach ($availableProviders as $provider): ?>
                            <option value="<?php echo e($provider); ?>" <?php echo $selectedProviderKey === $provider ? 'selected' : ''; ?>>
                                <?php echo strtoupper(e($provider)); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php foreach (array_diff(array_keys($providerConfigs), $availableProviders) as $provider): ?>
                            <option value="<?php echo e($provider); ?>" <?php echo $selectedProviderKey === $provider ? 'selected' : ''; ?>>
                                <?php echo strtoupper(e($provider)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="border-top: 1px solid var(--border); padding-top: 20px; margin-top: 20px;">
                    <h3 style="font-size: 16px; margin: 0 0 16px 0; color: var(--text);">📊 Usage Limits</h3>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Daily Limit</label>
                            <input type="number" name="daily_limit" class="form-control" min="0" placeholder="Unlimited">
                            <small class="text-muted">Max requests per day</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monthly Limit</label>
                            <input type="number" name="monthly_limit" class="form-control" min="0" placeholder="Unlimited">
                            <small class="text-muted">Max requests per month</small>
                        </div>
                    </div>
                </div>
                
                <div style="border-top: 1px solid var(--border); padding-top: 20px; margin-top: 20px;">
                    <h3 style="font-size: 16px; margin: 0 0 16px 0; color: var(--text);">🔄 Backup Priority</h3>
                    <div class="form-group">
                        <label class="form-label">Priority Number</label>
                        <?php
                        $currentPriority = 100;
                        if ($selectedProviderKey && isset($providerConfigs[$selectedProviderKey])) {
                            $currentPriority = (int)($providerConfigs[$selectedProviderKey]['failover_priority'] ?? 100);
                        }
                        ?>
                        <input type="number" name="failover_priority" class="form-control" min="1" value="<?php echo $currentPriority; ?>" id="priorityInput">
                        <small class="text-muted">1 = First choice (Primary), 100 = Last resort (Backup). Lower number = higher priority.</small>
                    </div>
                </div>

                <div class="provider-settings-wrapper full-width" style="display: grid; gap: 16px; margin-top: 20px;">
                    <div class="provider-settings" data-provider="openai" hidden>
                        <div style="background: rgba(14,165,233,0.08); border: 1px solid rgba(14,165,233,0.25); padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                            <strong>OpenAI-compatible endpoint.</strong> Supports OpenAI and Azure/OpenAI proxies.
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">API Key</label>
                            <input type="password" name="settings[api_key]" class="form-control" data-field="api_key" autocomplete="new-password" disabled placeholder="Enter API key">
                            <small class="text-muted" data-api-key-hint>Enter the API key for this provider.</small>
                        </div>
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Default Model</label>
                                <input type="text" name="settings[model]" class="form-control" data-field="model" placeholder="gpt-4o-mini" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Timeout (seconds)</label>
                                <input type="number" name="settings[timeout]" class="form-control" data-field="timeout" min="5" step="1" placeholder="30" disabled>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Base URL</label>
                            <input type="text" name="settings[base_url]" class="form-control" data-field="base_url" placeholder="https://api.openai.com/v1" disabled>
                            <small class="text-muted">Leave blank for default endpoint</small>
                        </div>
                    </div>

                    <div class="provider-settings" data-provider="deepseek" hidden>
                        <div style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.25); padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                            <strong>DeepSeek</strong> uses an OpenAI-compatible interface.
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">API Key</label>
                            <input type="password" name="settings[api_key]" class="form-control" data-field="api_key" autocomplete="new-password" disabled placeholder="Enter API key">
                            <small class="text-muted" data-api-key-hint>Enter the API key for this provider.</small>
                        </div>
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Default Model</label>
                                <input type="text" name="settings[model]" class="form-control" data-field="model" placeholder="deepseek-chat" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Timeout (seconds)</label>
                                <input type="number" name="settings[timeout]" class="form-control" data-field="timeout" min="5" step="1" placeholder="30" disabled>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Base URL</label>
                            <input type="text" name="settings[base_url]" class="form-control" data-field="base_url" placeholder="https://api.deepseek.com/v1" disabled>
                            <small class="text-muted">Leave blank for default endpoint</small>
                        </div>
                    </div>

                    <div class="provider-settings" data-provider="gemini" hidden>
                        <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                            <strong>Google Gemini</strong> - Ensure the Generative Language API is enabled in Google Cloud.
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">API Key</label>
                            <input type="password" name="settings[api_key]" class="form-control" data-field="api_key" autocomplete="new-password" disabled placeholder="Enter API key">
                            <small class="text-muted" data-api-key-hint>Enter the API key for this provider.</small>
                        </div>
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Default Model</label>
                                <input type="text" name="settings[model]" class="form-control" data-field="model" placeholder="gemini-1.5-flash-latest" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Timeout (seconds)</label>
                                <input type="number" name="settings[timeout]" class="form-control" data-field="timeout" min="5" step="1" placeholder="30" disabled>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Base URL</label>
                            <input type="text" name="settings[base_url]" class="form-control" data-field="base_url" placeholder="https://generativelanguage.googleapis.com/v1beta" disabled>
                            <small class="text-muted">Leave blank for default endpoint</small>
                        </div>
                    </div>

                    <div class="provider-settings" data-provider="ollama" hidden>
                        <div style="background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.25); padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                            <strong>Ollama</strong> - Self-hosted instance. Ensure ABBIS server can reach the provided URL.
                        </div>
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Base URL</label>
                                <input type="text" name="settings[base_url]" class="form-control" data-field="base_url" placeholder="http://127.0.0.1:11434" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Default Model</label>
                                <input type="text" name="settings[model]" class="form-control" data-field="model" placeholder="llama3" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Timeout (seconds)</label>
                                <input type="number" name="settings[timeout]" class="form-control" data-field="timeout" min="5" step="1" placeholder="120" disabled>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <small class="text-muted">Ollama requests do not require an API key but rely on network access.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label">Status</label>
                    <div class="form-check">
                        <input type="checkbox" id="providerEnabled" name="is_enabled" class="form-check-input" checked>
                        <label for="providerEnabled" class="form-check-label">Provider enabled for use</label>
                    </div>
                </div>
                
                <div class="form-group full-width" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">💾 Save Provider Settings</button>
                </div>
            </form>

            <hr style="margin: 32px 0; border-color: var(--border);">

            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0 0 16px 0; font-size: 18px;">✅ Currently Configured Providers</h3>
                <?php if (empty($providerConfigList)): ?>
                    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 40px; text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                        <h4 style="margin: 0 0 8px 0; color: var(--text);">No Providers Configured</h4>
                        <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                            Configure providers above to see them listed here.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                        <?php foreach ($providerConfigList as $row): ?>
                            <div class="provider-card" style="border-left-color: <?php echo (int)($row['is_enabled'] ?? 0) === 1 ? '#10b981' : '#ef4444'; ?>;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                    <div style="font-size: 20px; font-weight: 700; color: var(--text);">
                                        <?php echo strtoupper(e($row['provider_key'])); ?>
                                    </div>
                                    <?php if ((int)($row['is_enabled'] ?? 0) === 1): ?>
                                        <span class="badge badge-success" style="font-size: 11px;">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="font-size: 11px;">Disabled</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                                    <div>
                                        <div style="font-size: 11px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Daily</div>
                                        <div style="font-size: 16px; font-weight: 600; color: var(--text);">
                                            <?php echo $row['daily_limit'] !== null ? number_format((int)$row['daily_limit']) : '∞'; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 11px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Monthly</div>
                                        <div style="font-size: 16px; font-weight: 600; color: var(--text);">
                                            <?php echo $row['monthly_limit'] !== null ? number_format((int)$row['monthly_limit']) : '∞'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-size: 11px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Priority</div>
                                        <div style="font-size: 14px; font-weight: 600; color: var(--text);">
                                            #<?php echo (int)($row['failover_priority'] ?? 100); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 11px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Updated</div>
                                        <div style="font-size: 11px; color: var(--secondary);">
                                            <?php echo date('M j, Y', strtotime($row['updated_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System-Wide Settings -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h2 style="margin: 0; font-size: 24px;">⚙️ System-Wide Settings</h2>
                    <div class="help-trigger">
                        <span class="help-icon" onclick="toggleHelp('system-settings')" title="Click for help">?</span>
                        <div class="help-tooltip" id="help-system-settings">
                            <strong>System-Wide Settings</strong>
                            Global limits that apply to all AI usage in ABBIS, regardless of which provider is used. These are configured via environment variables.
                        </div>
                    </div>
                </div>
            </div>
            <div class="system-settings-grid">
                <div class="policy-box" style="background: linear-gradient(135deg, rgba(14,165,233,0.05) 0%, rgba(14,165,233,0.02) 100%); border: 1px solid rgba(14,165,233,0.2); padding: 24px; border-radius: 12px; border-left: 4px solid #0ea5e9; box-shadow: 0 2px 8px rgba(14,165,233,0.1); transition: transform 0.2s, box-shadow 0.2s; height: 100%;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(14,165,233,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 2px 8px rgba(14,165,233,0.1)'">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 6px rgba(14,165,233,0.3);">
                                ⏱️
                            </div>
                            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text);">Speed Limits</h3>
                        </div>
                        <div class="help-trigger">
                            <span class="help-icon" onclick="toggleHelp('speed-limits')" title="Click for help">?</span>
                            <div class="help-tooltip" id="help-speed-limits">
                                <strong>Speed Limits</strong>
                                Prevents the system from making too many requests too quickly. Configured via <code>AI_HOURLY_LIMIT</code> and <code>AI_DAILY_LIMIT</code> environment variables.
                            </div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 8px;">
                        <div style="background: rgba(255,255,255,0.6); padding: 16px; border-radius: 8px; border: 1px solid rgba(14,165,233,0.15);">
                            <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">Hourly Limit</div>
                            <div style="font-size: 28px; font-weight: 700; color: #0ea5e9; line-height: 1;">
                                <?php echo getenv('AI_HOURLY_LIMIT') ?: AI_DEFAULT_HOURLY_LIMIT; ?>
                            </div>
                            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">requests</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.6); padding: 16px; border-radius: 8px; border: 1px solid rgba(14,165,233,0.15);">
                            <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">Daily Limit</div>
                            <div style="font-size: 28px; font-weight: 700; color: #0ea5e9; line-height: 1;">
                                <?php echo getenv('AI_DAILY_LIMIT') ?: AI_DEFAULT_DAILY_LIMIT; ?>
                            </div>
                            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">requests</div>
                        </div>
                    </div>
                </div>

                <div class="policy-box" style="background: linear-gradient(135deg, rgba(16,185,129,0.05) 0%, rgba(16,185,129,0.02) 100%); border: 1px solid rgba(16,185,129,0.2); padding: 24px; border-radius: 12px; border-left: 4px solid #10b981; box-shadow: 0 2px 8px rgba(16,185,129,0.1); transition: transform 0.2s, box-shadow 0.2s; height: 100%;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(16,185,129,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 2px 8px rgba(16,185,129,0.1)'">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 6px rgba(16,185,129,0.3);">
                                🔄
                            </div>
                            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text);">Backup Order</h3>
                        </div>
                        <div class="help-trigger">
                            <span class="help-icon" onclick="toggleHelp('backup-order')" title="Click for help">?</span>
                            <div class="help-tooltip" id="help-backup-order">
                                <strong>Backup Order</strong>
                                If the first provider fails, ABBIS automatically tries the next one in this order. Configured via <code>AI_PROVIDER_FAILOVER</code> environment variable.
                            </div>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.6); padding: 18px; border-radius: 8px; border: 1px solid rgba(16,185,129,0.15);">
                        <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; font-weight: 600;">Provider Sequence</div>
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <?php 
                            $providers = array_map('strtoupper', $availableProviders);
                            foreach ($providers as $index => $provider): 
                                $isLast = $index === count($providers) - 1;
                            ?>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; box-shadow: 0 2px 4px rgba(16,185,129,0.2);">
                                        <?php echo $provider; ?>
                                    </span>
                                    <?php if (!$isLast): ?>
                                        <span style="color: #10b981; font-size: 18px; font-weight: 700;">→</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="policy-box" style="background: linear-gradient(135deg, rgba(234,179,8,0.05) 0%, rgba(234,179,8,0.02) 100%); border: 1px solid rgba(234,179,8,0.2); padding: 24px; border-radius: 12px; border-left: 4px solid #eab308; box-shadow: 0 2px 8px rgba(234,179,8,0.1); transition: transform 0.2s, box-shadow 0.2s; height: 100%;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(234,179,8,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 2px 8px rgba(234,179,8,0.1)'">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 6px rgba(234,179,8,0.3);">
                                💾
                            </div>
                            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text);">Memory Budget</h3>
                        </div>
                        <div class="help-trigger">
                            <span class="help-icon" onclick="toggleHelp('memory-budget')" title="Click for help">?</span>
                            <div class="help-tooltip" id="help-memory-budget">
                                <strong>Memory Budget</strong>
                                Limits how much information can be sent to AI in one request. Higher = more context, but costs more. Configured via <code>AI_CONTEXT_TOKEN_BUDGET</code> environment variable.
                            </div>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.6); padding: 18px; border-radius: 8px; border: 1px solid rgba(234,179,8,0.15);">
                        <div style="font-size: 12px; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; font-weight: 600;">Token Limit</div>
                        <div style="display: flex; align-items: baseline; gap: 8px;">
                            <div style="font-size: 36px; font-weight: 700; color: #eab308; line-height: 1;">
                                <?php echo number_format((int)(getenv('AI_CONTEXT_TOKEN_BUDGET') ?: 3200)); ?>
                            </div>
                            <div style="font-size: 16px; color: var(--secondary); font-weight: 500; margin-left: 4px;">tokens</div>
                        </div>
                        <div style="font-size: 13px; color: var(--secondary); margin-top: 8px;">per request</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($action === 'logs'): ?>
        <?php include __DIR__ . '/ai-governance-logs.php'; ?>
    <?php else: ?>
        <div class="card" style="padding: 24px; margin-top: 32px; text-align: center; background: var(--bg); border: 1px solid var(--border);">
            <h3 style="margin: 0 0 12px 0; color: var(--text);">📋 AI Usage Logs</h3>
            <p style="color: var(--secondary); margin-bottom: 20px;">
                View detailed logs of all AI feature usage, including who used them, when, and the results.
            </p>
            <a href="ai-governance.php?action=logs" class="quick-action-btn quick-action-btn-primary">
                View Full AI Usage Logs →
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Encryption Key Generator Modal -->
<div class="modal-overlay" id="encryptionKeyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="margin: 0; font-size: 24px;">🔐 Encryption Key Setup</h2>
            <button class="modal-close" onclick="closeEncryptionKeyModal()" title="Close">×</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--secondary); margin-bottom: 20px;">
                An encryption key is required to securely store API keys. Generate a new key below.
            </p>
            
            <div id="keyGeneratorSection">
                <button onclick="generateEncryptionKey()" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px; margin-bottom: 20px;">
                    🎲 Generate Encryption Key
                </button>
            </div>
            
            <div id="keyDisplaySection" style="display: none;">
                <div class="key-display" id="generatedKeyDisplay"></div>
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button onclick="copyGeneratedKey()" class="btn btn-outline" style="flex: 1;">
                        📋 Copy Key
                    </button>
                    <button onclick="saveEncryptionKey()" class="btn btn-success" style="flex: 1;" id="saveKeyBtn">
                        💾 Save to File
                    </button>
                </div>
                <div id="saveErrorSection" style="display: none; margin-top: 16px; padding: 12px; background: rgba(239,68,68,0.1); border-left: 4px solid #ef4444; border-radius: 6px;">
                    <strong style="color: #ef4444; display: block; margin-bottom: 8px;">❌ Save Failed</strong>
                    <div id="saveErrorMessage" style="color: var(--text); font-size: 13px; margin-bottom: 12px;"></div>
                    <div style="font-size: 12px; color: var(--secondary); margin-top: 8px;">
                        <strong>Troubleshooting:</strong>
                        <ol style="margin: 8px 0 0 20px; padding: 0; line-height: 1.6;">
                            <li>Run: <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">sudo chmod 777 <?php echo dirname(__DIR__); ?>/config/secrets</code></li>
                            <li>Or: <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">sudo chown daemon:daemon <?php echo dirname(__DIR__); ?>/config/secrets</code></li>
                            <li>Or delete the existing file: <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">sudo rm <?php echo dirname(__DIR__); ?>/config/secrets/encryption.key</code></li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
                <h3 style="font-size: 16px; margin: 0 0 12px 0;">📖 Setup Instructions</h3>
                <ol style="line-height: 1.8; color: var(--text); padding-left: 20px;">
                    <li>Click "Generate Encryption Key" above</li>
                    <li>Copy the generated key</li>
                    <li>Click "Save to File" to automatically save it</li>
                    <li>The system will use this key to encrypt all API keys</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
let generatedEncryptionKey = '';

// Provider settings handler
(function () {
    const providerSettings = <?php echo json_encode($providerSettingsForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>;
    const providerSelect = document.getElementById('providerSelect');
    const sections = document.querySelectorAll('.provider-settings');

    function activateSection(provider) {
        const key = (provider || '').toLowerCase();
        sections.forEach(section => {
            const isActive = section.dataset.provider === key;
            section.hidden = !isActive;
            section.querySelectorAll('input,select,textarea').forEach(input => {
                input.disabled = !isActive;
            });

            if (isActive) {
                const config = providerSettings[key] || {};
                const apiInput = section.querySelector('[data-field="api_key"]');
                if (apiInput) {
                    apiInput.value = '';
                    apiInput.placeholder = config.has_api_key ? 'Key stored — leave blank to keep' : 'Enter API key';
                }

                const hint = section.querySelector('[data-api-key-hint]');
                if (hint) {
                    hint.textContent = config.has_api_key
                        ? 'Key already stored. Leave blank to keep it, or enter a replacement value.'
                        : 'Enter the API key for this provider.';
                }

                const modelInput = section.querySelector('[data-field="model"]');
                if (modelInput) {
                    modelInput.value = config.model || '';
                }

                const baseInput = section.querySelector('[data-field="base_url"]');
                if (baseInput) {
                    baseInput.value = config.base_url || '';
                }

                const timeoutInput = section.querySelector('[data-field="timeout"]');
                if (timeoutInput) {
                    timeoutInput.value = config.timeout !== '' ? config.timeout : '';
                }
            }
        });
    }

    // Load priority value when provider changes
    function updatePriorityValue(provider) {
        const priorityInput = document.getElementById('priorityInput');
        if (!priorityInput) return;
        
        const providerSettings = <?php echo json_encode($providerConfigs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>;
        const key = (provider || '').toLowerCase();
        const config = providerSettings[key] || {};
        const currentPriority = config.failover_priority || 100;
        priorityInput.value = currentPriority;
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', () => {
            activateSection(providerSelect.value);
            updatePriorityValue(providerSelect.value);
        });
        if (providerSelect.value) {
            activateSection(providerSelect.value);
            updatePriorityValue(providerSelect.value);
        }
    }
})();

// Help tooltip handler
function toggleHelp(helpId) {
    const tooltip = document.getElementById('help-' + helpId);
    if (!tooltip) return;
    
    document.querySelectorAll('.help-tooltip').forEach(t => {
        if (t !== tooltip) {
            t.classList.remove('show');
        }
    });
    
    tooltip.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.help-trigger') && !e.target.closest('.help-tooltip')) {
        document.querySelectorAll('.help-tooltip').forEach(t => {
            t.classList.remove('show');
        });
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.help-tooltip').forEach(t => {
            t.classList.remove('show');
        });
        closeEncryptionKeyModal();
    }
});

// Encryption Key Modal Functions
function openEncryptionKeyModal() {
    const modal = document.getElementById('encryptionKeyModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeEncryptionKeyModal() {
    const modal = document.getElementById('encryptionKeyModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        // Reset modal state
        document.getElementById('keyGeneratorSection').style.display = 'block';
        document.getElementById('keyDisplaySection').style.display = 'none';
        generatedEncryptionKey = '';
    }
}

// Close modal on overlay click
document.getElementById('encryptionKeyModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEncryptionKeyModal();
    }
});

// Generate encryption key
async function generateEncryptionKey() {
    try {
        const formData = new FormData();
        formData.append('generate_encryption_key', '1');
        formData.append('csrf_token', '<?php echo CSRF::getToken(); ?>');
        
        const response = await fetch('ai-governance.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            generatedEncryptionKey = data.key;
            document.getElementById('generatedKeyDisplay').textContent = data.key;
            document.getElementById('keyGeneratorSection').style.display = 'none';
            document.getElementById('keyDisplaySection').style.display = 'block';
        } else {
            alert('Error generating key: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error generating key:', error);
        alert('Failed to generate encryption key. Please try again.');
    }
}

// Copy generated key
function copyGeneratedKey() {
    if (!generatedEncryptionKey) return;
    
    const textarea = document.createElement('textarea');
    textarea.value = generatedEncryptionKey;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        alert('Key copied to clipboard!');
    } catch (err) {
        alert('Failed to copy. Please select and copy manually.');
    }
    
    document.body.removeChild(textarea);
}

// Save encryption key
async function saveEncryptionKey() {
    if (!generatedEncryptionKey) {
        alert('No key generated. Please generate a key first.');
        return;
    }
    
    if (!confirm('Save this encryption key to file? This will enable API key encryption.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('save_encryption_key', '1');
        formData.append('encryption_key', generatedEncryptionKey);
        formData.append('csrf_token', '<?php echo CSRF::getToken(); ?>');
        
        const response = await fetch('ai-governance.php', {
            method: 'POST',
            body: formData
        });
        
        // Read response as text first (we can only read the body once)
        const responseText = await response.text();
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            // Not valid JSON - might be an HTML error page
            console.error('Failed to parse JSON response:', responseText.substring(0, 500));
            showSaveError('Server returned an unexpected response. Please check the browser console for details. If the issue persists, try running: sudo chmod 777 <?php echo dirname(__DIR__); ?>/config/secrets');
            return;
        }
        
        if (data.success) {
            alert('✅ ' + (data.message || 'Encryption key saved successfully!'));
            window.location.reload();
        } else {
            showSaveError(data.message || 'Failed to save encryption key');
        }
    } catch (error) {
        console.error('Error saving key:', error);
        showSaveError('Network error: ' + error.message + '. Please check your connection and try again.');
    }
}

function showSaveError(message) {
    const errorSection = document.getElementById('saveErrorSection');
    const errorMessage = document.getElementById('saveErrorMessage');
    if (errorSection && errorMessage) {
        errorMessage.textContent = message;
        errorSection.style.display = 'block';
        // Scroll to error
        errorSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        alert('❌ Error: ' + message);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>

