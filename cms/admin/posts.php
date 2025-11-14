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
$message = null;
$messageType = 'success';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'bulk_action':
            $bulkAction = $_POST['bulk_action'] ?? '';
            $postIds = $_POST['post_ids'] ?? [];
            
            if (empty($postIds)) {
                echo json_encode(['success' => false, 'error' => 'No posts selected']);
                exit;
            }
            
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            
            try {
                if ($bulkAction === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM cms_posts WHERE id IN ($placeholders)");
                    $stmt->execute($postIds);
                    echo json_encode(['success' => true, 'message' => count($postIds) . ' post(s) deleted']);
                } elseif ($bulkAction === 'publish') {
                    $stmt = $pdo->prepare("UPDATE cms_posts SET status='published', published_at=COALESCE(published_at, NOW()) WHERE id IN ($placeholders)");
                    $stmt->execute($postIds);
                    echo json_encode(['success' => true, 'message' => count($postIds) . ' post(s) published']);
                } elseif ($bulkAction === 'draft') {
                    $stmt = $pdo->prepare("UPDATE cms_posts SET status='draft' WHERE id IN ($placeholders)");
                    $stmt->execute($postIds);
                    echo json_encode(['success' => true, 'message' => count($postIds) . ' post(s) moved to draft']);
                } elseif ($bulkAction === 'archive') {
                    $stmt = $pdo->prepare("UPDATE cms_posts SET status='archived' WHERE id IN ($placeholders)");
                    $stmt->execute($postIds);
                    echo json_encode(['success' => true, 'message' => count($postIds) . ' post(s) archived']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'duplicate':
            $postId = intval($_POST['post_id'] ?? 0);
            if (!$postId) {
                echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM cms_posts WHERE id=?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$post) {
                    echo json_encode(['success' => false, 'error' => 'Post not found']);
                    exit;
                }
                
                // Generate new slug
                $newSlug = $post['slug'] . '-copy-' . time();
                $stmt = $pdo->prepare("INSERT INTO cms_posts (title, slug, content, excerpt, featured_image, category_id, status, seo_title, seo_description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $post['title'] . ' (Copy)',
                    $newSlug,
                    $post['content'],
                    $post['excerpt'],
                    $post['featured_image'],
                    $post['category_id'],
                    'draft',
                    $post['seo_title'],
                    $post['seo_description'],
                    $_SESSION['cms_user_id'] ?? null
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Post duplicated', 'id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'toggle_status':
            $postId = intval($_POST['post_id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'draft';
            if (!$postId) {
                echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
                exit;
            }
            
            try {
                $publishedAt = $newStatus === 'published' ? date('Y-m-d H:i:s') : null;
                $stmt = $pdo->prepare("UPDATE cms_posts SET status=?, published_at=COALESCE(published_at, ?) WHERE id=?");
                $stmt->execute([$newStatus, $publishedAt, $postId]);
                echo json_encode(['success' => true, 'status' => $newStatus]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Handle post save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    
    // Handle GrapesJS content if submitted
    if (isset($_POST['grapesjs-content']) && !empty($_POST['grapesjs-content'])) {
        $content = $_POST['grapesjs-content'];
    }
    
    $excerpt = trim($_POST['excerpt'] ?? '');
    $featured_image = trim($_POST['featured_image'] ?? '');
    $category_id = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'draft';
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_description = trim($_POST['seo_description'] ?? '');
    $published_at = ($status === 'published' && !$id) ? date('Y-m-d H:i:s') : null;
    
    // Auto-generate slug from title if empty
    if (empty($slug) && !empty($title)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM cms_posts WHERE slug=? AND id!=?");
            $checkStmt->execute([$slug, $id ?: 0]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }
    
    if ($title && $slug) {
        // Check for duplicate slug
        $checkStmt = $pdo->prepare("SELECT id FROM cms_posts WHERE slug=? AND id!=?");
        $checkStmt->execute([$slug, $id ?: 0]);
        if ($checkStmt->fetch()) {
            $message = 'Error: A post with this slug already exists';
            $messageType = 'error';
        } else {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE cms_posts SET title=?, slug=?, content=?, excerpt=?, featured_image=?, category_id=?, status=?, seo_title=?, seo_description=?, published_at=? WHERE id=?");
                $stmt->execute([$title, $slug, $content, $excerpt, $featured_image ?: null, $category_id ?: null, $status, $seo_title ?: null, $seo_description ?: null, $published_at, $id]);
                $message = 'Post updated successfully';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_posts (title, slug, content, excerpt, featured_image, category_id, status, seo_title, seo_description, published_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$title, $slug, $content, $excerpt, $featured_image ?: null, $category_id ?: null, $status, $seo_title ?: null, $seo_description ?: null, $published_at, $_SESSION['cms_user_id'] ?? null]);
                $message = 'Post created successfully';
                $id = $pdo->lastInsertId();
                $action = 'edit';
            }
        }
    } else {
        $message = 'Error: Title and slug are required';
        $messageType = 'error';
    }
}

// Handle post delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $deleteId = intval($_POST['id'] ?? 0);
    if ($deleteId) {
        $pdo->prepare("DELETE FROM cms_posts WHERE id=?")->execute([$deleteId]);
        $message = 'Post deleted successfully';
        header('Location: posts.php');
        exit;
    }
}

// Handle clone action (enhance existing duplicate functionality)
if (isset($_GET['action']) && $_GET['action'] === 'clone' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_posts WHERE id=?");
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
            $checkStmt = $pdo->prepare("SELECT id FROM cms_posts WHERE slug=? LIMIT 1");
            $checkStmt->execute([$newSlug]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $newSlug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        // Create clone
        $stmt = $pdo->prepare("INSERT INTO cms_posts (title, slug, content, excerpt, featured_image, category_id, status, seo_title, seo_description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $newTitle,
            $newSlug,
            $original['content'],
            $original['excerpt'],
            $original['featured_image'],
            $original['category_id'],
            'draft', // Always clone as draft
            $original['seo_title'],
            $original['seo_description'],
            $_SESSION['cms_user_id'] ?? null
        ]);
        
        $newId = $pdo->lastInsertId();
        header('Location: posts.php?action=edit&id=' . $newId);
        exit;
    }
}

$post = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_posts WHERE id=?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterCategory = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($filterStatus !== 'all') {
    $whereConditions[] = "p.status = ?";
    $params[] = $filterStatus;
}

if ($filterCategory !== 'all') {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$postsStmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM cms_posts p LEFT JOIN cms_categories c ON c.id=p.category_id $whereClause ORDER BY p.created_at DESC");
$postsStmt->execute($params);
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM cms_categories ORDER BY name")->fetchAll();

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM cms_posts")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='published'")->fetchColumn(),
    'draft' => $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='draft'")->fetchColumn(),
    'archived' => $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='archived'")->fetchColumn(),
    'this_month' => $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn()
];

require_once dirname(__DIR__) . '/public/get-site-name.php';
$companyName = getCMSSiteName('CMS Admin');
$baseUrl = app_url();
$currentPage = 'posts';
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
        
        .post-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            color: #2563eb;
            margin: 8px 0;
        }
        
        .stat-card-label {
            font-size: 14px;
            color: #646970;
            font-weight: 600;
        }
        
        .post-filters {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .post-filters input,
        .post-filters select {
            padding: 10px 12px;
            border: 2px solid #c3c4c7;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .post-filters input[type="search"] {
            flex: 1;
            min-width: 250px;
        }
        
        .bulk-actions-bar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 12px;
        }
        
        .bulk-actions-bar.active {
            display: flex;
        }
        
        .post-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #c3c4c7;
        }
        
        .post-title-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .post-title-info {
            flex: 1;
        }
        
        .post-title-info strong {
            display: block;
            margin-bottom: 4px;
            color: #1d2327;
        }
        
        .post-title-info small {
            color: #646970;
            font-size: 12px;
        }
        
        .featured-image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #c3c4c7;
            margin-top: 8px;
        }
        
        .slug-preview {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #646970;
            background: #f6f7f7;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 4px;
            display: inline-block;
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
            
            // Auto-generate slug from title
            const titleInput = document.querySelector('input[name="title"]');
            const slugInput = document.querySelector('input[name="slug"]');
            if (titleInput && slugInput && !slugInput.value) {
                titleInput.addEventListener('blur', function() {
                    if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
                        const slug = this.value.toLowerCase()
                            .trim()
                            .replace(/[^a-z0-9]+/g, '-')
                            .replace(/^-+|-+$/g, '');
                        slugInput.value = slug;
                        slugInput.dataset.autoGenerated = 'true';
                        updateSlugPreview();
                    }
                });
            }
            
            // Update slug preview
            function updateSlugPreview() {
                if (slugInput) {
                    const preview = document.getElementById('slug-preview');
                    if (preview) {
                        preview.textContent = '<?php echo $baseUrl; ?>/cms/post/' + slugInput.value;
                    }
                }
            }
            
            if (slugInput) {
                slugInput.addEventListener('input', updateSlugPreview);
                updateSlugPreview();
            }
            
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
                
                if (saveBtn) {
                    saveBtn.style.display = 'inline-block';
                }
                
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
                        modeText.textContent = '🎨 Switch to Visual Builder';
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
        <!-- Page Header -->
        <div class="admin-page-header">
            <h1><?php echo $action === 'edit' ? '✏️ Edit Post' : ($action === 'add' ? '➕ Add New Post' : '✏️ Posts Management'); ?></h1>
            <p>
                <?php if ($action === 'edit' || $action === 'add'): ?>
                    Create and edit blog posts. Use the Rich Text Editor for formatted content or the Visual Builder for drag-and-drop design.
                <?php else: ?>
                    Manage all your blog posts. Create new posts, edit existing ones, organize by categories, and control their publication status.
                <?php endif; ?>
            </p>
            <?php if ($action === 'edit' && $post): ?>
            <div class="admin-page-actions">
                <a href="?action=clone&id=<?php echo $id; ?>" class="admin-btn admin-btn-outline" onclick="return confirm('This will create a copy of this post. Continue?');" style="background: #f0f0f1; color: #1d2327; border-color: #c3c4c7;">
                    <span>📋</span> Clone Post
                </a>
            </div>
            <?php elseif ($action !== 'edit' && $action !== 'add'): ?>
            <div class="admin-page-actions">
                <a href="?action=add" class="admin-btn admin-btn-primary">
                    <span>➕</span> Add New Post
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="admin-notice admin-notice-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>" style="margin-bottom: 24px;">
                <div class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠️' : '✅'; ?></div>
                <div class="admin-notice-content"><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <!-- Post Form -->
            <div class="admin-card">
                <form method="post" class="admin-form">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                        <div>
                            <div class="admin-form-group">
                                <label>Post Title <span style="color: #d63638;">*</span></label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" required>
                                <div class="admin-form-help">The title of your blog post as it will appear on the website.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Slug (URL) <span style="color: #d63638;">*</span></label>
                                <input type="text" name="slug" id="slug-input" value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>" required>
                                <div class="admin-form-help">
                                    The URL-friendly version of the title. Auto-generated from title if left empty.
                                    <div class="slug-preview" id="slug-preview"><?php echo $baseUrl; ?>/cms/post/<?php echo htmlspecialchars($post['slug'] ?? ''); ?></div>
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Excerpt</label>
                                <textarea name="excerpt" rows="3" placeholder="A short summary of your post..."><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                                <div class="admin-form-help">A short summary of your post. This will be displayed in blog listings and search results.</div>
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
                                    <textarea name="content" id="content-editor" rows="20"><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
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
                        </div>
                        
                        <div>
                            <div class="admin-form-group">
                                <label>Featured Image</label>
                                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                                    <input type="url" name="featured_image" id="featured_image" value="<?php echo htmlspecialchars($post['featured_image'] ?? ''); ?>" placeholder="https://example.com/image.jpg" style="flex: 1;">
                                    <button type="button" class="admin-btn admin-btn-primary" onclick="openMediaPicker({
                                        targetInput: '#featured_image',
                                        targetPreview: '#featured_image_preview',
                                        allowedTypes: ['image'],
                                        baseUrl: '<?php echo $baseUrl; ?>'
                                    }); return false;">📁 Select from Media Library</button>
                                </div>
                                <div class="admin-form-help">Enter an image URL or select from your media library.</div>
                                <div id="featured_image_preview" style="margin-top: 12px;">
                                    <?php if (!empty($post['featured_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="Featured Image" class="featured-image-preview" onerror="this.style.display='none'" style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid #c3c4c7;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Category</label>
                                <select name="category_id">
                                    <option value="">Uncategorized</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($post['category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="admin-form-help">Organize your post by category for better navigation.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="draft" <?php echo ($post['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="archived" <?php echo ($post['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                                <div class="admin-form-help">Draft posts are not visible to visitors. Published posts are live on your blog.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>SEO Title</label>
                                <input type="text" name="seo_title" value="<?php echo htmlspecialchars($post['seo_title'] ?? ''); ?>" placeholder="Leave empty to use post title">
                                <div class="admin-form-help">Custom title for search engines. If empty, the post title will be used.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>SEO Description</label>
                                <textarea name="seo_description" rows="3" placeholder="Leave empty to use excerpt"><?php echo htmlspecialchars($post['seo_description'] ?? ''); ?></textarea>
                                <div class="admin-form-help">Meta description for search engines. If empty, the excerpt will be used.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #c3c4c7; display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" name="save_post" class="admin-btn admin-btn-primary">
                            <span>💾</span> Save Post
                        </button>
                        <a href="posts.php" class="admin-btn admin-btn-outline">Cancel</a>
                        <?php if ($id): ?>
                            <a href="<?php echo $baseUrl; ?>/cms/post/<?php echo htmlspecialchars($post['slug']); ?>" target="_blank" class="admin-btn admin-btn-outline">
                                <span>👁️</span> Preview
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="post-stats">
                <div class="stat-card">
                    <div class="stat-card-label">Total Posts</div>
                    <div class="stat-card-value"><?php echo number_format($stats['total']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Published</div>
                    <div class="stat-card-value" style="color: #10b981;"><?php echo number_format($stats['published']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Drafts</div>
                    <div class="stat-card-value" style="color: #f59e0b;"><?php echo number_format($stats['draft']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Archived</div>
                    <div class="stat-card-value" style="color: #6b7280;"><?php echo number_format($stats['archived']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">This Month</div>
                    <div class="stat-card-value" style="color: #2563eb;"><?php echo number_format($stats['this_month']); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="post-filters">
                <input type="search" id="search-input" placeholder="🔍 Search posts by title, content, or excerpt..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select id="filter-status">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="published" <?php echo $filterStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $filterStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="archived" <?php echo $filterStatus === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
                <select id="filter-category">
                    <option value="all" <?php echo $filterCategory === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="?action=add" class="admin-btn admin-btn-primary">
                    <span>➕</span> Add New Post
                </a>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="bulk-actions-bar">
                <strong id="selected-count">0</strong> post(s) selected
                <select id="bulk-action-select" class="admin-form-group select" style="margin: 0;">
                    <option value="">Bulk Actions</option>
                    <option value="publish">Publish</option>
                    <option value="draft">Move to Draft</option>
                    <option value="archive">Archive</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" id="apply-bulk-action" class="admin-btn admin-btn-primary">Apply</button>
                <button type="button" id="clear-selection" class="admin-btn admin-btn-outline">Clear</button>
            </div>
            
            <!-- Posts Table -->
            <?php if (empty($posts)): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-state-icon">✏️</div>
                    <h3>No posts found</h3>
                    <p><?php echo !empty($searchQuery) || $filterStatus !== 'all' || $filterCategory !== 'all' ? 'Try adjusting your filters.' : 'Get started by creating your first blog post.'; ?></p>
                    <?php if (empty($searchQuery) && $filterStatus === 'all' && $filterCategory === 'all'): ?>
                        <a href="?action=add" class="admin-btn admin-btn-primary" style="margin-top: 16px;">
                            <span>➕</span> Create Your First Post
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all">
                                    </th>
                                    <th>Post</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $p): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="post-checkbox" value="<?php echo $p['id']; ?>">
                                        </td>
                                        <td>
                                            <div class="post-title-cell">
                                                <?php if (!empty($p['featured_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($p['featured_image']); ?>" alt="Thumbnail" class="post-thumbnail" onerror="this.style.display='none'">
                                                <?php else: ?>
                                                    <div style="width: 60px; height: 60px; background: #f3f4f6; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 24px;">📄</div>
                                                <?php endif; ?>
                                                <div class="post-title-info">
                                                    <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                                                    <?php if ($p['excerpt']): ?>
                                                        <small><?php echo htmlspecialchars(substr($p['excerpt'], 0, 80)); ?><?php echo strlen($p['excerpt']) > 80 ? '...' : ''; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="admin-badge" style="background: rgba(37, 99, 235, 0.1); color: #2563eb; font-size: 11px; padding: 4px 10px;">
                                                <?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?php echo $p['status']; ?>">
                                                <?php echo ucfirst($p['status']); ?>
                                            </span>
                                        </td>
                                        <td style="color: #646970; font-size: 13px;">
                                            <?php if ($p['published_at']): ?>
                                                <div><?php echo date('M j, Y', strtotime($p['published_at'])); ?></div>
                                                <small><?php echo date('g:i A', strtotime($p['published_at'])); ?></small>
                                            <?php else: ?>
                                                <div><?php echo date('M j, Y', strtotime($p['created_at'])); ?></div>
                                                <small>Draft</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <a href="?action=edit&id=<?php echo $p['id']; ?>" class="admin-action-btn admin-action-btn-edit" title="Edit">✏️</a>
                                                <a href="<?php echo $baseUrl; ?>/cms/post/<?php echo htmlspecialchars($p['slug']); ?>" target="_blank" class="admin-action-btn admin-action-btn-view" title="View">👁️</a>
                                                <button type="button" class="admin-action-btn duplicate-post" data-id="<?php echo $p['id']; ?>" title="Duplicate">📄</button>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" name="delete_post" class="admin-action-btn" style="color: #d63638;" title="Delete">🗑️</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Bulk actions
        var selectedPosts = [];
        
        $('#select-all').on('change', function() {
            $('.post-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkActions();
        });
        
        $('.post-checkbox').on('change', function() {
            updateBulkActions();
        });
        
        function updateBulkActions() {
            selectedPosts = $('.post-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            $('#selected-count').text(selectedPosts.length);
            $('#bulk-actions-bar').toggleClass('active', selectedPosts.length > 0);
            $('#select-all').prop('checked', selectedPosts.length === $('.post-checkbox').length && selectedPosts.length > 0);
        }
        
        $('#clear-selection').on('click', function() {
            $('.post-checkbox, #select-all').prop('checked', false);
            updateBulkActions();
        });
        
        $('#apply-bulk-action').on('click', function() {
            var action = $('#bulk-action-select').val();
            if (!action) {
                alert('Please select a bulk action');
                return;
            }
            
            if (selectedPosts.length === 0) {
                alert('Please select at least one post');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to delete ' + selectedPosts.length + ' post(s)?')) {
                return;
            }
            
            $.post('', {
                ajax_action: 'bulk_action',
                bulk_action: action,
                post_ids: selectedPosts
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to perform bulk action'));
                }
            }, 'json');
        });
        
        // Duplicate post
        $('.duplicate-post').on('click', function() {
            var postId = $(this).data('id');
            if (!confirm('Duplicate this post?')) return;
            
            $.post('', {
                ajax_action: 'duplicate',
                post_id: postId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to duplicate post'));
                }
            }, 'json');
        });
        
        // Filters
        function applyFilters() {
            var search = $('#search-input').val();
            var status = $('#filter-status').val();
            var category = $('#filter-category').val();
            
            var params = new URLSearchParams();
            if (search) params.set('search', search);
            if (status !== 'all') params.set('status', status);
            if (category !== 'all') params.set('category', category);
            
            window.location.href = 'posts.php' + (params.toString() ? '?' + params.toString() : '');
        }
        
        $('#search-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyFilters();
            }
        });
        
        $('#filter-status, #filter-category').on('change', applyFilters);
    });
    </script>
</body>
</html>
