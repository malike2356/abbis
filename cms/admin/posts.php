<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    
    // Handle GrapesJS content if submitted
    if (isset($_POST['grapesjs-content']) && !empty($_POST['grapesjs-content'])) {
        $content = $_POST['grapesjs-content'];
    }
    
    $excerpt = trim($_POST['excerpt'] ?? '');
    $category_id = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'draft';
    $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
    
    if ($title && $slug) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cms_posts SET title=?, slug=?, content=?, excerpt=?, category_id=?, status=?, published_at=? WHERE id=?");
            $stmt->execute([$title, $slug, $content, $excerpt, $category_id ?: null, $status, $published_at, $id]);
            $message = 'Post updated';
        } else {
            $stmt = $pdo->prepare("INSERT INTO cms_posts (title, slug, content, excerpt, category_id, status, published_at, created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $slug, $content, $excerpt, $category_id ?: null, $status, $published_at, $_SESSION['cms_user_id']]);
            $message = 'Post created';
        }
    }
}

$post = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_posts WHERE id=?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
}

$posts = $pdo->query("SELECT p.*, c.name as category_name FROM cms_posts p LEFT JOIN cms_categories c ON c.id=p.category_id ORDER BY p.created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM cms_categories ORDER BY name")->fetchAll();

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
    <title>Posts - <?php echo htmlspecialchars($companyName); ?> CMS</title>
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
        let currentMode = 'ckeditor';
        const initialContent = <?php echo json_encode($post['content'] ?? ''); ?>;
        
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
                if (grapesEditor) return;
                
                const saveBtn = document.getElementById('gjs-save-btn');
                
                grapesEditor = grapesjs.init({
                    container: '#gjs-editor',
                    plugins: ['gjs-preset-webpage', 'gjs-blocks-basic'],
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
                    width: '100%'
                });
                
                if (initialContent && initialContent.includes('gjs-')) {
                    grapesEditor.setComponents(initialContent);
                } else if (initialContent) {
                    grapesEditor.setComponents(initialContent);
                }
                
                // Show save button
                if (saveBtn) {
                    saveBtn.style.display = 'inline-block';
                }
                
                // Update content with save feedback
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
                
                // Save button handler
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
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (currentMode === 'ckeditor') {
                        currentMode = 'grapesjs';
                        modeText.textContent = 'Switch to Rich Text Editor';
                        ckeditorContainer.style.display = 'none';
                        grapesjsContainer.style.display = 'block';
                        
                        if (editorInstance) {
                            const ckeditorContent = editorInstance.getData();
                            if (contentTextarea) {
                                contentTextarea.value = ckeditorContent;
                            }
                        }
                        
                        if (!grapesEditor) {
                            initGrapesJS();
                        } else {
                            if (contentTextarea && contentTextarea.value) {
                                grapesEditor.setComponents(contentTextarea.value);
                            }
                        }
                    } else {
                        currentMode = 'ckeditor';
                        modeText.textContent = 'Switch to Visual Builder';
                        ckeditorContainer.style.display = 'block';
                        grapesjsContainer.style.display = 'none';
                        
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
            }
            
            // Before form submit, sync content
            const form = document.querySelector('form');
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
        <h1><?php echo $action === 'edit' ? 'Edit Post' : ($action === 'add' ? 'Add New Post' : 'Posts'); ?></h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <form method="post">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" required class="large-text">
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($post['category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Excerpt</label>
                    <textarea name="excerpt" rows="3" class="large-text"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                    <p class="description">A short summary of your post. This will be displayed in blog listings.</p>
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
                        <textarea name="content" id="content-editor" rows="20" class="large-text"><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
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
                        <option value="draft" <?php echo ($post['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <p class="submit">
                    <input type="submit" name="save_post" class="button button-primary" value="Save Post">
                    <a href="posts.php" class="button">Cancel</a>
                </p>
            </form>
        <?php else: ?>
            <a href="?action=add" class="page-title-action">Add New</a>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>Title</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><span class="status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                            <td><?php echo $p['published_at'] ? date('Y/m/d', strtotime($p['published_at'])) : '-'; ?></td>
                            <td><a href="?action=edit&id=<?php echo $p['id']; ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

