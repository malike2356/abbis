<?php
/**
 * Field Report Materials Service
 * Handles materials flow from field reports to materials_inventory, Resources, POS, and CMS
 * 
 * Flow:
 * 1. Field Report → materials_inventory (received/used)
 * 2. materials_inventory → catalog_items (Resources)
 * 3. catalog_items → pos_inventory (POS)
 * 4. catalog_items → CMS inventory
 * 
 * Return Flow:
 * - Remaining materials (for company) → Resources (visible)
 * - Return button → POS alert → Accept/Reject
 * - If accepted: Resources decreases, POS increases
 * - If rejected: Resources returns to original, POS unchanged
 */
class FieldReportMaterialsService
{
    private PDO $pdo;
    private $materialsService;
    private $inventoryService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        require_once __DIR__ . '/MaterialsService.php';
        require_once __DIR__ . '/UnifiedInventoryService.php';
        $this->materialsService = new MaterialsService($this->pdo);
        $this->inventoryService = new UnifiedInventoryService($this->pdo);
    }

    /**
     * Process materials from field report
     * Handles received, used, and remaining materials with system-wide sync
     */
    public function processFieldReportMaterials(int $reportId, array $data): array
    {
        $this->pdo->beginTransaction();
        
        try {
            $jobType = $data['job_type'] ?? 'direct';
            $materialsProvidedBy = $data['materials_provided_by'] ?? 'client';
            $materialsStoreId = !empty($data['materials_store_id']) ? (int)$data['materials_store_id'] : null;
            
            // Material quantities
            $screenPipesReceived = floatval($data['screen_pipes_received'] ?? 0);
            $plainPipesReceived = floatval($data['plain_pipes_received'] ?? 0);
            $gravelReceived = floatval($data['gravel_received'] ?? 0);
            
            $screenPipesUsed = floatval($data['screen_pipes_used'] ?? 0);
            $plainPipesUsed = floatval($data['plain_pipes_used'] ?? 0);
            $gravelUsed = floatval($data['gravel_used'] ?? 0);
            
            // Calculate remaining
            $screenPipesRemaining = max(0, $screenPipesReceived - $screenPipesUsed);
            $plainPipesRemaining = max(0, $plainPipesReceived - $plainPipesUsed);
            $gravelRemaining = max(0, $gravelReceived - $gravelUsed);
            
            $results = [
                'received' => [],
                'used' => [],
                'remaining' => [],
                'cost_calculation' => [],
                'sync_status' => []
            ];
            
            // Determine if materials should be included in cost calculation
            // Rule: If contractor job AND materials provided by client → NOT in cost
            // Company materials (shop or store) are always included in cost
            $includeInCost = true;
            if ($jobType === 'subcontract' && $materialsProvidedBy === 'client') {
                $includeInCost = false;
            }
            
            // Both company_shop and company_store are company materials
            if (in_array($materialsProvidedBy, ['company_shop', 'company_store', 'company'])) {
                $includeInCost = true;
            }
            
            // Process each material type
            $materials = [
                'screen_pipe' => [
                    'received' => $screenPipesReceived,
                    'used' => $screenPipesUsed,
                    'remaining' => $screenPipesRemaining
                ],
                'plain_pipe' => [
                    'received' => $plainPipesReceived,
                    'used' => $plainPipesUsed,
                    'remaining' => $plainPipesRemaining
                ],
                'gravel' => [
                    'received' => $gravelReceived,
                    'used' => $gravelUsed,
                    'remaining' => $gravelRemaining
                ]
            ];
            
            foreach ($materials as $materialType => $quantities) {
                if ($quantities['received'] <= 0 && $quantities['used'] <= 0) {
                    continue; // Skip if no materials
                }
                
                // Step 1: Handle materials received (if from store/company)
                if ($quantities['received'] > 0 && 
                    ($materialsProvidedBy === 'store' || $materialsProvidedBy === 'company')) {
                    
                    $receiveResult = $this->handleMaterialsReceived(
                        $materialType,
                        $quantities['received'],
                        $reportId,
                        $materialsStoreId,
                        $materialsProvidedBy
                    );
                    $results['received'][$materialType] = $receiveResult;
                }
                
                // Step 2: Handle materials used
                if ($quantities['used'] > 0) {
                    $useResult = $this->handleMaterialsUsed(
                        $materialType,
                        $quantities['used'],
                        $reportId,
                        $includeInCost
                    );
                    $results['used'][$materialType] = $useResult;
                }
                
                // Step 3: Handle remaining materials (if for company/store)
                if ($quantities['remaining'] > 0 && 
                    ($materialsProvidedBy === 'company' || $materialsProvidedBy === 'store')) {
                    
                    $remainingResult = $this->handleRemainingMaterials(
                        $materialType,
                        $quantities['remaining'],
                        $reportId,
                        $materialsStoreId,
                        $materialsProvidedBy
                    );
                    $results['remaining'][$materialType] = $remainingResult;
                }
            }
            
            // Store materials data in field report for reference
            $this->storeMaterialsData($reportId, $data, $results);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'results' => $results,
                'include_in_cost' => $includeInCost,
                'message' => 'Materials processed successfully'
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[FieldReportMaterialsService] Error: ' . $e->getMessage());
            error_log('[FieldReportMaterialsService] Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle materials received from store/company
     */
    private function handleMaterialsReceived(
        string $materialType,
        float $quantity,
        int $reportId,
        ?int $storeId,
        string $providedBy
    ): array {
        try {
            // If from store, deduct from POS inventory first
            if ($providedBy === 'store' && $storeId) {
                $this->deductFromPosInventory($materialType, $quantity, $storeId, $reportId);
            }
            
            // Add to materials_inventory (operations)
            $addResult = $this->materialsService->addMaterial(
                $materialType,
                $quantity,
                $_SESSION['user_id'] ?? 1,
                [
                    'reference_type' => 'field_report',
                    'reference_id' => $reportId,
                    'remarks' => "Materials received from {$providedBy} for field report #{$reportId}"
                ]
            );
            
            return [
                'success' => $addResult['success'] ?? false,
                'quantity' => $quantity,
                'source' => $providedBy,
                'store_id' => $storeId
            ];
            
        } catch (Exception $e) {
            error_log("[FieldReportMaterialsService] Error receiving {$materialType}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle materials used in field report
     */
    private function handleMaterialsUsed(
        string $materialType,
        float $quantity,
        int $reportId,
        bool $includeInCost
    ): array {
        try {
            // Deduct from materials_inventory (operations)
            $deductResult = $this->materialsService->deductMaterial(
                $materialType,
                $quantity,
                $_SESSION['user_id'] ?? 1,
                [
                    'reference_type' => 'field_report',
                    'reference_id' => $reportId,
                    'remarks' => "Materials used in field report #{$reportId}" . 
                                ($includeInCost ? ' (included in cost)' : ' (not included in cost)')
                ]
            );
            
            return [
                'success' => $deductResult['success'] ?? false,
                'quantity' => $quantity,
                'include_in_cost' => $includeInCost,
                'cost' => $includeInCost ? $this->calculateMaterialCost($materialType, $quantity) : 0
            ];
            
        } catch (Exception $e) {
            error_log("[FieldReportMaterialsService] Error using {$materialType}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle remaining materials (for company/store - can be returned)
     */
    private function handleRemainingMaterials(
        string $materialType,
        float $quantity,
        int $reportId,
        ?int $storeId,
        string $providedBy
    ): array {
        try {
            // Remaining materials stay in materials_inventory
            // They will be visible in Resources page
            // Return button will trigger POS return request
            
            // Log remaining materials for tracking
            $this->logRemainingMaterials($materialType, $quantity, $reportId, $storeId, $providedBy);
            
            return [
                'success' => true,
                'quantity' => $quantity,
                'status' => 'pending_return',
                'visible_in_resources' => true,
                'visible_in_pos' => false // Only visible after return is accepted
            ];
            
        } catch (Exception $e) {
            error_log("[FieldReportMaterialsService] Error handling remaining {$materialType}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deduct materials from POS inventory when received from store
     */
    private function deductFromPosInventory(string $materialType, float $quantity, int $storeId, int $reportId): void
    {
        try {
            // Get mapping to find catalog_item_id
            $mappingStmt = $this->pdo->prepare("
                SELECT catalog_item_id, pos_product_id 
                FROM pos_material_mappings 
                WHERE material_type = ?
            ");
            $mappingStmt->execute([$materialType]);
            $mapping = $mappingStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mapping && $mapping['pos_product_id']) {
                // Deduct from POS inventory
                $this->inventoryService->adjustPosInventory(
                    $storeId,
                    (int)$mapping['pos_product_id'],
                    -$quantity, // Negative = decrease
                    "Materials taken from store for field report #{$reportId}"
                );
            }
        } catch (Exception $e) {
            error_log("[FieldReportMaterialsService] Error deducting from POS: " . $e->getMessage());
            // Don't fail - continue processing
        }
    }

    /**
     * Calculate material cost
     */
    private function calculateMaterialCost(string $materialType, float $quantity): float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT unit_cost FROM materials_inventory WHERE material_type = ?
            ");
            $stmt->execute([$materialType]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($material) {
                return floatval($material['unit_cost']) * $quantity;
            }
        } catch (Exception $e) {
            error_log("[FieldReportMaterialsService] Error calculating cost: " . $e->getMessage());
        }
        
        return 0;
    }

    /**
     * Log remaining materials for return tracking
     */
    private function logRemainingMaterials(
        string $materialType,
        float $quantity,
        int $reportId,
        ?int $storeId,
        string $providedBy
    ): void {
        try {
            // Check if table exists
            $this->pdo->query("SELECT 1 FROM field_report_materials_remaining LIMIT 1");
        } catch (PDOException $e) {
            // Create table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS field_report_materials_remaining (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    report_id INT NOT NULL,
                    material_type VARCHAR(100) NOT NULL,
                    quantity DECIMAL(10,2) NOT NULL,
                    store_id INT DEFAULT NULL,
                    provided_by VARCHAR(50) NOT NULL,
                    status ENUM('pending', 'returned', 'accepted', 'rejected') DEFAULT 'pending',
                    return_request_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_report (report_id),
                    INDEX idx_status (status),
                    INDEX idx_material_type (material_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO field_report_materials_remaining 
            (report_id, material_type, quantity, store_id, provided_by, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$reportId, $materialType, $quantity, $storeId, $providedBy]);
    }

    /**
     * Store materials data in field report
     */
    private function storeMaterialsData(int $reportId, array $data, array $results): void
    {
        try {
            // Update field_reports with materials data if columns exist
            $updateFields = [];
            $updateValues = [];
            
            // Store received quantities (add columns if they don't exist)
            $materialsReceived = [
                'screen_pipes_received' => floatval($data['screen_pipes_received'] ?? 0),
                'plain_pipes_received' => floatval($data['plain_pipes_received'] ?? 0),
                'gravel_received' => floatval($data['gravel_received'] ?? 0)
            ];
            
            foreach ($materialsReceived as $column => $value) {
                try {
                    $this->pdo->query("SELECT {$column} FROM field_reports LIMIT 1");
                    $updateFields[] = "{$column} = ?";
                    $updateValues[] = $value;
                } catch (PDOException $e) {
                    // Column doesn't exist - try to add it
                    try {
                        $this->pdo->exec("ALTER TABLE field_reports ADD COLUMN {$column} DECIMAL(10,2) DEFAULT 0");
                        $updateFields[] = "{$column} = ?";
                        $updateValues[] = $value;
                    } catch (PDOException $e2) {
                        // Failed to add column, skip
                        error_log("[FieldReportMaterialsService] Could not add column {$column}: " . $e2->getMessage());
                    }
                }
            }
            
            if (!empty($updateFields)) {
                $updateValues[] = $reportId;
                $stmt = $this->pdo->prepare("
                    UPDATE field_reports 
                    SET " . implode(', ', $updateFields) . "
                    WHERE id = ?
                ");
                $stmt->execute($updateValues);
            }
        } catch (Exception $e) {
            error_log("[FieldReportMaterialsService] Error storing materials data: " . $e->getMessage());
            // Non-fatal
        }
    }

    /**
     * Get remaining materials for a field report
     */
    public function getRemainingMaterials(int $reportId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM field_report_materials_remaining 
                WHERE report_id = ? AND status = 'pending'
            ");
            $stmt->execute([$reportId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Create return request for remaining materials
     */
    public function createReturnRequest(int $reportId, array $materialIds): array
    {
        $this->pdo->beginTransaction();
        
        try {
            $results = [];
            
            foreach ($materialIds as $materialId) {
                // Get remaining material record
                $stmt = $this->pdo->prepare("
                    SELECT * FROM field_report_materials_remaining 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$materialId]);
                $material = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$material) {
                    continue;
                }
                
                // Create POS return request
                $returnResult = $this->materialsService->createReturnRequest(
                    [
                        'material_type' => $material['material_type'],
                        'material_name' => ucfirst(str_replace('_', ' ', $material['material_type'])),
                        'quantity' => $material['quantity'],
                        'remarks' => "Return from field report #{$reportId}",
                        'pos_sale_id' => null
                    ],
                    $_SESSION['user_id'] ?? 1
                );
                
                if ($returnResult['success']) {
                    // Update status
                    $updateStmt = $this->pdo->prepare("
                        UPDATE field_report_materials_remaining 
                        SET status = 'returned',
                            return_request_id = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$returnResult['return_id'], $materialId]);
                    
                    $results[] = [
                        'material_id' => $materialId,
                        'return_id' => $returnResult['return_id'],
                        'success' => true
                    ];
                } else {
                    $results[] = [
                        'material_id' => $materialId,
                        'success' => false,
                        'error' => $returnResult['error'] ?? 'Unknown error'
                    ];
                }
            }
            
            $this->pdo->commit();
            return ['success' => true, 'results' => $results];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

