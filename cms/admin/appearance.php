<?php
/**
 * Appearance Management - Themes & Customization
 */
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

// Ensure cms_themes table has version column
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM cms_themes LIKE 'version'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE cms_themes ADD COLUMN version VARCHAR(50) DEFAULT '1.0' AFTER description");
    }
} catch (Exception $e) {
    // Column might already exist or table doesn't exist yet
}

// Ensure default theme exists
try {
    $defaultTheme = $pdo->query("SELECT * FROM cms_themes WHERE slug='default' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$defaultTheme) {
        $defaultConfig = json_encode([
            'primary_color' => '#0ea5e9',
            'secondary_color' => '#64748b',
            'header_bg' => '#ffffff',
            'header_text_color' => '#1e293b',
            'footer_bg' => '#1e293b',
            'footer_text_color' => '#ffffff',
            'link_color' => '#0ea5e9',
            'button_bg' => '#0ea5e9',
            'button_text' => '#ffffff',
            'background_color' => '#ffffff',
            'text_color' => '#1e293b'
        ]);
        // Check if version column exists before inserting
        $versionColExists = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM cms_themes LIKE 'version'");
            $versionColExists = $colCheck->rowCount() > 0;
        } catch (Exception $e) {}
        
        if ($versionColExists) {
            $pdo->prepare("INSERT INTO cms_themes (name, slug, description, version, config, is_active) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute(['ABBIS Default', 'default', 'Default ABBIS theme with customizable colors and styles', '1.0', $defaultConfig, 1]);
        } else {
            $pdo->prepare("INSERT INTO cms_themes (name, slug, description, config, is_active) VALUES (?, ?, ?, ?, ?)")
                ->execute(['ABBIS Default', 'default', 'Default ABBIS theme with customizable colors and styles', $defaultConfig, 1]);
        }
    }
} catch (Exception $e) {}

$action = $_GET['action'] ?? 'list';
$themeId = $_GET['theme_id'] ?? null;
$message = null;
$error = null;

// Handle theme activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_theme'])) {
    $themeId = $_POST['theme_id'];
    $pdo->exec("UPDATE cms_themes SET is_active=0");
    $pdo->prepare("UPDATE cms_themes SET is_active=1 WHERE id=?")->execute([$themeId]);
    $message = 'Theme activated successfully';
}

// Handle theme ZIP upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_theme']) && isset($_FILES['theme_zip'])) {
    $zipFile = $_FILES['theme_zip'];
    
    if ($zipFile['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error: ' . $zipFile['error'];
    } elseif ($zipFile['size'] > 50 * 1024 * 1024) { // 50MB limit
        $error = 'Theme file is too large. Maximum size is 50MB.';
    } else {
        $ext = strtolower(pathinfo($zipFile['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $error = 'Please upload a ZIP file.';
        } else {
            // Create temp directory for extraction
            $tempDir = sys_get_temp_dir() . '/theme_upload_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipFile['tmp_name']) === TRUE) {
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Find theme directory (look for style.css)
                $themeDir = null;
                $directories = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($directories as $file) {
                    if ($file->isFile() && $file->getFilename() === 'style.css') {
                        $themeDir = $file->getPath();
                        break;
                    }
                }
                
                if ($themeDir && file_exists($themeDir . '/style.css')) {
                    // Read theme info
                    $styleContent = file_get_contents($themeDir . '/style.css');
                    preg_match('/Theme Name:\s*(.+)/i', $styleContent, $nameMatch);
                    preg_match('/Description:\s*(.+)/i', $styleContent, $descMatch);
                    preg_match('/Version:\s*(.+)/i', $styleContent, $versionMatch);
                    
                    $themeName = trim($nameMatch[1] ?? 'Unknown Theme');
                    $themeDesc = trim($descMatch[1] ?? '');
                    $themeVersion = trim($versionMatch[1] ?? '1.0');
                    $themeSlug = preg_replace('/[^a-z0-9_-]/i', '', strtolower($themeName));
                    $themeSlug = preg_replace('/\s+/', '-', $themeSlug);
                    
                    // Check if theme already exists
                    $exists = $pdo->prepare("SELECT id FROM cms_themes WHERE slug=?");
                    $exists->execute([$themeSlug]);
                    
                    if ($exists->fetch()) {
                        $error = "Theme '{$themeName}' is already installed.";
                    } else {
                        // Move theme to themes directory
                        $themesDir = dirname(__DIR__) . '/themes/' . $themeSlug;
                        if (!is_dir($themesDir)) {
                            mkdir($themesDir, 0755, true);
                        }
                        
                        // Copy all files
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        
                        foreach ($iterator as $item) {
                            $destPath = $themesDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                            if ($item->isDir()) {
                                if (!is_dir($destPath)) {
                                    mkdir($destPath, 0755, true);
                                }
                            } else {
                                copy($item, $destPath);
                            }
                        }
                        
                        // Install to database
                        $defaultConfig = json_encode([
                            'primary_color' => '#f39c12',
                            'secondary_color' => '#34495e'
                        ]);
                        
                        $versionColExists = false;
                        try {
                            $colCheck = $pdo->query("SHOW COLUMNS FROM cms_themes LIKE 'version'");
                            $versionColExists = $colCheck->rowCount() > 0;
                        } catch (Exception $e) {}
                        
                        if ($versionColExists) {
                            $pdo->prepare("INSERT INTO cms_themes (name, slug, description, version, config, is_active) VALUES (?, ?, ?, ?, ?, 0)")
                                ->execute([$themeName, $themeSlug, $themeDesc, $themeVersion, $defaultConfig]);
                        } else {
                            $pdo->prepare("INSERT INTO cms_themes (name, slug, description, config, is_active) VALUES (?, ?, ?, ?, 0)")
                                ->execute([$themeName, $themeSlug, $themeDesc, $defaultConfig]);
                        }
                        
                        $message = "Theme '{$themeName}' uploaded and installed successfully!";
                    }
                } else {
                    $error = 'Invalid theme structure. Theme must contain a style.css file.';
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

// Handle theme installation (register theme from directory)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_theme'])) {
    $themeSlug = $_POST['theme_slug'] ?? '';
    $themePath = dirname(__DIR__) . '/themes/' . basename($themeSlug);
    
    if (is_dir($themePath) && file_exists($themePath . '/style.css')) {
        // Read theme info from style.css
        $styleContent = file_get_contents($themePath . '/style.css');
        preg_match('/Theme Name:\s*(.+)/i', $styleContent, $nameMatch);
        preg_match('/Description:\s*(.+)/i', $styleContent, $descMatch);
        preg_match('/Version:\s*(.+)/i', $styleContent, $versionMatch);
        
        $themeName = trim($nameMatch[1] ?? basename($themeSlug));
        $themeDesc = trim($descMatch[1] ?? '');
        $themeVersion = trim($versionMatch[1] ?? '1.0');
        
        // Check if theme already exists
        $existing = $pdo->prepare("SELECT id FROM cms_themes WHERE slug=?")->execute([$themeSlug]);
        $exists = $pdo->prepare("SELECT id FROM cms_themes WHERE slug=?");
        $exists->execute([$themeSlug]);
        
        if ($exists->fetch()) {
            $error = 'Theme is already installed';
        } else {
            // Default config based on theme
            $defaultConfig = json_encode([
                'primary_color' => '#f39c12',
                'secondary_color' => '#34495e'
            ]);
            
            // Check if version column exists
            $versionColExists = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM cms_themes LIKE 'version'");
                $versionColExists = $colCheck->rowCount() > 0;
            } catch (Exception $e) {}
            
            if ($versionColExists) {
                $pdo->prepare("INSERT INTO cms_themes (name, slug, description, version, config, is_active) VALUES (?, ?, ?, ?, ?, 0)")
                    ->execute([$themeName, $themeSlug, $themeDesc, $themeVersion, $defaultConfig]);
            } else {
                $pdo->prepare("INSERT INTO cms_themes (name, slug, description, config, is_active) VALUES (?, ?, ?, ?, 0)")
                    ->execute([$themeName, $themeSlug, $themeDesc, $defaultConfig]);
            }
            $message = "Theme '{$themeName}' installed successfully!";
        }
    } else {
        $error = 'Theme not found or invalid theme structure';
    }
}

// Handle theme deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_theme'])) {
    $themeId = $_POST['theme_id'];
    $stmt = $pdo->prepare("SELECT slug, is_active FROM cms_themes WHERE id=?");
    $stmt->execute([$themeId]);
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($theme) {
        if ($theme['is_active']) {
            $error = 'Cannot delete active theme. Please activate another theme first.';
        } else {
            $pdo->prepare("DELETE FROM cms_themes WHERE id=?")->execute([$themeId]);
            $message = 'Theme deleted successfully';
        }
    }
}

// Scan themes directory for available themes
function scanThemesDirectory() {
    $themesDir = dirname(__DIR__) . '/themes';
    $availableThemes = [];
    
    if (is_dir($themesDir)) {
        $dirs = scandir($themesDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            
            $themePath = $themesDir . '/' . $dir;
            $stylePath = $themePath . '/style.css';
            
            if (is_dir($themePath) && file_exists($stylePath)) {
                $styleContent = @file_get_contents($stylePath);
                if ($styleContent) {
                    preg_match('/Theme Name:\s*(.+)/i', $styleContent, $nameMatch);
                    preg_match('/Description:\s*(.+)/i', $styleContent, $descMatch);
                    preg_match('/Version:\s*(.+)/i', $styleContent, $versionMatch);
                    
                    $availableThemes[] = [
                        'slug' => $dir,
                        'name' => trim($nameMatch[1] ?? $dir),
                        'description' => trim($descMatch[1] ?? ''),
                        'version' => trim($versionMatch[1] ?? '1.0'),
                        'path' => $themePath
                    ];
                }
            }
        }
    }
    
    return $availableThemes;
}

// Handle theme configuration save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme_config'])) {
    $themeId = $_POST['theme_id'];
    $config = [
        'primary_color' => trim($_POST['primary_color'] ?? '#0ea5e9'),
        'secondary_color' => trim($_POST['secondary_color'] ?? '#64748b'),
        'header_bg' => trim($_POST['header_bg'] ?? '#ffffff'),
        'header_text_color' => trim($_POST['header_text_color'] ?? '#1e293b'),
        'footer_bg' => trim($_POST['footer_bg'] ?? '#1e293b'),
        'footer_text_color' => trim($_POST['footer_text_color'] ?? '#ffffff'),
        'link_color' => trim($_POST['link_color'] ?? '#0ea5e9'),
        'button_bg' => trim($_POST['button_bg'] ?? '#0ea5e9'),
        'button_text' => trim($_POST['button_text'] ?? '#ffffff'),
        'background_color' => trim($_POST['background_color'] ?? '#ffffff'),
        'text_color' => trim($_POST['text_color'] ?? '#1e293b'),
        'font_family' => trim($_POST['font_family'] ?? 'system'),
        'font_size' => trim($_POST['font_size'] ?? '16px'),
        'border_radius' => trim($_POST['border_radius'] ?? '6px'),
    ];
    
    $configJson = json_encode($config);
    $pdo->prepare("UPDATE cms_themes SET config=? WHERE id=?")->execute([$configJson, $themeId]);
    $message = 'Theme configuration saved successfully';
}

// Get themes
$themes = $pdo->query("SELECT * FROM cms_themes ORDER BY is_active DESC, name")->fetchAll();

// Get installed theme slugs
$installedSlugs = array_column($themes, 'slug');

// Scan for available themes
$availableThemes = scanThemesDirectory();
$uninstalledThemes = array_filter($availableThemes, function($theme) use ($installedSlugs) {
    return !in_array($theme['slug'], $installedSlugs);
});

// Get theme for editing
$theme = null;
if ($themeId && $action === 'customize') {
    $stmt = $pdo->prepare("SELECT * FROM cms_themes WHERE id=?");
    $stmt->execute([$themeId]);
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($theme) {
        $theme['config'] = json_decode($theme['config'] ?? '{}', true);
    }
}

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
    <title>Appearance - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php 
    $currentPage = 'appearance';
    include 'header.php'; 
    ?>
    <style>
        .theme-card { background: white; border: 2px solid #c3c4c7; padding: 20px; border-radius: 4px; transition: all 0.2s; }
        .theme-card:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .theme-card.active { border-color: #2271b1; background: #f0f9ff; }
        .color-picker { width: 100%; height: 40px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer; }
        .config-preview { padding: 20px; background: #f6f7f7; border-radius: 4px; margin-top: 20px; }
        .config-section { margin-bottom: 30px; }
        .config-section h3 { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #c3c4c7; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .color-input-group { display: flex; gap: 10px; align-items: center; }
        .color-input-group input[type="color"] { width: 60px; height: 40px; border: 1px solid #c3c4c7; border-radius: 4px; }
        .color-input-group input[type="text"] { flex: 1; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Appearance</h1>
        
        <div class="nav-tab-wrapper" style="margin-bottom: 20px; border-bottom: 1px solid #c3c4c7;">
            <a href="appearance.php" class="nav-tab <?php echo $action === 'list' || !$action ? 'nav-tab-active' : ''; ?>">Themes</a>
            <a href="menus.php" class="nav-tab">Menus</a>
            <a href="widgets.php" class="nav-tab">Widgets</a>
        </div>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'customize' && $theme): ?>
            <div style="margin-bottom: 20px;">
                <a href="appearance.php" class="button">← Back to Themes</a>
                <h2 style="margin-top: 20px;">Customize: <?php echo htmlspecialchars($theme['name']); ?></h2>
                <p><?php echo htmlspecialchars($theme['description'] ?? ''); ?></p>
            </div>
            
            <form method="post" class="post-form" style="background: white; padding: 20px; border: 1px solid #c3c4c7;">
                <input type="hidden" name="theme_id" value="<?php echo $theme['id']; ?>">
                
                <div class="config-section">
                    <h3>🎨 Color Scheme</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Primary Color</label>
                            <div class="color-input-group">
                                <input type="color" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($theme['config']['primary_color'] ?? '#0ea5e9'); ?>" onchange="updateColorText('primary_color')">
                                <input type="text" id="primary_color_text" value="<?php echo htmlspecialchars($theme['config']['primary_color'] ?? '#0ea5e9'); ?>" onchange="updateColorPicker('primary_color')" pattern="^#[0-9A-Fa-f]{6}$" placeholder="#0ea5e9">
                            </div>
                            <p class="description">Main brand color used for links, buttons, and accents</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Secondary Color</label>
                            <div class="color-input-group">
                                <input type="color" id="secondary_color" name="secondary_color" value="<?php echo htmlspecialchars($theme['config']['secondary_color'] ?? '#64748b'); ?>" onchange="updateColorText('secondary_color')">
                                <input type="text" id="secondary_color_text" value="<?php echo htmlspecialchars($theme['config']['secondary_color'] ?? '#64748b'); ?>" onchange="updateColorPicker('secondary_color')" pattern="^#[0-9A-Fa-f]{6}$" placeholder="#64748b">
                            </div>
                            <p class="description">Secondary accent color</p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Link Color</label>
                            <div class="color-input-group">
                                <input type="color" id="link_color" name="link_color" value="<?php echo htmlspecialchars($theme['config']['link_color'] ?? '#0ea5e9'); ?>" onchange="updateColorText('link_color')">
                                <input type="text" id="link_color_text" value="<?php echo htmlspecialchars($theme['config']['link_color'] ?? '#0ea5e9'); ?>" onchange="updateColorPicker('link_color')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Background Color</label>
                            <div class="color-input-group">
                                <input type="color" id="background_color" name="background_color" value="<?php echo htmlspecialchars($theme['config']['background_color'] ?? '#ffffff'); ?>" onchange="updateColorText('background_color')">
                                <input type="text" id="background_color_text" value="<?php echo htmlspecialchars($theme['config']['background_color'] ?? '#ffffff'); ?>" onchange="updateColorPicker('background_color')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Text Color</label>
                            <div class="color-input-group">
                                <input type="color" id="text_color" name="text_color" value="<?php echo htmlspecialchars($theme['config']['text_color'] ?? '#1e293b'); ?>" onchange="updateColorText('text_color')">
                                <input type="text" id="text_color_text" value="<?php echo htmlspecialchars($theme['config']['text_color'] ?? '#1e293b'); ?>" onchange="updateColorPicker('text_color')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="config-section">
                    <h3>📱 Header Styling</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Header Background</label>
                            <div class="color-input-group">
                                <input type="color" id="header_bg" name="header_bg" value="<?php echo htmlspecialchars($theme['config']['header_bg'] ?? '#ffffff'); ?>" onchange="updateColorText('header_bg')">
                                <input type="text" id="header_bg_text" value="<?php echo htmlspecialchars($theme['config']['header_bg'] ?? '#ffffff'); ?>" onchange="updateColorPicker('header_bg')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Header Text Color</label>
                            <div class="color-input-group">
                                <input type="color" id="header_text_color" name="header_text_color" value="<?php echo htmlspecialchars($theme['config']['header_text_color'] ?? '#1e293b'); ?>" onchange="updateColorText('header_text_color')">
                                <input type="text" id="header_text_color_text" value="<?php echo htmlspecialchars($theme['config']['header_text_color'] ?? '#1e293b'); ?>" onchange="updateColorPicker('header_text_color')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="config-section">
                    <h3>🔽 Footer Styling</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Footer Background</label>
                            <div class="color-input-group">
                                <input type="color" id="footer_bg" name="footer_bg" value="<?php echo htmlspecialchars($theme['config']['footer_bg'] ?? '#1e293b'); ?>" onchange="updateColorText('footer_bg')">
                                <input type="text" id="footer_bg_text" value="<?php echo htmlspecialchars($theme['config']['footer_bg'] ?? '#1e293b'); ?>" onchange="updateColorPicker('footer_bg')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Footer Text Color</label>
                            <div class="color-input-group">
                                <input type="color" id="footer_text_color" name="footer_text_color" value="<?php echo htmlspecialchars($theme['config']['footer_text_color'] ?? '#ffffff'); ?>" onchange="updateColorText('footer_text_color')">
                                <input type="text" id="footer_text_color_text" value="<?php echo htmlspecialchars($theme['config']['footer_text_color'] ?? '#ffffff'); ?>" onchange="updateColorPicker('footer_text_color')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="config-section">
                    <h3>🔘 Button Styling</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Button Background</label>
                            <div class="color-input-group">
                                <input type="color" id="button_bg" name="button_bg" value="<?php echo htmlspecialchars($theme['config']['button_bg'] ?? '#0ea5e9'); ?>" onchange="updateColorText('button_bg')">
                                <input type="text" id="button_bg_text" value="<?php echo htmlspecialchars($theme['config']['button_bg'] ?? '#0ea5e9'); ?>" onchange="updateColorPicker('button_bg')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Button Text Color</label>
                            <div class="color-input-group">
                                <input type="color" id="button_text" name="button_text" value="<?php echo htmlspecialchars($theme['config']['button_text'] ?? '#ffffff'); ?>" onchange="updateColorText('button_text')">
                                <input type="text" id="button_text_text" value="<?php echo htmlspecialchars($theme['config']['button_text'] ?? '#ffffff'); ?>" onchange="updateColorPicker('button_text')" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="config-section">
                    <h3>📝 Typography & Spacing</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Font Family</label>
                            <select name="font_family" class="regular-text">
                                <option value="system" <?php echo ($theme['config']['font_family'] ?? 'system') === 'system' ? 'selected' : ''; ?>>System Default</option>
                                <option value="Arial" <?php echo ($theme['config']['font_family'] ?? '') === 'Arial' ? 'selected' : ''; ?>>Arial</option>
                                <option value="Georgia" <?php echo ($theme['config']['font_family'] ?? '') === 'Georgia' ? 'selected' : ''; ?>>Georgia</option>
                                <option value="Times New Roman" <?php echo ($theme['config']['font_family'] ?? '') === 'Times New Roman' ? 'selected' : ''; ?>>Times New Roman</option>
                                <option value="Verdana" <?php echo ($theme['config']['font_family'] ?? '') === 'Verdana' ? 'selected' : ''; ?>>Verdana</option>
                                <option value="Helvetica" <?php echo ($theme['config']['font_family'] ?? '') === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Base Font Size</label>
                            <input type="text" name="font_size" value="<?php echo htmlspecialchars($theme['config']['font_size'] ?? '16px'); ?>" class="regular-text" placeholder="16px">
                            <p class="description">e.g., 16px, 1rem, 14px</p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Border Radius</label>
                            <input type="text" name="border_radius" value="<?php echo htmlspecialchars($theme['config']['border_radius'] ?? '6px'); ?>" class="regular-text" placeholder="6px">
                            <p class="description">Controls button and card corner roundness</p>
                        </div>
                    </div>
                </div>
                
                <div class="config-preview" style="background: <?php echo htmlspecialchars($theme['config']['background_color'] ?? '#ffffff'); ?>; color: <?php echo htmlspecialchars($theme['config']['text_color'] ?? '#1e293b'); ?>; border: 2px solid <?php echo htmlspecialchars($theme['config']['primary_color'] ?? '#0ea5e9'); ?>;">
                    <h4 style="color: <?php echo htmlspecialchars($theme['config']['primary_color'] ?? '#0ea5e9'); ?>; margin-bottom: 15px;">Live Preview</h4>
                    <p style="margin-bottom: 15px;">This is how your text will look with the current settings.</p>
                    <a href="#" style="color: <?php echo htmlspecialchars($theme['config']['link_color'] ?? '#0ea5e9'); ?>; text-decoration: underline;">This is a link</a>
                    <div style="margin-top: 15px;">
                        <button type="button" style="background: <?php echo htmlspecialchars($theme['config']['button_bg'] ?? '#0ea5e9'); ?>; color: <?php echo htmlspecialchars($theme['config']['button_text'] ?? '#ffffff'); ?>; padding: 10px 20px; border: none; border-radius: <?php echo htmlspecialchars($theme['config']['border_radius'] ?? '6px'); ?>; cursor: pointer;">Sample Button</button>
                    </div>
                </div>
                
                <p class="submit" style="margin-top: 30px;">
                    <input type="submit" name="save_theme_config" class="button button-primary" value="Save Changes">
                    <a href="appearance.php" class="button">Cancel</a>
                </p>
            </form>
            
            <script>
                function updateColorText(colorId) {
                    const picker = document.getElementById(colorId);
                    const textInput = document.getElementById(colorId + '_text');
                    if (textInput) {
                        textInput.value = picker.value;
                        updatePreview();
                    }
                }
                
                function updateColorPicker(colorId) {
                    const textInput = document.getElementById(colorId + '_text');
                    const picker = document.getElementById(colorId);
                    if (textInput && picker && /^#[0-9A-Fa-f]{6}$/.test(textInput.value)) {
                        picker.value = textInput.value;
                        updatePreview();
                    }
                }
                
                function updatePreview() {
                    const preview = document.querySelector('.config-preview');
                    if (preview) {
                        const bgColor = document.getElementById('background_color')?.value || '#ffffff';
                        const textColor = document.getElementById('text_color')?.value || '#1e293b';
                        const primaryColor = document.getElementById('primary_color')?.value || '#0ea5e9';
                        const linkColor = document.getElementById('link_color')?.value || '#0ea5e9';
                        const buttonBg = document.getElementById('button_bg')?.value || '#0ea5e9';
                        const buttonText = document.getElementById('button_text')?.value || '#ffffff';
                        const borderRadius = document.querySelector('input[name="border_radius"]')?.value || '6px';
                        
                        preview.style.background = bgColor;
                        preview.style.color = textColor;
                        preview.style.borderColor = primaryColor;
                        preview.querySelector('h4').style.color = primaryColor;
                        preview.querySelector('a').style.color = linkColor;
                        const button = preview.querySelector('button');
                        if (button) {
                            button.style.background = buttonBg;
                            button.style.color = buttonText;
                            button.style.borderRadius = borderRadius;
                        }
                    }
                }
                
                // Add event listeners for real-time preview
                document.addEventListener('DOMContentLoaded', function() {
                    const colorInputs = document.querySelectorAll('input[type="color"], input[type="text"][id$="_text"], input[name="border_radius"]');
                    colorInputs.forEach(input => {
                        input.addEventListener('input', updatePreview);
                        input.addEventListener('change', updatePreview);
                    });
                });
            </script>
        <?php else: ?>
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0;">🎨 Theme Converter</h3>
                <p style="margin: 10px 0; color: #856404;">
                    Convert any HTML template or WordPress theme into a usable CMS theme automatically!
                </p>
                <a href="theme-converter.php" class="button button-primary">Open Theme Converter →</a>
            </div>
            
            <h2>Installed Themes</h2>
            <p>Customize the appearance of your site. Click "Customize" to edit theme colors, fonts, and styles.</p>
            
            <!-- Theme Upload Form -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0;">📦 Upload Theme</h3>
                <p style="margin: 10px 0; color: #646970;">Upload a theme ZIP file to install it. The theme must contain a style.css file with theme information.</p>
                <form method="post" enctype="multipart/form-data" style="margin-top: 15px;">
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <input type="file" name="theme_zip" accept=".zip" required style="padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; width: 100%;">
                            <small style="color: #646970; display: block; margin-top: 5px;">Maximum file size: 50MB. Theme ZIP must contain a style.css file.</small>
                        </div>
                        <button type="submit" name="upload_theme" class="button button-primary">Upload & Install Theme</button>
                    </div>
                </form>
            </div>
            
            <?php if (!empty($uninstalledThemes)): ?>
                <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Available Themes to Install</h3>
                    <p>These themes are available in your themes directory but not yet installed:</p>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-top:15px;">
                        <?php foreach ($uninstalledThemes as $theme): ?>
                            <div style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">
                                <h4 style="margin-top: 0;"><?php echo htmlspecialchars($theme['name']); ?></h4>
                                <p style="color: #646970; font-size: 13px; margin: 5px 0;"><?php echo htmlspecialchars($theme['description']); ?></p>
                                <p style="color: #646970; font-size: 12px; margin: 5px 0;"><strong>Version:</strong> <?php echo htmlspecialchars($theme['version']); ?></p>
                                <form method="post" style="margin-top: 10px;">
                                    <input type="hidden" name="theme_slug" value="<?php echo htmlspecialchars($theme['slug']); ?>">
                                    <input type="submit" name="install_theme" value="Install Theme" class="button button-primary">
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-top:20px;">
                <?php foreach ($themes as $theme): ?>
                    <div class="theme-card <?php echo $theme['is_active'] ? 'active' : ''; ?>">
                        <h3 style="margin-top: 0;"><?php echo htmlspecialchars($theme['name']); ?></h3>
                        <p style="color: #646970; font-size: 13px; margin: 10px 0;"><?php echo htmlspecialchars($theme['description'] ?? ''); ?></p>
                        
                        <?php if ($theme['is_active']): ?>
                            <p style="margin: 15px 0;"><strong style="color:#00a32a;">✓ Active Theme</strong></p>
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <a href="?action=customize&theme_id=<?php echo $theme['id']; ?>" class="button button-primary">Customize</a>
                            </div>
                        <?php else: ?>
                            <form method="post" style="display: inline; width: 100%;">
                                <input type="hidden" name="theme_id" value="<?php echo $theme['id']; ?>">
                                <input type="submit" name="activate_theme" value="Activate" class="button button-primary" style="width: 100%;">
                            </form>
                            <div style="display: flex; gap: 5px; margin-top: 10px;">
                                <a href="?action=customize&theme_id=<?php echo $theme['id']; ?>" class="button" style="flex: 1; text-align: center;">Customize</a>
                                <form method="post" style="display: inline; flex: 1;" onsubmit="return confirm('Are you sure you want to delete this theme? This will remove it from the database but not delete the theme files.');">
                                    <input type="hidden" name="theme_id" value="<?php echo $theme['id']; ?>">
                                    <input type="submit" name="delete_theme" value="Delete" class="button" style="width: 100%; background: #dc3232; color: white; border-color: #dc3232;">
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
