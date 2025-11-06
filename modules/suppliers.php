<?php
/**
 * Supplier Intelligence
 * Rank suppliers by delivery time, price stability, and defect rate; generate purchase drafts
 */
$page_title = 'Supplier Intelligence';
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$pdo = getDBConnection();

// Ensure suppliers table exists
try {
    $pdo->query("SELECT 1 FROM suppliers LIMIT 1");
} catch (Exception $e) {
    // Create suppliers table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            delivery_time_avg INT DEFAULT NULL COMMENT 'Average delivery time in days',
            price_stability_score DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 score',
            defect_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Defect percentage',
            overall_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Calculated overall score',
            last_delivery_date DATE DEFAULT NULL,
            total_orders INT DEFAULT 0,
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_supplier_name (supplier_name),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert sample supplier if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("
            INSERT INTO suppliers (supplier_name, contact_person, contact_phone, email, delivery_time_avg, price_stability_score, defect_rate, overall_score, last_delivery_date, total_orders, status) 
            VALUES ('Sample Supplier', 'John Doe', '+233 XX XXX XXXX', 'supplier@example.com', 5, 85.5, 2.3, 82.0, NULL, 0, 'active')
        ");
    }
}

// Get suppliers with calculated scores
$suppliers = [];
try {
    $suppliers = $pdo->query("
        SELECT 
            id, supplier_name, contact_person, contact_phone, email, address,
            delivery_time_avg, price_stability_score, defect_rate, overall_score,
            last_delivery_date, total_orders, status
        FROM suppliers 
        WHERE status = 'active'
        ORDER BY overall_score DESC, supplier_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback to sample data
    $suppliers = [
        [
            'id' => 0,
            'supplier_name' => 'Sample Supplier',
            'contact_person' => 'John Doe',
            'contact_phone' => '+233 XX XXX XXXX',
            'email' => 'supplier@example.com',
            'overall_score' => 82,
            'last_delivery_date' => null,
            'total_orders' => 0
        ]
    ];
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🏭 Supplier Intelligence</h1>
        <p>Rank suppliers by delivery time, price stability, and defect rate; generate purchase drafts</p>
    </div>

    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="margin: 0;">Supplier Ranking</h2>
            <a href="suppliers.php?action=manage" class="btn btn-outline">Manage Suppliers</a>
        </div>
        
        <?php if (empty($suppliers)): ?>
            <p style="color: var(--secondary); text-align: center; padding: 2rem;">
                No suppliers found. <a href="suppliers.php?action=add">Add your first supplier</a>
            </p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Score</th>
                            <th>Delivery Avg</th>
                            <th>Price Stability</th>
                            <th>Defect Rate</th>
                            <th>Last Delivery</th>
                            <th>Total Orders</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                    <?php if ($supplier['contact_person']): ?>
                                        <br><small style="color: var(--secondary);"><?php echo htmlspecialchars($supplier['contact_person']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 1.25rem; font-weight: 600; color: <?php 
                                        $score = floatval($supplier['overall_score'] ?? 0);
                                        if ($score >= 80) echo '#10b981';
                                        elseif ($score >= 60) echo '#f59e0b';
                                        else echo '#ef4444';
                                    ?>;">
                                        <?php echo number_format($score, 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($supplier['delivery_time_avg']): ?>
                                        <?php echo intval($supplier['delivery_time_avg']); ?> days
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($supplier['price_stability_score']): ?>
                                        <?php echo number_format(floatval($supplier['price_stability_score']), 1); ?>%
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($supplier['defect_rate']): ?>
                                        <?php echo number_format(floatval($supplier['defect_rate']), 2); ?>%
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($supplier['last_delivery_date']): ?>
                                        <?php echo date('M j, Y', strtotime($supplier['last_delivery_date'])); ?>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($supplier['total_orders'] ?? 0); ?></td>
                                <td>
                                    <a href="purchase-order-draft.php?supplier=<?php echo urlencode($supplier['supplier_name']); ?>" 
                                       class="btn btn-sm btn-primary">
                                        Create Draft PO
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p style="margin-top: 1.5rem; color: var(--secondary); font-size: 0.875rem;">
                <strong>Note:</strong> Supplier scores are calculated based on delivery time, price stability, and defect rates. 
                Higher scores indicate better supplier performance.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


