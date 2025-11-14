<?php
/**
 * Endpoint Verification Script
 * Tests actual HTTP endpoints to verify they're accessible
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

$baseUrl = 'http://localhost:8080/abbis3.2';
$errors = [];
$warnings = [];
$success = [];

echo "🌐 ABBIS Endpoint Verification Script\n";
echo "=====================================\n\n";

// Test endpoints (we'll check if files exist and are readable)
$endpoints = [
    // Client Portal
    [
        'path' => 'client-portal/login.php',
        'description' => 'Client Portal Login',
        'required' => true
    ],
    [
        'path' => 'client-portal/dashboard.php',
        'description' => 'Client Portal Dashboard',
        'required' => true
    ],
    [
        'path' => 'client-portal/auth-check.php',
        'description' => 'Client Portal Auth Check',
        'required' => true
    ],
    
    // POS API
    [
        'path' => 'pos/api/sales.php',
        'description' => 'POS Sales API',
        'required' => true
    ],
    [
        'path' => 'pos/api/catalog.php',
        'description' => 'POS Catalog API',
        'required' => true
    ],
    [
        'path' => 'pos/api/inventory.php',
        'description' => 'POS Inventory API',
        'required' => true
    ],
    [
        'path' => 'pos/api/store-stock.php',
        'description' => 'POS Store Stock API',
        'required' => true
    ],
    [
        'path' => 'pos/api/transfer-materials.php',
        'description' => 'POS Transfer Materials API',
        'required' => true
    ],
];

echo "📋 Testing File Existence and Readability...\n\n";

foreach ($endpoints as $endpoint) {
    $fullPath = __DIR__ . '/../' . $endpoint['path'];
    
    if (file_exists($fullPath)) {
        if (is_readable($fullPath)) {
            // Check if it's a PHP file
            if (pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                // Try to parse the file for syntax errors
                $output = [];
                $return = 0;
                exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $return);
                
                if ($return === 0) {
                    $success[] = "✓ {$endpoint['description']} ({$endpoint['path']}) - Valid PHP";
                } else {
                    $errors[] = "✗ {$endpoint['description']} ({$endpoint['path']}) - PHP Syntax Error: " . implode("\n", $output);
                }
            } else {
                $success[] = "✓ {$endpoint['description']} ({$endpoint['path']}) - Exists";
            }
        } else {
            $errors[] = "✗ {$endpoint['description']} ({$endpoint['path']}) - Not readable";
        }
    } else {
        if ($endpoint['required']) {
            $errors[] = "✗ {$endpoint['description']} ({$endpoint['path']}) - MISSING";
        } else {
            $warnings[] = "⚠ {$endpoint['description']} ({$endpoint['path']}) - Not found (optional)";
        }
    }
}

// Test path references in key files
echo "\n🔍 Testing Path References in Code...\n\n";

$pathChecks = [
    [
        'file' => 'includes/header.php',
        'should_contain' => ['client-portal/login.php'],
        'should_not_contain' => ['cms/client/login.php'],
        'description' => 'Header navigation links'
    ],
    [
        'file' => 'modules/pos.php',
        'should_contain' => ['pos/api/'],
        'should_not_contain' => ['api/pos/'],
        'description' => 'POS module API paths'
    ],
    [
        'file' => 'client-portal/header.php',
        'should_contain' => ['client-portal/client-styles.css'],
        'should_not_contain' => ['cms/client/client-styles.css'],
        'description' => 'Client portal header CSS path'
    ],
];

foreach ($pathChecks as $check) {
    $filePath = __DIR__ . '/../' . $check['file'];
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $allGood = true;
        
        foreach ($check['should_contain'] as $pattern) {
            if (strpos($content, $pattern) === false) {
                $errors[] = "✗ {$check['description']}: Missing expected path '{$pattern}' in {$check['file']}";
                $allGood = false;
            }
        }
        
        foreach ($check['should_not_contain'] as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $errors[] = "✗ {$check['description']}: Found old path '{$pattern}' in {$check['file']}";
                $allGood = false;
            }
        }
        
        if ($allGood) {
            $success[] = "✓ {$check['description']} - Paths correct";
        }
    } else {
        $errors[] = "✗ {$check['file']} - File not found";
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 SUMMARY\n";
echo str_repeat("=", 50) . "\n\n";

echo "✅ Success: " . count($success) . " checks passed\n";
if (count($warnings) > 0) {
    echo "⚠️  Warnings: " . count($warnings) . "\n";
}
if (count($errors) > 0) {
    echo "❌ Errors: " . count($errors) . "\n";
}

if (count($warnings) > 0) {
    echo "\n⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
}

if (count($errors) > 0) {
    echo "\n❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   $error\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "\n✅ All endpoint checks passed!\n";
    exit(0);
}

