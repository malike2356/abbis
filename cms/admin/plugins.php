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

// Get installed plugin slugs
$installedSlugs = array_column($plugins, 'slug');

// Scan for available plugins
$availablePlugins = scanPluginsDirectory();
$uninstalledPlugins = array_filter($availablePlugins, function($plugin) use ($installedSlugs) {
    return !in_array($plugin['slug'], $installedSlugs);
});

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
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
        .plugin-card { background: white; border: 2px solid #c3c4c7; padding: 20px; border-radius: 4px; transition: all 0.2s; margin-bottom: 20px; }
        .plugin-card:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .plugin-card.active { border-color: #00a32a; background: #f0f9ff; }
        .plugin-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .plugin-info { flex: 1; }
        .plugin-status { padding: 5px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; }
        .status-active { background: #00a32a; color: white; }
        .status-inactive { background: #dcdcde; color: #646970; }
        .plugin-meta { color: #646970; font-size: 13px; margin: 5px 0; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Plugins</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <h2>Installed Plugins</h2>
        <p>Manage your installed plugins. Activate or deactivate them as needed.</p>
        
        <!-- Plugin Upload Form -->
        <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0;">📦 Upload Plugin</h3>
            <p style="margin: 10px 0; color: #646970;">Upload a plugin ZIP file to install it. The plugin must contain a PHP file with "Plugin Name:" header comment.</p>
            <form method="post" enctype="multipart/form-data" style="margin-top: 15px;">
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <input type="file" name="plugin_zip" accept=".zip" required style="padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; width: 100%;">
                        <small style="color: #646970; display: block; margin-top: 5px;">Maximum file size: 50MB. Plugin ZIP must contain a PHP file with plugin header.</small>
                    </div>
                    <button type="submit" name="upload_plugin" class="button button-primary">Upload & Install Plugin</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($uninstalledPlugins)): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Available Plugins to Install</h3>
                <p>These plugins are available in your plugins directory but not yet installed:</p>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:20px; margin-top:15px;">
                    <?php foreach ($uninstalledPlugins as $plugin): ?>
                        <div style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">
                            <h4 style="margin-top: 0;"><?php echo htmlspecialchars($plugin['name']); ?></h4>
                            <p style="color: #646970; font-size: 13px; margin: 5px 0;"><?php echo htmlspecialchars($plugin['description']); ?></p>
                            <p style="color: #646970; font-size: 12px; margin: 5px 0;">
                                <strong>Version:</strong> <?php echo htmlspecialchars($plugin['version']); ?><br>
                                <?php if ($plugin['author']): ?>
                                    <strong>Author:</strong> <?php echo htmlspecialchars($plugin['author']); ?>
                                <?php endif; ?>
                            </p>
                            <form method="post" style="margin-top: 10px;">
                                <input type="hidden" name="plugin_slug" value="<?php echo htmlspecialchars($plugin['slug']); ?>">
                                <input type="submit" name="install_plugin" value="Install Plugin" class="button button-primary">
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($plugins)): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; text-align: center; margin-top: 20px;">
                <p style="margin: 0; color: #646970;">No plugins installed yet. Add plugin folders to <code>cms/plugins/</code> directory to install them.</p>
                <p style="margin: 10px 0 0 0; font-size: 13px; color: #646970;">
                    <strong>Plugin Structure:</strong><br>
                    <code>cms/plugins/your-plugin-slug/your-plugin-slug.php</code><br>
                    The PHP file should include a header comment with:<br>
                    <code>Plugin Name: Your Plugin Name</code><br>
                    <code>Description: Plugin description</code><br>
                    <code>Version: 1.0</code><br>
                    <code>Author: Author Name</code>
                </p>
            </div>
        <?php else: ?>
            <div style="margin-top: 20px;">
                <?php foreach ($plugins as $plugin): ?>
                    <div class="plugin-card <?php echo $plugin['is_active'] ? 'active' : ''; ?>">
                        <div class="plugin-header">
                            <div class="plugin-info">
                                <h3 style="margin: 0 0 5px 0;">
                                    <?php echo htmlspecialchars($plugin['name']); ?>
                                    <span class="plugin-status <?php echo $plugin['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $plugin['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </h3>
                                <p style="margin: 5px 0; color: #646970;"><?php echo htmlspecialchars($plugin['description'] ?? ''); ?></p>
                                <div class="plugin-meta">
                                    Version <?php echo htmlspecialchars($plugin['version']); ?>
                                    <?php if ($plugin['author']): ?>
                                        | By <?php echo htmlspecialchars($plugin['author']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <?php if ($plugin['is_active']): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                    <input type="submit" name="deactivate_plugin" value="Deactivate" class="button">
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this plugin? This will remove it from the database but not delete the plugin files.');">
                                    <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                    <input type="submit" name="delete_plugin" value="Delete" class="button" style="background: #dc3232; color: white; border-color: #dc3232;">
                                </form>
                            <?php else: ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                    <input type="submit" name="activate_plugin" value="Activate" class="button button-primary">
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this plugin? This will remove it from the database but not delete the plugin files.');">
                                    <input type="hidden" name="plugin_id" value="<?php echo $plugin['id']; ?>">
                                    <input type="submit" name="delete_plugin" value="Delete" class="button" style="background: #dc3232; color: white; border-color: #dc3232;">
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 30px;">
            <h3>Creating Custom Plugins</h3>
            <p>To create a new plugin:</p>
            <ol style="margin-left: 20px; color: #646970;">
                <li>Create a folder in <code>cms/plugins/your-plugin-slug/</code></li>
                <li>Create a PHP file (e.g., <code>your-plugin-slug.php</code>) with a header comment containing:
                    <pre style="background: #f6f7f7; padding: 10px; border-radius: 4px; overflow-x: auto; margin: 10px 0;"><code>&lt;?php
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
</body>
</html>

