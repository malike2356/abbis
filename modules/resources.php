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
require_once __DIR__ . '/../includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    try {
        $pdo->exec("ALTER TABLE catalog_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Ignore if already converted
    }
    
    try {
        $pdo->exec("ALTER TABLE catalog_categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Ignore if already converted
    }
    
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
                
            case 'transfer_from_pos':
                $productId = intval($_POST['product_id'] ?? 0);
                $materialType = sanitizeInput($_POST['material_type'] ?? '');
                $quantity = floatval($_POST['quantity'] ?? 0);
                $storeId = !empty($_POST['store_id']) ? intval($_POST['store_id']) : null;
                $remarks = sanitizeInput($_POST['remarks'] ?? '');
                
                if (!$productId) throw new Exception('Product ID is required');
                if (empty($materialType)) throw new Exception('Material type is required');
                if ($quantity <= 0) throw new Exception('Quantity must be greater than 0');
                
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Get POS product and inventory
                    $posStmt = $pdo->prepare("
                        SELECT pp.*, pi.quantity_on_hand as pos_quantity, pi.store_id, ci.id as catalog_item_id
                        FROM pos_products pp
                        LEFT JOIN pos_inventory pi ON pp.id = pi.product_id AND (pi.store_id = ? OR pi.store_id IS NULL)
                        LEFT JOIN catalog_items ci ON pp.catalog_item_id = ci.id
                        WHERE pp.id = ?
                        ORDER BY pi.store_id DESC
                        LIMIT 1
                    ");
                    $posStmt->execute([$storeId, $productId]);
                    $posProduct = $posStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$posProduct) {
                        throw new Exception('POS product not found');
                    }
                    
                    $availableQty = floatval($posProduct['pos_quantity'] ?? 0);
                    if ($quantity > $availableQty) {
                        throw new Exception("Insufficient stock. Available: {$availableQty}, Requested: {$quantity}");
                    }
                    
                    // Get unit cost from POS product or catalog item
                    $unitCost = 0;
                    if (!empty($posProduct['catalog_item_id'])) {
                        $costStmt = $pdo->prepare("SELECT cost_price FROM catalog_items WHERE id = ?");
                        $costStmt->execute([$posProduct['catalog_item_id']]);
                        $costRow = $costStmt->fetch(PDO::FETCH_ASSOC);
                        $unitCost = floatval($costRow['cost_price'] ?? 0);
                    }
                    if ($unitCost == 0 && !empty($posProduct['cost_price'])) {
                        $unitCost = floatval($posProduct['cost_price']);
                    }
                    
                    // Update or insert material in materials_inventory
                    $materialStmt = $pdo->prepare("
                        INSERT INTO materials_inventory (material_type, material_name, quantity_received, unit_cost, unit_of_measure, notes, last_updated)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            quantity_received = quantity_received + ?,
                            unit_cost = ?,
                            quantity_remaining = (quantity_received + ?) - quantity_used,
                            total_value = ((quantity_received + ?) - quantity_used) * ?,
                            notes = CONCAT(COALESCE(notes, ''), '\n', ?),
                            last_updated = NOW()
                    ");
                    
                    $materialName = ucfirst(str_replace('_', ' ', $materialType));
                    $unit = $posProduct['unit'] ?? 'pcs';
                    $note = "Transferred from POS (Product ID: {$productId}, Store ID: " . ($storeId ?? 'N/A') . ") on " . date('Y-m-d H:i:s');
                    if (!empty($remarks)) {
                        $note .= " - " . $remarks;
                    }
                    
                    $materialStmt->execute([
                        $materialType,
                        $materialName,
                        $quantity,
                        $unitCost,
                        $unit,
                        $note,
                        $quantity, // For ON DUPLICATE KEY UPDATE
                        $unitCost, // For ON DUPLICATE KEY UPDATE
                        $quantity, // For quantity_remaining calculation
                        $quantity, // For total_value calculation
                        $unitCost, // For total_value calculation
                        $note
                    ]);
                    
                    // Deduct from POS inventory
                    if ($storeId) {
                        $deductStmt = $pdo->prepare("
                            UPDATE pos_inventory 
                            SET quantity = quantity - ?
                            WHERE product_id = ? AND store_id = ?
                        ");
                        $deductStmt->execute([$quantity, $productId, $storeId]);
                    } else {
                        // Deduct from all stores or create entry
                        $deductStmt = $pdo->prepare("
                            UPDATE pos_inventory 
                            SET quantity = GREATEST(0, quantity - ?)
                            WHERE product_id = ?
                        ");
                        $deductStmt->execute([$quantity, $productId]);
                    }
                    
                    // Update catalog_items inventory (system-wide sync)
                    if (!empty($posProduct['catalog_item_id'])) {
                        try {
                            require_once __DIR__ . '/../includes/pos/UnifiedInventoryService.php';
                            $inventoryService = new UnifiedInventoryService($pdo);
                            
                            // Deduct from catalog (negative quantity = decrease)
                            $inventoryService->updateCatalogStock(
                                $posProduct['catalog_item_id'],
                                -$quantity, // Negative = decrease
                                "Transferred to Materials Store: {$materialType}"
                            );
                        } catch (Exception $e) {
                            error_log("[Resources] Catalog inventory sync failed: " . $e->getMessage());
                            // Continue - don't fail the transfer if sync fails
                        }
                    }
                    
                    // Create material mapping if it doesn't exist
                    if (!empty($posProduct['catalog_item_id'])) {
                        try {
                            $mappingStmt = $pdo->prepare("
                                INSERT INTO pos_material_mappings (material_type, catalog_item_id, pos_product_id, created_at)
                                VALUES (?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE
                                    catalog_item_id = VALUES(catalog_item_id),
                                    pos_product_id = VALUES(pos_product_id)
                            ");
                            $mappingStmt->execute([$materialType, $posProduct['catalog_item_id'], $productId]);
                        } catch (PDOException $e) {
                            error_log("[Resources] Material mapping creation failed: " . $e->getMessage());
                            // Continue - mapping is optional
                        }
                    }
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "Successfully transferred {$quantity} {$unit} of {$materialName} from POS to Materials Store"
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
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
                $catalogItemId = (int) $pdo->lastInsertId();

                try {
                    require_once __DIR__ . '/../includes/pos/UnifiedCatalogSyncService.php';
                    $syncService = new UnifiedCatalogSyncService($pdo);
                    
                    // Sync product details to POS
                    $syncService->syncCatalogToPos($catalogItemId);
                    
                    // Sync inventory to POS if inventory_quantity was set
                    if ($inventoryQuantity > 0) {
                        require_once __DIR__ . '/../includes/pos/UnifiedInventoryService.php';
                        $inventoryService = new UnifiedInventoryService($pdo);
                        $inventoryService->setCatalogStock($catalogItemId, (float) $inventoryQuantity, 'ABBIS Resources product creation');
                    }
                } catch (Throwable $syncError) {
                    error_log('[Resources] POS sync failed (create): ' . $syncError->getMessage());
                }
                
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
                
                // Get old inventory quantity before update
                $oldInvStmt = $pdo->prepare("SELECT COALESCE(inventory_quantity, stock_quantity, 0) FROM catalog_items WHERE id = ?");
                $oldInvStmt->execute([$id]);
                $oldInventory = (float) ($oldInvStmt->fetchColumn() ?: 0);
                
                $stmt = $pdo->prepare("UPDATE catalog_items SET name=?, sku=?, item_type=?, category_id=?, unit=?, cost_price=?, sell_price=?, taxable=?, is_active=?, inventory_quantity=?, reorder_level=?, notes=? WHERE id=?");
                $stmt->execute([$name, $sku, $itemType, $categoryId, $unit, $costPrice, $sellPrice, $taxable, $isActive, $inventoryQuantity, $reorderLevel, $notes, $id]);

                try {
                    require_once __DIR__ . '/../includes/pos/UnifiedCatalogSyncService.php';
                    $syncService = new UnifiedCatalogSyncService($pdo);
                    
                    // Sync product details to POS
                    $syncService->syncCatalogToPos($id);
                    
                    // Sync inventory to POS if inventory_quantity was changed
                    if ($inventoryQuantity != $oldInventory) {
                        require_once __DIR__ . '/../includes/pos/UnifiedInventoryService.php';
                        $inventoryService = new UnifiedInventoryService($pdo);
                        $inventoryService->setCatalogStock($id, (float) $inventoryQuantity, 'ABBIS Resources inventory update');
                    }
                } catch (Throwable $syncError) {
                    error_log('[Resources] POS sync failed (update) for product ' . $id . ': ' . $syncError->getMessage());
                    error_log('[Resources] Stack trace: ' . $syncError->getTraceAsString());
                }
                
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
                break;
                
            case 'delete_catalog_item':
                $id = intval($_POST['id'] ?? 0);
                if (!$id) throw new Exception('Invalid item ID');
                
                $stmt = $pdo->prepare("DELETE FROM catalog_items WHERE id = ?");
                $stmt->execute([$id]);

                try {
                    $posRepository = new PosRepository($pdo);
                    $posRepository->disableProductForCatalogItem($id);
                } catch (Throwable $syncError) {
                    error_log('[Resources] POS sync failed (delete): ' . $syncError->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
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
                
                $assetId = $pdo->lastInsertId();
                
                // Automatically track asset purchase in accounting
                if ($purchaseCost > 0) {
                    try {
                        require_once '../includes/AccountingAutoTracker.php';
                        $accountingTracker = new AccountingAutoTracker($pdo);
                        $accountingTracker->trackAssetPurchase($assetId, [
                            'asset_name' => $assetName,
                            'purchase_date' => $purchaseDate ?: date('Y-m-d'),
                            'purchase_cost' => $purchaseCost,
                            'is_new_purchase' => true,
                            'created_by' => $_SESSION['user_id'] ?? null
                        ]);
                    } catch (Exception $e) {
                        error_log("Accounting auto-tracking error for asset purchase: " . $e->getMessage());
                    }
                }
                
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
                
                // Get old purchase cost for comparison
                $oldStmt = $pdo->prepare("SELECT purchase_cost, purchase_date, asset_name FROM assets WHERE id = ?");
                $oldStmt->execute([$id]);
                $oldAsset = $oldStmt->fetch();
                $oldPurchaseCost = $oldAsset ? floatval($oldAsset['purchase_cost'] ?? 0) : 0;
                
                $stmt = $pdo->prepare("UPDATE assets SET asset_code=?, asset_name=?, asset_type=?, category=?, brand=?, model=?, purchase_date=?, purchase_cost=?, current_value=?, location=?, status=?, condition=?, notes=? WHERE id=?");
                $stmt->execute([$assetCode, $assetName, $assetType, $category, $brand, $model, $purchaseDate, $purchaseCost, $currentValue, $location, $status, $condition, $notes, $id]);
                
                // Automatically track asset purchase cost change in accounting
                if ($purchaseCost > 0 && abs($purchaseCost - $oldPurchaseCost) > 0.01) {
                    try {
                        require_once '../includes/AccountingAutoTracker.php';
                        $accountingTracker = new AccountingAutoTracker($pdo);
                        $accountingTracker->trackAssetPurchase($id, [
                            'asset_name' => $assetName,
                            'purchase_date' => $purchaseDate ?: ($oldAsset['purchase_date'] ?? date('Y-m-d')),
                            'purchase_cost' => $purchaseCost,
                            'old_purchase_cost' => $oldPurchaseCost,
                            'is_new_purchase' => false,
                            'created_by' => $_SESSION['user_id'] ?? null
                        ]);
                    } catch (Exception $e) {
                        error_log("Accounting auto-tracking error for asset update: " . $e->getMessage());
                    }
                }
                
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
    
    // Get recent activity data for overview
    $recentMaterials = $pdo->query("SELECT * FROM materials_inventory ORDER BY last_updated DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recentCatalogItems = $pdo->query("SELECT i.*, c.name as category_name FROM catalog_items i LEFT JOIN catalog_categories c ON c.id=i.category_id WHERE i.is_active = 1 ORDER BY i.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recentAssets = $pdo->query("SELECT * FROM assets ORDER BY updated_at DESC, created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recentMaintenance = $pdo->query("SELECT m.*, a.asset_name, mt.type_name FROM maintenance_records m LEFT JOIN assets a ON m.asset_id = a.id LEFT JOIN maintenance_types mt ON m.maintenance_type_id = mt.id ORDER BY m.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock items
    $lowStockItems = $pdo->query("SELECT * FROM materials_inventory WHERE quantity_remaining < 10 ORDER BY quantity_remaining ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming maintenance
    $upcomingMaintenance = $pdo->query("SELECT m.*, a.asset_name, mt.type_name FROM maintenance_records m LEFT JOIN assets a ON m.asset_id = a.id LEFT JOIN maintenance_types mt ON m.maintenance_type_id = mt.id WHERE m.status IN ('scheduled', 'logged') AND (m.scheduled_date >= CURDATE() OR m.scheduled_date IS NULL) ORDER BY m.scheduled_date ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get catalog items needing reorder
    $reorderItems = $pdo->query("SELECT i.*, c.name as category_name FROM catalog_items i LEFT JOIN catalog_categories c ON c.id=i.category_id WHERE i.is_active = 1 AND i.inventory_quantity <= i.reorder_level AND i.reorder_level > 0 ORDER BY (i.inventory_quantity / NULLIF(i.reorder_level, 0)) ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
    $recentMaterials = [];
    $recentCatalogItems = [];
    $recentAssets = [];
    $recentMaintenance = [];
    $lowStockItems = [];
    $upcomingMaintenance = [];
    $reorderItems = [];
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
                'brand' => null, // Brand can be separate from model
                'model' => $rig['truck_model'] ?? null, // Map truck_model to model field
                'truck_model' => $rig['truck_model'] ?? null, // Keep original for reference
                'registration_number' => $rig['registration_number'] ?? null,
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
        <button class="tab-nav-item <?php echo $action === 'advanced' ? 'active' : ''; ?>" onclick="window.location.href='resources.php?action=advanced'">🚀 Advanced Features</button>
    </div>

    <!-- Overview Tab -->
    <?php if ($action === 'overview'): ?>
        <!-- Key Metrics Dashboard -->
        <div style="margin-bottom: 30px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <!-- Materials KPI -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div>
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Materials Inventory</div>
                            <div style="font-size: 32px; font-weight: 700; line-height: 1;"><?php echo number_format($stats['materials']['count']); ?></div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Material Types</div>
                        </div>
                        <div style="font-size: 40px; opacity: 0.3;">📦</div>
                    </div>
                    <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; opacity: 0.9;">Total Items:</span>
                            <strong style="font-size: 14px;"><?php echo number_format($stats['materials']['total_items']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; opacity: 0.9;">Total Value:</span>
                            <strong style="font-size: 14px;"><?php echo formatCurrency($stats['materials']['total_value']); ?></strong>
                        </div>
                        <?php if ($stats['materials']['low_stock'] > 0): ?>
                            <div style="background: rgba(255,255,255,0.2); padding: 8px; border-radius: 6px; margin-top: 10px; text-align: center;">
                                <span style="font-size: 12px;">⚠️ <?php echo $stats['materials']['low_stock']; ?> Low Stock Items</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button onclick="window.location.href='resources.php?action=materials'" class="btn" style="margin-top: 15px; width: 100%; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">View Materials →</button>
                </div>

                <!-- Catalog KPI -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div>
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Product Catalog</div>
                            <div style="font-size: 32px; font-weight: 700; line-height: 1;"><?php echo number_format($stats['catalog']['items']); ?></div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Active Items</div>
                        </div>
                        <div style="font-size: 40px; opacity: 0.3;">🗂️</div>
                    </div>
                    <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-size: 12px; opacity: 0.9;">Categories:</span>
                            <strong style="font-size: 14px;"><?php echo number_format($stats['catalog']['categories']); ?></strong>
                        </div>
                        <?php if (count($reorderItems) > 0): ?>
                            <div style="background: rgba(255,255,255,0.2); padding: 8px; border-radius: 6px; margin-top: 10px; text-align: center;">
                                <span style="font-size: 12px;">⚠️ <?php echo count($reorderItems); ?> Need Reorder</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button onclick="window.location.href='resources.php?action=catalog'" class="btn" style="margin-top: 15px; width: 100%; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">View Catalog →</button>
                </div>

                <!-- Assets KPI -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div>
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Company Assets</div>
                            <div style="font-size: 32px; font-weight: 700; line-height: 1;"><?php echo number_format($stats['assets']['count']); ?></div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Total Assets</div>
                        </div>
                        <div style="font-size: 40px; opacity: 0.3;">🏭</div>
                    </div>
                    <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; opacity: 0.9;">Active:</span>
                            <strong style="font-size: 14px;"><?php echo number_format($stats['assets']['active']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-size: 12px; opacity: 0.9;">Total Value:</span>
                            <strong style="font-size: 14px;"><?php echo formatCurrency($stats['assets']['total_value']); ?></strong>
                        </div>
                    </div>
                    <button onclick="window.location.href='resources.php?action=assets'" class="btn" style="margin-top: 15px; width: 100%; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">View Assets →</button>
                </div>

                <!-- Maintenance KPI -->
                <div class="dashboard-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; border: none;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div>
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Maintenance</div>
                            <div style="font-size: 32px; font-weight: 700; line-height: 1;"><?php echo number_format($stats['maintenance']['pending']); ?></div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Pending Tasks</div>
                        </div>
                        <div style="font-size: 40px; opacity: 0.3;">🔧</div>
                    </div>
                    <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; opacity: 0.9;">Due Soon:</span>
                            <strong style="font-size: 14px;"><?php echo number_format($stats['maintenance']['due_soon']); ?></strong>
                        </div>
                        <?php if ($stats['maintenance']['overdue'] > 0): ?>
                            <div style="background: rgba(255,255,255,0.3); padding: 8px; border-radius: 6px; margin-top: 10px; text-align: center;">
                                <span style="font-size: 12px; font-weight: 600;">⚠️ <?php echo $stats['maintenance']['overdue']; ?> Overdue</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button onclick="window.location.href='resources.php?action=maintenance'" class="btn" style="margin-top: 15px; width: 100%; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">View Maintenance →</button>
                </div>
            </div>
        </div>

        <!-- Alerts & Quick Actions -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Alerts Section -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>⚠️</span> Alerts & Notifications
                    </h3>
                    <?php 
                    $totalAlerts = $stats['materials']['low_stock'] + $stats['maintenance']['overdue'] + count($reorderItems);
                    if ($totalAlerts > 0): 
                    ?>
                        <span style="background: var(--danger); color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                            <?php echo $totalAlerts; ?> Alert<?php echo $totalAlerts > 1 ? 's' : ''; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php if ($totalAlerts == 0): ?>
                        <div style="text-align: center; padding: 40px; color: var(--secondary);">
                            <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                            <div style="font-size: 16px; font-weight: 600; margin-bottom: 5px;">All Clear!</div>
                            <div style="font-size: 13px;">No alerts or notifications at this time.</div>
                        </div>
                    <?php else: ?>
                        <?php if ($stats['materials']['low_stock'] > 0): ?>
                            <div style="padding: 12px; background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; border-radius: 6px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;">Low Stock Materials</div>
                                        <div style="font-size: 13px; color: var(--secondary);"><?php echo $stats['materials']['low_stock']; ?> material type(s) are running low</div>
                                    </div>
                                    <button onclick="window.location.href='resources.php?action=materials'" class="btn btn-sm btn-outline" style="margin-left: 10px;">View</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (count($reorderItems) > 0): ?>
                            <div style="padding: 12px; background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; border-radius: 6px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;">Catalog Items Need Reorder</div>
                                        <div style="font-size: 13px; color: var(--secondary);"><?php echo count($reorderItems); ?> item(s) at or below reorder level</div>
                                    </div>
                                    <button onclick="window.location.href='resources.php?action=catalog'" class="btn btn-sm btn-outline" style="margin-left: 10px;">View</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['maintenance']['overdue'] > 0): ?>
                            <div style="padding: 12px; background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545; border-radius: 6px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;">Overdue Maintenance</div>
                                        <div style="font-size: 13px; color: var(--secondary);"><?php echo $stats['maintenance']['overdue']; ?> maintenance task(s) are overdue</div>
                                    </div>
                                    <button onclick="window.location.href='resources.php?action=maintenance'" class="btn btn-sm btn-outline" style="margin-left: 10px;">View</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>⚡</span> Quick Actions
                    </h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <button onclick="openMaterialModal()" class="btn btn-outline" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span style="font-size: 24px;">📦</span>
                        <span style="font-size: 13px; font-weight: 600;">Add Material</span>
                    </button>
                    <button onclick="openCatalogItemModal()" class="btn btn-outline" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span style="font-size: 24px;">🗂️</span>
                        <span style="font-size: 13px; font-weight: 600;">Add Catalog Item</span>
                    </button>
                    <button onclick="window.location.href='resources.php?action=assets'" class="btn btn-outline" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span style="font-size: 24px;">🏭</span>
                        <span style="font-size: 13px; font-weight: 600;">Add Asset</span>
                    </button>
                    <button onclick="openMaintenanceModal()" class="btn btn-outline" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <span style="font-size: 24px;">🔧</span>
                        <span style="font-size: 13px; font-weight: 600;">Log Maintenance</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Upcoming -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Recent Materials -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>📦</span> Recent Materials
                    </h3>
                    <a href="resources.php?action=materials" style="font-size: 12px; color: var(--primary); text-decoration: none;">View All →</a>
                </div>
                <?php if (empty($recentMaterials)): ?>
                    <div style="text-align: center; padding: 30px; color: var(--secondary);">
                        <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                        <div style="font-size: 13px;">No materials yet</div>
                    </div>
                <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($recentMaterials as $mat): ?>
                            <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;"><?php echo e($mat['material_name']); ?></div>
                                    <div style="font-size: 12px; color: var(--secondary);">
                                        <?php echo e($mat['material_type']); ?> • 
                                        Remaining: <strong style="color: <?php echo intval($mat['quantity_remaining']) < 10 ? 'var(--danger)' : 'var(--text)'; ?>;"><?php echo number_format($mat['quantity_remaining']); ?></strong>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <div style="font-size: 12px; color: var(--secondary);"><?php echo formatCurrency($mat['total_value']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Maintenance -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>🔧</span> Upcoming Maintenance
                    </h3>
                    <a href="resources.php?action=maintenance" style="font-size: 12px; color: var(--primary); text-decoration: none;">View All →</a>
                </div>
                <?php if (empty($upcomingMaintenance)): ?>
                    <div style="text-align: center; padding: 30px; color: var(--secondary);">
                        <div style="font-size: 32px; margin-bottom: 8px;">✅</div>
                        <div style="font-size: 13px;">No upcoming maintenance</div>
                    </div>
                <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($upcomingMaintenance as $maint): ?>
                            <?php
                            $isOverdue = $maint['scheduled_date'] && strtotime($maint['scheduled_date']) < time();
                            $daysUntil = $maint['scheduled_date'] ? floor((strtotime($maint['scheduled_date']) - time()) / 86400) : null;
                            ?>
                            <div style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;">
                                            <?php echo e($maint['asset_name'] ?: $maint['maintenance_code']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--secondary);">
                                            <?php echo e($maint['type_name'] ?: 'Maintenance'); ?>
                                        </div>
                                    </div>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo $isOverdue ? 'rgba(220, 53, 69, 0.1)' : 'rgba(14, 165, 233, 0.1)'; ?>; color: <?php echo $isOverdue ? '#dc3545' : 'var(--primary)'; ?>;">
                                        <?php 
                                        if ($isOverdue) echo 'Overdue';
                                        elseif ($daysUntil === 0) echo 'Today';
                                        elseif ($daysUntil === 1) echo 'Tomorrow';
                                        elseif ($daysUntil !== null) echo $daysUntil . ' days';
                                        else echo 'Scheduled';
                                        ?>
                                    </span>
                                </div>
                                <?php if ($maint['scheduled_date']): ?>
                                    <div style="font-size: 11px; color: var(--secondary);">
                                        📅 <?php echo date('M j, Y', strtotime($maint['scheduled_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock & Recent Catalog Items -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">
            <!-- Low Stock Items -->
            <?php if (!empty($lowStockItems)): ?>
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>⚠️</span> Low Stock Items
                        </h3>
                        <a href="resources.php?action=materials" style="font-size: 12px; color: var(--primary); text-decoration: none;">View All →</a>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($lowStockItems as $item): ?>
                            <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;"><?php echo e($item['material_name']); ?></div>
                                    <div style="font-size: 12px; color: var(--secondary);">
                                        <?php echo e($item['material_type']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <div style="font-size: 14px; font-weight: 600; color: var(--danger);">
                                        <?php echo number_format($item['quantity_remaining']); ?> <?php echo e($item['unit_of_measure'] ?: 'pcs'); ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--secondary); margin-top: 2px;">Remaining</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Catalog Items -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>🗂️</span> Recent Catalog Items
                    </h3>
                    <a href="resources.php?action=catalog" style="font-size: 12px; color: var(--primary); text-decoration: none;">View All →</a>
                </div>
                <?php if (empty($recentCatalogItems)): ?>
                    <div style="text-align: center; padding: 30px; color: var(--secondary);">
                        <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                        <div style="font-size: 13px;">No catalog items yet</div>
                    </div>
                <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($recentCatalogItems as $item): ?>
                            <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;"><?php echo e($item['name']); ?></div>
                                    <div style="font-size: 12px; color: var(--secondary);">
                                        <?php echo e($item['category_name'] ?: 'Uncategorized'); ?> • 
                                        <?php echo ucfirst($item['item_type']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <div style="font-size: 14px; font-weight: 600; color: var(--primary);">
                                        <?php echo formatCurrency($item['sell_price']); ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--secondary); margin-top: 2px;">Sell Price</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Specialized Modules -->
    <?php endif; ?>

    <?php if ($action === 'advanced'): ?>
        <div style="margin: 30px 0;">
            <h2 style="margin-bottom: 16px; display:flex; align-items:center; gap:10px;">🚀 Advanced Features</h2>
            <p style="color: var(--secondary); margin-bottom: 20px; max-width:720px;">
                Explore extended capability modules for compliance automation, telemetry, environmental sampling, and predictive geology.
            </p>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                <div class="dashboard-card" style="border:1px solid var(--border); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:28px;">🗺️</div>
                        <div>
                            <h3 style="margin:0; font-size:18px;">Smart Job Planner</h3>
                            <span style="font-size:12px; color:var(--secondary);">Interactive scheduling map & routing</span>
                        </div>
                    </div>
                    <p style="font-size:13px; color:var(--secondary); flex:1;">
                        Plot every pending rig job on a live map, color-code by route, and optimize dispatch with distance metrics, crew notes, and rig availability.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span style="background:rgba(59,130,246,0.12); color:#1d4ed8; padding:4px 10px; border-radius:999px; font-size:12px;">Route Plans</span>
                        <span style="background:rgba(251,191,36,0.18); color:#92400e; padding:4px 10px; border-radius:999px; font-size:12px;">Color Pins</span>
                        <span style="background:rgba(16,185,129,0.15); color:#047857; padding:4px 10px; border-radius:999px; font-size:12px;">Crew Notes</span>
                    </div>
                    <button onclick="window.location.href='job-planner.php'" class="btn btn-outline" style="justify-content:center;">Open Job Planner →</button>
                </div>

                <div class="dashboard-card" style="border:1px solid var(--border); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:28px;">📝</div>
                        <div>
                            <h3 style="margin:0; font-size:18px;">Regulatory Forms Automation</h3>
                            <span style="font-size:12px; color:var(--secondary);">Template-driven compliance exports</span>
                        </div>
                    </div>
                    <p style="font-size:13px; color:var(--secondary); flex:1;">
                        Build HTML merge templates, generate regulator-ready documents, and maintain an export audit log.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span style="background:rgba(59,130,246,0.12); color:#1d4ed8; padding:4px 10px; border-radius:999px; font-size:12px;">Templates</span>
                        <span style="background:rgba(96,165,250,0.12); color:#1e3a8a; padding:4px 10px; border-radius:999px; font-size:12px;">Merge Fields</span>
                        <span style="background:rgba(45,212,191,0.12); color:#0f766e; padding:4px 10px; border-radius:999px; font-size:12px;">Audit Log</span>
                    </div>
                    <button onclick="window.location.href='regulatory-forms.php'" class="btn btn-outline" style="justify-content:center;">Open Regulatory Forms →</button>
                </div>

                <div class="dashboard-card" style="border:1px solid var(--border); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:28px;">📡</div>
                        <div>
                            <h3 style="margin:0; font-size:18px;">Rig Telemetry & Alerts</h3>
                            <span style="font-size:12px; color:var(--secondary);">Live sensor streams & auto alerts</span>
                        </div>
                    </div>
                    <p style="font-size:13px; color:var(--secondary); flex:1;">
                        Issue ingest tokens, monitor telemetry dashboards, and trigger maintenance alerts from sensor thresholds.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span style="background:rgba(59,130,246,0.12); color:#1d4ed8; padding:4px 10px; border-radius:999px; font-size:12px;">Streams</span>
                        <span style="background:rgba(251,191,36,0.18); color:#92400e; padding:4px 10px; border-radius:999px; font-size:12px;">Thresholds</span>
                        <span style="background:rgba(248,113,113,0.18); color:#b91c1c; padding:4px 10px; border-radius:999px; font-size:12px;">Alerts</span>
                    </div>
                    <button onclick="window.location.href='rig-maintenance-telemetry.php'" class="btn btn-outline" style="justify-content:center;">Open Rig Telemetry →</button>
                </div>

                <div class="dashboard-card" style="border:1px solid var(--border); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:28px;">🧪</div>
                        <div>
                            <h3 style="margin:0; font-size:18px;">Environmental Sampling</h3>
                            <span style="font-size:12px; color:var(--secondary);">Projects, custody, and lab results</span>
                        </div>
                    </div>
                    <p style="font-size:13px; color:var(--secondary); flex:1;">
                        Schedule sampling campaigns, ensure chain-of-custody integrity, and record laboratory analysis with QA/QC flags.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span style="background:rgba(59,130,246,0.12); color:#1d4ed8; padding:4px 10px; border-radius:999px; font-size:12px;">Projects</span>
                        <span style="background:rgba(45,212,191,0.12); color:#0f766e; padding:4px 10px; border-radius:999px; font-size:12px;">Chain-of-Custody</span>
                        <span style="background:rgba(165,180,252,0.18); color:#3730a3; padding:4px 10px; border-radius:999px; font-size:12px;">Lab Results</span>
                    </div>
                    <button onclick="window.location.href='environmental-sampling.php'" class="btn btn-outline" style="justify-content:center;">Open Environmental Sampling →</button>
                </div>

                <div class="dashboard-card" style="border:1px solid var(--border); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:28px;">🌍</div>
                        <div>
                            <h3 style="margin:0; font-size:18px;">Geology Estimator</h3>
                            <span style="font-size:12px; color:var(--secondary);">Predict depth & strata</span>
                        </div>
                    </div>
                    <p style="font-size:13px; color:var(--secondary); flex:1;">
                        Import historical wells, analyze surrounding sites, and estimate drilling difficulty and aquifer presence.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span style="background:rgba(34,197,94,0.18); color:#166534; padding:4px 10px; border-radius:999px; font-size:12px;">Predictions</span>
                        <span style="background:rgba(59,130,246,0.12); color:#1d4ed8; padding:4px 10px; border-radius:999px; font-size:12px;">Well Data</span>
                        <span style="background:rgba(251,191,36,0.18); color:#92400e; padding:4px 10px; border-radius:999px; font-size:12px;">Routing</span>
                    </div>
                    <button onclick="window.location.href='geology-estimator.php'" class="btn btn-outline" style="justify-content:center;">Open Geology Estimator →</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Materials Tab -->
    <?php if ($action === 'materials'): 
        // Calculate materials statistics
        $materialsStats = [
            'total_items' => count($materials),
            'total_value' => array_sum(array_column($materials, 'total_value')),
            'total_remaining' => array_sum(array_column($materials, 'quantity_remaining')),
            'low_stock_count' => 0,
            'by_type' => []
        ];
        
        foreach ($materials as $m) {
            $type = $m['material_type'];
            if (!isset($materialsStats['by_type'][$type])) {
                $materialsStats['by_type'][$type] = [
                    'count' => 0,
                    'total_value' => 0,
                    'total_remaining' => 0
                ];
            }
            $materialsStats['by_type'][$type]['count']++;
            $materialsStats['by_type'][$type]['total_value'] += floatval($m['total_value']);
            $materialsStats['by_type'][$type]['total_remaining'] += intval($m['quantity_remaining']);
            
            if (intval($m['quantity_remaining']) < 10) {
                $materialsStats['low_stock_count']++;
            }
        }
        
        // Get recent inventory transactions for materials
        $recentTransactions = [];
        try {
            $transStmt = $pdo->prepare("
                SELECT it.*, mi.material_name, mi.material_type
                FROM inventory_transactions it
                JOIN materials_inventory mi ON it.item_id = mi.id
                WHERE it.item_type = 'material'
                ORDER BY it.created_at DESC
                LIMIT 10
            ");
            $transStmt->execute();
            $recentTransactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table might not exist yet
        }
    ?>
        <!-- Page Header -->
        <div style="margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 300px;">
                    <h2 style="margin: 0 0 10px 0; font-size: 24px; font-weight: 700; color: var(--text);">📦 Operational Materials Inventory</h2>
                    <p style="color: var(--secondary); font-size: 14px; margin: 0; line-height: 1.6;">
                        Track operational inventory items used in field operations. Materials sync with Catalog and POS inventory for unified management.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="openMaterialModal()" class="btn btn-primary" style="white-space: nowrap;">
                        ➕ Add Material
                    </button>
                    <button onclick="openTransferFromPosModal()" class="btn" style="background: #10b981; color: white; white-space: nowrap;">
                        📦 Transfer from POS
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Row -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div class="dashboard-card" style="padding: 20px; text-align: center; border-left: 4px solid var(--primary);">
                <div style="font-size: 28px; font-weight: 700; color: var(--primary); margin-bottom: 6px;">
                    <?php echo number_format($materialsStats['total_items']); ?>
                </div>
                <div style="color: var(--secondary); font-size: 13px; font-weight: 500;">Material Types</div>
            </div>
            <div class="dashboard-card" style="padding: 20px; text-align: center; border-left: 4px solid var(--success);">
                <div style="font-size: 28px; font-weight: 700; color: var(--success); margin-bottom: 6px;">
                    <?php echo formatCurrency($materialsStats['total_value']); ?>
                </div>
                <div style="color: var(--secondary); font-size: 13px; font-weight: 500;">Total Inventory Value</div>
            </div>
            <?php if ($materialsStats['low_stock_count'] > 0): ?>
            <div class="dashboard-card" style="padding: 20px; text-align: center; border-left: 4px solid var(--danger); background: rgba(239, 68, 68, 0.05);">
                <div style="font-size: 28px; font-weight: 700; color: var(--danger); margin-bottom: 6px;">
                    ⚠️ <?php echo $materialsStats['low_stock_count']; ?>
                </div>
                <div style="color: var(--secondary); font-size: 13px; font-weight: 500;">Low Stock Items</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Materials by Type - Two Column Layout -->
        <?php if (!empty($materialsStats['by_type'])): 
            uasort($materialsStats['by_type'], function($a, $b) {
                return $b['total_remaining'] - $a['total_remaining'];
            });
        ?>
        <div class="dashboard-card" style="margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border);">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text);">📊 Materials by Type</h3>
                <span style="font-size: 12px; color: var(--secondary);"><?php echo count($materialsStats['by_type']); ?> type(s)</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php foreach ($materialsStats['by_type'] as $type => $stats): 
                    $typeLabel = ucfirst(str_replace('_', ' ', $type));
                    $typeIcon = '';
                    if (stripos($type, 'pipe') !== false) {
                        $typeIcon = '🔩';
                    } elseif (stripos($type, 'gravel') !== false) {
                        $typeIcon = '🪨';
                    } elseif (stripos($type, 'rod') !== false) {
                        $typeIcon = '⚙️';
                    } else {
                        $typeIcon = '📦';
                    }
                ?>
                <div style="padding: 16px; background: var(--bg); border-radius: 8px; border: 1px solid var(--border); text-align: center; transition: all 0.2s;">
                    <div style="font-size: 28px; margin-bottom: 10px;"><?php echo $typeIcon; ?></div>
                    <div style="font-weight: 600; color: var(--text); margin-bottom: 8px; font-size: 14px;">
                        <?php echo $typeLabel; ?>
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--primary); margin-bottom: 6px;">
                        <?php echo number_format($stats['total_remaining']); ?>
                    </div>
                    <div style="font-size: 11px; color: var(--secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Units Available
                    </div>
                    <div style="font-size: 13px; color: var(--success); font-weight: 600; margin-bottom: 4px;">
                        <?php echo formatCurrency($stats['total_value']); ?>
                    </div>
                    <div style="font-size: 11px; color: var(--secondary);">
                        <?php echo $stats['count']; ?> item(s)
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Materials Table Section -->
        <div class="dashboard-card" style="margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border); flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text);">📋 All Materials</h3>
                    <span style="font-size: 12px; color: var(--secondary);"><?php echo count($materials); ?> item(s) total</span>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" id="materialSearch" placeholder="🔍 Search materials..." 
                           style="padding: 8px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; min-width: 200px; background: var(--input-bg); color: var(--text);"
                           onkeyup="filterMaterialsTable()">
                    <select id="materialTypeFilter" onchange="filterMaterialsTable()" 
                            style="padding: 8px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--input-bg); color: var(--text);">
                        <option value="">All Types</option>
                        <?php 
                        $types = array_unique(array_column($materials, 'material_type'));
                        foreach ($types as $type): 
                        ?>
                        <option value="<?php echo e($type); ?>"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="materialsTable">
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
                            <th>Supplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materials)): ?>
                            <tr><td colspan="10" style="text-align: center; padding: 40px;">No materials found. Click "Add Material" to get started.</td></tr>
                        <?php else: ?>
                            <?php foreach ($materials as $m): ?>
                                <?php 
                                $remaining = intval($m['quantity_remaining'] ?? 0);
                                $isLowStock = $remaining < 10;
                                $usagePercent = $m['quantity_received'] > 0 ? ($m['quantity_used'] / $m['quantity_received']) * 100 : 0;
                                ?>
                                <tr data-material-type="<?php echo e($m['material_type']); ?>" 
                                    data-material-name="<?php echo strtolower(e($m['material_name'])); ?>"
                                    style="<?php echo $isLowStock ? 'background: rgba(239, 68, 68, 0.05); border-left: 3px solid var(--danger);' : 'border-left: 3px solid transparent;'; ?>">
                                    <td style="padding: 12px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <strong style="color: var(--text); font-size: 13px;"><?php echo e($m['material_name']); ?></strong>
                                            <?php if ($isLowStock): ?>
                                                <span style="color: var(--danger); font-size: 10px; padding: 2px 6px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; font-weight: 600;">⚠️ Low</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 12px;">
                                        <span class="badge" style="background: var(--primary); color: white; font-size: 11px; padding: 4px 8px; border-radius: 4px; font-weight: 500;">
                                            <?php echo ucfirst(str_replace('_', ' ', $m['material_type'])); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-size: 13px;"><?php echo number_format($m['quantity_received']); ?></td>
                                    <td style="padding: 12px; text-align: right;">
                                        <div style="font-size: 13px; font-weight: 500;"><?php echo number_format($m['quantity_used']); ?></div>
                                        <?php if ($m['quantity_received'] > 0): ?>
                                            <div style="font-size: 11px; color: var(--secondary);">
                                                (<?php echo number_format($usagePercent, 1); ?>%)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600; font-size: 14px; <?php echo $isLowStock ? 'color: var(--danger);' : 'color: var(--success);'; ?>">
                                        <?php echo number_format($remaining); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right; font-size: 13px;"><?php echo formatCurrency($m['unit_cost']); ?></td>
                                    <td style="padding: 12px; text-align: right; font-weight: 600; font-size: 13px; color: var(--text);"><?php echo formatCurrency($m['total_value']); ?></td>
                                    <td style="padding: 12px; font-size: 12px; color: var(--secondary);"><?php echo e($m['unit_of_measure'] ?: 'pcs'); ?></td>
                                    <td style="padding: 12px; font-size: 12px; color: var(--secondary);">
                                        <?php echo e($m['supplier'] ?: '—'); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <div style="display: flex; flex-direction: column; gap: 6px; align-items: center;">
                                            <button onclick="openInventoryModal('material', <?php echo $m['id']; ?>, '<?php echo e($m['material_name']); ?>', <?php echo $remaining; ?>)" 
                                                    class="btn btn-sm btn-primary" style="font-size: 11px; padding: 5px 10px; width: 100%;">
                                                📦 Manage
                                            </button>
                                            <?php if ($remaining > 0): ?>
                                            <button onclick="openReturnMaterialModal(<?php echo htmlspecialchars(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" 
                                                    class="btn btn-sm" style="font-size: 11px; padding: 5px 10px; width: 100%; background: #f59e0b; color: white;">
                                                🔄 Return
                                            </button>
                                            <?php endif; ?>
                                            <div style="display: flex; gap: 4px; width: 100%;">
                                                <button onclick="openMaterialModal(<?php echo htmlspecialchars(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" 
                                                        class="btn btn-sm btn-outline" style="font-size: 11px; padding: 5px 8px; flex: 1;">
                                                    Edit
                                                </button>
                                                <button onclick="deleteResource('material', <?php echo $m['id']; ?>)" 
                                                        class="btn btn-sm btn-danger" style="font-size: 11px; padding: 5px 8px; flex: 1;">
                                                    Del
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Sync & Integration Section -->
        <?php
        require_once __DIR__ . '/../includes/pos/MaterialSyncService.php';
        $materialSyncService = new MaterialSyncService($pdo);
        $materialMappings = $materialSyncService->getMappings();
        ?>
        <div class="dashboard-card" style="margin-top: 25px; border: 2px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border); flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 8px;">
                        🔄 System Integration
                    </h3>
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: var(--secondary); line-height: 1.5;">
                        Sync materials with Catalog and POS inventory for unified management across all systems.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="autoMapMaterials()" class="btn btn-outline" style="white-space: nowrap;">
                        🔗 Auto-Map Materials
                    </button>
                    <button onclick="syncAllMaterials()" class="btn btn-primary" style="white-space: nowrap;">
                        ⚡ Sync All Systems
                    </button>
                </div>
            </div>
            
            <?php if (!empty($materialMappings)): ?>
            <div style="margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: var(--text);">📊 Material Mappings</h4>
                    <span style="font-size: 11px; color: var(--secondary);"><?php echo count($materialMappings); ?> mapping(s)</span>
                </div>
                <div style="overflow-x: auto;">
                    <table class="data-table" style="font-size: 12px;">
                        <thead>
                            <tr style="background: var(--bg);">
                                <th style="padding: 10px; font-weight: 600;">Material</th>
                                <th style="padding: 10px; font-weight: 600;">Catalog Item</th>
                                <th style="padding: 10px; font-weight: 600; text-align: right;">Materials</th>
                                <th style="padding: 10px; font-weight: 600; text-align: right;">Catalog</th>
                                <th style="padding: 10px; font-weight: 600; text-align: right;">POS</th>
                                <th style="padding: 10px; font-weight: 600; text-align: center;">Status</th>
                                <th style="padding: 10px; font-weight: 600; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materialMappings as $mapping): ?>
                            <tr>
                                <td style="padding: 10px;">
                                    <strong style="color: var(--text);"><?php echo e($mapping['material_name']); ?></strong><br>
                                    <small style="color: var(--secondary); font-size: 11px;"><?php echo e($mapping['material_type']); ?></small>
                                </td>
                                <td style="padding: 10px;">
                                    <?php if ($mapping['catalog_name']): ?>
                                        <strong style="color: var(--text);"><?php echo e($mapping['catalog_name']); ?></strong><br>
                                        <small style="color: var(--secondary); font-size: 11px;"><?php echo e($mapping['catalog_sku'] ?: 'No SKU'); ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--secondary); font-size: 12px;">Not mapped</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px; text-align: right; font-weight: 600; color: var(--primary);">
                                    <?php echo number_format($mapping['materials_quantity'], 0); ?>
                                </td>
                                <td style="padding: 10px; text-align: right; font-weight: 600;">
                                    <?php echo number_format($mapping['catalog_quantity'], 0); ?>
                                </td>
                                <td style="padding: 10px; text-align: right; font-weight: 600;">
                                    <?php echo number_format($mapping['pos_quantity'], 0); ?>
                                </td>
                                <td style="padding: 10px; text-align: center;">
                                    <?php
                                    $diff = abs($mapping['materials_quantity'] - $mapping['catalog_quantity']);
                                    if ($diff < 1) {
                                        echo '<span style="color: #22c55e; font-weight: 600; font-size: 11px;">✅ Synced</span>';
                                    } else {
                                        echo '<span style="color: #f59e0b; font-weight: 600; font-size: 11px;">⚠️ Out of Sync</span><br>';
                                        echo '<small style="color: var(--secondary); font-size: 10px;">Diff: ' . number_format($diff, 0) . '</small>';
                                    }
                                    ?>
                                </td>
                                <td style="padding: 10px; text-align: center;">
                                    <button onclick="syncMaterialType('<?php echo e($mapping['material_type']); ?>')" 
                                            class="btn btn-sm btn-primary" 
                                            style="font-size: 11px; padding: 4px 10px;">
                                        🔄 Sync
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div style="padding: 20px; text-align: center; background: var(--bg); border-radius: 6px; margin-top: 15px;">
                <div style="font-size: 32px; margin-bottom: 10px;">🔗</div>
                <p style="margin: 0; font-size: 13px; color: var(--secondary); line-height: 1.6;">
                    No material mappings found. Click <strong>"Auto-Map Materials"</strong> to automatically link materials to catalog items.
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Material Activity Log -->
        <?php if (!empty($materialActivityLogs)): ?>
        <div class="dashboard-card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 15px;">📋 Material Activity Log</h3>
            <div style="overflow-x: auto;">
                <table class="data-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Material</th>
                            <th>Activity</th>
                            <th>Quantity Change</th>
                            <th>Before</th>
                            <th>After</th>
                            <th>Performed By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materialActivityLogs as $log): ?>
                        <tr>
                            <td><?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?></td>
                            <td>
                                <span class="badge" style="background: var(--primary); color: white; font-size: 11px;">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['material_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $activityLabels = [
                                    'sale_deduction' => '🔻 Sale Deduction',
                                    'return_request' => '📤 Return Request',
                                    'return_accepted' => '✅ Return Accepted',
                                    'return_rejected' => '❌ Return Rejected',
                                    'manual_adjustment' => '🔧 Manual Adjustment',
                                    'stock_sync' => '🔄 Stock Sync'
                                ];
                                echo $activityLabels[$log['activity_type']] ?? ucfirst(str_replace('_', ' ', $log['activity_type']));
                                ?>
                            </td>
                            <td style="<?php echo $log['quantity_change'] < 0 ? 'color: var(--danger);' : 'color: var(--success);'; ?> font-weight: 600;">
                                <?php echo $log['quantity_change'] > 0 ? '+' : ''; ?><?php echo number_format($log['quantity_change'], 2); ?>
                            </td>
                            <td><?php echo $log['quantity_before'] !== null ? number_format($log['quantity_before'], 2) : '—'; ?></td>
                            <td><?php echo $log['quantity_after'] !== null ? number_format($log['quantity_after'], 2) : '—'; ?></td>
                            <td><?php echo e($log['performed_by_name'] ?? 'System'); ?></td>
                            <td style="max-width: 200px; font-size: 12px; color: var(--secondary);">
                                <?php echo e($log['remarks'] ?: '—'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Transactions -->
        <?php if (!empty($recentTransactions)): ?>
        <div class="dashboard-card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 15px;">📜 Recent Inventory Transactions</h3>
            <div style="overflow-x: auto;">
                <table class="data-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Material</th>
                            <th>Type</th>
                            <th>Transaction</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recentTransactions, 0, 10) as $trans): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($trans['created_at'])); ?></td>
                            <td><?php echo e($trans['material_name']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $trans['material_type'])); ?></td>
                            <td>
                                <span class="badge <?php echo $trans['transaction_type'] === 'purchase' ? 'active' : ($trans['transaction_type'] === 'use' ? 'inactive' : ''); ?>">
                                    <?php echo ucfirst($trans['transaction_type']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($trans['quantity']); ?></td>
                            <td><?php echo formatCurrency($trans['unit_cost']); ?></td>
                            <td><?php echo formatCurrency($trans['total_cost']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <script>
        function filterMaterialsTable() {
            const search = document.getElementById('materialSearch').value.toLowerCase();
            const typeFilter = document.getElementById('materialTypeFilter').value;
            const rows = document.querySelectorAll('#materialsTable tbody tr');
            
            rows.forEach(row => {
                const materialName = row.getAttribute('data-material-name') || '';
                const materialType = row.getAttribute('data-material-type') || '';
                
                const matchesSearch = !search || materialName.includes(search);
                const matchesType = !typeFilter || materialType === typeFilter;
                
                row.style.display = (matchesSearch && matchesType) ? '' : 'none';
            });
        }
        
        function autoMapMaterials() {
            if (!confirm('This will automatically create mappings between materials and catalog items. Continue?')) {
                return;
            }
            
            fetch('api/material-sync.php?action=auto_map', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: '<?php echo CSRF::generateToken(); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Materials mapped successfully! ' + data.mapped_count + ' mappings created.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while mapping materials.');
            });
        }
        
        function syncAllMaterials() {
            if (!confirm('This will sync all materials inventory to catalog and POS. This may take a moment. Continue?')) {
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = '⏳ Syncing...';
            
            fetch('api/material-sync.php?action=sync_all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: '<?php echo CSRF::generateToken(); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Sync completed! ' + data.synced_count + ' materials synced.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = '⚡ Sync All to Catalog/POS';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while syncing materials.');
                btn.disabled = false;
                btn.textContent = '⚡ Sync All to Catalog/POS';
            });
        }
        
        function syncMaterialType(materialType) {
            if (!confirm('Sync this material type to catalog and POS?')) {
                return;
            }
            
            fetch('api/material-sync.php?action=sync_type', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    material_type: materialType,
                    csrf_token: '<?php echo CSRF::generateToken(); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Material synced successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while syncing material.');
            });
        }
        
        function openReturnMaterialModal(material) {
            document.getElementById('returnMaterialId').value = material.id;
            document.getElementById('returnMaterialType').value = material.material_type;
            document.getElementById('returnMaterialName').textContent = material.material_name;
            document.getElementById('returnMaterialAvailable').textContent = 
                material.quantity_remaining + ' ' + (material.unit_of_measure || 'pcs');
            document.getElementById('returnMaterialQuantity').value = '';
            document.getElementById('returnMaterialQuantity').max = material.quantity_remaining;
            document.getElementById('returnMaterialRemarks').value = '';
            document.getElementById('returnMaterialModal').style.display = 'flex';
        }
        
        function closeReturnMaterialModal() {
            document.getElementById('returnMaterialModal').style.display = 'none';
        }
        
        function submitReturnMaterial(event) {
            event.preventDefault();
            
            const quantity = parseFloat(document.getElementById('returnMaterialQuantity').value);
            const available = parseFloat(document.getElementById('returnMaterialAvailable').textContent);
            
            if (quantity <= 0) {
                alert('Quantity must be greater than 0');
                return;
            }
            
            if (quantity > available) {
                alert('Quantity cannot exceed available quantity');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'return_material');
            formData.append('material_type', document.getElementById('returnMaterialType').value);
            formData.append('quantity', quantity);
            formData.append('remarks', document.getElementById('returnMaterialRemarks').value);
            formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
            
            fetch('api/material-return-request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Return request submitted successfully! POS will be notified.');
                    closeReturnMaterialModal();
                    location.reload();
                } else {
                    const errorMsg = data.message || 'Failed to submit return request';
                    if (errorMsg.includes('migration') || errorMsg.includes('table') || errorMsg.includes('not initialized')) {
                        alert('Database tables not set up. Please run the migration first:\n\n' + 
                              'Go to: ' + window.location.origin + '/abbis3.2/pos/admin/run-materials-migration.php');
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function openTransferFromPosModal() {
            const modal = document.getElementById('transferFromPosModal');
            const loadingDiv = document.getElementById('transferPosLoading');
            const itemsDiv = document.getElementById('transferPosItems');
            const errorDiv = document.getElementById('transferPosError');
            
            // Reset
            itemsDiv.innerHTML = '';
            errorDiv.style.display = 'none';
            loadingDiv.style.display = 'block';
            modal.style.display = 'flex';
            
            // Fetch POS inventory items that can be transferred
            fetch('../pos/api/transfer-materials.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.style.display = 'none';
                
                if (data.success && data.items && data.items.length > 0) {
                    let html = '<div style="max-height: 400px; overflow-y: auto;">';
                    data.items.forEach(item => {
                        html += `
                            <div style="padding: 15px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px; background: var(--card);">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <div>
                                        <div style="font-weight: 600; color: var(--text); margin-bottom: 5px;">${item.name || item.product_name}</div>
                                        <div style="font-size: 13px; color: var(--secondary);">
                                            Available: <strong>${item.available_quantity || 0}</strong> ${item.unit || 'pcs'}
                                        </div>
                                        ${item.store_name ? `<div style="font-size: 12px; color: var(--secondary); margin-top: 3px;">Store: ${item.store_name}</div>` : ''}
                                    </div>
                                    <button type="button" onclick="selectPosItemForTransfer(${item.product_id || item.id}, '${(item.name || item.product_name).replace(/'/g, "\\'")}', ${item.available_quantity || 0}, '${item.material_type || ''}', ${item.store_id || 0})" 
                                            class="btn btn-sm btn-primary" style="padding: 6px 12px; font-size: 13px;">
                                        Transfer
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    itemsDiv.innerHTML = html;
                } else {
                    errorDiv.textContent = data.message || 'No POS items available for transfer.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingDiv.style.display = 'none';
                errorDiv.textContent = 'Error loading POS inventory. Please try again.';
                errorDiv.style.display = 'block';
            });
        }
        
        function closeTransferFromPosModal() {
            document.getElementById('transferFromPosModal').style.display = 'none';
        }
        
        function selectPosItemForTransfer(productId, productName, availableQty, materialType, storeId) {
            // Populate the transfer form
            document.getElementById('transferProductId').value = productId;
            document.getElementById('transferProductName').textContent = productName;
            document.getElementById('transferAvailableQty').textContent = availableQty;
            document.getElementById('transferQuantity').value = '';
            document.getElementById('transferQuantity').max = availableQty;
            document.getElementById('transferMaterialType').value = materialType || '';
            document.getElementById('transferStoreId').value = storeId || '';
            document.getElementById('transferRemarks').value = '';
            
            // Switch to transfer form view
            document.getElementById('transferPosItemsList').style.display = 'none';
            document.getElementById('transferPosForm').style.display = 'block';
        }
        
        function goBackToTransferList() {
            document.getElementById('transferPosItemsList').style.display = 'block';
            document.getElementById('transferPosForm').style.display = 'none';
        }
        
        function submitTransferFromPos(event) {
            event.preventDefault();
            
            const quantity = parseFloat(document.getElementById('transferQuantity').value);
            const available = parseFloat(document.getElementById('transferAvailableQty').textContent);
            const productId = document.getElementById('transferProductId').value;
            const materialType = document.getElementById('transferMaterialType').value;
            
            if (!quantity || quantity <= 0) {
                alert('Please enter a valid quantity');
                return;
            }
            
            if (quantity > available) {
                alert('Quantity cannot exceed available quantity');
                return;
            }
            
            if (!materialType) {
                alert('Please select a material type');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'transfer_from_pos');
            formData.append('product_id', productId);
            formData.append('material_type', materialType);
            formData.append('quantity', quantity);
            formData.append('store_id', document.getElementById('transferStoreId').value);
            formData.append('remarks', document.getElementById('transferRemarks').value);
            formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
            
            // Show loading
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Transferring...';
            
            fetch('resources.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Materials transferred successfully from POS to Materials Store!');
                    closeTransferFromPosModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || data.message || 'Transfer failed'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }
        </script>
        
        <!-- Return Material Modal -->
        <div id="returnMaterialModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 500px;">
                <h3>🔄 Return Materials to POS</h3>
                <form id="returnMaterialForm" onsubmit="submitReturnMaterial(event)">
                    <input type="hidden" id="returnMaterialId">
                    <input type="hidden" id="returnMaterialType">
                    
                    <div style="margin-bottom: 15px;">
                        <label><strong>Material:</strong></label>
                        <div id="returnMaterialName" style="padding: 8px; background: var(--input-bg); border-radius: 6px; margin-top: 5px;"></div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label><strong>Available Quantity:</strong></label>
                        <div id="returnMaterialAvailable" style="padding: 8px; background: var(--input-bg); border-radius: 6px; margin-top: 5px;"></div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="returnMaterialQuantity"><strong>Quantity to Return:</strong> <span style="color: var(--danger);">*</span></label>
                        <input type="number" id="returnMaterialQuantity" class="form-control" 
                               min="0.01" step="0.01" required 
                               placeholder="Enter quantity">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="returnMaterialRemarks"><strong>Remarks:</strong></label>
                        <textarea id="returnMaterialRemarks" class="form-control" rows="3" 
                                  placeholder="Reason for return (e.g., unused materials, quality check passed, etc.)"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" onclick="closeReturnMaterialModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="flex: 1; background: var(--warning);">Submit Return Request</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Transfer from POS Modal -->
        <div id="transferFromPosModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 700px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>📦 Transfer from POS to Material Store</h3>
                    <button class="close-btn" onclick="closeTransferFromPosModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text);">&times;</button>
                </div>
                
                <div id="transferPosLoading" style="text-align: center; padding: 40px;">
                    <div style="font-size: 18px; color: var(--secondary);">Loading POS inventory...</div>
                </div>
                
                <div id="transferPosError" style="display: none; padding: 15px; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); border-radius: 6px; color: var(--danger); margin-bottom: 15px;"></div>
                
                <div id="transferPosItemsList">
                    <div id="transferPosItems"></div>
                </div>
                
                <div id="transferPosForm" style="display: none;">
                    <form onsubmit="submitTransferFromPos(event)">
                        <input type="hidden" id="transferProductId">
                        <input type="hidden" id="transferStoreId">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <div style="margin-bottom: 15px;">
                            <label><strong>Product:</strong></label>
                            <div id="transferProductName" style="padding: 10px; background: var(--input-bg); border-radius: 6px; margin-top: 5px; font-weight: 600;"></div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label><strong>Available in POS:</strong></label>
                            <div id="transferAvailableQty" style="padding: 10px; background: var(--input-bg); border-radius: 6px; margin-top: 5px;"></div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="transferMaterialType"><strong>Material Type:</strong> <span style="color: var(--danger);">*</span></label>
                            <select id="transferMaterialType" class="form-control" required>
                                <option value="">Select Material Type</option>
                                <option value="screen_pipe">Screen Pipe</option>
                                <option value="plain_pipe">Plain Pipe</option>
                                <option value="gravel">Gravel</option>
                                <option value="rod">Rod</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="transferQuantity"><strong>Quantity to Transfer:</strong> <span style="color: var(--danger);">*</span></label>
                            <input type="number" id="transferQuantity" class="form-control" 
                                   min="0.01" step="0.01" required 
                                   placeholder="Enter quantity">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="transferRemarks"><strong>Remarks:</strong></label>
                            <textarea id="transferRemarks" class="form-control" rows="3" 
                                      placeholder="Optional remarks about this transfer"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" class="btn btn-outline" onclick="goBackToTransferList()">Back</button>
                            <button type="button" class="btn btn-outline" onclick="closeTransferFromPosModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary" style="background: #10b981;">Transfer to Materials</button>
                        </div>
                    </form>
                </div>
            </div>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0;">📦 Catalog Items</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" id="catalogSearch" placeholder="🔍 Search by name or SKU..." 
                           style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; min-width: 200px;"
                           onkeyup="filterCatalogTable()">
                    <select id="catalogTypeFilter" onchange="filterCatalogTable()" 
                            style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px;">
                        <option value="">All Types</option>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                    <select id="catalogCategoryFilter" onchange="filterCatalogTable()" 
                            style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px;">
                        <option value="">All Categories</option>
                        <?php 
                        $uniqueCategories = [];
                        foreach ($catalogItems as $item) {
                            $catName = $item['category_name'] ?: 'Uncategorized';
                            if (!in_array($catName, $uniqueCategories)) {
                                $uniqueCategories[] = $catName;
                            }
                        }
                        sort($uniqueCategories);
                        foreach ($uniqueCategories as $catName): 
                        ?>
                            <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo e($catName); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="catalogStatusFilter" onchange="filterCatalogTable()" 
                            style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px;">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <table class="data-table" id="catalogTable">
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
                <tbody id="catalogTableBody">
                    <?php if (empty($catalogItems)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px;">No items found. Click "Add Item" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($catalogItems as $item): ?>
                            <?php 
                            $inventoryQty = intval($item['inventory_quantity'] ?? 0);
                            $reorderLevel = intval($item['reorder_level'] ?? 0);
                            $isLowStock = $reorderLevel > 0 && $inventoryQty <= $reorderLevel;
                            ?>
                            <tr data-catalog-name="<?php echo strtolower(e($item['name'])); ?>" 
                                data-catalog-sku="<?php echo strtolower(e($item['sku'] ?: '')); ?>"
                                data-catalog-type="<?php echo strtolower($item['item_type']); ?>"
                                data-catalog-category="<?php echo strtolower(e($item['category_name'] ?: 'uncategorized')); ?>"
                                data-catalog-status="<?php echo $item['is_active'] ? 'active' : 'inactive'; ?>">
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
            <div id="catalogNoResults" style="display: none; text-align: center; padding: 40px; color: var(--secondary);">
                No items match your search criteria.
            </div>
        </div>
        
        <script>
        function filterCatalogTable() {
            const search = (document.getElementById('catalogSearch')?.value || '').toLowerCase();
            const typeFilter = (document.getElementById('catalogTypeFilter')?.value || '').toLowerCase();
            const categoryFilter = (document.getElementById('catalogCategoryFilter')?.value || '').toLowerCase();
            const statusFilter = (document.getElementById('catalogStatusFilter')?.value || '').toLowerCase();
            
            const rows = document.querySelectorAll('#catalogTableBody tr');
            const noResults = document.getElementById('catalogNoResults');
            const table = document.getElementById('catalogTable');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.getAttribute('data-catalog-name') || '';
                const sku = row.getAttribute('data-catalog-sku') || '';
                const type = row.getAttribute('data-catalog-type') || '';
                const category = row.getAttribute('data-catalog-category') || '';
                const status = row.getAttribute('data-catalog-status') || '';
                
                const matchesSearch = !search || name.includes(search) || sku.includes(search);
                const matchesType = !typeFilter || type === typeFilter;
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesType && matchesCategory && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (visibleCount === 0 && rows.length > 0) {
                noResults.style.display = 'block';
                table.style.display = 'none';
            } else {
                noResults.style.display = 'none';
                table.style.display = '';
            }
        }
        </script>
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
                                        <button onclick="openAssetModal(<?php echo htmlspecialchars(json_encode($asset, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="btn btn-sm btn-outline" title="Edit Rig">Edit</button>
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
        // Get rigs with location data and most recent field report location
        // Fix: Use a subquery with LIMIT 1 to prevent duplication when multiple reports have same max date
        $allRigsQuery = "
            SELECT 
                r.*,
                COALESCE(r.current_rpm, 0) as current_rpm,
                COALESCE(r.last_maintenance_rpm, 0) as last_maintenance_rpm,
                r.maintenance_due_at_rpm,
                COALESCE(r.maintenance_rpm_interval, 30.00) as maintenance_rpm_interval,
                r.current_latitude,
                r.current_longitude,
                r.current_location_updated_at,
                r.tracking_enabled,
                CASE 
                    WHEN r.maintenance_due_at_rpm IS NULL THEN NULL
                    WHEN r.current_rpm >= r.maintenance_due_at_rpm THEN 'due'
                    WHEN (r.maintenance_due_at_rpm - r.current_rpm) <= (r.maintenance_rpm_interval * 0.1) THEN 'soon'
                    ELSE 'ok'
                END as maintenance_status,
                CASE 
                    WHEN r.maintenance_due_at_rpm IS NULL THEN NULL
                    ELSE GREATEST(0, r.maintenance_due_at_rpm - COALESCE(r.current_rpm, 0))
                END as rpm_remaining,
                fr_latest.site_name as last_site_name,
                fr_latest.plus_code as last_plus_code,
                fr_latest.latitude as last_latitude,
                fr_latest.longitude as last_longitude,
                fr_latest.location_description as last_location_description,
                fr_latest.region as last_region,
                fr_latest.report_date as last_report_date
            FROM rigs r
            LEFT JOIN (
                SELECT fr.*
                FROM field_reports fr
                INNER JOIN (
                    SELECT rig_id, MAX(id) as max_id
                    FROM field_reports
                    WHERE report_date = (
                        SELECT MAX(report_date) 
                        FROM field_reports fr2 
                        WHERE fr2.rig_id = field_reports.rig_id
                    )
                    GROUP BY rig_id
                ) latest ON fr.id = latest.max_id
            ) fr_latest ON r.id = fr_latest.rig_id
            WHERE r.status = 'active'
            GROUP BY r.id
            ORDER BY r.rig_name
        ";
        $allRigsStmt = $pdo->prepare($allRigsQuery);
        $allRigsStmt->execute();
        $allRigs = $allRigsStmt->fetchAll(PDO::FETCH_ASSOC);
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
                        
                        // Determine location to display
                        // Priority: 1) Tracked location (current_latitude/longitude), 2) Last field report location, 3) Site name only
                        $hasCurrentLocation = !empty($rig['current_latitude']) && !empty($rig['current_longitude']) && 
                                             ($rig['current_latitude'] != 0 && $rig['current_longitude'] != 0);
                        $hasLastReportLocation = !empty($rig['last_latitude']) && !empty($rig['last_longitude']) && 
                                                ($rig['last_latitude'] != 0 && $rig['last_longitude'] != 0);
                        $hasSiteName = !empty($rig['last_site_name']);
                        
                        $locationSource = null;
                        $locationLat = null;
                        $locationLng = null;
                        $locationName = null;
                        $locationUpdated = null;
                        $showLocation = false;
                        
                        if ($hasCurrentLocation) {
                            // Use tracked GPS location
                            $locationSource = 'tracked';
                            $locationLat = floatval($rig['current_latitude']);
                            $locationLng = floatval($rig['current_longitude']);
                            $locationName = 'Current Location';
                            $locationUpdated = $rig['current_location_updated_at'];
                            $showLocation = true;
                        } elseif ($hasLastReportLocation) {
                            // Use last field report GPS location
                            $locationSource = 'last_report';
                            $locationLat = floatval($rig['last_latitude']);
                            $locationLng = floatval($rig['last_longitude']);
                            $locationName = $rig['last_site_name'] ?? 'Last Job Site';
                            $locationUpdated = $rig['last_report_date'];
                            $showLocation = true;
                        } elseif ($hasSiteName) {
                            // Only site name available, no coordinates
                            $locationSource = 'site_name_only';
                            $locationName = $rig['last_site_name'];
                            $locationUpdated = $rig['last_report_date'];
                            $showLocation = false; // Can't show map without coordinates
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
                            
                            <?php if ($showLocation && $locationLat && $locationLng): ?>
                                <!-- Location with GPS coordinates - can show map -->
                                <div style="margin-bottom: 15px; padding: 10px; background: rgba(14, 165, 233, 0.05); border-radius: 6px; border-left: 3px solid var(--primary);">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <span style="font-size: 16px;">📍</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 2px;">
                                                <?php echo e($locationName); ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--secondary);">
                                                <?php if ($locationSource === 'tracked'): ?>
                                                    <span style="color: #28a745;">●</span> Tracked Location
                                                    <?php if ($locationUpdated): ?>
                                                        • <?php echo date('M j, Y', strtotime($locationUpdated)); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #ffc107;">●</span> Last Job Site
                                                    <?php if ($locationUpdated): ?>
                                                        • <?php echo date('M j, Y', strtotime($locationUpdated)); ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($rig['last_plus_code'])): ?>
                                        <div style="font-size: 11px; color: var(--secondary); margin-top: 4px; margin-bottom: 4px;">
                                            Plus Code: <code style="background: rgba(0,0,0,0.05); padding: 2px 4px; border-radius: 3px;"><?php echo e($rig['last_plus_code']); ?></code>
                                        </div>
                                    <?php endif; ?>
                                    <a href="https://www.google.com/maps?q=<?php echo $locationLat; ?>,<?php echo $locationLng; ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline" 
                                       style="margin-top: 8px; width: 100%; text-align: center; padding: 6px; font-size: 11px;">
                                        🗺️ View on Map
                                    </a>
                                </div>
                            <?php elseif ($locationSource === 'site_name_only' && $locationName): ?>
                                <!-- Only site name available, no GPS coordinates -->
                                <div style="margin-bottom: 15px; padding: 10px; background: rgba(255, 193, 7, 0.05); border-radius: 6px; border-left: 3px solid #ffc107;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 16px;">📍</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 2px;">
                                                <?php echo e($locationName); ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--secondary);">
                                                <span style="color: #ffc107;">●</span> Site Name Only
                                                <?php if ($locationUpdated): ?>
                                                    • <?php echo date('M j, Y', strtotime($locationUpdated)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 10px; color: var(--secondary); margin-top: 4px; font-style: italic;">
                                                GPS coordinates not available
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- No location data available -->
                                <div style="margin-bottom: 15px; padding: 10px; background: rgba(108, 117, 125, 0.05); border-radius: 6px; text-align: center;">
                                    <div style="font-size: 12px; color: var(--secondary);">
                                        📍 Location not available
                                    </div>
                                    <div style="font-size: 10px; color: var(--secondary); margin-top: 4px; font-style: italic;">
                                        No GPS tracking or recent field report location
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 8px; margin-top: 15px;">
                                <button type="button" class="btn btn-sm btn-primary" style="flex: 1; text-align: center; padding: 8px;" 
                                        onclick="showRigDetailsModal(<?php echo htmlspecialchars(json_encode($rig)); ?>)">View Details</button>
                                <button type="button" class="btn btn-sm btn-outline" style="flex: 1; text-align: center; padding: 8px;" 
                                        onclick="showRigConfigureModal(<?php echo htmlspecialchars(json_encode($rig)); ?>)">Configure</button>
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
                    <label>Model <span id="modelLabelNote" style="font-size: 11px; color: var(--secondary); display: none;">(Truck Model for Rigs)</span></label>
                    <input type="text" name="model" id="assetModel" placeholder="e.g., Volvo FH16">
                </div>
            </div>
            
            <div class="form-group" id="registrationNumberField" style="display: none;">
                <label>Registration Number</label>
                <input type="text" name="registration_number" id="registrationNumber" placeholder="e.g., GR-1234-A">
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
    const isRig = asset && (asset.is_rig || asset.rig_id);
    
    form.reset();
    if (asset) {
        if (isRig) {
            title.textContent = 'Edit Rig';
            document.getElementById('assetAction').value = 'edit_rig';
            document.getElementById('assetId').value = asset.rig_id || asset.id;
            // Store rig_id for reference
            if (!document.getElementById('rigId')) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'rigId';
                hiddenInput.name = 'rig_id';
                form.appendChild(hiddenInput);
            }
            document.getElementById('rigId').value = asset.rig_id || asset.id;
        } else {
            title.textContent = 'Edit Asset';
            document.getElementById('assetAction').value = 'edit_asset';
            document.getElementById('assetId').value = asset.id;
        }
        
        document.getElementById('assetCode').value = asset.asset_code || '';
        document.getElementById('assetName').value = asset.asset_name || '';
        document.getElementById('assetType').value = asset.asset_type || 'equipment';
        document.getElementById('assetCategory').value = asset.category || '';
        document.getElementById('assetBrand').value = asset.brand || '';
        // For rigs, map truck_model to model field; for assets, use model directly
        document.getElementById('assetModel').value = (isRig ? (asset.truck_model || asset.model) : (asset.model || '')) || '';
        document.getElementById('purchaseDate').value = asset.purchase_date || '';
        document.getElementById('purchaseCost').value = asset.purchase_cost || '0.00';
        document.getElementById('currentValue').value = asset.current_value || '0.00';
        document.getElementById('assetLocation').value = asset.location || '';
        document.getElementById('assetStatus').value = asset.status || 'active';
        document.getElementById('assetCondition').value = asset.condition || 'good';
        document.getElementById('assetNotes').value = asset.notes || '';
        
        // Handle registration number field for rigs
        const regField = document.getElementById('registrationNumberField');
        const regInput = document.getElementById('registrationNumber');
        if (regField && regInput) {
            if (isRig) {
                regField.style.display = 'block';
                regInput.value = asset.registration_number || '';
                document.getElementById('modelLabelNote').style.display = 'inline';
            } else {
                regField.style.display = 'none';
                document.getElementById('modelLabelNote').style.display = 'none';
            }
        }
        
        // Disable certain fields for rigs (they're managed in config)
        if (isRig) {
            document.getElementById('assetCode').disabled = true;
            document.getElementById('assetName').disabled = true;
            document.getElementById('assetType').disabled = true;
            // Only allow editing model (truck_model), registration number, brand, category, status, and notes
            // Purchase date, cost, value, location, condition are not applicable for rigs
            document.getElementById('purchaseDate').disabled = true;
            document.getElementById('purchaseCost').disabled = true;
            document.getElementById('currentValue').disabled = true;
            document.getElementById('assetLocation').disabled = true;
            document.getElementById('assetCondition').disabled = true;
        } else {
            document.getElementById('assetCode').disabled = false;
            document.getElementById('assetName').disabled = false;
            document.getElementById('assetType').disabled = false;
            document.getElementById('purchaseDate').disabled = false;
            document.getElementById('purchaseCost').disabled = false;
            document.getElementById('currentValue').disabled = false;
            document.getElementById('assetLocation').disabled = false;
            document.getElementById('assetCondition').disabled = false;
        }
    } else {
        title.textContent = 'Add Asset';
        document.getElementById('assetAction').value = 'add_asset';
        document.getElementById('assetId').value = '';
        document.getElementById('assetCode').disabled = false;
        document.getElementById('assetName').disabled = false;
        document.getElementById('assetType').disabled = false;
        document.getElementById('purchaseDate').disabled = false;
        document.getElementById('purchaseCost').disabled = false;
        document.getElementById('currentValue').disabled = false;
        document.getElementById('assetLocation').disabled = false;
        document.getElementById('assetCondition').disabled = false;
        if (document.getElementById('registrationNumberField')) {
            document.getElementById('registrationNumberField').style.display = 'none';
        }
        if (document.getElementById('modelLabelNote')) {
            document.getElementById('modelLabelNote').style.display = 'none';
        }
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
    const form = document.getElementById('assetForm');
    const formData = new FormData(form);
    const action = formData.get('action');
    const isRig = action === 'edit_rig';
    
    // If editing a rig, use the config API instead
    if (isRig) {
        const rigId = formData.get('rig_id') || formData.get('id');
        const rigData = {
            action: 'update_rig',
            id: rigId,
            rig_name: formData.get('asset_name'), // Keep original name (disabled field)
            rig_code: formData.get('asset_code'), // Keep original code (disabled field)
            truck_model: formData.get('model') || null, // Map model field to truck_model
            registration_number: formData.get('registration_number') || null,
            status: formData.get('status'),
            csrf_token: '<?php echo CSRF::generateToken(); ?>'
        };
        
        // Convert to FormData for consistency
        const rigFormData = new FormData();
        Object.keys(rigData).forEach(key => {
            rigFormData.append(key, rigData[key]);
        });
        
        fetch('api/config-crud.php', {
            method: 'POST',
            body: rigFormData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Rig updated successfully', 'success');
                closeAssetModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message || data.error || 'Save failed', 'error');
            }
        })
        .catch(e => {
            console.error('Error:', e);
            showNotification('An error occurred while updating rig', 'error');
        });
    } else {
        // Regular asset save
        formData.append('csrf_token', '<?php echo CSRF::generateToken(); ?>');
        
        fetch('resources.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                closeAssetModal();
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

// Rig Details Modal
function showRigDetailsModal(rig) {
    const modal = document.getElementById('rigDetailsModal');
    const currentRpm = parseFloat(rig.current_rpm || 0);
    const dueAtRpm = rig.maintenance_due_at_rpm ? parseFloat(rig.maintenance_due_at_rpm) : null;
    const interval = parseFloat(rig.maintenance_rpm_interval || 30);
    const lastRpm = parseFloat(rig.last_maintenance_rpm || 0);
    const rpmRemaining = dueAtRpm ? Math.max(0, dueAtRpm - currentRpm) : null;
    const progress = dueAtRpm && currentRpm > 0 ? Math.min(100, (currentRpm / dueAtRpm) * 100) : 0;
    
    // Determine status
    let statusColor = '#28a745';
    let statusText = 'OK';
    if (rig.maintenance_status === 'due') {
        statusColor = '#dc3545';
        statusText = 'Due Now';
    } else if (rig.maintenance_status === 'soon') {
        statusColor = '#ffc107';
        statusText = 'Due Soon';
    }
    
    // Populate modal content
    document.getElementById('rigDetailsName').textContent = rig.rig_name;
    document.getElementById('rigDetailsCode').textContent = rig.rig_code;
    document.getElementById('rigDetailsStatus').textContent = statusText;
    document.getElementById('rigDetailsStatus').style.color = statusColor;
    document.getElementById('rigDetailsCurrentRpm').textContent = currentRpm.toFixed(2);
    document.getElementById('rigDetailsDueAtRpm').textContent = dueAtRpm ? dueAtRpm.toFixed(2) : 'Not configured';
    document.getElementById('rigDetailsInterval').textContent = interval.toFixed(2);
    document.getElementById('rigDetailsLastRpm').textContent = lastRpm > 0 ? lastRpm.toFixed(2) : 'N/A';
    document.getElementById('rigDetailsRemaining').textContent = rpmRemaining !== null ? rpmRemaining.toFixed(2) : 'N/A';
    document.getElementById('rigDetailsProgress').style.width = progress + '%';
    document.getElementById('rigDetailsProgress').className = 'rpm-progress-fill ' + rig.maintenance_status;
    
    // Show maintenance records link
    document.getElementById('rigDetailsViewRecords').href = 'resources.php?action=maintenance&rig_id=' + rig.id + '#maintenance-records';
    
    // Show location if available
    const locationSection = document.getElementById('rigDetailsLocationSection');
    const hasCurrentLocation = rig.current_latitude && rig.current_longitude;
    const hasLastReportLocation = rig.last_latitude && rig.last_longitude;
    
    if (hasCurrentLocation || hasLastReportLocation) {
        const lat = hasCurrentLocation ? rig.current_latitude : rig.last_latitude;
        const lng = hasCurrentLocation ? rig.current_longitude : rig.last_longitude;
        const locationName = hasCurrentLocation ? 'Current Location' : (rig.last_site_name || 'Last Job Site');
        const locationSource = hasCurrentLocation ? 
            '<span style="color: #28a745;">●</span> Tracked' + (rig.current_location_updated_at ? ' • ' + new Date(rig.current_location_updated_at).toLocaleDateString() : '') :
            '<span style="color: #ffc107;">●</span> Last Job Site' + (rig.last_report_date ? ' • ' + new Date(rig.last_report_date).toLocaleDateString() : '');
        
        document.getElementById('rigDetailsLocationName').textContent = locationName;
        document.getElementById('rigDetailsLocationSource').innerHTML = locationSource;
        document.getElementById('rigDetailsLocationCoords').textContent = lat + ', ' + lng;
        document.getElementById('rigDetailsLocationMapLink').href = 'https://www.google.com/maps?q=' + lat + ',' + lng;
        
        if (rig.last_plus_code) {
            document.getElementById('rigDetailsLocationPlusCode').innerHTML = 'Plus Code: <code style="background: rgba(0,0,0,0.05); padding: 2px 4px; border-radius: 3px;">' + rig.last_plus_code + '</code>';
            document.getElementById('rigDetailsLocationPlusCode').style.display = 'block';
        } else {
            document.getElementById('rigDetailsLocationPlusCode').style.display = 'none';
        }
        
        locationSection.style.display = 'block';
    } else {
        locationSection.style.display = 'none';
    }
    
    modal.style.display = 'flex';
}

function closeRigDetailsModal() {
    document.getElementById('rigDetailsModal').style.display = 'none';
}

// Rig Configure Modal
function showRigConfigureModal(rig) {
    const modal = document.getElementById('rigConfigureModal');
    document.getElementById('rigConfigureId').value = rig.id;
    document.getElementById('rigConfigureName').textContent = rig.rig_name;
    document.getElementById('rigConfigureCode').textContent = rig.rig_code;
    document.getElementById('rigConfigureCurrentRpm').value = rig.current_rpm || 0;
    document.getElementById('rigConfigureInterval').value = rig.maintenance_rpm_interval || 30;
    document.getElementById('rigConfigureDueAtRpm').value = rig.maintenance_due_at_rpm || '';
    
    // Update status display
    updateRigConfigureStatus();
    
    modal.style.display = 'flex';
}

function closeRigConfigureModal() {
    document.getElementById('rigConfigureModal').style.display = 'none';
}

function updateRigConfigureStatus() {
    const currentRpm = parseFloat(document.getElementById('rigConfigureCurrentRpm').value) || 0;
    const interval = parseFloat(document.getElementById('rigConfigureInterval').value) || 30;
    const dueAtRpm = document.getElementById('rigConfigureDueAtRpm').value ? 
        parseFloat(document.getElementById('rigConfigureDueAtRpm').value) : null;
    
    const calculatedDueAt = dueAtRpm || (currentRpm + interval);
    const rpmRemaining = calculatedDueAt - currentRpm;
    const percentage = calculatedDueAt > 0 ? (currentRpm / calculatedDueAt * 100) : 0;
    
    let statusColor = '#28a745';
    let statusText = 'OK';
    if (rpmRemaining <= 0) {
        statusColor = '#dc3545';
        statusText = 'Due Now';
    } else if (rpmRemaining <= interval * 0.1) {
        statusColor = '#ffc107';
        statusText = 'Due Soon';
    }
    
    const statusDisplay = document.getElementById('rigConfigureStatusDisplay');
    statusDisplay.innerHTML = `
        <div style="margin-bottom: 8px;">
            <strong>Current:</strong> ${currentRpm.toFixed(2)} RPM
        </div>
        <div style="margin-bottom: 8px;">
            <strong>Due At:</strong> ${calculatedDueAt.toFixed(2)} RPM
        </div>
        <div style="margin-bottom: 8px;">
            <strong>Remaining:</strong> <span style="font-weight: 600; color: ${statusColor};">${rpmRemaining.toFixed(2)} RPM</span>
        </div>
        <div style="margin-bottom: 8px;">
            <strong>Status:</strong> <span style="font-weight: 600; color: ${statusColor};">${statusText}</span>
        </div>
        <div style="margin-top: 12px;">
            <div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background: ${statusColor}; height: 100%; width: ${Math.min(100, percentage)}%; transition: width 0.3s;"></div>
            </div>
            <div style="text-align: center; font-size: 11px; color: var(--secondary); margin-top: 4px;">
                ${percentage.toFixed(1)}% complete
            </div>
        </div>
    `;
    statusDisplay.style.display = 'block';
}

// Add event listeners for RPM input changes
document.addEventListener('DOMContentLoaded', function() {
    const currentRpmInput = document.getElementById('rigConfigureCurrentRpm');
    const intervalInput = document.getElementById('rigConfigureInterval');
    const dueAtRpmInput = document.getElementById('rigConfigureDueAtRpm');
    
    if (currentRpmInput) {
        currentRpmInput.addEventListener('input', updateRigConfigureStatus);
    }
    if (intervalInput) {
        intervalInput.addEventListener('input', updateRigConfigureStatus);
    }
    if (dueAtRpmInput) {
        dueAtRpmInput.addEventListener('input', updateRigConfigureStatus);
    }
    
    // Close modals when clicking outside
    const detailsModal = document.getElementById('rigDetailsModal');
    const configureModal = document.getElementById('rigConfigureModal');
    
    if (detailsModal) {
        detailsModal.addEventListener('click', function(event) {
            if (event.target === detailsModal) {
                closeRigDetailsModal();
            }
        });
    }
    
    if (configureModal) {
        configureModal.addEventListener('click', function(event) {
            if (event.target === configureModal) {
                closeRigConfigureModal();
            }
        });
    }
});
</script>

<!-- Rig Details Modal -->
<div id="rigDetailsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Rig Maintenance Details</h2>
            <button type="button" class="modal-close" onclick="closeRigDetailsModal()">&times;</button>
        </div>
        <div style="padding: 20px;">
            <div style="margin-bottom: 20px;">
                <h3 id="rigDetailsName" style="margin: 0 0 5px 0; color: var(--text);"></h3>
                <div style="font-size: 14px; color: var(--secondary);" id="rigDetailsCode"></div>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="font-weight: 600; color: var(--text);">Status:</span>
                    <span id="rigDetailsStatus" style="font-weight: 600;"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--secondary);">Current RPM:</span>
                    <strong style="color: var(--primary);" id="rigDetailsCurrentRpm"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--secondary);">Due At RPM:</span>
                    <strong id="rigDetailsDueAtRpm"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--secondary);">RPM Interval:</span>
                    <strong id="rigDetailsInterval"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="color: var(--secondary);">Last Maintenance RPM:</span>
                    <strong id="rigDetailsLastRpm"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <span style="color: var(--secondary);">RPM Remaining:</span>
                    <strong id="rigDetailsRemaining"></strong>
                </div>
                <div class="rpm-progress-bar" style="margin-top: 10px;">
                    <div id="rigDetailsProgress" class="rpm-progress-fill" style="width: 0%;"></div>
                </div>
            </div>
            
            <div id="rigDetailsLocationSection" style="background: rgba(14, 165, 233, 0.05); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 3px solid var(--primary); display: none;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <span style="font-size: 18px;">📍</span>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: var(--text); margin-bottom: 4px;" id="rigDetailsLocationName"></div>
                        <div style="font-size: 12px; color: var(--secondary);" id="rigDetailsLocationSource"></div>
                    </div>
                </div>
                <div style="font-size: 12px; color: var(--secondary); margin-bottom: 10px;" id="rigDetailsLocationCoords"></div>
                <div id="rigDetailsLocationPlusCode" style="font-size: 11px; color: var(--secondary); margin-bottom: 10px; display: none;"></div>
                <a id="rigDetailsLocationMapLink" href="#" target="_blank" class="btn btn-sm btn-outline" style="width: 100%; text-align: center; padding: 6px;">
                    🗺️ View on Map
                </a>
            </div>
            
            <div style="text-align: center;">
                <a id="rigDetailsViewRecords" href="#" class="btn btn-primary">View Maintenance Records</a>
                <button type="button" class="btn btn-outline" onclick="closeRigDetailsModal()" style="margin-left: 10px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Rig Configure Modal -->
<div id="rigConfigureModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Configure Rig Maintenance</h2>
            <button type="button" class="modal-close" onclick="closeRigConfigureModal()">&times;</button>
        </div>
        <form method="POST" action="<?php echo api_url('config-crud.php'); ?>" id="rigConfigureForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="update_rig">
            <input type="hidden" name="id" id="rigConfigureId" value="">
            
            <div style="padding: 20px;">
                <div style="margin-bottom: 20px;">
                    <h3 id="rigConfigureName" style="margin: 0 0 5px 0; color: var(--text);"></h3>
                    <div style="font-size: 14px; color: var(--secondary);" id="rigConfigureCode"></div>
                </div>
                
                <div class="form-group">
                    <label for="rigConfigureCurrentRpm" class="form-label">Current RPM *</label>
                    <input type="number" id="rigConfigureCurrentRpm" name="current_rpm" class="form-control" 
                           step="0.01" min="0" required>
                    <small style="color: var(--secondary);">Cumulative RPM from all field reports</small>
                </div>
                
                <div class="form-group">
                    <label for="rigConfigureInterval" class="form-label">Maintenance RPM Interval *</label>
                    <input type="number" id="rigConfigureInterval" name="maintenance_rpm_interval" class="form-control" 
                           step="0.01" min="0" value="30.00" required>
                    <small style="color: var(--secondary);">Service interval (e.g., 30.00 means service every 30 RPM)</small>
                </div>
                
                <div class="form-group">
                    <label for="rigConfigureDueAtRpm" class="form-label">Next Maintenance Due At (RPM)</label>
                    <input type="number" id="rigConfigureDueAtRpm" name="maintenance_due_at_rpm" class="form-control" 
                           step="0.01" min="0">
                    <small style="color: var(--secondary);">RPM threshold when maintenance is due. Auto-calculated if not set.</small>
                </div>
                
                <div id="rigConfigureStatusDisplay" style="padding: 12px; background: #f8f9fa; border-radius: 6px; margin-top: 12px; display: none;"></div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                    <button type="button" class="btn btn-outline" onclick="closeRigConfigureModal()">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Handle rig configure form submission
document.getElementById('rigConfigureForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../api/config-crud.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Rig configuration updated successfully', 'success');
            setTimeout(() => {
                closeRigConfigureModal();
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Failed to update configuration', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating configuration', 'error');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
