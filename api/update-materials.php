<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

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