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
$baseUrl = app_url();
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
        :root {
            --converter-bg: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
            --card-bg: rgba(255,255,255,0.96);
            --accent: #6366f1;
            --accent-strong: #0ea5e9;
        }
        body.cms-admin{
            background: #f1f5f9;
        }
        .converter-hero {
            background: var(--converter-bg);
            border-radius: 24px;
            padding: 40px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 40px 80px -60px rgba(14,165,233,0.7);
            margin-bottom: 36px;
        }
        .converter-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, rgba(255,255,255,0.28), transparent 45%);
            pointer-events: none;
        }
        .converter-hero h1 {
            margin: 0;
            font-size: 2.4rem;
            font-weight: 700;
        }
        .converter-hero p {
            font-size: 1.05rem;
            max-width: 620px;
            margin: 12px 0 0;
            opacity: 0.92;
        }
        .converter-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        }
        .converter-card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 20px 48px -28px rgba(15,23,42,0.18);
            border: 1px solid rgba(14,165,233,0.08);
            backdrop-filter: blur(12px);
        }
        .converter-card h2 {
            margin-top: 0;
            font-size: 1.35rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .feature-item {
            background: rgba(99,102,241,0.08);
            border-radius: 14px;
            padding: 18px;
            border: 1px solid rgba(99,102,241,0.16);
        }
        .feature-item h3 {
            margin: 0 0 8px;
            font-size: 1rem;
            color: var(--accent);
        }
        .upload-dropzone {
            border: 2px dashed rgba(99,102,241,0.45);
            border-radius: 20px;
            padding: 48px 32px;
            text-align: center;
            background: rgba(255,255,255,0.95);
            transition: border-color 0.3s ease, background 0.3s ease, transform 0.2s ease;
        }
        .upload-dropzone:hover {
            border-color: rgba(14,165,233,0.65);
            background: rgba(224,242,254,0.85);
            transform: translateY(-2px);
        }
        .upload-dropzone.dragover {
            border-color: rgba(14,165,233,0.75);
            background: rgba(224,242,254,0.95);
        }
        .upload-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-top: 16px;
        }
        .summary-card {
            background: rgba(14,165,233,0.08);
            border-left: 4px solid var(--accent);
            padding: 18px;
            border-radius: 14px;
            margin-top: 18px;
        }
        .conversion-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .conversion-table th,
        .conversion-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(148,163,184,0.25);
            text-align: left;
            font-size: 0.95rem;
        }
        .conversion-steps ol {
            padding-left: 22px;
            line-height: 1.7;
            color: #1f2937;
        }
        .conversion-steps li strong {
            color: var(--accent);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(15,118,110,0.1);
            color: #0f766e;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge.info {
            background: rgba(99,102,241,0.12);
            color: var(--accent);
        }
        .badge.success {
            background: rgba(16,185,129,0.12);
            color: #059669;
        }
        .badge.warning {
            background: rgba(245,158,11,0.12);
            color: #b45309;
        }
        .conversion-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            padding: 24px;
        }
        .conversion-overlay.is-visible {
            display: flex;
        }
        .conversion-overlay__card {
            background: white;
            border-radius: 18px;
            padding: 32px 36px;
            width: min(420px, 100%);
            text-align: center;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.25);
        }
        .conversion-overlay__spinner {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: 6px solid rgba(14, 165, 233, 0.25);
            border-top-color: #0ea5e9;
            margin: 0 auto 18px;
            animation: converter-spin 1s linear infinite;
        }
        @keyframes converter-spin {
            to { transform: rotate(360deg); }
        }
        body.conversion-overlay-active {
            overflow: hidden;
        }
    </style>
</head>
<body class="cms-admin">
    <main class="wrap" style="padding-bottom:40px;">
        <div class="converter-hero">
            <h1>🎨 Theme Converter Toolkit</h1>
            <p>Automatically transform HTML templates or WordPress themes into ABBIS-ready CMS themes. Preserve design, responsive layout, and functionality with one upload.</p>
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:24px;">
                <span class="badge info">HTML → CMS</span>
                <span class="badge success">WordPress → CMS</span>
                <span class="badge warning">Supports Bootstrap / Tailwind</span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>

        <div class="converter-grid">
            <section class="converter-card" aria-labelledby="converter-upload">
                <h2 id="converter-upload">⬆️ Upload & Convert</h2>
                <p style="color:#475569; margin-bottom:18px;">Upload a ZIP, TAR, or GZ archive. We’ll analyse the structure, detect whether it’s WordPress or static HTML, and produce a ready-to-activate CMS theme.</p>
                <div class="upload-dropzone" id="uploadArea">
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <svg width="72" height="72" style="margin-bottom: 18px; opacity: 0.35;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <h3 style="margin: 6px 0 12px; font-size: 1.3rem;">Drop your theme package here</h3>
                        <p style="color:#475569; margin:0 0 18px;">or click the button below to browse.</p>
                        <div class="upload-actions">
                            <label for="themeFile" class="button button-primary" style="cursor:pointer; padding:12px 28px; border-radius:999px;">Choose Theme File</label>
                            <input type="file" name="theme_file" id="themeFile" accept=".zip,.tar,.gz" required style="display:none;">
                            <div id="fileName" style="color:#0f172a; font-weight:600; min-height:24px;"></div>
                            <small style="color:#64748b;">Max size 100 MB • HTML templates, WordPress themes, Bootstrap, Tailwind</small>
                            <button type="submit" name="convert_theme" class="button button-primary button-large" id="convertBtn" disabled style="padding:12px 32px; border-radius:999px; margin-top:6px;">🔁 Convert Theme</button>
                        </div>
                    </form>
                </div>
                <div class="summary-card" role="status">
                    <strong>Status Tips:</strong>
                    <ul style="margin:12px 0 0 18px; color:#475569;">
                        <li>Conversion handles assets, menus, and template tags automatically.</li>
                        <li>WordPress functions like <code>the_title()</code> map to CMS-friendly equivalents.</li>
                        <li>Converted themes appear instantly under <strong>Appearance → Themes</strong>.</li>
                    </ul>
                </div>
            </section>

            <section class="converter-card" aria-labelledby="converter-overview">
                <h2 id="converter-overview">✨ What Gets Converted</h2>
                <div class="feature-list" style="margin-bottom: 20px;">
                    <div class="feature-item">
                        <h3>📦 HTML Templates</h3>
                        <p>Bootstrap, Tailwind, and custom CSS templates become CMS themes, preserving page structure.</p>
                    </div>
                    <div class="feature-item">
                        <h3>🔄 WordPress Themes</h3>
                        <p>Automatically translates WordPress hooks, template tags, and menus into ABBIS equivalents.</p>
                    </div>
                    <div class="feature-item">
                        <h3>🎯 Function Mapping</h3>
                        <p>Smart replacements for <code>the_content()</code>, <code>wp_nav_menu()</code>, <code>bloginfo()</code>, and more.</p>
                    </div>
                    <div class="feature-item">
                        <h3>📱 Responsive Design</h3>
                        <p>Keeps original CSS/JS assets, ensuring responsive behavior across devices.</p>
                    </div>
                    <div class="feature-item">
                        <h3>🎨 Style Preservation</h3>
                        <p>Maintains typography, colors, animations, and layout aesthetics.</p>
                    </div>
                    <div class="feature-item">
                        <h3>🔗 Navigation Mapping</h3>
                        <p>Converts theme menus into CMS menu structures without breaking navigation.</p>
                    </div>
                </div>
                <div class="conversion-steps">
                    <h3>🔧 Workflow</h3>
                    <ol>
                        <li><strong>Upload</strong> a theme package (ZIP/TAR/GZ)</li>
                        <li><strong>Analyse</strong> file structure and detect HTML vs WordPress</li>
                        <li><strong>Convert</strong> template tags, menu calls, asset paths, and theme metadata</li>
                        <li><strong>Install</strong> the converted theme and make it available in the CMS</li>
                    </ol>
                </div>
            </section>
        </div>

        <?php if ($conversionResult): ?>
            <section class="converter-card" style="margin-top:24px;" aria-labelledby="converter-result">
                <h2 id="converter-result">✅ Conversion Report</h2>
                <p style="color:#475569;">Your theme was converted successfully. Review the summary below and head to Appearance → Themes to activate it.</p>
                <table class="conversion-table">
                    <tr>
                        <th>Theme Name</th>
                        <td><?php echo htmlspecialchars($conversionResult['theme_name'] ?? 'Unknown'); ?></td>
                    </tr>
                    <tr>
                        <th>Theme Slug</th>
                        <td><code><?php echo htmlspecialchars($conversionResult['theme_slug'] ?? ''); ?></code></td>
                    </tr>
                    <tr>
                        <th>Detected Type</th>
                        <td>
                            <span class="badge <?php echo ($conversionResult['is_wordpress'] ?? false) ? 'info' : 'success'; ?>">
                                <?php echo ($conversionResult['is_wordpress'] ?? false) ? 'WordPress Theme' : 'HTML Template'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Files Converted</th>
                        <td><?php echo (int) ($conversionResult['files_converted'] ?? 0); ?></td>
                    </tr>
                </table>
                <?php if (!empty($conversionResult['conversions'])): ?>
                    <div class="summary-card" style="margin-top: 20px;">
                        <strong>Transformations:</strong>
                        <ul style="margin: 10px 0 0 18px;">
                            <?php foreach ($conversionResult['conversions'] as $conv): ?>
                                <li><?php echo htmlspecialchars($conv); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;">
                    <a href="appearance.php" class="button button-primary">Manage Themes</a>
                    <a href="<?php echo $baseUrl; ?>/cms/" target="_blank" class="button">Preview Site</a>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="conversion-overlay" id="conversionOverlay" role="alert" aria-live="assertive" aria-hidden="true">
        <div class="conversion-overlay__card">
            <div class="conversion-overlay__spinner" aria-hidden="true"></div>
            <h2 style="margin:0 0 8px; font-size:1.35rem; color:#0f172a;">Converting theme…</h2>
            <p style="margin:0; color:#475569;">We’re uploading your package and transforming template files. Keep this tab open; you’ll see the result in a moment.</p>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('themeFile');
        const convertBtn = document.getElementById('convertBtn');
        const fileName = document.getElementById('fileName');
        const form = document.getElementById('uploadForm');
        const overlay = document.getElementById('conversionOverlay');

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
            if (fileInput.files.length) {
                fileName.textContent = fileInput.files[0].name;
                convertBtn.disabled = false;
            }
        });

        form.addEventListener('submit', (e) => {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a theme file to convert.');
                return;
            }
            convertBtn.disabled = true;
            convertBtn.textContent = '⏳ Converting...';
            if (overlay) {
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('conversion-overlay-active');
            }
        });
    </script>
</body>
</html>
