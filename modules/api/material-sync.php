<?php
/**
 * Material Sync API
 * Handles auto-mapping and syncing of materials to catalog/POS
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/MaterialSyncService.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

header('Content-Type: application/json');

$pdo = getDBConnection();
$syncService = new MaterialSyncService($pdo);
$action = $_GET['action'] ?? '';

// Handle JSON request body
$input = file_get_contents('php://input');
$data = [];
if (!empty($input)) {
    $data = json_decode($input, true) ?? [];
}

// Merge POST and JSON data (for compatibility)
$requestData = array_merge($_POST, $data);

try {
    $csrfToken = $requestData['csrf_token'] ?? '';
    if (!CSRF::validateToken($csrfToken)) {
        throw new Exception('Invalid security token');
    }
    
    switch ($action) {
        case 'auto_map':
            try {
                // Check if table has required column before proceeding
                $columnCheck = $pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'catalog_item_id'");
                if ($columnCheck->rowCount() === 0) {
                    // Column doesn't exist - try to add it step by step
                    try {
                        // Step 1: Add catalog_item_id column
                        try {
                            $pdo->exec("ALTER TABLE pos_material_mappings ADD COLUMN catalog_item_id INT DEFAULT NULL COMMENT 'Link to catalog_items' AFTER material_type");
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                                throw $e;
                            }
                        }
                        
                        // Step 2: Add auto_deduct_on_sale column
                        try {
                            $checkAutoDeduct = $pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'auto_deduct_on_sale'");
                            if ($checkAutoDeduct->rowCount() === 0) {
                                // Find position after pos_product_id
                                $posProductIdCheck = $pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'pos_product_id'");
                                if ($posProductIdCheck->rowCount() > 0) {
                                    $pdo->exec("ALTER TABLE pos_material_mappings ADD COLUMN auto_deduct_on_sale TINYINT(1) DEFAULT 1 COMMENT 'Auto-deduct when company sale is made' AFTER pos_product_id");
                                } else {
                                    $pdo->exec("ALTER TABLE pos_material_mappings ADD COLUMN auto_deduct_on_sale TINYINT(1) DEFAULT 1 COMMENT 'Auto-deduct when company sale is made'");
                                }
                            }
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                                error_log('[Material Sync] Warning: Could not add auto_deduct_on_sale: ' . $e->getMessage());
                            }
                        }
                        
                        // Step 3: Drop unique constraint before modifying material_type (if it exists)
                        try {
                            $constraintCheck = $pdo->query("SHOW INDEX FROM pos_material_mappings WHERE Key_name = 'material_product_unique'");
                            if ($constraintCheck->rowCount() > 0) {
                                $pdo->exec("ALTER TABLE pos_material_mappings DROP INDEX material_product_unique");
                            }
                        } catch (PDOException $e) {
                            // Ignore if constraint doesn't exist
                        }
                        
                        // Step 4: Modify material_type to VARCHAR (if it's ENUM)
                        try {
                            $typeCheck = $pdo->query("SHOW COLUMNS FROM pos_material_mappings WHERE Field = 'material_type'");
                            $typeInfo = $typeCheck->fetch(PDO::FETCH_ASSOC);
                            if ($typeInfo && strpos(strtoupper($typeInfo['Type']), 'ENUM') !== false) {
                                $pdo->exec("ALTER TABLE pos_material_mappings MODIFY COLUMN material_type VARCHAR(100) NOT NULL COMMENT 'screen_pipe, plain_pipe, gravel'");
                            }
                        } catch (PDOException $e) {
                            error_log('[Material Sync] Warning: Could not modify material_type: ' . $e->getMessage());
                            // If modification fails, we can still continue - the column might already be VARCHAR
                        }
                        
                        // Step 5: Make pos_product_id nullable if needed
                        try {
                            $nullableCheck = $pdo->query("SHOW COLUMNS FROM pos_material_mappings WHERE Field = 'pos_product_id'");
                            $nullableInfo = $nullableCheck->fetch(PDO::FETCH_ASSOC);
                            if ($nullableInfo && $nullableInfo['Null'] === 'NO') {
                                $pdo->exec("ALTER TABLE pos_material_mappings MODIFY COLUMN pos_product_id INT UNSIGNED DEFAULT NULL");
                            }
                        } catch (PDOException $e) {
                            error_log('[Material Sync] Warning: Could not modify pos_product_id: ' . $e->getMessage());
                        }
                        
                        // Step 6: Add index for catalog_item_id
                        try {
                            $indexCheck = $pdo->query("SHOW INDEX FROM pos_material_mappings WHERE Key_name = 'idx_catalog_item'");
                            if ($indexCheck->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE pos_material_mappings ADD INDEX idx_catalog_item (catalog_item_id)");
                            }
                        } catch (PDOException $e) {
                            error_log('[Material Sync] Warning: Could not add index: ' . $e->getMessage());
                        }
                        
                        // Step 7: Add unique constraint on material_type (if it doesn't exist)
                        try {
                            $constraintCheck = $pdo->query("SHOW INDEX FROM pos_material_mappings WHERE Key_name = 'material_type' AND Non_unique = 0");
                            if ($constraintCheck->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE pos_material_mappings ADD UNIQUE KEY material_type (material_type)");
                            }
                        } catch (PDOException $e) {
                            error_log('[Material Sync] Warning: Could not add unique constraint: ' . $e->getMessage());
                        }
                    } catch (PDOException $e) {
                        throw new Exception('Database schema update failed. Please run migration 014_fix_material_mappings_schema.sql manually: ' . $e->getMessage());
                    }
                }
                
                $results = $syncService->autoMapMaterials();
                $mappedCount = count(array_filter($results, function($r) {
                    return ($r['status'] ?? '') === 'mapped';
                }));
                
                echo json_encode([
                    'success' => true,
                    'mapped_count' => $mappedCount,
                    'results' => $results
                ]);
            } catch (Exception $e) {
                error_log('[Material Sync API] Auto-map error: ' . $e->getMessage());
                throw $e;
            }
            break;
            
        case 'sync_all':
            $results = $syncService->syncMaterialsToCatalogAndPos();
            $syncedCount = count(array_filter($results, function($r) {
                return ($r['status'] ?? '') === 'synced';
            }));
            
            echo json_encode([
                'success' => true,
                'synced_count' => $syncedCount,
                'results' => $results
            ]);
            break;
            
        case 'sync_type':
            $materialType = $requestData['material_type'] ?? '';
            if (empty($materialType)) {
                throw new Exception('Material type is required');
            }
            
            $result = $syncService->syncMaterialType($materialType);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

