<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

/**
 * Inventory Transactions View
 * Display and manage inventory transactions
 */
$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';
$currentUserId = $_SESSION['user_id'];

// Filters
$typeFilter = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$materialFilter = intval($_GET['material_id'] ?? 0);

// Build query
$where = [];
$params = [];

if (!empty($typeFilter)) {
    $where[] = "it.transaction_type = ?";
    $params[] = $typeFilter;
}

if (!empty($dateFrom)) {
    $where[] = "DATE(it.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "DATE(it.created_at) <= ?";
    $params[] = $dateTo;
}

if ($materialFilter > 0) {
    $where[] = "it.material_id = ?";
    $params[] = $materialFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get transactions
try {
    $sql = "
        SELECT it.*, m.material_name, m.material_type, u.full_name as created_by_name
        FROM inventory_transactions it
        LEFT JOIN materials m ON it.material_id = m.id
        LEFT JOIN users u ON it.created_by = u.id
        $whereClause
        ORDER BY it.created_at DESC
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}

// Get materials for filter
try {
    $stmt = $pdo->query("SELECT id, material_name, material_type FROM materials WHERE is_trackable = 1 ORDER BY material_name");
    $materials = $stmt->fetchAll();
} catch (PDOException $e) {
    try {
        // Fallback to materials_inventory
        $stmt = $pdo->query("SELECT DISTINCT material_type FROM materials_inventory");
        $materials = $stmt->fetchAll();
    } catch (PDOException $e2) {
        $materials = [];
    }
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0; color: var(--text);">💳 Inventory Transactions</h2>
    <a href="?action=transactions&add=1" class="btn btn-primary">➕ New Transaction</a>
</div>

<!-- Filters -->
<div class="dashboard-card" style="margin-bottom: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
        <input type="hidden" name="action" value="transactions">
        <div>
            <label class="form-label">Transaction Type</label>
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="purchase" <?php echo $typeFilter === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                <option value="sale" <?php echo $typeFilter === 'sale' ? 'selected' : ''; ?>>Sale</option>
                <option value="usage" <?php echo $typeFilter === 'usage' ? 'selected' : ''; ?>>Usage</option>
                <option value="adjustment" <?php echo $typeFilter === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                <option value="transfer" <?php echo $typeFilter === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                <option value="return" <?php echo $typeFilter === 'return' ? 'selected' : ''; ?>>Return</option>
            </select>
        </div>
        <div>
            <label class="form-label">Material</label>
            <select name="material_id" class="form-control">
                <option value="">All Materials</option>
                <?php foreach ($materials as $material): ?>
                    <option value="<?php echo $material['id'] ?? 0; ?>" <?php echo $materialFilter === ($material['id'] ?? 0) ? 'selected' : ''; ?>>
                        <?php echo e($material['material_name'] ?? ucfirst(str_replace('_', ' ', $material['material_type'] ?? 'Unknown'))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
        </div>
        <div>
            <label class="form-label">Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="?action=transactions" class="btn btn-outline">Clear</a>
        </div>
    </form>
</div>

<!-- Transactions List -->
<div class="dashboard-card">
    <?php if (empty($transactions)): ?>
        <p style="text-align: center; padding: 40px; color: var(--secondary);">
            No transactions found. <a href="?action=transactions&add=1" style="color: var(--primary);">Record your first transaction</a>
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Reference</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $trans): ?>
                        <?php
                        $typeColors = [
                            'purchase' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'fg' => '#10b981'],
                            'sale' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'fg' => '#3b82f6'],
                            'usage' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'fg' => '#f59e0b'],
                            'adjustment' => ['bg' => 'rgba(139, 92, 246, 0.1)', 'fg' => '#8b5cf6'],
                            'transfer' => ['bg' => 'rgba(236, 72, 153, 0.1)', 'fg' => '#ec4899'],
                            'return' => ['bg' => 'rgba(14, 165, 233, 0.1)', 'fg' => '#0ea5e9'],
                            'damage' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'fg' => '#ef4444'],
                            'expiry' => ['bg' => 'rgba(100, 116, 139, 0.1)', 'fg' => '#64748b']
                        ];
                        $typeStyle = $typeColors[$trans['transaction_type']] ?? ['bg' => 'rgba(100, 116, 139, 0.1)', 'fg' => '#64748b'];
                        ?>
                        <tr>
                            <td style="color: var(--text);">
                                <?php echo date('M j, Y', strtotime($trans['created_at'])); ?><br>
                                <small style="color: var(--secondary);"><?php echo date('g:i A', strtotime($trans['created_at'])); ?></small>
                            </td>
                            <td style="color: var(--text);">
                                <strong><?php echo e($trans['material_name'] ?? ucfirst(str_replace('_', ' ', $trans['material_type'] ?? 'Unknown'))); ?></strong>
                            </td>
                            <td>
                                <span style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 11px;
                                    font-weight: 600;
                                    background: <?php echo $typeStyle['bg']; ?>;
                                    color: <?php echo $typeStyle['fg']; ?>;
                                ">
                                    <?php echo ucfirst($trans['transaction_type']); ?>
                                </span>
                            </td>
                            <td style="color: var(--text); font-weight: 600;">
                                <?php echo $trans['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($trans['quantity'], 2); ?>
                            </td>
                            <td style="color: var(--text);">
                                <?php echo formatCurrency($trans['unit_cost'] ?? 0); ?>
                            </td>
                            <td>
                                <strong style="color: var(--text);">
                                    <?php echo formatCurrency($trans['total_cost'] ?? 0); ?>
                                </strong>
                            </td>
                            <td style="color: var(--secondary); font-size: 12px;">
                                <?php if ($trans['reference_type'] && $trans['reference_id']): ?>
                                    <?php echo ucfirst($trans['reference_type']); ?> #<?php echo $trans['reference_id']; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text);">
                                <?php echo e($trans['created_by_name'] ?? 'System'); ?>
                            </td>
                            <td>
                                <a href="?action=transactions&view=<?php echo $trans['id']; ?>" class="btn btn-sm btn-outline">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

