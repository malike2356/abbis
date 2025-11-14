<?php
$page_title = 'Map Providers';
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);
$pdo = getDBConnection();
$msg = null; $type='success';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid security token'; $type='danger';
    } else {
        $provider = $_POST['map_provider'] ?? 'google';
        $apiKey = trim($_POST['map_api_key'] ?? '');
        try {
            $up = $pdo->prepare("REPLACE INTO system_config (config_key, config_value) VALUES ('map_provider', ?)");
            $up->execute([$provider]);
            $up = $pdo->prepare("REPLACE INTO system_config (config_key, config_value) VALUES ('map_api_key', ?)");
            $up->execute([$apiKey]);
            $msg = 'Map provider settings saved';
        } catch (PDOException $e) {
            $msg = 'Error: '.$e->getMessage(); $type='danger';
        }
    }
}

$providerRow = $pdo->query("SELECT config_value FROM system_config WHERE config_key='map_provider'")->fetch();
$apiRow = $pdo->query("SELECT config_value FROM system_config WHERE config_key='map_api_key'")->fetch();
$provider = $providerRow['config_value'] ?? 'google';
$apiKey = $apiRow['config_value'] ?? '';

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <nav aria-label="Breadcrumb" style="margin-bottom: 12px;">
        <div style="display:inline-block; padding:6px 10px; border:1px solid var(--border); background: var(--bg); border-radius: 6px; font-size: 13px; color: var(--text);">
            <span>System</span> <span style="opacity:0.6;">→</span> <span>Integrations</span> <span style="opacity:0.6;">→</span> <span>Map Providers</span>
        </div>
    </nav>
    <div class="page-header">
        <h1>🗺️ Map Providers</h1>
        <p>Select and configure the map used by the location picker</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $type==='danger'?'danger':'success'; ?>"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <div class="dashboard-card">
        <form method="post" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items:end;">
            <?php echo CSRF::getTokenField(); ?>
            <div>
                <label class="form-label">Provider</label>
                <select name="map_provider" class="form-control">
                    <option value="google" <?php echo $provider==='google'?'selected':''; ?>>Google Maps</option>
                    <option value="leaflet" <?php echo $provider==='leaflet'?'selected':''; ?>>Leaflet (OpenStreetMap)</option>
                </select>
            </div>
            <div>
                <label class="form-label">API Key (Google only)</label>
                <input name="map_api_key" class="form-control" value="<?php echo e($apiKey); ?>" placeholder="Google Maps API Key">
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn btn-primary">Save Settings</button>
            </div>
        </form>
        <p style="margin-top: 10px; color: var(--secondary); font-size: 13px;">Leaflet (OSM) works without an API key. Google Maps requires a valid API key with Maps JavaScript & Places enabled.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


