<?php
/**
 * CMS Admin - Module Positions (Joomla-inspired)
 * Assign widgets to specific template positions
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
$positionId = $_GET['position_id'] ?? null;
$message = null;
$messageType = 'success';

// Ensure tables exist
require_once dirname(__DIR__) . '/includes/ensure-advanced-tables.php';
if (!ensureAdvancedTablesExist($pdo)) {
    die("❌ Error: Could not create required database tables. Please run: php database/create_advanced_features_tables.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_position'])) {
        $positionName = trim($_POST['position_name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $description = $_POST['description'] ?? '';
        $template = $_POST['template'] ?? null;
        $displayOrder = intval($_POST['display_order'] ?? 0);
        
        if ($positionName && $label) {
            if ($positionId) {
                $stmt = $pdo->prepare("UPDATE cms_module_positions SET label=?, description=?, template=?, display_order=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$label, $description, $template, $displayOrder, $positionId]);
                $message = 'Position updated successfully';
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM cms_module_positions WHERE position_name=? LIMIT 1");
                $checkStmt->execute([$positionName]);
                if ($checkStmt->fetch()) {
                    $message = 'Position with this name already exists';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cms_module_positions (position_name, label, description, template, display_order) VALUES (?,?,?,?,?)");
                    $stmt->execute([$positionName, $label, $description, $template, $displayOrder]);
                    $message = 'Position created successfully';
                }
            }
        }
    }
    
    if (isset($_POST['save_assignment'])) {
        $positionId = $_POST['position_id'] ?? null;
        $widgetId = $_POST['widget_id'] ?? null;
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $conditions = json_encode($_POST['conditions'] ?? []);
        $assignmentId = $_POST['assignment_id'] ?? null;
        
        if ($positionId && $widgetId) {
            if ($assignmentId) {
                $stmt = $pdo->prepare("UPDATE cms_module_assignments SET widget_id=?, display_order=?, conditions=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$widgetId, $displayOrder, $conditions, $assignmentId]);
                $message = 'Assignment updated successfully';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_module_assignments (widget_id, position_id, display_order, conditions) VALUES (?,?,?,?)");
                $stmt->execute([$widgetId, $positionId, $displayOrder, $conditions]);
                $message = 'Widget assigned successfully';
            }
        }
    }
    
    if (isset($_POST['delete_position'])) {
        $pdo->prepare("DELETE FROM cms_module_positions WHERE id=?")->execute([$positionId]);
        header('Location: module-positions.php');
        exit;
    }
    
    if (isset($_POST['delete_assignment'])) {
        $assignmentId = $_POST['assignment_id'] ?? null;
        if ($assignmentId) {
            $pdo->prepare("DELETE FROM cms_module_assignments WHERE id=?")->execute([$assignmentId]);
            $message = 'Assignment removed successfully';
        }
    }
}

// Get positions
$positions = $pdo->query("SELECT p.*, 
    (SELECT COUNT(*) FROM cms_module_assignments WHERE position_id = p.id) as assignment_count
    FROM cms_module_positions p 
    ORDER BY p.display_order, p.label")->fetchAll(PDO::FETCH_ASSOC);

// Get widgets
$widgets = $pdo->query("SELECT * FROM cms_widgets ORDER BY widget_title")->fetchAll(PDO::FETCH_ASSOC);

// Get single position for editing
$position = null;
$assignments = [];
$assignment = null; // For editing single assignment

if ($positionId && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_module_positions WHERE id=?");
    $stmt->execute([$positionId]);
    $position = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($position) {
        $assignmentsStmt = $pdo->prepare("SELECT a.*, w.widget_title 
            FROM cms_module_assignments a 
            LEFT JOIN cms_widgets w ON a.widget_id = w.id
            WHERE a.position_id = ? 
            ORDER BY a.display_order");
        $assignmentsStmt->execute([$positionId]);
        $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get single assignment for editing
if (isset($_GET['assignment_id']) && $action === 'edit_assignment') {
    $assignmentId = intval($_GET['assignment_id']);
    $stmt = $pdo->prepare("SELECT a.*, w.widget_title 
        FROM cms_module_assignments a 
        LEFT JOIN cms_widgets w ON a.widget_id = w.id
        WHERE a.id = ?");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($assignment) {
        $positionId = $assignment['position_id'];
        // Also get the position
        $posStmt = $pdo->prepare("SELECT * FROM cms_module_positions WHERE id=?");
        $posStmt->execute([$positionId]);
        $position = $posStmt->fetch(PDO::FETCH_ASSOC);
    }
}

include __DIR__ . '/header.php';
?>

<style>
    .positions-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 1.5rem;
        margin-top: 2rem;
    }
    .position-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .position-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .position-card-header {
        margin-bottom: 1rem;
    }
    .position-card-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }
    .assignment-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    @media (max-width: 1400px) {
        .positions-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    @media (max-width: 1024px) {
        .positions-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 768px) {
        .positions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 480px) {
        .positions-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>📍 Module Positions</h1>
        <p>Assign widgets to specific template positions (Joomla-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add Position</a>
            <?php elseif ($action === 'edit' && $positionId): ?>
                <a href="?action=assign&position_id=<?php echo $positionId; ?>" class="admin-btn admin-btn-primary">➕ Assign Widget</a>
                <a href="?" class="admin-btn admin-btn-outline">← Back</a>
            <?php else: ?>
                <a href="?" class="admin-btn admin-btn-outline">← Back</a>
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
            <?php if (empty($positions)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📍</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No Positions Yet</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Create module positions to organize widgets</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create Position</a>
                </div>
            <?php else: ?>
                <div class="positions-grid">
                    <?php foreach ($positions as $pos): ?>
                        <div class="position-card">
                            <div class="position-card-header">
                                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: #1e293b; font-weight: 600;">
                                    <?php echo htmlspecialchars($pos['label']); ?>
                                </h3>
                                <p style="margin: 0 0 0.5rem 0; color: #64748b; font-size: 0.75rem; font-family: monospace;">
                                    <?php echo htmlspecialchars($pos['position_name']); ?>
                                </p>
                                <p style="margin: 0 0 0.5rem 0; color: #64748b; font-size: 0.875rem;">
                                    <strong><?php echo $pos['assignment_count']; ?></strong> widget(s)
                                </p>
                                <?php if ($pos['template']): ?>
                                    <p style="margin: 0 0 0.5rem 0; color: #64748b; font-size: 0.875rem;">
                                        Template: <strong><?php echo htmlspecialchars($pos['template']); ?></strong>
                                    </p>
                                <?php else: ?>
                                    <p style="margin: 0 0 0.5rem 0; color: #64748b; font-size: 0.875rem;">
                                        All Templates
                                    </p>
                                <?php endif; ?>
                                <?php if ($pos['description']): ?>
                                    <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.85rem; line-height: 1.4;">
                                        <?php echo htmlspecialchars(mb_substr($pos['description'], 0, 80)) . (mb_strlen($pos['description']) > 80 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="position-card-actions">
                                <a href="?action=edit&position_id=<?php echo $pos['id']; ?>" class="admin-btn admin-btn-outline" style="flex: 1; text-align: center; padding: 0.5rem;">✏️ Edit</a>
                                <form method="post" style="display: inline; flex: 0;" onsubmit="return confirm('Delete this position?');">
                                    <input type="hidden" name="delete_position" value="1">
                                    <input type="hidden" name="position_id" value="<?php echo $pos['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger" style="padding: 0.5rem 0.75rem;">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'add' || ($action === 'edit' && $positionId)): ?>
        <!-- Position Form -->
        <form method="post" style="margin-top: 2rem;">
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">📍</div>
                    <h3>Position Information</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Position Name *</label>
                    <input type="text" name="position_name" value="<?php echo htmlspecialchars($position['position_name'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., sidebar-left, footer-column-1"
                           <?php echo $position ? 'readonly' : ''; ?>>
                </div>
                
                <div class="admin-form-group">
                    <label>Label *</label>
                    <input type="text" name="label" value="<?php echo htmlspecialchars($position['label'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., Left Sidebar">
                </div>
                
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="large-text"><?php echo htmlspecialchars($position['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="admin-form-group">
                    <label>Template (Optional)</label>
                    <input type="text" name="template" value="<?php echo htmlspecialchars($position['template'] ?? ''); ?>" 
                           class="large-text" placeholder="e.g., default, custom (leave empty for all templates)">
                </div>
                
                <div class="admin-form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="<?php echo htmlspecialchars($position['display_order'] ?? 0); ?>" 
                           class="large-text">
                </div>
                
                <?php if ($position): ?>
                    <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_position" class="admin-btn admin-btn-primary">💾 Save Position</button>
                    <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
        
        <?php if ($positionId && $position): ?>
            <!-- Assignments List -->
            <div class="editor-section" style="margin-top: 2rem;">
                <div class="editor-section-header">
                    <div class="icon">📦</div>
                    <h3>Assigned Widgets (<?php echo count($assignments); ?>)</h3>
                </div>
                
                <?php if (empty($assignments)): ?>
                    <p style="color: #64748b; text-align: center; padding: 2rem;">No widgets assigned yet. <a href="?action=assign&position_id=<?php echo $positionId; ?>">Assign first widget</a></p>
                <?php else: ?>
                    <?php foreach ($assignments as $assign): ?>
                        <div class="assignment-item">
                            <div>
                                <strong><?php echo htmlspecialchars($assign['widget_title'] ?? 'Unknown Widget'); ?></strong>
                                <p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.875rem;">
                                    Order: <?php echo $assign['display_order']; ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="?action=edit_assignment&assignment_id=<?php echo $assign['id']; ?>&position_id=<?php echo $positionId; ?>" class="admin-btn admin-btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">✏️</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Remove this assignment?');">
                                    <input type="hidden" name="delete_assignment" value="1">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assign['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'assign' || $action === 'edit_assignment'): ?>
        <!-- Assignment Form -->
        <form method="post" style="margin-top: 2rem;">
            <input type="hidden" name="position_id" value="<?php echo $positionId; ?>">
            
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">📦</div>
                    <h3>Assign Widget to Position</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Widget *</label>
                    <select name="widget_id" required class="large-text">
                        <option value="">-- Select Widget --</option>
                        <?php foreach ($widgets as $w): ?>
                            <option value="<?php echo $w['id']; ?>" <?php echo ($assignment && $assignment['widget_id'] == $w['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($w['widget_title'] ?? 'Widget #' . $w['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="admin-form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="<?php echo htmlspecialchars($assignment['display_order'] ?? 0); ?>" 
                           class="large-text">
                </div>
                
                <?php if ($assignment && isset($assignment['id'])): ?>
                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_assignment" class="admin-btn admin-btn-primary">💾 Save Assignment</button>
                    <a href="?action=edit&position_id=<?php echo $positionId; ?>" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

