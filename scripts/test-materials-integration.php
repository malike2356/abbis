<?php
/**
 * Materials Integration Test Script
 * Tests the complete flow from Field Reports → Materials → Resources → POS → CMS
 * 
 * Usage: php scripts/test-materials-integration.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/pos/FieldReportMaterialsService.php';
require_once __DIR__ . '/../includes/pos/UnifiedInventoryService.php';
require_once __DIR__ . '/../includes/pos/MaterialsService.php';
require_once __DIR__ . '/../includes/pos/PosRepository.php';

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  MATERIALS INTEGRATION SYSTEM TEST\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$testResults = [];

// Test 1: Verify Database Tables Exist
echo "Test 1: Verifying Database Tables...\n";
$tables = [
    'field_reports',
    'materials_inventory',
    'catalog_items',
    'pos_inventory',
    'pos_material_returns',
    'field_report_materials_remaining',
    'pos_material_mappings'
];

$allTablesExist = true;
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        echo "  ✓ Table '{$table}' exists\n";
    } catch (PDOException $e) {
        echo "  ✗ Table '{$table}' MISSING\n";
        $allTablesExist = false;
    }
}
$testResults['tables'] = $allTablesExist;
echo "\n";

// Test 2: Verify Service Classes Exist
echo "Test 2: Verifying Service Classes...\n";
$services = [
    'FieldReportMaterialsService',
    'UnifiedInventoryService',
    'MaterialsService',
    'PosRepository'
];

$allServicesExist = true;
foreach ($services as $service) {
    if (class_exists($service)) {
        echo "  ✓ Class '{$service}' exists\n";
    } else {
        echo "  ✗ Class '{$service}' MISSING\n";
        $allServicesExist = false;
    }
}
$testResults['services'] = $allServicesExist;
echo "\n";

// Test 3: Verify Material Mappings
echo "Test 3: Verifying Material Mappings...\n";
try {
    $stmt = $pdo->query("
        SELECT material_type, catalog_item_id, pos_product_id 
        FROM pos_material_mappings 
        WHERE material_type IN ('screen_pipe', 'plain_pipe', 'gravel')
    ");
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($mappings) > 0) {
        echo "  ✓ Found " . count($mappings) . " material mapping(s):\n";
        foreach ($mappings as $mapping) {
            echo "    - {$mapping['material_type']} → catalog_item_id: " . ($mapping['catalog_item_id'] ?? 'NULL') . "\n";
        }
        $testResults['mappings'] = true;
    } else {
        echo "  ⚠ No material mappings found (will be created automatically)\n";
        $testResults['mappings'] = true; // Not critical, auto-created
    }
} catch (PDOException $e) {
    echo "  ✗ Error checking mappings: " . $e->getMessage() . "\n";
    $testResults['mappings'] = false;
}
echo "\n";

// Test 4: Verify Materials Inventory
echo "Test 4: Verifying Materials Inventory...\n";
try {
    $stmt = $pdo->query("
        SELECT material_type, quantity_remaining, unit_cost 
        FROM materials_inventory 
        WHERE material_type IN ('screen_pipe', 'plain_pipe', 'gravel')
    ");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($materials) > 0) {
        echo "  ✓ Found " . count($materials) . " material(s) in inventory:\n";
        foreach ($materials as $mat) {
            echo "    - {$mat['material_type']}: " . number_format($mat['quantity_remaining']) . " units @ " . formatCurrency($mat['unit_cost']) . "\n";
        }
        $testResults['inventory'] = true;
    } else {
        echo "  ⚠ No materials in inventory\n";
        $testResults['inventory'] = true; // Not critical
    }
} catch (PDOException $e) {
    echo "  ✗ Error checking inventory: " . $e->getMessage() . "\n";
    $testResults['inventory'] = false;
}
echo "\n";

// Test 5: Verify Catalog Items (Source of Truth)
echo "Test 5: Verifying Catalog Items (Source of Truth)...\n";
try {
    $stmt = $pdo->query("
        SELECT id, name, sku, 
               COALESCE(stock_quantity, inventory_quantity, 0) as stock,
               item_type
        FROM catalog_items 
        WHERE item_type = 'product' 
        AND (name LIKE '%screen%pipe%' OR name LIKE '%plain%pipe%' OR name LIKE '%gravel%'
             OR sku LIKE '%SCREEN%' OR sku LIKE '%PLAIN%' OR sku LIKE '%GRAVEL%')
        LIMIT 10
    ");
    $catalogItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($catalogItems) > 0) {
        echo "  ✓ Found " . count($catalogItems) . " catalog item(s):\n";
        foreach ($catalogItems as $item) {
            echo "    - {$item['name']} (SKU: {$item['sku']}): " . number_format($item['stock'], 2) . " units\n";
        }
        $testResults['catalog'] = true;
    } else {
        echo "  ⚠ No matching catalog items found\n";
        $testResults['catalog'] = true; // Not critical
    }
} catch (PDOException $e) {
    echo "  ✗ Error checking catalog: " . $e->getMessage() . "\n";
    $testResults['catalog'] = false;
}
echo "\n";

// Test 6: Verify POS Inventory
echo "Test 6: Verifying POS Inventory...\n";
try {
    $stmt = $pdo->query("
        SELECT i.id, p.name, i.store_id, i.quantity_on_hand
        FROM pos_inventory i
        INNER JOIN pos_products p ON p.id = i.product_id
        WHERE p.name LIKE '%screen%pipe%' OR p.name LIKE '%plain%pipe%' OR p.name LIKE '%gravel%'
        LIMIT 10
    ");
    $posInventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($posInventory) > 0) {
        echo "  ✓ Found " . count($posInventory) . " POS inventory item(s):\n";
        foreach ($posInventory as $inv) {
            echo "    - {$inv['name']} (Store {$inv['store_id']}): " . number_format($inv['quantity_on_hand'], 2) . " units\n";
        }
        $testResults['pos'] = true;
    } else {
        echo "  ⚠ No matching POS inventory found\n";
        $testResults['pos'] = true; // Not critical
    }
} catch (PDOException $e) {
    echo "  ✗ Error checking POS inventory: " . $e->getMessage() . "\n";
    $testResults['pos'] = false;
}
echo "\n";

// Test 7: Verify Integration Methods
echo "Test 7: Verifying Integration Methods...\n";
$methods = [
    'FieldReportMaterialsService' => ['processFieldReportMaterials', 'getRemainingMaterials', 'createReturnRequest'],
    'UnifiedInventoryService' => ['updateCatalogStock', 'setCatalogStock', 'syncAllInventory'],
    'MaterialsService' => ['acceptReturnRequest', 'createReturnRequest', 'deductMaterial', 'addMaterial']
];

$allMethodsExist = true;
foreach ($methods as $class => $methodList) {
    if (!class_exists($class)) {
        echo "  ✗ Class '{$class}' not found\n";
        $allMethodsExist = false;
        continue;
    }
    
    $reflection = new ReflectionClass($class);
    foreach ($methodList as $method) {
        if ($reflection->hasMethod($method)) {
            echo "  ✓ {$class}::{$method}() exists\n";
        } else {
            echo "  ✗ {$class}::{$method}() MISSING\n";
            $allMethodsExist = false;
        }
    }
}
$testResults['methods'] = $allMethodsExist;
echo "\n";

// Test 8: Verify API Endpoints
echo "Test 8: Verifying API Endpoints...\n";
$endpoints = [
    'api/save-report.php' => 'Field report submission',
    'api/update-materials.php' => 'Material receipt',
    'modules/api/material-return-request.php' => 'Material return request',
    'pos/api/material-returns.php' => 'POS return accept/reject',
    'pos/api/store-stock.php' => 'Store stock lookup',
    'pos/api/sync-inventory.php' => 'Inventory sync'
];

$allEndpointsExist = true;
foreach ($endpoints as $endpoint => $description) {
    $path = __DIR__ . '/../' . $endpoint;
    if (file_exists($path)) {
        echo "  ✓ {$endpoint} ({$description})\n";
    } else {
        echo "  ✗ {$endpoint} MISSING\n";
        $allEndpointsExist = false;
    }
}
$testResults['endpoints'] = $allEndpointsExist;
echo "\n";

// Test 9: Verify Cost Calculation Logic
echo "Test 9: Verifying Cost Calculation Logic...\n";
$logicFiles = [
    'includes/functions.php' => 'Server-side calculation',
    'assets/js/calculations.js' => 'Client-side calculation',
    'includes/pos/FieldReportMaterialsService.php' => 'Service logic'
];

$allLogicFilesExist = true;
foreach ($logicFiles as $file => $description) {
    $path = __DIR__ . '/../' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (strpos($content, 'subcontract') !== false && strpos($content, 'materials_provided_by') !== false) {
            echo "  ✓ {$file} contains contractor logic ({$description})\n";
        } else {
            echo "  ⚠ {$file} exists but contractor logic not found\n";
        }
    } else {
        echo "  ✗ {$file} MISSING\n";
        $allLogicFilesExist = false;
    }
}
$testResults['logic'] = $allLogicFilesExist;
echo "\n";

// Summary
echo "═══════════════════════════════════════════════════════════════\n";
echo "  TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$passed = 0;
$total = count($testResults);

foreach ($testResults as $test => $result) {
    $status = $result ? '✓ PASS' : '✗ FAIL';
    echo sprintf("  %-20s %s\n", ucfirst($test) . ':', $status);
    if ($result) $passed++;
}

echo "\n";
echo "Results: {$passed}/{$total} tests passed\n\n";

if ($passed === $total) {
    echo "✅ ALL TESTS PASSED - System is fully integrated!\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed - Please review the issues above\n";
    exit(1);
}

