#!/usr/bin/env php
<?php
/**
 * Toggle Super Admin Bypass
 * 
 * This script helps you enable or disable the Super Admin bypass feature
 * by setting/unsetting the required environment variables.
 * 
 * Usage:
 *   php scripts/toggle-super-admin.php enable
 *   php scripts/toggle-super-admin.php disable
 *   php scripts/toggle-super-admin.php status
 */

$action = $argv[1] ?? 'status';

// Load environment detection
require_once __DIR__ . '/../config/environment.php';

$envFile = __DIR__ . '/../.env';
$envExampleFile = __DIR__ . '/../.env.example';

// Check if .env file exists, create from example if not
if (!file_exists($envFile) && file_exists($envExampleFile)) {
    copy($envExampleFile, $envFile);
}

function getEnvValue($key) {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        return null;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        if (strpos($line, $key . '=') === 0) {
            $parts = explode('=', $line, 2);
            return isset($parts[1]) ? trim($parts[1], '"\'') : '';
        }
    }
    return null;
}

function setEnvValue($key, $value) {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        file_put_contents($envFile, "# ABBIS Environment Variables\n");
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;
    $newLines = [];
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            $newLines[] = $line;
            continue;
        }
        if (strpos($line, $key . '=') === 0) {
            $newLines[] = $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : '"' . addslashes($value) . '"');
            $found = true;
        } else {
            $newLines[] = $line;
        }
    }
    
    if (!$found) {
        $newLines[] = $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : '"' . addslashes($value) . '"');
    }
    
    file_put_contents($envFile, implode("\n", $newLines) . "\n");
}

function checkStatus() {
    $enabled = getenv('SUPER_ADMIN_ENABLED');
    $hasSecret = getenv('SUPER_ADMIN_SECRET') !== false;
    $hasUsername = getenv('SUPER_ADMIN_USERNAME') !== false;
    $hasPassword = getenv('SUPER_ADMIN_PASSWORD') !== false;
    $env = defined('APP_ENV') ? APP_ENV : 'unknown';
    
    echo "Super Admin Bypass Status:\n";
    echo "========================\n\n";
    echo "Environment: {$env}\n";
    echo "SUPER_ADMIN_ENABLED: " . ($enabled ?: 'not set') . "\n";
    echo "SUPER_ADMIN_SECRET: " . ($hasSecret ? '✓ set' : '✗ not set') . "\n";
    echo "SUPER_ADMIN_USERNAME: " . ($hasUsername ? '✓ set' : '✗ not set') . "\n";
    echo "SUPER_ADMIN_PASSWORD: " . ($hasPassword ? '✓ set' : '✗ not set') . "\n\n";
    
    $isEnabled = ($enabled === 'true' || $enabled === '1' || $enabled === 'yes') &&
                 $hasSecret && $hasUsername && $hasPassword && $env === 'development';
    
    if ($isEnabled) {
        echo "Status: ✅ ENABLED\n";
    } else {
        echo "Status: ❌ DISABLED\n";
        echo "\nTo enable, run: php scripts/toggle-super-admin.php enable\n";
    }
}

function enable() {
    $env = defined('APP_ENV') ? APP_ENV : 'unknown';
    
    if ($env !== 'development') {
        echo "❌ Error: Super Admin bypass can only be enabled in development mode.\n";
        echo "Current environment: {$env}\n";
        echo "Set APP_ENV=development to enable.\n";
        exit(1);
    }
    
    echo "Enabling Super Admin bypass...\n\n";
    
    // Check if credentials are already set
    $secret = getenv('SUPER_ADMIN_SECRET');
    $username = getenv('SUPER_ADMIN_USERNAME');
    $password = getenv('SUPER_ADMIN_PASSWORD');
    
    if (!$secret || !$username || !$password) {
        echo "⚠️  Warning: Some credentials are missing.\n";
        echo "You need to set the following environment variables:\n\n";
        
        if (!$secret) {
            echo "  export SUPER_ADMIN_SECRET=\"your-secret-key\"\n";
        }
        if (!$username) {
            echo "  export SUPER_ADMIN_USERNAME=\"your-username\"\n";
        }
        if (!$password) {
            echo "  export SUPER_ADMIN_PASSWORD=\"your-password\"\n";
        }
        
        echo "\nOr set them in your .env file:\n";
        echo "  SUPER_ADMIN_SECRET=\"your-secret-key\"\n";
        echo "  SUPER_ADMIN_USERNAME=\"your-username\"\n";
        echo "  SUPER_ADMIN_PASSWORD=\"your-password\"\n\n";
        
        echo "After setting credentials, run this script again.\n";
        exit(1);
    }
    
    // Enable via .env file
    setEnvValue('SUPER_ADMIN_ENABLED', true);
    
    echo "✅ Super Admin bypass enabled!\n\n";
    echo "Note: You may need to restart your web server/PHP-FPM for changes to take effect.\n";
    echo "For XAMPP, restart Apache from the XAMPP Control Panel.\n\n";
    
    checkStatus();
}

function disable() {
    echo "Disabling Super Admin bypass...\n\n";
    
    // Disable via .env file
    setEnvValue('SUPER_ADMIN_ENABLED', false);
    
    echo "✅ Super Admin bypass disabled!\n\n";
    echo "Note: You may need to restart your web server/PHP-FPM for changes to take effect.\n";
    echo "For XAMPP, restart Apache from the XAMPP Control Panel.\n\n";
    
    checkStatus();
}

switch ($action) {
    case 'enable':
        enable();
        break;
    case 'disable':
        disable();
        break;
    case 'status':
    default:
        checkStatus();
        break;
}

