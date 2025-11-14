<?php
/**
 * Material Store Service
 * Manages materials in the Material Store (separate from POS/Material Shop)
 * 
 * Flow:
 * 1. POS (Material Shop) → Material Store: Transfer materials
 * 2. Material Store → Field Work: Use materials (via field reports)
 * 3. Material Store → POS: Return unused materials
 */
require_once __DIR__ . '/UnifiedInventoryService.php';
require_once __DIR__ . '/MaterialsService.php';

class MaterialStoreService
{
    private $pdo;
    private $inventoryService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->inventoryService = new UnifiedInventoryService($pdo);
    }

    /**
     * Transfer materials from POS (Material Shop) to Material Store
     * Decreases POS inventory, increases Material Store inventory
     */
    public function transferFromPos(string $materialType, float $quantity, int $userId, array $options = [], bool $skipTransaction = false): array
    {
        if (!$skipTransaction && !$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
        
        try {
            // Step 1: Get material info
            $materialStmt = $this->pdo->prepare("
                SELECT material_name, unit_cost 
                FROM materials_inventory 
                WHERE material_type = ?
            ");
            $materialStmt->execute([$materialType]);
            $material = $materialStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$material) {
                throw new Exception("Material type '{$materialType}' not found");
            }

            // Step 2: Decrease POS inventory (via catalog)
            $mappingStmt = $this->pdo->prepare("
                SELECT catalog_item_id FROM pos_material_mappings 
                WHERE material_type = ?
            ");
            $mappingStmt->execute([$materialType]);
            $mapping = $mappingStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mapping && $mapping['catalog_item_id']) {
                $this->inventoryService->updateCatalogStock(
                    (int)$mapping['catalog_item_id'],
                    -$quantity, // Negative = decrease POS
                    "Transfer to Material Store: {$materialType}"
                );
            }

            // Step 3: Increase Material Store inventory
            $this->addToStore($materialType, $quantity, $material['unit_cost'], $userId, [
                'transaction_type' => 'transfer_from_pos',
                'reference_type' => $options['reference_type'] ?? 'pos_transfer',
                'reference_id' => $options['reference_id'] ?? null,
                'remarks' => $options['remarks'] ?? "Transferred from POS/Material Shop"
            ]);

            if (!$skipTransaction && !$this->pdo->inTransaction()) {
                // Only commit if we started the transaction
            } else if ($skipTransaction) {
                // Don't commit, parent transaction will handle it
            } else {
                $this->pdo->commit();
            }
            
            return [
                'success' => true,
                'material_type' => $materialType,
                'quantity_transferred' => $quantity
            ];
        } catch (Exception $e) {
            if (!$skipTransaction) {
                $this->pdo->rollBack();
            }
            error_log('[MaterialStoreService] Transfer failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Use materials in field work (from field report)
     * Decreases Material Store inventory
     */
    public function useInFieldWork(int $fieldReportId, array $materialsUsed, int $userId): array
    {
        $this->pdo->beginTransaction();
        
        try {
            $results = [];
            $totalValue = 0;

            // Map field report field names to material types
            $materialMapping = [
                'screen_pipe' => 'screen_pipes_used',
                'plain_pipe' => 'plain_pipes_used',
                'gravel' => 'gravel_used'
            ];
            
            foreach ($materialMapping as $materialType => $fieldName) {
                $quantity = floatval($materialsUsed[$fieldName] ?? $materialsUsed[$materialType . '_used'] ?? 0);
                
                if ($quantity <= 0) {
                    continue;
                }

                // Get current store inventory
                $storeStmt = $this->pdo->prepare("
                    SELECT quantity_remaining, unit_cost 
                    FROM material_store_inventory 
                    WHERE material_type = ?
                ");
                $storeStmt->execute([$materialType]);
                $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$store) {
                    throw new Exception("Material '{$materialType}' not available in Material Store");
                }
                
                if ($store['quantity_remaining'] < $quantity) {
                    throw new Exception("Insufficient {$materialType} in Material Store. Available: {$store['quantity_remaining']}, Required: {$quantity}");
                }

                // Deduct from Material Store
                $this->deductFromStore($materialType, $quantity, $userId, [
                    'transaction_type' => 'usage_in_field',
                    'reference_type' => 'field_report',
                    'reference_id' => $fieldReportId,
                    'remarks' => "Used in field work - Report #{$fieldReportId}"
                ]);

                // Calculate value
                $materialValue = $quantity * floatval($store['unit_cost']);
                $totalValue += $materialValue;

                // Calculate remaining
                $remaining = $store['quantity_remaining'] - $quantity;

                $results[$materialType] = [
                    'used' => $quantity,
                    'remaining' => $remaining,
                    'value' => $materialValue
                ];
            }

            // Update field report with remaining quantities and value
            $updateStmt = $this->pdo->prepare("
                UPDATE field_reports 
                SET screen_pipes_remaining = ?,
                    plain_pipes_remaining = ?,
                    gravel_remaining = ?,
                    materials_value_used = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $results['screen_pipe']['remaining'] ?? null,
                $results['plain_pipe']['remaining'] ?? null,
                $results['gravel']['remaining'] ?? null,
                $totalValue,
                $fieldReportId
            ]);

            $this->pdo->commit();
            
            return [
                'success' => true,
                'materials' => $results,
                'total_value' => $totalValue
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[MaterialStoreService] Field work usage failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Return materials from Material Store to POS
     * Decreases Material Store, increases POS
     */
    public function returnToPos(string $materialType, float $quantity, int $userId, array $options = []): array
    {
        $this->pdo->beginTransaction();
        
        try {
            // Check Material Store has enough
            $storeStmt = $this->pdo->prepare("
                SELECT quantity_remaining, unit_cost 
                FROM material_store_inventory 
                WHERE material_type = ?
            ");
            $storeStmt->execute([$materialType]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store || $store['quantity_remaining'] < $quantity) {
                throw new Exception("Insufficient {$materialType} in Material Store to return");
            }

            // Deduct from Material Store
            $this->deductFromStore($materialType, $quantity, $userId, [
                'transaction_type' => 'return_to_pos',
                'reference_type' => $options['reference_type'] ?? 'material_return',
                'reference_id' => $options['reference_id'] ?? null,
                'remarks' => $options['remarks'] ?? "Returned to POS/Material Shop"
            ], true); // Mark as returned

            // Increase POS inventory (via catalog)
            $mappingStmt = $this->pdo->prepare("
                SELECT catalog_item_id FROM pos_material_mappings 
                WHERE material_type = ?
            ");
            $mappingStmt->execute([$materialType]);
            $mapping = $mappingStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mapping && $mapping['catalog_item_id']) {
                $this->inventoryService->updateCatalogStock(
                    (int)$mapping['catalog_item_id'],
                    $quantity, // Positive = increase POS
                    "Return from Material Store: {$materialType}"
                );
            }

            $this->pdo->commit();
            
            return [
                'success' => true,
                'material_type' => $materialType,
                'quantity_returned' => $quantity
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[MaterialStoreService] Return to POS failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add materials to Material Store
     */
    private function addToStore(string $materialType, float $quantity, float $unitCost, int $userId, array $options = []): void
    {
        // Ensure store inventory exists
        $checkStmt = $this->pdo->prepare("SELECT id FROM material_store_inventory WHERE material_type = ?");
        $checkStmt->execute([$materialType]);
        $exists = $checkStmt->fetchColumn();
        
        if (!$exists) {
            // Get material name
            $nameStmt = $this->pdo->prepare("SELECT material_name FROM materials_inventory WHERE material_type = ?");
            $nameStmt->execute([$materialType]);
            $materialName = $nameStmt->fetchColumn() ?: $materialType;
            
            $insertStmt = $this->pdo->prepare("
                INSERT INTO material_store_inventory 
                (material_type, material_name, quantity_received, quantity_remaining, unit_cost, total_value)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $materialType,
                $materialName,
                $quantity,
                $quantity,
                $unitCost,
                $quantity * $unitCost
            ]);
        } else {
            // Update existing
            $updateStmt = $this->pdo->prepare("
                UPDATE material_store_inventory 
                SET quantity_received = quantity_received + ?,
                    quantity_remaining = quantity_remaining + ?,
                    total_value = quantity_remaining * unit_cost,
                    last_updated = NOW()
                WHERE material_type = ?
            ");
            $updateStmt->execute([$quantity, $quantity, $materialType]);
        }

        // Log transaction
        $this->logTransaction($materialType, $options['transaction_type'] ?? 'transfer_from_pos', $quantity, $unitCost, $userId, $options);
    }

    /**
     * Deduct materials from Material Store
     */
    private function deductFromStore(string $materialType, float $quantity, int $userId, array $options = [], bool $isReturn = false): void
    {
        $updateFields = [
            'quantity_used = quantity_used + ?',
            'quantity_remaining = quantity_remaining - ?',
            'total_value = quantity_remaining * unit_cost',
            'last_updated = NOW()'
        ];
        
        if ($isReturn) {
            $updateFields[] = 'quantity_returned = quantity_returned + ?';
        }
        
        $sql = "UPDATE material_store_inventory SET " . implode(', ', $updateFields) . " WHERE material_type = ?";
        $params = [$quantity, $quantity];
        
        if ($isReturn) {
            $params[] = $quantity;
        }
        $params[] = $materialType;
        
        $updateStmt = $this->pdo->prepare($sql);
        $updateStmt->execute($params);

        // Get unit cost for logging
        $costStmt = $this->pdo->prepare("SELECT unit_cost FROM material_store_inventory WHERE material_type = ?");
        $costStmt->execute([$materialType]);
        $unitCost = $costStmt->fetchColumn() ?: 0;

        // Log transaction
        $this->logTransaction($materialType, $options['transaction_type'] ?? 'usage_in_field', -$quantity, $unitCost, $userId, $options);
    }

    /**
     * Log transaction
     */
    private function logTransaction(string $materialType, string $transactionType, float $quantity, float $unitCost, int $userId, array $options = []): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO material_store_transactions 
                (transaction_type, material_type, quantity, unit_cost, reference_type, reference_id, field_report_id, performed_by, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transactionType,
                $materialType,
                $quantity,
                $unitCost,
                $options['reference_type'] ?? null,
                $options['reference_id'] ?? null,
                $options['field_report_id'] ?? null,
                $userId,
                $options['remarks'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('[MaterialStoreService] Transaction log failed: ' . $e->getMessage());
        }
    }

    /**
     * Get Material Store inventory
     */
    public function getStoreInventory(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM material_store_inventory 
            ORDER BY material_type
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Material Store inventory for a specific material type
     */
    public function getMaterialStock(string $materialType): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM material_store_inventory 
            WHERE material_type = ?
        ");
        $stmt->execute([$materialType]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get Material Store transactions with filters
     */
    public function getTransactions(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['material_type'])) {
            $where[] = 'material_type = ?';
            $params[] = $filters['material_type'];
        }
        
        if (!empty($filters['transaction_type'])) {
            $where[] = 'transaction_type = ?';
            $params[] = $filters['transaction_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Check if users table has username column
        $userColumn = 'id';
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
            if ($checkStmt->rowCount() > 0) {
                $userColumn = 'username';
            } else {
                $checkStmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'name'");
                if ($checkStmt->rowCount() > 0) {
                    $userColumn = 'name';
                } else {
                    $checkStmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'full_name'");
                    if ($checkStmt->rowCount() > 0) {
                        $userColumn = 'full_name';
                    }
                }
            }
        } catch (PDOException $e) {
            // Users table might not exist, use id
        }
        
        // Select specific columns from transactions to avoid any column issues
        $sql = "
            SELECT 
                t.id,
                t.transaction_type,
                t.material_type,
                t.quantity,
                t.unit_cost,
                t.reference_type,
                t.reference_id,
                t.field_report_id,
                t.performed_by,
                t.remarks,
                t.created_at,
                COALESCE(u.{$userColumn}, CONCAT('User ', t.performed_by)) as performed_by_name,
                fr.report_id as field_report_code
            FROM material_store_transactions t
            LEFT JOIN users u ON t.performed_by = u.id
            LEFT JOIN field_reports fr ON t.field_report_id = fr.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.created_at DESC
            LIMIT 1000
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(float $thresholdPercent = 20.0): array
    {
        $stmt = $this->pdo->query("
            SELECT *,
                   CASE 
                       WHEN quantity_remaining = 0 THEN 'out_of_stock'
                       WHEN (quantity_remaining / NULLIF(quantity_received, 0)) * 100 <= ? THEN 'critical'
                       WHEN (quantity_remaining / NULLIF(quantity_received, 0)) * 100 <= ? THEN 'low'
                       ELSE 'ok'
                   END as stock_status
            FROM material_store_inventory
            WHERE quantity_received > 0
            HAVING stock_status IN ('out_of_stock', 'critical', 'low')
            ORDER BY 
                CASE stock_status
                    WHEN 'out_of_stock' THEN 1
                    WHEN 'critical' THEN 2
                    WHEN 'low' THEN 3
                END,
                quantity_remaining ASC
        ");
        $stmt->execute([$thresholdPercent * 0.5, $thresholdPercent]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get material usage analytics
     */
    public function getUsageAnalytics(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Usage by material type
        $usageStmt = $this->pdo->prepare("
            SELECT 
                material_type,
                SUM(CASE WHEN transaction_type = 'usage_in_field' THEN ABS(quantity) ELSE 0 END) as total_used,
                SUM(CASE WHEN transaction_type = 'transfer_from_pos' THEN quantity ELSE 0 END) as total_received,
                SUM(CASE WHEN transaction_type = 'return_to_pos' THEN ABS(quantity) ELSE 0 END) as total_returned,
                COUNT(CASE WHEN transaction_type = 'usage_in_field' THEN 1 END) as usage_count,
                COUNT(CASE WHEN transaction_type = 'transfer_from_pos' THEN 1 END) as transfer_count
            FROM material_store_transactions
            WHERE " . implode(' AND ', $where) . "
            GROUP BY material_type
        ");
        $usageStmt->execute($params);
        $usageByMaterial = $usageStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Usage by date
        $dailyStmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN transaction_type = 'usage_in_field' THEN ABS(quantity) ELSE 0 END) as daily_used,
                COUNT(CASE WHEN transaction_type = 'usage_in_field' THEN 1 END) as daily_usage_count
            FROM material_store_transactions
            WHERE " . implode(' AND ', $where) . "
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");
        $dailyStmt->execute($params);
        $dailyUsage = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'by_material' => $usageByMaterial,
            'daily' => $dailyUsage
        ];
    }

    /**
     * Bulk transfer from POS to Material Store
     */
    public function bulkTransferFromPos(array $transfers, int $userId): array
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
        
        try {
            $results = [];
            $errors = [];
            
            foreach ($transfers as $transfer) {
                $materialType = $transfer['material_type'] ?? '';
                $quantity = floatval($transfer['quantity'] ?? 0);
                
                if (empty($materialType) || $quantity <= 0) {
                    $errors[] = "Invalid transfer: {$materialType} - {$quantity}";
                    continue;
                }
                
                // Call transferFromPos with skipTransaction=true to avoid nested transactions
                $result = $this->transferFromPos($materialType, $quantity, $userId, [
                    'remarks' => $transfer['remarks'] ?? 'Bulk transfer'
                ], true); // Skip transaction, we're in a parent transaction
                
                if ($result['success']) {
                    $results[] = $result;
                } else {
                    $errors[] = "Failed: {$materialType} - " . ($result['error'] ?? 'Unknown error');
                }
            }
            
            if (!empty($errors) && count($errors) === count($transfers)) {
                // All failed
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => false,
                    'errors' => $errors,
                    'results' => $results
                ];
            }
            
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            
            return [
                'success' => true,
                'transferred' => count($results),
                'results' => $results,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[MaterialStoreService] Bulk transfer failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

