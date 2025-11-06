<?php
/**
 * CMS Admin - Pages Management
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_page'])) {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        
        // Handle GrapesJS content if submitted
        if (isset($_POST['grapesjs-content']) && !empty($_POST['grapesjs-content'])) {
            $content = $_POST['grapesjs-content'];
        }
        
        $status = $_POST['status'] ?? 'draft';
        $seo_title = trim($_POST['seo_title'] ?? '');
        $seo_description = trim($_POST['seo_description'] ?? '');
        
        // Auto-generate slug from title if not provided
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        }
        
        if ($title && $slug) {
            // Check if this is homepage
            $isHomepage = ($slug === 'home' || $id === 'home');
            
            if ($isHomepage) {
                // Check if homepage already exists
                $checkStmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug='home' LIMIT 1");
                $checkStmt->execute();
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Update existing homepage
                    $stmt = $pdo->prepare("UPDATE cms_pages SET title=?, content=?, status=?, seo_title=?, seo_description=? WHERE slug='home'");
                    $stmt->execute([$title, $content, $status, $seo_title, $seo_description]);
                    $id = $existing['id'];
                    $message = 'Homepage updated successfully';
                } else {
                    // Create new homepage
                    $stmt = $pdo->prepare("INSERT INTO cms_pages (title, slug, content, status, seo_title, seo_description, created_by) VALUES (?, 'home', ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $content, $status, $seo_title, $seo_description, $_SESSION['cms_user_id'] ?? 1]);
                    $id = $pdo->lastInsertId();
                    $message = 'Homepage created successfully';
                }
            } elseif ($id) {
                // Update existing page
                $stmt = $pdo->prepare("UPDATE cms_pages SET title=?, slug=?, content=?, status=?, seo_title=?, seo_description=? WHERE id=?");
                $stmt->execute([$title, $slug, $content, $status, $seo_title, $seo_description, $id]);
                $message = 'Page updated successfully';
            } else {
                // Check if page with slug exists
                $checkStmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug=? LIMIT 1");
                $checkStmt->execute([$slug]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Update existing
                    $stmt = $pdo->prepare("UPDATE cms_pages SET title=?, content=?, status=?, seo_title=?, seo_description=? WHERE id=?");
                    $stmt->execute([$title, $content, $status, $seo_title, $seo_description, $existing['id']]);
                    $id = $existing['id'];
                    $message = 'Page updated successfully';
                } else {
                    // Create new
                    $stmt = $pdo->prepare("INSERT INTO cms_pages (title, slug, content, status, seo_title, seo_description, created_by) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$title, $slug, $content, $status, $seo_title, $seo_description, $_SESSION['cms_user_id'] ?? 1]);
                    $message = 'Page created successfully';
                    $id = $pdo->lastInsertId();
                }
            }
        }
    }
    if (isset($_POST['delete_page'])) {
        $pdo->prepare("DELETE FROM cms_pages WHERE id=?")->execute([$id]);
        header('Location: pages.php');
        exit;
    }
}

$page = null;
if ($id && $action === 'edit') {
    if ($id === 'home') {
        // Load homepage
        $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE slug='home' LIMIT 1");
        $stmt->execute();
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If homepage doesn't exist, initialize with default content that matches what's displayed
        if (!$page) {
            $defaultContent = '
<section style="padding: 4rem 0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 2rem;">
        <h2 class="section-title" style="text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: #1e293b;">Our Comprehensive Services</h2>
        <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🕳️</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Borehole Drilling</h3>
                <p style="color: #64748b; line-height: 1.6;">Professional borehole drilling and construction services across Ghana. We use state-of-the-art equipment and techniques.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Geophysical Survey</h3>
                <p style="color: #64748b; line-height: 1.6;">Expert site selection and water source identification using advanced geophysical methods to ensure optimal well placement.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">⚙️</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Pump Installation</h3>
                <p style="color: #64748b; line-height: 1.6;">Complete pump installation and system automation with smart controls for efficient water delivery.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">💧</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Water Treatment</h3>
                <p style="color: #64748b; line-height: 1.6;">Filtration, reverse osmosis, UV purification, and complete water treatment solutions for safe, clean water.</p>
                <a href="/abbis3.2/cms/shop" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Shop Now →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Maintenance & Repair</h3>
                <p style="color: #64748b; line-height: 1.6;">Regular maintenance, rehabilitation, and repair services to keep your water systems running efficiently.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Request Service →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🏪</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Equipment Sales</h3>
                <p style="color: #64748b; line-height: 1.6;">Quality pumps, tanks, pipes, and complete water systems from trusted manufacturers. Shop our online store.</p>
                <a href="/abbis3.2/cms/shop" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Shop Now →</a>
            </div>
        </div>
    </div>
</section>
';
            $page = [
                'title' => 'Homepage',
                'slug' => 'home',
                'content' => $defaultContent,
                'status' => 'published',
                'seo_title' => '',
                'seo_description' => ''
            ];
        } elseif (empty($page['content'])) {
            // If homepage exists but content is empty, load default content
            $defaultContent = '
<section style="padding: 4rem 0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 2rem;">
        <h2 class="section-title" style="text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: #1e293b;">Our Comprehensive Services</h2>
        <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🕳️</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Borehole Drilling</h3>
                <p style="color: #64748b; line-height: 1.6;">Professional borehole drilling and construction services across Ghana. We use state-of-the-art equipment and techniques.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Geophysical Survey</h3>
                <p style="color: #64748b; line-height: 1.6;">Expert site selection and water source identification using advanced geophysical methods to ensure optimal well placement.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">⚙️</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Pump Installation</h3>
                <p style="color: #64748b; line-height: 1.6;">Complete pump installation and system automation with smart controls for efficient water delivery.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">💧</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Water Treatment</h3>
                <p style="color: #64748b; line-height: 1.6;">Filtration, reverse osmosis, UV purification, and complete water treatment solutions for safe, clean water.</p>
                <a href="/abbis3.2/cms/shop" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Shop Now →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Maintenance & Repair</h3>
                <p style="color: #64748b; line-height: 1.6;">Regular maintenance, rehabilitation, and repair services to keep your water systems running efficiently.</p>
                <a href="/abbis3.2/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Request Service →</a>
            </div>
            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🏪</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Equipment Sales</h3>
                <p style="color: #64748b; line-height: 1.6;">Quality pumps, tanks, pipes, and complete water systems from trusted manufacturers. Shop our online store.</p>
                <a href="/abbis3.2/cms/shop" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Shop Now →</a>
            </div>
        </div>
    </div>
</section>
';
            $page['content'] = $defaultContent;
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE id=?");
        $stmt->execute([$id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} elseif ($action === 'add' && isset($_GET['slug']) && $_GET['slug'] === 'home') {
    // Creating homepage
    $page = [
        'title' => 'Homepage',
        'slug' => 'home',
        'content' => '',
        'status' => 'published',
        'seo_title' => '',
        'seo_description' => ''
    ];
    $id = 'home';
}

// Get all pages, including homepage
$pages = $pdo->query("SELECT * FROM cms_pages ORDER BY 
    CASE WHEN slug='home' THEN 0 ELSE 1 END, 
    created_at DESC")->fetchAll();

// Check if homepage exists
$homepageExists = false;
foreach ($pages as $p) {
    if ($p['slug'] === 'home') {
        $homepageExists = true;
        break;
    }
}

// If homepage doesn't exist, add a virtual entry for display
if (!$homepageExists) {
    array_unshift($pages, [
        'id' => 'home',
        'title' => 'Homepage',
        'slug' => 'home',
        'status' => 'published',
        'created_at' => date('Y-m-d H:i:s'),
        'is_homepage' => true
    ]);
}

// Get company name
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
    <title>Pages - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php include 'header.php'; ?>
    <!-- CKEditor 5 - Free Open Source Rich Text Editor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
    
    <!-- GrapesJS - Visual Page Builder (Elementor-like) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.5/dist/css/grapes.min.css">
    <script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.5"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-preset-webpage@1.0.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.1/dist/index.min.js"></script>
    
    <style>
        #gjs-editor {
            min-height: 600px;
        }
        .gjs-editor {
            border: 1px solid #c3c4c7;
        }
        .gjs-cv-canvas {
            background: white;
        }
    </style>
    
    <script>
        let editorInstance = null;
        let grapesEditor = null;
        let currentMode = 'ckeditor'; // 'ckeditor' or 'grapesjs'
        const initialContent = <?php echo json_encode($page['content'] ?? ''); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.querySelector('textarea[name="content"]');
            const toggleBtn = document.getElementById('editor-toggle');
            const ckeditorContainer = document.getElementById('ckeditor-container');
            const grapesjsContainer = document.getElementById('grapesjs-container');
            const grapesjsTextarea = document.getElementById('grapesjs-content');
            const modeText = document.getElementById('editor-mode-text');
            
            // Initialize CKEditor
            if (contentTextarea) {
                ClassicEditor
                    .create(contentTextarea, {
                        toolbar: {
                            items: [
                                'heading', '|',
                                'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                                'outdent', 'indent', '|',
                                'blockQuote', 'insertTable', '|',
                                'undo', 'redo', '|',
                                'sourceEditing'
                            ]
                        },
                        heading: {
                            options: [
                                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                                { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                            ]
                        }
                    })
                    .then(editor => {
                        editorInstance = editor;
                        // Load initial content if switching from GrapesJS
                        if (initialContent && !initialContent.includes('gjs-')) {
                            editor.setData(initialContent);
                        }
                    })
                    .catch(error => {
                        console.error('CKEditor initialization error:', error);
                    });
            }
            
            // Initialize GrapesJS
            function initGrapesJS() {
                if (grapesEditor) return; // Already initialized
                
                const saveBtn = document.getElementById('gjs-save-btn');
                
                grapesEditor = grapesjs.init({
                    container: '#gjs-editor',
                    plugins: ['gjs-preset-webpage'],
                    pluginsOpts: {
                        'gjs-preset-webpage': {
                            modalImportTitle: 'Import Template',
                            filestackOpts: null,
                            blocksBasicOpts: { flexGrid: true }
                        }
                    },
                    storageManager: {
                        type: 'local',
                        autosave: false,
                        autoload: false
                    },
                    deviceManager: {
                        devices: [
                            { name: 'Desktop', width: '' },
                            { name: 'Tablet', width: '768px', widthMedia: '992px' },
                            { name: 'Mobile', width: '320px', widthMedia: '768px' }
                        ]
                    },
                    height: '600px',
                    width: '100%',
                    canvas: {
                        styles: []
                    },
                    traitManager: {
                        label: 'Settings'
                    }
                });
                
                // Load content if exists
                if (initialContent && initialContent.includes('gjs-')) {
                    grapesEditor.setComponents(initialContent);
                } else if (initialContent) {
                    // Convert HTML to GrapesJS components
                    grapesEditor.setComponents(initialContent);
                }
                
                // Show save button when editor is ready
                if (saveBtn) {
                    saveBtn.style.display = 'inline-block';
                }
                
                // Update hidden textarea on content change
                let updateTimeout;
                grapesEditor.on('update', () => {
                    clearTimeout(updateTimeout);
                    updateTimeout = setTimeout(() => {
                        const html = grapesEditor.getHtml();
                        const css = grapesEditor.getCss();
                        const grapesContent = html + '<style>' + css + '</style>';
                        grapesjsTextarea.value = grapesContent;
                        if (contentTextarea) {
                            contentTextarea.value = grapesContent;
                        }
                        // Show save indicator
                        if (saveBtn) {
                            saveBtn.textContent = '💾 Changes Saved';
                            saveBtn.style.background = '#00a32a';
                            setTimeout(() => {
                                saveBtn.textContent = '💾 Save & Continue Editing';
                                saveBtn.style.background = '';
                            }, 2000);
                        }
                    }, 500);
                });
                
                // Save button click handler
                if (saveBtn) {
                    saveBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const html = grapesEditor.getHtml();
                        const css = grapesEditor.getCss();
                        const grapesContent = html + '<style>' + css + '</style>';
                        grapesjsTextarea.value = grapesContent;
                        if (contentTextarea) {
                            contentTextarea.value = grapesContent;
                        }
                        saveBtn.textContent = '✅ Saved!';
                        saveBtn.style.background = '#00a32a';
                        setTimeout(() => {
                            saveBtn.textContent = '💾 Save & Continue Editing';
                            saveBtn.style.background = '';
                        }, 2000);
                    });
                }
            }
            
            // Toggle between editors
            toggleBtn.addEventListener('click', function() {
                if (currentMode === 'ckeditor') {
                    // Switch to GrapesJS
                    currentMode = 'grapesjs';
                    modeText.textContent = 'Switch to Rich Text Editor';
                    ckeditorContainer.style.display = 'none';
                    grapesjsContainer.style.display = 'block';
                    
                    // Save CKEditor content
                    if (editorInstance) {
                        const ckeditorContent = editorInstance.getData();
                        if (contentTextarea) {
                            contentTextarea.value = ckeditorContent;
                        }
                    }
                    
                    // Initialize GrapesJS if not already done
                    if (!grapesEditor) {
                        initGrapesJS();
                    } else {
                        // Load content from CKEditor
                        if (contentTextarea && contentTextarea.value) {
                            grapesEditor.setComponents(contentTextarea.value);
                        }
                    }
                } else {
                    // Switch to CKEditor
                    currentMode = 'ckeditor';
                    modeText.textContent = 'Switch to Visual Builder';
                    ckeditorContainer.style.display = 'block';
                    grapesjsContainer.style.display = 'none';
                    
                    // Save GrapesJS content
                    if (grapesEditor) {
                        const html = grapesEditor.getHtml();
                        const css = grapesEditor.getCss();
                        const grapesContent = html + '<style>' + css + '</style>';
                        grapesjsTextarea.value = grapesContent;
                        if (contentTextarea) {
                            contentTextarea.value = grapesContent;
                        }
                        if (editorInstance) {
                            editorInstance.setData(grapesContent);
                        }
                    }
                }
            });
            
            // Before form submit, sync content
            const form = document.querySelector('form.post-form');
            if (form) {
                form.addEventListener('submit', function() {
                    if (currentMode === 'grapesjs' && grapesEditor) {
                        const html = grapesEditor.getHtml();
                        const css = grapesEditor.getCss();
                        grapesjsTextarea.value = html + '<style>' + css + '</style>';
                        if (contentTextarea) {
                            contentTextarea.value = html + '<style>' + css + '</style>';
                        }
                    } else if (currentMode === 'ckeditor' && editorInstance) {
                        const content = editorInstance.getData();
                        if (contentTextarea) {
                            contentTextarea.value = content;
                        }
                    }
                });
            }
        });
    </script>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1><?php echo $action === 'edit' ? 'Edit Page' : ($action === 'add' ? 'Add New Page' : 'Pages'); ?></h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <form method="post" class="post-form">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($page['title'] ?? ''); ?>" required class="large-text">
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>" required <?php echo ($page['slug'] ?? '') === 'home' ? 'readonly' : ''; ?>>
                    <p class="description">
                        <?php if (($page['slug'] ?? '') === 'home'): ?>
                            <strong>Homepage:</strong> This is your site's homepage. The slug is automatically set to "home".
                        <?php else: ?>
                            The URL-friendly version of the name. Leave empty to auto-generate from title.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <div style="margin-bottom: 10px;">
                        <button type="button" id="editor-toggle" class="button" style="margin-right: 10px;">
                            <span id="editor-mode-text">Switch to Visual Builder</span>
                        </button>
                        <span class="description" style="display: inline-block; margin-left: 10px;">
                            Choose between Rich Text Editor or Visual Builder (Elementor-like)
                        </span>
                    </div>
                    <div id="ckeditor-container" style="display: block;">
                        <textarea name="content" id="content-editor" rows="20" class="large-text"><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
                        <p class="description">Use the rich text editor above to format your content. You can add images, links, lists, and more.</p>
                    </div>
                    <div id="grapesjs-container" style="display: none; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; position: relative;">
                        <div style="background: #f6f7f7; padding: 10px; border-bottom: 1px solid #c3c4c7; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600; color: #1e293b;">Visual Builder</span>
                            <button type="button" id="gjs-save-btn" class="button button-primary" style="display: none; margin: 0;">
                                💾 Save & Continue Editing
                            </button>
                        </div>
                        <div id="gjs-editor"></div>
                        <textarea name="grapesjs-content" id="grapesjs-content" style="display: none;"></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="draft" <?php echo ($page['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($page['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>SEO Title</label>
                    <input type="text" name="seo_title" value="<?php echo htmlspecialchars($page['seo_title'] ?? ''); ?>" class="large-text">
                </div>
                <div class="form-group">
                    <label>SEO Description</label>
                    <textarea name="seo_description" rows="3" class="large-text"><?php echo htmlspecialchars($page['seo_description'] ?? ''); ?></textarea>
                </div>
                <p class="submit">
                    <input type="submit" name="save_page" class="button button-primary" value="Save Page">
                    <?php if ($id && $id !== 'home' && !isset($page['is_homepage'])): ?>
                        <input type="submit" name="delete_page" class="button button-delete" value="Delete" onclick="return confirm('Are you sure?');">
                    <?php endif; ?>
                    <a href="pages.php" class="button">Cancel</a>
                </p>
            </form>
        <?php else: ?>
            <div class="notice notice-info" style="margin: 15px 0; padding: 12px;">
                <p><strong>💡 Homepage Editing:</strong> The homepage is listed at the top of this table with a blue "Homepage" badge. Click "Create/Edit Homepage" to edit the main content section. The hero banner (top section) is controlled by Site Title and Tagline in Settings. <a href="?action=edit&id=home" style="font-weight: 600;">Edit Homepage Now →</a></p>
            </div>
            <a href="?action=add" class="page-title-action">Add New</a>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $p): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                                <?php if (($p['slug'] ?? '') === 'home' || isset($p['is_homepage'])): ?>
                                    <span style="background: #0ea5e9; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 8px;">Homepage</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['slug']); ?></td>
                            <td><span class="status-<?php echo $p['status'] ?? 'published'; ?>"><?php echo ucfirst($p['status'] ?? 'Published'); ?></span></td>
                            <td><?php echo isset($p['created_at']) ? date('Y/m/d', strtotime($p['created_at'])) : '-'; ?></td>
                            <td>
                                <?php if (isset($p['is_homepage'])): ?>
                                    <a href="?action=edit&id=home">Create/Edit Homepage</a> |
                                    <a href="<?php echo $baseUrl; ?>/" target="_blank">View</a>
                                <?php else: ?>
                                    <a href="?action=edit&id=<?php echo $p['id']; ?>">Edit</a> |
                                    <a href="<?php echo $baseUrl; ?>/cms/<?php echo htmlspecialchars($p['slug']); ?>" target="_blank">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

