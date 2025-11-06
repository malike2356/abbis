<?php
/**
 * API Key Management Module
 * Manage API keys for external system integrations (Wazuh, etc.)
 */
$page_title = 'API Key Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

// Ensure api_keys table exists
try {
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
} catch (PDOException $e) {
    // Table might already exist
}

// Handle form submissions
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'generate':
                    // Generate new API key
                    $keyName = sanitizeInput($_POST['key_name'] ?? '');
                    $rateLimit = (int)($_POST['rate_limit'] ?? 100);
                    $expiresInDays = !empty($_POST['expires_in_days']) ? (int)$_POST['expires_in_days'] : null;
                    
                    if (empty($keyName)) {
                        throw new Exception('Key name is required');
                    }
                    
                    $apiKey = 'abbis_' . bin2hex(random_bytes(24));
                    $apiSecret = bin2hex(random_bytes(32));
                    
                    $expiresAt = null;
                    if ($expiresInDays) {
                        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));
                    }
                    
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
                    
                    $message = "API key '{$keyName}' generated successfully. Key: {$apiKey}";
                    $messageType = 'success';
                    break;
                    
                case 'toggle':
                    // Toggle API key status
                    $keyId = (int)($_POST['key_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$keyId]);
                    $message = 'API key status updated';
                    $messageType = 'success';
                    break;
                    
                case 'delete':
                    // Delete API key
                    $keyId = (int)($_POST['key_id'] ?? 0);
                    $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
                    $stmt->execute([$keyId]);
                    $message = 'API key deleted';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get all API keys
try {
    $stmt = $pdo->query("
        SELECT ak.*, u.full_name as created_by_name 
        FROM api_keys ak 
        LEFT JOIN users u ON ak.created_by = u.id 
        ORDER BY ak.created_at DESC
    ");
    $apiKeys = $stmt->fetchAll();
} catch (PDOException $e) {
    $apiKeys = [];
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔑 API Key Management</h1>
        <p>Manage API keys for external system integrations (Wazuh, monitoring tools, etc.)</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-grid">
        <!-- Generate New API Key -->
        <div class="dashboard-card">
            <h2>Generate New API Key</h2>
            <form method="POST" class="form-grid-compact">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="generate">
                
                <div class="form-group">
                    <label class="form-label">Key Name</label>
                    <input type="text" name="key_name" class="form-control" required 
                           placeholder="e.g., Wazuh Integration">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Rate Limit (requests/minute)</label>
                    <input type="number" name="rate_limit" class="form-control" value="100" 
                           min="1" max="1000" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Expires In (days, optional)</label>
                    <input type="number" name="expires_in_days" class="form-control" 
                           min="1" placeholder="Leave empty for no expiration">
                </div>
                
                <div class="form-group full-width">
                    <button type="submit" class="btn btn-primary">Generate API Key</button>
                </div>
            </form>
        </div>
        
        <!-- API Documentation -->
        <div class="dashboard-card">
            <h2>📚 API Documentation</h2>
            <div style="padding: 20px;">
                <h3>Monitoring API Endpoint</h3>
                <p><strong>URL:</strong> <code>api/monitoring-api.php</code></p>
                
                <h4>Authentication</h4>
                <p>Send API key in header:</p>
                <pre style="background: var(--bg); padding: 10px; border-radius: 6px;">X-API-Key: your_api_key_here</pre>
                
                <h4>Available Endpoints</h4>
                <ul>
                    <li><code>?endpoint=health</code> - System health check</li>
                    <li><code>?endpoint=metrics</code> - System metrics</li>
                    <li><code>?endpoint=performance</code> - Performance data</li>
                    <li><code>?endpoint=alerts</code> - System alerts</li>
                    <li><code>?endpoint=logs</code> - System logs</li>
                </ul>
                
                <h4>Example Request</h4>
                <pre style="background: var(--bg); padding: 10px; border-radius: 6px;">curl -H "X-API-Key: your_key" \
  "https://yourdomain.com/abbis3.2/api/monitoring-api.php?endpoint=metrics"</pre>
            </div>
        </div>
    </div>
    
    <!-- Existing API Keys -->
    <div class="dashboard-card" style="margin-top: 30px;">
        <h2>Existing API Keys</h2>
        
        <?php if (empty($apiKeys)): ?>
            <p>No API keys generated yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Key Name</th>
                            <th>API Key</th>
                            <th>Rate Limit</th>
                            <th>Status</th>
                            <th>Last Used</th>
                            <th>Expires At</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiKeys as $key): ?>
                            <tr>
                                <td><strong><?php echo e($key['key_name']); ?></strong></td>
                                <td><code><?php echo e(substr($key['api_key'], 0, 20)); ?>...</code></td>
                                <td><?php echo e($key['rate_limit']); ?>/min</td>
                                <td>
                                    <?php if ($key['is_active']): ?>
                                        <span style="color: var(--success);">● Active</span>
                                    <?php else: ?>
                                        <span style="color: var(--danger);">● Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $key['last_used'] ? formatDate($key['last_used']) : 'Never'; ?></td>
                                <td><?php echo $key['expires_at'] ? formatDate($key['expires_at']) : 'Never'; ?></td>
                                <td><?php echo e($key['created_by_name'] ?? 'System'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline">
                                            <?php echo $key['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this API key?');">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

