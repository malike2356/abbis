<?php
/**
 * ELK Stack Integration Management
 */
$page_title = 'ELK Stack Integration';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

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
                case 'configure':
                    $elasticsearchUrl = sanitizeInput($_POST['elasticsearch_url'] ?? '');
                    $username = sanitizeInput($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $indexPrefix = sanitizeInput($_POST['index_prefix'] ?? 'abbis');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($elasticsearchUrl)) {
                        throw new Exception('Elasticsearch URL is required');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO elk_config 
                        (elasticsearch_url, username, password, index_prefix, is_active) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        elasticsearch_url = VALUES(elasticsearch_url),
                        username = VALUES(username),
                        password = IF(? = '', password, VALUES(password)),
                        index_prefix = VALUES(index_prefix),
                        is_active = VALUES(is_active),
                        updated_at = NOW()
                    ");
                    $stmt->execute([$elasticsearchUrl, $username, $password, $indexPrefix, $isActive, $password]);
                    
                    $message = 'ELK configuration saved';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get ELK configuration
try {
    $stmt = $pdo->query("SELECT * FROM elk_config LIMIT 1");
    $config = $stmt->fetch();
    if (!$config) {
        $config = [
            'elasticsearch_url' => 'http://localhost:9200',
            'username' => '',
            'password' => '',
            'index_prefix' => 'abbis',
            'is_active' => 0,
            'last_sync' => null
        ];
    }
} catch (PDOException $e) {
    $config = [
        'elasticsearch_url' => 'http://localhost:9200',
        'username' => '',
        'password' => '',
        'index_prefix' => 'abbis',
        'is_active' => 0,
        'last_sync' => null
    ];
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔍 ELK Stack Integration</h1>
        <p>Connect ABBIS to Elasticsearch, Logstash, and Kibana for log analysis and visualization</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-grid">
        <!-- Configuration -->
        <div class="dashboard-card">
            <h2>⚙️ Configuration</h2>
            <form method="POST" class="form-grid-compact">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="configure">
                
                <div class="form-group">
                    <label class="form-label">Elasticsearch URL</label>
                    <input type="text" name="elasticsearch_url" class="form-control" 
                           value="<?php echo e($config['elasticsearch_url']); ?>" 
                           placeholder="http://localhost:9200" required>
                    <small class="form-text">Your Elasticsearch server URL</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username (optional)</label>
                    <input type="text" name="username" class="form-control" 
                           value="<?php echo e($config['username']); ?>" 
                           placeholder="Leave empty if no authentication">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password (optional)</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Leave empty to keep current password">
                    <small class="form-text">Only enter if changing password</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Index Prefix</label>
                    <input type="text" name="index_prefix" class="form-control" 
                           value="<?php echo e($config['index_prefix']); ?>" 
                           placeholder="abbis">
                    <small class="form-text">Prefix for Elasticsearch indices</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo $config['is_active'] ? 'checked' : ''; ?>>
                        Activate Integration
                    </label>
                </div>
                
                <div class="form-group full-width">
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
        
        <!-- Connection Status -->
        <div class="dashboard-card">
            <h2>📊 Connection Status</h2>
            <div style="margin-top: 20px;">
                <?php if ($config['is_active']): ?>
                    <p><strong>Status:</strong> <span style="color: var(--success);">● Active</span></p>
                <?php else: ?>
                    <p><strong>Status:</strong> <span style="color: var(--danger);">● Inactive</span></p>
                <?php endif; ?>
                
                <p><strong>Elasticsearch URL:</strong><br>
                <code><?php echo e($config['elasticsearch_url']); ?></code></p>
                
                <p><strong>Index Prefix:</strong> <code><?php echo e($config['index_prefix']); ?></code></p>
                
                <?php if ($config['last_sync']): ?>
                    <p><strong>Last Sync:</strong> <?php echo formatDate($config['last_sync']); ?></p>
                <?php endif; ?>
                
                <button onclick="testConnection()" class="btn btn-outline" style="margin-top: 16px;">
                    Test Connection
                </button>
                <div id="testResult" style="margin-top: 12px;"></div>
            </div>
        </div>
        
        <!-- Sync Actions -->
        <div class="dashboard-card">
            <h2>🔄 Sync Data</h2>
            <div style="margin-top: 20px;">
                <p>Sync ABBIS data to Elasticsearch:</p>
                
                <button onclick="syncData('sync_field_reports')" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                    📄 Sync Field Reports
                </button>
                
                <button onclick="syncData('sync_logs')" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                    📋 Sync System Logs
                </button>
                
                <button onclick="syncData('sync_metrics')" class="btn btn-primary" style="width: 100%;">
                    📊 Sync Metrics
                </button>
                
                <div id="syncResult" style="margin-top: 16px;"></div>
            </div>
        </div>
        
        <!-- Integration Guide -->
        <div class="dashboard-card">
            <h2>📖 Integration Guide</h2>
            <div style="margin-top: 20px;">
                <h3>Kibana Setup:</h3>
                <ol style="line-height: 2;">
                    <li>Open Kibana at your Kibana URL</li>
                    <li>Go to <strong>Management</strong> → <strong>Index Patterns</strong></li>
                    <li>Create index pattern: <code><?php echo e($config['index_prefix']); ?>-*</code></li>
                    <li>Select time field: <code>@timestamp</code></li>
                    <li>Create index pattern</li>
                </ol>
                
                <h3 style="margin-top: 20px;">Available Indices:</h3>
                <ul>
                    <li><code><?php echo e($config['index_prefix']); ?>-field-reports</code></li>
                    <li><code><?php echo e($config['index_prefix']); ?>-logs</code></li>
                    <li><code><?php echo e($config['index_prefix']); ?>-metrics</code></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function testConnection() {
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML = '<p>Testing connection...</p>';
    
    fetch('../api/elk-integration.php?action=test_connection')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ Connection Successful!</strong><br>
                        Cluster Status: ${data.cluster_status || 'unknown'}
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>✗ Connection Failed</strong><br>
                        ${data.message || 'Unknown error'}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>✗ Error</strong><br>
                    ${error.message}
                </div>
            `;
        });
}

function syncData(action) {
    const resultDiv = document.getElementById('syncResult');
    resultDiv.innerHTML = '<p>Syncing data...</p>';
    
    fetch(`../api/elk-integration.php?action=${action}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ ${data.message}</strong><br>
                        ${data.synced ? `Synced: ${data.synced} records` : ''}
                        ${data.errors ? `Errors: ${data.errors}` : ''}
                    </div>
                `;
                // Reload page after 2 seconds
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>✗ Sync Failed</strong><br>
                        ${data.message || 'Unknown error'}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>✗ Error</strong><br>
                    ${error.message}
                </div>
            `;
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>

