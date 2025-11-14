<?php
/**
 * CMS Admin - Views System (Drupal-inspired)
 * Visual query builder for custom content displays
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
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = null;
$messageType = 'success';

// Ensure tables exist
require_once dirname(__DIR__) . '/includes/ensure-advanced-tables.php';
if (!ensureAdvancedTablesExist($pdo)) {
    die("❌ Error: Could not create required database tables. Please run: php database/create_advanced_features_tables.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_view'])) {
        $machineName = trim($_POST['machine_name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $description = $_POST['description'] ?? '';
        $contentTypeId = $_POST['content_type_id'] ?? null;
        $displayType = $_POST['display_type'] ?? 'list';
        $queryConfig = json_encode($_POST['query_config'] ?? []);
        $styleConfig = json_encode($_POST['style_config'] ?? []);
        
        if (empty($machineName) && !empty($label)) {
            $machineName = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $label));
        }
        
        if ($machineName && $label) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE cms_views SET label=?, description=?, content_type_id=?, display_type=?, query_config=?, style_config=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$label, $description, $contentTypeId, $displayType, $queryConfig, $styleConfig, $id]);
                $message = 'View updated successfully';
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM cms_views WHERE machine_name=? LIMIT 1");
                $checkStmt->execute([$machineName]);
                if ($checkStmt->fetch()) {
                    $message = 'View with this machine name already exists';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cms_views (machine_name, label, description, content_type_id, display_type, query_config, style_config, created_by) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$machineName, $label, $description, $contentTypeId, $displayType, $queryConfig, $styleConfig, $_SESSION['cms_user_id'] ?? 1]);
                    $id = $pdo->lastInsertId();
                    $message = 'View created successfully';
                }
            }
        }
    }
    
    if (isset($_POST['delete_view'])) {
        $pdo->prepare("DELETE FROM cms_views WHERE id=?")->execute([$id]);
        header('Location: views.php');
        exit;
    }
}

// Get views
$views = $pdo->query("SELECT v.*, ct.label as content_type_label 
    FROM cms_views v 
    LEFT JOIN cms_content_types ct ON v.content_type_id = ct.id
    ORDER BY v.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get content types for dropdown
$contentTypes = $pdo->query("SELECT * FROM cms_content_types WHERE status='active' ORDER BY label")->fetchAll(PDO::FETCH_ASSOC);

// Get single view for editing
$view = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_views WHERE id=?");
    $stmt->execute([$id]);
    $view = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($view) {
        $view['query_config'] = json_decode($view['query_config'], true) ?: [];
        $view['style_config'] = json_decode($view['style_config'], true) ?: [];
    }
}

include __DIR__ . '/header.php';
?>

<style>
    .view-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s;
    }
    .view-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #2563eb;
    }
    .query-builder {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
    }
    .filter-group {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>👁️ Views System</h1>
        <p>Create custom content displays with visual query builder (Drupal-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add New View</a>
            <?php else: ?>
                <a href="?" class="admin-btn admin-btn-outline">← Back to List</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-notice admin-notice-<?php echo $messageType; ?>">
            <span class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠' : '✓'; ?></span>
            <div class="admin-notice-content">
                <strong><?php echo $messageType === 'error' ? 'Error' : 'Success'; ?>!</strong>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <div style="margin-top: 2rem;">
            <?php if (empty($views)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">👁️</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No Views Yet</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Create your first view to display content in custom ways</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create View</a>
                </div>
            <?php else: ?>
                <?php foreach ($views as $v): ?>
                    <div class="view-card">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; color: #1e293b;">
                                    <?php echo htmlspecialchars($v['label']); ?>
                                </h3>
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <code><?php echo htmlspecialchars($v['machine_name']); ?></code>
                                    <?php if ($v['content_type_label']): ?>
                                        <span style="margin-left: 1rem;">• <?php echo htmlspecialchars($v['content_type_label']); ?></span>
                                    <?php endif; ?>
                                    <span style="margin-left: 1rem;">• <?php echo ucfirst($v['display_type']); ?></span>
                                </p>
                                <?php if ($v['description']): ?>
                                    <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($v['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="?action=edit&id=<?php echo $v['id']; ?>" class="admin-btn admin-btn-outline">✏️ Edit</a>
                                <a href="../public/view.php?view=<?php echo urlencode($v['machine_name']); ?>" target="_blank" class="admin-btn admin-btn-outline">👁️ Preview</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this view?');">
                                    <input type="hidden" name="delete_view" value="1">
                                    <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger">🗑️</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <form method="post" style="margin-top: 2rem;">
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">👁️</div>
                    <h3>View Information</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Machine Name *</label>
                    <input type="text" name="machine_name" value="<?php echo htmlspecialchars($view['machine_name'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., recent_posts, featured_products"
                           <?php echo $view ? 'readonly' : ''; ?>>
                </div>
                
                <div class="admin-form-group">
                    <label>Label *</label>
                    <input type="text" name="label" value="<?php echo htmlspecialchars($view['label'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., Recent Posts">
                </div>
                
                <div class="admin-form-group">
                    <label>Content Type</label>
                    <select name="content_type_id" class="large-text">
                        <option value="">-- All Content Types --</option>
                        <?php foreach ($contentTypes as $ct): ?>
                            <option value="<?php echo $ct['id']; ?>" <?php echo ($view['content_type_id'] ?? '') == $ct['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ct['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label>Display Type *</label>
                    <select name="display_type" required class="large-text">
                        <option value="list" <?php echo ($view['display_type'] ?? 'list') === 'list' ? 'selected' : ''; ?>>List</option>
                        <option value="grid" <?php echo ($view['display_type'] ?? '') === 'grid' ? 'selected' : ''; ?>>Grid</option>
                        <option value="table" <?php echo ($view['display_type'] ?? '') === 'table' ? 'selected' : ''; ?>>Table</option>
                        <option value="calendar" <?php echo ($view['display_type'] ?? '') === 'calendar' ? 'selected' : ''; ?>>Calendar</option>
                        <option value="map" <?php echo ($view['display_type'] ?? '') === 'map' ? 'selected' : ''; ?>>Map</option>
                        <option value="chart" <?php echo ($view['display_type'] ?? '') === 'chart' ? 'selected' : ''; ?>>Chart</option>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="large-text"><?php echo htmlspecialchars($view['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="editor-section" style="margin-top: 2rem;">
                <div class="editor-section-header">
                    <div class="icon">🔍</div>
                    <h3>Query Configuration</h3>
                </div>
                
                <div class="query-builder">
                    <div class="admin-form-group">
                        <label>Limit Results</label>
                        <input type="number" name="query_config[limit]" value="<?php echo htmlspecialchars($view['query_config']['limit'] ?? '10'); ?>" 
                               class="large-text" min="1" max="1000">
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Sort By</label>
                        <select name="query_config[sort_by]" class="large-text">
                            <option value="created_at" <?php echo ($view['query_config']['sort_by'] ?? 'created_at') === 'created_at' ? 'selected' : ''; ?>>Created Date</option>
                            <option value="updated_at" <?php echo ($view['query_config']['sort_by'] ?? '') === 'updated_at' ? 'selected' : ''; ?>>Updated Date</option>
                            <option value="title" <?php echo ($view['query_config']['sort_by'] ?? '') === 'title' ? 'selected' : ''; ?>>Title</option>
                            <option value="id" <?php echo ($view['query_config']['sort_by'] ?? '') === 'id' ? 'selected' : ''; ?>>ID</option>
                        </select>
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Sort Order</label>
                        <select name="query_config[sort_order]" class="large-text">
                            <option value="DESC" <?php echo ($view['query_config']['sort_order'] ?? 'DESC') === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo ($view['query_config']['sort_order'] ?? '') === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                    
                    <div class="admin-form-group">
                        <label>Status Filter</label>
                        <select name="query_config[status]" class="large-text">
                            <option value="">All Statuses</option>
                            <option value="published" <?php echo ($view['query_config']['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo ($view['query_config']['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="archived" <?php echo ($view['query_config']['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <button type="submit" name="save_view" class="admin-btn admin-btn-primary">💾 Save View</button>
                <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

