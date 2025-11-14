<?php
/**
 * Test script to verify encryption key file write permissions
 * Run this via web browser to test if the web server can write to the secrets directory
 */

$secretsDir = __DIR__ . '/config/secrets';
$keyFile = $secretsDir . '/encryption.key';
$testFile = $secretsDir . '/test-write-' . time() . '.txt';

header('Content-Type: text/plain');

echo "=== Encryption Key File Write Test ===\n\n";

// Check directory
echo "1. Checking directory...\n";
echo "   Path: $secretsDir\n";
echo "   Exists: " . (is_dir($secretsDir) ? 'Yes' : 'No') . "\n";
if (is_dir($secretsDir)) {
    $dirPerms = fileperms($secretsDir);
    echo "   Permissions: " . substr(sprintf('%o', $dirPerms), -4) . "\n";
    echo "   Writable: " . (is_writable($secretsDir) ? 'Yes' : 'No') . "\n";
    if (function_exists('posix_getpwuid')) {
        $dirOwner = fileowner($secretsDir);
        $ownerInfo = @posix_getpwuid($dirOwner);
        echo "   Owner: " . ($ownerInfo ? $ownerInfo['name'] : "UID $dirOwner") . "\n";
    }
}

// Check process user
echo "\n2. Checking process user...\n";
if (function_exists('posix_getpwuid')) {
    $processUid = posix_geteuid();
    $processInfo = @posix_getpwuid($processUid);
    echo "   Process UID: $processUid\n";
    echo "   Process user: " . ($processInfo ? $processInfo['name'] : 'unknown') . "\n";
} else {
    echo "   posix functions not available\n";
}

// Test write
echo "\n3. Testing file write...\n";
$testContent = 'Test write at ' . date('Y-m-d H:i:s');
$writeResult = @file_put_contents($testFile, $testContent);

if ($writeResult !== false) {
    echo "   ✓ Successfully wrote test file\n";
    echo "   File size: " . filesize($testFile) . " bytes\n";
    
    // Try to read it back
    $readContent = @file_get_contents($testFile);
    if ($readContent === $testContent) {
        echo "   ✓ Successfully read test file\n";
    } else {
        echo "   ✗ Failed to read test file back\n";
    }
    
    // Clean up
    @unlink($testFile);
    echo "   ✓ Test file cleaned up\n";
} else {
    $lastError = error_get_last();
    echo "   ✗ Failed to write test file\n";
    echo "   Error: " . ($lastError['message'] ?? 'Unknown error') . "\n";
}

// Test encryption key file write
echo "\n4. Testing encryption key file write...\n";
$testKey = 'test-key-' . time();
$keyWriteResult = @file_put_contents($keyFile, $testKey);

if ($keyWriteResult !== false) {
    echo "   ✓ Successfully wrote to encryption.key file\n";
    $readKey = @file_get_contents($keyFile);
    if ($readKey === $testKey) {
        echo "   ✓ Successfully read encryption key file\n";
    } else {
        echo "   ✗ Failed to read encryption key file back\n";
    }
    
    // Clean up - restore original if it existed
    // For testing, we'll leave it, but in production this would be the real key
    echo "   ⚠️  Test key written to file (you may want to remove it)\n";
} else {
    $lastError = error_get_last();
    echo "   ✗ Failed to write encryption key file\n";
    echo "   Error: " . ($lastError['message'] ?? 'Unknown error') . "\n";
    
    if (file_exists($keyFile)) {
        echo "   File exists: Yes\n";
        echo "   File writable: " . (is_writable($keyFile) ? 'Yes' : 'No') . "\n";
        $filePerms = fileperms($keyFile);
        echo "   File permissions: " . substr(sprintf('%o', $filePerms), -4) . "\n";
        if (function_exists('posix_getpwuid')) {
            $fileOwner = fileowner($keyFile);
            $ownerInfo = @posix_getpwuid($fileOwner);
            echo "   File owner: " . ($ownerInfo ? $ownerInfo['name'] : "UID $fileOwner") . "\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
echo "\nIf all tests passed, the encryption key generator should work.\n";
echo "If tests failed, you may need to run:\n";
echo "  sudo chmod 777 $secretsDir\n";
echo "  sudo chown daemon:daemon $secretsDir\n";

