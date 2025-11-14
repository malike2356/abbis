<?php
/**
 * Create Deployment Package
 * 
 * This script creates a deployment package (ZIP file) that can be uploaded
 * to your production server for easy updates.
 * 
 * Usage: php scripts/deploy/create-package.php
 */

$rootPath = dirname(dirname(__DIR__));
chdir($rootPath);

echo "📦 ABBIS Deployment Package Creator\n";
echo "====================================\n\n";

// Configuration
$packageName = 'abbis-update-' . date('Y-m-d-His');
$packageDir = $rootPath . '/deployment-packages';
$packagePath = $packageDir . '/' . $packageName . '.zip';

// Create package directory
if (!is_dir($packageDir)) {
    mkdir($packageDir, 0755, true);
}

// Files/directories to include
$includePaths = [
    'api',
    'assets',
    'client-portal',
    'cms',
    'config',
    'includes',
    'modules',
    'pos',
    'offline',
    'tools',
    'docs',
    'database',
    'scripts',
    '.htaccess',
    'index.php',
    'login.php',
    'logout.php',
    'manifest.webmanifest',
    'sw.js',
    'README.md'
];

// Files/directories to exclude
$excludePaths = [
    '.git',
    '.gitignore',
    'node_modules',
    'vendor',
    'deployment-packages',
    'logs',
    'storage',
    'uploads',
    'config/deployment.php',
    'config/secrets',
    'config/super-admin.php',
    '*.tmp',
    '*.bak',
    '*.backup',
    '*.log'
];

echo "📋 Creating deployment package...\n";
echo "   Package name: {$packageName}.zip\n\n";

// Create ZIP file
$zip = new ZipArchive();
if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("❌ Error: Cannot create ZIP file\n");
}

$fileCount = 0;
$dirCount = 0;

function addToZip($zip, $source, $basePath, $excludePaths, &$fileCount, &$dirCount) {
    if (is_file($source)) {
        // Check if file should be excluded
        $shouldExclude = false;
        foreach ($excludePaths as $exclude) {
            if (strpos($source, $exclude) !== false) {
                $shouldExclude = true;
                break;
            }
        }
        
        if (!$shouldExclude) {
            $relativePath = str_replace($basePath . '/', '', $source);
            $zip->addFile($source, $relativePath);
            $fileCount++;
            if ($fileCount % 50 == 0) {
                echo "   Added {$fileCount} files...\n";
            }
        }
    } elseif (is_dir($source)) {
        // Check if directory should be excluded
        $shouldExclude = false;
        $dirName = basename($source);
        foreach ($excludePaths as $exclude) {
            if ($dirName === $exclude || strpos($source, $exclude) !== false) {
                $shouldExclude = true;
                break;
            }
        }
        
        if (!$shouldExclude) {
            $dirCount++;
            $files = scandir($source);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    addToZip($zip, $source . '/' . $file, $basePath, $excludePaths, $fileCount, $dirCount);
                }
            }
        }
    }
}

// Add files to ZIP
foreach ($includePaths as $path) {
    $fullPath = $rootPath . '/' . $path;
    if (file_exists($fullPath)) {
        addToZip($zip, $fullPath, $rootPath, $excludePaths, $fileCount, $dirCount);
    }
}

$zip->close();

// Get package size
$packageSize = filesize($packagePath);
$packageSizeMB = round($packageSize / 1024 / 1024, 2);

echo "\n✅ Package created successfully!\n\n";
echo "📊 Package Details:\n";
echo "   File: {$packageName}.zip\n";
echo "   Location: {$packagePath}\n";
echo "   Size: {$packageSizeMB} MB\n";
echo "   Files: {$fileCount}\n";
echo "   Directories: {$dirCount}\n\n";

// Create update instructions file
$instructions = <<<INSTRUCTIONS
ABBIS Update Package
====================

Package Name: {$packageName}
Created: " . date('Y-m-d H:i:s') . "
Size: {$packageSizeMB} MB

UPDATE INSTRUCTIONS:
====================

1. Upload this ZIP file to your server (via cPanel File Manager or FTP)
   Location: /public_html/abbis3.2/ (or your ABBIS directory)

2. Extract the ZIP file on the server

3. Run the update script:
   php scripts/deploy/update-server.php

   OR via cPanel Terminal:
   cd /path/to/abbis3.2
   php scripts/deploy/update-server.php

4. The script will:
   - Backup current files
   - Extract new files
   - Set proper permissions
   - Verify installation

5. Clear browser cache and test your site

IMPORTANT:
- Keep your config/deployment.php file (it won't be overwritten)
- Keep your uploads/ directory (it won't be overwritten)
- Database changes (if any) need to be applied separately

For detailed instructions, see: docs/DEPLOYMENT_UPDATE_GUIDE.md
INSTRUCTIONS;

file_put_contents($packageDir . '/' . $packageName . '-INSTRUCTIONS.txt', $instructions);

echo "📄 Instructions file created: {$packageName}-INSTRUCTIONS.txt\n\n";
echo "🚀 Next Steps:\n";
echo "   1. Upload {$packageName}.zip to your server\n";
echo "   2. Extract it in your ABBIS directory\n";
echo "   3. Run: php scripts/deploy/update-server.php\n\n";
echo "✅ Done!\n";

