<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/PosCatalogSync.php';
require_once __DIR__ . '/UnifiedInventoryService.php';
require_once __DIR__ . '/UnifiedCatalogSyncService.php';

class PosRepository
{
    private PDO $pdo;
    private UnifiedInventoryService $inventoryService;
    private static bool $schemaEnsured = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->inventoryService = new UnifiedInventoryService($this->pdo);
        $this->ensureSchema();
        $this->ensureAccountingQueueSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            $this->pdo->query('SELECT 1 FROM pos_sales LIMIT 1');
            self::$schemaEnsured = true;
            return;
        } catch (PDOException $e) {
            // Table missing or schema not yet created – continue to run migrations.
        }

        $migrationDir = dirname(__DIR__, 2) . '/database/migrations/pos';
        if (is_dir($migrationDir)) {
            $files = glob($migrationDir . '/*.sql');
            sort($files);
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    continue;
                }
                $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));
                foreach ($statements as $statement) {
                    if ($statement === '') {
                        continue;
                    }
                    try {
                        $this->pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore duplicate-table errors so migrations are idempotent.
                        if ($e->getCode() !== '42S01') { // table already exists
                            throw $e;
                        }
                    }
                }
            }
        }

        self::$schemaEnsured = true;
    }

    /* ---------- Catalog ---------- */

    public function listProducts(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT p.*, 
                c.name AS category_name,
                COALESCE(ci.inventory_quantity, ci.stock_quantity, 0) AS stock_quantity,
                COALESCE(SUM(i.quantity_on_hand), 0) AS pos_inventory_total
                FROM pos_products p
                LEFT JOIN pos_categories c ON p.category_id = c.id
                LEFT JOIN catalog_items ci ON p.catalog_item_id = ci.id
                LEFT JOIN pos_inventory i ON i.product_id = p.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE :search_name OR p.sku LIKE :search_sku)";
            $params[':search_name'] = '%' . $filters['search'] . '%';
            $params[':search_sku'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND p.is_active = :is_active";
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['expose_to_shop'])) {
            $sql .= " AND p.expose_to_shop = :expose_to_shop";
            $params[':expose_to_shop'] = (int) $filters['expose_to_shop'];
        }

        $sql .= " GROUP BY p.id ORDER BY p.name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Use catalog_items.inventory_quantity as the primary inventory source (fallback to stock_quantity)
        foreach ($products as &$product) {
            $product['inventory'] = (float) ($product['stock_quantity'] ?? 0);
            $product['inventory_available'] = $product['inventory'] > 0;
        }
        
        return $products;
    }

    public function countProducts(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM pos_products p WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE :search_name OR p.sku LIKE :search_sku)";
            $params[':search_name'] = '%' . $filters['search'] . '%';
            $params[':search_sku'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND p.is_active = :is_active";
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['expose_to_shop'])) {
            $sql .= " AND p.expose_to_shop = :expose_to_shop";
            $params[':expose_to_shop'] = (int) $filters['expose_to_shop'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function createProduct(array $data): int
    {
        $sql = "INSERT INTO pos_products
                (sku, name, description, category_id, barcode, unit_price, cost_price, tax_rate, track_inventory, is_active, expose_to_shop)
                VALUES
                (:sku, :name, :description, :category_id, :barcode, :unit_price, :cost_price, :tax_rate, :track_inventory, :is_active, :expose_to_shop)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sku' => $data['sku'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':category_id' => $data['category_id'] ?? null,
            ':barcode' => $data['barcode'] ?? null,
            ':unit_price' => $data['unit_price'],
            ':cost_price' => $data['cost_price'] ?? null,
            ':tax_rate' => $data['tax_rate'] ?? null,
            ':track_inventory' => !empty($data['track_inventory']) ? 1 : 0,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':expose_to_shop' => !empty($data['expose_to_shop']) ? 1 : 0,
        ]);

        $productId = (int) $this->pdo->lastInsertId();

        if (!empty($data['expose_to_shop'])) {
            $this->syncCatalogSafely($productId);
        }

        return $productId;
    }

    public function updateProduct(int $productId, array $data): void
    {
        $sql = "UPDATE pos_products SET
                    sku = :sku,
                    name = :name,
                    description = :description,
                    category_id = :category_id,
                    barcode = :barcode,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    tax_rate = :tax_rate,
                    track_inventory = :track_inventory,
                    is_active = :is_active,
                    expose_to_shop = :expose_to_shop,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sku' => $data['sku'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':category_id' => $data['category_id'] ?? null,
            ':barcode' => $data['barcode'] ?? null,
            ':unit_price' => $data['unit_price'],
            ':cost_price' => $data['cost_price'] ?? null,
            ':tax_rate' => $data['tax_rate'] ?? null,
            ':track_inventory' => !empty($data['track_inventory']) ? 1 : 0,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':expose_to_shop' => !empty($data['expose_to_shop']) ? 1 : 0,
            ':id' => $productId,
        ]);

        // Always sync to catalog when product is updated
        try {
            $syncService = new UnifiedCatalogSyncService($this->pdo);
            $syncService->syncPosToCatalog($productId);
        } catch (Throwable $e) {
            error_log('[POS Product Update] Catalog sync failed: ' . $e->getMessage());
            // Don't fail the update, just log the error
        }
    }

    public function upsertProductFromCatalog(int $catalogItemId): ?int
    {
        $itemStmt = $this->pdo->prepare("
            SELECT ci.*, cc.name AS category_name
            FROM catalog_items ci
            LEFT JOIN catalog_categories cc ON cc.id = ci.category_id
            WHERE ci.id = :id
        ");
        $itemStmt->execute([':id' => $catalogItemId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return null;
        }

        if (($item['item_type'] ?? 'product') !== 'product') {
            $this->disableProductForCatalogItem($catalogItemId);
            return null;
        }

        $categoryId = null;
        if (!empty($item['category_name'])) {
            $categoryId = $this->ensurePosCategory($item['category_name']);
        }

        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'CAT-' . str_pad((string) $catalogItemId, 6, '0', STR_PAD_LEFT);
        }

        $productId = $this->findProductByCatalogLink($catalogItemId, $sku);

        $params = [
            ':sku' => $sku,
            ':name' => $item['name'],
            ':description' => $item['notes'] ?? null,
            ':category_id' => $categoryId,
            ':unit_price' => $item['sell_price'],
            ':cost_price' => $item['cost_price'],
            ':tax_rate' => !empty($item['taxable']) ? 1 : null,
            ':track_inventory' => 1,
            ':is_active' => (int) ($item['is_active'] ?? 0),
            ':expose_to_shop' => (int) ($item['is_sellable'] ?? 1),
            ':catalog_item_id' => $catalogItemId,
        ];

        if ($productId) {
            $params[':id'] = $productId;
            $update = $this->pdo->prepare("
                UPDATE pos_products
                SET sku = :sku,
                    name = :name,
                    description = :description,
                    category_id = :category_id,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    tax_rate = :tax_rate,
                    track_inventory = :track_inventory,
                    is_active = :is_active,
                    expose_to_shop = :expose_to_shop,
                    catalog_item_id = :catalog_item_id,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute($params);
        } else {
            $insert = $this->pdo->prepare("
                INSERT INTO pos_products
                    (sku, name, description, category_id, barcode, unit_price, cost_price, tax_rate, track_inventory, is_active, expose_to_shop, catalog_item_id)
                VALUES
                    (:sku, :name, :description, :category_id, NULL, :unit_price, :cost_price, :tax_rate, :track_inventory, :is_active, :expose_to_shop, :catalog_item_id)
            ");
            $insert->execute($params);
            $productId = (int) $this->pdo->lastInsertId();
        }

        if ($productId) {
            $this->syncCatalogSafely($productId);
        }

        return $productId;
    }

    public function disableProductForCatalogItem(int $catalogItemId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM pos_products WHERE catalog_item_id = :catalog_item_id LIMIT 1");
        $stmt->execute([':catalog_item_id' => $catalogItemId]);
        $productId = $stmt->fetchColumn();

        if ($productId) {
            $this->pdo->prepare("
                UPDATE pos_products
                SET is_active = 0,
                    expose_to_shop = 0,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([':id' => $productId]);
        }
    }

    /* ---------- Inventory ---------- */

    public function adjustStock(array $payload): array
    {
        $manageTx = !$this->pdo->inTransaction();
        if ($manageTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $storeId = (int) $payload['store_id'];
            $productId = (int) $payload['product_id'];
            $quantity = (float) $payload['quantity_delta'];
            $transactionType = $payload['transaction_type'];
            $unitCost = isset($payload['unit_cost']) ? (float) $payload['unit_cost'] : null;
            $remarks = $payload['remarks'] ?? null;

            // Use unified inventory service to sync across all systems
            $reason = $remarks ?: "POS adjustment: {$transactionType}";
            $this->inventoryService->adjustPosInventory($storeId, $productId, $quantity, $reason);

            // Ensure inventory row exists for ledger tracking
            $inventoryId = $this->ensureInventoryRow($storeId, $productId);

            // Update average cost if provided
            if ($unitCost !== null && $quantity > 0) {
                $updateCostSql = "UPDATE pos_inventory
                                  SET average_cost = CASE
                                    WHEN quantity_on_hand > 0
                                        THEN COALESCE((quantity_on_hand * COALESCE(average_cost, :unit_cost) + :delta * :unit_cost) / NULLIF(quantity_on_hand + :delta, 0), :unit_cost)
                                    ELSE :unit_cost
                                  END,
                                  last_restocked_at = CASE WHEN :delta > 0 THEN NOW() ELSE last_restocked_at END
                                  WHERE id = :inventory_id";
                $stmtUpdate = $this->pdo->prepare($updateCostSql);
                $stmtUpdate->execute([
                    ':delta' => $quantity,
                    ':unit_cost' => $unitCost,
                    ':inventory_id' => $inventoryId,
                ]);
            }

            // Insert ledger entry
            $stmtLedger = $this->pdo->prepare("
                INSERT INTO pos_stock_ledger
                    (store_id, product_id, transaction_type, reference_type, reference_id, quantity_delta, unit_cost, remarks, performed_by, performed_at)
                VALUES
                    (:store_id, :product_id, :transaction_type, :reference_type, :reference_id, :quantity_delta, :unit_cost, :remarks, :performed_by, NOW())
            ");
            $stmtLedger->execute([
                ':store_id' => $storeId,
                ':product_id' => $productId,
                ':transaction_type' => $transactionType,
                ':reference_type' => $payload['reference_type'] ?? null,
                ':reference_id' => $payload['reference_id'] ?? null,
                ':quantity_delta' => $quantity,
                ':unit_cost' => $unitCost,
                ':remarks' => $remarks,
                ':performed_by' => $payload['performed_by'] ?? null,
            ]);

            if ($manageTx) {
                $this->pdo->commit();
            }

            return [
                'inventory_id' => $inventoryId,
                'ledger_id' => (int) $this->pdo->lastInsertId(),
            ];
        } catch (Throwable $e) {
            if ($manageTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function ensureInventoryRow(int $storeId, int $productId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM pos_inventory WHERE store_id = :store_id AND product_id = :product_id LIMIT 1");
        $stmt->execute([
            ':store_id' => $storeId,
            ':product_id' => $productId,
        ]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            return (int) $existing;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO pos_inventory (store_id, product_id, quantity_on_hand, average_cost)
            VALUES (:store_id, :product_id, 0, NULL)
        ");
        $insert->execute([
            ':store_id' => $storeId,
            ':product_id' => $productId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /* ---------- Sales ---------- */

    public function getStores(bool $activeOnly = true): array
    {
        $sql = "SELECT id, store_code, store_name, location, contact_phone, contact_email, is_primary, is_active, created_at
                FROM pos_stores";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_primary DESC, store_name ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Alias for getStores() for backward compatibility
     */
    public function listStores(bool $activeOnly = true): array
    {
        return $this->getStores($activeOnly);
    }

    private function ensureAccountingQueueSchema(): void
    {
        try {
            $columns = $this->pdo->query("SHOW COLUMNS FROM pos_accounting_queue LIKE 'reference_type'");
            if ($columns->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE pos_accounting_queue ADD COLUMN reference_type VARCHAR(60) NULL AFTER sale_id");
                $this->pdo->exec("ALTER TABLE pos_accounting_queue ADD COLUMN reference_id VARCHAR(100) NULL AFTER reference_type");
                $this->pdo->exec("ALTER TABLE pos_accounting_queue ADD INDEX idx_reference (reference_type, reference_id)");
            }
        } catch (PDOException $e) {
            // Table might not exist yet; ignore and let migrations handle creation.
        }
    }

    public function fetchPendingAccountingQueue(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM pos_accounting_queue
            WHERE status IN ('pending','error')
            ORDER BY created_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSale(array $salePayload): array
    {
        // Validate critical fields before starting transaction
        if (empty($salePayload['store_id']) || $salePayload['store_id'] <= 0) {
            throw new InvalidArgumentException('Invalid store_id');
        }
        if (empty($salePayload['cashier_id']) || $salePayload['cashier_id'] <= 0) {
            throw new InvalidArgumentException('Invalid cashier_id');
        }
        if (empty($salePayload['items']) || !is_array($salePayload['items']) || count($salePayload['items']) === 0) {
            throw new InvalidArgumentException('Sale must contain at least one item');
        }
        if (empty($salePayload['payments']) || !is_array($salePayload['payments']) || count($salePayload['payments']) === 0) {
            throw new InvalidArgumentException('Sale must have at least one payment');
        }
        
        $this->pdo->beginTransaction();

        try {
            $saleNumber = $this->generateSaleNumber();
            // Check if receipt_number column exists
            $receiptNumberColumnExists = false;
            try {
                $checkStmt = $this->pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'receipt_number'");
                $receiptNumberColumnExists = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                // Column doesn't exist
            }
            
            // Generate receipt number only if column exists
            $receiptNumber = null;
            if ($receiptNumberColumnExists) {
                $receiptNumber = $this->generateSaleReceiptNumber();
            }
            
            // Check if entity_type and entity_id columns exist
            $entityTypeColumnExists = false;
            $entityIdColumnExists = false;
            try {
                $checkStmt = $this->pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'entity_type'");
                $entityTypeColumnExists = $checkStmt->rowCount() > 0;
                $checkStmt = $this->pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'entity_id'");
                $entityIdColumnExists = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                // Columns don't exist
            }
            
            // Build dynamic INSERT statement for Phase 4 fields
            $fields = [
                'sale_number', 'store_id', 'cashier_id', 'customer_id', 'customer_name', 
                'sale_status', 'payment_status', 'subtotal_amount', 'discount_total', 
                'tax_total', 'total_amount', 'amount_paid', 'change_due', 'notes'
            ];
            $values = [];
            $params = [];
            
            // Add entity_type and entity_id if columns exist
            if ($entityTypeColumnExists && $entityIdColumnExists) {
                $fields[] = 'entity_type';
                $fields[] = 'entity_id';
            }
            
            // Add receipt_number if column exists
            if ($receiptNumberColumnExists) {
                $fields[] = 'receipt_number';
            }
            
            // Add Phase 4 fields if they exist
            if (isset($salePayload['promotion_id'])) {
                $fields[] = 'promotion_id';
            }
            if (isset($salePayload['loyalty_points_earned'])) {
                $fields[] = 'loyalty_points_earned';
            }
            if (isset($salePayload['loyalty_points_redeemed'])) {
                $fields[] = 'loyalty_points_redeemed';
            }
            
            // Check if paper_receipt_number column exists
            $paperReceiptColumnExists = false;
            try {
                $checkStmt = $this->pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'paper_receipt_number'");
                $paperReceiptColumnExists = $checkStmt->rowCount() > 0;
            } catch (PDOException $e) {
                // Column doesn't exist
            }
            
            if ($paperReceiptColumnExists && isset($salePayload['paper_receipt_number'])) {
                $fields[] = 'paper_receipt_number';
            }
            
            foreach ($fields as $field) {
                $values[] = ':' . $field;
            }
            
            $saleStmt = $this->pdo->prepare("
                INSERT INTO pos_sales (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            
            // Determine entity_type and entity_id from payload
            $entityType = $salePayload['entity_type'] ?? null;
            $entityId = isset($salePayload['entity_id']) ? (int)$salePayload['entity_id'] : null;
            
            // If entity_type/entity_id not provided but customer_id is, set entity_type to 'client'
            if (!$entityType && !$entityId && !empty($salePayload['customer_id'])) {
                $entityType = 'client';
                $entityId = (int)$salePayload['customer_id'];
            }
            
            $saleParams = [
                ':sale_number' => $saleNumber,
                ':store_id' => $salePayload['store_id'],
                ':cashier_id' => $salePayload['cashier_id'],
                ':customer_id' => $salePayload['customer_id'] ?? ($entityType === 'client' ? $entityId : null),
                ':customer_name' => $salePayload['customer_name'] ?? null,
                ':sale_status' => $salePayload['sale_status'] ?? 'completed',
                ':payment_status' => $salePayload['payment_status'] ?? 'paid',
                ':subtotal_amount' => $salePayload['subtotal_amount'],
                ':discount_total' => $salePayload['discount_total'] ?? 0,
                ':tax_total' => $salePayload['tax_total'] ?? 0,
                ':total_amount' => $salePayload['total_amount'],
                ':amount_paid' => $salePayload['amount_paid'] ?? $salePayload['total_amount'],
                ':change_due' => $salePayload['change_due'] ?? 0,
                ':notes' => $salePayload['notes'] ?? null,
            ];
            
            // Add entity_type and entity_id if columns exist
            if ($entityTypeColumnExists && $entityIdColumnExists) {
                $saleParams[':entity_type'] = $entityType;
                $saleParams[':entity_id'] = $entityId;
            }
            
            // Add receipt_number if column exists
            if ($receiptNumberColumnExists && $receiptNumber) {
                $saleParams[':receipt_number'] = $receiptNumber;
            }
            
            if (isset($salePayload['promotion_id'])) {
                $saleParams[':promotion_id'] = (int)$salePayload['promotion_id'];
            }
            if (isset($salePayload['loyalty_points_earned'])) {
                $saleParams[':loyalty_points_earned'] = (int)$salePayload['loyalty_points_earned'];
            }
            if (isset($salePayload['loyalty_points_redeemed'])) {
                $saleParams[':loyalty_points_redeemed'] = (int)$salePayload['loyalty_points_redeemed'];
            }
            if ($paperReceiptColumnExists && isset($salePayload['paper_receipt_number']) && !empty(trim($salePayload['paper_receipt_number']))) {
                $saleParams[':paper_receipt_number'] = trim($salePayload['paper_receipt_number']);
            }
            try {
                $saleStmt->execute($saleParams);
            } catch (PDOException $e) {
                error_log('[POS Sale] insert pos_sales failed: ' . $e->getMessage() . ' params=' . json_encode($saleParams));
                throw $e;
            }

            $saleId = (int) $this->pdo->lastInsertId();

            // Insert line items
            $itemStmt = $this->pdo->prepare("
                INSERT INTO pos_sale_items
                    (sale_id, product_id, description, quantity, unit_price, discount_amount, tax_amount, line_total, cost_amount)
                VALUES
                    (:sale_id, :product_id, :description, :quantity, :unit_price, :discount_amount, :tax_amount, :line_total, :cost_amount)
            ");

            foreach ($salePayload['items'] as $item) {
                $itemParams = [
                    ':sale_id' => $saleId,
                    ':product_id' => $item['product_id'],
                    ':description' => $item['description'] ?? null,
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':discount_amount' => $item['discount_amount'] ?? 0,
                    ':tax_amount' => $item['tax_amount'] ?? 0,
                    ':line_total' => $item['line_total'],
                    ':cost_amount' => $item['cost_amount'] ?? null,
                ];
                try {
                    $itemStmt->execute($itemParams);
                } catch (PDOException $e) {
                    error_log('[POS Sale] insert pos_sale_items failed: ' . $e->getMessage() . ' params=' . json_encode($itemParams));
                    throw $e;
                }

                // Deduct inventory - ALWAYS deduct unless explicitly disabled
                // inventory_impact can be false to skip deduction (for services, non-trackable items)
                $shouldDeductInventory = !isset($item['inventory_impact']) || !empty($item['inventory_impact']);
                
                if ($shouldDeductInventory) {
                    try {
                        $this->adjustStock([
                            'store_id' => $salePayload['store_id'],
                            'product_id' => $item['product_id'],
                            'quantity_delta' => -abs($item['quantity']),
                            'transaction_type' => 'sale',
                            'reference_type' => 'pos_sale',
                            'reference_id' => $saleNumber,
                            'unit_cost' => $item['cost_amount'] ?? null,
                            'remarks' => 'POS Sale ' . $saleNumber,
                            'performed_by' => $salePayload['cashier_id'],
                        ]);
                        error_log("[POS Sale] Inventory deducted: Product {$item['product_id']}, Qty: -{$item['quantity']}, Sale: {$saleNumber}");
                    } catch (Throwable $invError) {
                        // Log inventory error but don't fail the sale
                        error_log('[POS Sale] Inventory adjustment failed for product ' . $item['product_id'] . ': ' . $invError->getMessage());
                        error_log('[POS Sale] Stack trace: ' . $invError->getTraceAsString());
                        // Continue with sale even if inventory adjustment fails
                    }
                } else {
                    error_log("[POS Sale] Inventory deduction skipped for product {$item['product_id']} (inventory_impact=false)");
                }
            }

            // Payments
            $paymentStmt = $this->pdo->prepare("
                INSERT INTO pos_sale_payments
                    (sale_id, payment_method, amount, reference, received_at)
                VALUES
                    (:sale_id, :payment_method, :amount, :reference, :received_at)
            ");
            foreach ($salePayload['payments'] as $payment) {
                $paymentParams = [
                    ':sale_id' => $saleId,
                    ':payment_method' => $payment['payment_method'],
                    ':amount' => $payment['amount'],
                    ':reference' => $payment['reference'] ?? null,
                    ':received_at' => $payment['received_at'] ?? date('Y-m-d H:i:s'),
                ];
                try {
                    $paymentStmt->execute($paymentParams);
                } catch (PDOException $e) {
                    error_log('[POS Sale] insert pos_sale_payments failed: ' . $e->getMessage() . ' params=' . json_encode($paymentParams));
                    throw $e;
                }
            }

            // Phase 4: Award loyalty points
            if (!empty($salePayload['customer_id']) && !empty($salePayload['loyalty_points_earned']) && $salePayload['loyalty_points_earned'] > 0) {
                $program = $this->getActiveLoyaltyProgram();
                if ($program) {
                    $this->earnLoyaltyPoints(
                        (int)$salePayload['customer_id'],
                        (int)$program['id'],
                        (int)$salePayload['loyalty_points_earned'],
                        $saleId,
                        "Points earned from sale {$saleNumber}"
                    );
                }
            }

            // Phase 4: Record promotion usage
            if (!empty($salePayload['promotion_id']) && !empty($salePayload['discount_total'])) {
                $this->recordPromotionUsage(
                    (int)$salePayload['promotion_id'],
                    $saleId,
                    $salePayload['customer_id'] ?? null,
                    (float)$salePayload['discount_total']
                );
            }

            // Phase 4: Send email receipt if customer email available (non-blocking)
            if (!empty($salePayload['customer_id'])) {
                try {
                    $customerStmt = $this->pdo->prepare("SELECT email FROM clients WHERE id = :id");
                    $customerStmt->execute([':id' => $salePayload['customer_id']]);
                    $customerEmail = $customerStmt->fetchColumn();
                    if ($customerEmail && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                        // Send email receipt asynchronously (don't block sale completion)
                        $this->sendEmailReceipt($saleId, $customerEmail, (int)$salePayload['customer_id']);
                    }
                } catch (Throwable $emailError) {
                    // Log but don't fail the sale if email fails
                    error_log('[POS Sale] Email receipt failed for sale ' . $saleId . ': ' . $emailError->getMessage());
                }
            }

            // Queue accounting sync (non-blocking - don't fail sale if this fails)
            try {
                $this->queueAccountingPayload('sale', $saleId, $salePayload, $saleId);
            } catch (Throwable $accountingError) {
                error_log('[POS Sale] Accounting queue failed for sale ' . $saleId . ': ' . $accountingError->getMessage());
                // Continue - sale is still valid even if accounting sync fails
            }

            $this->pdo->commit();
            
            // Auto-deduct materials if company sale (after commit to avoid rollback issues)
            try {
                require_once __DIR__ . '/MaterialsService.php';
                $materialsService = new MaterialsService($this->pdo);
                $materialsDeduction = $materialsService->deductMaterialsForSale(
                    $saleId,
                    $salePayload['items'],
                    $salePayload['cashier_id']
                );
                
                if (!empty($materialsDeduction)) {
                    error_log('[POS Sale] Materials deducted: ' . json_encode($materialsDeduction));
                }
            } catch (Throwable $matError) {
                // Log but don't fail the sale - materials deduction is secondary
                error_log('[POS Sale] Materials deduction failed: ' . $matError->getMessage());
            }

            $result = [
                'sale_id' => $saleId,
                'sale_number' => $saleNumber,
            ];
            
            // Add receipt_number to result if it was generated
            if ($receiptNumberColumnExists && $receiptNumber) {
                $result['receipt_number'] = $receiptNumber;
            }
            
            return $result;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Log detailed database error
            error_log('[POS Sale createSale] PDO Error: ' . $e->getMessage());
            error_log('[POS Sale createSale] SQL State: ' . $e->getCode());
            error_log('[POS Sale createSale] Sale payload: ' . json_encode($salePayload, JSON_UNESCAPED_UNICODE));
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Log detailed error
            error_log('[POS Sale createSale] Error: ' . $e->getMessage());
            error_log('[POS Sale createSale] File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            throw $e;
        }
    }

    private function generateSaleNumber(): string
    {
        $prefix = 'POS-' . date('Ymd');
        $stmt = $this->pdo->prepare("SELECT sale_number FROM pos_sales WHERE sale_number LIKE :prefix ORDER BY sale_number DESC LIMIT 1");
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        if (!$last) {
            return sprintf('%s-%04d', $prefix, 1);
        }

        $suffix = (int) substr($last, -4);
        return sprintf('%s-%04d', $prefix, $suffix + 1);
    }

    private function generateSaleReceiptNumber(): string
    {
        // Generate unique receipt number: RCP-YYYYMMDD-XXXXX
        // Check if receipt_number column exists first
        $columnExists = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'receipt_number'");
            $columnExists = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Column doesn't exist, skip uniqueness check
        }
        
        do {
            $number = 'RCP-' . date('Ymd') . '-' . str_pad((string) rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            
            if ($columnExists) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pos_sales WHERE receipt_number = :number");
                $stmt->execute([':number' => $number]);
                $exists = $stmt->fetchColumn() > 0;
            } else {
                // Column doesn't exist, assume unique
                $exists = false;
            }
        } while ($exists);
        
        return $number;
    }

    public function getSaleForAccounting(int $saleId): ?array
    {
        $saleStmt = $this->pdo->prepare("
            SELECT s.*, st.store_code, st.store_name
            FROM pos_sales s
            INNER JOIN pos_stores st ON s.store_id = st.id
            WHERE s.id = :sale_id
        ");
        $saleStmt->execute([':sale_id' => $saleId]);
        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return null;
        }

        $itemsStmt = $this->pdo->prepare("
            SELECT si.*, p.sku, p.name
            FROM pos_sale_items si
            INNER JOIN pos_products p ON si.product_id = p.id
            WHERE si.sale_id = :sale_id
        ");
        $itemsStmt->execute([':sale_id' => $saleId]);
        $sale['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentsStmt = $this->pdo->prepare("
            SELECT *
            FROM pos_sale_payments
            WHERE sale_id = :sale_id
        ");
        $paymentsStmt->execute([':sale_id' => $saleId]);
        $sale['payments'] = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $sale;
    }

    public function markAccountingSyncStatus(int $saleId, string $status, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_accounting_queue
            SET status = :status,
                last_error = :last_error,
                attempts = attempts + 1,
                updated_at = NOW(),
                synced_at = CASE WHEN :status = 'synced' THEN NOW() ELSE synced_at END
            WHERE sale_id = :sale_id
        ");
        $stmt->execute([
            ':status' => $status,
            ':last_error' => $error,
            ':sale_id' => $saleId,
        ]);

        if ($status === 'synced') {
            $this->pdo->prepare("UPDATE pos_sales SET synced_to_accounting = 1, synced_at = NOW() WHERE id = :sale_id")
                ->execute([':sale_id' => $saleId]);
        }
    }

    /* ---------- Store Management ---------- */

    public function getStore(int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_stores WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $storeId]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        return $store ?: null;
    }

    public function createStore(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_stores (store_code, store_name, location, contact_phone, contact_email, is_primary, is_active)
            VALUES (:store_code, :store_name, :location, :contact_phone, :contact_email, :is_primary, :is_active)
        ");
        $stmt->execute([
            ':store_code' => $data['store_code'],
            ':store_name' => $data['store_name'],
            ':location' => $data['location'],
            ':contact_phone' => $data['contact_phone'],
            ':contact_email' => $data['contact_email'],
            ':is_primary' => !empty($data['is_primary']) ? 1 : 0,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        $storeId = (int) $this->pdo->lastInsertId();
        if (!empty($data['is_primary'])) {
            $this->setPrimaryStore($storeId);
        }

        return $storeId;
    }

    public function updateStore(int $storeId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_stores
            SET store_code = :store_code,
                store_name = :store_name,
                location = :location,
                contact_phone = :contact_phone,
                contact_email = :contact_email,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':store_code' => $data['store_code'],
            ':store_name' => $data['store_name'],
            ':location' => $data['location'],
            ':contact_phone' => $data['contact_phone'],
            ':contact_email' => $data['contact_email'],
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $storeId,
        ]);

        if (!empty($data['is_primary'])) {
            $this->setPrimaryStore($storeId);
        }
    }

    public function setPrimaryStore(int $storeId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("UPDATE pos_stores SET is_primary = 0");
            $stmt = $this->pdo->prepare("UPDATE pos_stores SET is_primary = 1, is_active = 1 WHERE id = :id");
            $stmt->execute([':id' => $storeId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function toggleStoreActive(int $storeId, bool $isActive): void
    {
        $stmt = $this->pdo->prepare("UPDATE pos_stores SET is_active = :is_active WHERE id = :id");
        $stmt->execute([
            ':is_active' => $isActive ? 1 : 0,
            ':id' => $storeId,
        ]);
    }

    private function syncCatalogSafely(int $productId): void
    {
        try {
            $syncService = new UnifiedCatalogSyncService($this->pdo);
            $syncService->syncPosToCatalog($productId);
        } catch (Throwable $e) {
            error_log(sprintf('[POS Catalog Sync] Failed for product %d: %s', $productId, $e->getMessage()));
        }
    }

    private function ensurePosCategory(string $categoryName): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM pos_categories WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $categoryName]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            return (int) $existing;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO pos_categories (parent_id, name, slug, description, is_active)
            VALUES (NULL, :name, :slug, :description, 1)
        ");
        $insert->execute([
            ':name' => $categoryName,
            ':slug' => $this->slugify($categoryName),
            ':description' => 'Auto-synced from catalog',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function findProductByCatalogLink(int $catalogItemId, string $sku): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM pos_products WHERE catalog_item_id = :catalog_item_id LIMIT 1");
        $stmt->execute([':catalog_item_id' => $catalogItemId]);
        $productId = $stmt->fetchColumn();
        if ($productId) {
            return (int) $productId;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM pos_products WHERE sku = :sku LIMIT 1");
        $stmt->execute([':sku' => $sku]);
        $productId = $stmt->fetchColumn();

        return $productId ? (int) $productId : null;
    }

    /* ---------- Category Management ---------- */

    public function listCategories(bool $includeInactive = false): array
    {
        $sql = "SELECT c.*, parent.name AS parent_name
                FROM pos_categories c
                LEFT JOIN pos_categories parent ON c.parent_id = parent.id";
        if (!$includeInactive) {
            $sql .= " WHERE c.is_active = 1";
        }
        $sql .= " ORDER BY c.name ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategory(int $categoryId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return $category ?: null;
    }

    public function createCategory(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_categories (parent_id, name, slug, description, is_active)
            VALUES (:parent_id, :name, :slug, :description, :is_active)
        ");
        $stmt->execute([
            ':parent_id' => $data['parent_id'],
            ':name' => $data['name'],
            ':slug' => $this->slugify($data['name']),
            ':description' => $data['description'],
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $categoryId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_categories
            SET parent_id = :parent_id,
                name = :name,
                slug = :slug,
                description = :description,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':parent_id' => $data['parent_id'],
            ':name' => $data['name'],
            ':slug' => $this->slugify($data['name']),
            ':description' => $data['description'],
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $categoryId,
        ]);
    }

    public function toggleCategoryActive(int $categoryId, bool $isActive): void
    {
        $stmt = $this->pdo->prepare("UPDATE pos_categories SET is_active = :is_active WHERE id = :id");
        $stmt->execute([
            ':is_active' => $isActive ? 1 : 0,
            ':id' => $categoryId,
        ]);
    }

    /* ---------- Supplier Management ---------- */

    public function listSuppliers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM pos_suppliers WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE :search OR code LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int) $filters['is_active'];
        }

        $sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupplier(int $supplierId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_suppliers WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $supplierId]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        return $supplier ?: null;
    }

    public function createSupplier(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_suppliers (code, name, email, phone, payment_terms, currency, tax_number, address, notes, is_active)
            VALUES (:code, :name, :email, :phone, :payment_terms, :currency, :tax_number, :address, :notes, :is_active)
        ");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':payment_terms' => $data['payment_terms'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':tax_number' => $data['tax_number'] ?? null,
            ':address' => $data['address'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateSupplier(int $supplierId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_suppliers
            SET code = :code,
                name = :name,
                email = :email,
                phone = :phone,
                payment_terms = :payment_terms,
                currency = :currency,
                tax_number = :tax_number,
                address = :address,
                notes = :notes,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':payment_terms' => $data['payment_terms'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':tax_number' => $data['tax_number'] ?? null,
            ':address' => $data['address'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $supplierId,
        ]);
    }

    public function createSupplierContact(int $supplierId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_supplier_contacts (supplier_id, name, role, phone, email, notes)
            VALUES (:supplier_id, :name, :role, :phone, :email, :notes)
        ");
        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':name' => $data['name'],
            ':role' => $data['role'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteSupplierContact(int $contactId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM pos_supplier_contacts WHERE id = :id");
        $stmt->execute([':id' => $contactId]);
    }

    public function listSupplierContacts(int $supplierId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_supplier_contacts WHERE supplier_id = :supplier_id ORDER BY name ASC");
        $stmt->execute([':supplier_id' => $supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPurchaseOrder(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_purchase_orders
                (po_number, supplier_id, store_id, status, expected_date, payment_terms, currency, notes, created_by)
            VALUES
                (:po_number, :supplier_id, :store_id, :status, :expected_date, :payment_terms, :currency, :notes, :created_by)
        ");
        $stmt->execute([
            ':po_number' => $data['po_number'],
            ':supplier_id' => $data['supplier_id'],
            ':store_id' => $data['store_id'],
            ':status' => $data['status'] ?? 'draft',
            ':expected_date' => $data['expected_date'] ?? null,
            ':payment_terms' => $data['payment_terms'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addPurchaseOrderItem(int $poId, array $item): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_purchase_order_items
                (po_id, product_id, description, ordered_qty, unit_cost, tax_rate, discount_percent, expected_date)
            VALUES
                (:po_id, :product_id, :description, :ordered_qty, :unit_cost, :tax_rate, :discount_percent, :expected_date)
        ");
        $stmt->execute([
            ':po_id' => $poId,
            ':product_id' => $item['product_id'],
            ':description' => $item['description'] ?? null,
            ':ordered_qty' => $item['ordered_qty'],
            ':unit_cost' => $item['unit_cost'],
            ':tax_rate' => $item['tax_rate'] ?? null,
            ':discount_percent' => $item['discount_percent'] ?? null,
            ':expected_date' => $item['expected_date'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updatePurchaseOrderStatus(int $poId, string $status, ?int $approvedBy = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_purchase_orders
            SET status = :status,
                approved_by = :approved_by,
                approved_at = CASE WHEN :approved_by IS NOT NULL THEN NOW() ELSE approved_at END,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':approved_by' => $approvedBy,
            ':id' => $poId,
        ]);
    }

    public function createGoodsReceipt(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_goods_receipts
                (grn_number, po_id, store_id, received_by, received_at, status, notes)
            VALUES
                (:grn_number, :po_id, :store_id, :received_by, :received_at, :status, :notes)
        ");
        $stmt->execute([
            ':grn_number' => $data['grn_number'],
            ':po_id' => $data['po_id'],
            ':store_id' => $data['store_id'],
            ':received_by' => $data['received_by'],
            ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
            ':status' => $data['status'] ?? 'draft',
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addGoodsReceiptItem(int $grnId, array $item): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_goods_receipt_items
                (grn_id, po_item_id, product_id, received_qty, rejected_qty, unit_cost, batch_code, expiry_date)
            VALUES
                (:grn_id, :po_item_id, :product_id, :received_qty, :rejected_qty, :unit_cost, :batch_code, :expiry_date)
        ");
        $stmt->execute([
            ':grn_id' => $grnId,
            ':po_item_id' => $item['po_item_id'],
            ':product_id' => $item['product_id'],
            ':received_qty' => $item['received_qty'],
            ':rejected_qty' => $item['rejected_qty'] ?? 0,
            ':unit_cost' => $item['unit_cost'],
            ':batch_code' => $item['batch_code'] ?? null,
            ':expiry_date' => $item['expiry_date'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createSupplierInvoice(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_supplier_invoices
                (invoice_number, supplier_id, store_id, po_id, grn_id, invoice_date, due_date, status, currency, notes, created_by)
            VALUES
                (:invoice_number, :supplier_id, :store_id, :po_id, :grn_id, :invoice_date, :due_date, :status, :currency, :notes, :created_by)
        ");
        $stmt->execute([
            ':invoice_number' => $data['invoice_number'],
            ':supplier_id' => $data['supplier_id'],
            ':store_id' => $data['store_id'],
            ':po_id' => $data['po_id'] ?? null,
            ':grn_id' => $data['grn_id'],
            ':invoice_date' => $data['invoice_date'],
            ':due_date' => $data['due_date'] ?? null,
            ':status' => $data['status'] ?? 'draft',
            ':currency' => $data['currency'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addSupplierInvoiceItem(int $invoiceId, array $item): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_supplier_invoice_items
                (invoice_id, product_id, quantity, unit_cost, tax_rate, line_total, description)
            VALUES
                (:invoice_id, :product_id, :quantity, :unit_cost, :tax_rate, :line_total, :description)
        ");
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':unit_cost' => $item['unit_cost'],
            ':tax_rate' => $item['tax_rate'] ?? null,
            ':line_total' => $item['line_total'],
            ':description' => $item['description'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /* ---------- Product Helpers ---------- */

    public function getProduct(int $productId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name
            FROM pos_products p
            LEFT JOIN pos_categories c ON p.category_id = c.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product ?: null;
    }

    /* ---------- Inventory & Sales Intelligence ---------- */

    public function listInventoryByStore(int $storeId, ?int $limit = null, int $offset = 0): array
    {
        $sql = "
            SELECT
                p.id,
                p.sku,
                p.name,
                p.track_inventory,
                COALESCE(ci.inventory_quantity, ci.stock_quantity, 0) AS quantity_on_hand,
                COALESCE(i.quantity_on_hand, 0) AS pos_store_quantity,
                i.average_cost,
                p.unit_price,
                i.reorder_level,
                i.reorder_quantity,
                COALESCE(i.updated_at, p.updated_at) AS updated_at
            FROM pos_products p
            LEFT JOIN catalog_items ci ON p.catalog_item_id = ci.id
            LEFT JOIN pos_inventory i
                ON i.product_id = p.id AND i.store_id = :store_id
            ORDER BY p.name ASC
        ";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
        if ($limit !== null) {
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countInventoryByStore(int $storeId): int
    {
        if ($storeId <= 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pos_products");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function listRecentSales(?int $storeId = null, int $limit = 20): array
    {
        $sql = "
            SELECT
                s.id,
                s.sale_number,
                s.store_id,
                st.store_name,
                s.total_amount,
                s.payment_status,
                s.sale_status,
                s.sale_timestamp,
                s.synced_to_accounting,
                s.synced_at
            FROM pos_sales s
            INNER JOIN pos_stores st ON s.store_id = st.id
        ";

        $params = [];
        if ($storeId) {
            $sql .= " WHERE s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }
        $sql .= " ORDER BY s.sale_timestamp DESC LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAccountingQueue(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                q.id,
                q.sale_id,
                q.reference_type,
                q.reference_id,
                q.status,
                q.attempts,
                q.last_error,
                q.created_at,
                q.updated_at,
                s.sale_number,
                s.total_amount,
                s.sale_timestamp,
                st.store_name
            FROM pos_accounting_queue q
            LEFT JOIN pos_sales s ON q.sale_id = s.id
            LEFT JOIN pos_stores st ON s.store_id = st.id
            ORDER BY q.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ---------- Receipts ---------- */

    public function getReceiptForSale(int $saleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_receipts WHERE sale_id = :sale_id ORDER BY printed_at DESC LIMIT 1");
        $stmt->execute([':sale_id' => $saleId]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        return $receipt ?: null;
    }

    public function recordReceipt(int $saleId, string $format, string $contentHtml, ?int $printedBy = null): void
    {
        $existing = $this->getReceiptForSale($saleId);
        $receiptNumber = $existing['receipt_number'] ?? $this->generateReceiptNumber($saleId);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE pos_receipts
                SET content_html = :content_html,
                    format = :format,
                    printed_by = :printed_by,
                    printed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':content_html' => $contentHtml,
                ':format' => $format,
                ':printed_by' => $printedBy,
                ':id' => $existing['id'],
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_receipts (sale_id, receipt_number, format, content_html, printed_by)
                VALUES (:sale_id, :receipt_number, :format, :content_html, :printed_by)
            ");
            $stmt->execute([
                ':sale_id' => $saleId,
                ':receipt_number' => $receiptNumber,
                ':format' => $format,
                ':content_html' => $contentHtml,
                ':printed_by' => $printedBy,
            ]);
        }
    }

    private function generateReceiptNumber(int $saleId): string
    {
        return 'RC-' . date('Ymd') . '-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);
    }

    public function updateSupplierInvoiceStatus(int $invoiceId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_supplier_invoices
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':id' => $invoiceId,
        ]);
    }

    public function updateSupplierInvoiceTotals(int $invoiceId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(line_total),0) AS subtotal,
                COALESCE(SUM(line_total * (tax_rate/100)),0) AS tax
            FROM pos_supplier_invoice_items
            WHERE invoice_id = :invoice_id
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['subtotal' => 0, 'tax' => 0];
        $total = (float) $totals['subtotal'] + (float) $totals['tax'];

        $update = $this->pdo->prepare("
            UPDATE pos_supplier_invoices
            SET subtotal_amount = :subtotal,
                tax_amount = :tax,
                total_amount = :total,
                balance_due = :total,
                updated_at = NOW()
            WHERE id = :invoice_id
        ");
        $update->execute([
            ':subtotal' => $totals['subtotal'],
            ':tax' => $totals['tax'],
            ':total' => $total,
            ':invoice_id' => $invoiceId,
        ]);
    }

    public function getSupplierInvoice(int $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_supplier_invoices WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    public function incrementPurchaseOrderItemReceived(int $poItemId, float $quantity): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_purchase_order_items
            SET received_qty = received_qty + :quantity,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':quantity' => $quantity,
            ':id' => $poItemId,
        ]);
    }

    public function incrementPurchaseOrderItemBilled(int $poItemId, float $quantity): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_purchase_order_items
            SET billed_qty = billed_qty + :quantity,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':quantity' => $quantity,
            ':id' => $poItemId,
        ]);
    }

    public function updateGoodsReceiptStatus(int $grnId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_goods_receipts
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':id' => $grnId,
        ]);
    }

    public function getPurchaseOrder(int $poId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_purchase_orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $poId]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);
        return $po ?: null;
    }

    public function listPurchaseOrderItems(int $poId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_purchase_order_items WHERE po_id = :po_id ORDER BY id ASC");
        $stmt->execute([':po_id' => $poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function refreshPurchaseOrderStatus(int $poId): void
    {
        $po = $this->getPurchaseOrder($poId);
        if (!$po || $po['status'] === 'cancelled') {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(ordered_qty),0) AS ordered_qty,
                COALESCE(SUM(received_qty),0) AS received_qty
            FROM pos_purchase_order_items
            WHERE po_id = :po_id
        ");
        $stmt->execute([':po_id' => $poId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['ordered_qty' => 0, 'received_qty' => 0];

        $ordered = (float) $totals['ordered_qty'];
        $received = (float) $totals['received_qty'];

        if ($ordered <= 0) {
            return;
        }

        $newStatus = $po['status'];
        if ($received >= $ordered) {
            $newStatus = 'completed';
        } elseif ($received > 0 && $po['status'] !== 'completed') {
            $newStatus = 'partially_received';
        }

        if ($newStatus !== $po['status']) {
            $this->updatePurchaseOrderStatus($poId, $newStatus);
        }
    }

    public function queueAccountingPayload(string $referenceType, ?int $referenceId, array $payload, ?int $saleId = null): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_accounting_queue (sale_id, reference_type, reference_id, payload_json)
                VALUES (:sale_id, :reference_type, :reference_id, :payload_json)
            ");
            $stmt->execute([
                ':sale_id' => $saleId,
                ':reference_type' => $referenceType,
                ':reference_id' => $referenceId,
                ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (PDOException $e) {
            // Gracefully skip if the queue table is unavailable so cashier sales never fail.
            if ($e->getCode() !== '42S02') { // 42S02 = table not found (MySQL)
                throw $e;
            }
            error_log('[POS Accounting Queue] Skipped queueing payload: ' . $e->getMessage());
        }
    }

    /* ---------- Refunds ---------- */

    public function createRefund(array $refundPayload): array
    {
        $this->pdo->beginTransaction();
        try {
            // Get original sale
            $saleStmt = $this->pdo->prepare("SELECT * FROM pos_sales WHERE id = :sale_id");
            $saleStmt->execute([':sale_id' => $refundPayload['original_sale_id']]);
            $originalSale = $saleStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$originalSale) {
                throw new InvalidArgumentException('Original sale not found');
            }
            
            $refundNumber = $this->generateRefundNumber();
            $totalAmount = (float)($refundPayload['total_amount'] ?? 0);
            
            // Check if approval is required (configurable threshold, default 500)
            $approvalThreshold = (float)($this->getCompanyConfig('pos_refund_approval_threshold') ?: 500);
            $requiresApproval = $totalAmount > $approvalThreshold;
            $refundStatus = $requiresApproval ? 'pending' : ($refundPayload['refund_status'] ?? 'completed');
            
            // If approval is provided and required, use it
            if ($requiresApproval && !empty($refundPayload['approved_by'])) {
                $refundStatus = 'completed';
            }
            
            // Ensure schema has approval fields (migration will handle if not exists)
            try {
                $this->pdo->exec("
                    ALTER TABLE pos_refunds 
                    ADD COLUMN IF NOT EXISTS requires_approval TINYINT(1) DEFAULT 0,
                    ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS approval_notes TEXT DEFAULT NULL
                ");
            } catch (PDOException $e) {
                // Columns might already exist, continue
            }
            
            $refundStmt = $this->pdo->prepare("
                INSERT INTO pos_refunds
                    (refund_number, original_sale_id, store_id, cashier_id, customer_id, customer_name,
                     refund_type, refund_reason, subtotal_amount, tax_total, total_amount,
                     refund_method, refund_status, notes, requires_approval, approved_by, approved_at, approval_notes)
                VALUES
                    (:refund_number, :original_sale_id, :store_id, :cashier_id, :customer_id, :customer_name,
                     :refund_type, :refund_reason, :subtotal_amount, :tax_total, :total_amount,
                     :refund_method, :refund_status, :notes, :requires_approval, :approved_by, :approved_at, :approval_notes)
            ");
            
            $refundStmt->execute([
                ':refund_number' => $refundNumber,
                ':original_sale_id' => $refundPayload['original_sale_id'],
                ':store_id' => $refundPayload['store_id'],
                ':cashier_id' => $refundPayload['cashier_id'],
                ':customer_id' => $refundPayload['customer_id'] ?? $originalSale['customer_id'],
                ':customer_name' => $refundPayload['customer_name'] ?? $originalSale['customer_name'],
                ':refund_type' => $refundPayload['refund_type'] ?? 'full',
                ':refund_reason' => $refundPayload['refund_reason'] ?? null,
                ':subtotal_amount' => $refundPayload['subtotal_amount'] ?? 0,
                ':tax_total' => $refundPayload['tax_total'] ?? 0,
                ':total_amount' => $totalAmount,
                ':refund_method' => $refundPayload['refund_method'] ?? 'original_method',
                ':refund_status' => $refundStatus,
                ':notes' => $refundPayload['notes'] ?? null,
                ':requires_approval' => $requiresApproval ? 1 : 0,
                ':approved_by' => $refundPayload['approved_by'] ?? null,
                ':approved_at' => !empty($refundPayload['approved_by']) ? date('Y-m-d H:i:s') : null,
                ':approval_notes' => $refundPayload['approval_notes'] ?? null,
            ]);
            
            $refundId = (int)$this->pdo->lastInsertId();
            
            // Add refund items
            if (!empty($refundPayload['items'])) {
                $itemStmt = $this->pdo->prepare("
                    INSERT INTO pos_refund_items
                        (refund_id, original_sale_item_id, product_id, quantity, unit_price, tax_amount, line_total)
                    VALUES
                        (:refund_id, :original_sale_item_id, :product_id, :quantity, :unit_price, :tax_amount, :line_total)
                ");
                
                foreach ($refundPayload['items'] as $item) {
                    $itemStmt->execute([
                        ':refund_id' => $refundId,
                        ':original_sale_item_id' => $item['original_sale_item_id'],
                        ':product_id' => $item['product_id'],
                        ':quantity' => $item['quantity'],
                        ':unit_price' => $item['unit_price'],
                        ':tax_amount' => $item['tax_amount'] ?? 0,
                        ':line_total' => $item['line_total'],
                    ]);
                    
                    // Automatically restore inventory (return to stock) for all refunded items
                    // Only skip if explicitly set to false
                    // Only restore if refund is completed (not pending approval)
                    $restoreInventory = !isset($item['restore_inventory']) || $item['restore_inventory'] !== false;
                    
                    if ($restoreInventory && $refundStatus === 'completed') {
                        // Check if product tracks inventory
                        $productStmt = $this->pdo->prepare("SELECT track_inventory FROM pos_products WHERE id = :product_id");
                        $productStmt->execute([':product_id' => $item['product_id']]);
                        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($product && !empty($product['track_inventory'])) {
                            $this->adjustStock([
                                'store_id' => $refundPayload['store_id'],
                                'product_id' => $item['product_id'],
                                'quantity_delta' => abs($item['quantity']),
                                'transaction_type' => 'return_in',
                                'reference_type' => 'pos_refund',
                                'reference_id' => $refundNumber,
                                'remarks' => 'Refund ' . $refundNumber . ($refundPayload['refund_reason'] ? ' - ' . $refundPayload['refund_reason'] : ''),
                                'performed_by' => $refundPayload['cashier_id'],
                            ]);
                        }
                    }
                }
            }
            
            // Update original sale status if full refund and completed (not pending)
            if (($refundPayload['refund_type'] ?? 'full') === 'full' && $refundStatus === 'completed') {
                $updateStmt = $this->pdo->prepare("
                    UPDATE pos_sales SET sale_status = 'refunded' WHERE id = :sale_id
                ");
                $updateStmt->execute([':sale_id' => $refundPayload['original_sale_id']]);
            }
            
            $this->pdo->commit();
            
            return [
                'refund_id' => $refundId,
                'refund_number' => $refundNumber,
                'requires_approval' => $requiresApproval,
                'status' => $refundStatus,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    private function generateRefundNumber(): string
    {
        $prefix = 'REF-' . date('Ymd');
        $stmt = $this->pdo->prepare("SELECT refund_number FROM pos_refunds WHERE refund_number LIKE :prefix ORDER BY refund_number DESC LIMIT 1");
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        
        if (!$last) {
            return sprintf('%s-%04d', $prefix, 1);
        }
        
        $suffix = (int) substr($last, -4);
        return sprintf('%s-%04d', $prefix, $suffix + 1);
    }
    
    public function getRefund(int $refundId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_refunds WHERE id = :id");
        $stmt->execute([':id' => $refundId]);
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$refund) {
            return null;
        }
        
        // Get refund items with cost information from original sale items
        $itemsStmt = $this->pdo->prepare("
            SELECT 
                ri.*, 
                p.name, 
                p.sku,
                p.cost_price,
                si.cost_amount as original_cost_amount
            FROM pos_refund_items ri
            INNER JOIN pos_products p ON ri.product_id = p.id
            LEFT JOIN pos_sale_items si ON ri.original_sale_item_id = si.id
            WHERE ri.refund_id = :refund_id
        ");
        $itemsStmt->execute([':refund_id' => $refundId]);
        $refund['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $refund;
    }
    
    public function getRefundsBySale(int $saleId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_refunds WHERE original_sale_id = :sale_id ORDER BY refund_timestamp DESC");
        $stmt->execute([':sale_id' => $saleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function listRefunds(int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        $sql = "
            SELECT r.*, s.sale_number AS original_sale_number,
                   u_approver.full_name AS approver_name,
                   u_cashier.full_name AS cashier_name
            FROM pos_refunds r
            LEFT JOIN pos_sales s ON r.original_sale_id = s.id
            LEFT JOIN users u_approver ON r.approved_by = u_approver.id
            LEFT JOIN users u_cashier ON r.cashier_id = u_cashier.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($status !== null) {
            $sql .= " AND r.refund_status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY r.refund_timestamp DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approveRefund(int $refundId, int $approverId, ?string $approvalNotes = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Get refund
            $refund = $this->getRefund($refundId);
            if (!$refund) {
                throw new InvalidArgumentException('Refund not found');
            }
            
            if ($refund['refund_status'] !== 'pending') {
                throw new InvalidArgumentException('Refund is not pending approval');
            }
            
            // Update refund status
            $updateStmt = $this->pdo->prepare("
                UPDATE pos_refunds
                SET refund_status = 'completed',
                    approved_by = :approver_id,
                    approved_at = NOW(),
                    approval_notes = :approval_notes
                WHERE id = :refund_id
            ");
            $updateStmt->execute([
                ':refund_id' => $refundId,
                ':approver_id' => $approverId,
                ':approval_notes' => $approvalNotes,
            ]);
            
            // Now restore inventory for refunded items
            $itemsStmt = $this->pdo->prepare("
                SELECT ri.*, p.track_inventory
                FROM pos_refund_items ri
                INNER JOIN pos_products p ON ri.product_id = p.id
                WHERE ri.refund_id = :refund_id
            ");
            $itemsStmt->execute([':refund_id' => $refundId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                if (!empty($item['track_inventory'])) {
                    $this->adjustStock([
                        'store_id' => $refund['store_id'],
                        'product_id' => $item['product_id'],
                        'quantity_delta' => abs($item['quantity']),
                        'transaction_type' => 'return_in',
                        'reference_type' => 'pos_refund',
                        'reference_id' => $refund['refund_number'],
                        'remarks' => 'Refund ' . $refund['refund_number'] . ($refund['refund_reason'] ? ' - ' . $refund['refund_reason'] : '') . ' (Approved)',
                        'performed_by' => $approverId,
                    ]);
                }
            }
            
            // Update original sale status if full refund
            if ($refund['refund_type'] === 'full') {
                $updateStmt = $this->pdo->prepare("
                    UPDATE pos_sales SET sale_status = 'refunded' WHERE id = :sale_id
                ");
                $updateStmt->execute([':sale_id' => $refund['original_sale_id']]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function rejectRefund(int $refundId, int $rejectorId, string $rejectionNotes): bool
    {
        $refund = $this->getRefund($refundId);
        if (!$refund) {
            throw new InvalidArgumentException('Refund not found');
        }
        
        if ($refund['refund_status'] !== 'pending') {
            throw new InvalidArgumentException('Refund is not pending approval');
        }
        
        $updateStmt = $this->pdo->prepare("
            UPDATE pos_refunds
            SET refund_status = 'cancelled',
                approved_by = :rejector_id,
                approved_at = NOW(),
                approval_notes = :rejection_notes
            WHERE id = :refund_id
        ");
        $updateStmt->execute([
            ':refund_id' => $refundId,
            ':rejector_id' => $rejectorId,
            ':rejection_notes' => 'REJECTED: ' . $rejectionNotes,
        ]);
        
        return true;
    }

    public function getPendingRefunds(int $limit = 50): array
    {
        return $this->listRefunds($limit, 0, 'pending');
    }

    /* ---------- Cash Drawer Management ---------- */

    public function openDrawerSession(int $storeId, int $cashierId, float $openingAmount = 0): array
    {
        // Check if there's already an open session
        $existing = $this->getCurrentDrawerSession($storeId, $cashierId);
        if ($existing && ($existing['status'] ?? '') === 'open') {
            return $existing;
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_cash_drawer_sessions
                (store_id, cashier_id, opening_amount, status)
            VALUES
                (:store_id, :cashier_id, :opening_amount, 'open')
        ");
        $stmt->execute([
            ':store_id' => $storeId,
            ':cashier_id' => $cashierId,
            ':opening_amount' => $openingAmount,
        ]);
        
        $sessionId = (int)$this->pdo->lastInsertId();
        $session = $this->getDrawerSession($sessionId);
        if (!$session) {
            throw new RuntimeException('Failed to retrieve created drawer session');
        }
        return $session;
    }
    
    public function closeDrawerSession(int $storeId, int $cashierId, ?float $countedAmount = null, ?string $notes = null): array
    {
        $session = $this->getCurrentDrawerSession($storeId, $cashierId);
        if (!$session || ($session['status'] ?? '') !== 'open') {
            throw new InvalidArgumentException('No open drawer session found');
        }
        
        // Calculate expected amount from sales (only cash payments)
        $salesStmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN sp.payment_method = 'cash' THEN sp.amount ELSE 0 END), 0) AS cash_sales,
                COALESCE(SUM(CASE WHEN sp.payment_method != 'cash' THEN sp.amount ELSE 0 END), 0) AS non_cash_sales,
                COUNT(DISTINCT s.id) AS total_transactions
            FROM pos_sales s
            LEFT JOIN pos_sale_payments sp ON s.id = sp.sale_id
            WHERE s.store_id = :store_id
              AND s.cashier_id = :cashier_id
              AND s.sale_timestamp >= :opened_at
              AND s.sale_status = 'completed'
              AND s.payment_status = 'paid'
        ");
        $salesStmt->execute([
            ':store_id' => $storeId,
            ':cashier_id' => $cashierId,
            ':opened_at' => $session['opened_at'],
        ]);
        $salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);
        $cashSales = (float)($salesData['cash_sales'] ?? 0);
        $nonCashSales = (float)($salesData['non_cash_sales'] ?? 0);
        $totalTransactions = (int)($salesData['total_transactions'] ?? 0);
        
        // Expected cash = opening float + cash sales
        $expectedCash = $session['opening_amount'] + $cashSales;
        
        $stmt = $this->pdo->prepare("
            UPDATE pos_cash_drawer_sessions
            SET status = 'closed',
                expected_amount = :expected_amount,
                counted_amount = :counted_amount,
                difference = :difference,
                closed_at = NOW(),
                notes = :notes
            WHERE id = :session_id
        ");
        
        $difference = $countedAmount !== null ? $countedAmount - $expectedCash : null;
        
        $stmt->execute([
            ':session_id' => $session['id'],
            ':expected_amount' => $expectedCash,
            ':counted_amount' => $countedAmount,
            ':difference' => $difference,
            ':notes' => $notes,
        ]);
        
        // Store reconciliation data (cash sales, non-cash sales, transactions)
        try {
            $this->pdo->exec("
                ALTER TABLE pos_cash_drawer_sessions 
                ADD COLUMN IF NOT EXISTS cash_sales DECIMAL(12,2) DEFAULT 0,
                ADD COLUMN IF NOT EXISTS non_cash_sales DECIMAL(12,2) DEFAULT 0,
                ADD COLUMN IF NOT EXISTS total_transactions INT DEFAULT 0
            ");
            
            $updateStmt = $this->pdo->prepare("
                UPDATE pos_cash_drawer_sessions
                SET cash_sales = :cash_sales,
                    non_cash_sales = :non_cash_sales,
                    total_transactions = :total_transactions
                WHERE id = :session_id
            ");
            $updateStmt->execute([
                ':session_id' => $session['id'],
                ':cash_sales' => $cashSales,
                ':non_cash_sales' => $nonCashSales,
                ':total_transactions' => $totalTransactions,
            ]);
        } catch (PDOException $e) {
            // Columns might already exist or migration needed, continue
        }
        
        $closedSession = $this->getDrawerSession($session['id']);
        if (!$closedSession) {
            throw new RuntimeException('Failed to retrieve closed drawer session');
        }
        return $closedSession;
    }
    
    public function countDrawerSession(int $storeId, int $cashierId, float $countedAmount): array
    {
        $session = $this->getCurrentDrawerSession($storeId, $cashierId);
        if (!$session) {
            throw new InvalidArgumentException('No drawer session found');
        }
        
        // Calculate expected amount from cash sales only
        $salesStmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN sp.payment_method = 'cash' THEN sp.amount ELSE 0 END), 0) AS cash_sales
            FROM pos_sales s
            LEFT JOIN pos_sale_payments sp ON s.id = sp.sale_id
            WHERE s.store_id = :store_id
              AND s.cashier_id = :cashier_id
              AND s.sale_timestamp >= :opened_at
              AND s.sale_status = 'completed'
              AND s.payment_status = 'paid'
        ");
        $salesStmt->execute([
            ':store_id' => $storeId,
            ':cashier_id' => $cashierId,
            ':opened_at' => $session['opened_at'],
        ]);
        $cashSales = (float)$salesStmt->fetchColumn();
        $expectedCash = $session['opening_amount'] + $cashSales;
        $difference = $countedAmount - $expectedCash;
        
        $stmt = $this->pdo->prepare("
            UPDATE pos_cash_drawer_sessions
            SET status = 'counted',
                expected_amount = :expected_amount,
                counted_amount = :counted_amount,
                difference = :difference,
                counted_at = NOW()
            WHERE id = :session_id
        ");
        $stmt->execute([
            ':session_id' => $session['id'],
            ':expected_amount' => $expectedCash,
            ':counted_amount' => $countedAmount,
            ':difference' => $difference,
        ]);
        
        $countedSession = $this->getDrawerSession($session['id']);
        if (!$countedSession) {
            throw new RuntimeException('Failed to retrieve counted drawer session');
        }
        return $countedSession;
    }
    
    public function getCurrentDrawerSession(int $storeId, int $cashierId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pos_cash_drawer_sessions
            WHERE store_id = :store_id
              AND cashier_id = :cashier_id
              AND status IN ('open', 'counted')
            ORDER BY opened_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':store_id' => $storeId,
            ':cashier_id' => $cashierId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getDrawerSession(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_cash_drawer_sessions WHERE id = :id");
        $stmt->execute([':id' => $sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function listDrawerSessions(int $cashierId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pos_cash_drawer_sessions
            WHERE cashier_id = :cashier_id
            ORDER BY opened_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':cashier_id', $cashierId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ---------- Employee Shift Management ---------- */

    public function startShift(int $employeeId, int $storeId, float $openingCash = 0): array
    {
        // Check for active shift
        $active = $this->getActiveShift($employeeId, $storeId);
        if ($active) {
            return $active;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pos_employee_shifts
                (employee_id, store_id, shift_start, opening_cash, status)
            VALUES
                (:employee_id, :store_id, NOW(), :opening_cash, 'active')
        ");
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':store_id' => $storeId,
            ':opening_cash' => $openingCash,
        ]);

        $shiftId = (int)$this->pdo->lastInsertId();
        return $this->getShift($shiftId);
    }

    public function endShift(int $employeeId, int $storeId, ?float $closingCash = null, ?string $notes = null): array
    {
        $shift = $this->getActiveShift($employeeId, $storeId);
        if (!$shift) {
            throw new InvalidArgumentException('No active shift found');
        }

        // Calculate sales totals
        $salesStmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COUNT(*) AS total_transactions
            FROM pos_sales
            WHERE cashier_id = :employee_id
              AND store_id = :store_id
              AND sale_timestamp >= :shift_start
              AND sale_status = 'completed'
        ");
        $salesStmt->execute([
            ':employee_id' => $employeeId,
            ':store_id' => $storeId,
            ':shift_start' => $shift['shift_start'],
        ]);
        $sales = $salesStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalSales = (float)($sales['total_sales'] ?? 0);
        $totalTransactions = (int)($sales['total_transactions'] ?? 0);
        $expectedCash = $shift['opening_cash'] + $totalSales;
        $cashDifference = $closingCash !== null ? $closingCash - $expectedCash : null;

        $stmt = $this->pdo->prepare("
            UPDATE pos_employee_shifts
            SET shift_end = NOW(),
                closing_cash = :closing_cash,
                expected_cash = :expected_cash,
                cash_difference = :cash_difference,
                total_sales = :total_sales,
                total_transactions = :total_transactions,
                status = 'completed',
                notes = :notes
            WHERE id = :shift_id
        ");
        $stmt->execute([
            ':shift_id' => $shift['id'],
            ':closing_cash' => $closingCash,
            ':expected_cash' => $expectedCash,
            ':cash_difference' => $cashDifference,
            ':total_sales' => $totalSales,
            ':total_transactions' => $totalTransactions,
            ':notes' => $notes,
        ]);

        return $this->getShift($shift['id']);
    }

    public function getActiveShift(int $employeeId, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pos_employee_shifts
            WHERE employee_id = :employee_id
              AND store_id = :store_id
              AND status = 'active'
            ORDER BY shift_start DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':store_id' => $storeId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getShift(int $shiftId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_employee_shifts WHERE id = :id");
        $stmt->execute([':id' => $shiftId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listShifts(int $employeeId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, st.store_name, u.full_name AS employee_name
            FROM pos_employee_shifts s
            INNER JOIN pos_stores st ON st.id = s.store_id
            LEFT JOIN users u ON u.id = s.employee_id
            WHERE s.employee_id = :employee_id
            ORDER BY s.shift_start DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getShiftReport(int $shiftId): ?array
    {
        $shift = $this->getShift($shiftId);
        if (!$shift) {
            return null;
        }
        
        // Get detailed sales data for the shift
        $salesStmt = $this->pdo->prepare("
            SELECT 
                s.*,
                COUNT(DISTINCT s.id) as transaction_count,
                COALESCE(SUM(s.total_amount), 0) as total_sales,
                COALESCE(SUM(s.subtotal_amount), 0) as total_subtotal,
                COALESCE(SUM(s.discount_total), 0) as total_discounts,
                COALESCE(SUM(s.tax_total), 0) as total_tax,
                COALESCE(SUM(CASE WHEN sp.payment_method = 'cash' THEN sp.amount ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN sp.payment_method = 'card' THEN sp.amount ELSE 0 END), 0) as card_sales,
                COALESCE(SUM(CASE WHEN sp.payment_method = 'mobile_money' THEN sp.amount ELSE 0 END), 0) as mobile_money_sales,
                COALESCE(AVG(s.total_amount), 0) as avg_sale_amount
            FROM pos_sales s
            LEFT JOIN pos_sale_payments sp ON s.id = sp.sale_id
            WHERE s.cashier_id = :employee_id
              AND s.store_id = :store_id
              AND s.sale_timestamp >= :shift_start
              AND s.sale_timestamp <= COALESCE(:shift_end, NOW())
              AND s.sale_status = 'completed'
            GROUP BY s.id
        ");
        $salesStmt->execute([
            ':employee_id' => $shift['employee_id'],
            ':store_id' => $shift['store_id'],
            ':shift_start' => $shift['shift_start'],
            ':shift_end' => $shift['shift_end'],
        ]);
        $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate summary
        $totalSales = array_sum(array_column($sales, 'total_amount'));
        $totalTransactions = count($sales);
        $avgSale = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;
        
        // Get top products
        $topProductsStmt = $this->pdo->prepare("
            SELECT 
                p.name,
                p.sku,
                SUM(si.quantity) as total_quantity,
                SUM(si.line_total) as total_revenue
            FROM pos_sale_items si
            INNER JOIN pos_products p ON si.product_id = p.id
            INNER JOIN pos_sales s ON si.sale_id = s.id
            WHERE s.cashier_id = :employee_id
              AND s.store_id = :store_id
              AND s.sale_timestamp >= :shift_start
              AND s.sale_timestamp <= COALESCE(:shift_end, NOW())
              AND s.sale_status = 'completed'
            GROUP BY p.id, p.name, p.sku
            ORDER BY total_revenue DESC
            LIMIT 10
        ");
        $topProductsStmt->execute([
            ':employee_id' => $shift['employee_id'],
            ':store_id' => $shift['store_id'],
            ':shift_start' => $shift['shift_start'],
            ':shift_end' => $shift['shift_end'],
        ]);
        $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'shift' => $shift,
            'summary' => [
                'total_sales' => (float)$totalSales,
                'total_transactions' => $totalTransactions,
                'avg_sale_amount' => (float)$avgSale,
                'cash_sales' => (float)array_sum(array_column($sales, 'cash_sales')),
                'card_sales' => (float)array_sum(array_column($sales, 'card_sales')),
                'mobile_money_sales' => (float)array_sum(array_column($sales, 'mobile_money_sales')),
            ],
            'top_products' => $topProducts,
            'sales' => $sales,
        ];
    }

    public function getCashierPerformance(int $cashierId, ?string $startDate = null, ?string $endDate = null): array
    {
        $whereClause = "WHERE s.cashier_id = :cashier_id AND s.sale_status = 'completed'";
        $params = [':cashier_id' => $cashierId];
        
        if ($startDate) {
            $whereClause .= " AND s.sale_timestamp >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $whereClause .= " AND s.sale_timestamp <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        // Get performance metrics
        $perfStmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as total_transactions,
                COALESCE(SUM(s.total_amount), 0) as total_sales,
                COALESCE(AVG(s.total_amount), 0) as avg_sale_amount,
                COALESCE(MAX(s.total_amount), 0) as max_sale_amount,
                COALESCE(MIN(s.total_amount), 0) as min_sale_amount,
                COALESCE(SUM(s.discount_total), 0) as total_discounts,
                COALESCE(SUM(s.tax_total), 0) as total_tax,
                COUNT(DISTINCT DATE(s.sale_timestamp)) as days_worked
            FROM pos_sales s
            {$whereClause}
        ");
        $perfStmt->execute($params);
        $performance = $perfStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get hourly performance
        $hourlyStmt = $this->pdo->prepare("
            SELECT 
                HOUR(s.sale_timestamp) as hour,
                COUNT(*) as transaction_count,
                COALESCE(SUM(s.total_amount), 0) as sales_amount
            FROM pos_sales s
            {$whereClause}
            GROUP BY HOUR(s.sale_timestamp)
            ORDER BY hour
        ");
        $hourlyStmt->execute($params);
        $hourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment method breakdown
        $paymentStmt = $this->pdo->prepare("
            SELECT 
                sp.payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(sp.amount), 0) as total_amount
            FROM pos_sale_payments sp
            INNER JOIN pos_sales s ON sp.sale_id = s.id
            {$whereClause}
            GROUP BY sp.payment_method
        ");
        $paymentStmt->execute($params);
        $paymentMethods = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'cashier_id' => $cashierId,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'performance' => $performance,
            'hourly_data' => $hourlyData,
            'payment_methods' => $paymentMethods,
        ];
    }

    /* ---------- Product Variants ---------- */

    public function createProductVariant(int $productId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_product_variants
                (product_id, variant_name, sku_suffix, barcode, unit_price, cost_price, quantity_on_hand, is_default, is_active)
            VALUES
                (:product_id, :variant_name, :sku_suffix, :barcode, :unit_price, :cost_price, :quantity_on_hand, :is_default, :is_active)
        ");
        $stmt->execute([
            ':product_id' => $productId,
            ':variant_name' => trim($data['variant_name']),
            ':sku_suffix' => !empty($data['sku_suffix']) ? trim($data['sku_suffix']) : null,
            ':barcode' => !empty($data['barcode']) ? trim($data['barcode']) : null,
            ':unit_price' => (float)$data['unit_price'],
            ':cost_price' => isset($data['cost_price']) ? (float)$data['cost_price'] : null,
            ':quantity_on_hand' => (float)($data['quantity_on_hand'] ?? 0),
            ':is_default' => !empty($data['is_default']) ? 1 : 0,
            ':is_active' => !isset($data['is_active']) || $data['is_active'] ? 1 : 0,
        ]);

        // If this is set as default, unset others
        if (!empty($data['is_default'])) {
            $this->pdo->prepare("
                UPDATE pos_product_variants
                SET is_default = 0
                WHERE product_id = :product_id AND id != :variant_id
            ")->execute([
                ':product_id' => $productId,
                ':variant_id' => (int)$this->pdo->lastInsertId(),
            ]);
        }

        return (int)$this->pdo->lastInsertId();
    }

    public function updateProductVariant(int $variantId, array $data): void
    {
        $updates = [];
        $params = [':id' => $variantId];

        if (isset($data['variant_name'])) {
            $updates[] = 'variant_name = :variant_name';
            $params[':variant_name'] = trim($data['variant_name']);
        }
        if (isset($data['sku_suffix'])) {
            $updates[] = 'sku_suffix = :sku_suffix';
            $params[':sku_suffix'] = !empty($data['sku_suffix']) ? trim($data['sku_suffix']) : null;
        }
        if (isset($data['barcode'])) {
            $updates[] = 'barcode = :barcode';
            $params[':barcode'] = !empty($data['barcode']) ? trim($data['barcode']) : null;
        }
        if (isset($data['unit_price'])) {
            $updates[] = 'unit_price = :unit_price';
            $params[':unit_price'] = (float)$data['unit_price'];
        }
        if (isset($data['cost_price'])) {
            $updates[] = 'cost_price = :cost_price';
            $params[':cost_price'] = $data['cost_price'] !== null ? (float)$data['cost_price'] : null;
        }
        if (isset($data['quantity_on_hand'])) {
            $updates[] = 'quantity_on_hand = :quantity_on_hand';
            $params[':quantity_on_hand'] = (float)$data['quantity_on_hand'];
        }
        if (isset($data['is_default'])) {
            $updates[] = 'is_default = :is_default';
            $params[':is_default'] = !empty($data['is_default']) ? 1 : 0;
        }
        if (isset($data['is_active'])) {
            $updates[] = 'is_active = :is_active';
            $params[':is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (empty($updates)) {
            return;
        }

        $sql = "UPDATE pos_product_variants SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // If set as default, unset others for the same product
        if (isset($data['is_default']) && !empty($data['is_default'])) {
            $variant = $this->getProductVariant($variantId);
            if ($variant) {
                $this->pdo->prepare("
                    UPDATE pos_product_variants
                    SET is_default = 0
                    WHERE product_id = :product_id AND id != :variant_id
                ")->execute([
                    ':product_id' => $variant['product_id'],
                    ':variant_id' => $variantId,
                ]);
            }
        }
    }

    public function getProductVariant(int $variantId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_product_variants WHERE id = :id");
        $stmt->execute([':id' => $variantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listProductVariants(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pos_product_variants
            WHERE product_id = :product_id
            ORDER BY is_default DESC, variant_name ASC
        ");
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteProductVariant(int $variantId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM pos_product_variants WHERE id = :id");
        $stmt->execute([':id' => $variantId]);
    }

    /* ---------- Loyalty Programs ---------- */

    public function getLoyaltyProgram(int $programId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_loyalty_programs WHERE id = :id");
        $stmt->execute([':id' => $programId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getActiveLoyaltyProgram(): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_loyalty_programs WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCustomerLoyalty(int $customerId, ?int $programId = null): ?array
    {
        if ($programId) {
            $stmt = $this->pdo->prepare("SELECT * FROM pos_customer_loyalty WHERE customer_id = :customer_id AND program_id = :program_id");
            $stmt->execute([':customer_id' => $customerId, ':program_id' => $programId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM pos_customer_loyalty WHERE customer_id = :customer_id ORDER BY program_id ASC LIMIT 1");
            $stmt->execute([':customer_id' => $customerId]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function earnLoyaltyPoints(int $customerId, int $programId, int $points, ?int $saleId = null, ?string $description = null): void
    {
        $this->pdo->beginTransaction();
        try {
            // Get or create customer loyalty record
            $loyalty = $this->getCustomerLoyalty($customerId, $programId);
            if (!$loyalty) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pos_customer_loyalty (customer_id, program_id, points_balance, points_earned_lifetime)
                    VALUES (:customer_id, :program_id, :points, :points)
                ");
                $stmt->execute([
                    ':customer_id' => $customerId,
                    ':program_id' => $programId,
                    ':points' => $points,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE pos_customer_loyalty
                    SET points_balance = points_balance + :points,
                        points_earned_lifetime = points_earned_lifetime + :points,
                        last_activity = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $loyalty['id'],
                    ':points' => $points,
                ]);
            }

            // Record transaction
            $txnStmt = $this->pdo->prepare("
                INSERT INTO pos_loyalty_transactions
                    (customer_id, program_id, sale_id, transaction_type, points, description)
                VALUES
                    (:customer_id, :program_id, :sale_id, 'earned', :points, :description)
            ");
            $txnStmt->execute([
                ':customer_id' => $customerId,
                ':program_id' => $programId,
                ':sale_id' => $saleId,
                ':points' => $points,
                ':description' => $description ?? "Points earned from sale",
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function redeemLoyaltyPoints(int $customerId, int $programId, int $points, ?int $saleId = null, ?string $description = null): float
    {
        $this->pdo->beginTransaction();
        try {
            $loyalty = $this->getCustomerLoyalty($customerId, $programId);
            if (!$loyalty || $loyalty['points_balance'] < $points) {
                throw new InvalidArgumentException('Insufficient loyalty points');
            }

            $program = $this->getLoyaltyProgram($programId);
            $currencyValue = $points * (float)$program['currency_per_point'];

            // Update balance
            $stmt = $this->pdo->prepare("
                UPDATE pos_customer_loyalty
                SET points_balance = points_balance - :points,
                    points_redeemed_lifetime = points_redeemed_lifetime + :points,
                    last_activity = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $loyalty['id'],
                ':points' => $points,
            ]);

            // Record transaction
            $txnStmt = $this->pdo->prepare("
                INSERT INTO pos_loyalty_transactions
                    (customer_id, program_id, sale_id, transaction_type, points, currency_value, description)
                VALUES
                    (:customer_id, :program_id, :sale_id, 'redeemed', :points, :currency_value, :description)
            ");
            $txnStmt->execute([
                ':customer_id' => $customerId,
                ':program_id' => $programId,
                ':sale_id' => $saleId,
                ':points' => $points,
                ':currency_value' => $currencyValue,
                ':description' => $description ?? "Points redeemed",
            ]);

            $this->pdo->commit();
            return $currencyValue;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* ---------- Gift Cards ---------- */

    public function createGiftCard(array $data): array
    {
        $cardNumber = $this->generateGiftCardNumber();
        $pin = !empty($data['pin']) ? $data['pin'] : $this->generateGiftCardPin();

        $stmt = $this->pdo->prepare("
            INSERT INTO pos_gift_cards
                (card_number, pin, initial_balance, current_balance, purchased_by_customer_id, purchased_sale_id, expires_at, notes)
            VALUES
                (:card_number, :pin, :initial_balance, :current_balance, :purchased_by_customer_id, :purchased_sale_id, :expires_at, :notes)
        ");
        $stmt->execute([
            ':card_number' => $cardNumber,
            ':pin' => $pin,
            ':initial_balance' => (float)$data['initial_balance'],
            ':current_balance' => (float)$data['initial_balance'],
            ':purchased_by_customer_id' => !empty($data['purchased_by_customer_id']) ? (int)$data['purchased_by_customer_id'] : null,
            ':purchased_sale_id' => !empty($data['purchased_sale_id']) ? (int)$data['purchased_sale_id'] : null,
            ':expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            ':notes' => !empty($data['notes']) ? trim($data['notes']) : null,
        ]);

        $cardId = (int)$this->pdo->lastInsertId();

        // Record initial transaction
        $this->recordGiftCardTransaction($cardId, null, 'purchase', (float)$data['initial_balance'], 0, (float)$data['initial_balance']);

        return $this->getGiftCard($cardId);
    }

    private function generateGiftCardNumber(): string
    {
        do {
            $number = 'GC-' . strtoupper(bin2hex(random_bytes(4))) . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pos_gift_cards WHERE card_number = :number");
            $stmt->execute([':number' => $number]);
        } while ($stmt->fetchColumn() > 0);
        return $number;
    }

    private function generateGiftCardPin(): string
    {
        return str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function getGiftCard(int $cardId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_gift_cards WHERE id = :id");
        $stmt->execute([':id' => $cardId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getGiftCardByNumber(string $cardNumber): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_gift_cards WHERE card_number = :number");
        $stmt->execute([':number' => $cardNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function redeemGiftCard(string $cardNumber, float $amount, ?int $saleId = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $card = $this->getGiftCardByNumber($cardNumber);
            if (!$card) {
                throw new InvalidArgumentException('Gift card not found');
            }
            if ($card['status'] !== 'active') {
                throw new InvalidArgumentException('Gift card is not active');
            }
            if ($card['current_balance'] < $amount) {
                throw new InvalidArgumentException('Insufficient gift card balance');
            }

            $balanceBefore = (float)$card['current_balance'];
            $balanceAfter = $balanceBefore - $amount;

            $stmt = $this->pdo->prepare("
                UPDATE pos_gift_cards
                SET current_balance = :balance_after,
                    last_used_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $card['id'],
                ':balance_after' => $balanceAfter,
            ]);

            $this->recordGiftCardTransaction($card['id'], $saleId, 'redemption', $amount, $balanceBefore, $balanceAfter);

            $this->pdo->commit();
            return $this->getGiftCard($card['id']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function recordGiftCardTransaction(int $cardId, ?int $saleId, string $type, float $amount, float $balanceBefore, float $balanceAfter): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_gift_card_transactions
                (gift_card_id, sale_id, transaction_type, amount, balance_before, balance_after)
            VALUES
                (:gift_card_id, :sale_id, :transaction_type, :amount, :balance_before, :balance_after)
        ");
        $stmt->execute([
            ':gift_card_id' => $cardId,
            ':sale_id' => $saleId,
            ':transaction_type' => $type,
            ':amount' => $amount,
            ':balance_before' => $balanceBefore,
            ':balance_after' => $balanceAfter,
        ]);
    }

    /* ---------- Tax Management ---------- */

    public function getTaxRules(?int $categoryId = null, ?int $productId = null, ?int $customerId = null): array
    {
        $where = ["is_active = 1"];
        $params = [];

        if ($productId) {
            $where[] = "(applies_to = 'product' AND product_id = :product_id) OR (applies_to = 'all')";
            $params[':product_id'] = $productId;
        } elseif ($categoryId) {
            $where[] = "(applies_to = 'category' AND category_id = :category_id) OR (applies_to = 'all')";
            $params[':category_id'] = $categoryId;
        } elseif ($customerId) {
            $where[] = "(applies_to = 'customer' AND customer_id = :customer_id) OR (applies_to = 'all')";
            $params[':customer_id'] = $customerId;
        } else {
            $where[] = "applies_to = 'all'";
        }

        $sql = "SELECT * FROM pos_tax_rules WHERE " . implode(' OR ', $where) . " ORDER BY priority DESC, id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calculateTax(float $amount, ?int $categoryId = null, ?int $productId = null, ?int $customerId = null): float
    {
        // Check if customer is tax exempt
        if ($customerId) {
            $customerStmt = $this->pdo->prepare("SELECT tax_exempt, tax_exemption_expiry FROM clients WHERE id = :customer_id");
            $customerStmt->execute([':customer_id' => $customerId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer && !empty($customer['tax_exempt'])) {
                // Check if exemption is still valid
                if (empty($customer['tax_exemption_expiry']) || strtotime($customer['tax_exemption_expiry']) >= time()) {
                    return 0; // Customer is tax exempt
                }
            }
        }
        
        $rules = $this->getTaxRules($categoryId, $productId, $customerId);
        if (empty($rules)) {
            return 0;
        }

        // Use the highest priority rule
        $rule = $rules[0];
        if ($rule['tax_type'] === 'percentage') {
            return $amount * ((float)$rule['tax_rate'] / 100);
        } else {
            return (float)$rule['tax_rate'];
        }
    }
    
    /**
     * Check if customer is tax exempt
     */
    public function isCustomerTaxExempt(?int $customerId): bool
    {
        if (!$customerId) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("SELECT tax_exempt, tax_exemption_expiry FROM clients WHERE id = :customer_id");
        $stmt->execute([':customer_id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer || empty($customer['tax_exempt'])) {
            return false;
        }
        
        // Check if exemption is still valid
        if (!empty($customer['tax_exemption_expiry'])) {
            return strtotime($customer['tax_exemption_expiry']) >= time();
        }
        
        return true;
    }
    
    /**
     * Check if discount requires manager approval
     */
    public function requiresDiscountApproval(float $discountAmount, float $subtotal): bool
    {
        // Get approval threshold from system config (default 500)
        $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'pos_discount_approval_threshold' LIMIT 1");
        $stmt->execute();
        $threshold = $stmt->fetchColumn();
        $threshold = $threshold ? (float)$threshold : 500;
        
        // Also check percentage threshold (default 20%)
        $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'pos_discount_approval_percentage' LIMIT 1");
        $stmt->execute();
        $percentageThreshold = $stmt->fetchColumn();
        $percentageThreshold = $percentageThreshold ? (float)$percentageThreshold : 20;
        
        // Require approval if discount exceeds absolute threshold OR percentage threshold
        return $discountAmount >= $threshold || ($subtotal > 0 && ($discountAmount / $subtotal * 100) >= $percentageThreshold);
    }
    
    /**
     * Check if price override requires manager approval
     */
    public function requiresPriceOverrideApproval(float $originalPrice, float $overridePrice): bool
    {
        // Get approval threshold from system config (default 10% difference)
        $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'pos_price_override_approval_threshold' LIMIT 1");
        $stmt->execute();
        $threshold = $stmt->fetchColumn();
        $threshold = $threshold ? (float)$threshold : 10; // percentage
        
        if ($originalPrice <= 0) {
            return true; // Always require approval for zero/negative prices
        }
        
        $differencePercent = abs(($overridePrice - $originalPrice) / $originalPrice * 100);
        return $differencePercent >= $threshold;
    }
    
    /**
     * Create pending approval request
     */
    public function createPendingApproval(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_pending_approvals
                (sale_id, approval_type, requested_by, status, reason, metadata)
            VALUES
                (:sale_id, :approval_type, :requested_by, 'pending', :reason, :metadata)
        ");
        
        $metadata = !empty($data['metadata']) ? json_encode($data['metadata']) : null;
        
        $stmt->execute([
            ':sale_id' => $data['sale_id'] ?? null,
            ':approval_type' => $data['approval_type'],
            ':requested_by' => $data['requested_by'],
            ':reason' => $data['reason'] ?? null,
            ':metadata' => $metadata,
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Approve pending approval
     */
    public function approvePendingApproval(int $approvalId, int $approvedBy, ?string $notes = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_pending_approvals
            SET status = 'approved',
                approved_by = :approved_by,
                approved_at = NOW(),
                approval_notes = :approval_notes
            WHERE id = :approval_id AND status = 'pending'
        ");
        
        return $stmt->execute([
            ':approval_id' => $approvalId,
            ':approved_by' => $approvedBy,
            ':approval_notes' => $notes,
        ]);
    }
    
    /**
     * Reject pending approval
     */
    public function rejectPendingApproval(int $approvalId, int $rejectedBy, ?string $notes = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_pending_approvals
            SET status = 'rejected',
                rejected_by = :rejected_by,
                rejected_at = NOW(),
                approval_notes = :approval_notes
            WHERE id = :approval_id AND status = 'pending'
        ");
        
        return $stmt->execute([
            ':approval_id' => $approvalId,
            ':rejected_by' => $rejectedBy,
            ':approval_notes' => $notes,
        ]);
    }
    
    /**
     * Get pending approvals
     */
    public function getPendingApprovals(?string $approvalType = null, int $limit = 50): array
    {
        $sql = "
            SELECT pa.*, 
                   u_requested.full_name AS requested_by_name,
                   u_approved.full_name AS approved_by_name,
                   u_rejected.full_name AS rejected_by_name,
                   s.sale_number
            FROM pos_pending_approvals pa
            LEFT JOIN users u_requested ON pa.requested_by = u_requested.id
            LEFT JOIN users u_approved ON pa.approved_by = u_approved.id
            LEFT JOIN users u_rejected ON pa.rejected_by = u_rejected.id
            LEFT JOIN pos_sales s ON pa.sale_id = s.id
            WHERE pa.status = 'pending'
        ";
        
        $params = [];
        if ($approvalType) {
            $sql .= " AND pa.approval_type = :approval_type";
            $params[':approval_type'] = $approvalType;
        }
        
        $sql .= " ORDER BY pa.requested_at DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode metadata JSON
        foreach ($approvals as &$approval) {
            if (!empty($approval['metadata'])) {
                $approval['metadata'] = json_decode($approval['metadata'], true);
            }
        }
        
        return $approvals;
    }

    /* ---------- Promotions ---------- */

    public function getPromotion(string $code): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pos_promotions
            WHERE promotion_code = :code
              AND is_active = 1
              AND (start_date IS NULL OR start_date <= NOW())
              AND (end_date IS NULL OR end_date >= NOW())
        ");
        $stmt->execute([':code' => $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function validatePromotion(string $code, float $subtotal, ?int $customerId = null, ?int $categoryId = null, ?int $productId = null): ?array
    {
        $promotion = $this->getPromotion($code);
        if (!$promotion) {
            return null;
        }

        // Check usage limit
        if ($promotion['usage_limit'] && $promotion['usage_count'] >= $promotion['usage_limit']) {
            return null;
        }

        // Check minimum purchase
        if ($promotion['min_purchase_amount'] && $subtotal < (float)$promotion['min_purchase_amount']) {
            return null;
        }

        // Check applicable to
        if ($promotion['applicable_to'] === 'customer' && (!$customerId || $promotion['customer_id'] != $customerId)) {
            return null;
        }
        if ($promotion['applicable_to'] === 'category' && (!$categoryId || $promotion['category_id'] != $categoryId)) {
            return null;
        }
        if ($promotion['applicable_to'] === 'product' && (!$productId || $promotion['product_id'] != $productId)) {
            return null;
        }

        // Check per-customer limit
        if ($promotion['usage_limit_per_customer'] && $customerId) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM pos_promotion_usage
                WHERE promotion_id = :promo_id AND customer_id = :customer_id
            ");
            $stmt->execute([':promo_id' => $promotion['id'], ':customer_id' => $customerId]);
            if ($stmt->fetchColumn() >= $promotion['usage_limit_per_customer']) {
                return null;
            }
        }

        return $promotion;
    }

    public function applyPromotion(int $promotionId, float $subtotal): float
    {
        $promotion = $this->getPromotionById($promotionId);
        if (!$promotion) {
            return 0;
        }

        $discount = 0;
        if ($promotion['discount_type'] === 'percentage') {
            $discount = $subtotal * ((float)$promotion['discount_value'] / 100);
            if ($promotion['max_discount_amount']) {
                $discount = min($discount, (float)$promotion['max_discount_amount']);
            }
        } elseif ($promotion['discount_type'] === 'fixed') {
            $discount = (float)$promotion['discount_value'];
        }

        return $discount;
    }

    public function getPromotionById(int $promotionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pos_promotions WHERE id = :id");
        $stmt->execute([':id' => $promotionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function recordPromotionUsage(int $promotionId, int $saleId, ?int $customerId, float $discountAmount): void
    {
        $this->pdo->beginTransaction();
        try {
            // Record usage
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_promotion_usage (promotion_id, sale_id, customer_id, discount_amount)
                VALUES (:promotion_id, :sale_id, :customer_id, :discount_amount)
            ");
            $stmt->execute([
                ':promotion_id' => $promotionId,
                ':sale_id' => $saleId,
                ':customer_id' => $customerId,
                ':discount_amount' => $discountAmount,
            ]);

            // Update usage count
            $this->pdo->prepare("
                UPDATE pos_promotions SET usage_count = usage_count + 1 WHERE id = :id
            ")->execute([':id' => $promotionId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* ---------- Email Receipts ---------- */

    public function sendEmailReceipt(int $saleId, string $emailAddress, ?int $customerId = null, ?array $templateOptions = null): array
    {
        $sale = $this->getSaleForAccounting($saleId);
        if (!$sale) {
            throw new InvalidArgumentException('Sale not found');
        }

        // Get receipt template settings
        $templateOptions = $templateOptions ?? $this->getReceiptTemplateOptions();
        
        // Generate receipt HTML using template
        $receiptHtml = $this->generateEmailReceiptHtml($sale, $templateOptions);
        $receiptText = $this->generateEmailReceiptText($sale, $templateOptions);
        
        // Get company info
        $companyName = $this->getCompanyConfig('company_name') ?: 'ABBIS';
        $companyEmail = $this->getCompanyConfig('company_email') ?: 'noreply@abbis.africa';
        $companyPhone = $this->getCompanyConfig('company_phone') ?: '';
        $companyAddress = $this->getCompanyConfig('company_address') ?: '';
        
        $subject = $templateOptions['email_subject'] ?? "Receipt for Sale {$sale['sale_number']}";
        $subject = str_replace(['{{sale_number}}', '{{company_name}}'], [$sale['sale_number'], $companyName], $subject);

        // Store email receipt record
        $emailReceiptId = null;
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_email_receipts
                    (sale_id, customer_id, email_address, email_subject, email_body_html, email_body_text, status)
                VALUES
                    (:sale_id, :customer_id, :email_address, :email_subject, :email_body_html, :email_body_text, 'pending')
            ");
            $stmt->execute([
                ':sale_id' => $saleId,
                ':customer_id' => $customerId,
                ':email_address' => $emailAddress,
                ':email_subject' => $subject,
                ':email_body_html' => $receiptHtml,
                ':email_body_text' => $receiptText,
            ]);
            $emailReceiptId = (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('[POS Email Receipt] Failed to create email receipt record: ' . $e->getMessage());
        }

        // Actually send the email
        $emailSent = false;
        $errorMessage = null;
        try {
            require_once __DIR__ . '/../email.php';
            $email = new Email();
            
            $result = $email->send(
                $emailAddress,
                $subject,
                $receiptHtml,
                [
                    'from_email' => $companyEmail,
                    'from_name' => $companyName,
                    'reply_to' => $companyEmail,
                    'plain_body' => $receiptText,
                ]
            );
            
            $emailSent = (bool)$result;
            
            // Update email receipt status
            if ($emailReceiptId) {
                $status = $emailSent ? 'sent' : 'failed';
                $updateStmt = $this->pdo->prepare("
                    UPDATE pos_email_receipts
                    SET status = :status,
                        sent_at = CASE WHEN :status = 'sent' THEN NOW() ELSE NULL END,
                        error_message = :error_message
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    ':status' => $status,
                    ':error_message' => $emailSent ? null : ($errorMessage ?? 'Email sending failed'),
                    ':id' => $emailReceiptId,
                ]);
            }
            
            // Update sale email receipt sent flag
            if ($emailSent) {
                $this->pdo->prepare("
                    UPDATE pos_sales SET email_receipt_sent = 1 WHERE id = :sale_id
                ")->execute([':sale_id' => $saleId]);
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            error_log('[POS Email Receipt] Failed to send email: ' . $errorMessage);
            
            if ($emailReceiptId) {
                $this->pdo->prepare("
                    UPDATE pos_email_receipts
                    SET status = 'failed',
                        error_message = :error_message
                    WHERE id = :id
                ")->execute([
                    ':error_message' => $errorMessage,
                    ':id' => $emailReceiptId,
                ]);
            }
        }

        return [
            'success' => $emailSent,
            'email_receipt_id' => $emailReceiptId,
            'error' => $errorMessage,
        ];
    }

    private function generateEmailReceiptHtml(array $sale, array $templateOptions = []): string
    {
        // Get company info
        $companyName = $this->getCompanyConfig('company_name') ?: 'ABBIS';
        $companyEmail = $this->getCompanyConfig('company_email') ?: '';
        $companyPhone = $this->getCompanyConfig('company_phone') ?: '';
        $companyAddress = $this->getCompanyConfig('company_address') ?: '';
        $companyLogo = $this->getCompanyConfig('company_logo') ?: '';
        $currency = $this->getCompanyConfig('currency') ?: 'GHS';
        $receiptFooter = $templateOptions['footer_text'] ?? $this->getCompanyConfig('receipt_footer') ?: '';
        $receiptTerms = $templateOptions['terms_text'] ?? $this->getCompanyConfig('receipt_terms') ?: '';
        
        // Get store info
        $storeName = $sale['store_name'] ?? 'Store';
        $storeCode = $sale['store_code'] ?? '';
        
        // Build logo HTML
        $logoHtml = '';
        if ($companyLogo && file_exists(ROOT_PATH . '/' . $companyLogo)) {
            $logoUrl = app_base_path() . '/' . $companyLogo;
            $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '" style="max-width: 200px; max-height: 100px; margin-bottom: 20px;">';
        }
        
        // Format currency
        $formatCurrency = function($amount) use ($currency) {
            return $currency . ' ' . number_format((float)$amount, 2, '.', ',');
        };
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - ' . htmlspecialchars($sale['sale_number'], ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .receipt-container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 10px; color: #1a1a1a; }
        .company-info { font-size: 12px; color: #666; margin-top: 10px; }
        .receipt-details { margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-label { font-weight: 600; color: #555; }
        .detail-value { color: #333; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; }
        .items-table td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        .items-table tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { margin-top: 20px; padding-top: 20px; border-top: 2px solid #333; }
        .total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 16px; }
        .total-row.grand-total { font-size: 20px; font-weight: bold; border-top: 2px solid #333; padding-top: 12px; margin-top: 12px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; text-align: center; }
        .qr-code { text-align: center; margin: 20px 0; }
        @media print { body { background: #fff; } .receipt-container { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            ' . $logoHtml . '
            <div class="company-name">' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</div>';
        
        if ($companyAddress || $companyPhone || $companyEmail) {
            $html .= '<div class="company-info">';
            if ($companyAddress) $html .= htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8') . '<br>';
            if ($companyPhone) $html .= 'Phone: ' . htmlspecialchars($companyPhone, ENT_QUOTES, 'UTF-8') . '<br>';
            if ($companyEmail) $html .= 'Email: ' . htmlspecialchars($companyEmail, ENT_QUOTES, 'UTF-8');
            $html .= '</div>';
        }
        
        $html .= '</div>
        
        <div class="receipt-details">
            <div class="detail-row">
                <span class="detail-label">Receipt #:</span>
                <span class="detail-value">' . htmlspecialchars($sale['sale_number'], ENT_QUOTES, 'UTF-8') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">' . date('F j, Y g:i A', strtotime($sale['sale_timestamp'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Store:</span>
                <span class="detail-value">' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . '</span>
            </div>';
        
        if (!empty($sale['customer_name'])) {
            $html .= '<div class="detail-row">
                <span class="detail-label">Customer:</span>
                <span class="detail-value">' . htmlspecialchars($sale['customer_name'], ENT_QUOTES, 'UTF-8') . '</span>
            </div>';
        }
        
        $html .= '</div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($sale['items'] as $item) {
            $itemName = htmlspecialchars($item['name'] ?? $item['product_name'] ?? 'Item', ENT_QUOTES, 'UTF-8');
            $quantity = number_format((float)($item['quantity'] ?? 0), 2, '.', '');
            $unitPrice = $formatCurrency($item['unit_price'] ?? 0);
            $lineTotal = $formatCurrency($item['line_total'] ?? 0);
            
            $html .= '<tr>
                <td>' . $itemName . '</td>
                <td class="text-right">' . $quantity . '</td>
                <td class="text-right">' . $unitPrice . '</td>
                <td class="text-right">' . $lineTotal . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>' . $formatCurrency($sale['subtotal_amount'] ?? 0) . '</span>
            </div>';
        
        if (($sale['discount_total'] ?? 0) > 0) {
            $html .= '<div class="total-row" style="color: #28a745;">
                <span>Discount:</span>
                <span>-' . $formatCurrency($sale['discount_total']) . '</span>
            </div>';
        }
        
        if (($sale['tax_total'] ?? 0) > 0) {
            $html .= '<div class="total-row">
                <span>Tax:</span>
                <span>' . $formatCurrency($sale['tax_total']) . '</span>
            </div>';
        }
        
        $html .= '<div class="total-row grand-total">
                <span>Total:</span>
                <span>' . $formatCurrency($sale['total_amount'] ?? 0) . '</span>
            </div>';
        
        if (($sale['amount_paid'] ?? 0) > 0) {
            $html .= '<div class="total-row">
                <span>Amount Paid:</span>
                <span>' . $formatCurrency($sale['amount_paid']) . '</span>
            </div>';
            
            if (($sale['change_due'] ?? 0) > 0) {
                $html .= '<div class="total-row">
                    <span>Change:</span>
                    <span>' . $formatCurrency($sale['change_due']) . '</span>
                </div>';
            }
        }
        
        // Payment methods
        if (!empty($sale['payments'])) {
            $html .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                <div style="font-weight: 600; margin-bottom: 8px;">Payment Methods:</div>';
            foreach ($sale['payments'] as $payment) {
                $method = ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? ''));
                $amount = $formatCurrency($payment['amount'] ?? 0);
                $html .= '<div class="total-row" style="font-size: 14px;">
                    <span>' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ':</span>
                    <span>' . $amount . '</span>
                </div>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // QR Code placeholder (can be implemented with a QR code library)
        if (!empty($templateOptions['show_qr_code']) && $templateOptions['show_qr_code']) {
            $qrData = json_encode([
                'sale_number' => $sale['sale_number'],
                'total' => $sale['total_amount'],
                'date' => $sale['sale_timestamp'],
            ]);
            $html .= '<div class="qr-code">
                <!-- QR Code can be generated here using a QR code library -->
                <div style="padding: 20px; background: #f0f0f0; border-radius: 4px; display: inline-block;">
                    <small>QR Code: ' . htmlspecialchars($sale['sale_number'], ENT_QUOTES, 'UTF-8') . '</small>
                </div>
            </div>';
        }
        
        // Footer and terms
        if ($receiptFooter || $receiptTerms) {
            $html .= '<div class="footer">';
            if ($receiptFooter) {
                $html .= '<p>' . nl2br(htmlspecialchars($receiptFooter, ENT_QUOTES, 'UTF-8')) . '</p>';
            }
            if ($receiptTerms) {
                $html .= '<p style="margin-top: 10px; font-size: 11px; color: #999;">' . nl2br(htmlspecialchars($receiptTerms, ENT_QUOTES, 'UTF-8')) . '</p>';
            }
            $html .= '<p style="margin-top: 15px; color: #999;">Thank you for your business!</p>';
            $html .= '</div>';
        } else {
            $html .= '<div class="footer">
                <p>Thank you for your business!</p>
            </div>';
        }
        
        $html .= '</div>
</body>
</html>';
        
        return $html;
    }

    private function generateEmailReceiptText(array $sale, array $templateOptions = []): string
    {
        $companyName = $this->getCompanyConfig('company_name') ?: 'ABBIS';
        $currency = $this->getCompanyConfig('currency') ?: 'GHS';
        $storeName = $sale['store_name'] ?? 'Store';
        
        $formatCurrency = function($amount) use ($currency) {
            return $currency . ' ' . number_format((float)$amount, 2, '.', ',');
        };
        
        $text = "RECEIPT\n";
        $text .= str_repeat('=', 50) . "\n\n";
        $text .= $companyName . "\n";
        $text .= "Receipt #: " . $sale['sale_number'] . "\n";
        $text .= "Date: " . date('F j, Y g:i A', strtotime($sale['sale_timestamp'])) . "\n";
        $text .= "Store: " . $storeName . "\n";
        if (!empty($sale['customer_name'])) {
            $text .= "Customer: " . $sale['customer_name'] . "\n";
        }
        $text .= "\n" . str_repeat('-', 50) . "\n";
        $text .= "ITEMS:\n";
        $text .= str_repeat('-', 50) . "\n";
        
        foreach ($sale['items'] as $item) {
            $itemName = $item['name'] ?? $item['product_name'] ?? 'Item';
            $quantity = number_format((float)($item['quantity'] ?? 0), 2, '.', '');
            $unitPrice = $formatCurrency($item['unit_price'] ?? 0);
            $lineTotal = $formatCurrency($item['line_total'] ?? 0);
            
            $text .= sprintf("%-30s %6s @ %10s %12s\n", 
                substr($itemName, 0, 30), 
                $quantity, 
                $unitPrice, 
                $lineTotal
            );
        }
        
        $text .= str_repeat('-', 50) . "\n";
        $text .= "Subtotal: " . str_pad($formatCurrency($sale['subtotal_amount'] ?? 0), 30, ' ', STR_PAD_LEFT) . "\n";
        
        if (($sale['discount_total'] ?? 0) > 0) {
            $text .= "Discount: " . str_pad('-' . $formatCurrency($sale['discount_total']), 30, ' ', STR_PAD_LEFT) . "\n";
        }
        
        if (($sale['tax_total'] ?? 0) > 0) {
            $text .= "Tax: " . str_pad($formatCurrency($sale['tax_total']), 30, ' ', STR_PAD_LEFT) . "\n";
        }
        
        $text .= "TOTAL: " . str_pad($formatCurrency($sale['total_amount'] ?? 0), 30, ' ', STR_PAD_LEFT) . "\n";
        
        if (($sale['amount_paid'] ?? 0) > 0) {
            $text .= "Amount Paid: " . str_pad($formatCurrency($sale['amount_paid']), 30, ' ', STR_PAD_LEFT) . "\n";
            if (($sale['change_due'] ?? 0) > 0) {
                $text .= "Change: " . str_pad($formatCurrency($sale['change_due']), 30, ' ', STR_PAD_LEFT) . "\n";
            }
        }
        
        $text .= "\n" . str_repeat('=', 50) . "\n";
        $text .= "Thank you for your business!\n";
        
        return $text;
    }

    private function getReceiptTemplateOptions(): array
    {
        // Get template options from system config
        $options = [
            'email_subject' => $this->getCompanyConfig('receipt_email_subject') ?: 'Receipt for Sale {{sale_number}}',
            'footer_text' => $this->getCompanyConfig('receipt_footer') ?: '',
            'terms_text' => $this->getCompanyConfig('receipt_terms') ?: '',
            'show_qr_code' => (bool)($this->getCompanyConfig('receipt_show_qr') ?? false),
        ];
        
        return $options;
    }

    private function getCompanyConfig(string $key): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = :key LIMIT 1");
            $stmt->execute([':key' => $key]);
            $value = $stmt->fetchColumn();
            return $value ? trim($value) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: strtolower(bin2hex(random_bytes(4)));
    }
}


