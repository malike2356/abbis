<?php
/**
 * Test Maintenance Extraction
 * Quick test to verify maintenance extraction is working
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MaintenanceExtractor.php';

header('Content-Type: text/plain');

$pdo = getDBConnection();
$extractor = new MaintenanceExtractor($pdo);

echo "===========================================\n";
echo "Maintenance Extraction Test\n";
echo "===========================================\n\n";

// Test data
$testReportData = [
    'rig_id' => 1,
    'report_date' => date('Y-m-d'),
    'job_type' => 'maintenance',
    'is_maintenance_work' => 1,
    'maintenance_work_type' => 'Repair',
    'incident_log' => 'Hydraulic pump broke down during drilling operation. Engine was making strange noises.',
    'solution_log' => 'Replaced hydraulic pump with new one. Fixed engine oil leak. Lubricated all moving parts.',
    'remarks' => 'Maintenance completed successfully. Rig is now operational.',
    'total_duration' => 120, // 2 hours
    'total_wages' => 500.00,
    'expenses' => [
        [
            'description' => 'Hydraulic pump replacement',
            'quantity' => 1,
            'unit_cost' => 1500.00,
            'amount' => 1500.00
        ],
        [
            'description' => 'Engine oil',
            'quantity' => 5,
            'unit_cost' => 50.00,
            'amount' => 250.00
        ]
    ]
];

echo "Test Data:\n";
echo "- Job Type: " . $testReportData['job_type'] . "\n";
echo "- Incident: " . substr($testReportData['incident_log'], 0, 50) . "...\n";
echo "- Solution: " . substr($testReportData['solution_log'], 0, 50) . "...\n";
echo "- Expenses: " . count($testReportData['expenses']) . " items\n\n";

echo "Extracting maintenance information...\n\n";

$maintenanceData = $extractor->extractFromFieldReport($testReportData);

if ($maintenanceData && isset($maintenanceData['is_maintenance'])) {
    echo "✓ Maintenance detected!\n\n";
    echo "Extracted Data:\n";
    echo "- Maintenance Type: " . ($maintenanceData['maintenance_type'] ?? 'N/A') . "\n";
    echo "- Category: " . ($maintenanceData['category'] ?? 'N/A') . "\n";
    echo "- Priority: " . ($maintenanceData['priority'] ?? 'N/A') . "\n";
    echo "- Description: " . substr($maintenanceData['description'] ?? 'N/A', 0, 60) . "...\n";
    echo "- Work Performed: " . substr($maintenanceData['work_performed'] ?? 'N/A', 0, 60) . "...\n";
    echo "- Parts Cost: GHS " . number_format($maintenanceData['parts_cost'] ?? 0, 2) . "\n";
    echo "- Labor Cost: GHS " . number_format($maintenanceData['labor_cost'] ?? 0, 2) . "\n";
    echo "- Total Cost: GHS " . number_format($maintenanceData['total_cost'] ?? 0, 2) . "\n";
    echo "- Downtime Hours: " . number_format($maintenanceData['downtime_hours'] ?? 0, 2) . "\n";
    echo "- Parts Extracted: " . count($maintenanceData['parts'] ?? []) . "\n";
    
    if (!empty($maintenanceData['parts'])) {
        echo "\nParts List:\n";
        foreach ($maintenanceData['parts'] as $part) {
            echo "  - " . $part['part_name'] . " (Qty: " . $part['quantity'] . ", Cost: GHS " . number_format($part['total_cost'], 2) . ")\n";
        }
    }
    
    echo "\n✓ Extraction successful!\n";
} else {
    echo "✗ No maintenance detected in test data\n";
}

echo "\n===========================================\n";
echo "Test Complete!\n";
echo "===========================================\n";

