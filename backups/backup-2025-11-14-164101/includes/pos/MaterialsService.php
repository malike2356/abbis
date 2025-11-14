<?php
/**
 * Materials Service
 * Handles bidirectional sync between Materials Inventory and POS System
 */
class MaterialsService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if customer is a company (has company_type or is in clients table)
     */
    public function isCompanyCustomer(?int $customerId): bool
    {
        if (!$customerId) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM clients 
                WHERE id = ? 
                AND (company_type IS NOT NULL OR client_name LIKE '%Ltd%' OR client_name LIKE '%Inc%' OR client_name LIKE '%Company%')
            ");
            $stmt->execute([$customerId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('[MaterialsService] Error checking company: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get material mapping for a catalog item or POS product
     */
    public function getMaterialMapping(string $materialType): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM pos_material_mappings 
                WHERE material_type = ?
            ");
            $stmt->execute([$materialType]);
            $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
            return $mapping ?: null;
        } catch (PDOException $e) {
            // Table might not exist yet
            return null;
        }
    }

    /**
     * Check if product is a material (screen_pipe, plain_pipe, gravel)
     */
    public function isMaterialProduct(int $productId, ?int $catalogItemId = null): ?string
    {
        // Check by product name/SKU patterns
        try {
            if ($catalogItemId) {
                $stmt = $this->pdo->prepare("
                    SELECT name, sku FROM catalog_items WHERE id = ?
                ");
                $stmt->execute([$catalogItemId]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($item) {
                    $name = strtolower($item['name'] ?? '');
                    $sku = strtolower($item['sku'] ?? '');
                    
                    if (strpos($name, 'screen') !== false && strpos($name, 'pipe') !== false) {
                        return 'screen_pipe';
                    }
                    if (strpos($name, 'plain') !== false && strpos($name, 'pipe') !== false) {
                        return 'plain_pipe';
                    }
                    if (strpos($name, 'gravel') !== false || strpos($sku, 'gravel') !== false) {
                        return 'gravel';
                    }
                }
            }
            
            // Check POS products
            $stmt = $this->pdo->prepare("
                SELECT name, sku FROM pos_products WHERE id = ?
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                $name = strtolower($product['name'] ?? '');
                $sku = strtolower($product['sku'] ?? '');
                
                if (strpos($name, 'screen') !== false && strpos($name, 'pipe') !== false) {
                    return 'screen_pipe';
                }
                if (strpos($name, 'plain') !== false && strpos($name, 'pipe') !== false) {
                    return 'plain_pipe';
                }
                if (strpos($name, 'gravel') !== false || strpos($sku, 'gravel') !== false) {
                    return 'gravel';
                }
            }
        } catch (PDOException $e) {
            error_log('[MaterialsService] Error checking material product: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Deduct materials from inventory when company sale is made
     */
    public function deductMaterialsForSale(int $saleId, array $saleItems, int $userId): array
    {
        $results = [];
        
        try {
            // Get sale to check if customer is a company
            $stmt = $this->pdo->prepare("SELECT customer_id FROM pos_sales WHERE id = ?");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sale || !$this->isCompanyCustomer($sale['customer_id'])) {
                return $results; // Not a company sale, skip
            }

            foreach ($saleItems as $item) {
                $materialType = $this->isMaterialProduct(
                    $item['product_id'] ?? 0,
                    $item['catalog_item_id'] ?? null
                );
                
                if (!$materialType) {
                    continue; // Not a material product
                }

                $quantity = floatval($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                // Deduct from materials_inventory
                $deductResult = $this->deductMaterial($materialType, $quantity, $userId, [
                    'reference_type' => 'pos_sale',
                    'reference_id' => $saleId,
                    'remarks' => "Auto-deducted for company sale #{$saleId}"
                ]);

                if ($deductResult['success']) {
                    $results[] = [
                        'material_type' => $materialType,
                        'quantity' => $quantity,
                        'success' => true
                    ];
                } else {
                    $results[] = [
                        'material_type' => $materialType,
                        'quantity' => $quantity,
                        'success' => false,
                        'error' => $deductResult['error'] ?? 'Unknown error'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('[MaterialsService] Error deducting materials: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Deduct material from inventory
     */
    public function deductMaterial(string $materialType, float $quantity, int $userId, array $options = []): array
    {
        $transactionStarted = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transactionStarted = true;
        }
        
        try {
            // Get current inventory
            $stmt = $this->pdo->prepare("
                SELECT id, quantity_remaining, material_name 
                FROM materials_inventory 
                WHERE material_type = ?
            ");
            $stmt->execute([$materialType]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$material) {
                if ($transactionStarted && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'error' => "Material type '{$materialType}' not found in inventory"];
            }

            $quantityBefore = floatval($material['quantity_remaining']);
            $quantityAfter = max(0, $quantityBefore - $quantity);

            // Update inventory
            $updateStmt = $this->pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_remaining = ?,
                    quantity_used = quantity_used + ?,
                    last_updated = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantityAfter, $quantity, $material['id']]);

            // Log activity
            $this->logActivity(
                $materialType,
                'sale_deduction',
                -$quantity,
                $quantityBefore,
                $quantityAfter,
                $userId,
                $options['reference_type'] ?? null,
                $options['reference_id'] ?? null,
                $options['remarks'] ?? null
            );

            // Record in inventory_transactions if table exists
            try {
                $transStmt = $this->pdo->prepare("
                    INSERT INTO inventory_transactions (
                        transaction_code, material_id, transaction_type, quantity,
                        reference_type, reference_id, notes, created_by
                    ) VALUES (?, ?, 'usage', ?, ?, ?, ?, ?)
                ");
                $transCode = 'POS-' . date('YmdHis') . '-' . rand(1000, 9999);
                $transStmt->execute([
                    $transCode,
                    $material['id'],
                    -$quantity,
                    $options['reference_type'] ?? 'pos_sale',
                    $options['reference_id'] ?? null,
                    $options['remarks'] ?? null,
                    $userId
                ]);
            } catch (PDOException $e) {
                // inventory_transactions table might not exist, continue
                error_log('[MaterialsService] inventory_transactions not available: ' . $e->getMessage());
            }

            if ($transactionStarted) {
                $this->pdo->commit();
            }
            return ['success' => true, 'quantity_before' => $quantityBefore, 'quantity_after' => $quantityAfter];
        } catch (Exception $e) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[MaterialsService] Error deducting material: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add material to inventory (for returns)
     */
    public function addMaterial(string $materialType, float $quantity, int $userId, array $options = []): array
    {
        $transactionStarted = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transactionStarted = true;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, quantity_remaining, material_name 
                FROM materials_inventory 
                WHERE material_type = ?
            ");
            $stmt->execute([$materialType]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$material) {
                if ($transactionStarted && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'error' => "Material type '{$materialType}' not found"];
            }

            $quantityBefore = floatval($material['quantity_remaining']);
            $quantityAfter = $quantityBefore + $quantity;

            // Update inventory
            $updateStmt = $this->pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_remaining = ?,
                    quantity_used = GREATEST(0, quantity_used - ?),
                    last_updated = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantityAfter, $quantity, $material['id']]);

            // Log activity
            $this->logActivity(
                $materialType,
                'return_accepted',
                $quantity,
                $quantityBefore,
                $quantityAfter,
                $userId,
                $options['reference_type'] ?? null,
                $options['reference_id'] ?? null,
                $options['remarks'] ?? null
            );

            // Record in inventory_transactions
            try {
                $transStmt = $this->pdo->prepare("
                    INSERT INTO inventory_transactions (
                        transaction_code, material_id, transaction_type, quantity,
                        reference_type, reference_id, notes, created_by
                    ) VALUES (?, ?, 'return', ?, ?, ?, ?, ?)
                ");
                $transCode = 'RET-' . date('YmdHis') . '-' . rand(1000, 9999);
                $transStmt->execute([
                    $transCode,
                    $material['id'],
                    $quantity,
                    $options['reference_type'] ?? 'material_return',
                    $options['reference_id'] ?? null,
                    $options['remarks'] ?? null,
                    $userId
                ]);
            } catch (PDOException $e) {
                // Table might not exist
                error_log('[MaterialsService] inventory_transactions not available: ' . $e->getMessage());
            }

            if ($transactionStarted) {
                $this->pdo->commit();
            }
            return ['success' => true, 'quantity_before' => $quantityBefore, 'quantity_after' => $quantityAfter];
        } catch (Exception $e) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[MaterialsService] Error adding material: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Log material activity
     */
    private function logActivity(
        string $materialType,
        string $activityType,
        float $quantityChange,
        ?float $quantityBefore,
        ?float $quantityAfter,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_material_activity_log (
                    material_type, activity_type, quantity_change,
                    quantity_before, quantity_after, reference_type,
                    reference_id, performed_by, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $materialType,
                $activityType,
                $quantityChange,
                $quantityBefore,
                $quantityAfter,
                $referenceType,
                $referenceId,
                $userId,
                $remarks
            ]);
        } catch (PDOException $e) {
            // Table might not exist yet
            error_log('[MaterialsService] Activity log table not available: ' . $e->getMessage());
        }
    }

    /**
     * Create material return request
     */
    public function createReturnRequest(array $data, int $userId): array
    {
        try {
            $requestNumber = 'MRR-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO pos_material_returns (
                    request_number, material_type, material_name, quantity,
                    unit_of_measure, status, requested_by, remarks, pos_sale_id
                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");
            
            $stmt->execute([
                $requestNumber,
                $data['material_type'],
                $data['material_name'] ?? $data['material_type'],
                $data['quantity'],
                $data['unit_of_measure'] ?? 'pcs',
                $userId,
                $data['remarks'] ?? null,
                $data['pos_sale_id'] ?? null
            ]);
            
            $returnId = (int)$this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'return_id' => $returnId,
                'request_number' => $requestNumber
            ];
        } catch (PDOException $e) {
            error_log('[MaterialsService] Error creating return request: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Accept material return request
     */
    public function acceptReturnRequest(int $returnId, int $userId, array $data): array
    {
        $this->pdo->beginTransaction();
        
        try {
            // Get return request
            $stmt = $this->pdo->prepare("
                SELECT * FROM pos_material_returns 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$returnId]);
            $returnRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$returnRequest) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'Return request not found or already processed'];
            }

            $actualQuantity = floatval($data['actual_quantity'] ?? $returnRequest['quantity']);
            $qualityCheck = $data['quality_check'] ?? null;

            // Update return request
            $updateStmt = $this->pdo->prepare("
                UPDATE pos_material_returns 
                SET status = 'accepted',
                    accepted_by = ?,
                    accepted_at = NOW(),
                    actual_quantity_received = ?,
                    quality_check = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$userId, $actualQuantity, $qualityCheck, $returnId]);

            // When materials are returned TO POS, they come FROM operations
            // So: DECREASE materials_inventory (operations) and INCREASE POS inventory (shop)
            
            // Step 1: Decrease materials_inventory (operations side)
            $deductResult = $this->deductMaterial(
                $returnRequest['material_type'],
                $actualQuantity,
                $userId,
                [
                    'reference_type' => 'material_return',
                    'reference_id' => $returnId,
                    'remarks' => "Materials returned to POS shop: " . ($returnRequest['remarks'] ?? 'No remarks')
                ]
            );

            if (!$deductResult['success']) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => $deductResult['error']];
            }
            
            // Log return acceptance activity (separate from deduction log)
            $this->logActivity(
                $returnRequest['material_type'],
                'return_accepted',
                -$actualQuantity, // Negative because materials are leaving operations
                $deductResult['quantity_before'] ?? null,
                $deductResult['quantity_after'] ?? null,
                $userId,
                'material_return',
                $returnId,
                "Return accepted. Quality: " . ($qualityCheck ?: 'Not specified') . ". " . ($returnRequest['remarks'] ?? '')
            );
            
            // Step 2: Increase inventory across ALL systems (CMS, POS, ABBIS)
            // Materials returned TO POS come FROM operations, so:
            // - Decrease: materials_inventory (operations) ✓ Already done above
            // - Increase: catalog_items.stock_quantity (CMS/POS source of truth)
            // - Increase: pos_inventory (POS stores) - auto-synced via UnifiedInventoryService
            try {
                require_once __DIR__ . '/UnifiedInventoryService.php';
                $inventoryService = new UnifiedInventoryService($this->pdo);
                
                // Get material mapping to find catalog_item_id
                $mappingStmt = $this->pdo->prepare("
                    SELECT catalog_item_id, pos_product_id 
                    FROM pos_material_mappings 
                    WHERE material_type = ?
                ");
                $mappingStmt->execute([$returnRequest['material_type']]);
                $mapping = $mappingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($mapping && $mapping['catalog_item_id']) {
                    // Update catalog_items (source of truth) - this syncs to POS and CMS automatically
                    $inventoryService->updateCatalogStock(
                        (int)$mapping['catalog_item_id'],
                        $actualQuantity, // Positive = increase inventory
                        "Material return from operations: {$returnRequest['material_type']} (Return #{$returnId})"
                    );
                    
                    error_log("[MaterialsService] Synced material return to catalog_item_id {$mapping['catalog_item_id']}: +{$actualQuantity} units");
                } else {
                    // Try to find catalog item by material type name pattern
                    $materialName = ucfirst(str_replace('_', ' ', $returnRequest['material_type']));
                    $catalogStmt = $this->pdo->prepare("
                        SELECT id FROM catalog_items 
                        WHERE (name LIKE ? OR sku LIKE ?) 
                        AND item_type = 'product'
                        LIMIT 1
                    ");
                    $searchPattern = "%{$materialName}%";
                    $catalogStmt->execute([$searchPattern, $searchPattern]);
                    $catalogItem = $catalogStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($catalogItem) {
                        $inventoryService->updateCatalogStock(
                            (int)$catalogItem['id'],
                            $actualQuantity,
                            "Material return from operations: {$returnRequest['material_type']} (Return #{$returnId})"
                        );
                        
                        // Create mapping for future use
                        try {
                            $mapStmt = $this->pdo->prepare("
                                INSERT INTO pos_material_mappings (material_type, catalog_item_id, created_at)
                                VALUES (?, ?, NOW())
                                ON DUPLICATE KEY UPDATE catalog_item_id = VALUES(catalog_item_id)
                            ");
                            $mapStmt->execute([$returnRequest['material_type'], $catalogItem['id']]);
                        } catch (PDOException $e) {
                            error_log("[MaterialsService] Could not create mapping: " . $e->getMessage());
                        }
                        
                        error_log("[MaterialsService] Found and synced catalog item {$catalogItem['id']} for material type {$returnRequest['material_type']}");
                    } else {
                        error_log("[MaterialsService] WARNING: No catalog_item found for material_type: {$returnRequest['material_type']}. Inventory not synced to CMS/POS.");
                    }
                }
            } catch (Exception $e) {
                error_log('[MaterialsService] System-wide inventory sync failed: ' . $e->getMessage());
                error_log('[MaterialsService] Stack trace: ' . $e->getTraceAsString());
                // Don't fail the return if sync fails, but log it
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'quantity_added' => $actualQuantity,
                'material_type' => $returnRequest['material_type']
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[MaterialsService] Error accepting return: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get pending return requests
     */
    public function getPendingReturns(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT mr.*, 
                       u1.full_name as requested_by_name,
                       u2.full_name as accepted_by_name
                FROM pos_material_returns mr
                LEFT JOIN users u1 ON mr.requested_by = u1.id
                LEFT JOIN users u2 ON mr.accepted_by = u2.id
                WHERE mr.status = 'pending'
                ORDER BY mr.requested_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[MaterialsService] Error getting pending returns: ' . $e->getMessage());
            return [];
        }
    }
}

