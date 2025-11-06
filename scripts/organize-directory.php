<?php
/**
 * Directory Organization Script
 * Safely organizes the ABBIS directory structure
 */

$baseDir = dirname(__DIR__);
$errors = [];
$moved = [];

echo "=== ABBIS Directory Organization ===\n\n";

// 1. Create necessary directories
$directories = [
    'docs/implementation',
    'docs/analysis',
    'docs/guides',
    'docs/status',
    'storage/logs',
    'storage/cache',
    'storage/temp'
];

foreach ($directories as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    if (!file_exists($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            echo "✓ Created directory: $dir\n";
        } else {
            $errors[] = "Failed to create directory: $dir";
            echo "✗ Failed to create directory: $dir\n";
        }
    }
}

// 2. Move documentation files to appropriate subdirectories
$docMappings = [
    // Implementation docs
    'IMPLEMENTATION_COMPLETE.md' => 'docs/implementation/',
    'IMPLEMENTATION_STATUS.md' => 'docs/implementation/',
    'HR_IMPLEMENTATION_COMPLETE.md' => 'docs/implementation/',
    'HR_IMPLEMENTATION_STATUS.md' => 'docs/implementation/',
    'RESOURCES_REBUILD_COMPLETE.md' => 'docs/implementation/',
    'RIG_INTEGRATION_COMPLETE.md' => 'docs/implementation/',
    'WORKER_STANDARDIZATION_COMPLETE.md' => 'docs/implementation/',
    'WORKER_RIG_ROLE_MAPPING_IMPLEMENTATION.md' => 'docs/implementation/',
    'DASHBOARD_ENHANCEMENTS_COMPLETE.md' => 'docs/implementation/',
    'CONSOLIDATION_COMPLETE.md' => 'docs/implementation/',
    'CONSOLIDATION_IMPLEMENTATION_STATUS.md' => 'docs/implementation/',
    
    // Analysis docs
    'HR_SYSTEM_ANALYSIS.md' => 'docs/analysis/',
    'HR_SYSTEM_SUMMARY.md' => 'docs/analysis/',
    'DASHBOARD_AUDIT_REPORT.md' => 'docs/analysis/',
    'DASHBOARD_COMPARISON_ANALYSIS.md' => 'docs/analysis/',
    'SYSTEM_ANALYSIS_REVIEW.md' => 'docs/analysis/',
    'SYSTEM_HEALTH_REPORT.md' => 'docs/analysis/',
    'RPM_ANALYSIS.md' => 'docs/analysis/',
    'ANALYTICS_ACCURACY_FIXES.md' => 'docs/analysis/',
    'CALCULATION_FIXES.md' => 'docs/analysis/',
    'DATA_INTERCONNECTION_ANALYSIS.md' => 'docs/analysis/',
    'CONSOLIDATION_OPPORTUNITIES.md' => 'docs/analysis/',
    
    // Status/Reports
    'DEPLOYMENT_STATUS.md' => 'docs/status/',
    'DEPLOYMENT_STEPS.md' => 'docs/status/',
    'MAINTENANCE_RPM_IMPLEMENTATION_STATUS.md' => 'docs/status/',
    'RPM_ISSUE_FIX.md' => 'docs/status/',
    'RPM_ISSUE_FOUND.md' => 'docs/status/',
    'RPM_CORRECTION_COMPLETE.md' => 'docs/status/',
    
    // Guides
    'RESOURCES_GUIDE.md' => 'docs/guides/',
    'RESOURCES_INTEGRATION_GUIDE.md' => 'docs/guides/',
    'RESOURCES_USAGE_GUIDE.md' => 'docs/guides/',
    'TESTING_GUIDE.md' => 'docs/guides/',
    'MENU_ORGANIZATION_ADVICE.md' => 'docs/guides/',
    'MAINTENANCE_RPM_DOCUMENTATION.md' => 'docs/guides/',
    
    // Other
    'DASHBOARD_ENHANCEMENTS_SUMMARY.md' => 'docs/',
    'IMPROVEMENTS_AND_SUGGESTIONS.md' => 'docs/',
    'NEXT_STEPS_ROADMAP.md' => 'docs/',
    'QUICK_REVIEW_SUMMARY.md' => 'docs/',
];

foreach ($docMappings as $file => $targetDir) {
    $source = $baseDir . '/' . $file;
    $target = $baseDir . '/' . $targetDir . $file;
    
    if (file_exists($source)) {
        if (rename($source, $target)) {
            $moved[] = "$file → $targetDir";
            echo "✓ Moved: $file → $targetDir\n";
        } else {
            $errors[] = "Failed to move: $file";
            echo "✗ Failed to move: $file\n";
        }
    }
}

// 3. Move logs directory if it exists and is empty or has old logs
$logsDir = $baseDir . '/logs';
if (file_exists($logsDir) && is_dir($logsDir)) {
    $storageLogs = $baseDir . '/storage/logs';
    if (!file_exists($storageLogs)) {
        mkdir($storageLogs, 0755, true);
    }
    
    // Move log files
    $logFiles = glob($logsDir . '/*');
    foreach ($logFiles as $logFile) {
        if (is_file($logFile)) {
            $target = $storageLogs . '/' . basename($logFile);
            if (rename($logFile, $target)) {
                echo "✓ Moved log file: " . basename($logFile) . "\n";
            }
        }
    }
    
    // Remove empty logs directory
    if (count(glob($logsDir . '/*')) === 0) {
        rmdir($logsDir);
        echo "✓ Removed empty logs directory\n";
    }
}

// 4. Create .gitignore if it doesn't exist
$gitignore = $baseDir . '/.gitignore';
if (!file_exists($gitignore)) {
    $gitignoreContent = <<<'GITIGNORE'
# ABBIS .gitignore

# Environment and config
.env
.env.local
config/database.php
config/local.php

# Storage and logs
storage/logs/*
!storage/logs/.gitkeep
storage/cache/*
!storage/cache/.gitkeep
storage/temp/*
!storage/temp/.gitkeep
logs/*
*.log

# Uploads (keep structure, ignore content)
uploads/logos/*
!uploads/logos/.gitkeep
uploads/media/*
!uploads/media/.gitkeep
uploads/payslips/*
!uploads/payslips/.gitkeep
uploads/products/*
!uploads/products/.gitkeep
uploads/qrcodes/*
!uploads/qrcodes/.gitkeep
uploads/site/*
!uploads/site/.gitkeep

# IDE and editor files
.vscode/
.idea/
*.swp
*.swo
*~
.DS_Store
Thumbs.db

# Composer
/vendor/
composer.lock

# Node modules (if any)
node_modules/
npm-debug.log
yarn-error.log

# Temporary files
*.tmp
*.temp
*.bak
*.backup
*~

# System files
.htaccess.bak
.htpasswd

# CMS specific
cms/public/uploads/*
!cms/public/uploads/.gitkeep

# Dashboard assets (if large)
dashboard/images/*
!dashboard/images/.gitkeep

# Webalizer
webalizer/*

# Sources (if contains generated files)
sources/*.zip
sources/*.tar.gz
GITIGNORE;
    
    file_put_contents($gitignore, $gitignoreContent);
    echo "✓ Created .gitignore\n";
}

// 5. Create .gitkeep files for empty directories
$gitkeepDirs = [
    'storage/logs',
    'storage/cache',
    'storage/temp',
    'uploads/logos',
    'uploads/media',
    'uploads/payslips',
    'uploads/products',
    'uploads/qrcodes',
    'uploads/site'
];

foreach ($gitkeepDirs as $dir) {
    $gitkeep = $baseDir . '/' . $dir . '/.gitkeep';
    if (!file_exists($gitkeep)) {
        file_put_contents($gitkeep, '');
    }
}

// Summary
echo "\n=== Summary ===\n";
echo "Files moved: " . count($moved) . "\n";
echo "Errors: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n✓ Directory organization complete!\n";
echo "\nNote: All critical PHP files remain in root directory.\n";
echo "Documentation has been organized into docs/ subdirectories.\n";

