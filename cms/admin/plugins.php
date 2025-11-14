<?php
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure plugins table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cms_plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            version VARCHAR(50) DEFAULT '1.0',
            author VARCHAR(255),
            plugin_file VARCHAR(255) NOT NULL,
            is_active TINYINT(1) DEFAULT 0,
            config TEXT,
            installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Table might already exist
}

$action = $_GET['action'] ?? 'list';
$pluginId = $_GET['plugin_id'] ?? null;
$message = null;
$error = null;

// Handle plugin activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_plugin'])) {
    $pluginId = $_POST['plugin_id'];
    $stmt = $pdo->prepare("SELECT slug, plugin_file FROM cms_plugins WHERE id=?");
    $stmt->execute([$pluginId]);
    $plugin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($plugin) {
        $pluginFile = dirname(__DIR__) . '/plugins/' . $plugin['slug'] . '/' . $plugin['plugin_file'];
        if (file_exists($pluginFile)) {
            $pdo->prepare("UPDATE cms_plugins SET is_active=1 WHERE id=?")->execute([$pluginId]);
            $message = 'Plugin activated successfully';
        } else {
            $error = 'Plugin file not found';
        }
    }
}

// Handle plugin deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_plugin'])) {
    $pluginId = $_POST['plugin_id'];
    $pdo->prepare("UPDATE cms_plugins SET is_active=0 WHERE id=?")->execute([$pluginId]);
    $message = 'Plugin deactivated successfully';
}

// Handle plugin ZIP upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_plugin']) && isset($_FILES['plugin_zip'])) {
    $zipFile = $_FILES['plugin_zip'];
    
    if ($zipFile['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error: ' . $zipFile['error'];
    } elseif ($zipFile['size'] > 50 * 1024 * 1024) { // 50MB limit
        $error = 'Plugin file is too large. Maximum size is 50MB.';
    } else {
        $ext = strtolower(pathinfo($zipFile['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $error = 'Please upload a ZIP file.';
        } else {
            // Create temp directory for extraction
            $tempDir = sys_get_temp_dir() . '/plugin_upload_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipFile['tmp_name']) === TRUE) {
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Find plugin directory (look for .php file with plugin header)
                $pluginDir = null;
                $pluginFile = null;
                $directories = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($directories as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getRealPath());
                        if (preg_match('/Plugin Name:/i', $content)) {
                            $pluginDir = $file->getPath();
                            $pluginFile = $file->getFilename();
                            break;
                        }
                    }
                }
                
                if ($pluginDir && $pluginFile) {
                    // Read plugin info
                    $pluginContent = file_get_contents($pluginDir . '/' . $pluginFile);
                    preg_match('/Plugin Name:\s*(.+)/i', $pluginContent, $nameMatch);
                    preg_match('/Description:\s*(.+)/i', $pluginContent, $descMatch);
                    preg_match('/Version:\s*(.+)/i', $pluginContent, $versionMatch);
                    preg_match('/Author:\s*(.+)/i', $pluginContent, $authorMatch);
                    
                    $pluginName = trim($nameMatch[1] ?? 'Unknown Plugin');
                    $pluginDesc = trim($descMatch[1] ?? '');
                    $pluginVersion = trim($versionMatch[1] ?? '1.0');
                    $pluginAuthor = trim($authorMatch[1] ?? '');
                    $pluginSlug = preg_replace('/[^a-z0-9_-]/i', '', strtolower($pluginName));
                    $pluginSlug = preg_replace('/\s+/', '-', $pluginSlug);
                    
                    // Check if plugin already exists
                    $exists = $pdo->prepare("SELECT id FROM cms_plugins WHERE slug=?");
                    $exists->execute([$pluginSlug]);
                    
                    if ($exists->fetch()) {
                        $error = "Plugin '{$pluginName}' is already installed.";
                    } else {
                        // Move plugin to plugins directory
                        $pluginsDir = dirname(__DIR__) . '/plugins/' . $pluginSlug;
                        if (!is_dir($pluginsDir)) {
                            mkdir($pluginsDir, 0755, true);
                        }
                        
                        // Copy all files
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        
                        foreach ($iterator as $item) {
                            $destPath = $pluginsDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                            if ($item->isDir()) {
                                if (!is_dir($destPath)) {
                                    mkdir($destPath, 0755, true);
                                }
                            } else {
                                copy($item, $destPath);
                            }
                        }
                        
                        // Install to database
                        $pdo->prepare("INSERT INTO cms_plugins (name, slug, description, version, author, plugin_file, is_active, config) VALUES (?, ?, ?, ?, ?, ?, 0, '{}')")
                            ->execute([$pluginName, $pluginSlug, $pluginDesc, $pluginVersion, $pluginAuthor, $pluginFile]);
                        
                        $message = "Plugin '{$pluginName}' uploaded and installed successfully!";
                    }
                } else {
                    $error = 'Invalid plugin structure. Plugin must contain a PHP file with "Plugin Name:" header comment.';
                }
                
                // Clean up temp directory
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($tempDir);
            } else {
                $error = 'Failed to extract ZIP file.';
            }
        }
    }
}

// Handle plugin installation (register plugin from directory)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_plugin'])) {
    $pluginSlug = $_POST['plugin_slug'] ?? '';
    $pluginPath = dirname(__DIR__) . '/plugins/' . basename($pluginSlug);
    
    if (is_dir($pluginPath)) {
        // Look for main plugin file (usually plugin-slug.php or index.php)
        $possibleFiles = [
            $pluginSlug . '.php',
            'index.php',
            basename($pluginSlug) . '.php'
        ];
        
        $pluginFile = null;
        foreach ($possibleFiles as $file) {
            if (file_exists($pluginPath . '/' . $file)) {
                $pluginFile = $file;
                break;
            }
        }
        
        if (!$pluginFile) {
            // Try to find any .php file
            $files = glob($pluginPath . '/*.php');
            if (!empty($files)) {
                $pluginFile = basename($files[0]);
            }
        }
        
        if ($pluginFile) {
            // Read plugin info from PHP file header
            $pluginContent = file_get_contents($pluginPath . '/' . $pluginFile);
            preg_match('/Plugin Name:\s*(.+)/i', $pluginContent, $nameMatch);
            preg_match('/Description:\s*(.+)/i', $pluginContent, $descMatch);
            preg_match('/Version:\s*(.+)/i', $pluginContent, $versionMatch);
            preg_match('/Author:\s*(.+)/i', $pluginContent, $authorMatch);
            
            $pluginName = trim($nameMatch[1] ?? basename($pluginSlug));
            $pluginDesc = trim($descMatch[1] ?? '');
            $pluginVersion = trim($versionMatch[1] ?? '1.0');
            $pluginAuthor = trim($authorMatch[1] ?? '');
            
            // Check if plugin already exists
            $exists = $pdo->prepare("SELECT id FROM cms_plugins WHERE slug=?");
            $exists->execute([$pluginSlug]);
            
            if ($exists->fetch()) {
                $error = 'Plugin is already installed';
            } else {
                $pdo->prepare("INSERT INTO cms_plugins (name, slug, description, version, author, plugin_file, is_active, config) VALUES (?, ?, ?, ?, ?, ?, 0, '{}')")
                    ->execute([$pluginName, $pluginSlug, $pluginDesc, $pluginVersion, $pluginAuthor, $pluginFile]);
                $message = "Plugin '{$pluginName}' installed successfully!";
            }
        } else {
            $error = 'No plugin file found. Plugin must have a .php file.';
        }
    } else {
        $error = 'Plugin directory not found';
    }
}

// Handle plugin deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plugin'])) {
    $pluginId = $_POST['plugin_id'];
    $stmt = $pdo->prepare("SELECT slug, is_active FROM cms_plugins WHERE id=?");
    $stmt->execute([$pluginId]);
    $plugin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($plugin) {
        if ($plugin['is_active']) {
            $error = 'Cannot delete active plugin. Please deactivate it first.';
        } else {
            $pdo->prepare("DELETE FROM cms_plugins WHERE id=?")->execute([$pluginId]);
            $message = 'Plugin deleted successfully';
        }
    }
}

// Scan plugins directory for available plugins
function scanPluginsDirectory() {
    $pluginsDir = dirname(__DIR__) . '/plugins';
    $availablePlugins = [];
    
    if (!is_dir($pluginsDir)) {
        @mkdir($pluginsDir, 0755, true);
        return $availablePlugins;
    }
    
    $dirs = scandir($pluginsDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $pluginPath = $pluginsDir . '/' . $dir;
        if (!is_dir($pluginPath)) continue;
        
        // Look for main plugin file
        $possibleFiles = [
            $dir . '.php',
            'index.php',
            basename($dir) . '.php'
        ];
        
        $pluginFile = null;
        foreach ($possibleFiles as $file) {
            if (file_exists($pluginPath . '/' . $file)) {
                $pluginFile = $file;
                break;
            }
        }
        
        if (!$pluginFile) {
            // Try to find any .php file
            $files = glob($pluginPath . '/*.php');
            if (!empty($files)) {
                $pluginFile = basename($files[0]);
            }
        }
        
        if ($pluginFile) {
            $pluginContent = @file_get_contents($pluginPath . '/' . $pluginFile);
            if ($pluginContent) {
                preg_match('/Plugin Name:\s*(.+)/i', $pluginContent, $nameMatch);
                preg_match('/Description:\s*(.+)/i', $pluginContent, $descMatch);
                preg_match('/Version:\s*(.+)/i', $pluginContent, $versionMatch);
                preg_match('/Author:\s*(.+)/i', $pluginContent, $authorMatch);
                
                $availablePlugins[] = [
                    'slug' => $dir,
                    'name' => trim($nameMatch[1] ?? $dir),
                    'description' => trim($descMatch[1] ?? ''),
                    'version' => trim($versionMatch[1] ?? '1.0'),
                    'author' => trim($authorMatch[1] ?? ''),
                    'plugin_file' => $pluginFile,
                    'path' => $pluginPath
                ];
            }
        }
    }
    
    return $availablePlugins;
}

// Get plugins
$plugins = $pdo->query("SELECT * FROM cms_plugins ORDER BY is_active DESC, name")->fetchAll();

// Calculate statistics
$totalPlugins = count($plugins);
$activePlugins = count(array_filter($plugins, function($p) { return $p['is_active']; }));
$inactivePlugins = $totalPlugins - $activePlugins;

// Get installed plugin slugs
$installedSlugs = array_column($plugins, 'slug');

// Scan for available plugins
$availablePlugins = scanPluginsDirectory();
$uninstalledPlugins = array_filter($availablePlugins, function($plugin) use ($installedSlugs) {
    return !in_array($plugin['slug'], $installedSlugs);
});

// Check plugin file health
function checkPluginHealth($plugin) {
    $pluginFile = dirname(__DIR__) . '/plugins/' . $plugin['slug'] . '/' . $plugin['plugin_file'];
    return file_exists($pluginFile);
}

// Add health status to plugins
foreach ($plugins as &$plugin) {
    $plugin['is_healthy'] = checkPluginHealth($plugin);
}
unset($plugin);

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugins - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'plugins';
    include 'header.php'; 
    ?>
    <style>
        .plugins-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 20px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .stat-card.active { border-left-color: #00a32a; }
        .stat-card.inactive { border-left-color: #dcdcde; }
        .stat-card.total { border-left-color: #2271b1; }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1d2327;
            margin: 10px 0 5px 0;
        }
        .stat-label {
            color: #646970;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .plugins-tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #c3c4c7;
            margin: 30px 0 20px 0;
            padding: 0;
        }
        .plugins-tab {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #646970;
            transition: all 0.2s;
            position: relative;
            top: 2px;
        }
        .plugins-tab:hover {
            color: #2271b1;
            background: #f6f7f7;
        }
        .plugins-tab.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .plugins-search {
            margin: 20px 0;
        }
        .plugins-search input {
            width: 100%;
            max-width: 400px;
            padding: 10px 15px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
        }
        .plugins-search input:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }
        .plugin-card {
            background: white;
            border: 1px solid #c3c4c7;
            padding: 24px;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 20px;
            position: relative;
        }
        .plugin-card:hover {
            border-color: #2271b1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .plugin-card.active {
            border-color: #00a32a;
            background: linear-gradient(to right, #f0f9ff 0%, white 10%);
        }
        .plugin-card.unhealthy {
            border-color: #dc3232;
            background: linear-gradient(to right, #fff0f0 0%, white 10%);
        }
        .plugin-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            gap: 20px;
        }
        .plugin-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .plugin-card.active .plugin-icon {
            background: linear-gradient(135deg, #00a32a 0%, #007cba 100%);
        }
        .plugin-info {
            flex: 1;
            min-width: 0;
        }
        .plugin-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .plugin-name {
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
            margin: 0;
        }
        .plugin-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active {
            background: #00a32a;
            color: white;
        }
        .status-inactive {
            background: #dcdcde;
            color: #646970;
        }
        .status-unhealthy {
            background: #dc3232;
            color: white;
        }
        .plugin-description {
            color: #646970;
            font-size: 14px;
            margin: 8px 0;
            line-height: 1.5;
        }
        .plugin-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            color: #646970;
            font-size: 13px;
            margin: 12px 0;
        }
        .plugin-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .plugin-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .plugin-actions form {
            display: inline;
        }
        .plugins-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .upload-area {
            background: white;
            border: 2px dashed #c3c4c7;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            margin: 20px 0;
        }
        .upload-area:hover {
            border-color: #2271b1;
            background: #f6f7f7;
        }
        .upload-area.dragover {
            border-color: #2271b1;
            background: #f0f9ff;
        }
        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.6;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #646970;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        @media (max-width: 768px) {
            .plugins-grid {
                grid-template-columns: 1fr;
            }
            .plugin-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="margin: 0;">🔌 Plugins</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="plugins-stats">
            <div class="stat-card total">
                <div class="stat-label">Total Plugins</div>
                <div class="stat-value"><?php echo $totalPlugins; ?></div>
            </div>
            <div class="stat-card active">
                <div class="stat-label">Active</div>
                <div class="stat-value"><?php echo $activePlugins; ?></div>
            </div>
            <div class="stat-card inactive">
                <div class="stat-label">Inactive</div>
                <div class="stat-value"><?php echo $inactivePlugins; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Available</div>
                <div class="stat-value"><?php echo count($uninstalledPlugins); ?></div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="plugins-tabs">
            <button class="plugins-tab active" onclick="showPluginTab(event, 'installed')">
                📦 Installed (<?php echo $totalPlugins; ?>)
            </button>
            <button class="plugins-tab" onclick="showPluginTab(event, 'available')">
                📥 Available (<?php echo count($uninstalledPlugins); ?>)
            </button>
            <button class="plugins-tab" onclick="showPluginTab(event, 'upload')">
                ⬆️ Upload Plugin
            </button>
        </div>
        
        <!-- Installed Plugins Tab -->
        <div id="installed" class="tab-content active">
            <div class="plugins-search">
                <input type="text" id="pluginSearch" placeholder="🔍 Search plugins by name, author, or description..." onkeyup="filterPlugins()">
            </div>
            
            <?php if (empty($plugins)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔌</div>
                    <h3>No Plugins Installed</h3>
                    <p>Get started by uploading a plugin or installing one from the available plugins directory.</p>
                </div>
            <?php else: ?>
                <div id="pluginsList" class="plugins-grid">
                    <?php foreach ($plugins as $plugin): ?>
                        <div class="plugin-card <?php echo $plugin['is_active'] ? 'active' : ''; ?> <?php echo !$plugin['is_healthy'] ? 'unhealthy' : ''; ?>" 
                             data-name="<?php echo strtolower(htmlspecialchars($plugin['name'])); ?>"
                             data-author="<?php echo strtolower(htmlspecialchars($plugin['author'] ?? '')); ?>"
                             data-description="<?php echo strtolower(htmlspecialchars($plugin['description'] ?? '')); ?>">
                            <div class="plugin-header">
                                <div class="plugin-icon">🔌</div>
                                <div class="plugin-info">
                                    <div class="plugin-title-row">
                                        <h3 class="plugin-name"><?php echo htmlspecialchars($plugin['name']); ?></h3>
                                        <span class="plugin-status <?php 
                                            echo !$plugin['is_healthy'] ? 'status-unhealthy' : ($plugin['is_active'] ? 'status-active' : 'status-inactive'); 
                                        ?>">
                                            <?php 
                                            if (!$plugin['is_healthy']) {
                                                echo '⚠️ Missing';
                                            } else {
                                                echo $plugin['is_active'] ? '✓ Active' : '○ Inactive';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($plugin['description']): ?>
                                        <p class="plugin-description"><?php echo htmlspecialchars($plugin['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="plugin-meta">
                                        <span class="plugin-meta-item">
                                            <strong>Version:</strong> <?php echo htmlspecialchars($plugin['version']); ?>
                                        </span>
                                        <?php if ($plugin['author']): ?>
                                            <span class="plugin-meta-item">
                                                <strong>By:</strong> <?php echo htmlspecialchars($plugin['author']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($plugin['installed_at']): ?>
                                            <span class="plugin-meta-item">
                                                <strong>Installed:</strong> <?php echo date('M j, Y', strtotime($plugin['installed_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="plugin-actions">
                                <?php if ($plugin['is_active']): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                        <button type="submit" name="deactivate_plugin" class="button">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                        <button type="submit" name="activate_plugin" class="button button-primary">Activate</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this plugin? This will remove it from the database but not delete the plugin files.');">
                                    <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                    <button type="submit" name="delete_plugin" class="button" style="background: #dc3232; color: white; border-color: #dc3232;">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Available Plugins Tab -->
        <div id="available" class="tab-content">
            <?php if (empty($uninstalledPlugins)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>No Available Plugins</h3>
                    <p>No uninstalled plugins found in the plugins directory.</p>
                    <p style="margin-top: 15px; font-size: 13px; color: #646970;">
                        Add plugin folders to <code>cms/plugins/</code> directory to see them here.
                    </p>
                </div>
            <?php else: ?>
                <p style="color: #646970; margin-bottom: 20px;">These plugins are available in your plugins directory but not yet installed:</p>
                <div class="plugins-grid">
                    <?php foreach ($uninstalledPlugins as $plugin): ?>
                        <div class="plugin-card">
                            <div class="plugin-header">
                                <div class="plugin-icon">📦</div>
                                <div class="plugin-info">
                                    <div class="plugin-title-row">
                                        <h3 class="plugin-name"><?php echo htmlspecialchars($plugin['name']); ?></h3>
                                        <span class="plugin-status status-inactive">Not Installed</span>
                                    </div>
                                    <?php if ($plugin['description']): ?>
                                        <p class="plugin-description"><?php echo htmlspecialchars($plugin['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="plugin-meta">
                                        <span class="plugin-meta-item">
                                            <strong>Version:</strong> <?php echo htmlspecialchars($plugin['version']); ?>
                                        </span>
                                        <?php if ($plugin['author']): ?>
                                            <span class="plugin-meta-item">
                                                <strong>By:</strong> <?php echo htmlspecialchars($plugin['author']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="plugin-actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="plugin_slug" value="<?php echo htmlspecialchars($plugin['slug']); ?>">
                                    <button type="submit" name="install_plugin" class="button button-primary">Install Plugin</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Upload Plugin Tab -->
        <div id="upload" class="tab-content">
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📦</div>
                <h3 style="margin: 10px 0;">Upload Plugin ZIP File</h3>
                <p style="color: #646970; margin-bottom: 20px;">Drag and drop your plugin ZIP file here, or click to browse</p>
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="file" name="plugin_zip" accept=".zip" required id="pluginZipInput" style="display: none;" onchange="document.getElementById('uploadForm').submit();">
                    <button type="button" class="button button-primary" onclick="document.getElementById('pluginZipInput').click();">Choose File</button>
                    <button type="submit" name="upload_plugin" class="button button-primary" style="display: none;" id="uploadSubmit">Upload & Install</button>
                </form>
                <p style="margin-top: 20px; font-size: 13px; color: #646970;">
                    <strong>Requirements:</strong><br>
                    • Maximum file size: 50MB<br>
                    • Plugin ZIP must contain a PHP file with "Plugin Name:" header comment<br>
                    • Supported format: ZIP archive
                </p>
            </div>
            
            <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 30px; border-radius: 8px;">
                <h3 style="margin-top: 0;">📝 Creating Custom Plugins</h3>
                <p style="color: #646970;">To create a new plugin:</p>
                <ol style="margin-left: 20px; color: #646970; line-height: 1.8;">
                    <li>Create a folder in <code>cms/plugins/your-plugin-slug/</code></li>
                    <li>Create a PHP file (e.g., <code>your-plugin-slug.php</code>) with a header comment containing:
                        <pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto; margin: 10px 0; border: 1px solid #c3c4c7;"><code>&lt;?php
/**
 * Plugin Name: Your Plugin Name
 * Description: Plugin description here
 * Version: 1.0
 * Author: Your Name
 */

// Your plugin code here</code></pre>
                    </li>
                    <li>Refresh this page to see your plugin in the "Available Plugins" section</li>
                    <li>Click "Install Plugin" to register it in the system</li>
                    <li>Click "Activate" to enable the plugin</li>
                </ol>
            </div>
        </div>
    </div>
    
    <script>
        function showPluginTab(event, tabName) {
            event.preventDefault();
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('plugins-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function filterPlugins() {
            var input = document.getElementById('pluginSearch');
            var filter = input.value.toLowerCase();
            var plugins = document.querySelectorAll('#pluginsList .plugin-card');
            
            plugins.forEach(function(plugin) {
                var name = plugin.getAttribute('data-name');
                var author = plugin.getAttribute('data-author');
                var description = plugin.getAttribute('data-description');
                
                if (name.indexOf(filter) > -1 || author.indexOf(filter) > -1 || description.indexOf(filter) > -1) {
                    plugin.style.display = '';
                } else {
                    plugin.style.display = 'none';
                }
            });
        }
        
        // Drag and drop for upload area
        var uploadArea = document.getElementById('uploadArea');
        var fileInput = document.getElementById('pluginZipInput');
        
        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    document.getElementById('uploadForm').submit();
                }
            });
            
            uploadArea.addEventListener('click', function(e) {
                if (e.target === uploadArea || e.target.closest('.upload-icon') || e.target.closest('h3') || e.target.closest('p')) {
                    fileInput.click();
                }
            });
        }
        
        // Show upload button when file is selected
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    document.getElementById('uploadSubmit').style.display = 'inline-block';
                }
            });
        }
    </script>
</body>
</html>

