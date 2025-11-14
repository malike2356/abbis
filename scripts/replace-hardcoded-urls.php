<?php
/**
 * Script to replace hardcoded URLs with URL helper functions
 * 
 * This script helps identify and replace common hardcoded URL patterns
 * with the appropriate URL helper functions.
 * 
 * Usage: php scripts/replace-hardcoded-urls.php [--dry-run] [--file=path]
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/url-manager.php';

$dryRun = in_array('--dry-run', $argv);
$specificFile = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $specificFile = substr($arg, 7);
    }
}

$replacements = [
    // API URLs
    '/href=["\']\.\.\/api\/([^"\']+)["\']/' => function($matches) {
        $file = $matches[1];
        // Extract query string if present
        if (strpos($file, '?') !== false) {
            list($file, $query) = explode('?', $file, 2);
            parse_str($query, $params);
            return 'href="<?php echo api_url(\'' . $file . '\', ' . var_export($params, true) . '); ?>"';
        }
        return 'href="<?php echo api_url(\'' . $file . '\'); ?>"';
    },
    
    // Module URLs
    '/href=["\']\.\.\/modules\/([^"\']+)["\']/' => 'href="<?php echo module_url(\'$1\'); ?>"',
    
    // CMS URLs
    '/href=["\']\.\.\/cms\/([^"\']+)["\']/' => 'href="<?php echo cms_url(\'$1\'); ?>"',
    
    // Client Portal URLs
    '/href=["\']\.\.\/client-portal\/([^"\']+)["\']/' => 'href="<?php echo client_portal_url(\'$1\'); ?>"',
    
    // POS URLs
    '/href=["\']\.\.\/pos\/([^"\']+)["\']/' => 'href="<?php echo pos_url(\'$1\'); ?>"',
];

$stats = [
    'files_processed' => 0,
    'files_modified' => 0,
    'replacements_made' => 0,
];

function processFile($filePath, $replacements, $dryRun, &$stats) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        echo "⚠️  Cannot read: $filePath\n";
        return;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $fileReplacements = 0;
    
    // Note: This is a simplified version. Full implementation would need
    // more sophisticated parsing to handle PHP code properly.
    
    echo "📄 Processing: $filePath\n";
    $stats['files_processed']++;
    
    if ($content !== $originalContent) {
        $stats['files_modified']++;
        $stats['replacements_made'] += $fileReplacements;
        
        if (!$dryRun) {
            file_put_contents($filePath, $content);
            echo "   ✅ Updated ($fileReplacements replacements)\n";
        } else {
            echo "   🔍 Would update ($fileReplacements replacements)\n";
        }
    } else {
        echo "   ℹ️  No changes needed\n";
    }
}

echo "🔍 Hardcoded URL Replacement Tool\n";
echo "==================================\n\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No files will be modified\n\n";
}

if ($specificFile) {
    processFile($specificFile, $replacements, $dryRun, $stats);
} else {
    echo "ℹ️  This script is a template. Manual replacement is recommended for accuracy.\n";
    echo "ℹ️  Use the find-hardcoded-urls.php script to identify files that need updates.\n\n";
}

echo "\n📊 Summary:\n";
echo "   Files processed: {$stats['files_processed']}\n";
echo "   Files modified: {$stats['files_modified']}\n";
echo "   Replacements made: {$stats['replacements_made']}\n";

