<?php
/**
 * Material Sync Service
 * Syncs materials inventory to catalog and POS inventory system-wide
 */
require_once __DIR__ . '/UnifiedInventoryService.php';
require_once __DIR__ . '/UnifiedCatalogSyncService.php';

class MaterialSyncService
{
    private $pdo;
    private $inventoryService;
    private $catalogSyncService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->inventoryService = new UnifiedInventoryService($pdo);
        $this->catalogSyncService = new UnifiedCatalogSyncService($pdo);
    }

    /**
     * Auto-create or update material mappings based on material names
     */
    public function autoMapMaterials(): array
    {
        $results = [];
        
        try {
            // Get all materials
            $materialsStmt = $this->pdo->query("
                SELECT material_type, material_name 
                FROM materials_inventory
            ");
            $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($materials as $material) {
                $materialType = $material['material_type'];
                $materialName = strtolower($material['material_name']);
                
                // Find matching catalog item
                $catalogItemId = $this->findCatalogItemByMaterialType($materialType, $materialName);
                
                if ($catalogItemId) {
                    // Find or create POS product
                    $posProductId = $this->findOrCreatePosProduct($catalogItemId);
                    
                    // Create or update mapping
                    $this->createOrUpdateMapping($materialType, $catalogItemId, $posProductId);
                    
                    $results[] = [
                        'material_type' => $materialType,
                        'catalog_item_id' => $catalogItemId,
                        'pos_product_id' => $posProductId,
                        'status' => 'mapped'
                    ];
                } else {
                    $results[] = [
                        'material_type' => $materialType,
                        'status' => 'no_match',
                        'message' => 'No matching catalog item found'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('[MaterialSyncService] Error auto-mapping: ' . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }

    /**
     * Find catalog item by material type
     */
    private function findCatalogItemByMaterialType(string $materialType, string $materialName): ?int
    {
        $searchPatterns = [
            'gravel' => ['%gravel%', '%Gravel%'],
            'screen_pipe' => ['%screen%pipe%', '%Screen%Pipe%', '%PVC%Screen%'],
            'plain_pipe' => ['%plain%pipe%', '%Plain%Pipe%', '%PVC%Plain%', '%PVC%Pipe%']
        ];
        
        $patterns = $searchPatterns[$materialType] ?? ['%' . $materialName . '%'];
        
        foreach ($patterns as $pattern) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM catalog_items 
                WHERE (LOWER(name) LIKE ? OR LOWER(sku) LIKE ?)
                AND item_type = 'product'
                ORDER BY 
                    CASE 
                        WHEN LOWER(name) LIKE ? THEN 1
                        WHEN LOWER(sku) LIKE ? THEN 2
                        ELSE 3
                    END
                LIMIT 1
            ");
            $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
            $catalogItemId = $stmt->fetchColumn();
            
            if ($catalogItemId) {
                return (int)$catalogItemId;
            }
        }
        
        return null;
    }

    /**
     * Find or create POS product from catalog item
     */
    private function findOrCreatePosProduct(int $catalogItemId): ?int
    {
        // Check if POS product already exists
        $stmt = $this->pdo->prepare("
            SELECT id FROM pos_products 
            WHERE catalog_item_id = ?
            LIMIT 1
        ");
        $stmt->execute([$catalogItemId]);
        $posProductId = $stmt->fetchColumn();
        
        if ($posProductId) {
            return (int)$posProductId;
        }
        
        // Create POS product from catalog
        require_once __DIR__ . '/PosRepository.php';
        $repo = new PosRepository($this->pdo);
        return $repo->upsertProductFromCatalog($catalogItemId);
    }

    /**
     * Create or update material mapping
     */
    private function createOrUpdateMapping(string $materialType, int $catalogItemId, ?int $posProductId): void
    {
        // First check if catalog_item_id column exists
        $columnCheck = $this->pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'catalog_item_id'");
        $hasCatalogItemId = $columnCheck->rowCount() > 0;
        
        // Check if updated_at column exists
        $updatedAtCheck = $this->pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'updated_at'");
        $hasUpdatedAt = $updatedAtCheck->rowCount() > 0;
        
        if ($hasCatalogItemId) {
            // Use the new schema with catalog_item_id
            if ($hasUpdatedAt) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pos_material_mappings 
                    (material_type, catalog_item_id, pos_product_id, auto_deduct_on_sale)
                    VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE
                        catalog_item_id = VALUES(catalog_item_id),
                        pos_product_id = VALUES(pos_product_id),
                        updated_at = NOW()
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pos_material_mappings 
                    (material_type, catalog_item_id, pos_product_id, auto_deduct_on_sale)
                    VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE
                        catalog_item_id = VALUES(catalog_item_id),
                        pos_product_id = VALUES(pos_product_id)
                ");
            }
            $stmt->execute([$materialType, $catalogItemId, $posProductId]);
        } else {
            // Fallback to old schema - only update pos_product_id
            // Try to insert/update without catalog_item_id
            if ($hasUpdatedAt) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pos_material_mappings 
                    (material_type, pos_product_id)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE
                        pos_product_id = VALUES(pos_product_id),
                        updated_at = NOW()
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pos_material_mappings 
                    (material_type, pos_product_id)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE
                        pos_product_id = VALUES(pos_product_id)
                ");
            }
            $stmt->execute([$materialType, $posProductId]);
            
            // Log that migration is needed
            error_log('[MaterialSyncService] Warning: pos_material_mappings table is missing catalog_item_id column. Please run migration 014_fix_material_mappings_schema.sql');
        }
    }

    /**
     * Sync materials inventory to catalog/POS
     * Updates catalog and POS inventory to match materials_inventory.quantity_remaining
     */
    public function syncMaterialsToCatalogAndPos(): array
    {
        $results = [];
        
        try {
            // Check if catalog_item_id column exists
            $columnCheck = $this->pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'catalog_item_id'");
            $hasCatalogItemId = $columnCheck->rowCount() > 0;
            
            // Build query based on available columns
            if ($hasCatalogItemId) {
                $mappingsStmt = $this->pdo->query("
                    SELECT 
                        mm.material_type,
                        mm.catalog_item_id,
                        mm.pos_product_id,
                        mi.quantity_remaining,
                        mi.material_name
                    FROM pos_material_mappings mm
                    INNER JOIN materials_inventory mi ON mm.material_type COLLATE utf8mb4_unicode_ci = mi.material_type COLLATE utf8mb4_unicode_ci
                    WHERE mm.catalog_item_id IS NOT NULL
                ");
            } else {
                // Fallback: try to get mappings without catalog_item_id filter
                $mappingsStmt = $this->pdo->query("
                    SELECT 
                        mm.material_type,
                        NULL as catalog_item_id,
                        mm.pos_product_id,
                        mi.quantity_remaining,
                        mi.material_name
                    FROM pos_material_mappings mm
                    INNER JOIN materials_inventory mi ON mm.material_type COLLATE utf8mb4_unicode_ci = mi.material_type COLLATE utf8mb4_unicode_ci
                ");
            }
            $mappings = $mappingsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($mappings as $mapping) {
                $materialType = $mapping['material_type'];
                $catalogItemId = !empty($mapping['catalog_item_id']) ? (int)$mapping['catalog_item_id'] : null;
                $materialsQuantity = floatval($mapping['quantity_remaining']);
                
                if (!$catalogItemId) {
                    $results[] = [
                        'material_type' => $materialType,
                        'status' => 'skipped',
                        'message' => 'No catalog_item_id mapping found. Please run auto-map first.'
                    ];
                    continue;
                }
                
                // Get current catalog inventory
                $catalogStmt = $this->pdo->prepare("
                    SELECT COALESCE(inventory_quantity, stock_quantity, 0) as current_stock
                    FROM catalog_items
                    WHERE id = ?
                ");
                $catalogStmt->execute([$catalogItemId]);
                $currentCatalogStock = floatval($catalogStmt->fetchColumn() ?? 0);
                
                // Calculate difference
                $quantityDelta = $materialsQuantity - $currentCatalogStock;
                
                if (abs($quantityDelta) > 0.01) { // Only sync if difference is significant
                    // Update catalog inventory
                    $this->inventoryService->updateCatalogStock(
                        $catalogItemId,
                        $quantityDelta,
                        "Auto-sync from materials inventory: {$materialType}"
                    );
                    
                    // Sync to POS
                    $this->catalogSyncService->syncInventoryToPos($catalogItemId, "Auto-sync from materials");
                    
                    $results[] = [
                        'material_type' => $materialType,
                        'catalog_item_id' => $catalogItemId,
                        'materials_quantity' => $materialsQuantity,
                        'catalog_quantity_before' => $currentCatalogStock,
                        'catalog_quantity_after' => $materialsQuantity,
                        'delta' => $quantityDelta,
                        'status' => 'synced'
                    ];
                } else {
                    $results[] = [
                        'material_type' => $materialType,
                        'catalog_item_id' => $catalogItemId,
                        'status' => 'already_synced',
                        'message' => 'Quantities already match'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('[MaterialSyncService] Error syncing materials: ' . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }

    /**
     * Sync a specific material type to catalog/POS
     */
    public function syncMaterialType(string $materialType): array
    {
        try {
            // Check if catalog_item_id column exists
            $columnCheck = $this->pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'catalog_item_id'");
            $hasCatalogItemId = $columnCheck->rowCount() > 0;
            
            // Build query based on available columns
            if ($hasCatalogItemId) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        mm.catalog_item_id,
                        mm.pos_product_id,
                        mi.quantity_remaining
                    FROM pos_material_mappings mm
                    INNER JOIN materials_inventory mi ON mm.material_type COLLATE utf8mb4_unicode_ci = mi.material_type COLLATE utf8mb4_unicode_ci
                    WHERE mm.material_type COLLATE utf8mb4_unicode_ci = ?
                    AND mm.catalog_item_id IS NOT NULL
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        NULL as catalog_item_id,
                        mm.pos_product_id,
                        mi.quantity_remaining
                    FROM pos_material_mappings mm
                    INNER JOIN materials_inventory mi ON mm.material_type COLLATE utf8mb4_unicode_ci = mi.material_type COLLATE utf8mb4_unicode_ci
                    WHERE mm.material_type COLLATE utf8mb4_unicode_ci = ?
                ");
            }
            $stmt->execute([$materialType]);
            $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mapping) {
                return [
                    'success' => false,
                    'error' => 'No mapping found for material type: ' . $materialType
                ];
            }
            
            $catalogItemId = !empty($mapping['catalog_item_id']) ? (int)$mapping['catalog_item_id'] : null;
            if (!$catalogItemId) {
                return [
                    'success' => false,
                    'error' => 'No catalog_item_id mapping found for material type: ' . $materialType . '. Please run auto-map first.'
                ];
            }
            
            $materialsQuantity = floatval($mapping['quantity_remaining']);
            
            // Get current catalog stock
            $catalogStmt = $this->pdo->prepare("
                SELECT COALESCE(inventory_quantity, stock_quantity, 0) as current_stock
                FROM catalog_items
                WHERE id = ?
            ");
            $catalogStmt->execute([$catalogItemId]);
            $currentCatalogStock = floatval($catalogStmt->fetchColumn() ?? 0);
            
            // Calculate delta
            $quantityDelta = $materialsQuantity - $currentCatalogStock;
            
            if (abs($quantityDelta) > 0.01) {
                // Update catalog
                $this->inventoryService->updateCatalogStock(
                    $catalogItemId,
                    $quantityDelta,
                    "Manual sync from materials: {$materialType}"
                );
                
                // Sync to POS
                $this->catalogSyncService->syncInventoryToPos($catalogItemId, "Manual sync from materials");
                
                return [
                    'success' => true,
                    'material_type' => $materialType,
                    'catalog_item_id' => $catalogItemId,
                    'quantity_delta' => $quantityDelta,
                    'materials_quantity' => $materialsQuantity,
                    'catalog_quantity_before' => $currentCatalogStock,
                    'catalog_quantity_after' => $materialsQuantity
                ];
            }
            
            return [
                'success' => true,
                'material_type' => $materialType,
                'message' => 'Already in sync'
            ];
        } catch (Exception $e) {
            error_log('[MaterialSyncService] Error syncing material type: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all material mappings with details
     */
    public function getMappings(): array
    {
        try {
            // First check if table exists and has the required columns
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'pos_material_mappings'");
            if ($tableCheck->rowCount() === 0) {
                return []; // Table doesn't exist yet
            }
            
            // Check if catalog_item_id column exists
            $columnCheck = $this->pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'catalog_item_id'");
            $hasCatalogItemId = $columnCheck->rowCount() > 0;
            
            // Check if pos_product_id column exists
            $columnCheck2 = $this->pdo->query("SHOW COLUMNS FROM pos_material_mappings LIKE 'pos_product_id'");
            $hasPosProductId = $columnCheck2->rowCount() > 0;
            
            if (!$hasCatalogItemId && !$hasPosProductId) {
                // Table exists but doesn't have mapping columns - return empty or basic info
                $stmt = $this->pdo->query("
                    SELECT 
                        mm.material_type,
                        mi.material_name,
                        mi.quantity_remaining as materials_quantity
                    FROM pos_material_mappings mm
                    INNER JOIN materials_inventory mi ON mm.material_type COLLATE utf8mb4_unicode_ci = mi.material_type COLLATE utf8mb4_unicode_ci
                    ORDER BY mm.material_type
                ");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Add null values for missing columns
                foreach ($results as &$row) {
                    $row['catalog_item_id'] = null;
                    $row['pos_product_id'] = null;
                    $row['catalog_name'] = null;
                    $row['catalog_sku'] = null;
                    $row['catalog_quantity'] = 0;
                    $row['pos_name'] = null;
                    $row['pos_sku'] = null;
                    $row['pos_quantity'] = 0;
                }
                return $results;
            }
            
            // Build query based on available columns
            $selectFields = [
                'mm.material_type',
                'mi.material_name',
                'mi.quantity_remaining as materials_quantity'
            ];
            
            $joins = [];
            
            if ($hasCatalogItemId) {
                $selectFields[] = 'mm.catalog_item_id';
                $selectFields[] = 'ci.name as catalog_name';
                $selectFields[] = 'ci.sku as catalog_sku';
                $selectFields[] = 'COALESCE(ci.inventory_quantity, ci.stock_quantity, 0) as catalog_quantity';
                $joins[] = "LEFT JOIN catalog_items ci ON mm.catalog_item_id = ci.id";
            } else {
                $selectFields[] = 'NULL as catalog_item_id';
                $selectFields[] = 'NULL as catalog_name';
                $selectFields[] = 'NULL as catalog_sku';
                $selectFields[] = '0 as catalog_quantity';
            }
            
            if ($hasPosProductId) {
                $selectFields[] = 'mm.pos_product_id';
                $selectFields[] = 'pp.name as pos_name';
                $selectFields[] = 'pp.sku as pos_sku';
                $selectFields[] = 'COALESCE(pi.quantity_on_hand, 0) as pos_quantity';
                $joins[] = "LEFT JOIN pos_products pp ON mm.pos_product_id = pp.id";
                $joins[] = "LEFT JOIN pos_inventory pi ON pp.id = pi.product_id AND pi.store_id = (
                    SELECT id FROM pos_stores WHERE is_active = 1 LIMIT 1
                )";
            } else {
                $selectFields[] = 'NULL as pos_product_id';
                $selectFields[] = 'NULL as pos_name';
                $selectFields[] = 'NULL as pos_sku';
                $selectFields[] = '0 as pos_quantity';
            }
            
            $sql = "
                SELECT " . implode(', ', $selectFields) . "
                FROM pos_material_mappings mm
                INNER JOIN materials_inventory mi ON mm.material_type COLLATE utf8mb4_unicode_ci = mi.material_type COLLATE utf8mb4_unicode_ci
                " . implode(' ', $joins) . "
                ORDER BY mm.material_type
            ";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[MaterialSyncService] Error getting mappings: ' . $e->getMessage());
            return []; // Return empty array on error
        }
    }
}

