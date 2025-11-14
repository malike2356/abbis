<?php
/**
 * CMS Admin - Access Control Lists (Joomla-inspired)
 * Granular permissions per content item
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
$contentTypeId = $_GET['content_type_id'] ?? null;
$entityId = $_GET['entity_id'] ?? null;
$message = null;
$messageType = 'success';

// Ensure tables exist
require_once dirname(__DIR__) . '/includes/ensure-advanced-tables.php';
if (!ensureAdvancedTablesExist($pdo)) {
    die("❌ Error: Could not create required database tables. Please run: php database/create_advanced_features_tables.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_acl_rule'])) {
        $contentTypeId = $_POST['content_type_id'] ?? null;
        $entityId = $_POST['entity_id'] ?? null;
        $userId = $_POST['user_id'] ?? null;
        $role = $_POST['role'] ?? null;
        $permission = $_POST['permission'];
        $granted = isset($_POST['granted']) ? 1 : 0;
        $ruleId = $_POST['rule_id'] ?? null;
        
        if ($permission) {
            if ($ruleId) {
                $stmt = $pdo->prepare("UPDATE cms_acl_rules SET content_type_id=?, entity_id=?, user_id=?, role=?, permission=?, granted=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$contentTypeId ?: null, $entityId ?: null, $userId ?: null, $role ?: null, $permission, $granted, $ruleId]);
                $message = 'ACL rule updated successfully';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_acl_rules (content_type_id, entity_id, user_id, role, permission, granted) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$contentTypeId ?: null, $entityId ?: null, $userId ?: null, $role ?: null, $permission, $granted]);
                $message = 'ACL rule created successfully';
            }
        }
    }
    
    if (isset($_POST['delete_acl_rule'])) {
        $ruleId = $_POST['rule_id'] ?? null;
        if ($ruleId) {
            $pdo->prepare("DELETE FROM cms_acl_rules WHERE id=?")->execute([$ruleId]);
            $message = 'ACL rule deleted successfully';
        }
    }
}

// Get content types
$contentTypes = $pdo->query("SELECT * FROM cms_content_types WHERE status='active' ORDER BY label")->fetchAll(PDO::FETCH_ASSOC);

// Get users
$users = $pdo->query("SELECT id, username, email FROM cms_users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get ACL rules
$where = [];
$params = [];
if ($contentTypeId) {
    $where[] = "r.content_type_id = ?";
    $params[] = $contentTypeId;
}
if ($entityId) {
    $where[] = "r.entity_id = ?";
    $params[] = $entityId;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$rules = $pdo->prepare("SELECT r.*, ct.label as content_type_label, u.username 
    FROM cms_acl_rules r 
    LEFT JOIN cms_content_types ct ON r.content_type_id = ct.id
    LEFT JOIN cms_users u ON r.user_id = u.id
    $whereClause
    ORDER BY r.created_at DESC");
$rules->execute($params);
$rules = $rules->fetchAll(PDO::FETCH_ASSOC);

// Get single rule for editing
$rule = null;
$ruleId = $_GET['rule_id'] ?? null;
if ($ruleId && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_acl_rules WHERE id=?");
    $stmt->execute([$ruleId]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/header.php';
?>

<style>
    .acl-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    .permission-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .permission-badge.allow {
        background: #d1fae5;
        color: #065f46;
    }
    .permission-badge.deny {
        background: #fee2e2;
        color: #991b1b;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>🔐 Access Control Lists</h1>
        <p>Manage granular permissions per content item (Joomla-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add ACL Rule</a>
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
        <!-- Filters -->
        <div style="background: white; padding: 1rem; border-radius: 12px; margin-top: 2rem; margin-bottom: 1rem;">
            <form method="get" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="admin-form-group" style="margin: 0;">
                    <label>Content Type</label>
                    <select name="content_type_id" class="large-text" onchange="this.form.submit()">
                        <option value="">All Content Types</option>
                        <?php foreach ($contentTypes as $ct): ?>
                            <option value="<?php echo $ct['id']; ?>" <?php echo ($contentTypeId ?? '') == $ct['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ct['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group" style="margin: 0;">
                    <label>Entity ID</label>
                    <input type="number" name="entity_id" value="<?php echo htmlspecialchars($entityId ?? ''); ?>" 
                           class="large-text" placeholder="Specific content item ID">
                </div>
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">🔍 Filter</button>
                    <a href="?" class="admin-btn admin-btn-outline">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Rules List -->
        <div style="margin-top: 1rem;">
            <?php if (empty($rules)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🔐</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No ACL Rules Yet</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Create ACL rules to control access to content</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create ACL Rule</a>
                </div>
            <?php else: ?>
                <?php foreach ($rules as $r): ?>
                    <div class="acl-card">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <span class="permission-badge <?php echo $r['granted'] ? 'allow' : 'deny'; ?>">
                                        <?php echo $r['granted'] ? '✓ Allow' : '✗ Deny'; ?>
                                    </span>
                                    <strong style="color: #1e293b;"><?php echo ucfirst($r['permission']); ?></strong>
                                </div>
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <?php if ($r['content_type_label']): ?>
                                        <span>Content Type: <strong><?php echo htmlspecialchars($r['content_type_label']); ?></strong></span>
                                    <?php else: ?>
                                        <span>Global Rule</span>
                                    <?php endif; ?>
                                    <?php if ($r['entity_id']): ?>
                                        <span style="margin-left: 1rem;">• Entity ID: <strong><?php echo $r['entity_id']; ?></strong></span>
                                    <?php endif; ?>
                                    <?php if ($r['username']): ?>
                                        <span style="margin-left: 1rem;">• User: <strong><?php echo htmlspecialchars($r['username']); ?></strong></span>
                                    <?php elseif ($r['role']): ?>
                                        <span style="margin-left: 1rem;">• Role: <strong><?php echo htmlspecialchars($r['role']); ?></strong></span>
                                    <?php else: ?>
                                        <span style="margin-left: 1rem;">• All Users</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="?action=edit&rule_id=<?php echo $r['id']; ?>" class="admin-btn admin-btn-outline">✏️ Edit</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this ACL rule?');">
                                    <input type="hidden" name="delete_acl_rule" value="1">
                                    <input type="hidden" name="rule_id" value="<?php echo $r['id']; ?>">
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
                    <div class="icon">🔐</div>
                    <h3>ACL Rule</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Content Type</label>
                    <select name="content_type_id" class="large-text" id="content_type_id">
                        <option value="">-- Global Rule (All Content Types) --</option>
                        <?php foreach ($contentTypes as $ct): ?>
                            <option value="<?php echo $ct['id']; ?>" <?php echo ($rule['content_type_id'] ?? '') == $ct['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ct['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="admin-form-help">Leave empty for global rule, or select specific content type</div>
                </div>
                
                <div class="admin-form-group">
                    <label>Entity ID (Optional)</label>
                    <input type="number" name="entity_id" value="<?php echo htmlspecialchars($rule['entity_id'] ?? ''); ?>" 
                           class="large-text" placeholder="Specific content item ID (leave empty for content type level)">
                    <div class="admin-form-help">Leave empty for content type level, or specify a specific content item ID</div>
                </div>
                
                <div class="admin-form-group">
                    <label>Permission *</label>
                    <select name="permission" required class="large-text">
                        <option value="view" <?php echo ($rule['permission'] ?? '') === 'view' ? 'selected' : ''; ?>>View</option>
                        <option value="edit" <?php echo ($rule['permission'] ?? '') === 'edit' ? 'selected' : ''; ?>>Edit</option>
                        <option value="delete" <?php echo ($rule['permission'] ?? '') === 'delete' ? 'selected' : ''; ?>>Delete</option>
                        <option value="publish" <?php echo ($rule['permission'] ?? '') === 'publish' ? 'selected' : ''; ?>>Publish</option>
                        <option value="unpublish" <?php echo ($rule['permission'] ?? '') === 'unpublish' ? 'selected' : ''; ?>>Unpublish</option>
                        <option value="manage" <?php echo ($rule['permission'] ?? '') === 'manage' ? 'selected' : ''; ?>>Manage</option>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label>Apply To</label>
                    <select name="apply_to" id="apply_to" class="large-text" onchange="toggleApplyTo()">
                        <option value="user" <?php echo ($rule['user_id'] ?? null) ? 'selected' : ''; ?>>Specific User</option>
                        <option value="role" <?php echo ($rule['role'] ?? null) ? 'selected' : ''; ?>>User Role</option>
                        <option value="all" <?php echo (!$rule['user_id'] && !$rule['role']) ? 'selected' : ''; ?>>All Users</option>
                    </select>
                </div>
                
                <div class="admin-form-group" id="user_select" style="display: none;">
                    <label>User</label>
                    <select name="user_id" class="large-text">
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($rule['user_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['username'] . ' (' . $u['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="admin-form-group" id="role_select" style="display: none;">
                    <label>Role</label>
                    <input type="text" name="role" value="<?php echo htmlspecialchars($rule['role'] ?? ''); ?>" 
                           class="large-text" placeholder="e.g., editor, author, subscriber">
                </div>
                
                <div class="admin-form-group">
                    <label>
                        <input type="checkbox" name="granted" value="1" <?php echo ($rule['granted'] ?? 1) ? 'checked' : ''; ?>>
                        Grant Permission (uncheck to deny)
                    </label>
                </div>
                
                <?php if ($rule): ?>
                    <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_acl_rule" class="admin-btn admin-btn-primary">💾 Save ACL Rule</button>
                    <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleApplyTo() {
    const applyTo = document.getElementById('apply_to').value;
    document.getElementById('user_select').style.display = applyTo === 'user' ? 'block' : 'none';
    document.getElementById('role_select').style.display = applyTo === 'role' ? 'block' : 'none';
}
toggleApplyTo();
</script>

<?php include __DIR__ . '/footer.php'; ?>

