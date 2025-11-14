<?php
/**
 * URL Helper Functions
 * 
 * Provides functions to generate obfuscated URLs throughout the system
 */

require_once __DIR__ . '/router.php';

/**
 * Generate obfuscated URL
 */
function route($path, $params = []) {
    return Router::getUrl($path, $params);
}

/**
 * Generate obfuscated URL for a module
 */
function moduleUrl($module, $params = []) {
    $moduleMap = [
        'dashboard' => 'modules/dashboard.php',
        'reports' => 'modules/field-reports.php',
        'materials' => 'modules/materials.php',
        'payroll' => 'modules/payroll.php',
        'finance' => 'modules/finance.php',
        'loans' => 'modules/loans.php',
        'clients' => 'modules/crm.php?action=clients',
        'config' => 'modules/config.php',
        'system' => 'modules/system.php',
        'help' => 'modules/help.php',
    ];
    
    if (isset($moduleMap[$module])) {
        return Router::getUrl($moduleMap[$module], $params);
    }
    
    return '#'; // Fallback
}

/**
 * Encode ID for URL
 */
function encodeId($id) {
    return Router::encodeId($id);
}

/**
 * Decode ID from URL
 */
function decodeId($encoded) {
    return Router::decodeId($encoded);
}

/**
 * Get current route
 */
function currentRoute() {
    $url = $_SERVER['REQUEST_URI'] ?? '/';
    $url = strtok($url, '?');
    return trim($url, '/');
}

