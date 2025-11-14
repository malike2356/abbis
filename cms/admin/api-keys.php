<?php
/**
 * CMS Admin - REST API Keys (WordPress-inspired)
 * Manage API keys for headless/API access
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/crypto.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$keyId = $_GET['key_id'] ?? null;
$message = null;
$messageType = 'success';

// Ensure tables exist
require_once dirname(__DIR__) . '/includes/ensure-advanced-tables.php';
if (!ensureAdvancedTablesExist($pdo)) {
    die("❌ Error: Could not create required database tables. Please run: php database/create_advanced_features_tables.php");
}

// Generate API key
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_api_key'])) {
        $keyName = trim($_POST['key_name'] ?? '');
        $userId = $_POST['user_id'] ?? null;
        $permissions = json_encode($_POST['permissions'] ?? []);
        $rateLimit = intval($_POST['rate_limit'] ?? 1000);
        $expiresAt = $_POST['expires_at'] ?? null;
        
        if ($keyName) {
            $apiKey = generateApiKey();
            $apiSecret = generateApiKey();
            $storedKey = Crypto::encrypt($apiKey);
            $storedSecret = Crypto::encrypt($apiSecret);
            
            $stmt = $pdo->prepare("INSERT INTO cms_api_keys (key_name, api_key, api_secret, user_id, permissions, rate_limit, expires_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$keyName, $storedKey, $storedSecret, $userId, $permissions, $rateLimit, $expiresAt ?: null]);
            $keyId = $pdo->lastInsertId();
            
            // Show the key once (store in session temporarily)
            $_SESSION['new_api_key'] = ['key' => $apiKey, 'secret' => $apiSecret, 'id' => $keyId];
            header('Location: api-keys.php?action=view_key&key_id=' . $keyId);
            exit;
        }
    }
    
    if (isset($_POST['update_api_key'])) {
        $keyId = $_POST['key_id'] ?? null;
        $keyName = trim($_POST['key_name'] ?? '');
        $permissions = json_encode($_POST['permissions'] ?? []);
        $rateLimit = intval($_POST['rate_limit'] ?? 1000);
        $expiresAt = $_POST['expires_at'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        if ($keyId && $keyName) {
            $stmt = $pdo->prepare("UPDATE cms_api_keys SET key_name=?, permissions=?, rate_limit=?, expires_at=?, status=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$keyName, $permissions, $rateLimit, $expiresAt ?: null, $status, $keyId]);
            $message = 'API key updated successfully';
        }
    }
    
    if (isset($_POST['revoke_api_key'])) {
        $keyId = $_POST['key_id'] ?? null;
        if ($keyId) {
            $pdo->prepare("UPDATE cms_api_keys SET status='revoked', updated_at=NOW() WHERE id=?")->execute([$keyId]);
            $message = 'API key revoked successfully';
        }
    }
    
    if (isset($_POST['delete_api_key'])) {
        $keyId = $_POST['key_id'] ?? null;
        if ($keyId) {
            $pdo->prepare("DELETE FROM cms_api_keys WHERE id=?")->execute([$keyId]);
            header('Location: api-keys.php');
            exit;
        }
    }
}

// Get API keys
$apiKeys = $pdo->query("SELECT k.*, u.username 
    FROM cms_api_keys k 
    LEFT JOIN cms_users u ON k.user_id = u.id
    ORDER BY k.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

foreach ($apiKeys as &$key) {
    $keyDisplay = '(encrypted)';
    $storedKey = $key['api_key'] ?? '';
    if (!empty($storedKey)) {
        if (Crypto::isEncrypted($storedKey)) {
            try {
                $storedKey = Crypto::decrypt($storedKey);
            } catch (RuntimeException $e) {
                error_log('Failed to decrypt API key for display (ID ' . ($key['id'] ?? '?') . '): ' . $e->getMessage());
                $storedKey = '';
            }
        }
        if ($storedKey !== '') {
            $keyDisplay = substr($storedKey, 0, 4) . '••••' . substr($storedKey, -4);
        }
    }
    $key['api_key_masked'] = $keyDisplay;
}
unset($key);

// Get users
$users = $pdo->query("SELECT id, username, email FROM cms_users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get single key for editing
$apiKey = null;
if ($keyId && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_api_keys WHERE id=?");
    $stmt->execute([$keyId]);
    $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($apiKey) {
        $apiKey['permissions'] = json_decode($apiKey['permissions'], true) ?: [];
    }
}

// View new key (show once)
$newKey = null;
if ($action === 'view_key' && isset($_SESSION['new_api_key']) && $_SESSION['new_api_key']['id'] == $keyId) {
    $newKey = $_SESSION['new_api_key'];
    unset($_SESSION['new_api_key']);
}

include __DIR__ . '/header.php';
?>

<style>
    .api-key-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    .api-key-display {
        background: #1e293b;
        color: #f1f5f9;
        padding: 1rem;
        border-radius: 8px;
        font-family: monospace;
        word-break: break-all;
        margin: 1rem 0;
    }
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.inactive {
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge.revoked {
        background: #fee2e2;
        color: #991b1b;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>🔑 REST API Keys</h1>
        <p>Manage API keys for headless/API access (WordPress-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create API Key</a>
            <?php else: ?>
                <a href="?" class="admin-btn admin-btn-outline">← Back to List</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-notice admin-notice-<?php echo $messageType; ?>">
            <span class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠' : '✓'; ?></span>
            <div class="admin-notice-content">
                <strong><?php echo $messageType === 'error' ? 'Error' : 'Success'; ?>!</strong>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'view_key' && $newKey): ?>
        <div class="editor-section" style="margin-top: 2rem; background: #fef3c7; border: 2px solid #f59e0b;">
            <div style="padding: 1.5rem;">
                <h3 style="color: #92400e; margin-bottom: 1rem;">⚠️ Save These Credentials!</h3>
                <p style="color: #78350f; margin-bottom: 1rem;">This is the only time you'll see these credentials. Save them securely.</p>
                
                <div class="admin-form-group">
                    <label>API Key:</label>
                    <div class="api-key-display"><?php echo htmlspecialchars($newKey['key']); ?></div>
                    <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($newKey['key']); ?>')" class="admin-btn admin-btn-outline">📋 Copy Key</button>
                </div>
                
                <div class="admin-form-group">
                    <label>API Secret:</label>
                    <div class="api-key-display"><?php echo htmlspecialchars($newKey['secret']); ?></div>
                    <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($newKey['secret']); ?>')" class="admin-btn admin-btn-outline">📋 Copy Secret</button>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <a href="?" class="admin-btn admin-btn-primary">✓ I've Saved These</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <div style="margin-top: 2rem;">
            <?php if (empty($apiKeys)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🔑</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No API Keys Yet</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Create API keys to enable REST API access</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create API Key</a>
                </div>
            <?php else: ?>
                <?php foreach ($apiKeys as $key): ?>
                    <div class="api-key-card">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;">
                                        <?php echo htmlspecialchars($key['key_name']); ?>
                                    </h3>
                                    <span class="status-badge <?php echo $key['status']; ?>">
                                        <?php echo ucfirst($key['status']); ?>
                                    </span>
                                </div>
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <code style="font-size: 0.75rem;"><?php echo htmlspecialchars($key['api_key_masked']); ?></code>
                                    <?php if ($key['username']): ?>
                                        <span style="margin-left: 1rem;">• User: <strong><?php echo htmlspecialchars($key['username']); ?></strong></span>
                                    <?php endif; ?>
                                    <span style="margin-left: 1rem;">• Rate Limit: <strong><?php echo $key['rate_limit']; ?>/hour</strong></span>
                                    <?php if ($key['last_used_at']): ?>
                                        <span style="margin-left: 1rem;">• Last Used: <strong><?php echo date('Y-m-d H:i', strtotime($key['last_used_at'])); ?></strong></span>
                                    <?php else: ?>
                                        <span style="margin-left: 1rem;">• Never Used</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="?action=edit&key_id=<?php echo $key['id']; ?>" class="admin-btn admin-btn-outline">✏️ Edit</a>
                                <?php if ($key['status'] !== 'revoked'): ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Revoke this API key?');">
                                        <input type="hidden" name="revoke_api_key" value="1">
                                        <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                        <button type="submit" class="admin-btn admin-btn-outline">🚫 Revoke</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Permanently delete this API key?');">
                                    <input type="hidden" name="delete_api_key" value="1">
                                    <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger">🗑️</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <form method="post" style="margin-top: 2rem;">
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">🔑</div>
                    <h3>API Key Information</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Key Name *</label>
                    <input type="text" name="key_name" value="<?php echo htmlspecialchars($apiKey['key_name'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., Mobile App, Third-party Integration">
                </div>
                
                <div class="admin-form-group">
                    <label>Associated User (Optional)</label>
                    <select name="user_id" class="large-text">
                        <option value="">-- No User --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($apiKey['user_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['username'] . ' (' . $u['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label>Rate Limit (requests per hour)</label>
                    <input type="number" name="rate_limit" value="<?php echo htmlspecialchars($apiKey['rate_limit'] ?? 1000); ?>" 
                           class="large-text" min="1" max="100000">
                </div>
                
                <div class="admin-form-group">
                    <label>Expires At (Optional)</label>
                    <input type="datetime-local" name="expires_at" value="<?php echo $apiKey['expires_at'] ? date('Y-m-d\TH:i', strtotime($apiKey['expires_at'])) : ''; ?>" 
                           class="large-text">
                </div>
                
                <?php if ($apiKey): ?>
                    <div class="admin-form-group">
                        <label>Status</label>
                        <select name="status" class="large-text">
                            <option value="active" <?php echo ($apiKey['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($apiKey['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="revoked" <?php echo ($apiKey['status'] ?? '') === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                        </select>
                    </div>
                    <input type="hidden" name="key_id" value="<?php echo $apiKey['id']; ?>">
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="<?php echo $apiKey ? 'update_api_key' : 'create_api_key'; ?>" class="admin-btn admin-btn-primary">
                        💾 <?php echo $apiKey ? 'Update' : 'Create'; ?> API Key
                    </button>
                    <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Copied to clipboard!');
    }, function() {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Copied to clipboard!');
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>

