<?php
/**
 * Base URL Helper for CMS Public Pages
 * Include this file to get $baseUrl variable
 */
if (!isset($baseUrl)) {
    $baseUrl = '';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '';
        if ($baseUrl !== '/' && substr($baseUrl, -1) === '/') {
            $baseUrl = rtrim($baseUrl, '/');
        }
    }
    
    // If still empty, try to detect from SCRIPT_NAME
    if (!$baseUrl) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName) {
            $parts = explode('/', trim($scriptName, '/'));
            foreach ($parts as $idx => $part) {
                if ($part === 'cms' && $idx > 0) {
                    $baseUrl = '/' . $parts[$idx - 1];
                    break;
                }
            }
            if (!$baseUrl && count($parts) > 0 && $parts[0] !== 'index.php' && $parts[0] !== '') {
                $baseUrl = '/' . $parts[0];
            }
        }
    }
    
    // Final fallback
    if (!$baseUrl) {
        $baseUrl = '/abbis3.2';
    }
}

