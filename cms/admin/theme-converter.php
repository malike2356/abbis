<?php
/**
 * Theme Converter - Convert HTML Templates & WordPress Themes to CMS Themes
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
$message = null;
$error = null;
$conversionResult = null;

// Handle theme conversion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_theme']) && isset($_FILES['theme_file'])) {
    $zipFile = $_FILES['theme_file'];
    
    if ($zipFile['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error: ' . $zipFile['error'];
    } elseif ($zipFile['size'] > 100 * 1024 * 1024) { // 100MB limit
        $error = 'Theme file is too large. Maximum size is 100MB.';
    } else {
        $ext = strtolower(pathinfo($zipFile['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['zip', 'tar', 'gz'])) {
            $error = 'Please upload a ZIP, TAR, or GZ file.';
        } else {
            require_once __DIR__ . '/theme-converter-engine.php';
            $converter = new ThemeConverter($pdo);
            $result = $converter->convert($zipFile);
            
            if ($result['success']) {
                $message = $result['message'];
                $conversionResult = $result;
            } else {
                $error = $result['error'];
            }
        }
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
    <title>Theme Converter - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'theme-converter';
    include 'header.php'; 
    ?>
    <style>
        .converter-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .upload-area {
            background: #fff;
            border: 2px dashed #c3c4c7;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #2271b1;
            background: #f0f6fc;
        }
        .upload-area.dragover {
            border-color: #2271b1;
            background: #e7f3ff;
        }
        .conversion-result {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #00a32a;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .conversion-info {
            background: #f6f7f7;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .features-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .feature-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 20px;
            border-radius: 4px;
        }
        .feature-card h3 {
            margin-top: 0;
            color: #2271b1;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin: 5px 0;
        }
        .status-success { background: #00a32a; color: white; }
        .status-warning { background: #f0b849; color: #1e293b; }
        .status-info { background: #2271b1; color: white; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>🎨 Theme Converter</h1>
        <p>Convert any HTML template or WordPress theme into a usable CMS theme automatically.</p>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <div class="converter-container">
            <!-- Features Overview -->
            <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h2>✨ Conversion Features</h2>
                <div class="features-list">
                    <div class="feature-card">
                        <h3>📦 HTML Templates</h3>
                        <p>Convert any HTML template (Bootstrap, Tailwind, custom CSS) into a CMS theme</p>
                    </div>
                    <div class="feature-card">
                        <h3>🔄 WordPress Themes</h3>
                        <p>Automatically convert WordPress themes with function mapping and compatibility</p>
                    </div>
                    <div class="feature-card">
                        <h3>🎯 Function Mapping</h3>
                        <p>Converts WordPress functions to CMS equivalents automatically</p>
                    </div>
                    <div class="feature-card">
                        <h3>📱 Responsive Support</h3>
                        <p>Preserves responsive design and mobile compatibility</p>
                    </div>
                    <div class="feature-card">
                        <h3>🎨 Style Preservation</h3>
                        <p>Maintains original CSS, JavaScript, and assets</p>
                    </div>
                    <div class="feature-card">
                        <h3>🔗 Navigation Mapping</h3>
                        <p>Adapts navigation menus to CMS menu system</p>
                    </div>
                </div>
            </div>
            
            <!-- Upload Form -->
            <div class="upload-area" id="uploadArea">
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <svg width="64" height="64" style="margin-bottom: 20px; opacity: 0.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <h2 style="margin: 10px 0;">Upload Theme/Template</h2>
                    <p style="color: #646970; margin: 10px 0 20px 0;">
                        Drag and drop your theme ZIP file here, or click to browse
                    </p>
                    <input type="file" name="theme_file" id="themeFile" accept=".zip,.tar,.gz" required 
                           style="display: none;" onchange="document.getElementById('fileName').textContent = this.files[0].name">
                    <label for="themeFile" class="button button-primary" style="cursor: pointer; display: inline-block; padding: 10px 20px;">
                        Choose File
                    </label>
                    <div id="fileName" style="margin-top: 10px; color: #646970; font-weight: 600;"></div>
                    <small style="display: block; margin-top: 15px; color: #646970;">
                        Supported formats: ZIP, TAR, GZ<br>
                        Maximum file size: 100MB<br>
                        Works with: HTML templates, WordPress themes, Bootstrap themes, custom CSS themes
                    </small>
                    <div style="margin-top: 20px;">
                        <button type="submit" name="convert_theme" class="button button-primary button-large" id="convertBtn" disabled>
                            🔄 Convert Theme
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Conversion Result -->
            <?php if ($conversionResult): ?>
                <div class="conversion-result">
                    <h2>✅ Conversion Complete!</h2>
                    <div class="conversion-info">
                        <div><strong>Theme Name:</strong> <?php echo htmlspecialchars($conversionResult['theme_name'] ?? 'Unknown'); ?></div>
                        <div><strong>Theme Slug:</strong> <code><?php echo htmlspecialchars($conversionResult['theme_slug'] ?? ''); ?></code></div>
                        <div><strong>Type Detected:</strong> 
                            <span class="status-badge status-<?php echo ($conversionResult['is_wordpress'] ?? false) ? 'info' : 'success'; ?>">
                                <?php echo ($conversionResult['is_wordpress'] ?? false) ? 'WordPress Theme' : 'HTML Template'; ?>
                            </span>
                        </div>
                        <div><strong>Files Converted:</strong> <?php echo $conversionResult['files_converted'] ?? 0; ?> files</div>
                        <?php if (!empty($conversionResult['conversions'])): ?>
                            <div style="margin-top: 15px;">
                                <strong>Conversions Made:</strong>
                                <ul style="margin: 10px 0 0 20px;">
                                    <?php foreach ($conversionResult['conversions'] as $conv): ?>
                                        <li><?php echo htmlspecialchars($conv); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <a href="appearance.php" class="button button-primary">Go to Themes →</a>
                        <a href="<?php echo $baseUrl; ?>/cms/" target="_blank" class="button">Preview Theme →</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- How It Works -->
            <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h2>📖 How It Works</h2>
                <ol style="line-height: 1.8;">
                    <li><strong>Upload</strong> your theme/template ZIP file (HTML template, WordPress theme, or any packaged theme)</li>
                    <li><strong>Analysis</strong> - The converter analyzes the structure and detects if it's a WordPress theme or HTML template</li>
                    <li><strong>Conversion</strong> - Automatically converts:
                        <ul>
                            <li>WordPress functions to CMS equivalents</li>
                            <li>Template tags and variables</li>
                            <li>Navigation menus</li>
                            <li>Asset paths</li>
                            <li>Creates proper theme structure with style.css header</li>
                        </ul>
                    </li>
                    <li><strong>Installation</strong> - Theme is automatically installed and ready to activate</li>
                </ol>
                
                <h3 style="margin-top: 30px;">🔄 WordPress Function Conversions</h3>
                <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-top: 10px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #c3c4c7;">WordPress</th>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #c3c4c7;">CMS Equivalent</th>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>the_title()</code></td>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>&lt;?php echo $page['title']; ?&gt;</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>the_content()</code></td>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>&lt;?php echo $page['content']; ?&gt;</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>wp_nav_menu()</code></td>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>CMS Menu System</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>get_header()</code></td>
                            <td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><code>include header.php</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px;"><code>bloginfo()</code></td>
                            <td style="padding: 8px;"><code>CMS Settings</code></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('themeFile');
        const convertBtn = document.getElementById('convertBtn');
        const fileName = document.getElementById('fileName');
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileName.textContent = files[0].name;
                convertBtn.disabled = false;
            }
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                fileName.textContent = fileInput.files[0].name;
                convertBtn.disabled = false;
            }
        });
        
        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a theme file to convert.');
                return false;
            }
            convertBtn.disabled = true;
            convertBtn.textContent = '⏳ Converting...';
        });
    </script>
</body>
</html>
