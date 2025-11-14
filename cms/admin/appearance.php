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
            // Helper to clean temp directories quietly
            $cleanupTempDirectory = static function (string $directory): void {
                if (!is_dir($directory)) {
                    return;
                }
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileInfo) {
                    $path = $fileInfo->getRealPath();
                    if ($fileInfo->isDir()) {
                        @rmdir($path);
                    } else {
                        @unlink($path);
                    }
                }
                @rmdir($directory);
            };

            // Create temp directory for extraction
            $tempDir = sys_get_temp_dir() . '/theme_upload_' . uniqid('', true);
            if (!is_dir($tempDir) && !@mkdir($tempDir, 0755, true)) {
                $error = 'Unable to prepare a temporary directory for the uploaded theme. Please check server permissions.';
            } else {
                // Extract ZIP
                $zip = new ZipArchive();
                if ($zip->open($zipFile['tmp_name']) === true) {
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
                            $themesRoot = dirname(__DIR__) . '/themes';

                            if (!is_dir($themesRoot)) {
                                if (!@mkdir($themesRoot, 0755, true)) {
                                    $error = 'Themes directory is missing and could not be created. Please ensure the `cms/themes` folder exists and is writable.';
                                }
                            }

                            if (empty($error) && (!is_dir($themesRoot) || !is_writable($themesRoot))) {
                                $error = 'The themes directory is not writable. Update permissions so new themes can be installed.';
                            }

                            if (empty($error)) {
                                $themesDir = $themesRoot . '/' . $themeSlug;
                                if (!is_dir($themesDir) && !@mkdir($themesDir, 0755, true)) {
                                    $error = 'Failed to create the destination folder for the new theme. Please verify file permissions.';
                                }
                            }

                            if (empty($error)) {
                                // Copy all files
                                $iterator = new RecursiveIteratorIterator(
                                    new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
                                    RecursiveIteratorIterator::SELF_FIRST
                                );

                                foreach ($iterator as $item) {
                                    $subPath = $iterator->getSubPathName();
                                    $destPath = $themesDir . DIRECTORY_SEPARATOR . $subPath;

                                    if ($item->isDir()) {
                                        if (!is_dir($destPath) && !@mkdir($destPath, 0755, true)) {
                                            $error = 'Failed to create folder `' . $subPath . '` inside the themes directory. Please review permissions.';
                                            break;
                                        }
                                        continue;
                                    }

                                    $destDir = dirname($destPath);
                                    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
                                        $error = 'Failed to create folder `' . $subPath . '` inside the themes directory. Please review permissions.';
                                        break;
                                    }

                                    if (!@copy($item->getPathname(), $destPath)) {
                                        $error = 'Failed to copy `' . $item->getFilename() . '` into the themes directory. Ensure it is writable.';
                                        break;
                                    }
                                }
                            }

                            if (empty($error)) {
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
                            } else {
                                // Clean up partially copied destination on failure
                                if (!empty($themesDir) && is_dir($themesDir)) {
                                    $cleanupTempDirectory($themesDir);
                                }
                            }
                        }
                    } else {
                        $error = 'Invalid theme structure. Theme must contain a style.css file.';
                    }
                    
                    // Clean up temp directory
                    $cleanupTempDirectory($tempDir);
                } else {
                    $error = $error ?: 'Failed to extract ZIP file.';
                    $cleanupTempDirectory($tempDir);
                }
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
$baseUrl = app_url();
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
        /* Enhanced Theme Cards */
        .theme-card { 
            background: white; 
            border: 2px solid #c3c4c7; 
            padding: 0; 
            border-radius: 12px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative;
        }
        .theme-card:hover { 
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: var(--admin-primary, #2563eb);
        }
        .theme-card.active { 
            border-color: var(--admin-primary, #2563eb); 
            border-width: 3px;
            box-shadow: 0 4px 16px var(--admin-primary-lighter, rgba(37, 99, 235, 0.2));
        }
        .theme-card.active::before {
            content: '✓';
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--admin-primary, #2563eb);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .theme-preview {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        .theme-preview::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect fill="%23ffffff" opacity="0.1" width="400" height="300"/><path d="M0 150 L400 150" stroke="%23ffffff" stroke-width="2" opacity="0.2"/><path d="M200 0 L200 300" stroke="%23ffffff" stroke-width="2" opacity="0.2"/></svg>');
            background-size: cover;
        }
        .theme-info {
            padding: 20px;
        }
        .theme-info h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 700;
            color: #1d2327;
        }
        .theme-info p {
            color: #646970;
            font-size: 13px;
            margin: 8px 0;
            line-height: 1.5;
        }
        .theme-meta {
            display: flex;
            gap: 12px;
            margin: 12px 0;
            font-size: 12px;
            color: #646970;
        }
        .theme-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .theme-actions {
            padding: 0 20px 20px 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .theme-actions .button {
            flex: 1;
            min-width: 120px;
        }
        
        /* Enhanced Color Picker */
        .color-picker { 
            width: 100%; 
            height: 50px; 
            border: 2px solid #c3c4c7; 
            border-radius: 8px; 
            cursor: pointer;
            transition: all 0.2s;
        }
        .color-picker:hover {
            border-color: var(--admin-primary, #2563eb);
            transform: scale(1.05);
        }
        .config-preview { 
            padding: 30px; 
            background: #f6f7f7; 
            border-radius: 12px; 
            margin-top: 30px;
            border: 2px solid #c3c4c7;
            transition: all 0.3s;
        }
        .config-section { 
            margin-bottom: 40px;
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #c3c4c7;
        }
        .config-section h3 { 
            margin: 0 0 20px 0; 
            padding-bottom: 15px; 
            border-bottom: 3px solid var(--admin-primary, #2563eb);
            font-size: 20px;
            font-weight: 700;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 24px; 
            margin-bottom: 20px; 
        }
        .color-input-group { 
            display: flex; 
            gap: 12px; 
            align-items: center;
            background: #f6f7f7;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #c3c4c7;
        }
        .color-input-group input[type="color"] { 
            width: 70px; 
            height: 50px; 
            border: 2px solid #c3c4c7; 
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .color-input-group input[type="color"]:hover {
            border-color: var(--admin-primary, #2563eb);
            transform: scale(1.1);
        }
        .color-input-group input[type="text"] { 
            flex: 1;
            padding: 12px;
            border: 2px solid #c3c4c7;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 600;
        }
        .color-input-group input[type="text"]:focus {
            outline: none;
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 0 0 3px var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
        }
        
        /* Search and Filter */
        .theme-search-bar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .theme-search-bar input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #c3c4c7;
            border-radius: 8px;
            font-size: 14px;
        }
        .theme-search-bar input:focus {
            outline: none;
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 0 0 3px var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
        }
        
        /* Enhanced Upload Section */
        .upload-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        .upload-section h3 {
            color: white;
            margin: 0 0 12px 0;
        }
        .upload-section p {
            color: rgba(255, 255, 255, 0.9);
            margin: 8px 0;
        }
        .upload-section input[type="file"] {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: none;
        }
        
        /* Responsive Grid */
        .themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        @media (max-width: 768px) {
            .themes-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Live Preview Enhancements */
        .live-preview-container {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div class="admin-page-header">
            <h1>🎨 Appearance</h1>
            <p>Customize your website's look and feel with themes, colors, and styling options.</p>
        </div>
        
        <div class="nav-tab-wrapper" style="margin-bottom: 24px; border-bottom: 2px solid #c3c4c7; background: white; padding: 0 20px; border-radius: 12px 12px 0 0;">
            <a href="appearance.php" class="nav-tab <?php echo $action === 'list' || !$action ? 'nav-tab-active' : ''; ?>">Themes</a>
            <a href="menus.php" class="nav-tab">Menus</a>
            <a href="widgets.php" class="nav-tab">Widgets</a>
        </div>
        
        <?php if ($message): ?>
            <div class="admin-notice admin-notice-success">
                <span class="admin-notice-icon">✓</span>
                <div class="admin-notice-content">
                    <strong>Success!</strong>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="admin-notice admin-notice-error">
                <span class="admin-notice-icon">⚠</span>
                <div class="admin-notice-content">
                    <strong>Error</strong>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'customize' && $theme): ?>
            <div class="admin-card" style="margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                    <a href="appearance.php" class="admin-btn admin-btn-outline">← Back to Themes</a>
                    <div style="flex: 1;">
                        <h2 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; color: #1d2327;">Customize: <?php echo htmlspecialchars($theme['name']); ?></h2>
                        <p style="margin: 0; color: #646970;"><?php echo htmlspecialchars($theme['description'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
            
            <form method="post" class="admin-form">
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
                
                <div style="margin-top: 30px; padding-top: 24px; border-top: 2px solid #c3c4c7; display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="appearance.php" class="admin-btn admin-btn-outline">Cancel</a>
                    <button type="submit" name="save_theme_config" class="admin-btn admin-btn-primary">Save Changes</button>
                </div>
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
            <!-- Theme Converter Banner -->
            <div class="admin-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h3 style="margin: 0 0 8px 0; color: white; font-size: 20px;">🎨 Theme Converter</h3>
                        <p style="margin: 0; color: rgba(255, 255, 255, 0.95);">Convert any HTML template or WordPress theme into a usable CMS theme automatically!</p>
                    </div>
                    <a href="theme-converter.php" class="admin-btn" style="background: white; color: #f5576c; border: none; font-weight: 700;">Open Theme Converter →</a>
                </div>
            </div>
            
            <!-- Theme Upload Section -->
            <div class="upload-section">
                <h3>📦 Upload New Theme</h3>
                <p>Upload a theme ZIP file to install it. The theme must contain a style.css file with theme information.</p>
                <form method="post" enctype="multipart/form-data" style="margin-top: 16px;">
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 250px;">
                            <input type="file" name="theme_zip" accept=".zip" required style="padding: 12px; border-radius: 8px; width: 100%; background: white; border: none; cursor: pointer;">
                            <small style="color: rgba(255, 255, 255, 0.9); display: block; margin-top: 8px;">Maximum file size: 50MB. Theme ZIP must contain a style.css file.</small>
                        </div>
                        <button type="submit" name="upload_theme" class="admin-btn" style="background: white; color: #667eea; border: none; font-weight: 700; padding: 12px 24px;">Upload & Install</button>
                    </div>
                </form>
            </div>
            
            <!-- Search Bar -->
            <div class="theme-search-bar">
                <input type="text" id="theme-search" placeholder="🔍 Search themes by name or description..." onkeyup="filterThemes()">
            </div>
            
            <div class="admin-card-header">
                <h2>Installed Themes</h2>
                <span class="admin-badge admin-badge-active"><?php echo count($themes); ?> Theme<?php echo count($themes) !== 1 ? 's' : ''; ?></span>
            </div>
            <p style="color: #646970; margin-bottom: 24px;">Customize the appearance of your site. Click "Customize" to edit theme colors, fonts, and styles.</p>
            
            <?php if (!empty($uninstalledThemes)): ?>
                <div class="admin-card" style="margin-bottom: 24px; border-left: 4px solid var(--admin-primary, #2563eb);">
                    <div class="admin-card-header">
                        <h2>Available Themes to Install</h2>
                        <span class="admin-badge admin-badge-pending"><?php echo count($uninstalledThemes); ?> Available</span>
                    </div>
                    <p style="color: #646970; margin-bottom: 16px;">These themes are available in your themes directory but not yet installed:</p>
                    <div class="themes-grid">
                        <?php foreach ($uninstalledThemes as $theme): ?>
                            <div class="theme-card">
                                <div class="theme-preview"></div>
                                <div class="theme-info">
                                    <h3><?php echo htmlspecialchars($theme['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($theme['description']); ?></p>
                                    <div class="theme-meta">
                                        <span>📦 v<?php echo htmlspecialchars($theme['version']); ?></span>
                                        <span>📁 <?php echo htmlspecialchars($theme['slug']); ?></span>
                                    </div>
                                </div>
                                <div class="theme-actions">
                                    <form method="post" style="width: 100%;">
                                        <input type="hidden" name="theme_slug" value="<?php echo htmlspecialchars($theme['slug']); ?>">
                                        <button type="submit" name="install_theme" class="admin-btn admin-btn-primary" style="width: 100%;">Install Theme</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="themes-grid" id="themes-container">
                <?php foreach ($themes as $theme): 
                    $config = json_decode($theme['config'] ?? '{}', true);
                    $primaryColor = $config['primary_color'] ?? '#0ea5e9';
                ?>
                    <div class="theme-card <?php echo $theme['is_active'] ? 'active' : ''; ?>" data-theme-name="<?php echo strtolower(htmlspecialchars($theme['name'])); ?>" data-theme-desc="<?php echo strtolower(htmlspecialchars($theme['description'] ?? '')); ?>">
                        <div class="theme-preview" style="background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $config['secondary_color'] ?? '#64748b'; ?> 100%);"></div>
                        <div class="theme-info">
                            <h3><?php echo htmlspecialchars($theme['name']); ?></h3>
                            <p><?php echo htmlspecialchars($theme['description'] ?? 'No description available.'); ?></p>
                            <div class="theme-meta">
                                <?php if (!empty($theme['version'])): ?>
                                    <span>📦 v<?php echo htmlspecialchars($theme['version']); ?></span>
                                <?php endif; ?>
                                <span>🎨 <?php echo htmlspecialchars($theme['slug']); ?></span>
                            </div>
                        </div>
                        <div class="theme-actions">
                            <?php if ($theme['is_active']): ?>
                                <span class="admin-badge admin-badge-active" style="width: 100%; text-align: center; margin-bottom: 12px;">✓ Active Theme</span>
                                <a href="?action=customize&theme_id=<?php echo $theme['id']; ?>" class="admin-btn admin-btn-primary" style="width: 100%;">Customize</a>
                            <?php else: ?>
                                <form method="post" style="width: 100%;">
                                    <input type="hidden" name="theme_id" value="<?php echo $theme['id']; ?>">
                                    <button type="submit" name="activate_theme" class="admin-btn admin-btn-primary" style="width: 100%;">Activate</button>
                                </form>
                                <div style="display: flex; gap: 8px; margin-top: 8px; width: 100%;">
                                    <a href="?action=customize&theme_id=<?php echo $theme['id']; ?>" class="admin-btn admin-btn-outline" style="flex: 1; text-align: center;">Customize</a>
                                    <form method="post" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this theme? This will remove it from the database but not delete the theme files.');">
                                        <input type="hidden" name="theme_id" value="<?php echo $theme['id']; ?>">
                                        <button type="submit" name="delete_theme" class="admin-btn admin-btn-danger" style="width: 100%;">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <script>
                function filterThemes() {
                    const search = document.getElementById('theme-search').value.toLowerCase();
                    const cards = document.querySelectorAll('.theme-card');
                    let visibleCount = 0;
                    
                    cards.forEach(card => {
                        const name = card.getAttribute('data-theme-name') || '';
                        const desc = card.getAttribute('data-theme-desc') || '';
                        const matches = name.includes(search) || desc.includes(search);
                        
                        if (matches || search === '') {
                            card.style.display = 'block';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Show message if no results
                    let noResultsMsg = document.getElementById('no-results');
                    if (visibleCount === 0 && search !== '') {
                        if (!noResultsMsg) {
                            noResultsMsg = document.createElement('div');
                            noResultsMsg.id = 'no-results';
                            noResultsMsg.className = 'admin-empty-state';
                            noResultsMsg.innerHTML = '<div class="admin-empty-state-icon">🔍</div><h3>No themes found</h3><p>Try a different search term.</p>';
                            document.getElementById('themes-container').appendChild(noResultsMsg);
                        }
                    } else if (noResultsMsg) {
                        noResultsMsg.remove();
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
