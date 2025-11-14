<?php
/**
 * Runtime test - verify URL helpers work in actual file context
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate a request context
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/abbis3.2/modules/test.php';
$_SERVER['HTTPS'] = '';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/url-manager.php';

echo "🔍 Runtime URL Helper Test\n";
echo "==========================\n\n";

$errors = [];
$success = [];

// Test that URL helpers work when included in files
$testFiles = [
    'modules/financial.php' => ['api_url', 'export.php'],
    'modules/system.php' => ['cms_url', 'client_portal_url'],
    'modules/config.php' => ['api_url'],
];

foreach ($testFiles as $file => $expectedHelpers) {
    $filePath = __DIR__ . '/../' . $file;
    if (!file_exists($filePath)) {
        $errors[] = "File not found: $file";
        continue;
    }
    
    $content = file_get_contents($filePath);
    $allFound = true;
    foreach ($expectedHelpers as $helper) {
        if (strpos($content, $helper) === false) {
            $allFound = false;
            $errors[] = "$file: Missing $helper() usage";
        }
    }
    
    if ($allFound) {
        $success[] = "$file: All expected helpers found";
        echo "✅ $file: All expected helpers found\n";
    }
}

echo "\n";

// Test URL generation with edge cases
echo "📋 Edge Case Tests\n";
echo "------------------\n";

$edgeCases = [
    'Empty params' => api_url('test.php', []),
    'Special chars in params' => api_url('test.php', ['name' => 'John Doe', 'email' => 'test@example.com']),
    'Numeric params' => api_url('test.php', ['id' => 123, 'page' => 1]),
    'Boolean params' => api_url('test.php', ['active' => true, 'deleted' => false]),
    'Array params' => api_url('test.php', ['tags' => ['a', 'b', 'c']]),
];

foreach ($edgeCases as $name => $url) {
    try {
        if (!empty($url)) {
            $success[] = "$name: Generated successfully";
            echo "✅ $name: " . substr($url, 0, 70) . "...\n";
        } else {
            $errors[] = "$name: Generated empty URL";
            echo "❌ $name: Generated empty URL\n";
        }
    } catch (Exception $e) {
        $errors[] = "$name: " . $e->getMessage();
        echo "❌ $name: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Verify URL structure
echo "📋 URL Structure Validation\n";
echo "----------------------------\n";

$testUrls = [
    api_url('test.php'),
    module_url('test.php'),
    cms_url('test.php'),
    client_portal_url('test.php'),
    pos_url('test.php'),
    site_url('test.php'),
];

foreach ($testUrls as $url) {
    // Check if URL starts with APP_URL base
    $appBase = parse_url(APP_URL, PHP_URL_SCHEME) . '://' . parse_url(APP_URL, PHP_URL_HOST);
    $port = parse_url(APP_URL, PHP_URL_PORT);
    if ($port) {
        $appBase .= ':' . $port;
    }
    
    if (strpos($url, $appBase) === 0) {
        $success[] = "URL structure correct: " . substr($url, 0, 50) . "...";
        echo "✅ URL structure correct: " . substr($url, 0, 50) . "...\n";
    } else {
        $errors[] = "URL structure incorrect: $url";
        echo "❌ URL structure incorrect: $url\n";
    }
}

echo "\n";

// Summary
echo "==========================\n";
echo "📊 SUMMARY\n";
echo "==========================\n";
echo "✅ Success: " . count($success) . "\n";
echo "❌ Errors: " . count($errors) . "\n\n";

if (count($errors) > 0) {
    echo "❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   $error\n";
    }
    exit(1);
} else {
    echo "🎉 All runtime tests passed!\n";
    exit(0);
}

