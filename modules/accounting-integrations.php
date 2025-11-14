<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/crypto.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();
$msg = null;
$error = null;

// Ensure accounting_integrations table has company_id column (for QuickBooks)
try {
    $pdo->query("SELECT company_id FROM accounting_integrations LIMIT 1");
} catch (PDOException $e) {
    // Column doesn't exist, add it
    try {
        $pdo->exec("ALTER TABLE accounting_integrations ADD COLUMN company_id VARCHAR(100) DEFAULT NULL COMMENT 'QuickBooks Company/Realm ID' AFTER redirect_uri");
    } catch (PDOException $e2) {
        // Ignore if already exists or other error
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_integration'])) {
    try {
        CSRF::validateToken($_POST['csrf_token'] ?? '');
        
        $provider = $_POST['provider'];
        $clientId = trim($_POST['client_id']);
        $clientSecret = trim($_POST['client_secret'] ?? '');
        $redirectUri = trim($_POST['redirect_uri']);

        $currentSecret = null;
        $stmt = $pdo->prepare("SELECT client_secret FROM accounting_integrations WHERE provider = ? LIMIT 1");
        $stmt->execute([$provider]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && !empty($existing['client_secret'])) {
            $currentSecret = $existing['client_secret'];
        }

        if ($clientSecret === '') {
            if ($currentSecret) {
                $encryptedSecret = $currentSecret;
            } else {
                throw new RuntimeException('Client secret is required.');
            }
        } else {
            $encryptedSecret = Crypto::encrypt($clientSecret);
        }
        
        $stmt = $pdo->prepare("INSERT INTO accounting_integrations (provider, client_id, client_secret, redirect_uri, is_active) VALUES (?,?,?,?,1)
            ON DUPLICATE KEY UPDATE client_id=VALUES(client_id), client_secret=VALUES(client_secret), redirect_uri=VALUES(redirect_uri), is_active=1");
        $stmt->execute([
            $provider, $clientId, $encryptedSecret, $redirectUri
        ]);
        $msg = 'Integration credentials saved successfully';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) { 
        $error = 'Error: '.$e->getMessage(); 
    }
}

// Get all integrations
try { 
    $integrations = $pdo->query("SELECT * FROM accounting_integrations ORDER BY provider")->fetchAll(); 
} catch (PDOException $e) { 
    $integrations = []; 
}

// Build integration status map
$integrationStatus = [];
$integrationSecretsStored = [];
foreach ($integrations as $int) {
    $isConnected = !empty($int['access_token']);
    $tokenExpired = false;
    if ($isConnected && $int['token_expires_at']) {
        $tokenExpired = strtotime($int['token_expires_at']) <= time();
    }
    
    $integrationStatus[$int['provider']] = [
        'id' => $int['id'],
        'configured' => !empty($int['client_id']),
        'connected' => $isConnected && !$tokenExpired,
        'token_expires_at' => $int['token_expires_at'],
        'redirect_uri' => $int['redirect_uri']
    ];

    $integrationSecretsStored[$int['provider']] = !empty($int['client_secret']);
}

// Default redirect URI base
$baseUrl = app_url();
?>

<div class="dashboard-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">Accounting Software Integrations</h2>
        <span style="color: var(--secondary); font-size: 14px;">Export journal entries to external accounting software</span>
    </div>
    
    <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom: 16px;"><?php echo e($msg); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 16px;"><?php echo e($error); ?></div>
    <?php endif; ?>
    
    <!-- QuickBooks Integration Card -->
    <div class="integration-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: white;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
                    <span>📊</span> QuickBooks
                </h3>
                <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                    Sync journal entries to QuickBooks Online
                </p>
            </div>
            <div id="qb-status" style="padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                <span id="qb-status-text">Loading...</span>
            </div>
        </div>
        
        <form id="qb-form" method="post" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="provider" value="QuickBooks">
            <input type="hidden" name="save_integration" value="1">
            
            <div>
                <label class="form-label">Client ID</label>
                <input type="text" name="client_id" class="form-control" placeholder="Enter QuickBooks Client ID" required>
            </div>
            <div>
                <label class="form-label">Client Secret</label>
                <input type="password" name="client_secret" class="form-control" placeholder="Enter QuickBooks Client Secret" <?php echo !empty($integrationSecretsStored['QuickBooks']) ? '' : 'required'; ?>>
                <?php if (!empty($integrationSecretsStored['QuickBooks'])): ?>
                    <small style="color: var(--secondary); font-size: 11px;">A secret is already stored securely. Leave blank to keep the existing secret.</small>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label">Redirect URI</label>
                <input type="text" name="redirect_uri" class="form-control" 
                       value="<?php echo e(app_url('api/accounting-integration-oauth.php?action=oauth_callback')); ?>" readonly>
                <small style="color: var(--secondary); font-size: 11px;">Copy this to your QuickBooks app settings</small>
            </div>
            
            <div style="grid-column: 1 / -1; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">Save Credentials</button>
            </div>
        </form>
        
        <div id="qb-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button id="qb-connect-btn" class="btn btn-success" style="display: none;" onclick="connectIntegration('QuickBooks')">
                🔗 Connect to QuickBooks
            </button>
            <button id="qb-disconnect-btn" class="btn btn-outline" style="display: none;" onclick="disconnectIntegration('QuickBooks')">
                ❌ Disconnect
            </button>
            <button id="qb-sync-btn" class="btn btn-primary" style="display: none;" onclick="syncIntegration('QuickBooks')">
                📤 Sync Journal Entries
            </button>
        </div>
        
        <div id="qb-info" style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 4px; font-size: 13px; display: none;">
            <strong>Connection Info:</strong>
            <div id="qb-info-content"></div>
        </div>
    </div>
    
    <!-- Zoho Books Integration Card -->
    <div class="integration-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: white;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
                    <span>📘</span> Zoho Books
                </h3>
                <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                    Sync journal entries to Zoho Books
                </p>
            </div>
            <div id="zoho-status" style="padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                <span id="zoho-status-text">Loading...</span>
            </div>
        </div>
        
        <form id="zoho-form" method="post" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="provider" value="ZohoBooks">
            <input type="hidden" name="save_integration" value="1">
            
            <div>
                <label class="form-label">Client ID</label>
                <input type="text" name="client_id" class="form-control" placeholder="Enter Zoho Books Client ID" required>
            </div>
            <div>
                <label class="form-label">Client Secret</label>
                <input type="password" name="client_secret" class="form-control" placeholder="Enter Zoho Books Client Secret" <?php echo !empty($integrationSecretsStored['ZohoBooks']) ? '' : 'required'; ?>>
                <?php if (!empty($integrationSecretsStored['ZohoBooks'])): ?>
                    <small style="color: var(--secondary); font-size: 11px;">A secret is already stored securely. Leave blank to keep the existing secret.</small>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label">Redirect URI</label>
                <input type="text" name="redirect_uri" class="form-control" 
                       value="<?php echo e(app_url('api/accounting-integration-oauth.php?action=oauth_callback')); ?>" readonly>
                <small style="color: var(--secondary); font-size: 11px;">Copy this to your Zoho Books app settings</small>
            </div>
            
            <div style="grid-column: 1 / -1; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">Save Credentials</button>
            </div>
        </form>
        
        <div id="zoho-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button id="zoho-connect-btn" class="btn btn-success" style="display: none;" onclick="connectIntegration('ZohoBooks')">
                🔗 Connect to Zoho Books
            </button>
            <button id="zoho-disconnect-btn" class="btn btn-outline" style="display: none;" onclick="disconnectIntegration('ZohoBooks')">
                ❌ Disconnect
            </button>
            <button id="zoho-sync-btn" class="btn btn-primary" style="display: none;" onclick="syncIntegration('ZohoBooks')">
                📤 Sync Journal Entries
            </button>
        </div>
        
        <div id="zoho-info" style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 4px; font-size: 13px; display: none;">
            <strong>Connection Info:</strong>
            <div id="zoho-info-content"></div>
        </div>
    </div>
    
    <div style="margin-top: 24px; padding: 16px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid #3b82f6;">
        <h4 style="margin: 0 0 8px 0; color: #1e293b;">📖 Setup Instructions</h4>
        <ol style="margin: 0; padding-left: 20px; color: #475569; line-height: 1.8;">
            <li><strong>Create App:</strong> Go to QuickBooks Developer Portal or Zoho API Console and create a new app</li>
            <li><strong>Get Credentials:</strong> Copy the Client ID and Client Secret from your app</li>
            <li><strong>Set Redirect URI:</strong> Copy the Redirect URI shown above and add it to your app settings</li>
            <li><strong>Save Credentials:</strong> Enter the credentials above and click "Save Credentials"</li>
            <li><strong>Connect:</strong> Click "Connect" to authorize ABBIS to access your accounting software</li>
            <li><strong>Sync:</strong> Once connected, click "Sync Journal Entries" to export your data</li>
        </ol>
    </div>
</div>

<script>
// Load integration status on page load
document.addEventListener('DOMContentLoaded', function() {
    loadIntegrationStatus('QuickBooks');
    loadIntegrationStatus('ZohoBooks');
    
    // Load existing credentials if any
    <?php foreach ($integrations as $int): ?>
    if (document.querySelector('#<?php echo strtolower($int['provider']); ?>-form input[name="client_id"]')) {
        document.querySelector('#<?php echo strtolower($int['provider']); ?>-form input[name="client_id"]').value = '<?php echo e($int['client_id'] ?? ''); ?>';
    }
    <?php endforeach; ?>
});

function loadIntegrationStatus(provider) {
    fetch(`api/accounting-integration-oauth.php?action=get_status&provider=${provider}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateIntegrationUI(provider, data);
            }
        })
        .catch(error => {
            console.error('Error loading status:', error);
            updateIntegrationUI(provider, { configured: false, connected: false });
        });
}

function updateIntegrationUI(provider, status) {
    const providerLower = provider.toLowerCase();
    const statusEl = document.getElementById(`${providerLower}-status`);
    const statusText = document.getElementById(`${providerLower}-status-text`);
    const connectBtn = document.getElementById(`${providerLower}-connect-btn`);
    const disconnectBtn = document.getElementById(`${providerLower}-disconnect-btn`);
    const syncBtn = document.getElementById(`${providerLower}-sync-btn`);
    const infoEl = document.getElementById(`${providerLower}-info`);
    const infoContent = document.getElementById(`${providerLower}-info-content`);
    
    if (!status.configured) {
        statusEl.style.background = '#fef3c7';
        statusEl.style.color = '#92400e';
        statusText.textContent = 'Not Configured';
        connectBtn.style.display = 'none';
        disconnectBtn.style.display = 'none';
        syncBtn.style.display = 'none';
        infoEl.style.display = 'none';
    } else if (!status.connected) {
        statusEl.style.background = '#fee2e2';
        statusEl.style.color = '#991b1b';
        statusText.textContent = 'Not Connected';
        connectBtn.style.display = 'inline-block';
        disconnectBtn.style.display = 'none';
        syncBtn.style.display = 'none';
        infoEl.style.display = 'none';
    } else {
        statusEl.style.background = '#d1fae5';
        statusEl.style.color = '#065f46';
        statusText.textContent = 'Connected';
        connectBtn.style.display = 'none';
        disconnectBtn.style.display = 'inline-block';
        syncBtn.style.display = 'inline-block';
        infoEl.style.display = 'block';
        
        if (status.token_expires_at) {
            const expiresAt = new Date(status.token_expires_at);
            infoContent.innerHTML = `Token expires: ${expiresAt.toLocaleString()}`;
        }
    }
}

function connectIntegration(provider) {
    fetch(`api/accounting-integration-oauth.php?action=get_auth_url&provider=${provider}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.auth_url) {
                // Open OAuth window
                const width = 600;
                const height = 700;
                const left = (window.innerWidth - width) / 2;
                const top = (window.innerHeight - height) / 2;
                
                const authWindow = window.open(
                    data.auth_url,
                    'OAuth',
                    `width=${width},height=${height},left=${left},top=${top}`
                );
                
                // Listen for postMessage from popup
                const messageHandler = function(event) {
                    if (event.data.type === 'oauth_success') {
                        window.removeEventListener('message', messageHandler);
                        alert(provider + ' connected successfully!');
                        loadIntegrationStatus(provider);
                    } else if (event.data.type === 'oauth_error') {
                        window.removeEventListener('message', messageHandler);
                        alert('Connection failed: ' + (event.data.message || 'Unknown error'));
                    }
                };
                window.addEventListener('message', messageHandler);
                
                // Fallback: Poll for window close
                const pollTimer = setInterval(() => {
                    if (authWindow.closed) {
                        clearInterval(pollTimer);
                        window.removeEventListener('message', messageHandler);
                        // Reload status after a short delay
                        setTimeout(() => {
                            loadIntegrationStatus(provider);
                        }, 1000);
                    }
                }, 500);
            } else {
                alert('Error: ' + (data.message || 'Failed to get authorization URL'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error connecting to ' + provider);
        });
}

function disconnectIntegration(provider) {
    if (!confirm(`Are you sure you want to disconnect ${provider}?`)) {
        return;
    }
    
    fetch(`api/accounting-integration-oauth.php?action=disconnect&provider=${provider}`, {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Disconnected successfully');
                loadIntegrationStatus(provider);
            } else {
                alert('Error: ' + (data.message || 'Failed to disconnect'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error disconnecting ' + provider);
        });
}

function syncIntegration(provider) {
    if (!confirm(`Sync journal entries to ${provider}? This will export recent journal entries.`)) {
        return;
    }
    
    const syncBtn = document.getElementById(`${provider.toLowerCase()}-sync-btn`);
    const originalText = syncBtn.textContent;
    syncBtn.disabled = true;
    syncBtn.textContent = '⏳ Syncing...';
    
    fetch(`api/accounting-export.php?action=export&provider=${provider}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ entry_ids: [] }) // Empty array = sync all recent entries
    })
        .then(response => response.json())
        .then(data => {
            syncBtn.disabled = false;
            syncBtn.textContent = originalText;
            
            if (data.success) {
                alert(`Successfully synced ${data.synced} journal entries to ${provider}`);
            } else {
                alert('Error: ' + (data.message || 'Sync failed'));
                if (data.errors && data.errors.length > 0) {
                    console.error('Sync errors:', data.errors);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            syncBtn.disabled = false;
            syncBtn.textContent = originalText;
            alert('Error syncing to ' + provider);
        });
}

// Handle OAuth callback (if redirected back to this page)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('oauth_callback') === 'success') {
    const provider = urlParams.get('provider');
    if (provider) {
        alert(provider + ' connected successfully!');
        setTimeout(() => {
            loadIntegrationStatus(provider);
        }, 500);
        // Remove query params
        window.history.replaceState({}, document.title, window.location.pathname + '?action=integrations');
    }
}

// Handle OAuth errors
if (urlParams.get('oauth_error')) {
    const error = urlParams.get('oauth_error');
    alert('Connection failed: ' + decodeURIComponent(error));
    // Remove query params
    window.history.replaceState({}, document.title, window.location.pathname + '?action=integrations');
}
</script>

<style>
.integration-card {
    transition: box-shadow 0.2s;
}

.integration-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-outline {
    background: white;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

.btn-outline:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
