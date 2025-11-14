<?php
/**
 * Fix Sale Inventory - Repair inventory for completed sales
 * This script will retroactively deduct inventory for sales that were completed
 * but didn't have inventory deducted properly
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';
require_once $rootPath . '/includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();
$repo = new PosRepository($pdo);

$saleNumber = $_GET['sale_number'] ?? '';
$action = $_POST['action'] ?? '';

if ($action === 'fix_sale' && !empty($saleNumber)) {
    try {
        // Get sale details
        $saleStmt = $pdo->prepare("SELECT * FROM pos_sales WHERE sale_number = ?");
        $saleStmt->execute([$saleNumber]);
        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            throw new Exception('Sale not found');
        }
        
        // Get sale items
        $itemsStmt = $pdo->prepare("SELECT * FROM pos_sale_items WHERE sale_id = ?");
        $itemsStmt->execute([$sale['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $fixed = [];
        $errors = [];
        
        foreach ($items as $item) {
            try {
                // Deduct inventory
                $repo->adjustStock([
                    'store_id' => $sale['store_id'],
                    'product_id' => $item['product_id'],
                    'quantity_delta' => -abs($item['quantity']),
                    'transaction_type' => 'sale',
                    'reference_type' => 'pos_sale',
                    'reference_id' => $saleNumber,
                    'unit_cost' => $item['cost_amount'] ?? null,
                    'remarks' => 'Retroactive inventory deduction for sale ' . $saleNumber,
                    'performed_by' => $sale['cashier_id'],
                ]);
                
                $fixed[] = "Product {$item['product_id']}: -{$item['quantity']} units";
            } catch (Exception $e) {
                $errors[] = "Product {$item['product_id']}: " . $e->getMessage();
            }
        }
        
        $message = "Fixed inventory for sale {$saleNumber}. " . count($fixed) . " items processed.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        $_SESSION['success'] = $message;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?sale_number=' . urlencode($saleNumber));
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get sale details if provided
$sale = null;
$saleItems = [];
if (!empty($saleNumber)) {
    $saleStmt = $pdo->prepare("SELECT * FROM pos_sales WHERE sale_number = ?");
    $saleStmt->execute([$saleNumber]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sale) {
        $itemsStmt = $pdo->prepare("
            SELECT si.*, p.name as product_name, p.sku, p.catalog_item_id,
                   ci.name as catalog_name, ci.stock_quantity as catalog_stock
            FROM pos_sale_items si
            LEFT JOIN pos_products p ON si.product_id = p.id
            LEFT JOIN catalog_items ci ON p.catalog_item_id = ci.id
            WHERE si.sale_id = ?
        ");
        $itemsStmt->execute([$sale['id']]);
        $saleItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Sale Inventory</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            background: #0ea5e9;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #0284c7;
        }
        .btn-danger {
            background: #ef4444;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Sale Inventory</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <form method="GET" style="margin-bottom: 30px;">
            <label><strong>Enter Sale Number:</strong></label>
            <input type="text" name="sale_number" value="<?php echo htmlspecialchars($saleNumber); ?>" 
                   placeholder="e.g., POS-20251110-0001" style="padding: 8px; width: 300px; margin-right: 10px;">
            <button type="submit" class="btn">Lookup Sale</button>
        </form>
        
        <?php if ($sale): ?>
            <h2>Sale Details: <?php echo htmlspecialchars($sale['sale_number']); ?></h2>
            <p><strong>Date:</strong> <?php echo date('Y-m-d H:i', strtotime($sale['sale_timestamp'])); ?></p>
            <p><strong>Total:</strong> <?php echo formatCurrency($sale['total_amount']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($sale['sale_status']); ?></p>
            
            <?php if (!empty($saleItems)): ?>
                <h3>Sale Items</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Linked to Catalog?</th>
                            <th>Catalog Stock</th>
                            <th>POS Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saleItems as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                            <td>
                                <?php if ($item['catalog_item_id']): ?>
                                    <span style="color: green;">✅ Yes (ID: <?php echo $item['catalog_item_id']; ?>)</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ No - Product not linked to catalog</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($item['catalog_stock'] ?? 0, 2); ?></td>
                            <td>
                                <?php
                                $posStockStmt = $pdo->prepare("
                                    SELECT quantity_on_hand FROM pos_inventory 
                                    WHERE store_id = ? AND product_id = ?
                                ");
                                $posStockStmt->execute([$sale['store_id'], $item['product_id']]);
                                $posStock = $posStockStmt->fetchColumn() ?? 0;
                                echo number_format($posStock, 2);
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="fix_sale">
                    <button type="submit" class="btn btn-danger" 
                            onclick="return confirm('This will retroactively deduct inventory for this sale. Continue?')">
                        🔧 Fix Inventory for This Sale
                    </button>
                </form>
            <?php else: ?>
                <p>No items found for this sale.</p>
            <?php endif; ?>
        <?php elseif (!empty($saleNumber)): ?>
            <p style="color: red;">Sale not found: <?php echo htmlspecialchars($saleNumber); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>

