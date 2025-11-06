<?php
/**
 * Asset Categories Management (lightweight)
 */

$pdo = getDBConnection();

// Handle add/update/delete (simple, non-AJAX)
$actionType = $_POST['action_type'] ?? '';
if ($actionType) {
    try {
        if ($actionType === 'add') {
            $stmt = $pdo->prepare("INSERT INTO asset_categories (category_name, description) VALUES (?, ?)");
            $stmt->execute([
                trim($_POST['category_name'] ?? ''),
                trim($_POST['description'] ?? ''),
            ]);
        } elseif ($actionType === 'edit') {
            $stmt = $pdo->prepare("UPDATE asset_categories SET category_name = ?, description = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['category_name'] ?? ''),
                trim($_POST['description'] ?? ''),
                intval($_POST['id'] ?? 0)
            ]);
        } elseif ($actionType === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM asset_categories WHERE id = ?");
            $stmt->execute([intval($_POST['id'] ?? 0)]);
        }
        // Redirect back to avoid resubmission
        header('Location: assets.php?action=categories');
        exit;
    } catch (PDOException $e) {
        // Fall through and show message
        $formError = $e->getMessage();
    }
}

// Load categories
try {
    $stmt = $pdo->query("SELECT id, category_name, description, created_at FROM asset_categories ORDER BY category_name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $tableMissing = true;
}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">📁 Asset Categories</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addCategoryForm').style.display='block'">➕ New Category</button>
    </div>
</div>

<?php if (!empty($tableMissing)): ?>
    <div class="dashboard-card" style="border-left: 4px solid var(--warning);">
        <p style="margin:0; color: var(--text);">
            ⚠️ The <code>asset_categories</code> table was not found. Please run the maintenance/assets/inventory migration.
        </p>
    </div>
<?php else: ?>
    <!-- Inline Add Form (hidden by default) -->
    <div id="addCategoryForm" class="dashboard-card" style="display:none;">
        <form method="post" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <input type="hidden" name="action_type" value="add">
            <div>
                <label class="form-label">Category Name</label>
                <input type="text" name="category_name" class="form-control" required>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Description</label>
                <textarea name="description" rows="2" class="form-control"></textarea>
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="this.closest('#addCategoryForm').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>

    <div class="dashboard-card">
        <?php if (empty($categories)): ?>
            <p style="text-align:center; color: var(--secondary); padding: 40px;">No categories yet. Add your first one.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td style="color: var(--text); font-weight: 600;"><?php echo e($cat['category_name']); ?></td>
                                <td style="color: var(--text);"><?php echo e($cat['description']); ?></td>
                                <td style="color: var(--secondary); font-size: 12px;">
                                    <?php echo !empty($cat['created_at']) ? formatDate($cat['created_at']) : '—'; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo e(addslashes($cat['category_name'])); ?>', '<?php echo e(addslashes($cat['description'])); ?>')">Edit</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                        <input type="hidden" name="action_type" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Modal (very lightweight) -->
    <div id="editCategoryModal" class="dashboard-card" style="display:none; position: fixed; left: 50%; top: 20%; transform: translateX(-50%); max-width: 700px; width: 90%; z-index: 1000;">
        <form method="post" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <input type="hidden" name="action_type" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div>
                <label class="form-label">Category Name</label>
                <input type="text" name="category_name" id="edit_name" class="form-control" required>
            </div>
            <div style="grid-column: 1 / -1;">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_desc" rows="2" class="form-control"></textarea>
            </div>
            <div style="grid-column: 1 / -1; display:flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editCategoryModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
    function editCategory(id, name, desc) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_desc').value = desc;
        document.getElementById('editCategoryModal').style.display = 'block';
    }
    </script>
<?php endif; ?>


