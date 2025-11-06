<?php
/**
 * Draft Purchase Order Creator
 * Allows creating draft purchase orders for suppliers
 */
$page_title = 'Draft Purchase Order';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole([ROLE_ADMIN, ROLE_MANAGER, ROLE_SUPERVISOR]);

$pdo = getDBConnection();

// Ensure purchase orders table exists
try {
    $pdo->query("SELECT 1 FROM purchase_orders LIMIT 1");
} catch (Exception $e) {
    // Create table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_number VARCHAR(50) UNIQUE NOT NULL,
            supplier_name VARCHAR(255) NOT NULL,
            supplier_contact VARCHAR(255) DEFAULT NULL,
            supplier_email VARCHAR(255) DEFAULT NULL,
            supplier_address TEXT DEFAULT NULL,
            status ENUM('draft','pending','approved','ordered','received','cancelled') DEFAULT 'draft',
            total_amount DECIMAL(12,2) DEFAULT 0.00,
            notes TEXT DEFAULT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_po_number (po_number),
            INDEX idx_status (status),
            INDEX idx_supplier (supplier_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_description TEXT DEFAULT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL,
            total_price DECIMAL(12,2) NOT NULL,
            catalog_item_id INT DEFAULT NULL,
            FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id) ON DELETE SET NULL,
            INDEX idx_po (po_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Get supplier from query parameter
$supplierName = $_GET['supplier'] ?? '';

// Handle form submission
$message = null;
$error = null;
$poNumber = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            $supplierName = trim($_POST['supplier_name'] ?? '');
            $supplierContact = trim($_POST['supplier_contact'] ?? '');
            $supplierEmail = trim($_POST['supplier_email'] ?? '');
            $supplierAddress = trim($_POST['supplier_address'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $items = $_POST['items'] ?? [];
            
            if (empty($supplierName)) {
                throw new Exception('Supplier name is required');
            }
            
            // Generate PO number
            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            
            // Calculate total
            $totalAmount = 0;
            $validItems = [];
            foreach ($items as $item) {
                if (!empty($item['item_name']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
                    $quantity = floatval($item['quantity']);
                    $unitPrice = floatval($item['unit_price']);
                    $totalPrice = $quantity * $unitPrice;
                    $totalAmount += $totalPrice;
                    
                    $validItems[] = [
                        'item_name' => trim($item['item_name']),
                        'item_description' => trim($item['item_description'] ?? ''),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'catalog_item_id' => !empty($item['catalog_item_id']) ? intval($item['catalog_item_id']) : null
                    ];
                }
            }
            
            if (empty($validItems)) {
                throw new Exception('At least one item is required');
            }
            
            $pdo->beginTransaction();
            
            // Insert purchase order
            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders (
                    po_number, supplier_name, supplier_contact, supplier_email, 
                    supplier_address, total_amount, notes, status, created_by
                ) VALUES (?,?,?,?,?,?,?,'draft',?)
            ");
            $stmt->execute([
                $poNumber, $supplierName, $supplierContact, $supplierEmail,
                $supplierAddress, $totalAmount, $notes, $_SESSION['user_id']
            ]);
            $poId = $pdo->lastInsertId();
            
            // Insert items
            $itemStmt = $pdo->prepare("
                INSERT INTO purchase_order_items (
                    po_id, item_name, item_description, quantity, unit_price, total_price, catalog_item_id
                ) VALUES (?,?,?,?,?,?,?)
            ");
            foreach ($validItems as $item) {
                $itemStmt->execute([
                    $poId, $item['item_name'], $item['item_description'], 
                    $item['quantity'], $item['unit_price'], $item['total_price'],
                    $item['catalog_item_id']
                ]);
            }
            
            $pdo->commit();
            $message = "Draft Purchase Order #{$poNumber} created successfully!";
            
            // Redirect to view the PO
            header("Location: purchase-order-view.php?id={$poId}");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get catalog items for dropdown
$catalogItems = [];
try {
    $catalogItems = $pdo->query("SELECT id, name, sku, sell_price FROM catalog_items WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Catalog might not exist
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>📋 Create Draft Purchase Order</h1>
        <p>Create a draft purchase order for supplier procurement</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="poForm">
        <?php echo CSRF::getTokenField(); ?>
        
        <div class="dashboard-card">
            <h2>Supplier Information</h2>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div>
                    <label>Supplier Name <span style="color: red;">*</span></label>
                    <input type="text" name="supplier_name" value="<?php echo htmlspecialchars($supplierName); ?>" required class="form-control">
                </div>
                <div>
                    <label>Contact Person/Phone</label>
                    <input type="text" name="supplier_contact" class="form-control" placeholder="Name or phone number">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="supplier_email" class="form-control" placeholder="supplier@example.com">
                </div>
                <div>
                    <label>Address</label>
                    <textarea name="supplier_address" class="form-control" rows="2" placeholder="Supplier address"></textarea>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>Items to Order</h2>
            <div id="itemsContainer">
                <div class="po-item-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end; margin-bottom: 1rem; padding: 1rem; background: var(--bg); border-radius: 8px;">
                    <div>
                        <label>Item Name <span style="color: red;">*</span></label>
                        <input type="text" name="items[0][item_name]" required class="form-control" placeholder="Enter item name">
                        <?php if (!empty($catalogItems)): ?>
                            <select class="form-control" style="margin-top: 0.5rem; font-size: 0.875rem;" onchange="selectCatalogItem(this, 0)">
                                <option value="">-- Select from Catalog --</option>
                                <?php foreach ($catalogItems as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>" data-price="<?php echo $item['sell_price']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label>Description</label>
                        <textarea name="items[0][item_description]" class="form-control" rows="2" placeholder="Item description"></textarea>
                    </div>
                    <div>
                        <label>Quantity <span style="color: red;">*</span></label>
                        <input type="number" name="items[0][quantity]" required min="0" step="0.01" value="1" class="form-control calculate-total" onchange="calculateItemTotal(0)">
                        <input type="hidden" name="items[0][catalog_item_id]" value="">
                    </div>
                    <div>
                        <label>Unit Price (GHS) <span style="color: red;">*</span></label>
                        <input type="number" name="items[0][unit_price]" required min="0" step="0.01" value="0.00" class="form-control calculate-total" onchange="calculateItemTotal(0)">
                    </div>
                    <div>
                        <label>Total (GHS)</label>
                        <input type="text" name="items[0][total_price]" readonly class="form-control item-total" value="0.00">
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline" onclick="removeItem(this)" style="margin-top: 1.5rem;">Remove</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline" onclick="addItem()">+ Add Item</button>
            
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid var(--border); text-align: right;">
                <h3 style="margin: 0;">Total Amount: <span id="grandTotal">GHS 0.00</span></h3>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>Additional Notes</h2>
            <textarea name="notes" class="form-control" rows="4" placeholder="Any additional notes or special instructions for this purchase order..."></textarea>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="suppliers.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Draft PO</button>
        </div>
    </form>
</div>

<script>
let itemCount = 1;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'po-item-row';
    newItem.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end; margin-bottom: 1rem; padding: 1rem; background: var(--bg); border-radius: 8px;';
    
    newItem.innerHTML = `
        <div>
            <label>Item Name <span style="color: red;">*</span></label>
            <input type="text" name="items[${itemCount}][item_name]" required class="form-control" placeholder="Enter item name">
            <?php if (!empty($catalogItems)): ?>
            <select class="form-control" style="margin-top: 0.5rem; font-size: 0.875rem;" onchange="selectCatalogItem(this, ${itemCount})">
                <option value="">-- Select from Catalog --</option>
                <?php foreach ($catalogItems as $item): ?>
                <option value="<?php echo $item['id']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>" data-price="<?php echo $item['sell_price']; ?>">
                    <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <div>
            <label>Description</label>
            <textarea name="items[${itemCount}][item_description]" class="form-control" rows="2" placeholder="Item description"></textarea>
        </div>
        <div>
            <label>Quantity <span style="color: red;">*</span></label>
            <input type="number" name="items[${itemCount}][quantity]" required min="0" step="0.01" value="1" class="form-control calculate-total" onchange="calculateItemTotal(${itemCount})">
            <input type="hidden" name="items[${itemCount}][catalog_item_id]" value="">
        </div>
        <div>
            <label>Unit Price (GHS) <span style="color: red;">*</span></label>
            <input type="number" name="items[${itemCount}][unit_price]" required min="0" step="0.01" value="0.00" class="form-control calculate-total" onchange="calculateItemTotal(${itemCount})">
        </div>
        <div>
            <label>Total (GHS)</label>
            <input type="text" name="items[${itemCount}][total_price]" readonly class="form-control item-total" value="0.00">
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-outline" onclick="removeItem(this)" style="margin-top: 1.5rem;">Remove</button>
        </div>
    `;
    
    container.appendChild(newItem);
    itemCount++;
}

function removeItem(btn) {
    const row = btn.closest('.po-item-row');
    if (document.querySelectorAll('.po-item-row').length > 1) {
        row.remove();
        calculateGrandTotal();
    } else {
        alert('At least one item is required');
    }
}

function selectCatalogItem(select, index) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        const row = select.closest('.po-item-row');
        const nameInput = row.querySelector('input[name*="[item_name]"]');
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        const catalogIdInput = row.querySelector('input[name*="[catalog_item_id]"]');
        
        nameInput.value = option.dataset.name;
        priceInput.value = parseFloat(option.dataset.price).toFixed(2);
        catalogIdInput.value = option.value;
        
        calculateItemTotal(index);
    }
}

function calculateItemTotal(index) {
    const row = document.querySelectorAll('.po-item-row')[index];
    if (!row) return;
    
    const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
    const total = quantity * unitPrice;
    
    row.querySelector('.item-total').value = total.toFixed(2);
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let total = 0;
    document.querySelectorAll('.item-total').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('grandTotal').textContent = 'GHS ' + total.toFixed(2);
}

// Initialize calculation
document.addEventListener('DOMContentLoaded', function() {
    calculateGrandTotal();
    document.querySelectorAll('.calculate-total').forEach(input => {
        input.addEventListener('input', function() {
            const index = Array.from(document.querySelectorAll('.po-item-row')).indexOf(this.closest('.po-item-row'));
            calculateItemTotal(index);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
