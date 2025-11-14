<?php
/**
 * Comprehensive test script to verify all URL changes are working correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/url-manager.php';

echo "🔍 ABBIS URL Changes Verification Script\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Test 1: Verify URL helper functions exist and work
echo "📋 Test 1: URL Helper Functions\n";
echo "--------------------------------\n";

$tests = [
    'api_url' => function() {
        return api_url('test.php');
    },
    'api_url with params' => function() {
        return api_url('test.php', ['param1' => 'value1', 'param2' => 'value2']);
    },
    'module_url' => function() {
        return module_url('test.php');
    },
    'module_url with params' => function() {
        return module_url('test.php', ['id' => 123]);
    },
    'cms_url' => function() {
        return cms_url('test.php');
    },
    'cms_url with params' => function() {
        return cms_url('test.php', ['page' => 'home']);
    },
    'client_portal_url' => function() {
        return client_portal_url('test.php');
    },
    'pos_url' => function() {
        return pos_url('test.php');
    },
    'site_url' => function() {
        return site_url('test.php');
    },
];

foreach ($tests as $name => $test) {
    try {
        $result = $test();
        if (empty($result)) {
            $errors[] = "❌ $name: Returned empty string";
            echo "   ❌ $name: Returned empty string\n";
        } else {
            $success[] = "✅ $name: " . substr($result, 0, 60) . "...";
            echo "   ✅ $name: " . substr($result, 0, 60) . "...\n";
        }
    } catch (Exception $e) {
        $errors[] = "❌ $name: " . $e->getMessage();
        echo "   ❌ $name: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        $errors[] = "❌ $name: " . $e->getMessage();
        echo "   ❌ $name: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 2: Check modified files for syntax errors
echo "📋 Test 2: Syntax Check on Modified Files\n";
echo "------------------------------------------\n";

$modifiedFiles = [
    'modules/financial.php',
    'modules/field-reports-list.php',
    'modules/system.php',
    'modules/config.php',
    'modules/field-reports.php',
    'modules/resources.php',
    'modules/regulatory-forms.php',
    'modules/data-management.php',
    'modules/job-planner.php',
    'modules/crm-client-detail.php',
    'modules/crm-emails.php',
    'modules/finance.php',
    'modules/payroll.php',
    'modules/payslip.php',
    'modules/legal-documents.php',
    'modules/profile.php',
    'modules/geology-estimator.php',
    'modules/looker-studio-integration.php',
    'api/sync-offline-reports.php',
    'api/bulk-payslips.php',
];

foreach ($modifiedFiles as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (!file_exists($filePath)) {
        $warnings[] = "⚠️  $file: File not found";
        echo "   ⚠️  $file: File not found\n";
        continue;
    }
    
    // Check syntax
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnVar);
    
    if ($returnVar === 0) {
        $success[] = "✅ $file: Syntax OK";
        echo "   ✅ $file: Syntax OK\n";
    } else {
        $errorMsg = implode("\n", $output);
        $errors[] = "❌ $file: Syntax error\n$errorMsg";
        echo "   ❌ $file: Syntax error\n";
        echo "      " . implode("\n      ", $output) . "\n";
    }
}

echo "\n";

// Test 3: Verify URL helper functions are included in modified files
echo "📋 Test 3: URL Helper Inclusion Check\n";
echo "--------------------------------------\n";

$filesNeedingUrlManager = [
    'modules/field-reports-list.php',
    'modules/looker-studio-integration.php',
    'api/bulk-payslips.php',
];

foreach ($filesNeedingUrlManager as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    if (strpos($content, 'url-manager.php') !== false || 
        strpos($content, 'api_url') !== false ||
        strpos($content, 'module_url') !== false) {
        $success[] = "✅ $file: Uses URL helpers";
        echo "   ✅ $file: Uses URL helpers\n";
    } else {
        $warnings[] = "⚠️  $file: May need url-manager.php include";
        echo "   ⚠️  $file: May need url-manager.php include\n";
    }
}

echo "\n";

// Test 4: Check for common URL patterns that should be replaced
echo "📋 Test 4: Check for Remaining Hardcoded URLs\n";
echo "-----------------------------------------------\n";

$patterns = [
    '/href=["\']\.\.\/api\//' => 'Relative API URLs',
    '/action=["\']\.\.\/api\//' => 'Form actions to API',
    '/href=["\']\.\.\/modules\//' => 'Relative module URLs',
    '/href=["\']\.\.\/cms\//' => 'Relative CMS URLs',
];

$filesToCheck = [
    'modules/financial.php',
    'modules/field-reports-list.php',
    'modules/system.php',
    'modules/config.php',
];

foreach ($filesToCheck as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    foreach ($patterns as $pattern => $description) {
        if (preg_match($pattern, $content)) {
            $warnings[] = "⚠️  $file: Still contains $description";
            echo "   ⚠️  $file: Still contains $description\n";
        }
    }
}

echo "\n";

// Test 5: Verify APP_URL is set correctly
echo "📋 Test 5: Configuration Check\n";
echo "-------------------------------\n";

if (defined('APP_URL') && !empty(APP_URL)) {
    $success[] = "✅ APP_URL is defined: " . APP_URL;
    echo "   ✅ APP_URL is defined: " . APP_URL . "\n";
} else {
    $errors[] = "❌ APP_URL is not defined or empty";
    echo "   ❌ APP_URL is not defined or empty\n";
}

if (file_exists(__DIR__ . '/../config/deployment.php')) {
    $success[] = "✅ deployment.php exists";
    echo "   ✅ deployment.php exists\n";
} else {
    $warnings[] = "⚠️  deployment.php does not exist (use deployment.php.example as template)";
    echo "   ⚠️  deployment.php does not exist (use deployment.php.example as template)\n";
}

echo "\n";

// Test 6: Test actual URL generation with different scenarios
echo "📋 Test 6: URL Generation Scenarios\n";
echo "-------------------------------------\n";

$scenarios = [
    'API endpoint' => api_url('export.php', ['module' => 'reports', 'format' => 'csv']),
    'Module page' => module_url('dashboard.php'),
    'CMS admin' => cms_url('admin/index.php'),
    'Client portal' => client_portal_url('login.php'),
    'POS endpoint' => pos_url('api/inventory.php'),
    'Site asset' => site_url('assets/css/styles.css'),
];

foreach ($scenarios as $name => $url) {
    if (filter_var($url, FILTER_VALIDATE_URL) || strpos($url, '/') === 0) {
        $success[] = "✅ $name: " . substr($url, 0, 60) . "...";
        echo "   ✅ $name: " . substr($url, 0, 60) . "...\n";
    } else {
        $errors[] = "❌ $name: Invalid URL - $url";
        echo "   ❌ $name: Invalid URL - $url\n";
    }
}

echo "\n";

// Summary
echo "========================================\n";
echo "📊 SUMMARY\n";
echo "========================================\n";
echo "✅ Success: " . count($success) . " checks passed\n";
echo "⚠️  Warnings: " . count($warnings) . "\n";
echo "❌ Errors: " . count($errors) . "\n\n";

if (count($errors) > 0) {
    echo "❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
    echo "\n";
}

if (count($errors) === 0 && count($warnings) === 0) {
    echo "🎉 All tests passed! URL changes are working correctly.\n";
    exit(0);
} else {
    echo "⚠️  Some issues found. Please review above.\n";
    exit(1);
}

