<?php
/**
 * CMS Admin - Content Types Builder (Drupal-inspired)
 * Create and manage custom content types with custom fields
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
    if (isset($_POST['save_content_type'])) {
        $machineName = trim($_POST['machine_name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $description = $_POST['description'] ?? '';
        $icon = $_POST['icon'] ?? '📄';
        $baseTable = $_POST['base_table'] ?? null;
        
        // Auto-generate machine name from label if not provided
        if (empty($machineName) && !empty($label)) {
            $machineName = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $label));
        }
        
        if ($machineName && $label) {
            if ($id) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE cms_content_types SET label=?, description=?, icon=?, base_table=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$label, $description, $icon, $baseTable, $id]);
                $message = 'Content type updated successfully';
            } else {
                // Check if machine name exists
                $checkStmt = $pdo->prepare("SELECT id FROM cms_content_types WHERE machine_name=? LIMIT 1");
                $checkStmt->execute([$machineName]);
                if ($checkStmt->fetch()) {
                    $message = 'Content type with this machine name already exists';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cms_content_types (machine_name, label, description, icon, base_table) VALUES (?,?,?,?,?)");
                    $stmt->execute([$machineName, $label, $description, $icon, $baseTable]);
                    $id = $pdo->lastInsertId();
                    $message = 'Content type created successfully';
                }
            }
        }
    }
    
    if (isset($_POST['save_field'])) {
        $contentTypeId = $_POST['content_type_id'] ?? $id;
        $fieldMachineName = trim($_POST['field_machine_name'] ?? '');
        $fieldLabel = trim($_POST['field_label'] ?? '');
        $fieldType = $_POST['field_type'] ?? 'text';
        $fieldSettings = json_encode($_POST['field_settings'] ?? []);
        $required = isset($_POST['required']) ? 1 : 0;
        $defaultValue = $_POST['default_value'] ?? null;
        $helpText = $_POST['help_text'] ?? '';
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $fieldId = $_POST['field_id'] ?? null;
        
        if ($fieldMachineName && $fieldLabel && $contentTypeId) {
            if ($fieldId) {
                // Update existing field
                $stmt = $pdo->prepare("UPDATE cms_custom_fields SET label=?, field_type=?, field_settings=?, required=?, default_value=?, help_text=?, display_order=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$fieldLabel, $fieldType, $fieldSettings, $required, $defaultValue, $helpText, $displayOrder, $fieldId]);
                $message = 'Field updated successfully';
            } else {
                // Create new field
                $stmt = $pdo->prepare("INSERT INTO cms_custom_fields (content_type_id, machine_name, label, field_type, field_settings, required, default_value, help_text, display_order) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$contentTypeId, $fieldMachineName, $fieldLabel, $fieldType, $fieldSettings, $required, $defaultValue, $helpText, $displayOrder]);
                $message = 'Field added successfully';
            }
        }
    }
    
    if (isset($_POST['delete_content_type'])) {
        $pdo->prepare("DELETE FROM cms_content_types WHERE id=?")->execute([$id]);
        header('Location: content-types.php');
        exit;
    }
    
    if (isset($_POST['delete_field'])) {
        $fieldId = $_POST['field_id'] ?? null;
        if ($fieldId) {
            $pdo->prepare("DELETE FROM cms_custom_fields WHERE id=?")->execute([$fieldId]);
            $message = 'Field deleted successfully';
        }
    }
}

// Get content types
$contentTypes = $pdo->query("SELECT ct.*, 
    (SELECT COUNT(*) FROM cms_custom_fields WHERE content_type_id = ct.id) as field_count
    FROM cms_content_types ct 
    ORDER BY ct.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get single content type for editing
$contentType = null;
$fields = [];
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_content_types WHERE id=?");
    $stmt->execute([$id]);
    $contentType = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contentType) {
        $fieldsStmt = $pdo->prepare("SELECT * FROM cms_custom_fields WHERE content_type_id=? ORDER BY display_order, id");
        $fieldsStmt->execute([$id]);
        $fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include __DIR__ . '/header.php';
?>

<style>
    .content-type-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .content-type-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #2563eb;
    }
    .content-type-info {
        flex: 1;
    }
    .content-type-icon {
        font-size: 2.5rem;
        margin-right: 1rem;
        display: inline-block;
    }
    .field-builder {
        background: #f8fafc;
        border: 2px dashed #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1.5rem;
    }
    .field-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .field-type-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>📦 Content Types Builder</h1>
        <p>Create custom content types with custom fields (Drupal-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add New Content Type</a>
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
        <!-- Content Types List -->
        <div style="margin-top: 2rem;">
            <?php if (empty($contentTypes)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No Content Types Yet</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Create your first custom content type to get started</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create Content Type</a>
                </div>
            <?php else: ?>
                <?php foreach ($contentTypes as $ct): ?>
                    <div class="content-type-card">
                        <div class="content-type-info">
                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                <span class="content-type-icon"><?php echo htmlspecialchars($ct['icon']); ?></span>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><?php echo htmlspecialchars($ct['label']); ?></h3>
                                    <p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.875rem;">
                                        <code><?php echo htmlspecialchars($ct['machine_name']); ?></code>
                                        <?php if ($ct['field_count'] > 0): ?>
                                            <span style="margin-left: 1rem;">• <?php echo $ct['field_count']; ?> field(s)</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php if ($ct['description']): ?>
                                <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($ct['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?action=edit&id=<?php echo $ct['id']; ?>" class="admin-btn admin-btn-outline">✏️ Edit</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this content type? This will also delete all associated fields and data.');">
                                <input type="hidden" name="delete_content_type" value="1">
                                <input type="hidden" name="id" value="<?php echo $ct['id']; ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Content Type Form -->
        <form method="post" style="margin-top: 2rem;">
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">📦</div>
                    <h3>Content Type Information</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Machine Name *</label>
                    <input type="text" name="machine_name" value="<?php echo htmlspecialchars($contentType['machine_name'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., portfolio_item, product, service"
                           <?php echo $contentType ? 'readonly' : ''; ?>>
                    <div class="admin-form-help">Unique identifier (lowercase, underscores only). Cannot be changed after creation.</div>
                </div>
                
                <div class="admin-form-group">
                    <label>Label *</label>
                    <input type="text" name="label" value="<?php echo htmlspecialchars($contentType['label'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., Portfolio Item">
                    <div class="admin-form-help">Human-readable name for this content type</div>
                </div>
                
                <div class="admin-form-group">
                    <label>Icon</label>
                    <input type="text" name="icon" value="<?php echo htmlspecialchars($contentType['icon'] ?? '📄'); ?>" 
                           class="large-text" placeholder="📄" maxlength="2">
                    <div class="admin-form-help">Emoji icon to represent this content type</div>
                </div>
                
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="large-text" placeholder="Describe what this content type is used for"><?php echo htmlspecialchars($contentType['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="admin-form-group">
                    <label>Base Table (Optional)</label>
                    <input type="text" name="base_table" value="<?php echo htmlspecialchars($contentType['base_table'] ?? ''); ?>" 
                           class="large-text" placeholder="e.g., cms_portfolio">
                    <div class="admin-form-help">If this content type uses an existing table (like Portfolio), specify it here</div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_content_type" class="admin-btn admin-btn-primary">💾 Save Content Type</button>
                    <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
        
        <?php if ($id && $contentType): ?>
            <!-- Fields Management -->
            <div class="editor-section" style="margin-top: 2rem;">
                <div class="editor-section-header">
                    <div class="icon">🔧</div>
                    <h3>Custom Fields</h3>
                </div>
                
                <div class="field-builder">
                    <h4 style="margin-top: 0; color: #1e293b;">Add New Field</h4>
                    <form method="post" id="field-form">
                        <input type="hidden" name="content_type_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="field_id" id="field_id" value="">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="admin-form-group">
                                <label>Field Machine Name *</label>
                                <input type="text" name="field_machine_name" id="field_machine_name" required 
                                       class="large-text" placeholder="e.g., price, description, image">
                            </div>
                            <div class="admin-form-group">
                                <label>Field Label *</label>
                                <input type="text" name="field_label" id="field_label" required 
                                       class="large-text" placeholder="e.g., Price, Description, Image">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="admin-form-group">
                                <label>Field Type *</label>
                                <select name="field_type" id="field_type" required class="large-text">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="number">Number</option>
                                    <option value="email">Email</option>
                                    <option value="url">URL</option>
                                    <option value="date">Date</option>
                                    <option value="datetime">Date & Time</option>
                                    <option value="boolean">Boolean (Yes/No)</option>
                                    <option value="select">Select (Dropdown)</option>
                                    <option value="multiselect">Multi-Select</option>
                                    <option value="image">Image</option>
                                    <option value="file">File</option>
                                    <option value="wysiwyg">WYSIWYG Editor</option>
                                    <option value="json">JSON</option>
                                </select>
                            </div>
                            <div class="admin-form-group">
                                <label>Display Order</label>
                                <input type="number" name="display_order" id="display_order" value="0" class="large-text">
                            </div>
                            <div class="admin-form-group">
                                <label>
                                    <input type="checkbox" name="required" id="required" value="1"> Required Field
                                </label>
                            </div>
                        </div>
                        
                        <div class="admin-form-group">
                            <label>Default Value</label>
                            <input type="text" name="default_value" id="default_value" class="large-text" placeholder="Optional default value">
                        </div>
                        
                        <div class="admin-form-group">
                            <label>Help Text</label>
                            <textarea name="help_text" id="help_text" rows="2" class="large-text" placeholder="Help text to show users"></textarea>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <button type="submit" name="save_field" class="admin-btn admin-btn-primary">➕ Add Field</button>
                            <button type="button" onclick="resetFieldForm()" class="admin-btn admin-btn-outline">Reset</button>
                        </div>
                    </form>
                </div>
                
                <!-- Existing Fields List -->
                <?php if (!empty($fields)): ?>
                    <div style="margin-top: 2rem;">
                        <h4 style="color: #1e293b; margin-bottom: 1rem;">Existing Fields (<?php echo count($fields); ?>)</h4>
                        <?php foreach ($fields as $field): ?>
                            <div class="field-item">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                                        <strong><?php echo htmlspecialchars($field['label']); ?></strong>
                                        <span class="field-type-badge"><?php echo htmlspecialchars($field['field_type']); ?></span>
                                        <?php if ($field['required']): ?>
                                            <span style="color: #ef4444; font-size: 0.75rem;">* Required</span>
                                        <?php endif; ?>
                                    </div>
                                    <code style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($field['machine_name']); ?></code>
                                    <?php if ($field['help_text']): ?>
                                        <p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.875rem;"><?php echo htmlspecialchars($field['help_text']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button type="button" onclick="editField(<?php echo htmlspecialchars(json_encode($field)); ?>)" class="admin-btn admin-btn-outline">✏️ Edit</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete this field?');">
                                        <input type="hidden" name="delete_field" value="1">
                                        <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                        <button type="submit" class="admin-btn admin-btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function editField(field) {
    document.getElementById('field_id').value = field.id;
    document.getElementById('field_machine_name').value = field.machine_name;
    document.getElementById('field_machine_name').readOnly = true;
    document.getElementById('field_label').value = field.label;
    document.getElementById('field_type').value = field.field_type;
    document.getElementById('display_order').value = field.display_order;
    document.getElementById('required').checked = field.required == 1;
    document.getElementById('default_value').value = field.default_value || '';
    document.getElementById('help_text').value = field.help_text || '';
    
    // Scroll to form
    document.getElementById('field-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('field_label').focus();
}

function resetFieldForm() {
    document.getElementById('field-form').reset();
    document.getElementById('field_id').value = '';
    document.getElementById('field_machine_name').readOnly = false;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>

