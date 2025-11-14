<?php
/**
 * Find Hardcoded URLs Script
 * Scans the entire codebase for hardcoded URLs that should use the URL manager
 */

$basePath = __DIR__ . '/..';
$patterns = [
    // Hardcoded localhost URLs
    '/localhost:8080/',
    '/127\.0\.0\.1/',
    '/localhost/',
    
    // Hardcoded HTTP/HTTPS URLs
    '/http:\/\/[^\s\'"]+/',
    '/https:\/\/[^\s\'"]+/',
    
    // Hardcoded domain patterns
    '/kariboreholes\.com/',
    '/abbis\.veloxpsi\.com/',
    
    // Relative paths that should use URL helpers
    '/href=["\']\.\.\/[^"\']+["\']/',
    '/action=["\']\.\.\/[^"\']+["\']/',
    '/src=["\']\.\.\/[^"\']+["\']/',
    
    // Direct file paths in URLs
    '/["\']\/modules\/[^"\']+["\']/',
    '/["\']\/api\/[^"\']+["\']/',
    '/["\']\/cms\/[^"\']+["\']/',
    '/["\']\/client-portal\/[^"\']+["\']/',
    '/["\']\/pos\/[^"\']+["\']/',
];

$excludeDirs = [
    'vendor',
    'node_modules',
    '.git',
    'storage',
    'logs',
    'uploads',
    'database',
];

$excludeFiles = [
    'find-hardcoded-urls.php',
    'url-manager.php',
    'environment.php',
];

$results = [];

function shouldExclude($path, $excludeDirs, $excludeFiles) {
    foreach ($excludeDirs as $dir) {
        if (strpos($path, '/' . $dir . '/') !== false || strpos($path, '\\' . $dir . '\\') !== false) {
            return true;
        }
    }
    
    $filename = basename($path);
    foreach ($excludeFiles as $file) {
        if ($filename === $file) {
            return true;
        }
    }
    
    return false;
}

function scanFile($filePath, $patterns) {
    $content = file_get_contents($filePath);
    $matches = [];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $found, PREG_OFFSET_CAPTURE)) {
            foreach ($found[0] as $match) {
                $lineNum = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $matches[] = [
                    'pattern' => $pattern,
                    'match' => $match[0],
                    'line' => $lineNum
                ];
            }
        }
    }
    
    return $matches;
}

function scanDirectory($dir, $patterns, $excludeDirs, $excludeFiles, &$results) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $filePath = $file->getRealPath();
            
            if (shouldExclude($filePath, $excludeDirs, $excludeFiles)) {
                continue;
            }
            
            $ext = $file->getExtension();
            if (!in_array($ext, ['php', 'js', 'html', 'htm', 'css'])) {
                continue;
            }
            
            $matches = scanFile($filePath, $patterns);
            if (!empty($matches)) {
                $relativePath = str_replace($dir . '/', '', $filePath);
                $results[$relativePath] = $matches;
            }
        }
    }
}

echo "🔍 Scanning for hardcoded URLs...\n\n";

scanDirectory($basePath, $patterns, $excludeDirs, $excludeFiles, $results);

echo "📊 Results:\n";
echo str_repeat("=", 60) . "\n\n";

$totalFiles = count($results);
$totalMatches = 0;

foreach ($results as $file => $matches) {
    $totalMatches += count($matches);
    echo "📄 {$file}\n";
    foreach ($matches as $match) {
        echo "   Line {$match['line']}: {$match['match']}\n";
    }
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "Total files with hardcoded URLs: {$totalFiles}\n";
echo "Total matches found: {$totalMatches}\n";
echo "\n";

// Generate report file
$reportFile = $basePath . '/docs/HARDCODED_URLS_REPORT.md';
$report = "# Hardcoded URLs Report\n\n";
$report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
$report .= "## Summary\n\n";
$report .= "- Total files: {$totalFiles}\n";
$report .= "- Total matches: {$totalMatches}\n\n";
$report .= "## Files with Hardcoded URLs\n\n";

foreach ($results as $file => $matches) {
    $report .= "### {$file}\n\n";
    foreach ($matches as $match) {
        $report .= "- Line {$match['line']}: `{$match['match']}`\n";
    }
    $report .= "\n";
}

file_put_contents($reportFile, $report);
echo "✅ Report saved to: {$reportFile}\n";

