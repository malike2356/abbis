<?php
/**
 * CMS Admin - Categories Management (Full CRUD)
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

// Ensure cms_categories table exists
try {
    $pdo->query("SELECT * FROM cms_categories LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        description TEXT DEFAULT NULL,
        parent_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES cms_categories(id) ON DELETE SET NULL,
        INDEX idx_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

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
            $categoryIds = $_POST['category_ids'] ?? [];
            
            if (empty($categoryIds)) {
                echo json_encode(['success' => false, 'error' => 'No categories selected']);
                exit;
            }
            
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            
            try {
                if ($bulkAction === 'delete') {
                    // Check if categories have posts
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE category_id IN ($placeholders)");
                    $checkStmt->execute($categoryIds);
                    $postCount = $checkStmt->fetchColumn();
                    
                    if ($postCount > 0) {
                        echo json_encode(['success' => false, 'error' => 'Cannot delete: ' . $postCount . ' post(s) are assigned to these categories']);
                        exit;
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM cms_categories WHERE id IN ($placeholders)");
                    $stmt->execute($categoryIds);
                    echo json_encode(['success' => true, 'message' => count($categoryIds) . ' category(ies) deleted']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Handle category save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    // Auto-generate slug from name if empty
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM cms_categories WHERE slug=? AND id!=?");
            $checkStmt->execute([$slug, $id ?: 0]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }
    
    if ($name && $slug) {
        // Check for duplicate slug
        $checkStmt = $pdo->prepare("SELECT id FROM cms_categories WHERE slug=? AND id!=?");
        $checkStmt->execute([$slug, $id ?: 0]);
        if ($checkStmt->fetch()) {
            $message = 'Error: A category with this slug already exists';
            $messageType = 'error';
        } else {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE cms_categories SET name=?, slug=?, description=?, parent_id=? WHERE id=?");
                $stmt->execute([$name, $slug, $description ?: null, $parentId, $id]);
                $message = 'Category updated successfully';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_categories (name, slug, description, parent_id) VALUES (?,?,?,?)");
                $stmt->execute([$name, $slug, $description ?: null, $parentId]);
                $message = 'Category created successfully';
                $id = $pdo->lastInsertId();
                $action = 'edit';
            }
        }
    } else {
        $message = 'Error: Name and slug are required';
        $messageType = 'error';
    }
}

// Handle category delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $deleteId = intval($_POST['id'] ?? 0);
    if ($deleteId) {
        try {
            // Check if category has posts
            $postCheck = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE category_id=?");
            $postCheck->execute([$deleteId]);
            $postCount = $postCheck->fetchColumn();
            
            // Check if category has children
            $childCheck = $pdo->prepare("SELECT COUNT(*) FROM cms_categories WHERE parent_id=?");
            $childCheck->execute([$deleteId]);
            $childCount = $childCheck->fetchColumn();
            
            if ($postCount > 0) {
                $message = 'Cannot delete category: It has ' . $postCount . ' post(s). Please reassign or delete posts first.';
                $messageType = 'error';
            } elseif ($childCount > 0) {
                $message = 'Cannot delete category: It has ' . $childCount . ' subcategory(ies). Please delete or reassign subcategories first.';
                $messageType = 'error';
            } else {
                $pdo->prepare("DELETE FROM cms_categories WHERE id=?")->execute([$deleteId]);
                $message = 'Category deleted successfully';
                header('Location: categories.php');
                exit;
            }
        } catch (PDOException $e) {
            $message = 'Error deleting category: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle clone action
if (isset($_GET['action']) && $_GET['action'] === 'clone' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM cms_categories WHERE id=?");
    $stmt->execute([$id]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($original) {
        // Generate new slug
        $newSlug = $original['slug'] . '-copy-' . time();
        $newName = $original['name'] . ' (Copy)';
        
        // Ensure slug uniqueness
        $baseSlug = $newSlug;
        $counter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM cms_categories WHERE slug=? LIMIT 1");
            $checkStmt->execute([$newSlug]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $newSlug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        // Create clone
        $stmt = $pdo->prepare("INSERT INTO cms_categories (name, slug, description, parent_id) VALUES (?,?,?,?)");
        $stmt->execute([
            $newName,
            $newSlug,
            $original['description'],
            $original['parent_id']
        ]);
        
        $newId = $pdo->lastInsertId();
        header('Location: categories.php?action=edit&id=' . $newId);
        exit;
    }
}

$category = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_categories WHERE id=?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all categories (for parent selection and listing)
$allCategories = $pdo->query("SELECT * FROM cms_categories ORDER BY name")->fetchAll();

// Build hierarchical category tree
function buildCategoryTree($categories, $parentId = null, $level = 0) {
    $tree = [];
    foreach ($categories as $cat) {
        if (($cat['parent_id'] ?? null) == $parentId) {
            $cat['level'] = $level;
            $tree[] = $cat;
            $children = buildCategoryTree($categories, $cat['id'], $level + 1);
            $tree = array_merge($tree, $children);
        }
    }
    return $tree;
}

$categoriesTree = buildCategoryTree($allCategories);

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM cms_categories")->fetchColumn(),
    'with_posts' => $pdo->query("SELECT COUNT(DISTINCT category_id) FROM cms_posts WHERE category_id IS NOT NULL")->fetchColumn(),
    'top_level' => $pdo->query("SELECT COUNT(*) FROM cms_categories WHERE parent_id IS NULL")->fetchColumn(),
    'subcategories' => $pdo->query("SELECT COUNT(*) FROM cms_categories WHERE parent_id IS NOT NULL")->fetchColumn()
];

require_once dirname(__DIR__) . '/public/get-site-name.php';
$companyName = getCMSSiteName('CMS Admin');
$baseUrl = app_url();
$currentPage = 'categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php include 'header.php'; ?>
    <style>
        .category-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .category-item {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-item:hover {
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
        }
        
        .category-item-info {
            flex: 1;
        }
        
        .category-item-name {
            font-weight: 600;
            font-size: 16px;
            color: #1d2327;
            margin-bottom: 4px;
        }
        
        .category-item-meta {
            font-size: 13px;
            color: #646970;
            display: flex;
            gap: 16px;
            align-items: center;
        }
        
        .category-item-slug {
            font-family: 'Courier New', monospace;
            background: #f6f7f7;
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .category-indent {
            margin-left: 32px;
            border-left: 3px solid #2563eb;
            padding-left: 16px;
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
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <!-- Page Header -->
        <div class="admin-page-header">
            <h1><?php echo $action === 'edit' ? '✏️ Edit Category' : ($action === 'add' ? '➕ Add New Category' : '📁 Categories Management'); ?></h1>
            <p>
                <?php if ($action === 'edit' || $action === 'add'): ?>
                    Create and manage post categories. Categories help organize your blog content and improve navigation.
                <?php else: ?>
                    Organize your blog posts with categories. Create hierarchical categories (parent/child) for better content organization.
                <?php endif; ?>
            </p>
            <?php if ($action === 'edit' && $category): ?>
            <div class="admin-page-actions">
                <a href="?action=clone&id=<?php echo $id; ?>" class="admin-btn admin-btn-outline" onclick="return confirm('This will create a copy of this category. Continue?');" style="background: #f0f0f1; color: #1d2327; border-color: #c3c4c7;">
                    <span>📋</span> Clone Category
                </a>
            </div>
            <?php elseif ($action !== 'edit' && $action !== 'add'): ?>
            <div class="admin-page-actions">
                <a href="?action=add" class="admin-btn admin-btn-primary">
                    <span>➕</span> Add New Category
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
            <!-- Category Form -->
            <div class="admin-card">
                <form method="post" class="admin-form">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                        <div>
                            <div class="admin-form-group">
                                <label>Category Name <span style="color: #d63638;">*</span></label>
                                <input type="text" name="name" id="category-name" value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" required>
                                <div class="admin-form-help">The name of the category as it will appear on your website.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Slug (URL) <span style="color: #d63638;">*</span></label>
                                <input type="text" name="slug" id="category-slug" value="<?php echo htmlspecialchars($category['slug'] ?? ''); ?>" required>
                                <div class="admin-form-help">
                                    The URL-friendly version of the name. Auto-generated from name if left empty.
                                    <div class="slug-preview" id="slug-preview" style="font-family: 'Courier New', monospace; font-size: 12px; color: #646970; background: #f6f7f7; padding: 4px 8px; border-radius: 4px; margin-top: 4px; display: inline-block;">
                                        <?php echo $baseUrl; ?>/cms/blog?category=<?php echo htmlspecialchars($category['slug'] ?? ''); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Description</label>
                                <textarea name="description" rows="4" placeholder="A brief description of this category..."><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                                <div class="admin-form-help">Optional description of this category. This may be displayed on category archive pages.</div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="admin-form-group">
                                <label>Parent Category</label>
                                <select name="parent_id">
                                    <option value="">None (Top Level)</option>
                                    <?php foreach ($allCategories as $cat): ?>
                                        <?php if ($cat['id'] != $id): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category['parent_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="admin-form-help">Optional. Select a parent category to create a hierarchical structure.</div>
                            </div>
                            
                            <?php if ($id): ?>
                                <div class="admin-form-group">
                                    <label>Category Info</label>
                                    <div style="background: #f6f7f7; padding: 16px; border-radius: 8px; font-size: 13px;">
                                        <?php
                                        $postCount = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE category_id=?");
                                        $postCount->execute([$id]);
                                        $posts = $postCount->fetchColumn();
                                        ?>
                                        <div style="margin-bottom: 8px;"><strong>Posts:</strong> <?php echo $posts; ?></div>
                                        <?php
                                        $childCount = $pdo->prepare("SELECT COUNT(*) FROM cms_categories WHERE parent_id=?");
                                        $childCount->execute([$id]);
                                        $children = $childCount->fetchColumn();
                                        ?>
                                        <div><strong>Subcategories:</strong> <?php echo $children; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #c3c4c7; display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" name="save_category" class="admin-btn admin-btn-primary">
                            <span>💾</span> Save Category
                        </button>
                        <a href="categories.php" class="admin-btn admin-btn-outline">Cancel</a>
                        <?php if ($id): ?>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category? This will not delete posts, but will remove the category assignment.');">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <button type="submit" name="delete_category" class="admin-btn admin-btn-outline" style="color: #d63638; border-color: #d63638;">
                                    <span>🗑️</span> Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="category-stats">
                <div class="stat-card">
                    <div class="stat-card-label">Total Categories</div>
                    <div class="stat-card-value"><?php echo number_format($stats['total']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">With Posts</div>
                    <div class="stat-card-value" style="color: #10b981;"><?php echo number_format($stats['with_posts']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Top Level</div>
                    <div class="stat-card-value" style="color: #2563eb;"><?php echo number_format($stats['top_level']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Subcategories</div>
                    <div class="stat-card-value" style="color: #f59e0b;"><?php echo number_format($stats['subcategories']); ?></div>
                </div>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="bulk-actions-bar">
                <strong id="selected-count">0</strong> category(ies) selected
                <select id="bulk-action-select" class="admin-form-group select" style="margin: 0;">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" id="apply-bulk-action" class="admin-btn admin-btn-primary">Apply</button>
                <button type="button" id="clear-selection" class="admin-btn admin-btn-outline">Clear</button>
            </div>
            
            <!-- Categories List -->
            <?php if (empty($categoriesTree)): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-state-icon">📁</div>
                    <h3>No categories found</h3>
                    <p>Create your first category to organize your blog posts.</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary" style="margin-top: 16px;">
                        <span>➕</span> Create Your First Category
                    </a>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>📁 All Categories</h2>
                    </div>
                    
                    <div id="categories-list">
                        <?php foreach ($categoriesTree as $cat): 
                            $postCount = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE category_id=?");
                            $postCount->execute([$cat['id']]);
                            $posts = $postCount->fetchColumn();
                            
                            $childCount = $pdo->prepare("SELECT COUNT(*) FROM cms_categories WHERE parent_id=?");
                            $childCount->execute([$cat['id']]);
                            $children = $childCount->fetchColumn();
                        ?>
                            <div class="category-item <?php echo $cat['level'] > 0 ? 'category-indent' : ''; ?>" style="margin-left: <?php echo $cat['level'] * 32; ?>px;">
                                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                    <input type="checkbox" class="category-checkbox" value="<?php echo $cat['id']; ?>">
                                    <div class="category-item-info">
                                        <div class="category-item-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                                        <div class="category-item-meta">
                                            <span class="category-item-slug"><?php echo htmlspecialchars($cat['slug']); ?></span>
                                            <span>📝 <?php echo $posts; ?> post(s)</span>
                                            <?php if ($children > 0): ?>
                                                <span>📁 <?php echo $children; ?> subcategory(ies)</span>
                                            <?php endif; ?>
                                            <?php if ($cat['description']): ?>
                                                <span>— <?php echo htmlspecialchars(substr($cat['description'], 0, 50)); ?><?php echo strlen($cat['description']) > 50 ? '...' : ''; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="admin-actions">
                                    <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="admin-action-btn admin-action-btn-edit" title="Edit">✏️</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" name="delete_category" class="admin-action-btn" style="color: #d63638;" title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Auto-generate slug from name
        const nameInput = $('#category-name');
        const slugInput = $('#category-slug');
        
        if (nameInput.length && slugInput.length && !slugInput.val()) {
            nameInput.on('blur', function() {
                if (!slugInput.val() || slugInput.data('auto-generated') === 'true') {
                    const slug = this.value.toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    slugInput.val(slug);
                    slugInput.data('auto-generated', 'true');
                    updateSlugPreview();
                }
            });
        }
        
        // Update slug preview
        function updateSlugPreview() {
            if (slugInput.length) {
                const preview = $('#slug-preview');
                if (preview.length) {
                    preview.text('<?php echo $baseUrl; ?>/cms/blog?category=' + slugInput.val());
                }
            }
        }
        
        if (slugInput.length) {
            slugInput.on('input', updateSlugPreview);
            updateSlugPreview();
        }
        
        // Bulk actions
        var selectedCategories = [];
        
        $('.category-checkbox').on('change', function() {
            updateBulkActions();
        });
        
        function updateBulkActions() {
            selectedCategories = $('.category-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            $('#selected-count').text(selectedCategories.length);
            $('#bulk-actions-bar').toggleClass('active', selectedCategories.length > 0);
        }
        
        $('#clear-selection').on('click', function() {
            $('.category-checkbox').prop('checked', false);
            updateBulkActions();
        });
        
        $('#apply-bulk-action').on('click', function() {
            var action = $('#bulk-action-select').val();
            if (!action) {
                alert('Please select a bulk action');
                return;
            }
            
            if (selectedCategories.length === 0) {
                alert('Please select at least one category');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to delete ' + selectedCategories.length + ' category(ies)?')) {
                return;
            }
            
            $.post('', {
                ajax_action: 'bulk_action',
                bulk_action: action,
                category_ids: selectedCategories
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to perform bulk action'));
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>

