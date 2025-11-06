<?php
/**
 * Maintenance Types Management (lightweight)
 */

$pdo = getDBConnection();

// Handle add/delete simple POST
if (!empty($_POST['action_type'])) {
    try {
        if ($_POST['action_type'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO maintenance_types (type_name, description) VALUES (?, ?)");
            $stmt->execute([trim($_POST['type_name']), trim($_POST['description'] ?? '')]);
        } elseif ($_POST['action_type'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM maintenance_types WHERE id = ?");
            $stmt->execute([intval($_POST['id'])]);
        }
        header('Location: maintenance.php?action=types');
        exit;
    } catch (PDOException $e) {
        $formError = $e->getMessage();
    }
}

try {
    $stmt = $pdo->query("SELECT id, type_name, description, created_at FROM maintenance_types ORDER BY type_name");
    $types = $stmt->fetchAll();
} catch (PDOException $e) {
    $types = [];
    $tableMissing = true;
}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">⚙️ Maintenance Types</h2>
        <button class="btn btn-primary" onclick="document.getElementById('newType').style.display='block'">➕ New Type</button>
    </div>
    <?php if (!empty($formError)): ?>
        <div style="margin-top: 10px; color: var(--danger);">Error: <?php echo e($formError); ?></div>
    <?php endif; ?>
    <?php if (!empty($tableMissing)): ?>
        <div style="margin-top: 12px; padding: 15px; border:1px solid #ff9800; border-radius: 6px; background: #fff3cd;">
            <strong>⚠️ Maintenance types table not found.</strong>
            <p style="margin: 10px 0 0 0; color: #856404;">
                Please run the <code>maintenance_assets_inventory_migration.sql</code> migration to create the required tables.
            </p>
            <a href="database-migrations.php" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-database"></i> Go to Database Migrations →
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($tableMissing)): ?>
<div id="newType" class="dashboard-card" style="display:none;">
    <form method="post" style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <input type="hidden" name="action_type" value="add">
        <div>
            <label class="form-label">Type Name</label>
            <input type="text" name="type_name" class="form-control" required>
        </div>
        <div style="grid-column: 1 / -1;">
            <label class="form-label">Description</label>
            <textarea name="description" rows="2" class="form-control"></textarea>
        </div>
        <div style="grid-column: 1 / -1; display:flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="this.closest('#newType').style.display='none'">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    
</div>

<div class="dashboard-card">
    <?php if (empty($types)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 40px;">No types yet.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
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
                    <?php foreach ($types as $t): ?>
                        <tr>
                            <td style="color: var(--text); font-weight:600; "><?php echo e($t['type_name']); ?></td>
                            <td style="color: var(--text); "><?php echo e($t['description']); ?></td>
                            <td style="color: var(--secondary); font-size:12px; "><?php echo !empty($t['created_at']) ? formatDate($t['created_at']) : '—'; ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this type?');">
                                    <input type="hidden" name="action_type" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
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
<?php endif; ?>


