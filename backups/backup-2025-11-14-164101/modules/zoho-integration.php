<?php
/**
 * Zoho Integration Management
 * Configure and manage integrations with Zoho services
 */
$page_title = 'Zoho Integration';

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
                    // Configure Zoho service
                    $serviceName = sanitizeInput($_POST['service_name'] ?? '');
                    $clientId = sanitizeInput($_POST['client_id'] ?? '');
                    $clientSecret = sanitizeInput($_POST['client_secret'] ?? '');
                    $redirectUri = sanitizeInput($_POST['redirect_uri'] ?? '');
                    
                    if (empty($serviceName) || empty($clientId) || empty($clientSecret)) {
                        throw new Exception('Service name, Client ID, and Client Secret are required');
                    }
                    
                    // Build redirect URI if not provided
                    if (empty($redirectUri)) {
                        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $redirectUri = "{$protocol}://{$host}/abbis3.2/api/zoho-integration.php?action=oauth_callback&service={$serviceName}";
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO zoho_integration 
                        (service_name, client_id, client_secret, redirect_uri) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        client_id = VALUES(client_id),
                        client_secret = VALUES(client_secret),
                        redirect_uri = VALUES(redirect_uri),
                        updated_at = NOW()
                    ");
                    $stmt->execute([$serviceName, $clientId, $clientSecret, $redirectUri]);
                    
                    $message = ucfirst($serviceName) . ' configuration saved. Click "Connect" to authorize.';
                    $messageType = 'success';
                    break;
                    
                case 'disconnect':
                    // Disconnect service
                    $serviceName = sanitizeInput($_POST['service_name'] ?? '');
                    $stmt = $pdo->prepare("UPDATE zoho_integration SET is_active = 0, access_token = NULL, refresh_token = NULL WHERE service_name = ?");
                    $stmt->execute([$serviceName]);
                    $message = ucfirst($serviceName) . ' disconnected';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get all Zoho configurations
try {
    $stmt = $pdo->query("SELECT * FROM zoho_integration ORDER BY service_name");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize empty configs for services that don't exist
    $services = ['crm', 'inventory', 'books', 'payroll', 'hr'];
    $existingServices = array_column($configs, 'service_name');
    
    foreach ($services as $service) {
        if (!in_array($service, $existingServices)) {
            $configs[] = [
                'service_name' => $service,
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => '',
                'is_active' => 0,
                'last_sync' => null
            ];
        }
    }
} catch (PDOException $e) {
    $configs = [];
}

// Get integration status
$statusResponse = @file_get_contents(
    (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
    $_SERVER['HTTP_HOST'] . 
    dirname($_SERVER['PHP_SELF']) . 
    '/../api/zoho-integration.php?action=get_status'
);
$statusData = $statusResponse ? json_decode($statusResponse, true) : null;
$status = $statusData['status'] ?? [];

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔗 Zoho Integration</h1>
        <p>Connect and synchronize data with Zoho CRM, Inventory, Books, Payroll, and HR</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Integration Status Overview -->
    <div class="dashboard-grid">
        <?php 
        $services = [
            'crm' => ['name' => 'Zoho CRM', 'icon' => '👥', 'desc' => 'Sync clients and contacts'],
            'inventory' => ['name' => 'Zoho Inventory', 'icon' => '📦', 'desc' => 'Sync materials and products'],
            'books' => ['name' => 'Zoho Books', 'icon' => '💰', 'desc' => 'Sync invoices and payments'],
            'payroll' => ['name' => 'Zoho Payroll', 'icon' => '💵', 'desc' => 'Sync worker payroll'],
            'hr' => ['name' => 'Zoho HR', 'icon' => '👷', 'desc' => 'Sync employee data']
        ];
        
        foreach ($services as $serviceKey => $serviceInfo): 
            $config = array_filter($configs, fn($c) => $c['service_name'] === $serviceKey);
            $config = $config ? reset($config) : null;
            $isConnected = $status[$serviceKey]['connected'] ?? false;
            $lastSync = $config['last_sync'] ?? null;
        ?>
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                    <div>
                        <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 28px;"><?php echo $serviceInfo['icon']; ?></span>
                            <?php echo e($serviceInfo['name']); ?>
                        </h2>
                        <p style="margin: 8px 0 0 0; color: var(--secondary); font-size: 14px;">
                            <?php echo e($serviceInfo['desc']); ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <?php if ($isConnected): ?>
                            <span style="color: var(--success); font-weight: 600;">● Connected</span><br>
                        <?php else: ?>
                            <span style="color: var(--danger); font-weight: 600;">● Disconnected</span><br>
                        <?php endif; ?>
                        <?php if ($lastSync): ?>
                            <small style="color: var(--secondary);">Last sync: <?php echo formatDate($lastSync, 'M j, Y H:i'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Configuration Form -->
                <form method="POST" class="form-grid-compact" style="margin-bottom: 16px;">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="configure">
                    <input type="hidden" name="service_name" value="<?php echo e($serviceKey); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Client ID</label>
                        <input type="text" name="client_id" class="form-control" 
                               value="<?php echo e($config['client_id'] ?? ''); ?>" 
                               placeholder="From Zoho Developer Console">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input type="password" name="client_secret" class="form-control" 
                               value="<?php echo e($config['client_secret'] ?? ''); ?>" 
                               placeholder="From Zoho Developer Console">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Redirect URI</label>
                        <input type="text" name="redirect_uri" class="form-control" 
                               value="<?php echo e($config['redirect_uri'] ?? ''); ?>" 
                               placeholder="Auto-generated if empty">
                        <small class="form-text">Copy this URL to Zoho Developer Console</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                    </div>
                </form>
                
                <!-- Actions -->
                <div style="display: flex; gap: 10px;">
                    <?php if (!empty($config['client_id'])): ?>
                        <a href="<?php 
                            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'];
                            $redirectUri = urlencode($config['redirect_uri'] ?: "{$protocol}://{$host}/abbis3.2/api/zoho-integration.php?action=oauth_callback&service={$serviceKey}");
                            $scopes = [
                                'crm' => 'ZohoCRM.modules.ALL',
                                'inventory' => 'ZohoInventory.fullaccess.all',
                                'books' => 'ZohoBooks.fullaccess.all',
                                'payroll' => 'ZohoPayroll.fullaccess.all',
                                'hr' => 'ZohoPeople.profile.READ,ZohoPeople.employment.READ'
                            ];
                            echo "https://accounts.zoho.com/oauth/v2/auth?scope=" . urlencode($scopes[$serviceKey] ?? '') . 
                                 "&client_id={$config['client_id']}&response_type=code&access_type=offline&redirect_uri={$redirectUri}&state={$serviceKey}";
                        ?>" class="btn btn-success" target="_blank">
                            🔗 Connect to <?php echo e($serviceInfo['name']); ?>
                        </a>
                        
                        <?php if ($isConnected): ?>
                            <button onclick="syncService('<?php echo $serviceKey; ?>')" class="btn btn-primary">
                                🔄 Sync Now
                            </button>
                            <form method="POST" style="display: inline;">
                                <?php echo CSRF::getTokenField(); ?>
                                <input type="hidden" name="action" value="disconnect">
                                <input type="hidden" name="service_name" value="<?php echo e($serviceKey); ?>">
                                <button type="submit" class="btn btn-outline" onclick="return confirm('Disconnect <?php echo e($serviceInfo['name']); ?>?');">
                                    Disconnect
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Quick Start Guide -->
    <div class="dashboard-card" style="margin-top: 30px;">
        <h2>📖 Quick Start Guide</h2>
        <ol style="line-height: 2;">
            <li><strong>Create Zoho Application:</strong>
                <ul>
                    <li>Go to <a href="https://api-console.zoho.com/" target="_blank">Zoho API Console</a></li>
                    <li>Click "Add Client" → Select "Server-based Applications"</li>
                    <li>Copy the Client ID and Client Secret</li>
                    <li>Add the Redirect URI shown above</li>
                </ul>
            </li>
            <li><strong>Configure in ABBIS:</strong>
                <ul>
                    <li>Enter Client ID and Client Secret above</li>
                    <li>Click "Save Configuration"</li>
                </ul>
            </li>
            <li><strong>Connect:</strong>
                <ul>
                    <li>Click "Connect to [Service]" button</li>
                    <li>Authorize the application in Zoho</li>
                    <li>You'll be redirected back and connected</li>
                </ul>
            </li>
            <li><strong>Sync Data:</strong>
                <ul>
                    <li>Click "Sync Now" to synchronize data</li>
                    <li>Data syncs automatically or can be triggered manually</li>
                </ul>
            </li>
        </ol>
    </div>
</div>

<script>
function syncService(service) {
    const actionMap = {
        'crm': 'sync_crm',
        'inventory': 'sync_inventory',
        'books': 'sync_books',
        'payroll': 'sync_payroll',
        'hr': 'sync_hr'
    };
    
    const action = actionMap[service];
    if (!action) {
        alert('Invalid service');
        return;
    }
    
    fetch(`../api/zoho-integration.php?action=${action}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

