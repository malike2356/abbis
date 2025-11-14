<?php
/**
 * Unified Inventory Service
 * Synchronizes inventory across ABBIS, POS, and CMS Shop
 * 
 * All three systems use catalog_items.stock_quantity as the source of truth
 * POS inventory (pos_inventory) aggregates per-store quantities
 * CMS shop reads directly from catalog_items.stock_quantity
 */
class UnifiedInventoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Get unified stock quantity for a catalog item
     * Returns the stock_quantity or inventory_quantity from catalog_items (source of truth)
     */
    public function getStockQuantity(int $catalogItemId): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(stock_quantity, inventory_quantity, 0) FROM catalog_items WHERE id = ?");
        $stmt->execute([$catalogItemId]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Update stock quantity in catalog_items (source of truth)
     * This automatically syncs to all systems
     */
    public function updateCatalogStock(int $catalogItemId, float $quantityDelta, ?string $reason = null): void
    {
        $transactionStarted = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transactionStarted = true;
        }
        
        try {
            // Update both stock_quantity and inventory_quantity to keep them in sync
            // Check which columns exist first
            $hasStockQty = false;
            $hasInvQty = false;
            try {
                $check = $this->pdo->query("SELECT stock_quantity FROM catalog_items LIMIT 1");
                $hasStockQty = true;
            } catch (PDOException $e) {}
            try {
                $check = $this->pdo->query("SELECT inventory_quantity FROM catalog_items LIMIT 1");
                $hasInvQty = true;
            } catch (PDOException $e) {}
            
            // Build update query based on available columns
            $updateFields = [];
            if ($hasStockQty) {
                $updateFields[] = "stock_quantity = GREATEST(0, COALESCE(stock_quantity, 0) + ?)";
            }
            if ($hasInvQty) {
                $updateFields[] = "inventory_quantity = GREATEST(0, COALESCE(inventory_quantity, 0) + ?)";
            }
            
            if (empty($updateFields)) {
                throw new RuntimeException("Neither stock_quantity nor inventory_quantity column exists in catalog_items");
            }
            
            $updateSql = "UPDATE catalog_items SET " . implode(", ", $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($updateSql);
            $params = array_fill(0, count($updateFields), $quantityDelta);
            $params[] = $catalogItemId;
            $stmt->execute($params);

            // Get the new stock quantity
            $newStock = $this->getStockQuantity($catalogItemId);

            // Sync to all POS stores that have this product
            $this->syncCatalogToPosStores($catalogItemId, $newStock, $reason);

            if ($transactionStarted) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Set absolute stock quantity in catalog_items
     * Updates both stock_quantity and inventory_quantity to keep them in sync
     */
    public function setCatalogStock(int $catalogItemId, float $quantity, ?string $reason = null): void
    {
        $this->pdo->beginTransaction();
        try {
            // Check which columns exist
            $hasStockQty = false;
            $hasInvQty = false;
            try {
                $this->pdo->query("SELECT stock_quantity FROM catalog_items LIMIT 1");
                $hasStockQty = true;
            } catch (PDOException $e) {}
            try {
                $this->pdo->query("SELECT inventory_quantity FROM catalog_items LIMIT 1");
                $hasInvQty = true;
            } catch (PDOException $e) {}
            
            // Build update query
            $updateFields = [];
            if ($hasStockQty) {
                $updateFields[] = "stock_quantity = ?";
            }
            if ($hasInvQty) {
                $updateFields[] = "inventory_quantity = ?";
            }
            
            if (empty($updateFields)) {
                throw new RuntimeException("Neither stock_quantity nor inventory_quantity column exists");
            }
            
            $updateSql = "UPDATE catalog_items SET " . implode(", ", $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($updateSql);
            $params = array_fill(0, count($updateFields), $quantity);
            $params[] = $catalogItemId;
            $stmt->execute($params);
            
            // Sync to POS stores
            $this->syncCatalogToPosStores($catalogItemId, $quantity, $reason);
            
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sync catalog_items stock to all POS stores
     * Distributes the total stock across stores based on their current inventory ratios
     */
    private function syncCatalogToPosStores(int $catalogItemId, float $totalStock, ?string $reason = null): void
    {
        // Get all POS products linked to this catalog item
        $stmt = $this->pdo->prepare("
            SELECT id FROM pos_products WHERE catalog_item_id = ?
        ");
        $stmt->execute([$catalogItemId]);
        $posProductIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($posProductIds)) {
            return; // No POS products linked
        }

        // Get all stores and their current inventory for this product
        $storeInventory = [];
        $totalPosStock = 0;

        foreach ($posProductIds as $posProductId) {
            $stmt = $this->pdo->prepare("
                SELECT store_id, quantity_on_hand 
                FROM pos_inventory 
                WHERE product_id = ?
            ");
            $stmt->execute([$posProductId]);
            $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($inventory as $inv) {
                $storeId = (int) $inv['store_id'];
                $qty = (float) $inv['quantity_on_hand'];
                if (!isset($storeInventory[$storeId])) {
                    $storeInventory[$storeId] = 0;
                }
                $storeInventory[$storeId] += $qty;
                $totalPosStock += $qty;
            }
        }

        // If no existing POS inventory, distribute evenly across all stores
        if ($totalPosStock <= 0) {
            $stmt = $this->pdo->query("SELECT id FROM pos_stores WHERE is_active = 1");
            $stores = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $storeCount = count($stores);
            
            if ($storeCount > 0) {
                $perStore = $totalStock / $storeCount;
                foreach ($stores as $storeId) {
                    foreach ($posProductIds as $posProductId) {
                        $this->setPosInventoryStock((int) $storeId, $posProductId, $perStore, $reason);
                    }
                }
            }
            return;
        }

        // Distribute total stock proportionally based on current store inventory
        foreach ($storeInventory as $storeId => $currentQty) {
            $ratio = $currentQty / $totalPosStock;
            $newQty = $totalStock * $ratio;
            
            foreach ($posProductIds as $posProductId) {
                // For multiple POS products linked to same catalog item, split evenly
                $productQty = $newQty / count($posProductIds);
                $this->setPosInventoryStock($storeId, $posProductId, $productQty, $reason);
            }
        }
    }

    /**
     * Set POS inventory stock for a specific store and product
     */
    private function setPosInventoryStock(int $storeId, int $posProductId, float $quantity, ?string $reason = null): void
    {
        // Ensure inventory row exists
        $stmt = $this->pdo->prepare("SELECT id FROM pos_inventory WHERE store_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$storeId, $posProductId]);
        $inventoryId = $stmt->fetchColumn();

        if (!$inventoryId) {
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_inventory (store_id, product_id, quantity_on_hand, updated_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$storeId, $posProductId, $quantity]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE pos_inventory 
                SET quantity_on_hand = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $inventoryId]);
        }
    }

    /**
     * Adjust POS inventory and sync to catalog_items
     * This is called when POS inventory changes (sales, adjustments, etc.)
     */
    public function adjustPosInventory(int $storeId, int $posProductId, float $quantityDelta, ?string $reason = null): void
    {
        $this->pdo->beginTransaction();
        try {
            // Get catalog_item_id from pos_product
            $stmt = $this->pdo->prepare("SELECT catalog_item_id FROM pos_products WHERE id = ?");
            $stmt->execute([$posProductId]);
            $catalogItemId = $stmt->fetchColumn();

            if (!$catalogItemId) {
                // No catalog link - just update POS inventory
                $this->adjustPosInventoryOnly($storeId, $posProductId, $quantityDelta);
                $this->pdo->commit();
                return;
            }

            // Update catalog_items (source of truth)
            $this->updateCatalogStock($catalogItemId, $quantityDelta, $reason);

            // Update POS inventory for this specific store
            $this->adjustPosInventoryOnly($storeId, $posProductId, $quantityDelta);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Adjust POS inventory only (without syncing to catalog)
     * Used internally when we've already updated catalog_items
     */
    private function adjustPosInventoryOnly(int $storeId, int $posProductId, float $quantityDelta): void
    {
        // Ensure inventory row exists
        $stmt = $this->pdo->prepare("SELECT id FROM pos_inventory WHERE store_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$storeId, $posProductId]);
        $inventoryId = $stmt->fetchColumn();

        if (!$inventoryId) {
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_inventory (store_id, product_id, quantity_on_hand, updated_at)
                VALUES (?, ?, GREATEST(0, ?), NOW())
            ");
            $stmt->execute([$storeId, $posProductId, $quantityDelta]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE pos_inventory 
                SET quantity_on_hand = GREATEST(0, quantity_on_hand + ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantityDelta, $inventoryId]);
        }
    }

    /**
     * Get total stock across all POS stores for a catalog item
     */
    public function getTotalPosStock(int $catalogItemId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(i.quantity_on_hand), 0)
            FROM pos_inventory i
            INNER JOIN pos_products p ON p.id = i.product_id
            WHERE p.catalog_item_id = ?
        ");
        $stmt->execute([$catalogItemId]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Sync all POS inventory to match catalog_items stock
     * Useful for initial sync or fixing discrepancies
     * Also links POS products to catalog_items by SKU if not already linked
     */
    public function syncAllInventory(): array
    {
        $results = ['synced' => 0, 'linked' => 0, 'errors' => []];
        
        // First, link POS products to catalog_items by SKU if not already linked
        $linkStmt = $this->pdo->query("
            UPDATE pos_products p
            INNER JOIN catalog_items ci ON ci.sku = p.sku AND ci.item_type = 'product'
            SET p.catalog_item_id = ci.id
            WHERE p.catalog_item_id IS NULL
        ");
        $results['linked'] = $linkStmt->rowCount();
        
        $stmt = $this->pdo->query("
            SELECT id, COALESCE(stock_quantity, inventory_quantity, 0) as stock_quantity 
            FROM catalog_items 
            WHERE item_type = 'product' AND is_active = 1
        ");
        $catalogItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($catalogItems as $item) {
            try {
                $this->syncCatalogToPosStores((int) $item['id'], (float) $item['stock_quantity'], 'Full inventory sync');
                $results['synced']++;
            } catch (Throwable $e) {
                $results['errors'][] = [
                    'catalog_item_id' => $item['id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}

