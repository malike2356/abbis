<?php
/**
 * Unified Resources Management System
 * 
 * Simple Rule:
 * - If you USE it → Materials
 * - If you SELL it → Catalog
 * - If you OWN it long-term → Assets
 * - If you SERVICE it → Maintenance
 */
$page_title = 'Resources Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'overview';
$resourceType = $_GET['type'] ?? '';
$currentUserId = $_SESSION['user_id'];

// Ensure all tables exist
try {
    // Materials Inventory - Fix table structure
    $pdo->exec("CREATE TABLE IF NOT EXISTS `materials_inventory` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `material_type` VARCHAR(100) NOT NULL,
        `material_name` VARCHAR(255) NOT NULL,
        `quantity_received` INT(11) DEFAULT '0',
        `quantity_used` INT(11) DEFAULT '0',
        `quantity_remaining` INT(11) DEFAULT '0',
        `unit_cost` DECIMAL(10,2) DEFAULT '0.00',
        `total_value` DECIMAL(12,2) DEFAULT '0.00',
        `unit_of_measure` VARCHAR(20) DEFAULT 'pcs',
        `supplier` VARCHAR(255) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `material_type` (`material_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Fix existing table if columns missing
    try {
        $pdo->exec("ALTER TABLE materials_inventory ADD COLUMN unit_of_measure VARCHAR(20) DEFAULT 'pcs' AFTER total_value");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    try {
        $pdo->exec("ALTER TABLE materials_inventory ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER unit_of_measure");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    try {
        $pdo->exec("ALTER TABLE materials_inventory ADD COLUMN notes TEXT DEFAULT NULL AFTER supplier");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    // Add catalog_item_id and inventory_quantity to catalog_items if missing
    try {
        $pdo->exec("ALTER TABLE catalog_items ADD COLUMN inventory_quantity INT(11) DEFAULT 0 AFTER is_active");
    } catch (PDOException $e) {
        // Column already exists
    }
    
    try {
        $pdo->exec("ALTER TABLE catalog_items ADD COLUMN reorder_level INT(11) DEFAULT 0 AFTER inventory_quantity");
    } catch (PDOException $e) {
        // Column already exists
    }
    
    // Catalog
    $pdo->exec("CREATE TABLE IF NOT EXISTS `catalog_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(120) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `catalog_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(200) NOT NULL,
        `sku` VARCHAR(80) DEFAULT NULL,
        `item_type` ENUM('product','service') NOT NULL DEFAULT 'product',
        `category_id` INT DEFAULT NULL,
        `unit` VARCHAR(40) DEFAULT NULL,
        `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `sell_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `taxable` TINYINT(1) NOT NULL DEFAULT 0,
        `is_purchasable` TINYINT(1) NOT NULL DEFAULT 1,
        `is_sellable` TINYINT(1) NOT NULL DEFAULT 1,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `inventory_quantity` INT(11) DEFAULT 0,
        `reorder_level` INT(11) DEFAULT 0,
        `notes` VARCHAR(500) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `category_id` (`category_id`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Inventory Transactions - Track all inventory movements
    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `transaction_type` ENUM('purchase','sale','use','adjustment','return') NOT NULL,
        `item_type` ENUM('material','catalog') NOT NULL,
        `item_id` INT(11) NOT NULL,
        `quantity` INT(11) NOT NULL,
        `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
        `total_cost` DECIMAL(12,2) DEFAULT 0.00,
        `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., purchase_order, field_report, sale_order',
        `reference_id` INT(11) DEFAULT NULL,
        `supplier_id` INT(11) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `item_type` (`item_type`, `item_id`),
        KEY `transaction_type` (`transaction_type`),
        KEY `reference` (`reference_type`, `reference_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Purchase Orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `po_number` VARCHAR(50) UNIQUE NOT NULL,
        `supplier_id` INT(11) DEFAULT NULL,
        `supplier_name` VARCHAR(255) NOT NULL,
        `status` ENUM('draft','pending','approved','ordered','received','cancelled') DEFAULT 'draft',
        `total_amount` DECIMAL(12,2) DEFAULT 0.00,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `po_number` (`po_number`),
        KEY `supplier_id` (`supplier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Purchase Order Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_order_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `po_id` INT(11) NOT NULL,
        `item_type` ENUM('material','catalog') NOT NULL,
        `item_id` INT(11) NOT NULL,
        `item_name` VARCHAR(255) NOT NULL,
        `quantity` INT(11) NOT NULL,
        `unit_price` DECIMAL(12,2) NOT NULL,
        `total_price` DECIMAL(12,2) NOT NULL,
        `received_quantity` INT(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `po_id` (`po_id`),
        FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Sale Orders (for CMS shop integration)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sale_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_number` VARCHAR(50) UNIQUE NOT NULL,
        `customer_name` VARCHAR(255) NOT NULL,
        `customer_email` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending','processing','completed','cancelled') DEFAULT 'pending',
        `total_amount` DECIMAL(12,2) DEFAULT 0.00,
        `cms_order_id` INT(11) DEFAULT NULL COMMENT 'Link to CMS orders table',
        `notes` TEXT DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `order_number` (`order_number`),
        KEY `cms_order_id` (`cms_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Sale Order Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sale_order_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_id` INT(11) NOT NULL,
        `item_type` ENUM('material','catalog') NOT NULL,
        `item_id` INT(11) NOT NULL,
        `item_name` VARCHAR(255) NOT NULL,
        `quantity` INT(11) NOT NULL,
        `unit_price` DECIMAL(12,2) NOT NULL,
        `total_price` DECIMAL(12,2) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `order_id` (`order_id`),
        FOREIGN KEY (`order_id`) REFERENCES `sale_orders` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Assets
    $pdo->exec("CREATE TABLE IF NOT EXISTS `assets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_code` VARCHAR(50) NOT NULL UNIQUE,
        `asset_name` VARCHAR(255) NOT NULL,
        `asset_type` ENUM('rig', 'vehicle', 'equipment', 'tool', 'building', 'land', 'other') NOT NULL,
        `category` VARCHAR(100) DEFAULT NULL,
        `brand` VARCHAR(100) DEFAULT NULL,
        `model` VARCHAR(100) DEFAULT NULL,
        `serial_number` VARCHAR(100) DEFAULT NULL,
        `purchase_date` DATE DEFAULT NULL,
        `purchase_cost` DECIMAL(15,2) DEFAULT 0.00,
        `current_value` DECIMAL(15,2) DEFAULT 0.00,
        `location` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active', 'inactive', 'maintenance', 'retired', 'sold', 'lost') DEFAULT 'active',
        `condition` ENUM('excellent', 'good', 'fair', 'poor', 'critical') DEFAULT 'good',
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `asset_code` (`asset_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Maintenance Types
    $pdo->exec("CREATE TABLE IF NOT EXISTS `maintenance_types` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `type_name` VARCHAR(100) NOT NULL UNIQUE,
        `description` TEXT DEFAULT NULL,
        `is_proactive` TINYINT(1) DEFAULT 1,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Maintenance Records
    $pdo->exec("CREATE TABLE IF NOT EXISTS `maintenance_records` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `maintenance_code` VARCHAR(50) NOT NULL UNIQUE,
        `maintenance_type_id` INT(11) DEFAULT NULL,
        `maintenance_category` ENUM('proactive', 'reactive') NOT NULL DEFAULT 'proactive',
        `asset_id` INT(11) DEFAULT NULL,
        `scheduled_date` DATETIME DEFAULT NULL,
        `started_date` DATETIME DEFAULT NULL,
        `completed_date` DATETIME DEFAULT NULL,
        `status` ENUM('logged', 'scheduled', 'in_progress', 'on_hold', 'completed', 'cancelled') DEFAULT 'logged',
        `priority` ENUM('low', 'medium', 'high', 'urgent', 'critical') DEFAULT 'medium',
        `description` TEXT DEFAULT NULL,
        `work_performed` TEXT DEFAULT NULL,
        `parts_cost` DECIMAL(15,2) DEFAULT 0.00,
        `labor_cost` DECIMAL(15,2) DEFAULT 0.00,
        `total_cost` DECIMAL(15,2) DEFAULT 0.00,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `maintenance_code` (`maintenance_code`),
        KEY `asset_id` (`asset_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Suppliers table (if not exists)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `supplier_name` VARCHAR(255) NOT NULL,
        `contact_person` VARCHAR(255) DEFAULT NULL,
        `contact_phone` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert default maintenance types if not exist
    $pdo->exec("INSERT IGNORE INTO maintenance_types (type_name, description, is_proactive) VALUES
        ('Preventive Maintenance', 'Scheduled maintenance to prevent failures', 1),
        ('Breakdown Repair', 'Repair after equipment failure', 0),
        ('Inspection', 'Routine inspection and assessment', 1),
        ('Emergency Repair', 'Urgent repair to restore operation', 0)");
} catch (PDOException $e) {
    error_log("Resources tables setup error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            // ===== MATERIALS =====
            case 'add_material':
                $materialType = sanitizeInput($_POST['material_type'] ?? '');
                $materialName = sanitizeInput($_POST['material_name'] ?? '');
                $quantityReceived = intval($_POST['quantity_received'] ?? 0);
                $unitCost = floatval($_POST['unit_cost'] ?? 0);
                $unitOfMeasure = sanitizeInput($_POST['unit_of_measure'] ?? 'pcs');
                $supplier = sanitizeInput($_POST['supplier'] ?? '');
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if (empty($materialType) || empty($materialName)) {
                    throw new Exception('Material type and name are required');
                }
                
                $checkStmt = $pdo->prepare("SELECT id FROM materials_inventory WHERE material_type = ?");
                $checkStmt->execute([$materialType]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Material type already exists');
                }
                
                $totalValue = $quantityReceived * $unitCost;
                $quantityRemaining = $quantityReceived; // Initial state: all received is remaining
                
                // Check which columns exist in the table
                $checkCols = $pdo->query("SHOW COLUMNS FROM materials_inventory");
                $existingCols = [];
                while ($col = $checkCols->fetch(PDO::FETCH_ASSOC)) {
                    $existingCols[] = $col['Field'];
                }
                
                // Build INSERT query dynamically based on existing columns
                $insertFields = ['material_type', 'material_name', 'quantity_received', 'quantity_remaining', 'unit_cost', 'total_value'];
                $insertValues = [$materialType, $materialName, $quantityReceived, $quantityRemaining, $unitCost, $totalValue];
                
                if (in_array('unit_of_measure', $existingCols)) {
                    $insertFields[] = 'unit_of_measure';
                    $insertValues[] = $unitOfMeasure;
                }
                
                if (in_array('supplier', $existingCols)) {
                    $insertFields[] = 'supplier';
                    $insertValues[] = $supplier;
                }
                
                if (in_array('notes', $existingCols)) {
                    $insertFields[] = 'notes';
                    $insertValues[] = $notes;
                }
                
                $placeholders = str_repeat('?,', count($insertFields) - 1) . '?';
                $sql = "INSERT INTO materials_inventory (" . implode(', ', $insertFields) . ") VALUES ($placeholders)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
                
                echo json_encode(['success' => true, 'message' => 'Material added successfully']);
                break;
                
            case 'edit_material':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid material ID');
                
                $materialType = sanitizeInput($_POST['material_type'] ?? '');
                $materialName = sanitizeInput($_POST['material_name'] ?? '');
                $quantityReceived = intval($_POST['quantity_received'] ?? 0);
                $quantityUsed = intval($_POST['quantity_used'] ?? 0);
                $unitCost = floatval($_POST['unit_cost'] ?? 0);
                $unitOfMeasure = sanitizeInput($_POST['unit_of_measure'] ?? 'pcs');
                $supplier = sanitizeInput($_POST['supplier'] ?? '');
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if (empty($materialType) || empty($materialName)) {
                    throw new Exception('Material type and name are required');
                }
                
                $checkStmt = $pdo->prepare("SELECT id FROM materials_inventory WHERE material_type = ? AND id != ?");
                $checkStmt->execute([$materialType, $id]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Material type already exists');
                }
                
                $quantityRemaining = max(0, $quantityReceived - $quantityUsed);
                $totalValue = $quantityRemaining * $unitCost;
                
                // Check which columns exist in the table
                $checkCols = $pdo->query("SHOW COLUMNS FROM materials_inventory");
                $existingCols = [];
                while ($col = $checkCols->fetch(PDO::FETCH_ASSOC)) {
                    $existingCols[] = $col['Field'];
                }
                
                // Build UPDATE query dynamically based on existing columns
                $updateFields = ['material_type', 'material_name', 'quantity_received', 'quantity_used', 'quantity_remaining', 'unit_cost', 'total_value'];
                $updateValues = [$materialType, $materialName, $quantityReceived, $quantityUsed, $quantityRemaining, $unitCost, $totalValue];
                
                if (in_array('unit_of_measure', $existingCols)) {
                    $updateFields[] = 'unit_of_measure';
                    $updateValues[] = $unitOfMeasure;
                }
                
                if (in_array('supplier', $existingCols)) {
                    $updateFields[] = 'supplier';
                    $updateValues[] = $supplier;
                }
                
                if (in_array('notes', $existingCols)) {
                    $updateFields[] = 'notes';
                    $updateValues[] = $notes;
                }
                
                $setClause = implode(' = ?, ', $updateFields) . ' = ?';
                $updateValues[] = $id; // Add id for WHERE clause
                $sql = "UPDATE materials_inventory SET $setClause WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateValues);
                
                echo json_encode(['success' => true, 'message' => 'Material updated successfully']);
                break;
                
            case 'delete_material':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid material ID');
                
                $stmt = $pdo->prepare("DELETE FROM materials_inventory WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Material deleted successfully']);
                } else {
                    throw new Exception('Material not found');
                }
                break;
                
            // ===== CATALOG =====
            case 'add_catalog_item':
                $name = sanitizeInput($_POST['name'] ?? '');
                $sku = sanitizeInput($_POST['sku'] ?? '');
                $itemType = sanitizeInput($_POST['item_type'] ?? 'product');
                $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
                $unit = sanitizeInput($_POST['unit'] ?? '');
                $costPrice = floatval($_POST['cost_price'] ?? 0);
                $sellPrice = floatval($_POST['sell_price'] ?? 0);
                $taxable = isset($_POST['taxable']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $inventoryQuantity = intval($_POST['inventory_quantity'] ?? 0);
                $reorderLevel = intval($_POST['reorder_level'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if (empty($name)) throw new Exception('Item name is required');
                
                $stmt = $pdo->prepare("INSERT INTO catalog_items (name, sku, item_type, category_id, unit, cost_price, sell_price, taxable, is_active, inventory_quantity, reorder_level, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $sku, $itemType, $categoryId, $unit, $costPrice, $sellPrice, $taxable, $isActive, $inventoryQuantity, $reorderLevel, $notes]);
                
                echo json_encode(['success' => true, 'message' => 'Item added successfully']);
                break;
                
            case 'edit_catalog_item':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid item ID');
                
                $name = sanitizeInput($_POST['name'] ?? '');
                $sku = sanitizeInput($_POST['sku'] ?? '');
                $itemType = sanitizeInput($_POST['item_type'] ?? 'product');
                $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
                $unit = sanitizeInput($_POST['unit'] ?? '');
                $costPrice = floatval($_POST['cost_price'] ?? 0);
                $sellPrice = floatval($_POST['sell_price'] ?? 0);
                $taxable = isset($_POST['taxable']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $inventoryQuantity = intval($_POST['inventory_quantity'] ?? 0);
                $reorderLevel = intval($_POST['reorder_level'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if (empty($name)) throw new Exception('Item name is required');
                
                $stmt = $pdo->prepare("UPDATE catalog_items SET name=?, sku=?, item_type=?, category_id=?, unit=?, cost_price=?, sell_price=?, taxable=?, is_active=?, inventory_quantity=?, reorder_level=?, notes=? WHERE id=?");
                $stmt->execute([$name, $sku, $itemType, $categoryId, $unit, $costPrice, $sellPrice, $taxable, $isActive, $inventoryQuantity, $reorderLevel, $notes, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                break;
                
            case 'delete_catalog_item':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid item ID');
                
                $stmt = $pdo->prepare("DELETE FROM catalog_items WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                break;
                
            case 'delete_catalog_category':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid category ID');
                
                // Check if category has items
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM catalog_items WHERE category_id = ?");
                $checkStmt->execute([$id]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete category with items. Please reassign items first.');
                }
                
                $stmt = $pdo->prepare("DELETE FROM catalog_categories WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
                break;
                
            case 'add_catalog_category':
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($name)) throw new Exception('Category name is required');
                
                $stmt = $pdo->prepare("INSERT INTO catalog_categories (name, description) VALUES (?,?)");
                $stmt->execute([$name, $description]);
                
                echo json_encode(['success' => true, 'message' => 'Category added successfully']);
                break;
                
            case 'edit_catalog_category':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid category ID');
                
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($name)) throw new Exception('Category name is required');
                
                $stmt = $pdo->prepare("UPDATE catalog_categories SET name=?, description=? WHERE id=?");
                $stmt->execute([$name, $description, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
                break;
                
            case 'delete_catalog_category':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid category ID');
                
                // Check if category has items
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM catalog_items WHERE category_id = ?");
                $checkStmt->execute([$id]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Cannot delete category with items. Please reassign items first.');
                }
                
                $stmt = $pdo->prepare("DELETE FROM catalog_categories WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
                break;
                
            case 'update_inventory':
                $itemType = sanitizeInput($_POST['item_type'] ?? '');
                $itemId = intval($_POST['item_id'] ?? 0);
                $transactionType = sanitizeInput($_POST['transaction_type'] ?? '');
                $quantity = intval($_POST['quantity'] ?? 0);
                $unitCost = floatval($_POST['unit_cost'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                $supplierId = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
                $referenceType = sanitizeInput($_POST['reference_type'] ?? '');
                $referenceId = !empty($_POST['reference_id']) ? intval($_POST['reference_id']) : null;
                
                if (!$itemId || !$transactionType || !$quantity) {
                    throw new Exception('Missing required fields');
                }
                
                $pdo->beginTransaction();
                try {
                    // Update inventory based on type
                    if ($itemType === 'material') {
                        $itemStmt = $pdo->prepare("SELECT quantity_remaining, unit_cost FROM materials_inventory WHERE id = ?");
                        $itemStmt->execute([$itemId]);
                        $material = $itemStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$material) throw new Exception('Material not found');
                        
                        $newQuantity = $material['quantity_remaining'];
                        $newCost = $material['unit_cost'];
                        
                        if ($transactionType === 'purchase' || $transactionType === 'return') {
                            $newQuantity += $quantity;
                            if ($unitCost > 0) {
                                // Weighted average cost
                                $oldValue = $material['quantity_remaining'] * $material['unit_cost'];
                                $newValue = $quantity * $unitCost;
                                $totalQuantity = $material['quantity_remaining'] + $quantity;
                                $newCost = $totalQuantity > 0 ? ($oldValue + $newValue) / $totalQuantity : $unitCost;
                            }
                        } elseif ($transactionType === 'use' || $transactionType === 'sale') {
                            $newQuantity -= $quantity;
                            if ($newQuantity < 0) throw new Exception('Insufficient inventory');
                        } elseif ($transactionType === 'adjustment') {
                            // For adjustments, set quantity directly (for correcting counts)
                            $newQuantity = $quantity;
                        }
                        
                        $updateStmt = $pdo->prepare("UPDATE materials_inventory SET quantity_remaining = ?, unit_cost = ?, total_value = ? WHERE id = ?");
                        $updateStmt->execute([$newQuantity, $newCost, $newQuantity * $newCost, $itemId]);
                        
                    } elseif ($itemType === 'catalog') {
                        $itemStmt = $pdo->prepare("SELECT inventory_quantity FROM catalog_items WHERE id = ?");
                        $itemStmt->execute([$itemId]);
                        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$item) throw new Exception('Catalog item not found');
                        
                        $newQuantity = $item['inventory_quantity'];
                        if ($transactionType === 'purchase' || $transactionType === 'return') {
                            $newQuantity += $quantity;
                        } elseif ($transactionType === 'use' || $transactionType === 'sale') {
                            $newQuantity -= $quantity;
                            if ($newQuantity < 0) throw new Exception('Insufficient inventory');
                        } elseif ($transactionType === 'adjustment') {
                            // For adjustments, set quantity directly
                            $newQuantity = $quantity;
                        }
                        
                        $updateStmt = $pdo->prepare("UPDATE catalog_items SET inventory_quantity = ? WHERE id = ?");
                        $updateStmt->execute([$newQuantity, $itemId]);
                    }
                    
                    // Record transaction
                    $transStmt = $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, quantity, unit_cost, total_cost, reference_type, reference_id, supplier_id, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $transStmt->execute([$transactionType, $itemType, $itemId, $quantity, $unitCost, $quantity * $unitCost, $referenceType, $referenceId, $supplierId, $notes, $currentUserId]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Inventory updated successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            // ===== ASSETS =====
            case 'add_asset':
                $assetCode = sanitizeInput($_POST['asset_code'] ?? '');
                $assetName = sanitizeInput($_POST['asset_name'] ?? '');
                $assetType = sanitizeInput($_POST['asset_type'] ?? 'equipment');
                $category = sanitizeInput($_POST['category'] ?? '');
                $brand = sanitizeInput($_POST['brand'] ?? '');
                $model = sanitizeInput($_POST['model'] ?? '');
                $purchaseDate = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
                $purchaseCost = floatval($_POST['purchase_cost'] ?? 0);
                $currentValue = floatval($_POST['current_value'] ?? $purchaseCost);
                $location = sanitizeInput($_POST['location'] ?? '');
                $status = sanitizeInput($_POST['status'] ?? 'active');
                $condition = sanitizeInput($_POST['condition'] ?? 'good');
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if (empty($assetCode) || empty($assetName)) {
                    throw new Exception('Asset code and name are required');
                }
                
                $stmt = $pdo->prepare("INSERT INTO assets (asset_code, asset_name, asset_type, category, brand, model, purchase_date, purchase_cost, current_value, location, status, condition, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$assetCode, $assetName, $assetType, $category, $brand, $model, $purchaseDate, $purchaseCost, $currentValue, $location, $status, $condition, $notes]);
                
                echo json_encode(['success' => true, 'message' => 'Asset added successfully']);
                break;
                
            case 'edit_asset':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid asset ID');
                
                $assetCode = sanitizeInput($_POST['asset_code'] ?? '');
                $assetName = sanitizeInput($_POST['asset_name'] ?? '');
                $assetType = sanitizeInput($_POST['asset_type'] ?? 'equipment');
                $category = sanitizeInput($_POST['category'] ?? '');
                $brand = sanitizeInput($_POST['brand'] ?? '');
                $model = sanitizeInput($_POST['model'] ?? '');
                $purchaseDate = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
                $purchaseCost = floatval($_POST['purchase_cost'] ?? 0);
                $currentValue = floatval($_POST['current_value'] ?? $purchaseCost);
                $location = sanitizeInput($_POST['location'] ?? '');
                $status = sanitizeInput($_POST['status'] ?? 'active');
                $condition = sanitizeInput($_POST['condition'] ?? 'good');
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                if (empty($assetCode) || empty($assetName)) {
                    throw new Exception('Asset code and name are required');
                }
                
                $checkStmt = $pdo->prepare("SELECT id FROM assets WHERE asset_code = ? AND id != ?");
                $checkStmt->execute([$assetCode, $id]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Asset code already exists');
                }
                
                $stmt = $pdo->prepare("UPDATE assets SET asset_code=?, asset_name=?, asset_type=?, category=?, brand=?, model=?, purchase_date=?, purchase_cost=?, current_value=?, location=?, status=?, condition=?, notes=? WHERE id=?");
                $stmt->execute([$assetCode, $assetName, $assetType, $category, $brand, $model, $purchaseDate, $purchaseCost, $currentValue, $location, $status, $condition, $notes, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Asset updated successfully']);
                break;
                
            case 'delete_asset':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid asset ID');
                
                $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Asset deleted successfully']);
                break;
                
            // ===== MAINTENANCE =====
            case 'add_maintenance':
                $maintenanceCode = 'MNT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                $maintenanceTypeId = !empty($_POST['maintenance_type_id']) ? intval($_POST['maintenance_type_id']) : null;
                $maintenanceCategory = sanitizeInput($_POST['maintenance_category'] ?? 'proactive');
                $assetId = !empty($_POST['asset_id']) ? intval($_POST['asset_id']) : null;
                $scheduledDate = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
                $status = sanitizeInput($_POST['status'] ?? 'logged');
                $priority = sanitizeInput($_POST['priority'] ?? 'medium');
                $description = sanitizeInput($_POST['description'] ?? '');
                $partsCost = floatval($_POST['parts_cost'] ?? 0);
                $laborCost = floatval($_POST['labor_cost'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                $totalCost = $partsCost + $laborCost;
                
                $stmt = $pdo->prepare("INSERT INTO maintenance_records (maintenance_code, maintenance_type_id, maintenance_category, asset_id, scheduled_date, status, priority, description, parts_cost, labor_cost, total_cost, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$maintenanceCode, $maintenanceTypeId, $maintenanceCategory, $assetId, $scheduledDate, $status, $priority, $description, $partsCost, $laborCost, $totalCost, $notes, $currentUserId]);
                
                echo json_encode(['success' => true, 'message' => 'Maintenance record added successfully']);
                break;
                
            case 'edit_maintenance':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid maintenance ID');
                
                $maintenanceTypeId = !empty($_POST['maintenance_type_id']) ? intval($_POST['maintenance_type_id']) : null;
                $maintenanceCategory = sanitizeInput($_POST['maintenance_category'] ?? 'proactive');
                $assetId = !empty($_POST['asset_id']) ? intval($_POST['asset_id']) : null;
                $scheduledDate = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
                $startedDate = !empty($_POST['started_date']) ? $_POST['started_date'] : null;
                $completedDate = !empty($_POST['completed_date']) ? $_POST['completed_date'] : null;
                $status = sanitizeInput($_POST['status'] ?? 'logged');
                $priority = sanitizeInput($_POST['priority'] ?? 'medium');
                $description = sanitizeInput($_POST['description'] ?? '');
                $workPerformed = sanitizeInput($_POST['work_performed'] ?? '');
                $partsCost = floatval($_POST['parts_cost'] ?? 0);
                $laborCost = floatval($_POST['labor_cost'] ?? 0);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                
                $totalCost = $partsCost + $laborCost;
                
                $stmt = $pdo->prepare("UPDATE maintenance_records SET maintenance_type_id=?, maintenance_category=?, asset_id=?, scheduled_date=?, started_date=?, completed_date=?, status=?, priority=?, description=?, work_performed=?, parts_cost=?, labor_cost=?, total_cost=?, notes=? WHERE id=?");
                $stmt->execute([$maintenanceTypeId, $maintenanceCategory, $assetId, $scheduledDate, $startedDate, $completedDate, $status, $priority, $description, $workPerformed, $partsCost, $laborCost, $totalCost, $notes, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Maintenance record updated successfully']);
                break;
                
            case 'delete_maintenance':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid maintenance ID');
                
                $stmt = $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Maintenance record deleted successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get statistics
$stats = [
    'materials' => ['count' => 0, 'total_value' => 0, 'total_items' => 0, 'low_stock' => 0],
    'catalog' => ['items' => 0, 'categories' => 0],
    'assets' => ['count' => 0, 'total_value' => 0, 'active' => 0],
    'maintenance' => ['pending' => 0, 'due_soon' => 0, 'overdue' => 0]
];

try {
    // Materials stats
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(quantity_remaining), 0) as total_items, COALESCE(SUM(total_value), 0) as total_value, SUM(CASE WHEN quantity_remaining < 10 THEN 1 ELSE 0 END) as low_stock FROM materials_inventory");
    $materialsData = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['materials'] = [
        'count' => intval($materialsData['count'] ?? 0),
        'total_items' => intval($materialsData['total_items'] ?? 0),
        'total_value' => floatval($materialsData['total_value'] ?? 0),
        'low_stock' => intval($materialsData['low_stock'] ?? 0)
    ];
    
    // Catalog stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM catalog_items WHERE is_active = 1");
    $stats['catalog']['items'] = intval($stmt->fetchColumn() ?: 0);
    $stmt = $pdo->query("SELECT COUNT(DISTINCT category_id) FROM catalog_items WHERE is_active = 1");
    $stats['catalog']['categories'] = intval($stmt->fetchColumn() ?: 0);
    
    // Assets stats
    $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, COALESCE(SUM(CASE WHEN status = 'active' THEN current_value ELSE 0 END), 0) as total_value FROM assets");
    $assetsData = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['assets'] = [
        'count' => intval($assetsData['count'] ?? 0),
        'active' => intval($assetsData['active'] ?? 0),
        'total_value' => floatval($assetsData['total_value'] ?? 0)
    ];
    
    // Maintenance stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status IN ('logged', 'scheduled', 'in_progress')");
    $stats['maintenance']['pending'] = intval($stmt->fetchColumn() ?: 0);
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status = 'scheduled' AND scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)");
    $stats['maintenance']['due_soon'] = intval($stmt->fetchColumn() ?: 0);
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status IN ('logged', 'scheduled') AND scheduled_date < NOW()");
    $stats['maintenance']['overdue'] = intval($stmt->fetchColumn() ?: 0);
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Get data for current view
$materials = [];
$catalogItems = [];
$catalogCategories = [];
$assets = [];
$maintenanceRecords = [];
$maintenanceTypes = [];

try {
    if ($action === 'materials' || $action === 'overview') {
        $materials = $pdo->query("SELECT * FROM materials_inventory ORDER BY material_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($action === 'catalog' || $action === 'overview') {
        $catalogItems = $pdo->query("SELECT i.*, COALESCE(i.inventory_quantity, 0) as inventory_quantity, COALESCE(i.reorder_level, 0) as reorder_level, c.name as category_name FROM catalog_items i LEFT JOIN catalog_categories c ON c.id=i.category_id ORDER BY i.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $catalogCategories = $pdo->query("SELECT * FROM catalog_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($action === 'assets' || $action === 'overview') {
        // Get regular assets
        $assets = $pdo->query("SELECT * FROM assets ORDER BY asset_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Get rigs from Configuration and add them as assets
        // Only add rigs that don't already exist in the assets table
        require_once '../includes/config-manager.php';
        $configManager = new ConfigManager();
        $rigs = $configManager->getRigs();
        
        // Create maps of existing assets to avoid duplicates
        // Check both asset_code and asset_name to catch all duplicates
        $existingAssetCodes = [];
        $existingAssetNames = [];
        foreach ($assets as $asset) {
            if (!empty($asset['asset_code'])) {
                $existingAssetCodes[strtoupper(trim($asset['asset_code']))] = true;
            }
            if (!empty($asset['asset_name'])) {
                $existingAssetNames[strtoupper(trim($asset['asset_name']))] = true;
            }
        }
        
        // Convert rigs to asset format for display (only if not already in assets table)
        foreach ($rigs as $rig) {
            $rigCodeUpper = strtoupper(trim($rig['rig_code']));
            $rigNameUpper = strtoupper(trim($rig['rig_name']));
            
            // Skip if this rig already exists in the assets table
            // Check both asset_code and asset_name to catch duplicates
            if (isset($existingAssetCodes[$rigCodeUpper]) || isset($existingAssetNames[$rigNameUpper])) {
                continue;
            }
            
            $assets[] = [
                'id' => 'rig_' . $rig['id'],
                'asset_code' => $rig['rig_code'],
                'asset_name' => $rig['rig_name'],
                'asset_type' => 'rig',
                'category' => 'Rig',
                'brand' => $rig['truck_model'] ?? null,
                'model' => null,
                'purchase_date' => null,
                'purchase_cost' => 0,
                'current_value' => 0,
                'location' => null,
                'status' => $rig['status'],
                'condition' => 'good',
                'notes' => 'Rig from Configuration',
                'is_rig' => true,
                'rig_id' => $rig['id'],
                'current_rpm' => $rig['current_rpm'] ?? 0,
                'maintenance_due_at_rpm' => $rig['maintenance_due_at_rpm'] ?? null,
                'maintenance_status' => $rig['maintenance_status'] ?? null
            ];
        }
    }
    if ($action === 'maintenance' || $action === 'overview') {
        // Build query with proper rig filtering
        $maintenanceQuery = "
            SELECT m.*, 
                   a.asset_name, 
                   mt.type_name,
                   r.id as rig_id,
                   r.rig_name,
                   r.rig_code,
                   fr.id as field_report_id,
                   fr.report_id
            FROM maintenance_records m 
            LEFT JOIN assets a ON m.asset_id = a.id 
            LEFT JOIN maintenance_types mt ON m.maintenance_type_id = mt.id
            LEFT JOIN rigs r ON m.rig_id = r.id
            LEFT JOIN field_reports fr ON m.field_report_id = fr.id
            WHERE 1=1
        ";
        
        $maintenanceParams = [];
        
        // If a rig_id is provided, filter by rig_id directly
        if (isset($selectedRigId) && $selectedRigId) {
            $maintenanceQuery .= " AND m.rig_id = ?";
            $maintenanceParams[] = $selectedRigId;
        }
        
        $maintenanceQuery .= " ORDER BY m.created_at DESC";
        
        $maintenanceStmt = $pdo->prepare($maintenanceQuery);
        $maintenanceStmt->execute($maintenanceParams);
        $maintenanceRecords = $maintenanceStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $maintenanceTypes = $pdo->query("SELECT * FROM maintenance_types WHERE is_active = 1 ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Data fetch error: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<style>
.resources-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.resources-header {
    margin-bottom: 30px;
}

.resources-rule-card {
    background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
    color: white;
    padding: 28px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 30%, transparent);
}

[data-theme="dark"] .resources-rule-card {
    box-shadow: 0 4px 12px color-mix(in srgb, black 40%, transparent);
}

.resources-rule-card h2 {
    margin: 0 0 20px 0;
    font-size: 26px;
    color: white;
    font-weight: 600;
}

.resources-rule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.rule-item {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.rule-item strong {
    display: block;
    font-size: 18px;
    margin-bottom: 8px;
}

.tab-navigation {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
}

.tab-nav-item {
    padding: 12px 24px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: var(--secondary);
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    font-weight: 500;
}

.tab-nav-item:hover {
    color: var(--primary);
    background: color-mix(in srgb, var(--text) 2%, transparent);
}

[data-theme="dark"] .tab-nav-item:hover {
    background: color-mix(in srgb, white 3%, transparent);
}

.tab-nav-item.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card);
    padding: 24px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent 0%, currentColor 50%, transparent 100%);
    opacity: 0.2;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--text) 12%, transparent);
}

.stat-card.materials { 
    border-left-color: var(--primary);
    color: var(--primary);
    background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, var(--primary) 5%, transparent) 100%);
}

.stat-card.catalog { 
    border-left-color: #8b5cf6;
    color: #8b5cf6;
    background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, #8b5cf6 5%, transparent) 100%);
}

.stat-card.assets { 
    border-left-color: var(--success);
    color: var(--success);
    background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, var(--success) 5%, transparent) 100%);
}

.stat-card.maintenance { 
    border-left-color: var(--warning);
    color: var(--warning);
    background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, var(--warning) 5%, transparent) 100%);
}

[data-theme="dark"] .stat-card {
    box-shadow: 0 2px 8px color-mix(in srgb, black 30%, transparent);
}

[data-theme="dark"] .stat-card:hover {
    box-shadow: 0 4px 12px color-mix(in srgb, black 40%, transparent);
}

.stat-card-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
}

.stat-card-label {
    font-size: 14px;
    color: var(--secondary);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px color-mix(in srgb, var(--text) 8%, transparent);
}

.data-table thead {
    background: var(--primary);
    color: white;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    color: var(--text);
}

.data-table tbody tr {
    background: var(--card);
}

.data-table tbody tr:hover {
    background: var(--bg);
}

[data-theme="dark"] .data-table tbody tr:hover {
    background: color-mix(in srgb, white 3%, transparent);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: color-mix(in srgb, var(--text) 50%, transparent);
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex !important;
}

.modal[style*="flex"] {
    display: flex !important;
}

.modal-content {
    background: var(--card);
    padding: 30px;
    border-radius: 12px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    position: relative;
    color: var(--text);
}

[data-theme="dark"] .modal-content {
    box-shadow: 0 10px 40px color-mix(in srgb, black 50%, transparent);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--secondary);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
    background: var(--input);
    color: var(--text);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 10%, transparent);
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    z-index: 2000;
    display: none;
}

.notification.success { background: var(--success); }
.notification.error { background: var(--danger); }

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.badge.active { 
    background: color-mix(in srgb, var(--success) 15%, var(--card)); 
    color: var(--success); 
}
.badge.inactive { 
    background: color-mix(in srgb, var(--danger) 15%, var(--card)); 
    color: var(--danger); 
}
.badge.product { 
    background: color-mix(in srgb, var(--primary) 15%, var(--card)); 
    color: var(--primary); 
}
.badge.service { 
    background: color-mix(in srgb, #8b5cf6 15%, var(--card)); 
    color: #8b5cf6; 
}

[data-theme="dark"] .badge.active { 
    background: color-mix(in srgb, var(--success) 20%, var(--card)); 
}
[data-theme="dark"] .badge.inactive { 
    background: color-mix(in srgb, var(--danger) 20%, var(--card)); 
}
[data-theme="dark"] .badge.product { 
    background: color-mix(in srgb, var(--primary) 20%, var(--card)); 
}
[data-theme="dark"] .badge.service { 
    background: color-mix(in srgb, #8b5cf6 20%, var(--card)); 
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: var(--primary, #007bff);
    color: white;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 30%, transparent);
}

.btn-outline {
    background: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-outline:hover {
    background: var(--bg);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: color-mix(in srgb, var(--danger) 90%, black);
    transform: translateY(-1px);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Resources-specific styles with balanced colors */
.resources-header h1 {
    color: var(--text);
}

.help-btn {
    background: var(--card);
    border: 1px solid var(--border);
    cursor: pointer;
    font-size: 14px;
    color: var(--secondary);
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.help-btn:hover {
    background: var(--bg);
    border-color: var(--primary);
    color: var(--primary);
}

.resources-rule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.rule-item {
    padding: 20px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.rule-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: currentColor;
    opacity: 0.6;
}

.rule-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--text) 10%, transparent);
}

[data-theme="dark"] .rule-item:hover {
    box-shadow: 0 4px 12px color-mix(in srgb, black 30%, transparent);
}

.rule-item.materials { 
    color: var(--primary);
    border-left-color: var(--primary);
}

.rule-item.catalog { 
    color: #8b5cf6;
    border-left-color: #8b5cf6;
}

.rule-item.assets { 
    color: var(--success);
    border-left-color: var(--success);
}

.rule-item.maintenance { 
    color: var(--warning);
    border-left-color: var(--warning);
}

.rule-item strong {
    display: block;
    font-size: 16px;
    margin-bottom: 8px;
    color: var(--text);
}

.rule-item em {
    font-style: italic;
    display: block;
    margin-bottom: 12px;
    color: var(--secondary);
    font-size: 14px;
}

.rule-item > div {
    font-size: 13px;
    color: var(--text);
    opacity: 0.9;
}

.stat-card-header {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.stat-card-title {
    font-size: 13px;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-card-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-card-number {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text);
}

.stat-card-detail {
    font-size: 13px;
    color: var(--secondary);
    margin-bottom: 4px;
}

.stat-card-alert {
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 4px;
    margin-top: 12px;
    margin-bottom: 8px;
}

.stat-card-alert.warning {
    background: color-mix(in srgb, var(--warning) 10%, transparent);
    color: var(--warning);
    border: 1px solid color-mix(in srgb, var(--warning) 20%, transparent);
}

.stat-card-alert.danger {
    background: color-mix(in srgb, var(--danger) 10%, transparent);
    color: var(--danger);
    border: 1px solid color-mix(in srgb, var(--danger) 20%, transparent);
}

.stat-card-action {
    margin-top: auto;
    padding-top: 16px;
}

.stat-card-action .btn {
    width: 100%;
}

/* Rig maintenance cards */
.rig-maintenance-card {
    padding: 20px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 2px 4px color-mix(in srgb, var(--text) 5%, transparent);
    transition: all 0.3s ease;
}

.rig-maintenance-card:hover {
    box-shadow: 0 4px 8px color-mix(in srgb, var(--text) 10%, transparent);
}

.rig-maintenance-card.due {
    border-left: 4px solid var(--danger);
}

.rig-maintenance-card.soon {
    border-left: 4px solid var(--warning);
}

.rig-maintenance-card.ok {
    border-left: 4px solid var(--success);
}

.rig-status-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.rig-status-badge.due {
    background: var(--danger);
    color: white;
}

.rig-status-badge.soon {
    background: var(--warning);
    color: var(--text);
}

.rig-status-badge.ok {
    background: var(--success);
    color: white;
}

.rpm-progress-bar {
    background: var(--border);
    height: 6px;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 5px;
}

.rpm-progress-fill {
    height: 100%;
    transition: width 0.3s;
}

.rpm-progress-fill.due {
    background: var(--danger);
}

.rpm-progress-fill.soon {
    background: var(--warning);
}

.rpm-progress-fill.ok {
    background: var(--success);
}

/* Highlight selected rig when navigated via rig_id */
.rig-maintenance-card.highlight {
    outline: 2px solid var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent);
}
</style>

<div class="resources-container">
    <div class="resources-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="display: inline-block; margin: 0;">📦 Resources Management</h1>
            <p style="margin: 6px 0 0 0; color: var(--secondary);">Unified system for managing all company resources</p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button onclick="openHelpModal()" class="help-btn" title="Where each item belongs across Materials, Catalog, Assets and Maintenance">Resources Guide</button>
        </div>
    </div>

    <!-- Help Modal -->
    <div id="helpModal" class="modal">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h2>🎯 The Simple Rule</h2>
                <button class="close-btn" onclick="closeHelpModal()">&times;</button>
            </div>
            <div style="padding: 20px;">
                <p style="margin: 0 0 20px 0; font-size: 16px; opacity: 0.95;">
                    Everything in your business follows one simple rule. Use this guide to know where each item belongs:
                </p>
                <div class="resources-rule-grid">
                    <div class="rule-item materials">
                        <strong>📦 Materials</strong>
                        <em>If you USE it</em>
                        <div>
                            Items consumed in operations (pipes, gravel, supplies)
                        </div>
                    </div>
                    <div class="rule-item catalog">
                        <strong>🗂️ Catalog</strong>
                        <em>If you SELL it</em>
                        <div>
                            Products & services you sell/buy (with pricing)
                        </div>
                    </div>
                    <div class="rule-item assets">
                        <strong>🏭 Assets</strong>
                        <em>If you OWN it long-term</em>
                        <div>
                            Equipment you own (rigs, vehicles, tools)
                        </div>
                    </div>
                    <div class="rule-item maintenance">
                        <strong>🔧 Maintenance</strong>
                        <em>If you SERVICE it</em>
                        <div>
                            Keeping assets working (scheduled & repairs)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-nav-item <?php echo $action === 'overview' ? 'active' : ''; ?>" onclick="window.location.href='resources.php?action=overview'">📊 Overview</button>
        <button class="tab-nav-item <?php echo $action === 'materials' ? 'active' : ''; ?>" onclick="window.location.href='resources.php?action=materials'">📦 Materials</button>
        <button class="tab-nav-item <?php echo $action === 'catalog' ? 'active' : ''; ?>" onclick="window.location.href='resources.php?action=catalog'">🗂️ Catalog</button>
        <button class="tab-nav-item <?php echo $action === 'assets' ? 'active' : ''; ?>" onclick="window.location.href='resources.php?action=assets'">🏭 Assets</button>
        <button class="tab-nav-item <?php echo $action === 'maintenance' ? 'active' : ''; ?>" onclick="window.location.href='resources.php?action=maintenance'">🔧 Maintenance</button>
    </div>

    <!-- Overview Tab -->
    <?php if ($action === 'overview'): ?>
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <!-- Materials Card -->
            <div class="stat-card materials" style="min-height: 200px; display: flex; flex-direction: column;">
                <div class="stat-card-header">
                    <div class="stat-card-icon">📦</div>
                    <div class="stat-card-title">Materials</div>
                </div>
                <div class="stat-card-number"><?php echo number_format($stats['materials']['count']); ?></div>
                <div class="stat-card-detail">Material Types</div>
                <div class="stat-card-detail"><?php echo number_format($stats['materials']['total_items']); ?> Total Items</div>
                <div class="stat-card-detail"><?php echo formatCurrency($stats['materials']['total_value']); ?> Total Value</div>
                <?php if ($stats['materials']['low_stock'] > 0): ?>
                    <div class="stat-card-alert warning">
                        ⚠️ <?php echo $stats['materials']['low_stock']; ?> Low Stock
                    </div>
                <?php endif; ?>
                <div class="stat-card-action">
                    <button onclick="window.location.href='resources.php?action=materials'" class="btn btn-sm btn-primary">Manage Materials</button>
                </div>
            </div>

            <!-- Catalog Card -->
            <div class="stat-card catalog" style="min-height: 200px; display: flex; flex-direction: column;">
                <div class="stat-card-header">
                    <div class="stat-card-icon">🗂️</div>
                    <div class="stat-card-title">Catalog</div>
                </div>
                <div class="stat-card-number"><?php echo number_format($stats['catalog']['items']); ?></div>
                <div class="stat-card-detail">Active Items</div>
                <div class="stat-card-detail"><?php echo number_format($stats['catalog']['categories']); ?> Categories</div>
                <div class="stat-card-action">
                    <button onclick="window.location.href='resources.php?action=catalog'" class="btn btn-sm btn-primary">Manage Catalog</button>
                </div>
            </div>

            <!-- Assets Card -->
            <div class="stat-card assets" style="min-height: 200px; display: flex; flex-direction: column;">
                <div class="stat-card-header">
                    <div class="stat-card-icon">🏭</div>
                    <div class="stat-card-title">Assets</div>
                </div>
                <div class="stat-card-number"><?php echo number_format($stats['assets']['count']); ?></div>
                <div class="stat-card-detail">Total Assets</div>
                <div class="stat-card-detail"><?php echo number_format($stats['assets']['active']); ?> Active</div>
                <div class="stat-card-detail"><?php echo formatCurrency($stats['assets']['total_value']); ?> Total Value</div>
                <div class="stat-card-action">
                    <button onclick="window.location.href='resources.php?action=assets'" class="btn btn-sm btn-primary">Manage Assets</button>
                </div>
            </div>

            <!-- Maintenance Card -->
            <div class="stat-card maintenance" style="min-height: 200px; display: flex; flex-direction: column;">
                <div class="stat-card-header">
                    <div class="stat-card-icon">🔧</div>
                    <div class="stat-card-title">Maintenance</div>
                </div>
                <div class="stat-card-number"><?php echo number_format($stats['maintenance']['pending']); ?></div>
                <div class="stat-card-detail">Pending Tasks</div>
                <div class="stat-card-detail"><?php echo number_format($stats['maintenance']['due_soon']); ?> Due Soon</div>
                <?php if ($stats['maintenance']['overdue'] > 0): ?>
                    <div class="stat-card-alert danger">
                        ⚠️ <?php echo $stats['maintenance']['overdue']; ?> Overdue
                    </div>
                <?php endif; ?>
                <div class="stat-card-action">
                    <button onclick="window.location.href='resources.php?action=maintenance'" class="btn btn-sm btn-primary">Manage Maintenance</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Materials Tab -->
    <?php if ($action === 'materials'): ?>
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h2>📦 Materials Management</h2>
            <button onclick="openMaterialModal()" class="btn btn-primary">➕ Add Material</button>
        </div>
        
        <div class="dashboard-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Material Name</th>
                        <th>Type</th>
                        <th>Received</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materials)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px;">No materials found. Click "Add Material" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($materials as $m): ?>
                            <?php 
                            $remaining = intval($m['quantity_remaining'] ?? 0);
                            $isLowStock = $remaining < 10;
                            ?>
                            <tr>
                                <td><?php echo e($m['material_name']); ?></td>
                                <td><?php echo e($m['material_type']); ?></td>
                                <td><?php echo number_format($m['quantity_received']); ?></td>
                                <td><?php echo number_format($m['quantity_used']); ?></td>
                                <td style="<?php echo $isLowStock ? 'color: var(--danger); font-weight: 600;' : ''; ?>">
                                    <?php echo number_format($remaining); ?>
                                    <?php if ($isLowStock): ?>
                                        <span style="color: var(--danger); font-size: 11px;">⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($m['unit_cost']); ?></td>
                                <td><?php echo formatCurrency($m['total_value']); ?></td>
                                <td><?php echo e($m['unit_of_measure'] ?: 'pcs'); ?></td>
                                <td>
                                    <button onclick="openInventoryModal('material', <?php echo $m['id']; ?>, '<?php echo e($m['material_name']); ?>', <?php echo $remaining; ?>)" class="btn btn-sm btn-outline" style="margin-bottom: 5px; display: block; width: 100%;">Manage Inventory</button>
                                    <button onclick="openMaterialModal(<?php echo htmlspecialchars(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                                    <button onclick="deleteResource('material', <?php echo $m['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Catalog Tab -->
    <?php if ($action === 'catalog'): ?>
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h2>🗂️ Catalog Management</h2>
            <div style="display: flex; gap: 10px;">
                <button onclick="openCategoryModal()" class="btn btn-outline">➕ Add Category</button>
                <button onclick="openCatalogItemModal()" class="btn btn-primary">➕ Add Item</button>
            </div>
        </div>
        
        <!-- Categories Section -->
        <div class="dashboard-card" style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <span>📁 Categories</span>
                <button onclick="openCategoryModal()" class="btn btn-sm btn-outline">➕ Add</button>
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                <?php if (empty($catalogCategories)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--secondary);">
                        No categories found. Click "Add Category" to get started.
                    </div>
                <?php else: ?>
                    <?php foreach ($catalogCategories as $cat): ?>
                        <div style="padding: 15px; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <strong style="font-size: 16px;"><?php echo e($cat['name']); ?></strong>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="openCategoryModal(<?php echo htmlspecialchars(json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 11px;">Edit</button>
                                    <button onclick="deleteResource('catalog_category', <?php echo $cat['id']; ?>)" class="btn btn-sm btn-danger" style="padding: 4px 8px; font-size: 11px;">Delete</button>
                                </div>
                            </div>
                            <?php if (!empty($cat['description'])): ?>
                                <div style="font-size: 13px; color: var(--secondary); margin-top: 8px;">
                                    <?php echo e($cat['description']); ?>
                                </div>
                            <?php endif; ?>
                            <?php
                            $itemCount = 0;
                            foreach ($catalogItems as $item) {
                                if ($item['category_id'] == $cat['id']) $itemCount++;
                            }
                            ?>
                            <div style="font-size: 12px; color: var(--secondary); margin-top: 8px;">
                                <?php echo $itemCount; ?> item(s)
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Catalog Items -->
        <div class="dashboard-card">
            <h3 style="margin-bottom: 15px;">📦 Catalog Items</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Inventory</th>
                        <th>Cost Price</th>
                        <th>Sell Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($catalogItems)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px;">No items found. Click "Add Item" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($catalogItems as $item): ?>
                            <?php 
                            $inventoryQty = intval($item['inventory_quantity'] ?? 0);
                            $reorderLevel = intval($item['reorder_level'] ?? 0);
                            $isLowStock = $reorderLevel > 0 && $inventoryQty <= $reorderLevel;
                            ?>
                            <tr>
                                <td><?php echo e($item['name']); ?></td>
                                <td><?php echo e($item['sku'] ?: '—'); ?></td>
                                <td><span class="badge <?php echo $item['item_type']; ?>"><?php echo ucfirst($item['item_type']); ?></span></td>
                                <td><?php echo e($item['category_name'] ?: '—'); ?></td>
                                <td>
                                    <span style="font-weight: 600; <?php echo $isLowStock ? 'color: var(--danger);' : ''; ?>">
                                        <?php echo number_format($inventoryQty); ?>
                                    </span>
                                    <?php if ($isLowStock): ?>
                                        <span style="color: var(--danger); font-size: 11px;">⚠️ Low</span>
                                    <?php endif; ?>
                                    <br>
                                    <button onclick="openInventoryModal('catalog', <?php echo $item['id']; ?>, '<?php echo e($item['name']); ?>', <?php echo $inventoryQty; ?>)" class="btn btn-sm btn-outline" style="margin-top: 5px; padding: 2px 8px; font-size: 11px;">Manage</button>
                                </td>
                                <td><?php echo formatCurrency($item['cost_price']); ?></td>
                                <td><?php echo formatCurrency($item['sell_price']); ?></td>
                                <td><span class="badge <?php echo $item['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <button onclick="openCatalogItemModal(<?php echo htmlspecialchars(json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                                    <button onclick="deleteResource('catalog_item', <?php echo $item['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Assets Tab -->
    <?php if ($action === 'assets'): ?>
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h2>🏭 Assets Management</h2>
            <button onclick="openAssetModal()" class="btn btn-primary">➕ Add Asset</button>
        </div>
        
        <div class="dashboard-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Type</th>
                        <th>Purchase Cost</th>
                        <th>Current Value</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 40px;">No assets found. Click "Add Asset" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assets as $asset): ?>
                            <?php 
                            $isRig = isset($asset['is_rig']) && $asset['is_rig'];
                            $rigMaintenanceStatus = $asset['maintenance_status'] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <?php echo e($asset['asset_code']); ?>
                                    <?php if ($isRig): ?>
                                        <span class="badge" style="background: var(--secondary); color: white; font-size: 10px; margin-left: 5px;">RIG</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($asset['asset_name']); ?></td>
                                <td><?php echo ucfirst($asset['asset_type']); ?></td>
                                <td><?php echo formatCurrency($asset['purchase_cost']); ?></td>
                                <td><?php echo formatCurrency($asset['current_value']); ?></td>
                                                                 <td>
                                     <span class="badge <?php echo $asset['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($asset['status']); ?></span>
                                     <?php if ($isRig && $rigMaintenanceStatus): ?>
                                         <br>
                                         <span class="badge <?php echo $rigMaintenanceStatus === 'due' ? 'badge-danger' : ($rigMaintenanceStatus === 'soon' ? 'badge-warning' : 'badge-success'); ?>" style="font-size: 10px; margin-top: 4px;">
                                             Maintenance: <?php echo ucfirst($rigMaintenanceStatus); ?>
                                         </span>
                                     <?php endif; ?>
                                 </td>
                                 <td>
                                     <?php if ($isRig): ?>
                                         <span class="badge" style="background: var(--secondary); color: white;">Configuration</span>
                                     <?php else: ?>
                                         <span class="badge" style="background: var(--primary); color: white;">Resources</span>
                                     <?php endif; ?>
                                 </td>
                                 <td>
                                    <?php if ($isRig): ?>
                                        <a href="config.php#rigs-tab" class="btn btn-sm btn-outline" title="Edit in Configuration">⚙️ Edit</a>
                                        <a href="resources.php?action=maintenance&rig_id=<?php echo $asset['rig_id']; ?>" class="btn btn-sm btn-primary" title="View Maintenance">🔧 Maintenance</a>
                                    <?php else: ?>
                                        <button onclick="openAssetModal(<?php echo htmlspecialchars(json_encode($asset, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                                        <button onclick="deleteResource('asset', <?php echo $asset['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Maintenance Tab -->
    <?php if ($action === 'maintenance'): 
        // Get rigs with RPM maintenance info
        require_once '../includes/config-manager.php';
        $configManager = new ConfigManager();
        $allRigs = $configManager->getRigs();
        $selectedRigId = isset($_GET['rig_id']) ? intval($_GET['rig_id']) : null;
    ?>
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h2>🔧 Maintenance Management</h2>
            <button onclick="openMaintenanceModal()" class="btn btn-primary">➕ Add Maintenance</button>
        </div>
        
        <!-- Rigs RPM-Based Maintenance -->
        <div class="dashboard-card" style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px;">🚛 Rigs Maintenance (RPM-Based)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                <?php if (empty($allRigs)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: var(--secondary);">
                        No rigs found. Add rigs in System → Configuration.
                    </div>
                <?php else: ?>
                    <?php foreach ($allRigs as $rig): 
                        $currentRpm = floatval($rig['current_rpm'] ?? 0);
                        $dueAtRpm = $rig['maintenance_due_at_rpm'] ? floatval($rig['maintenance_due_at_rpm']) : null;
                        $interval = floatval($rig['maintenance_rpm_interval'] ?? 30);
                        $lastRpm = floatval($rig['last_maintenance_rpm'] ?? 0);
                        $status = $rig['maintenance_status'] ?? 'ok';
                        $rpmRemaining = $dueAtRpm ? max(0, $dueAtRpm - $currentRpm) : null;
                        $progress = $dueAtRpm && $currentRpm > 0 ? min(100, ($currentRpm / $dueAtRpm) * 100) : 0;
                        
                        $statusColor = '#28a745';
                        $statusText = 'OK';
                        if ($status === 'due') {
                            $statusColor = '#dc3545';
                            $statusText = 'Due Now';
                        } elseif ($status === 'soon') {
                            $statusColor = '#ffc107';
                            $statusText = 'Due Soon';
                        }
                    ?>
                        <div id="rig-card-<?php echo $rig['id']; ?>" class="rig-maintenance-card <?php echo $status; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                <div>
                                    <strong style="font-size: 18px; color: var(--text);"><?php echo e($rig['rig_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;"><?php echo e($rig['rig_code']); ?></div>
                                </div>
                                <span class="rig-status-badge <?php echo $status; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="font-size: 13px; color: var(--secondary);">Current RPM:</span>
                                    <strong style="font-size: 16px; color: var(--primary);"><?php echo number_format($currentRpm, 2); ?></strong>
                                </div>
                                <?php if ($dueAtRpm): ?>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="font-size: 13px; color: var(--secondary);">Due At RPM:</span>
                                        <strong style="font-size: 14px; color: var(--text);"><?php echo number_format($dueAtRpm, 2); ?></strong>
                                    </div>
                                    <div class="rpm-progress-bar">
                                        <div class="rpm-progress-fill <?php echo $status; ?>" style="width: <?php echo $progress; ?>%;"></div>
                                    </div>
                                    <div style="font-size: 12px; color: var(--secondary); text-align: center;">
                                        <?php echo number_format($rpmRemaining, 2); ?> RPM remaining
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 12px; color: var(--secondary); text-align: center; padding: 10px;">
                                        RPM maintenance not configured
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 8px; margin-top: 15px;">
                                <a href="resources.php?action=maintenance&rig_id=<?php echo $rig['id']; ?>#maintenance-records" class="btn btn-sm btn-primary" style="flex: 1; text-align: center; padding: 8px;">View Details</a>
                                <a href="config.php#rigs-tab" class="btn btn-sm btn-outline" style="flex: 1; text-align: center; padding: 8px;">Configure</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- General Maintenance Records -->
        <div class="dashboard-card" id="maintenance-records">
            <h3 style="margin-bottom: 15px;">
                📋 <?php if (!empty($selectedRigId) && !empty($selectedRigName)): ?>Maintenance Records for <?php echo e($selectedRigName); ?><?php else: ?>All Maintenance Records<?php endif; ?>
            </h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Maintenance Code</th>
                        <th>Asset</th>
                        <th>Type</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Total Cost</th>
                        <th>Linked Report</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($maintenanceRecords)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px;">No maintenance records found. Click "Add Maintenance" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($maintenanceRecords as $maint): ?>
                            <tr>
                                <td><?php echo e($maint['maintenance_code']); ?></td>
                                <td><?php echo e($maint['asset_name'] ?: '—'); ?></td>
                                <td><?php echo e($maint['type_name'] ?: '—'); ?></td>
                                <td><?php echo $maint['scheduled_date'] ? date('M j, Y', strtotime($maint['scheduled_date'])) : '—'; ?></td>
                                <td><span class="badge <?php echo $maint['status'] === 'completed' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($maint['status']); ?></span></td>
                                <td><?php echo ucfirst($maint['priority']); ?></td>
                                <td><?php echo formatCurrency($maint['total_cost']); ?></td>
                                <td>
                                    <?php if (!empty($maint['field_report_id'])): ?>
                                        <a href="field-reports-list.php?id=<?php echo $maint['field_report_id']; ?>" 
                                           class="btn btn-sm btn-outline" 
                                           title="View Field Report"
                                           style="font-size: 11px; padding: 4px 8px;">
                                            📄 <?php echo e($maint['report_id'] ?? 'Report #' . $maint['field_report_id']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--secondary); font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="openMaintenanceModal(<?php echo htmlspecialchars(json_encode($maint, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                                    <button onclick="deleteResource('maintenance', <?php echo $maint['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Material Modal -->
<div id="materialModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="materialModalTitle">Add Material</h2>
            <button class="close-btn" onclick="closeMaterialModal()">&times;</button>
        </div>
        <form id="materialForm" onsubmit="saveMaterial(event)">
            <input type="hidden" name="action" id="materialAction" value="add_material">
            <input type="hidden" name="id" id="materialId" value="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="form-group">
                <label>Material Type *</label>
                <input type="text" name="material_type" id="materialType" required placeholder="e.g., Screen Pipe">
            </div>
            
            <div class="form-group">
                <label>Material Name *</label>
                <input type="text" name="material_name" id="materialName" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Quantity Received</label>
                    <input type="number" name="quantity_received" id="quantityReceived" min="0" value="0" onchange="updateMaterialValue()">
                </div>
                <div class="form-group">
                    <label>Unit Cost (GHS)</label>
                    <input type="number" name="unit_cost" id="unitCost" step="0.01" min="0" value="0.00" onchange="updateMaterialValue()">
                </div>
            </div>
            
            <div id="materialEditFields" style="display: none;">
                <div class="form-group">
                    <label>Quantity Used</label>
                    <input type="number" name="quantity_used" id="quantityUsed" min="0" value="0" onchange="updateMaterialValue()">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Unit of Measure</label>
                    <select name="unit_of_measure" id="unitOfMeasure">
                        <option value="pcs">Pieces</option>
                        <option value="kg">Kilograms</option>
                        <option value="m">Meters</option>
                        <option value="l">Liters</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" id="supplier">
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="materialNotes" rows="3"></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeMaterialModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Material</button>
            </div>
        </form>
    </div>
</div>

<!-- Catalog Item Modal -->
<div id="catalogItemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="catalogItemModalTitle">Add Catalog Item</h2>
            <button class="close-btn" onclick="closeCatalogItemModal()">&times;</button>
        </div>
        <form id="catalogItemForm" onsubmit="saveCatalogItem(event)">
            <input type="hidden" name="action" id="catalogItemAction" value="add_catalog_item">
            <input type="hidden" name="id" id="catalogItemId" value="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="name" id="itemName" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" id="itemSku">
                </div>
                <div class="form-group">
                    <label>Item Type *</label>
                    <select name="item_type" id="itemType" required>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" id="itemCategory">
                    <option value="">— Select Category —</option>
                    <?php foreach ($catalogCategories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Cost Price (GHS)</label>
                    <input type="number" name="cost_price" id="itemCostPrice" step="0.01" min="0" value="0.00">
                </div>
                <div class="form-group">
                    <label>Sell Price (GHS)</label>
                    <input type="number" name="sell_price" id="itemSellPrice" step="0.01" min="0" value="0.00">
                </div>
            </div>
            
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" id="itemUnit" placeholder="e.g., pcs, kg, hours">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Inventory Quantity</label>
                    <input type="number" name="inventory_quantity" id="itemInventoryQuantity" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="number" name="reorder_level" id="itemReorderLevel" min="0" value="0" placeholder="Alert when stock is at or below this">
                </div>
            </div>
            
            <div style="display: flex; gap: 20px; margin: 15px 0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="taxable" id="itemTaxable">
                    <span>Taxable</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="itemActive" checked>
                    <span>Active</span>
                </label>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="itemNotes" rows="3"></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeCatalogItemModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="categoryModalTitle">Add Category</h2>
            <button class="close-btn" onclick="closeCategoryModal()">&times;</button>
        </div>
        <form id="categoryForm" onsubmit="saveCategory(event)">
            <input type="hidden" name="action" id="categoryAction" value="add_catalog_category">
            <input type="hidden" name="id" id="categoryId" value="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" id="categoryName" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="categoryDescription" rows="3"></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Management Modal -->
<div id="inventoryModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="inventoryModalTitle">Manage Inventory</h2>
            <button class="close-btn" onclick="closeInventoryModal()">&times;</button>
        </div>
        <form id="inventoryForm" onsubmit="saveInventoryTransaction(event)">
            <input type="hidden" name="action" value="update_inventory">
            <input type="hidden" name="item_type" id="invItemType">
            <input type="hidden" name="item_id" id="invItemId">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="form-group">
                <label>Item</label>
                <input type="text" id="invItemName" readonly style="background: var(--bg);">
            </div>
            
            <div class="form-group">
                <label>Current Stock</label>
                <input type="number" id="invCurrentStock" readonly style="background: var(--bg); font-weight: 600;">
            </div>
            
            <div class="form-group">
                <label>Transaction Type *</label>
                <select name="transaction_type" id="invTransactionType" required onchange="toggleInventoryFields()">
                    <option value="">— Select —</option>
                    <option value="purchase">➕ Purchase (Add Stock)</option>
                    <option value="sale">➖ Sale (Reduce Stock)</option>
                    <option value="use">🔧 Use in Project (Reduce Stock)</option>
                    <option value="adjustment">📝 Adjustment (Correct Count)</option>
                    <option value="return">↩️ Return (Add Stock)</option>
                </select>
            </div>
            
            <div id="invSupplierFields" style="display: none;">
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id" id="invSupplierId">
                        <option value="">— Select Supplier —</option>
                        <?php
                        try {
                            $suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers WHERE status = 'active' ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($suppliers as $supp): ?>
                                <option value="<?php echo $supp['id']; ?>"><?php echo e($supp['supplier_name']); ?></option>
                            <?php endforeach;
                        } catch (PDOException $e) {
                            // Suppliers table might not exist yet
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" id="invQuantity" min="1" required>
                </div>
                <div class="form-group" id="invCostField" style="display: none;">
                    <label>Unit Cost (GHS)</label>
                    <input type="number" name="unit_cost" id="invUnitCost" step="0.01" min="0" value="0.00">
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="invNotes" rows="3" placeholder="Optional: Add notes about this transaction"></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeInventoryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- Asset Modal -->
<div id="assetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="assetModalTitle">Add Asset</h2>
            <button class="close-btn" onclick="closeAssetModal()">&times;</button>
        </div>
        <form id="assetForm" onsubmit="saveAsset(event)">
            <input type="hidden" name="action" id="assetAction" value="add_asset">
            <input type="hidden" name="id" id="assetId" value="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div class="form-group">
                <label>Asset Code *</label>
                <input type="text" name="asset_code" id="assetCode" required placeholder="e.g., RIG-001">
            </div>
            
            <div class="form-group">
                <label>Asset Name *</label>
                <input type="text" name="asset_name" id="assetName" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Asset Type *</label>
                    <select name="asset_type" id="assetType" required>
                        <option value="rig">Rig</option>
                        <option value="vehicle">Vehicle</option>
                        <option value="equipment">Equipment</option>
                        <option value="tool">Tool</option>
                        <option value="building">Building</option>
                        <option value="land">Land</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="assetCategory">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" id="assetBrand">
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" id="assetModel">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" id="purchaseDate">
                </div>
                <div class="form-group">
                    <label>Purchase Cost (GHS)</label>
                    <input type="number" name="purchase_cost" id="purchaseCost" step="0.01" min="0" value="0.00" onchange="updateAssetValue()">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Current Value (GHS)</label>
                    <input type="number" name="current_value" id="currentValue" step="0.01" min="0" value="0.00">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="assetLocation">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="assetStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="retired">Retired</option>
                        <option value="sold">Sold</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition" id="assetCondition">
                        <option value="excellent">Excellent</option>
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="assetNotes" rows="3"></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeAssetModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Maintenance Modal -->
<div id="maintenanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="maintenanceModalTitle">Add Maintenance</h2>
            <button class="close-btn" onclick="closeMaintenanceModal()">&times;</button>
        </div>
        <form id="maintenanceForm" onsubmit="saveMaintenance(event)">
            <input type="hidden" name="action" id="maintenanceAction" value="add_maintenance">
            <input type="hidden" name="id" id="maintenanceId" value="">
            <?php echo CSRF::getTokenField(); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Maintenance Type</label>
                    <select name="maintenance_type_id" id="maintenanceTypeId">
                        <option value="">— Select Type —</option>
                        <?php foreach ($maintenanceTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo e($type['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="maintenance_category" id="maintenanceCategory" required>
                        <option value="proactive">Proactive</option>
                        <option value="reactive">Reactive</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Asset</label>
                <select name="asset_id" id="maintenanceAssetId">
                    <option value="">— Select Asset —</option>
                    <?php foreach ($assets as $asset): ?>
                        <option value="<?php echo $asset['id']; ?>"><?php echo e($asset['asset_name'] . ' (' . $asset['asset_code'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="datetime-local" name="scheduled_date" id="scheduledDate">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="maintenanceStatus" onchange="toggleMaintenanceFields()">
                        <option value="logged">Logged</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" id="maintenancePriority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
            
            <div id="maintenanceEditFields" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Started Date</label>
                        <input type="datetime-local" name="started_date" id="startedDate">
                    </div>
                    <div class="form-group">
                        <label>Completed Date</label>
                        <input type="datetime-local" name="completed_date" id="completedDate">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="maintenanceDescription" rows="3"></textarea>
            </div>
            
            <div id="maintenanceWorkFields">
                <div class="form-group">
                    <label>Work Performed</label>
                    <textarea name="work_performed" id="workPerformed" rows="4"></textarea>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Parts Cost (GHS)</label>
                    <input type="number" name="parts_cost" id="partsCost" step="0.01" min="0" value="0.00" onchange="updateMaintenanceTotal()">
                </div>
                <div class="form-group">
                    <label>Labor Cost (GHS)</label>
                    <input type="number" name="labor_cost" id="laborCost" step="0.01" min="0" value="0.00" onchange="updateMaintenanceTotal()">
                </div>
            </div>
            
            <div class="form-group">
                <label>Total Cost (GHS)</label>
                <input type="number" id="totalCostDisplay" step="0.01" readonly style="background: var(--bg); font-weight: 600;">
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="maintenanceNotes" rows="3"></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeMaintenanceModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Maintenance</button>
            </div>
        </form>
    </div>
</div>

<div id="notification" class="notification"></div>

<script>
// Theme initialization - ensure theme toggle works properly
(function(){
    try {
        // Apply theme from localStorage or session
        var savedTheme = localStorage.getItem('abbis_theme_mode') || localStorage.getItem('abbis_theme') || 'system';
        var effectiveTheme = 'light';
        
        if (savedTheme === 'dark') {
            effectiveTheme = 'dark';
        } else if (savedTheme === 'system') {
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            effectiveTheme = prefersDark ? 'dark' : 'light';
        }
        
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.body && document.body.setAttribute('data-theme', effectiveTheme);
        
        // Listen for theme changes from main.js
        if (window.abbisApp && typeof window.abbisApp.initializeTheme === 'function') {
            window.abbisApp.initializeTheme();
        }
    } catch(e) {
        console.warn('Theme initialization error:', e);
    }
})();
// Material Modal Functions
function openMaterialModal(material = null) {
    const modal = document.getElementById('materialModal');
    const form = document.getElementById('materialForm');
    const title = document.getElementById('materialModalTitle');
    const editFields = document.getElementById('materialEditFields');
    
    form.reset();
    if (material) {
        title.textContent = 'Edit Material';
        document.getElementById('materialAction').value = 'edit_material';
        document.getElementById('materialId').value = material.id;
        document.getElementById('materialType').value = material.material_type || '';
        document.getElementById('materialName').value = material.material_name || '';
        document.getElementById('quantityReceived').value = material.quantity_received || 0;
        document.getElementById('quantityUsed').value = material.quantity_used || 0;
        document.getElementById('unitCost').value = material.unit_cost || '0.00';
        document.getElementById('unitOfMeasure').value = material.unit_of_measure || 'pcs';
        document.getElementById('supplier').value = material.supplier || '';
        document.getElementById('materialNotes').value = material.notes || '';
        editFields.style.display = 'block';
    } else {
        title.textContent = 'Add Material';
        document.getElementById('materialAction').value = 'add_material';
        document.getElementById('materialId').value = '';
        editFields.style.display = 'none';
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeMaterialModal() {
    document.getElementById('materialModal').style.display = 'none';
    document.getElementById('materialModal').classList.remove('active');
}

function updateMaterialValue() {
    const received = parseFloat(document.getElementById('quantityReceived').value) || 0;
    const used = parseFloat(document.getElementById('quantityUsed').value) || 0;
    const cost = parseFloat(document.getElementById('unitCost').value) || 0;
    // Value calculation happens on server side
}

function saveMaterial(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('materialForm'));
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Save failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

// Catalog Item Modal Functions
function openCatalogItemModal(item = null) {
    const modal = document.getElementById('catalogItemModal');
    const form = document.getElementById('catalogItemForm');
    const title = document.getElementById('catalogItemModalTitle');
    
    form.reset();
    if (item) {
        title.textContent = 'Edit Catalog Item';
        document.getElementById('catalogItemAction').value = 'edit_catalog_item';
        document.getElementById('catalogItemId').value = item.id;
        document.getElementById('itemName').value = item.name || '';
        document.getElementById('itemSku').value = item.sku || '';
        document.getElementById('itemType').value = item.item_type || 'product';
        document.getElementById('itemCategory').value = item.category_id || '';
        document.getElementById('itemCostPrice').value = item.cost_price || '0.00';
        document.getElementById('itemSellPrice').value = item.sell_price || '0.00';
        document.getElementById('itemUnit').value = item.unit || '';
        document.getElementById('itemTaxable').checked = item.taxable == 1;
        document.getElementById('itemActive').checked = item.is_active == 1;
        document.getElementById('itemInventoryQuantity').value = item.inventory_quantity || 0;
        document.getElementById('itemReorderLevel').value = item.reorder_level || 0;
        document.getElementById('itemNotes').value = item.notes || '';
    } else {
        title.textContent = 'Add Catalog Item';
        document.getElementById('catalogItemAction').value = 'add_catalog_item';
        document.getElementById('catalogItemId').value = '';
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeCatalogItemModal() {
    document.getElementById('catalogItemModal').style.display = 'none';
    document.getElementById('catalogItemModal').classList.remove('active');
}

function saveCatalogItem(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('catalogItemForm'));
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Save failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

// Help Modal Functions
function openHelpModal() {
    const modal = document.getElementById('helpModal');
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeHelpModal() {
    const modal = document.getElementById('helpModal');
    modal.style.display = 'none';
    modal.classList.remove('active');
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeHelpModal();
        // Also close other modals
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.style.display = 'none';
            modal.classList.remove('active');
        });
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        e.target.classList.remove('active');
    }
});

// Category Modal Functions
function openCategoryModal(category = null) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const title = document.getElementById('categoryModalTitle');
    
    form.reset();
    if (category) {
        title.textContent = 'Edit Category';
        document.getElementById('categoryAction').value = 'edit_catalog_category';
        document.getElementById('categoryId').value = category.id;
        document.getElementById('categoryName').value = category.name || '';
        document.getElementById('categoryDescription').value = category.description || '';
    } else {
        title.textContent = 'Add Category';
        document.getElementById('categoryAction').value = 'add_catalog_category';
        document.getElementById('categoryId').value = '';
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
    document.getElementById('categoryModal').classList.remove('active');
}

function saveCategory(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('categoryForm'));
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeCategoryModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Save failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

// Inventory Management Modal Functions
function openInventoryModal(itemType, itemId, itemName, currentStock) {
    const modal = document.getElementById('inventoryModal');
    const form = document.getElementById('inventoryForm');
    
    form.reset();
    document.getElementById('invItemType').value = itemType;
    document.getElementById('invItemId').value = itemId;
    document.getElementById('invItemName').value = itemName;
    document.getElementById('invCurrentStock').value = currentStock;
    
    modal.style.display = 'flex';
    modal.classList.add('active');
    toggleInventoryFields();
}

function closeInventoryModal() {
    document.getElementById('inventoryModal').style.display = 'none';
    document.getElementById('inventoryModal').classList.remove('active');
}

function toggleInventoryFields() {
    const transType = document.getElementById('invTransactionType').value;
    const supplierFields = document.getElementById('invSupplierFields');
    const costField = document.getElementById('invCostField');
    
    // Show supplier field for purchases
    supplierFields.style.display = (transType === 'purchase' || transType === 'return') ? 'block' : 'none';
    
    // Show cost field for purchases and returns
    costField.style.display = (transType === 'purchase' || transType === 'return') ? 'block' : 'none';
}

function saveInventoryTransaction(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('inventoryForm'));
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeInventoryModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Transaction failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

// Asset Modal Functions
function openAssetModal(asset = null) {
    const modal = document.getElementById('assetModal');
    const form = document.getElementById('assetForm');
    const title = document.getElementById('assetModalTitle');
    
    form.reset();
    if (asset) {
        title.textContent = 'Edit Asset';
        document.getElementById('assetAction').value = 'edit_asset';
        document.getElementById('assetId').value = asset.id;
        document.getElementById('assetCode').value = asset.asset_code || '';
        document.getElementById('assetName').value = asset.asset_name || '';
        document.getElementById('assetType').value = asset.asset_type || 'equipment';
        document.getElementById('assetCategory').value = asset.category || '';
        document.getElementById('assetBrand').value = asset.brand || '';
        document.getElementById('assetModel').value = asset.model || '';
        document.getElementById('purchaseDate').value = asset.purchase_date || '';
        document.getElementById('purchaseCost').value = asset.purchase_cost || '0.00';
        document.getElementById('currentValue').value = asset.current_value || '0.00';
        document.getElementById('assetLocation').value = asset.location || '';
        document.getElementById('assetStatus').value = asset.status || 'active';
        document.getElementById('assetCondition').value = asset.condition || 'good';
        document.getElementById('assetNotes').value = asset.notes || '';
    } else {
        title.textContent = 'Add Asset';
        document.getElementById('assetAction').value = 'add_asset';
        document.getElementById('assetId').value = '';
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeAssetModal() {
    document.getElementById('assetModal').style.display = 'none';
    document.getElementById('assetModal').classList.remove('active');
}

function updateAssetValue() {
    const purchaseCost = parseFloat(document.getElementById('purchaseCost').value) || 0;
    const currentValue = document.getElementById('currentValue');
    if (!currentValue.value || parseFloat(currentValue.value) === 0) {
        currentValue.value = purchaseCost.toFixed(2);
    }
}

function saveAsset(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('assetForm'));
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Save failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

// Maintenance Modal Functions
function openMaintenanceModal(maintenance = null) {
    const modal = document.getElementById('maintenanceModal');
    const form = document.getElementById('maintenanceForm');
    const title = document.getElementById('maintenanceModalTitle');
    const editFields = document.getElementById('maintenanceEditFields');
    const workFields = document.getElementById('maintenanceWorkFields');
    
    form.reset();
    if (maintenance) {
        title.textContent = 'Edit Maintenance';
        document.getElementById('maintenanceAction').value = 'edit_maintenance';
        document.getElementById('maintenanceId').value = maintenance.id;
        document.getElementById('maintenanceTypeId').value = maintenance.maintenance_type_id || '';
        document.getElementById('maintenanceCategory').value = maintenance.maintenance_category || 'proactive';
        document.getElementById('maintenanceAssetId').value = maintenance.asset_id || '';
        if (maintenance.scheduled_date) {
            document.getElementById('scheduledDate').value = maintenance.scheduled_date.replace(' ', 'T').substring(0, 16);
        }
        if (maintenance.started_date) {
            document.getElementById('startedDate').value = maintenance.started_date.replace(' ', 'T').substring(0, 16);
        }
        if (maintenance.completed_date) {
            document.getElementById('completedDate').value = maintenance.completed_date.replace(' ', 'T').substring(0, 16);
        }
        document.getElementById('maintenanceStatus').value = maintenance.status || 'logged';
        document.getElementById('maintenancePriority').value = maintenance.priority || 'medium';
        document.getElementById('maintenanceDescription').value = maintenance.description || '';
        document.getElementById('workPerformed').value = maintenance.work_performed || '';
        document.getElementById('partsCost').value = maintenance.parts_cost || '0.00';
        document.getElementById('laborCost').value = maintenance.labor_cost || '0.00';
        document.getElementById('maintenanceNotes').value = maintenance.notes || '';
        updateMaintenanceTotal();
        toggleMaintenanceFields();
    } else {
        title.textContent = 'Add Maintenance';
        document.getElementById('maintenanceAction').value = 'add_maintenance';
        document.getElementById('maintenanceId').value = '';
        editFields.style.display = 'none';
        workFields.style.display = 'none';
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeMaintenanceModal() {
    document.getElementById('maintenanceModal').style.display = 'none';
    document.getElementById('maintenanceModal').classList.remove('active');
}

function updateMaintenanceTotal() {
    const parts = parseFloat(document.getElementById('partsCost').value) || 0;
    const labor = parseFloat(document.getElementById('laborCost').value) || 0;
    const total = parts + labor;
    document.getElementById('totalCostDisplay').value = total.toFixed(2);
}

function toggleMaintenanceFields() {
    const status = document.getElementById('maintenanceStatus').value;
    const editFields = document.getElementById('maintenanceEditFields');
    const workFields = document.getElementById('maintenanceWorkFields');
    
    if (status === 'in_progress' || status === 'completed') {
        editFields.style.display = 'block';
        workFields.style.display = 'block';
    } else {
        editFields.style.display = 'none';
        workFields.style.display = 'none';
    }
}

function saveMaintenance(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('maintenanceForm'));
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Save failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

// Close modals when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    // Close modal on outside click
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
            }
        });
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modals.forEach(modal => {
                if (modal.style.display === 'flex' || modal.classList.contains('active')) {
                    modal.style.display = 'none';
                    modal.classList.remove('active');
                }
            });
        }
    });

    // If a rig_id is present, scroll and highlight the corresponding rig card
    const selectedRigId = <?php echo isset($selectedRigId) && $selectedRigId ? intval($selectedRigId) : 'null'; ?>;
    if (selectedRigId) {
        const el = document.getElementById('rig-card-' + selectedRigId);
        if (el) {
            el.classList.add('highlight');
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                // Fallback for older browsers
                window.location.hash = 'rig-card-' + selectedRigId;
            }
        }
    }
});

function deleteResource(type, id) {
    if (!confirm('Are you sure you want to delete this item?')) return;
    
    const actions = {
        'material': 'delete_material',
        'catalog_item': 'delete_catalog_item',
        'catalog_category': 'delete_catalog_category',
        'asset': 'delete_asset',
        'maintenance': 'delete_maintenance'
    };
    
    const formData = new FormData();
    formData.append('action', actions[type]);
    formData.append('id', id);
    formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
    
    fetch('resources.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Delete failed', 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showNotification('An error occurred', 'error');
    });
}

function showNotification(message, type) {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type;
    notification.style.display = 'block';
    setTimeout(() => notification.style.display = 'none', 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>
