<?php
/**
 * Server-Side Update Script
 * 
 * This script safely updates ABBIS on the production server.
 * It backs up current files, extracts new files, and sets permissions.
 * 
 * Usage: php scripts/deploy/update-server.php
 */

$rootPath = dirname(dirname(__DIR__));
chdir($rootPath);

echo "🔄 ABBIS Server Update Script\n";
echo "==============================\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    die("❌ This script must be run from command line or with ?run=1 parameter\n");
}

// Safety check - require confirmation in web mode
if (php_sapi_name() !== 'cli' && (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes')) {
    echo "<h1>ABBIS Update Script</h1>";
    echo "<p>⚠️ <strong>Warning:</strong> This will update your ABBIS installation.</p>";
    echo "<p>Make sure you have:</p>";
    echo "<ul>";
    echo "<li>✅ Backed up your database</li>";
    echo "<li>✅ Backed up your files</li>";
    echo "<li>✅ Uploaded the update package</li>";
    echo "</ul>";
    echo "<p><a href='?run=1&confirm=yes' style='background: #dc2626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>⚠️ Confirm and Run Update</a></p>";
    exit;
}

echo "📋 Starting update process...\n\n";

// Step 1: Create backup
echo "1️⃣ Creating backup...\n";
$backupDir = $rootPath . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupName = 'backup-' . date('Y-m-d-His');
$backupPath = $backupDir . '/' . $backupName;

// Backup critical directories
$criticalDirs = ['config', 'includes', 'modules', 'api', 'client-portal'];
foreach ($criticalDirs as $dir) {
    $source = $rootPath . '/' . $dir;
    if (is_dir($source)) {
        $dest = $backupPath . '/' . $dir;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }
        copyDirectory($source, $dest);
        echo "   ✓ Backed up: {$dir}\n";
    }
}

echo "   ✅ Backup created: {$backupName}\n\n";

// Step 2: Check for update package
echo "2️⃣ Looking for update package...\n";
$packageDir = $rootPath . '/deployment-packages';
$updatePackage = null;

if (is_dir($packageDir)) {
    $files = scandir($packageDir);
    rsort($files); // Newest first
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $updatePackage = $packageDir . '/' . $file;
            echo "   ✓ Found: {$file}\n";
            break;
        }
    }
}

if (!$updatePackage) {
    // Check if ZIP is in root directory
    $rootFiles = glob($rootPath . '/*.zip');
    if (!empty($rootFiles)) {
        rsort($rootFiles);
        $updatePackage = $rootFiles[0];
        echo "   ✓ Found in root: " . basename($updatePackage) . "\n";
    }
}

if (!$updatePackage || !file_exists($updatePackage)) {
    echo "   ⚠️  No update package found.\n";
    echo "   Please upload the update ZIP file to:\n";
    echo "   - {$packageDir}/ (preferred)\n";
    echo "   - {$rootPath}/ (root directory)\n\n";
    echo "   Then run this script again.\n";
    exit(1);
}

// Step 3: Extract update package
echo "\n3️⃣ Extracting update package...\n";

$zip = new ZipArchive();
if ($zip->open($updatePackage) !== TRUE) {
    die("   ❌ Error: Cannot open ZIP file\n");
}

// Files/directories to preserve (don't overwrite)
$preservePaths = [
    'config/deployment.php',
    'config/secrets',
    'config/super-admin.php',
    'uploads',
    'storage',
    'logs'
];

$extractedCount = 0;
$preservedCount = 0;

for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    
    // Skip directories
    if (substr($filename, -1) === '/') {
        continue;
    }
    
    // Check if file should be preserved
    $shouldPreserve = false;
    foreach ($preservePaths as $preserve) {
        if (strpos($filename, $preserve) === 0) {
            $shouldPreserve = true;
            break;
        }
    }
    
    if ($shouldPreserve) {
        $preservedCount++;
        echo "   ⊘ Preserved: {$filename}\n";
        continue;
    }
    
    // Extract file
    $targetPath = $rootPath . '/' . $filename;
    $targetDir = dirname($targetPath);
    
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Extract file from ZIP using stream_copy_to_stream (copy() doesn't work with streams)
    $stream = $zip->getStream($filename);
    if ($stream) {
        $targetFile = fopen($targetPath, 'w');
        if ($targetFile) {
            stream_copy_to_stream($stream, $targetFile);
            fclose($targetFile);
        }
        fclose($stream);
    }
    $extractedCount++;
    
    if ($extractedCount % 50 == 0) {
        echo "   ✓ Extracted {$extractedCount} files...\n";
    }
}

$zip->close();
echo "   ✅ Extracted {$extractedCount} files\n";
echo "   ⊘ Preserved {$preservedCount} files\n\n";

// Step 4: Set permissions
echo "4️⃣ Setting file permissions...\n";
setPermissions($rootPath);
echo "   ✅ Permissions set\n\n";

// Step 5: Verify installation
echo "5️⃣ Verifying installation...\n";
$checks = [
    'config/app.php' => 'Configuration file',
    'includes/functions.php' => 'Core functions',
    'modules/dashboard.php' => 'Dashboard module',
    'index.php' => 'Entry point'
];

$allGood = true;
foreach ($checks as $file => $description) {
    $path = $rootPath . '/' . $file;
    if (file_exists($path)) {
        echo "   ✓ {$description}\n";
    } else {
        echo "   ❌ Missing: {$description}\n";
        $allGood = false;
    }
}

if ($allGood) {
    echo "\n✅ Update completed successfully!\n\n";
    echo "📋 Next Steps:\n";
    echo "   1. Clear browser cache\n";
    echo "   2. Test your site\n";
    echo "   3. Check error logs if any issues\n\n";
    echo "💾 Backup location: {$backupPath}\n";
} else {
    echo "\n⚠️  Update completed with warnings.\n";
    echo "   Please check the missing files above.\n";
}

// Helper functions
function copyDirectory($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $sourceFile = $source . '/' . $file;
            $destFile = $dest . '/' . $file;
            
            if (is_dir($sourceFile)) {
                copyDirectory($sourceFile, $destFile);
            } else {
                copy($sourceFile, $destFile);
            }
        }
    }
    closedir($dir);
}

function setPermissions($rootPath) {
    $dirs = [
        'uploads' => 0755,
        'uploads/profiles' => 0755,
        'uploads/logos' => 0755,
        'storage' => 0755,
        'logs' => 0755
    ];
    
    foreach ($dirs as $dir => $perm) {
        $path = $rootPath . '/' . $dir;
        if (is_dir($path)) {
            chmod($path, $perm);
        }
    }
}

