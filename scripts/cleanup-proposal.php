<?php
/**
 * Cleanup Proposal Script (SAFE)
 * - Lists non-critical directories/files that can be archived to reduce footprint
 * - With --apply, moves candidates into ./archive/YYYYMMDD/ keeping structure
 */

date_default_timezone_set('Africa/Accra');
$root = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$archiveBase = '/home/malike/abbis-archives';
$archiveDir = $archiveBase . '/' . date('Ymd');

$candidates = [
    // XAMPP dashboard and sample assets not needed in production app
    ['path' => 'dashboard', 'reason' => 'XAMPP dashboard static docs/assets (not used by ABBIS app)'],
    // Web analytics reports folder
    ['path' => 'webalizer', 'reason' => 'Auto-generated web stats (not part of ABBIS)'],
    // Source dumps/archives (if present)
    ['path' => 'sources', 'reason' => 'Source archives/backups; keep outside web root'],
];

$fileCandidates = [
    ['path' => 'favicon.ico', 'reason' => 'Optional; ABBIS has its own favicon in assets/images'],
];

echo "\n=== ABBIS Cleanup Proposal ===\n\n";

$found = [];
foreach ($candidates as $item) {
    $p = $root . '/' . $item['path'];
    if (file_exists($p)) {
        $found[] = $item;
    }
}
foreach ($fileCandidates as $item) {
    $p = $root . '/' . $item['path'];
    if (file_exists($p)) {
        $found[] = $item;
    }
}

if (empty($found)) {
    echo "No cleanup candidates found. Project already clean.\n";
    exit(0);
}

echo "Candidates to archive:\n";
foreach ($found as $i => $item) {
    echo sprintf("  %2d) %-20s  — %s\n", $i + 1, $item['path'], $item['reason']);
}

if (!$apply) {
    echo "\nDRY RUN. To apply, run:\n  php scripts/cleanup-proposal.php --apply\n\n";
    // Safety advice
    echo "Notes:\n- Nothing will be deleted; items will be MOVED to $archiveBase/.\n- Files will be organized by date in subdirectories.\n";
    exit(0);
}

// Apply move
// Ensure base archive directory exists
if (!is_dir($archiveBase)) {
    if (!mkdir($archiveBase, 0755, true)) {
        fwrite(STDERR, "Failed to create archive base directory: $archiveBase\n");
        exit(1);
    }
    echo "Created archive base directory: $archiveBase\n";
}

if (!is_dir($archiveDir)) {
    if (!mkdir($archiveDir, 0755, true)) {
        fwrite(STDERR, "Failed to create archive directory: $archiveDir\n");
        exit(1);
    }
}

foreach ($found as $item) {
    $src = $root . '/' . $item['path'];
    $dst = $archiveDir . '/' . $item['path'];
    echo "Archiving {$item['path']} → $archiveBase/" . basename($archiveDir) . "/{$item['path']}\n";
    // Ensure dst parent exists
    @mkdir(dirname($dst), 0755, true);
    // Move (rename keeps metadata; works for files and directories)
    if (!@rename($src, $dst)) {
        fwrite(STDERR, "  ✗ Failed to move {$item['path']}\n");
    } else {
        echo "  ✓ Moved successfully\n";
    }
}

echo "\n✅ Cleanup complete!\n";
echo "Archived items are now at: $archiveBase/" . basename($archiveDir) . "/\n";
echo "Total items archived: " . count($found) . "\n";
exit(0);
?>


