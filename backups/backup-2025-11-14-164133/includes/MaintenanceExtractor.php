<?php
/**
 * Maintenance Extractor
 * Automatically extracts maintenance information from field reports
 * and creates maintenance records
 */

require_once __DIR__ . '/helpers.php';

class MaintenanceExtractor {
    private $pdo;
    
    // Maintenance type keywords
    private $maintenanceKeywords = [
        'repair' => ['repair', 'fixed', 'fix', 'mend', 'restore'],
        'breakdown' => ['breakdown', 'broken', 'faulty', 'malfunction', 'not working', 'stopped working', 'failure'],
        'service' => ['service', 'servicing', 'maintenance', 'overhaul', 'refurbish'],
        'inspection' => ['inspection', 'check', 'checked', 'examined', 'assessed'],
        'replacement' => ['replace', 'replaced', 'substitute', 'changed', 'swapped'],
        'lubrication' => ['lubricate', 'lubrication', 'oil change', 'grease', 'greased'],
        'cleaning' => ['clean', 'cleaned', 'cleaning', 'wash', 'washed'],
        'calibration' => ['calibrate', 'calibration', 'adjust', 'adjusted', 'tune'],
        'parts' => ['part', 'parts', 'component', 'spare', 'spares'],
    ];
    
    // Equipment/asset keywords
    private $equipmentKeywords = [
        'engine' => ['engine', 'motor'],
        'pump' => ['pump', 'water pump', 'hydraulic pump'],
        'hydraulic' => ['hydraulic', 'hydraulics', 'hydraulic system'],
        'drill' => ['drill', 'drill bit', 'drilling', 'drill head'],
        'pipe' => ['pipe', 'pipes', 'tubing'],
        'hose' => ['hose', 'hoses', 'tube'],
        'filter' => ['filter', 'filters', 'air filter', 'oil filter'],
        'tire' => ['tire', 'tyre', 'tires', 'tyres', 'wheel'],
        'battery' => ['battery', 'batteries'],
        'brake' => ['brake', 'brakes', 'braking'],
        'clutch' => ['clutch'],
        'transmission' => ['transmission', 'gearbox'],
        'rig' => ['rig', 'drilling rig', 'equipment'],
    ];
    
    // Action keywords
    private $actionKeywords = [
        'replaced' => ['replace', 'replaced', 'substituted', 'changed'],
        'fixed' => ['fix', 'fixed', 'repair', 'repaired', 'mended'],
        'serviced' => ['service', 'serviced', 'maintained', 'overhauled'],
        'checked' => ['check', 'checked', 'tested', 'inspected'],
        'adjusted' => ['adjust', 'adjusted', 'tuned', 'calibrated'],
        'cleaned' => ['clean', 'cleaned', 'washed'],
        'lubricated' => ['lubricate', 'lubricated', 'greased', 'oiled'],
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Extract maintenance information from field report data
     * 
     * @param array $reportData Field report data
     * @return array|null Maintenance data or null if no maintenance detected
     */
    public function extractFromFieldReport($reportData) {
        // Check if explicitly marked as maintenance
        if (isset($reportData['is_maintenance_work']) && $reportData['is_maintenance_work'] == 1) {
            return $this->extractExplicitMaintenance($reportData);
        }
        
        // Check if job_type is maintenance
        if (isset($reportData['job_type']) && $reportData['job_type'] === 'maintenance') {
            return $this->extractExplicitMaintenance($reportData);
        }
        
        // Auto-detect from text fields
        $textFields = [
            $reportData['remarks'] ?? '',
            $reportData['incident_log'] ?? '',
            $reportData['solution_log'] ?? '',
            $reportData['recommendation_log'] ?? '',
        ];
        
        $combinedText = strtolower(implode(' ', $textFields));
        
        // Check if maintenance keywords are present
        if ($this->containsMaintenanceKeywords($combinedText)) {
            return $this->extractFromText($reportData, $combinedText);
        }
        
        return null;
    }
    
    /**
     * Extract maintenance when explicitly marked
     */
    private function extractExplicitMaintenance($reportData) {
        $maintenance = [
            'is_maintenance' => true,
            'rig_id' => $reportData['rig_id'] ?? null,
            'asset_id' => $reportData['asset_id'] ?? null,
            'report_date' => $reportData['report_date'] ?? date('Y-m-d'),
            'started_date' => $this->getDateTime($reportData['report_date'], $reportData['start_time'] ?? null),
            'completed_date' => $this->getDateTime($reportData['report_date'], $reportData['finish_time'] ?? null),
            'description' => $this->buildDescription($reportData),
            'work_performed' => $reportData['solution_log'] ?? '',
            'category' => 'reactive', // Default, can be changed
            'priority' => $this->determinePriority($reportData),
            'maintenance_type' => $reportData['maintenance_work_type'] ?? $this->detectMaintenanceType($reportData),
        ];
        
        // Extract parts from expense entries
        if (isset($reportData['expenses']) && is_array($reportData['expenses'])) {
            $maintenance['parts'] = $this->extractPartsFromExpenses($reportData['expenses']);
            $maintenance['parts_cost'] = array_sum(array_column($maintenance['parts'], 'total_cost'));
        }
        
        // Calculate costs
        $maintenance['labor_cost'] = $reportData['total_wages'] ?? 0;
        $maintenance['total_cost'] = ($maintenance['parts_cost'] ?? 0) + ($maintenance['labor_cost'] ?? 0);
        
        // Calculate downtime
        $maintenance['downtime_hours'] = $this->calculateDowntime($reportData);
        
        return $maintenance;
    }
    
    /**
     * Extract maintenance from text analysis
     */
    private function extractFromText($reportData, $text) {
        $maintenance = [
            'is_maintenance' => true,
            'rig_id' => $reportData['rig_id'] ?? null,
            'asset_id' => $reportData['asset_id'] ?? null,
            'report_date' => $reportData['report_date'] ?? date('Y-m-d'),
            'started_date' => $this->getDateTime($reportData['report_date'], $reportData['start_time'] ?? null),
            'completed_date' => $this->getDateTime($reportData['report_date'], $reportData['finish_time'] ?? null),
            'description' => $this->extractDescription($text, $reportData),
            'work_performed' => $this->extractWorkPerformed($text, $reportData),
            'category' => $this->determineCategory($text),
            'priority' => $this->determinePriority($reportData),
            'maintenance_type' => $this->detectMaintenanceType($reportData, $text),
        ];
        
        // Extract parts mentioned in text
        $maintenance['parts'] = $this->extractPartsFromText($text);
        
        // Calculate costs
        if (isset($reportData['expenses']) && is_array($reportData['expenses'])) {
            $expenseParts = $this->extractPartsFromExpenses($reportData['expenses']);
            $maintenance['parts'] = array_merge($maintenance['parts'], $expenseParts);
        }
        
        $maintenance['parts_cost'] = array_sum(array_column($maintenance['parts'], 'total_cost'));
        $maintenance['labor_cost'] = $reportData['total_wages'] ?? 0;
        $maintenance['total_cost'] = ($maintenance['parts_cost'] ?? 0) + ($maintenance['labor_cost'] ?? 0);
        $maintenance['downtime_hours'] = $this->calculateDowntime($reportData);
        
        return $maintenance;
    }
    
    /**
     * Check if text contains maintenance keywords
     */
    private function containsMaintenanceKeywords($text) {
        foreach ($this->maintenanceKeywords as $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Detect maintenance type from data and text
     */
    private function detectMaintenanceType($reportData, $text = '') {
        if (!empty($reportData['maintenance_work_type'])) {
            return $reportData['maintenance_work_type'];
        }
        
        $combinedText = strtolower(
            ($text ?: '') . ' ' . 
            ($reportData['incident_log'] ?? '') . ' ' .
            ($reportData['solution_log'] ?? '') . ' ' .
            ($reportData['remarks'] ?? '')
        );
        
        // Check keywords to determine type
        foreach ($this->maintenanceKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($combinedText, $keyword) !== false) {
                    return ucfirst(str_replace('_', ' ', $type));
                }
            }
        }
        
        return 'General Maintenance';
    }
    
    /**
     * Extract description from text
     */
    private function extractDescription($text, $reportData) {
        // Use incident_log if available
        if (!empty($reportData['incident_log'])) {
            return $reportData['incident_log'];
        }
        
        // Use remarks if available
        if (!empty($reportData['remarks'])) {
            return $reportData['remarks'];
        }
        
        // Extract first sentence mentioning maintenance
        $sentences = preg_split('/[.!?]+/', $text);
        foreach ($sentences as $sentence) {
            if ($this->containsMaintenanceKeywords($sentence)) {
                return trim($sentence);
            }
        }
        
        return 'Maintenance work performed';
    }
    
    /**
     * Extract work performed from text
     */
    private function extractWorkPerformed($text, $reportData) {
        // Use solution_log if available
        if (!empty($reportData['solution_log'])) {
            return $reportData['solution_log'];
        }
        
        // Extract sentences with action keywords
        $sentences = preg_split('/[.!?]+/', $text);
        $workPerformed = [];
        
        foreach ($sentences as $sentence) {
            foreach ($this->actionKeywords as $action => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($sentence, $keyword) !== false) {
                        $workPerformed[] = trim($sentence);
                        break 2;
                    }
                }
            }
        }
        
        return implode('. ', $workPerformed) ?: 'Maintenance work completed';
    }
    
    /**
     * Extract parts from text
     */
    private function extractPartsFromText($text) {
        $parts = [];
        
        // Look for equipment keywords and extract context
        foreach ($this->equipmentKeywords as $equipment => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    // Try to find quantity and cost in nearby text
                    $pattern = '/(' . preg_quote($keyword, '/') . ')[^.!?]*?(\d+)/i';
                    if (preg_match($pattern, $text, $matches)) {
                        $parts[] = [
                            'part_name' => ucfirst($equipment),
                            'quantity' => 1,
                            'unit_cost' => 0,
                            'total_cost' => 0,
                        ];
                    }
                }
            }
        }
        
        return $parts;
    }
    
    /**
     * Extract parts from expense entries
     */
    private function extractPartsFromExpenses($expenses) {
        $parts = [];
        
        foreach ($expenses as $expense) {
            // Check if expense description contains part keywords
            $description = strtolower($expense['description'] ?? '');
            
            foreach ($this->equipmentKeywords as $equipment => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($description, $keyword) !== false) {
                        $parts[] = [
                            'part_name' => $expense['description'] ?? ucfirst($equipment),
                            'quantity' => floatval($expense['quantity'] ?? 1),
                            'unit_cost' => floatval($expense['unit_cost'] ?? 0),
                            'total_cost' => floatval($expense['amount'] ?? 0),
                            'supplier' => $expense['supplier'] ?? null,
                        ];
                        break 2;
                    }
                }
            }
        }
        
        return $parts;
    }
    
    /**
     * Determine maintenance category
     */
    private function determineCategory($text) {
        $text = strtolower($text);
        
        // Reactive keywords (breakdown, broken, failure)
        $reactiveKeywords = ['breakdown', 'broken', 'faulty', 'malfunction', 'failure', 'not working', 'stopped'];
        foreach ($reactiveKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'reactive';
            }
        }
        
        // Default to proactive
        return 'proactive';
    }
    
    /**
     * Determine priority
     */
    private function determinePriority($reportData) {
        $text = strtolower(
            ($reportData['incident_log'] ?? '') . ' ' .
            ($reportData['remarks'] ?? '')
        );
        
        // Urgent keywords
        if (stripos($text, 'urgent') !== false || stripos($text, 'emergency') !== false) {
            return 'urgent';
        }
        
        // Critical keywords
        if (stripos($text, 'critical') !== false || stripos($text, 'breakdown') !== false) {
            return 'critical';
        }
        
        // High priority keywords
        if (stripos($text, 'important') !== false || stripos($text, 'needed') !== false) {
            return 'high';
        }
        
        return 'medium';
    }
    
    /**
     * Calculate downtime hours
     */
    private function calculateDowntime($reportData) {
        if (!empty($reportData['total_duration'])) {
            // Convert minutes to hours
            return floatval($reportData['total_duration']) / 60;
        }
        
        if (!empty($reportData['start_time']) && !empty($reportData['finish_time'])) {
            $start = new DateTime($reportData['report_date'] . ' ' . $reportData['start_time']);
            $finish = new DateTime($reportData['report_date'] . ' ' . $reportData['finish_time']);
            $diff = $start->diff($finish);
            return $diff->h + ($diff->i / 60);
        }
        
        return 0;
    }
    
    /**
     * Build description from report data
     */
    private function buildDescription($reportData) {
        $parts = [];
        
        if (!empty($reportData['incident_log'])) {
            $parts[] = $reportData['incident_log'];
        }
        
        if (!empty($reportData['remarks'])) {
            $parts[] = $reportData['remarks'];
        }
        
        if (!empty($reportData['maintenance_work_type'])) {
            $parts[] = 'Type: ' . $reportData['maintenance_work_type'];
        }
        
        return implode(' | ', $parts) ?: 'Maintenance work performed';
    }
    
    /**
     * Get datetime from date and time
     */
    private function getDateTime($date, $time) {
        if (empty($date)) {
            return null;
        }
        
        if (!empty($time)) {
            return $date . ' ' . $time . ':00';
        }
        
        return $date . ' 00:00:00';
    }
    
    /**
     * Create maintenance record from extracted data
     * 
     * @param array $maintenanceData Extracted maintenance data
     * @param int $fieldReportId Field report ID
     * @param int $userId User ID who created the report
     * @return int|null Maintenance record ID or null on failure
     */
    public function createMaintenanceRecord($maintenanceData, $fieldReportId, $userId) {
        try {
            // Get or create maintenance type
            $maintenanceTypeId = $this->getOrCreateMaintenanceType($maintenanceData['maintenance_type'] ?? 'General Maintenance');
            
            // Get asset ID (use rig as asset if no specific asset)
            $assetId = $maintenanceData['asset_id'] ?? $this->getRigAssetId($maintenanceData['rig_id']);
            
            // Generate maintenance code
            $maintenanceCode = $this->generateMaintenanceCode($fieldReportId);
            
            // Determine status
            $status = 'completed'; // Field reports are usually completed
            if (!empty($maintenanceData['started_date']) && empty($maintenanceData['completed_date'])) {
                $status = 'in_progress';
            }
            
            // Insert maintenance record
            $stmt = $this->pdo->prepare("
                INSERT INTO maintenance_records (
                    maintenance_code, maintenance_type_id, maintenance_category, asset_id, rig_id,
                    field_report_id, started_date, completed_date, status, priority,
                    description, work_performed, parts_cost, labor_cost, total_cost,
                    downtime_hours, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $maintenanceCode,
                $maintenanceTypeId,
                $maintenanceData['category'] ?? 'reactive',
                $assetId,
                $maintenanceData['rig_id'] ?? null,
                $fieldReportId,
                $maintenanceData['started_date'] ?? null,
                $maintenanceData['completed_date'] ?? null,
                $status,
                $maintenanceData['priority'] ?? 'medium',
                $maintenanceData['description'] ?? '',
                $maintenanceData['work_performed'] ?? '',
                $maintenanceData['parts_cost'] ?? 0,
                $maintenanceData['labor_cost'] ?? 0,
                $maintenanceData['total_cost'] ?? 0,
                $maintenanceData['downtime_hours'] ?? 0,
                $userId
            ]);
            
            $maintenanceId = $this->pdo->lastInsertId();
            
            // Add parts if any
            if (!empty($maintenanceData['parts']) && is_array($maintenanceData['parts'])) {
                $this->addMaintenanceParts($maintenanceId, $maintenanceData['parts']);
            }
            
            return $maintenanceId;
            
        } catch (PDOException $e) {
            error_log("Error creating maintenance record: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get or create maintenance type
     */
    private function getOrCreateMaintenanceType($typeName) {
        $stmt = $this->pdo->prepare("SELECT id FROM maintenance_types WHERE type_name = ? LIMIT 1");
        $stmt->execute([$typeName]);
        $type = $stmt->fetch();
        
        if ($type) {
            return $type['id'];
        }
        
        // Create new type
        $stmt = $this->pdo->prepare("
            INSERT INTO maintenance_types (type_name, description, is_proactive, is_active)
            VALUES (?, ?, 1, 1)
        ");
        $stmt->execute([$typeName, "Auto-created from field report"]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get rig as asset ID
     */
    private function getRigAssetId($rigId) {
        if (empty($rigId)) {
            return null;
        }
        
        // Try to find rig as asset
        $stmt = $this->pdo->prepare("
            SELECT id FROM assets 
            WHERE asset_type = 'rig' AND asset_name LIKE CONCAT('%', (SELECT rig_name FROM rigs WHERE id = ?), '%')
            LIMIT 1
        ");
        $stmt->execute([$rigId]);
        $asset = $stmt->fetch();
        
        if ($asset) {
            return $asset['id'];
        }
        
        // If not found, return null (will need to be set manually)
        return null;
    }
    
    /**
     * Generate maintenance code
     */
    private function generateMaintenanceCode($fieldReportId) {
        return 'MAINT-' . date('Ymd') . '-' . str_pad($fieldReportId, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Add maintenance parts
     */
    private function addMaintenanceParts($maintenanceId, $parts) {
        $stmt = $this->pdo->prepare("
            INSERT INTO maintenance_parts (
                maintenance_id, part_name, quantity, unit_cost, total_cost, supplier
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($parts as $part) {
            $stmt->execute([
                $maintenanceId,
                $part['part_name'] ?? 'Unknown Part',
                $part['quantity'] ?? 1,
                $part['unit_cost'] ?? 0,
                $part['total_cost'] ?? 0,
                $part['supplier'] ?? null
            ]);
        }
    }
}

