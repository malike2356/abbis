<?php
/**
 * Log Cleanup Script
 * Removes old log files and rotates logs to prevent disk space issues
 * 
 * Usage: php scripts/cleanup-logs.php [--days=30] [--dry-run]
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$options = getopt('', ['days:', 'dry-run', 'help']);
$daysToKeep = isset($options['days']) ? (int)$options['days'] : 30;
$dryRun = isset($options['dry-run']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo "Log Cleanup Script\n";
    echo "==================\n\n";
    echo "Usage: php scripts/cleanup-logs.php [options]\n\n";
    echo "Options:\n";
    echo "  --days=N      Keep logs for N days (default: 30)\n";
    echo "  --dry-run     Show what would be deleted without actually deleting\n";
    echo "  --help        Show this help message\n\n";
    exit(0);
}

$logPath = LOG_PATH;
if (!is_dir($logPath)) {
    echo "Error: Log directory does not exist: {$logPath}\n";
    exit(1);
}

$cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
$deletedCount = 0;
$deletedSize = 0;
$errors = [];

echo "Log Cleanup Script\n";
echo "==================\n";
echo "Log directory: {$logPath}\n";
echo "Keeping logs newer than: " . date('Y-m-d H:i:s', $cutoffTime) . " ({$daysToKeep} days)\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no files will be deleted)" : "LIVE") . "\n\n";

$logFiles = glob($logPath . '/*.log');
if (empty($logFiles)) {
    echo "No log files found.\n";
    exit(0);
}

foreach ($logFiles as $file) {
    $fileTime = filemtime($file);
    $fileSize = filesize($file);
    $fileAge = time() - $fileTime;
    $fileAgeDays = round($fileAge / (24 * 60 * 60), 1);
    
    if ($fileTime < $cutoffTime) {
        $deletedCount++;
        $deletedSize += $fileSize;
        
        $sizeFormatted = formatBytes($fileSize);
        $fileName = basename($file);
        
        echo sprintf(
            "[%s] %s (%.1f days old, %s)\n",
            $dryRun ? 'WOULD DELETE' : 'DELETING',
            $fileName,
            $fileAgeDays,
            $sizeFormatted
        );
        
        if (!$dryRun) {
            if (unlink($file)) {
                // Success
            } else {
                $errors[] = "Failed to delete: {$fileName}";
                echo "  ERROR: Could not delete file\n";
            }
        }
    } else {
        echo sprintf(
            "[KEEP] %s (%.1f days old)\n",
            basename($file),
            $fileAgeDays
        );
    }
}

echo "\n";
echo "Summary:\n";
echo "--------\n";
echo "Files " . ($dryRun ? "that would be deleted" : "deleted") . ": {$deletedCount}\n";
echo "Space " . ($dryRun ? "that would be freed" : "freed") . ": " . formatBytes($deletedSize) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

if ($dryRun) {
    echo "\nThis was a dry run. Run without --dry-run to actually delete files.\n";
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

