<?php
/**
 * Material Store Dashboard
 * View transactions, analytics, and manage Material Store inventory
 */
$page_title = 'Material Store Dashboard';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/pos/MaterialStoreService.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

$pdo = getDBConnection();
$materialStoreService = new MaterialStoreService($pdo);

// Get filters
$materialType = $_GET['material_type'] ?? '';
$transactionType = $_GET['transaction_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get data
$inventory = $materialStoreService->getStoreInventory();
$lowStockAlerts = $materialStoreService->getLowStockAlerts(20.0);
$transactions = $materialStoreService->getTransactions([
    'material_type' => $materialType,
    'transaction_type' => $transactionType,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);
$analytics = $materialStoreService->getUsageAnalytics([
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div>
            <h1>🏪 Material Store Dashboard</h1>
            <p>Manage and monitor Material Store inventory, transactions, and analytics</p>
        </div>
        <div>
            <a href="resources.php?action=materials" class="btn btn-outline">Back to Resources</a>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <?php if (!empty($lowStockAlerts)): ?>
    <div class="dashboard-card" style="border-left: 4px solid var(--danger); margin-bottom: 20px;">
        <h3 style="color: var(--danger); margin-bottom: 15px;">⚠️ Low Stock Alerts</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <?php foreach ($lowStockAlerts as $alert): ?>
            <div style="padding: 12px; background: rgba(220, 53, 69, 0.1); border-radius: 6px;">
                <div style="font-weight: 600; margin-bottom: 5px;"><?php echo e($alert['material_name']); ?></div>
                <div style="font-size: 14px; color: var(--secondary);">
                    Remaining: <strong style="color: var(--danger);"><?php echo number_format($alert['quantity_remaining'], 0); ?></strong>
                    <?php if ($alert['stock_status'] === 'out_of_stock'): ?>
                        <span style="color: var(--danger); font-weight: 600;">(OUT OF STOCK)</span>
                    <?php elseif ($alert['stock_status'] === 'critical'): ?>
                        <span style="color: var(--danger);">(CRITICAL)</span>
                    <?php else: ?>
                        <span style="color: #ff9800;">(LOW)</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="dashboard-card" style="padding: 20px; text-align: center;">
            <div style="font-size: 32px; font-weight: 700; color: var(--primary); margin-bottom: 8px;">
                <?php echo count($inventory); ?>
            </div>
            <div style="color: var(--secondary); font-size: 14px;">Material Types</div>
        </div>
        <div class="dashboard-card" style="padding: 20px; text-align: center;">
            <div style="font-size: 32px; font-weight: 700; color: #10b981; margin-bottom: 8px;">
                <?php echo number_format(array_sum(array_column($inventory, 'quantity_remaining')), 0); ?>
            </div>
            <div style="color: var(--secondary); font-size: 14px;">Total Units Available</div>
        </div>
        <div class="dashboard-card" style="padding: 20px; text-align: center;">
            <div style="font-size: 32px; font-weight: 700; color: var(--success); margin-bottom: 8px;">
                <?php echo formatCurrency(array_sum(array_column($inventory, 'total_value'))); ?>
            </div>
            <div style="color: var(--secondary); font-size: 14px;">Total Inventory Value</div>
        </div>
        <?php if (count($lowStockAlerts) > 0): ?>
        <div class="dashboard-card" style="padding: 20px; text-align: center; border-left: 4px solid var(--danger);">
            <div style="font-size: 32px; font-weight: 700; color: var(--danger); margin-bottom: 8px;">
                ⚠️ <?php echo count($lowStockAlerts); ?>
            </div>
            <div style="color: var(--secondary); font-size: 14px;">Low Stock Items</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <h3>Filters</h3>
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div class="form-group">
                <label>Material Type</label>
                <select name="material_type" class="form-control">
                    <option value="">All Materials</option>
                    <option value="screen_pipe" <?php echo $materialType === 'screen_pipe' ? 'selected' : ''; ?>>Screen Pipe</option>
                    <option value="plain_pipe" <?php echo $materialType === 'plain_pipe' ? 'selected' : ''; ?>>Plain Pipe</option>
                    <option value="gravel" <?php echo $materialType === 'gravel' ? 'selected' : ''; ?>>Gravel</option>
                </select>
            </div>
            <div class="form-group">
                <label>Transaction Type</label>
                <select name="transaction_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="transfer_from_pos" <?php echo $transactionType === 'transfer_from_pos' ? 'selected' : ''; ?>>Transfer from POS</option>
                    <option value="usage_in_field" <?php echo $transactionType === 'usage_in_field' ? 'selected' : ''; ?>>Usage in Field</option>
                    <option value="return_to_pos" <?php echo $transactionType === 'return_to_pos' ? 'selected' : ''; ?>>Return to POS</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Apply Filters</button>
            </div>
        </form>
    </div>

    <!-- Inventory Overview -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3>📦 Current Inventory</h3>
            <button onclick="openBulkTransferModal()" class="btn" style="background: #10b981; color: white;">
                📦 Bulk Transfer from POS
            </button>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Received</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th>Returned</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">No materials in Material Store yet. Transfer materials from POS to get started.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($inventory as $item): 
                        $stockPercent = $item['quantity_received'] > 0 ? ($item['quantity_remaining'] / $item['quantity_received']) * 100 : 0;
                        $status = $stockPercent <= 0 ? 'out_of_stock' : ($stockPercent <= 10 ? 'critical' : ($stockPercent <= 20 ? 'low' : 'ok'));
                    ?>
                    <tr>
                        <td><strong><?php echo e($item['material_name']); ?></strong></td>
                        <td><?php echo number_format($item['quantity_received'], 0); ?></td>
                        <td style="color: var(--danger);"><?php echo number_format($item['quantity_used'], 0); ?></td>
                        <td style="font-weight: 600; color: <?php echo $status === 'ok' ? '#10b981' : ($status === 'low' ? '#ff9800' : 'var(--danger)'); ?>;">
                            <?php echo number_format($item['quantity_remaining'], 0); ?>
                        </td>
                        <td><?php echo number_format($item['quantity_returned'], 0); ?></td>
                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>
                        <td style="font-weight: 600;"><?php echo formatCurrency($item['total_value']); ?></td>
                        <td>
                            <?php if ($status === 'out_of_stock'): ?>
                                <span class="badge" style="background: var(--danger); color: white;">Out of Stock</span>
                            <?php elseif ($status === 'critical'): ?>
                                <span class="badge" style="background: var(--danger); color: white;">Critical</span>
                            <?php elseif ($status === 'low'): ?>
                                <span class="badge" style="background: #ff9800; color: white;">Low</span>
                            <?php else: ?>
                                <span class="badge" style="background: #10b981; color: white;">OK</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['quantity_remaining'] > 0): ?>
                            <button onclick="returnMaterialStoreToPos('<?php echo e($item['material_type']); ?>', <?php echo $item['quantity_remaining']; ?>)" 
                                    class="btn btn-sm" 
                                    style="font-size: 11px; padding: 4px 8px; background: #10b981; color: white;">
                                🔄 Return to POS
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analytics -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- Usage by Material -->
        <div class="dashboard-card">
            <h3>📊 Usage by Material</h3>
            <div style="overflow-x: auto;">
                <table class="data-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Received</th>
                            <th>Used</th>
                            <th>Returned</th>
                            <th>Usage Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($analytics['by_material'])): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">No usage data for selected period</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($analytics['by_material'] as $stat): ?>
                        <tr>
                            <td><strong><?php echo ucfirst(str_replace('_', ' ', $stat['material_type'])); ?></strong></td>
                            <td><?php echo number_format($stat['total_received'], 0); ?></td>
                            <td style="color: var(--danger);"><?php echo number_format($stat['total_used'], 0); ?></td>
                            <td><?php echo number_format($stat['total_returned'], 0); ?></td>
                            <td><?php echo $stat['usage_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Usage -->
        <div class="dashboard-card">
            <h3>📈 Daily Usage (Last 30 Days)</h3>
            <div style="overflow-x: auto;">
                <table class="data-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Quantity Used</th>
                            <th>Usage Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($analytics['daily'])): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">No usage data for selected period</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach (array_slice($analytics['daily'], 0, 10) as $daily): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($daily['date'])); ?></td>
                            <td style="color: var(--danger); font-weight: 600;"><?php echo number_format($daily['daily_used'], 0); ?></td>
                            <td><?php echo $daily['daily_usage_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="dashboard-card">
        <h3>📋 Recent Transactions</h3>
        <div style="overflow-x: auto;">
            <table class="data-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th>Material</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                        <th>Performed By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">No transactions found for selected filters</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach (array_slice($transactions, 0, 50) as $txn): ?>
                    <tr>
                        <td><?php echo date('M j, Y H:i', strtotime($txn['created_at'])); ?></td>
                        <td>
                            <?php
                            $typeLabels = [
                                'transfer_from_pos' => '📥 Transfer from POS',
                                'usage_in_field' => '🔨 Usage in Field',
                                'return_to_pos' => '📤 Return to POS',
                                'adjustment' => '⚙️ Adjustment'
                            ];
                            echo $typeLabels[$txn['transaction_type']] ?? $txn['transaction_type'];
                            ?>
                        </td>
                        <td><strong><?php echo ucfirst(str_replace('_', ' ', $txn['material_type'])); ?></strong></td>
                        <td style="font-weight: 600; color: <?php echo $txn['quantity'] < 0 ? 'var(--danger)' : '#10b981'; ?>;">
                            <?php echo $txn['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($txn['quantity'], 2); ?>
                        </td>
                        <td>
                            <?php if ($txn['field_report_code']): ?>
                                <a href="field-reports-list.php?search=<?php echo urlencode($txn['field_report_code']); ?>" style="color: var(--primary);">
                                    <?php echo e($txn['field_report_code']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo e($txn['reference_type'] ?? '—'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($txn['performed_by_name'] ?? 'System'); ?></td>
                        <td style="font-size: 12px; color: var(--secondary);"><?php echo e($txn['remarks'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Transfer Modal -->
<div id="bulkTransferModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <h3>📦 Bulk Transfer from POS to Material Store</h3>
        <form id="bulkTransferForm" onsubmit="submitBulkTransfer(event)">
            <div id="bulkTransferRows" style="margin-bottom: 15px;">
                <!-- Rows will be added dynamically -->
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button type="button" onclick="addBulkTransferRow()" class="btn btn-outline" style="flex: 1;">➕ Add Material</button>
                <button type="button" onclick="closeBulkTransferModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; background: #10b981;">Transfer All</button>
            </div>
        </form>
    </div>
</div>

<script>
let bulkTransferRowCount = 0;

function openBulkTransferModal() {
    document.getElementById('bulkTransferModal').style.display = 'flex';
    addBulkTransferRow(); // Add first row
}

function closeBulkTransferModal() {
    document.getElementById('bulkTransferModal').style.display = 'none';
    document.getElementById('bulkTransferRows').innerHTML = '';
    bulkTransferRowCount = 0;
}

function addBulkTransferRow() {
    bulkTransferRowCount++;
    const rows = document.getElementById('bulkTransferRows');
    const row = document.createElement('div');
    row.className = 'bulk-transfer-row';
    row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; margin-bottom: 10px; align-items: end;';
    row.innerHTML = `
        <div class="form-group">
            <label>Material Type</label>
            <select name="transfers[${bulkTransferRowCount}][material_type]" class="form-control" required>
                <option value="">Select Material</option>
                <option value="screen_pipe">Screen Pipe</option>
                <option value="plain_pipe">Plain Pipe</option>
                <option value="gravel">Gravel</option>
            </select>
        </div>
        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="transfers[${bulkTransferRowCount}][quantity]" class="form-control" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
            <label>Remarks</label>
            <input type="text" name="transfers[${bulkTransferRowCount}][remarks]" class="form-control" placeholder="Optional">
        </div>
        <div>
            <button type="button" onclick="this.closest('.bulk-transfer-row').remove()" class="btn btn-danger" style="padding: 8px 12px;">✕</button>
        </div>
    `;
    rows.appendChild(row);
}

function submitBulkTransfer(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const transfers = [];
    
    // Collect transfer data
    for (let i = 1; i <= bulkTransferRowCount; i++) {
        const materialType = formData.get(`transfers[${i}][material_type]`);
        const quantity = formData.get(`transfers[${i}][quantity]`);
        const remarks = formData.get(`transfers[${i}][remarks]`);
        
        if (materialType && quantity) {
            transfers.push({
                material_type: materialType,
                quantity: parseFloat(quantity),
                remarks: remarks || null
            });
        }
    }
    
    if (transfers.length === 0) {
        alert('Please add at least one material to transfer');
        return;
    }
    
    fetch('api/material-store-transfer.php?action=bulk_transfer', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            transfers: transfers,
            csrf_token: '<?php echo CSRF::generateToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully transferred ${data.transferred} materials!`);
            closeBulkTransferModal();
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            if (data.errors) {
                console.error('Transfer errors:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while transferring materials.');
    });
}

function returnMaterialStoreToPos(materialType, maxQuantity) {
    const quantity = prompt(`Enter quantity to return to POS (max: ${maxQuantity}):`, maxQuantity);
    if (!quantity || parseFloat(quantity) <= 0 || parseFloat(quantity) > maxQuantity) {
        return;
    }
    
    if (!confirm(`Return ${quantity} units of ${materialType} from Material Store to POS?`)) {
        return;
    }
    
    fetch('api/material-store-transfer.php?action=return_to_pos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            material_type: materialType,
            quantity: parseFloat(quantity),
            csrf_token: '<?php echo CSRF::generateToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Materials returned to POS successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while returning materials.');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

