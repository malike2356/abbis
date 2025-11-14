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
    if (isset($_GET['action']) && $_GET['action'] === 'clone' && $id) {
        // Clone page
        $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE id=?");
        $stmt->execute([$id]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($original) {
            // Generate new slug
            $newSlug = $original['slug'] . '-copy-' . time();
            $newTitle = $original['title'] . ' (Copy)';
            
            // Ensure slug uniqueness
            $baseSlug = $newSlug;
            $counter = 1;
            while (true) {
                $checkStmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug=? LIMIT 1");
                $checkStmt->execute([$newSlug]);
                if (!$checkStmt->fetch()) {
                    break;
                }
                $newSlug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            // Create clone
            $stmt = $pdo->prepare("INSERT INTO cms_pages (title, slug, content, status, seo_title, seo_description, created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $newTitle,
                $newSlug,
                $original['content'],
                'draft', // Always clone as draft
                $original['seo_title'],
                $original['seo_description'],
                $_SESSION['cms_user_id'] ?? 1
            ]);
            
            $newId = $pdo->lastInsertId();
            header('Location: pages.php?action=edit&id=' . $newId);
            exit;
        }
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
$baseUrl = app_url();
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
        <!-- Page Header -->
        <div class="admin-page-header">
            <h1><?php echo $action === 'edit' ? '✏️ Edit Page' : ($action === 'add' ? '➕ Add New Page' : '📄 Pages Management'); ?></h1>
            <p>
                <?php if ($action === 'edit' || $action === 'add'): ?>
                    Create and edit pages for your website. Use the Rich Text Editor for formatted content or the Visual Builder for drag-and-drop page design.
                <?php else: ?>
                    Manage all your website pages. Create new pages, edit existing ones, and control their visibility and SEO settings.
                <?php endif; ?>
            </p>
            <?php if ($action !== 'edit' && $action !== 'add'): ?>
            <div class="admin-page-actions">
                <a href="?action=add" class="admin-btn admin-btn-primary">
                    <span>➕</span> Add New Page
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="admin-notice admin-notice-success">
                <div class="admin-notice-icon">✅</div>
                <div class="admin-notice-content">
                    <strong>Success!</strong>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <div class="admin-card">
                <form method="post" class="admin-form">
                    <div class="admin-form-group">
                        <label>Page Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($page['title'] ?? ''); ?>" required>
                        <div class="admin-form-help">The title of your page as it will appear on the website.</div>
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Slug (URL) *</label>
                        <input type="text" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>" required <?php echo ($page['slug'] ?? '') === 'home' ? 'readonly' : ''; ?>>
                        <div class="admin-form-help">
                            <?php if (($page['slug'] ?? '') === 'home'): ?>
                                <strong>Homepage:</strong> This is your site's homepage. The slug is automatically set to "home".
                            <?php else: ?>
                                The URL-friendly version of the name. Leave empty to auto-generate from title. Example: "about-us" creates the URL /cms/about-us
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Content</label>
                        <div style="margin-bottom: 12px; padding: 12px; background: #f6f7f7; border-radius: 8px; border: 1px solid #c3c4c7;">
                            <button type="button" id="editor-toggle" class="admin-btn admin-btn-outline admin-btn-sm">
                                <span id="editor-mode-text">🎨 Switch to Visual Builder</span>
                            </button>
                            <span style="margin-left: 12px; color: #646970; font-size: 13px;">
                                Choose between Rich Text Editor or Visual Builder (Elementor-like drag-and-drop)
                            </span>
                        </div>
                        <div id="ckeditor-container" style="display: block;">
                            <textarea name="content" id="content-editor" rows="20"><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
                            <div class="admin-form-help">Use the rich text editor above to format your content. You can add images, links, lists, tables, and more.</div>
                        </div>
                        <div id="grapesjs-container" style="display: none; border: 2px solid #c3c4c7; border-radius: 8px; overflow: hidden; position: relative;">
                            <div style="background: linear-gradient(135deg, #f6f7f7 0%, #ffffff 100%); padding: 12px 16px; border-bottom: 2px solid #c3c4c7; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                    <span>🎨</span> Visual Builder
                                </span>
                                <button type="button" id="gjs-save-btn" class="admin-btn admin-btn-success admin-btn-sm" style="display: none;">
                                    💾 Save & Continue Editing
                                </button>
                            </div>
                            <div id="gjs-editor"></div>
                            <textarea name="grapesjs-content" id="grapesjs-content" style="display: none;"></textarea>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="admin-form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="draft" <?php echo ($page['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo ($page['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                            <div class="admin-form-help">Draft pages are not visible to visitors. Published pages are live on your website.</div>
                        </div>
                    </div>
                    
                    <div class="admin-card" style="margin-top: 24px; background: #f6f7f7;">
                        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #1d2327;">🔍 SEO Settings</h3>
                        <div class="admin-form-group">
                            <label>SEO Title</label>
                            <input type="text" name="seo_title" value="<?php echo htmlspecialchars($page['seo_title'] ?? ''); ?>" placeholder="Leave empty to use page title">
                            <div class="admin-form-help">The title that appears in search engine results. Recommended: 50-60 characters.</div>
                        </div>
                        <div class="admin-form-group">
                            <label>SEO Description</label>
                            <textarea name="seo_description" rows="3" placeholder="Brief description for search engines"><?php echo htmlspecialchars($page['seo_description'] ?? ''); ?></textarea>
                            <div class="admin-form-help">A brief description that appears in search engine results. Recommended: 150-160 characters.</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #c3c4c7; display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" name="save_page" class="admin-btn admin-btn-primary">
                            <span>💾</span> Save Page
                        </button>
                        <?php if ($id && $id !== 'home' && !isset($page['is_homepage'])): ?>
                            <button type="submit" name="delete_page" class="admin-btn admin-btn-danger" onclick="return confirm('Are you sure you want to delete this page? This action cannot be undone.');">
                                <span>🗑️</span> Delete Page
                            </button>
                        <?php endif; ?>
                        <a href="pages.php" class="admin-btn admin-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Info Notice -->
            <div class="admin-notice admin-notice-success">
                <div class="admin-notice-icon">💡</div>
                <div class="admin-notice-content">
                    <strong>Homepage Editing:</strong>
                    <p>The homepage is listed at the top of this table with a blue "Homepage" badge. Click "Create/Edit Homepage" to edit the main content section. The hero banner (top section) is controlled by Site Title and Tagline in Settings.</p>
                </div>
            </div>
            
            <!-- Special Pages Section -->
            <div class="admin-card" style="border-left: 4px solid #2563eb; background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.02) 100%);">
                <div class="admin-card-header">
                    <h2>⚡ Special Functional Pages</h2>
                </div>
                <p style="color: #646970; margin-bottom: 20px; font-size: 14px;">These pages have special functionality and can be edited via file or settings:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <div style="background: white; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px; transition: all 0.3s ease;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span style="font-size: 24px;">📋</span>
                            <strong style="font-size: 15px; color: #1d2327;">Estimates</strong>
                            <span class="admin-badge admin-badge-published" style="margin-left: auto; font-size: 10px; padding: 4px 8px;">Special</span>
                        </div>
                        <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.5;">Estimate request form page for customer inquiries</p>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="<?php echo $baseUrl; ?>/cms/quote" target="_blank" class="admin-action-btn admin-action-btn-view">View Page</a>
                            <a href="<?php echo $baseUrl; ?>/cms/public/quote.php" class="admin-action-btn admin-action-btn-edit">Edit File</a>
                        </div>
                    </div>
                    <div style="background: white; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px; transition: all 0.3s ease;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span style="font-size: 24px;">🚛</span>
                            <strong style="font-size: 15px; color: #1d2327;">Request Rig</strong>
                            <span class="admin-badge admin-badge-published" style="margin-left: auto; font-size: 10px; padding: 4px 8px;">Special</span>
                        </div>
                        <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.5;">Rig rental request form for drilling services</p>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="<?php echo $baseUrl; ?>/cms/rig-request" target="_blank" class="admin-action-btn admin-action-btn-view">View Page</a>
                            <a href="<?php echo $baseUrl; ?>/cms/public/rig-request.php" class="admin-action-btn admin-action-btn-edit">Edit File</a>
                        </div>
                    </div>
                    <div style="background: white; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px; transition: all 0.3s ease;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span style="font-size: 24px;">📧</span>
                            <strong style="font-size: 15px; color: #1d2327;">Contact Us</strong>
                            <span class="admin-badge admin-badge-published" style="margin-left: auto; font-size: 10px; padding: 4px 8px;">Special</span>
                        </div>
                        <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.5;">Contact form and company information</p>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="<?php echo $baseUrl; ?>/cms/contact" target="_blank" class="admin-action-btn admin-action-btn-view">View Page</a>
                            <a href="settings.php#contact" class="admin-action-btn admin-action-btn-edit">Edit Info</a>
                            <a href="<?php echo $baseUrl; ?>/cms/public/contact.php" class="admin-action-btn admin-action-btn-edit">Edit File</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pages List -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2>📋 All Pages</h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="search" id="pageSearch" placeholder="Search pages..." 
                               style="padding: 8px 12px; border: 2px solid #c3c4c7; border-radius: 6px; font-size: 13px; min-width: 200px;"
                               onkeyup="filterPagesTable()">
                    </div>
                </div>
                
                <?php if (empty($pages)): ?>
                    <div class="admin-empty-state">
                        <div class="admin-empty-state-icon">📄</div>
                        <h3>No pages found</h3>
                        <p>Get started by creating your first page.</p>
                        <a href="?action=add" class="admin-btn admin-btn-primary">Add New Page</a>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table" id="pagesTable">
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
                                    <tr data-page-title="<?php echo strtolower(htmlspecialchars($p['title'])); ?>">
                                        <td>
                                            <strong style="color: #1d2327;"><?php echo htmlspecialchars($p['title']); ?></strong>
                                            <?php if (($p['slug'] ?? '') === 'home' || isset($p['is_homepage'])): ?>
                                                <span class="admin-badge admin-badge-published" style="margin-left: 8px; font-size: 10px; padding: 4px 8px;">🏠 Homepage</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code style="background: #f6f7f7; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #646970;">
                                                <?php echo htmlspecialchars($p['slug']); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?php echo ($p['status'] ?? 'published') === 'published' ? 'published' : 'draft'; ?>">
                                                <?php echo ucfirst($p['status'] ?? 'Published'); ?>
                                            </span>
                                        </td>
                                        <td style="color: #646970; font-size: 13px;">
                                            <?php echo isset($p['created_at']) ? date('M j, Y', strtotime($p['created_at'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <?php if (isset($p['is_homepage'])): ?>
                                                    <a href="?action=edit&id=home" class="admin-action-btn admin-action-btn-edit">✏️ Edit</a>
                                                    <a href="<?php echo $baseUrl; ?>/" target="_blank" class="admin-action-btn admin-action-btn-view">👁️ View</a>
                                                <?php else: ?>
                                                    <a href="?action=edit&id=<?php echo $p['id']; ?>" class="admin-action-btn admin-action-btn-edit">✏️ Edit</a>
                                                    <a href="<?php echo $baseUrl; ?>/cms/<?php echo htmlspecialchars($p['slug']); ?>" target="_blank" class="admin-action-btn admin-action-btn-view">👁️ View</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <script>
            function filterPagesTable() {
                const search = document.getElementById('pageSearch').value.toLowerCase();
                const rows = document.querySelectorAll('#pagesTable tbody tr');
                
                rows.forEach(row => {
                    const pageTitle = row.getAttribute('data-page-title') || '';
                    row.style.display = !search || pageTitle.includes(search) ? '' : 'none';
                });
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>

