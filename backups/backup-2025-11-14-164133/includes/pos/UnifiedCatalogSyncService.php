<?php
/**
 * Unified Catalog Sync Service
 * Automatically synchronizes product and inventory data across ABBIS, CMS, and POS
 * 
 * When any system updates a product:
 * - Catalog items are updated (source of truth)
 * - POS products are synced
 * - CMS shop reflects changes immediately
 * - Inventory quantities stay in sync
 */
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/UnifiedInventoryService.php';

class UnifiedCatalogSyncService
{
    private PDO $pdo;
    private UnifiedInventoryService $inventoryService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->inventoryService = new UnifiedInventoryService($this->pdo);
    }

    /**
     * Sync catalog item changes to POS products
     * Called when catalog_items is updated
     */
    public function syncCatalogToPos(int $catalogItemId): void
    {
        // Get catalog item
        $stmt = $this->pdo->prepare("
            SELECT * FROM catalog_items WHERE id = ?
        ");
        $stmt->execute([$catalogItemId]);
        $catalogItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$catalogItem) {
            return;
        }

        // First, ensure POS product exists and is linked (create if needed)
        require_once __DIR__ . '/PosRepository.php';
        $repo = new PosRepository($this->pdo);
        $posProductId = $repo->upsertProductFromCatalog($catalogItemId);

        if (!$posProductId) {
            // Product couldn't be created/linked, skip update
            return;
        }

        // Get all POS products linked to this catalog item (in case there are multiple)
        $stmt = $this->pdo->prepare("
            SELECT id FROM pos_products WHERE catalog_item_id = ?
        ");
        $stmt->execute([$catalogItemId]);
        $posProductIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Update all linked POS products
        foreach ($posProductIds as $posProductId) {
            $this->updatePosProductFromCatalog($posProductId, $catalogItem);
        }
    }

    /**
     * Update POS product from catalog item data
     */
    private function updatePosProductFromCatalog(int $posProductId, array $catalogItem): void
    {
        $updateFields = [];
        $updateValues = [];

        // Sync name (always update)
        $updateFields[] = 'name = ?';
        $updateValues[] = $catalogItem['name'] ?? '';

        // Sync SKU
        if (isset($catalogItem['sku']) && !empty($catalogItem['sku'])) {
            $updateFields[] = 'sku = ?';
            $updateValues[] = $catalogItem['sku'];
        }

        // Sync price (sell_price -> unit_price)
        if (isset($catalogItem['sell_price'])) {
            $updateFields[] = 'unit_price = ?';
            $updateValues[] = $catalogItem['sell_price'];
        }

        // Sync cost price
        if (isset($catalogItem['cost_price'])) {
            $updateFields[] = 'cost_price = ?';
            $updateValues[] = $catalogItem['cost_price'];
        }

        // Sync active status
        if (isset($catalogItem['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $updateValues[] = (int)$catalogItem['is_active'];
        }

        // Sync sellable status (is_sellable -> expose_to_shop)
        if (isset($catalogItem['is_sellable'])) {
            try {
                $this->pdo->query("SELECT expose_to_shop FROM pos_products LIMIT 1");
                $updateFields[] = 'expose_to_shop = ?';
                $updateValues[] = (int)$catalogItem['is_sellable'];
            } catch (PDOException $e) {
                // Column doesn't exist, skip
            }
        }

        // Sync description if available
        if (isset($catalogItem['description'])) {
            try {
                $this->pdo->query("SELECT description FROM pos_products LIMIT 1");
                $updateFields[] = 'description = ?';
                $updateValues[] = $catalogItem['description'];
            } catch (PDOException $e) {
                // Description column doesn't exist, skip
            }
        }

        // Sync notes to description if description column doesn't exist
        if (isset($catalogItem['notes']) && !isset($catalogItem['description'])) {
            try {
                $this->pdo->query("SELECT description FROM pos_products LIMIT 1");
                $updateFields[] = 'description = ?';
                $updateValues[] = $catalogItem['notes'];
            } catch (PDOException $e) {
                // Description column doesn't exist, skip
            }
        }

        // Always update timestamp
        $updateFields[] = 'updated_at = NOW()';
        $updateValues[] = $posProductId;

        if (!empty($updateFields)) {
            $sql = "UPDATE pos_products SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($updateValues);
        }
    }

    /**
     * Sync POS product changes to catalog
     * Called when POS product is updated
     */
    public function syncPosToCatalog(int $posProductId): void
    {
        // Get POS product
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name
            FROM pos_products p
            LEFT JOIN pos_categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$posProductId]);
        $posProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$posProduct) {
            return;
        }

        $catalogItemId = $posProduct['catalog_item_id'] ?? null;

        if ($catalogItemId) {
            // Update existing catalog item
            $this->updateCatalogFromPos($catalogItemId, $posProduct);
        } else {
            // Try to find by SKU or create new
            $catalogItemId = $this->findOrCreateCatalogItem($posProduct);
            if ($catalogItemId) {
                // Link POS product to catalog
                $stmt = $this->pdo->prepare("UPDATE pos_products SET catalog_item_id = ? WHERE id = ?");
                $stmt->execute([$catalogItemId, $posProductId]);
            }
        }
    }

    /**
     * Update catalog item from POS product data
     */
    private function updateCatalogFromPos(int $catalogItemId, array $posProduct): void
    {
        $updateFields = [];
        $updateValues = [];

        // Sync name
        if (isset($posProduct['name'])) {
            $updateFields[] = 'name = ?';
            $updateValues[] = $posProduct['name'];
        }

        // Sync SKU
        if (isset($posProduct['sku']) && !empty($posProduct['sku'])) {
            $updateFields[] = 'sku = ?';
            $updateValues[] = $posProduct['sku'];
        }

        // Sync price (unit_price -> sell_price)
        if (isset($posProduct['unit_price'])) {
            $updateFields[] = 'sell_price = ?';
            $updateValues[] = $posProduct['unit_price'];
        }

        // Sync cost price
        if (isset($posProduct['cost_price'])) {
            $updateFields[] = 'cost_price = ?';
            $updateValues[] = $posProduct['cost_price'];
        }

        // Sync active status
        if (isset($posProduct['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $updateValues[] = (int)$posProduct['is_active'];
            $updateFields[] = 'is_sellable = ?';
            $updateValues[] = (int)$posProduct['is_active'];
        }

        // Sync description if available
        if (isset($posProduct['description'])) {
            try {
                $this->pdo->query("SELECT description FROM catalog_items LIMIT 1");
                $updateFields[] = 'description = ?';
                $updateValues[] = $posProduct['description'];
            } catch (PDOException $e) {
                // Description column doesn't exist, skip
            }
        }

        // Sync category if available
        if (isset($posProduct['category_name']) && !empty($posProduct['category_name'])) {
            $categoryId = $this->ensureCatalogCategory($posProduct['category_name']);
            if ($categoryId) {
                $updateFields[] = 'category_id = ?';
                $updateValues[] = $categoryId;
            }
        }

        if (!empty($updateFields)) {
            $updateFields[] = 'updated_at = NOW()';
            $updateValues[] = $catalogItemId;

            $sql = "UPDATE catalog_items SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($updateValues);
        }
    }

    /**
     * Find catalog item by SKU or create new one
     */
    private function findOrCreateCatalogItem(array $posProduct): ?int
    {
        if (empty($posProduct['sku'])) {
            return null;
        }

        // Try to find by SKU
        $stmt = $this->pdo->prepare("SELECT id FROM catalog_items WHERE sku = ? AND item_type = 'product' LIMIT 1");
        $stmt->execute([$posProduct['sku']]);
        $catalogItemId = $stmt->fetchColumn();

        if ($catalogItemId) {
            return (int)$catalogItemId;
        }

        // Create new catalog item
        $categoryId = null;
        if (isset($posProduct['category_name']) && !empty($posProduct['category_name'])) {
            $categoryId = $this->ensureCatalogCategory($posProduct['category_name']);
        }

        $insertFields = ['name', 'sku', 'item_type', 'cost_price', 'sell_price', 'is_active', 'is_sellable', 'is_purchasable'];
        $insertValues = [
            $posProduct['name'],
            $posProduct['sku'],
            'product',
            $posProduct['cost_price'] ?? 0,
            $posProduct['unit_price'] ?? 0,
            (int)($posProduct['is_active'] ?? 1),
            1,
            1
        ];

        if ($categoryId) {
            $insertFields[] = 'category_id';
            $insertValues[] = $categoryId;
        }

        if (isset($posProduct['description'])) {
            try {
                $this->pdo->query("SELECT description FROM catalog_items LIMIT 1");
                $insertFields[] = 'description';
                $insertValues[] = $posProduct['description'];
            } catch (PDOException $e) {}
        }

        $placeholders = str_repeat('?,', count($insertFields) - 1) . '?';
        $fieldsList = implode(', ', $insertFields);
        $sql = "INSERT INTO catalog_items ($fieldsList) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($insertValues);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Ensure catalog category exists
     */
    private function ensureCatalogCategory(string $categoryName): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM catalog_categories WHERE name = ? LIMIT 1");
        $stmt->execute([$categoryName]);
        $categoryId = $stmt->fetchColumn();

        if ($categoryId) {
            return (int)$categoryId;
        }

        // Create category
        $slug = $this->slugify($categoryName);
        $stmt = $this->pdo->prepare("
            INSERT INTO catalog_categories (name, slug, description)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $categoryName,
            $slug,
            'Auto-synced category'
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Sync inventory from catalog to POS
     */
    public function syncInventoryToPos(int $catalogItemId, ?string $reason = null): void
    {
        $stock = $this->inventoryService->getStockQuantity($catalogItemId);
        $this->inventoryService->setCatalogStock($catalogItemId, $stock, $reason);
    }

    /**
     * Sync inventory from POS to catalog
     */
    public function syncInventoryToCatalog(int $posProductId, float $quantityDelta, ?string $reason = null): void
    {
        $this->inventoryService->adjustPosInventory(
            $this->getDefaultStoreId(),
            $posProductId,
            $quantityDelta,
            $reason
        );
    }

    /**
     * Get default store ID (first active store)
     */
    private function getDefaultStoreId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM pos_stores WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $storeId = $stmt->fetchColumn();
        return $storeId ? (int)$storeId : 1;
    }

    /**
     * Sync all products (full sync)
     */
    public function syncAll(): array
    {
        $results = [
            'catalog_to_pos' => 0,
            'pos_to_catalog' => 0,
            'inventory_synced' => 0,
            'errors' => []
        ];

        // Sync all catalog items to POS
        $stmt = $this->pdo->query("SELECT id FROM catalog_items WHERE item_type = 'product'");
        $catalogIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($catalogIds as $catalogId) {
            try {
                $this->syncCatalogToPos((int)$catalogId);
                $results['catalog_to_pos']++;
            } catch (Throwable $e) {
                $results['errors'][] = ['type' => 'catalog_to_pos', 'id' => $catalogId, 'error' => $e->getMessage()];
            }
        }

        // Sync all POS products to catalog
        $stmt = $this->pdo->query("SELECT id FROM pos_products");
        $posIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($posIds as $posId) {
            try {
                $this->syncPosToCatalog((int)$posId);
                $results['pos_to_catalog']++;
            } catch (Throwable $e) {
                $results['errors'][] = ['type' => 'pos_to_catalog', 'id' => $posId, 'error' => $e->getMessage()];
            }
        }

        // Sync all inventory
        try {
            $inventoryResults = $this->inventoryService->syncAllInventory();
            $results['inventory_synced'] = $inventoryResults['synced'];
        } catch (Throwable $e) {
            $results['errors'][] = ['type' => 'inventory_sync', 'error' => $e->getMessage()];
        }

        return $results;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: strtolower(bin2hex(random_bytes(4)));
    }
}

