<?php
/**
 * CMS Admin - Advanced Taxonomy (Drupal-inspired)
 * Multiple vocabularies with hierarchical terms
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
$vocabId = $_GET['vocab_id'] ?? null;
$termId = $_GET['term_id'] ?? null;
$message = null;
$messageType = 'success';

// Ensure tables exist
require_once dirname(__DIR__) . '/includes/ensure-advanced-tables.php';
if (!ensureAdvancedTablesExist($pdo)) {
    die("❌ Error: Could not create required database tables. Please run: php database/create_advanced_features_tables.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_vocabulary'])) {
        $machineName = trim($_POST['machine_name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $description = $_POST['description'] ?? '';
        $hierarchical = isset($_POST['hierarchical']) ? 1 : 0;
        $multiple = isset($_POST['multiple']) ? 1 : 0;
        $required = isset($_POST['required']) ? 1 : 0;
        
        if (empty($machineName) && !empty($label)) {
            $machineName = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $label));
        }
        
        if ($machineName && $label) {
            if ($vocabId) {
                $stmt = $pdo->prepare("UPDATE cms_vocabularies SET label=?, description=?, hierarchical=?, multiple=?, required=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$label, $description, $hierarchical, $multiple, $required, $vocabId]);
                $message = 'Vocabulary updated successfully';
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM cms_vocabularies WHERE machine_name=? LIMIT 1");
                $checkStmt->execute([$machineName]);
                if ($checkStmt->fetch()) {
                    $message = 'Vocabulary with this machine name already exists';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cms_vocabularies (machine_name, label, description, hierarchical, multiple, required) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$machineName, $label, $description, $hierarchical, $multiple, $required]);
                    $vocabId = $pdo->lastInsertId();
                    $message = 'Vocabulary created successfully';
                }
            }
        }
    }
    
    if (isset($_POST['save_term'])) {
        $vocabId = $_POST['vocabulary_id'] ?? $vocabId;
        $parentId = $_POST['parent_id'] ?? null;
        $machineName = trim($_POST['term_machine_name'] ?? '');
        $label = trim($_POST['term_label'] ?? '');
        $description = $_POST['term_description'] ?? '';
        $slug = trim($_POST['slug'] ?? '');
        $weight = intval($_POST['weight'] ?? 0);
        
        if (empty($slug) && !empty($label)) {
            $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $label));
        }
        if (empty($machineName) && !empty($label)) {
            $machineName = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $label));
        }
        
        if ($machineName && $label && $vocabId) {
            if ($termId) {
                $stmt = $pdo->prepare("UPDATE cms_terms SET parent_id=?, label=?, description=?, slug=?, weight=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$parentId, $label, $description, $slug, $weight, $termId]);
                $message = 'Term updated successfully';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_terms (vocabulary_id, parent_id, machine_name, label, description, slug, weight) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$vocabId, $parentId, $machineName, $label, $description, $slug, $weight]);
                $message = 'Term added successfully';
            }
        }
    }
    
    if (isset($_POST['delete_vocabulary'])) {
        $pdo->prepare("DELETE FROM cms_vocabularies WHERE id=?")->execute([$vocabId]);
        header('Location: taxonomy.php');
        exit;
    }
    
    if (isset($_POST['delete_term'])) {
        $pdo->prepare("DELETE FROM cms_terms WHERE id=?")->execute([$termId]);
        $message = 'Term deleted successfully';
    }
}

// Get vocabularies
$vocabularies = $pdo->query("SELECT v.*, 
    (SELECT COUNT(*) FROM cms_terms WHERE vocabulary_id = v.id) as term_count
    FROM cms_vocabularies v 
    ORDER BY v.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get single vocabulary for editing
$vocabulary = null;
$terms = [];
if ($vocabId && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_vocabularies WHERE id=?");
    $stmt->execute([$vocabId]);
    $vocabulary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vocabulary) {
        $termsStmt = $pdo->prepare("SELECT * FROM cms_terms WHERE vocabulary_id=? ORDER BY weight, label");
        $termsStmt->execute([$vocabId]);
        $terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get single term for editing
$term = null;
if ($termId && $action === 'edit_term') {
    $stmt = $pdo->prepare("SELECT * FROM cms_terms WHERE id=?");
    $stmt->execute([$termId]);
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($term) {
        $vocabId = $term['vocabulary_id'];
    }
}

include __DIR__ . '/header.php';
?>

<style>
    .vocab-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    .term-tree {
        margin-left: 2rem;
    }
    .term-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>🏷️ Advanced Taxonomy</h1>
        <p>Manage vocabularies and hierarchical terms (Drupal-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add Vocabulary</a>
            <?php elseif ($action === 'edit' && $vocabId): ?>
                <a href="?action=add_term&vocab_id=<?php echo $vocabId; ?>" class="admin-btn admin-btn-primary">➕ Add Term</a>
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
            <?php if (empty($vocabularies)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏷️</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No Vocabularies Yet</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Create vocabularies to organize your content</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Create Vocabulary</a>
                </div>
            <?php else: ?>
                <?php foreach ($vocabularies as $vocab): ?>
                    <div class="vocab-card">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; color: #1e293b;">
                                    <?php echo htmlspecialchars($vocab['label']); ?>
                                </h3>
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <code><?php echo htmlspecialchars($vocab['machine_name']); ?></code>
                                    <span style="margin-left: 1rem;">• <?php echo $vocab['term_count']; ?> term(s)</span>
                                    <?php if ($vocab['hierarchical']): ?>
                                        <span style="margin-left: 1rem;">• Hierarchical</span>
                                    <?php endif; ?>
                                </p>
                                <?php if ($vocab['description']): ?>
                                    <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($vocab['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="?action=edit&vocab_id=<?php echo $vocab['id']; ?>" class="admin-btn admin-btn-outline">✏️ Edit</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this vocabulary and all its terms?');">
                                    <input type="hidden" name="delete_vocabulary" value="1">
                                    <input type="hidden" name="vocab_id" value="<?php echo $vocab['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger">🗑️</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'add' || ($action === 'edit' && $vocabId)): ?>
        <!-- Vocabulary Form -->
        <form method="post" style="margin-top: 2rem;">
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">🏷️</div>
                    <h3>Vocabulary Information</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Machine Name *</label>
                    <input type="text" name="machine_name" value="<?php echo htmlspecialchars($vocabulary['machine_name'] ?? ''); ?>" 
                           required class="large-text" <?php echo $vocabulary ? 'readonly' : ''; ?>>
                </div>
                
                <div class="admin-form-group">
                    <label>Label *</label>
                    <input type="text" name="label" value="<?php echo htmlspecialchars($vocabulary['label'] ?? ''); ?>" 
                           required class="large-text">
                </div>
                
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="large-text"><?php echo htmlspecialchars($vocabulary['description'] ?? ''); ?></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div class="admin-form-group">
                        <label>
                            <input type="checkbox" name="hierarchical" value="1" <?php echo ($vocabulary['hierarchical'] ?? 0) ? 'checked' : ''; ?>>
                            Hierarchical (Parent-Child)
                        </label>
                    </div>
                    <div class="admin-form-group">
                        <label>
                            <input type="checkbox" name="multiple" value="1" <?php echo ($vocabulary['multiple'] ?? 1) ? 'checked' : ''; ?>>
                            Allow Multiple Terms
                        </label>
                    </div>
                    <div class="admin-form-group">
                        <label>
                            <input type="checkbox" name="required" value="1" <?php echo ($vocabulary['required'] ?? 0) ? 'checked' : ''; ?>>
                            Required
                        </label>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_vocabulary" class="admin-btn admin-btn-primary">💾 Save Vocabulary</button>
                    <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
        
        <?php if ($vocabId && $vocabulary): ?>
            <!-- Terms List -->
            <div class="editor-section" style="margin-top: 2rem;">
                <div class="editor-section-header">
                    <div class="icon">📋</div>
                    <h3>Terms (<?php echo count($terms); ?>)</h3>
                </div>
                
                <?php if (empty($terms)): ?>
                    <p style="color: #64748b; text-align: center; padding: 2rem;">No terms yet. <a href="?action=add_term&vocab_id=<?php echo $vocabId; ?>">Add first term</a></p>
                <?php else: ?>
                    <?php
                    // Build term tree
                    function buildTermTree($terms, $parentId = null) {
                        $tree = [];
                        foreach ($terms as $term) {
                            if ($term['parent_id'] == $parentId) {
                                $term['children'] = buildTermTree($terms, $term['id']);
                                $tree[] = $term;
                            }
                        }
                        return $tree;
                    }
                    $termTree = buildTermTree($terms);
                    ?>
                    
                    <?php
                    function renderTermTree($terms, $level = 0) {
                        global $vocabId;
                        foreach ($terms as $term) {
                            echo '<div class="term-item" style="margin-left: ' . ($level * 2) . 'rem;">';
                            echo '<div>';
                            echo '<strong>' . htmlspecialchars($term['label']) . '</strong>';
                            echo ' <code style="font-size: 0.75rem; color: #64748b;">' . htmlspecialchars($term['slug']) . '</code>';
                            if ($term['description']) {
                                echo '<p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.875rem;">' . htmlspecialchars($term['description']) . '</p>';
                            }
                            echo '</div>';
                            echo '<div style="display: flex; gap: 0.5rem;">';
                            echo '<a href="?action=edit_term&term_id=' . $term['id'] . '&vocab_id=' . $vocabId . '" class="admin-btn admin-btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">✏️</a>';
                            echo '<form method="post" style="display: inline;" onsubmit="return confirm(\'Delete this term?\');">';
                            echo '<input type="hidden" name="delete_term" value="1">';
                            echo '<input type="hidden" name="term_id" value="' . $term['id'] . '">';
                            echo '<button type="submit" class="admin-btn admin-btn-danger" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">🗑️</button>';
                            echo '</form>';
                            echo '</div>';
                            echo '</div>';
                            if (!empty($term['children'])) {
                                renderTermTree($term['children'], $level + 1);
                            }
                        }
                    }
                    renderTermTree($termTree);
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'add_term' || ($action === 'edit_term' && $termId)): ?>
        <!-- Term Form -->
        <form method="post" style="margin-top: 2rem;">
            <input type="hidden" name="vocabulary_id" value="<?php echo $vocabId; ?>">
            
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">📋</div>
                    <h3>Term Information</h3>
                </div>
                
                <?php if ($vocabulary && $vocabulary['hierarchical']): ?>
                    <div class="admin-form-group">
                        <label>Parent Term</label>
                        <select name="parent_id" class="large-text">
                            <option value="">-- No Parent (Top Level) --</option>
                            <?php
                            $allTerms = $pdo->prepare("SELECT * FROM cms_terms WHERE vocabulary_id=? AND id != ? ORDER BY label");
                            $allTerms->execute([$vocabId, $termId ?? 0]);
                            foreach ($allTerms->fetchAll(PDO::FETCH_ASSOC) as $t):
                            ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($term['parent_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="admin-form-group">
                    <label>Machine Name *</label>
                    <input type="text" name="term_machine_name" value="<?php echo htmlspecialchars($term['machine_name'] ?? ''); ?>" 
                           required class="large-text" <?php echo $term ? 'readonly' : ''; ?>>
                </div>
                
                <div class="admin-form-group">
                    <label>Label *</label>
                    <input type="text" name="term_label" value="<?php echo htmlspecialchars($term['label'] ?? ''); ?>" 
                           required class="large-text">
                </div>
                
                <div class="admin-form-group">
                    <label>Slug *</label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($term['slug'] ?? ''); ?>" 
                           required class="large-text">
                </div>
                
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="term_description" rows="3" class="large-text"><?php echo htmlspecialchars($term['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="admin-form-group">
                    <label>Weight (Display Order)</label>
                    <input type="number" name="weight" value="<?php echo htmlspecialchars($term['weight'] ?? 0); ?>" 
                           class="large-text">
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_term" class="admin-btn admin-btn-primary">💾 Save Term</button>
                    <a href="?action=edit&vocab_id=<?php echo $vocabId; ?>" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

