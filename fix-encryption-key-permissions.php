<?php
/**
 * Fix Encryption Key File Permissions
 * Run this script to fix permissions for the encryption key file
 */

$secretsDir = __DIR__ . '/config/secrets';
$keyFile = $secretsDir . '/encryption.key';

echo "Fixing encryption key file permissions...\n\n";

// Check if directory exists
if (!is_dir($secretsDir)) {
    echo "Creating secrets directory...\n";
    mkdir($secretsDir, 0777, true);
    echo "✓ Directory created\n";
} else {
    echo "✓ Directory exists\n";
}

// Set directory permissions
chmod($secretsDir, 0777);
echo "✓ Directory permissions set to 777\n";

// Check if file exists
if (file_exists($keyFile)) {
    echo "✓ Encryption key file exists\n";
    
    // Get current permissions
    $currentPerms = fileperms($keyFile);
    $currentPermsOct = substr(sprintf('%o', $currentPerms), -4);
    echo "  Current permissions: $currentPermsOct\n";
    
    // Try to make it writable
    if (chmod($keyFile, 0666)) {
        echo "✓ File permissions set to 666 (writable)\n";
    } else {
        echo "✗ Failed to change file permissions\n";
        echo "  You may need to run: sudo chmod 666 $keyFile\n";
    }
    
    // Get file owner
    $fileOwner = fileowner($keyFile);
    $ownerInfo = posix_getpwuid($fileOwner);
    $ownerName = $ownerInfo ? $ownerInfo['name'] : "UID $fileOwner";
    echo "  File owner: $ownerName\n";
    
    // Check current process user
    $processUser = posix_getpwuid(posix_geteuid());
    $processUserName = $processUser ? $processUser['name'] : 'unknown';
    echo "  Process user: $processUserName\n";
    
    if ($fileOwner !== posix_geteuid()) {
        echo "  ⚠️  File owner differs from process user\n";
        echo "  You may need to run: sudo chown $processUserName:$processUserName $keyFile\n";
    }
} else {
    echo "ℹ️  Encryption key file does not exist yet\n";
    echo "  It will be created when you save an encryption key through the web interface\n";
}

echo "\n✓ Done!\n";
echo "\nIf you're still having issues, try:\n";
echo "  sudo chown daemon:daemon $keyFile\n";
echo "  sudo chmod 666 $keyFile\n";
echo "Or:\n";
echo "  sudo chmod 777 $secretsDir\n";
echo "  sudo chown daemon:daemon $secretsDir\n";

