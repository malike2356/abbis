<?php
/**
 * Link Verification Script
 * Tests all system links after restructuring
 */

require_once __DIR__ . '/../config/app.php';

$basePath = __DIR__ . '/..';
$baseUrl = 'http://localhost:8080/abbis3.2';
$errors = [];
$warnings = [];
$success = [];

echo "🔍 ABBIS Link Verification Script\n";
echo "===================================\n\n";

// Test 1: Client Portal Files
echo "📁 Testing Client Portal Files...\n";
$clientPortalFiles = [
    'client-portal/login.php',
    'client-portal/dashboard.php',
    'client-portal/auth-check.php',
    'client-portal/logout.php',
    'client-portal/header.php',
    'client-portal/footer.php',
    'client-portal/quotes.php',
    'client-portal/invoices.php',
    'client-portal/payments.php',
    'client-portal/payment-gateway.php',
    'client-portal/payment-callback.php',
    'client-portal/process-payment.php',
    'client-portal/profile.php',
    'client-portal/projects.php',
    'client-portal/quote-detail.php',
    'client-portal/quote-approve.php',
    'client-portal/invoice-detail.php',
    'client-portal/download.php',
    'client-portal/client-styles.css',
];

foreach ($clientPortalFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $success[] = "✓ $file exists";
    } else {
        $errors[] = "✗ $file MISSING";
    }
}

// Test 2: POS API Files
echo "\n📁 Testing POS API Files...\n";
$posApiFiles = [
    'pos/api/sales.php',
    'pos/api/catalog.php',
    'pos/api/inventory.php',
    'pos/api/reports.php',
    'pos/api/settings.php',
    'pos/api/store-stock.php',
    'pos/api/sync-inventory.php',
    'pos/api/transfer-materials.php',
    'pos/api/customers.php',
    'pos/api/drawer.php',
    'pos/api/receipt.php',
    'pos/api/refunds.php',
    'pos/api/approvals.php',
    'pos/api/charts.php',
    'pos/api/export.php',
    'pos/api/gift-cards.php',
    'pos/api/loyalty.php',
    'pos/api/holds.php',
    'pos/api/promotions.php',
    'pos/api/material-returns.php',
    'pos/api/shifts.php',
    'pos/api/health.php',
    'pos/api/settings.php',
];

foreach ($posApiFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $success[] = "✓ $file exists";
    } else {
        $warnings[] = "⚠ $file not found (may be optional)";
    }
}

// Test 3: Check for old paths
echo "\n🔍 Checking for old path references...\n";
$oldPaths = [
    'cms/client',
    'api/pos',
];

$filesToCheck = [
    'includes/header.php',
    'includes/sso.php',
    'login.php',
    'modules/pos.php',
    'modules/resources.php',
    'modules/help.php',
    'assets/js/field-reports.js',
    'assets/js/offline-reports.js',
    'client-portal/header.php',
    'client-portal/payment-gateway.php',
    'client-portal/process-payment.php',
    'includes/ClientPortal/ClientPaymentService.php',
];

foreach ($filesToCheck as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        foreach ($oldPaths as $oldPath) {
            if (strpos($content, $oldPath) !== false) {
                $errors[] = "✗ $file still contains reference to old path: $oldPath";
            }
        }
    }
}

// Test 4: Verify includes/requires
echo "\n🔗 Testing include/require paths...\n";
$includeTests = [
    [
        'file' => 'client-portal/auth-check.php',
        'pattern' => '/require.*config\/app\.php/',
        'description' => 'auth-check.php includes config'
    ],
    [
        'file' => 'client-portal/header.php',
        'pattern' => "/app_url\('client-portal/",
        'description' => 'header.php uses client-portal path'
    ],
    [
        'file' => 'modules/pos.php',
        'pattern' => '/pos\/api\//',
        'description' => 'pos.php uses pos/api path'
    ],
];

foreach ($includeTests as $test) {
    $fullPath = $basePath . '/' . $test['file'];
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $pattern = $test['pattern'];
        if (preg_match($pattern, $content)) {
            $success[] = "✓ {$test['description']}";
        } else {
            $warnings[] = "⚠ {$test['description']} - pattern not found";
        }
    }
}

// Test 5: Check for broken symlinks
echo "\n🔗 Checking for broken symlinks...\n";
$symlinkCheck = shell_exec("find $basePath -type l ! -exec test -e {} \; -print 2>/dev/null");
$symlinkCheck = $symlinkCheck ? trim($symlinkCheck) : '';
if (!empty($symlinkCheck)) {
    $errors[] = "✗ Broken symlinks found:\n" . $symlinkCheck;
} else {
    $success[] = "✓ No broken symlinks";
}

// Test 6: Verify directory structure
echo "\n📂 Verifying directory structure...\n";
$requiredDirs = [
    'client-portal',
    'pos/api',
    'cms',
];

foreach ($requiredDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (is_dir($fullPath)) {
        $success[] = "✓ Directory $dir exists";
    } else {
        $errors[] = "✗ Directory $dir MISSING";
    }
}

// Test 7: Check for old directories that should be removed
echo "\n🗑️  Checking for old directories that should be removed...\n";
$oldDirs = [
    'cms/client',
    'api/pos',
];

foreach ($oldDirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (is_dir($fullPath) || file_exists($fullPath)) {
        $errors[] = "✗ Old directory still exists: $dir (should be removed)";
    } else {
        $success[] = "✓ Old directory $dir properly removed";
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
    echo "\n✅ All critical checks passed!\n";
    exit(0);
}

