<?php
/**
 * Fix Duplicate Rig Assets
 * Identifies and helps remove duplicate rig entries in the assets table
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

header('Content-Type: text/plain');

echo "===========================================\n";
echo "Duplicate Rig Assets Check\n";
echo "===========================================\n\n";

// Get all rigs from Configuration
require_once __DIR__ . '/../includes/config-manager.php';
$configManager = new ConfigManager();
$rigs = $configManager->getRigs();

echo "Rigs in Configuration:\n";
foreach ($rigs as $rig) {
    echo "  - {$rig['rig_code']} / {$rig['rig_name']}\n";
}
echo "\n";

// Get all assets from assets table
$assets = $pdo->query("SELECT id, asset_code, asset_name, asset_type FROM assets ORDER BY asset_name ASC")->fetchAll(PDO::FETCH_ASSOC);

echo "Assets in assets table:\n";
foreach ($assets as $asset) {
    echo "  - ID: {$asset['id']}, Code: {$asset['asset_code']}, Name: {$asset['asset_name']}, Type: {$asset['asset_type']}\n";
}
echo "\n";

// Find duplicates
$duplicates = [];
foreach ($rigs as $rig) {
    $rigCodeUpper = strtoupper(trim($rig['rig_code']));
    $rigNameUpper = strtoupper(trim($rig['rig_name']));
    
    $matchingAssets = [];
    foreach ($assets as $asset) {
        $assetCodeUpper = !empty($asset['asset_code']) ? strtoupper(trim($asset['asset_code'])) : '';
        $assetNameUpper = !empty($asset['asset_name']) ? strtoupper(trim($asset['asset_name'])) : '';
        
        // Check if this asset matches the rig
        if (($assetCodeUpper === $rigCodeUpper || $assetNameUpper === $rigNameUpper) && 
            ($asset['asset_type'] === 'rig' || $asset['asset_type'] === 'Rig')) {
            $matchingAssets[] = $asset;
        }
    }
    
    if (count($matchingAssets) > 0) {
        $duplicates[] = [
            'rig' => $rig,
            'assets' => $matchingAssets
        ];
    }
}

if (empty($duplicates)) {
    echo "✅ No duplicates found. All rigs are properly synced.\n";
    exit(0);
}

echo "===========================================\n";
echo "DUPLICATES FOUND\n";
echo "===========================================\n\n";

foreach ($duplicates as $dup) {
    $rig = $dup['rig'];
    $matchingAssets = $dup['assets'];
    
    echo "Rig: {$rig['rig_code']} / {$rig['rig_name']}\n";
    echo "Found in assets table:\n";
    foreach ($matchingAssets as $asset) {
        echo "  - ID: {$asset['id']}, Code: {$asset['asset_code']}, Name: {$asset['asset_name']}\n";
    }
    echo "\n";
}

echo "===========================================\n";
echo "RECOMMENDATION\n";
echo "===========================================\n\n";
echo "Rigs should be managed in System → Configuration, not manually added to Assets.\n";
echo "The duplicate check will now prevent this from happening.\n\n";
echo "To fix:\n";
echo "1. Delete manually added rig assets from the assets table\n";
echo "2. Rigs should come from Configuration dynamically (they're added automatically in the display)\n\n";

$autoFix = isset($argv[1]) && ($argv[1] === '--fix' || $argv[1] === '-f');

if ($autoFix) {
    echo "===========================================\n";
    echo "AUTO-FIXING DUPLICATES\n";
    echo "===========================================\n\n";
    
    $pdo->beginTransaction();
    
    try {
        $deleted = 0;
        foreach ($duplicates as $dup) {
            $rig = $dup['rig'];
            $matchingAssets = $dup['assets'];
            
            // Delete all matching assets - rigs should come from Configuration dynamically, not be stored in assets table
            foreach ($matchingAssets as $asset) {
                $deleteStmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
                $deleteStmt->execute([$asset['id']]);
                echo "✓ Deleted duplicate asset ID {$asset['id']} ({$asset['asset_code']} / {$asset['asset_name']})\n";
                $deleted++;
            }
        }
        
        $pdo->commit();
        
        echo "\n===========================================\n";
        echo "Fix Complete!\n";
        echo "===========================================\n";
        echo "Deleted {$deleted} duplicate assets.\n";
        echo "===========================================\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Error: " . $e->getMessage() . "\n";
        echo "All changes rolled back.\n";
        exit(1);
    }
} else {
    echo "To automatically remove duplicates, run:\n";
    echo "  php scripts/fix-duplicate-rig-assets.php --fix\n\n";
}

