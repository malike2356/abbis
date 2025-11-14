<?php
/**
 * Test if secrets directory is writable
 * Run this via web browser to test permissions
 */
$secretsDir = __DIR__ . '/config/secrets';
$testFile = $secretsDir . '/test-write.txt';

echo "<h1>Secrets Directory Write Test</h1>";
echo "<p>Directory: <code>$secretsDir</code></p>";

// Check if directory exists
if (!is_dir($secretsDir)) {
    echo "<p style='color: red;'>❌ Directory does not exist!</p>";
    echo "<p>Creating directory...</p>";
    if (@mkdir($secretsDir, 0777, true)) {
        echo "<p style='color: green;'>✓ Directory created</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create directory</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>✓ Directory exists</p>";
}

// Check permissions
$perms = fileperms($secretsDir);
echo "<p>Permissions: <code>" . substr(sprintf('%o', $perms), -4) . "</code></p>";

// Check if writable
if (is_writable($secretsDir)) {
    echo "<p style='color: green;'>✓ Directory is writable</p>";
    
    // Try to write a test file
    if (@file_put_contents($testFile, 'test')) {
        echo "<p style='color: green;'>✓ Successfully wrote test file</p>";
        @unlink($testFile);
        echo "<p style='color: green;'>✓ Test file deleted</p>";
        echo "<p><strong>✅ The secrets directory is properly configured!</strong></p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to write test file</p>";
        echo "<p>Error: " . error_get_last()['message'] . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Directory is NOT writable</p>";
    echo "<p>Run: <code>chmod 777 $secretsDir</code></p>";
}

