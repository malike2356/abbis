<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';
require_once '../includes/AccountingAutoTracker.php';

$auth->requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$materialType = $_POST['material_type'] ?? '';
$catalogItemId = !empty($_POST['catalog_item_id']) ? intval($_POST['catalog_item_id']) : null;
$quantityReceived = intval($_POST['quantity_received'] ?? 0);
$unitCost = floatval($_POST['unit_cost'] ?? 0);
$action = $_POST['action'] ?? 'update';

$pdo = getDBConnection();

try {
    if (empty($materialType)) {
        throw new Exception('Material type is required');
    }
    
    $validMaterials = ['screen_pipe', 'plain_pipe', 'gravel'];
    if (!in_array($materialType, $validMaterials)) {
        throw new Exception('Invalid material type');
    }
    
    switch ($action) {
        case 'update':
            if ($quantityReceived < 0) {
                throw new Exception('Quantity received cannot be negative');
            }
            
            if ($unitCost < 0) {
                throw new Exception('Unit cost cannot be negative');
            }
            
            $stmt = $pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_received = quantity_received + ?, 
                    unit_cost = ?,
                    quantity_remaining = (quantity_received + ?) - quantity_used,
                    total_value = ((quantity_received + ?) - quantity_used) * ?,
                    last_updated = NOW()
                WHERE material_type = ?
            ");
            
            $stmt->execute([
                $quantityReceived, 
                $unitCost, 
                $quantityReceived, 
                $quantityReceived, 
                $unitCost, 
                $materialType
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Material not found in inventory');
            }
            
            // Get updated material data
            $updatedStmt = $pdo->prepare("SELECT * FROM materials_inventory WHERE material_type = ?");
            $updatedStmt->execute([$materialType]);
            $updatedMaterial = $updatedStmt->fetch();
            
            // SYSTEM-WIDE SYNC: Update inventory across CMS, POS, and ABBIS
            // When materials are received, they go TO operations (materials_inventory) ✓ Already done above
            // Also need to update catalog_items (CMS/POS source of truth) and pos_inventory
            try {
                require_once __DIR__ . '/../includes/pos/UnifiedInventoryService.php';
                $inventoryService = new UnifiedInventoryService($pdo);
                
                // Get material mapping to find catalog_item_id
                $mappingStmt = $pdo->prepare("
                    SELECT catalog_item_id, pos_product_id 
                    FROM pos_material_mappings 
                    WHERE material_type = ?
                ");
                $mappingStmt->execute([$materialType]);
                $mapping = $mappingStmt->fetch(PDO::FETCH_ASSOC);
                
                $catalogItemId = null;
                if ($mapping && $mapping['catalog_item_id']) {
                    $catalogItemId = (int)$mapping['catalog_item_id'];
                } else {
                    // Try to find catalog item by material type name pattern
                    $materialName = ucfirst(str_replace('_', ' ', $materialType));
                    $catalogStmt = $pdo->prepare("
                        SELECT id FROM catalog_items 
                        WHERE (name LIKE ? OR sku LIKE ?) 
                        AND item_type = 'product'
                        LIMIT 1
                    ");
                    $searchPattern = "%{$materialName}%";
                    $catalogStmt->execute([$searchPattern, $searchPattern]);
                    $catalogItem = $catalogStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($catalogItem) {
                        $catalogItemId = (int)$catalogItem['id'];
                        // Create mapping for future use
                        try {
                            $mapStmt = $pdo->prepare("
                                INSERT INTO pos_material_mappings (material_type, catalog_item_id, created_at)
                                VALUES (?, ?, NOW())
                                ON DUPLICATE KEY UPDATE catalog_item_id = VALUES(catalog_item_id)
                            ");
                            $mapStmt->execute([$materialType, $catalogItemId]);
                        } catch (PDOException $e) {
                            error_log("[update-materials] Could not create mapping: " . $e->getMessage());
                        }
                    }
                }
                
                // Update catalog_items (source of truth) - this syncs to POS and CMS automatically
                if ($catalogItemId) {
                    $inventoryService->updateCatalogStock(
                        $catalogItemId,
                        $quantityReceived, // Positive = increase inventory
                        "Material receipt: {$materialType} (Purchase)"
                    );
                    error_log("[update-materials] Synced material receipt to catalog_item_id {$catalogItemId}: +{$quantityReceived} units");
                } else {
                    error_log("[update-materials] WARNING: No catalog_item found for material_type: {$materialType}. Inventory not synced to CMS/POS.");
                }
            } catch (Exception $e) {
                error_log('[update-materials] System-wide inventory sync failed: ' . $e->getMessage());
                error_log('[update-materials] Stack trace: ' . $e->getTraceAsString());
                // Continue - don't fail the update if sync fails
            }
            
            // Try to log inventory transaction with catalog link
            try {
                // Ensure migration applied (add catalog_item_id, nullable material_id)
                @include_once __DIR__ . '/../database/run-sql.php';
                $path = __DIR__ . '/../database/inventory_catalog_link_migration.sql';
                if (function_exists('run_sql_file')) { @run_sql_file($path); }
                else {
                    $sql = @file_get_contents($path);
                    if ($sql) { foreach (preg_split('/;\s*\n/', $sql) as $stmt) { $stmt = trim($stmt); if ($stmt) { try { $pdo->exec($stmt); } catch (Throwable $ignored) {} } } }
                }
                $txCode = 'INV-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)),0,6);
                $tx = $pdo->prepare("INSERT INTO inventory_transactions (transaction_code, material_id, transaction_type, quantity, unit_cost, total_cost, reference_type, reference_id, notes, created_by, catalog_item_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $tx->execute([
                    $txCode,
                    null,
                    'purchase',
                    $quantityReceived,
                    $unitCost,
                    $quantityReceived * $unitCost,
                    'materials_update',
                    null,
                    'Updated via Materials module',
                    $_SESSION['user_id'] ?? 1,
                    $catalogItemId
                ]);
                
                $transactionId = $pdo->lastInsertId();
                
                // Automatically track materials purchase in accounting - runs for EVERY purchase
                if ($quantityReceived > 0 && $unitCost > 0) {
                    try {
                        // Ensure accounting tables exist
                        try {
                            $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
                        } catch (PDOException $e) {
                            // Initialize if needed
                            $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
                            if (file_exists($migrationFile)) {
                                $sql = file_get_contents($migrationFile);
                                if ($sql) {
                                    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                                        $stmt = trim($stmt);
                                        if ($stmt) {
                                            try {
                                                $pdo->exec($stmt);
                                            } catch (PDOException $e2) {}
                                        }
                                    }
                                }
                            }
                        }
                        
                        $accountingTracker = new AccountingAutoTracker($pdo);
                        $result = $accountingTracker->trackMaterialsPurchase($transactionId, [
                            'description' => "Purchase: {$materialType}",
                            'total_cost' => $quantityReceived * $unitCost,
                            'unit_cost' => $unitCost,
                            'quantity' => $quantityReceived,
                            'transaction_date' => date('Y-m-d'),
                            'created_by' => $_SESSION['user_id'] ?? 1
                        ]);
                        
                        if ($result) {
                            error_log("Accounting: Auto-tracked materials purchase transaction ID {$transactionId}");
                        }
                    } catch (Exception $e) {
                        error_log("Accounting auto-tracking error for materials purchase ID {$transactionId}: " . $e->getMessage());
                    }
                }
            } catch (Throwable $e) { /* non-fatal */ }

            echo json_encode([
                'success' => true, 
                'message' => 'Materials inventory updated successfully',
                'material' => $updatedMaterial
            ]);
            break;
            
        case 'adjust_used':
            $quantityUsed = intval($_POST['quantity_used'] ?? 0);
            
            if ($quantityUsed < 0) {
                throw new Exception('Quantity used cannot be negative');
            }
            
            $stmt = $pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_used = quantity_used + ?,
                    quantity_remaining = quantity_received - (quantity_used + ?),
                    total_value = (quantity_received - (quantity_used + ?)) * unit_cost,
                    last_updated = NOW()
                WHERE material_type = ?
            ");
            
            $stmt->execute([$quantityUsed, $quantityUsed, $quantityUsed, $materialType]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Material not found in inventory');
            }
            
            // Get updated material data
            $updatedStmt = $pdo->prepare("SELECT * FROM materials_inventory WHERE material_type = ?");
            $updatedStmt->execute([$materialType]);
            $updatedMaterial = $updatedStmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Materials usage updated successfully',
                'material' => $updatedMaterial
            ]);
            break;
            
        case 'reset':
            $confirm = $_POST['confirm'] ?? '';
            
            if ($confirm !== 'YES') {
                throw new Exception('Confirmation required. Please type YES to confirm reset.');
            }
            
            $stmt = $pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_used = 0,
                    quantity_remaining = quantity_received,
                    total_value = quantity_received * unit_cost,
                    last_updated = NOW()
                WHERE material_type = ?
            ");
            
            $stmt->execute([$materialType]);
            
            // Get updated material data
            $updatedStmt = $pdo->prepare("SELECT * FROM materials_inventory WHERE material_type = ?");
            $updatedStmt->execute([$materialType]);
            $updatedMaterial = $updatedStmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Materials usage reset successfully',
                'material' => $updatedMaterial
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>