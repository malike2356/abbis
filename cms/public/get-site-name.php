<?php
/**
 * Helper function to get consistent site name across all CMS pages
 * Prioritizes CMS settings over system config
 */
function getCMSSiteName($fallback = 'Our Company') {
    global $pdo, $cmsSettings;
    
    // Get CMS settings if not already loaded
    if (!isset($cmsSettings)) {
        try {
            $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
            $cmsSettings = [];
            while ($row = $settingsStmt->fetch()) {
                $cmsSettings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cmsSettings = [];
        }
    }
    
    // First try CMS settings (from backend Settings page)
    if (isset($cmsSettings['site_title']) && !empty(trim($cmsSettings['site_title']))) {
        return trim($cmsSettings['site_title']);
    }
    
    // Fallback to system config company name
    try {
        $configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name' LIMIT 1");
        $companyName = $configStmt->fetchColumn();
        if (!empty($companyName)) {
            return $companyName;
        }
    } catch (Throwable $e) {
        // Ignore errors
    }
    
    // Final fallback
    return $fallback;
}

