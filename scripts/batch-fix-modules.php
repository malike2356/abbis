<?php
/**
 * Batch fix modules - Add authentication and CSRF protection
 */

$modulesToFix = [
    // Accounting modules
    'accounting-accounts.php',
    'accounting-balance-sheet.php',
    'accounting-integrations.php',
    'accounting-journal.php',
    'accounting-ledger.php',
    'accounting-pl.php',
    'accounting-settings.php',
    'accounting-trial-balance.php',
    
    // Assets modules
    'assets-depreciation.php',
    'assets-detail.php',
    'assets-form.php',
    'assets-list.php',
    'assets-reports.php',
    
    // Inventory modules
    'inventory-analytics.php',
    'inventory-advanced.php',
    'inventory-reorder.php',
    'inventory-stock.php',
    'inventory-transactions.php',
    
    // Maintenance modules
    'maintenance-analytics.php',
    'maintenance-form.php',
    'maintenance-record-detail.php',
    'maintenance-records.php',
    'maintenance-schedule.php',
    'maintenance-digital-twin.php',
    
    // CRM modules
    'crm-client-detail.php',
    'crm-clients.php',
    'crm-dashboard.php',
    'crm-emails.php',
    'crm-followups.php',
    'crm-templates.php',
];

$basePath = __DIR__ . '/../modules';
$authHeader = "require_once '../config/app.php';\nrequire_once '../config/security.php';\nrequire_once '../includes/auth.php';\nrequire_once '../includes/helpers.php';\n\n\$auth->requireAuth();\n\n";

foreach ($modulesToFix as $module) {
    $filePath = $basePath . '/' . $module;
    if (!file_exists($filePath)) {
        echo "Skipping {$module} - file not found\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check if already has auth
    if (strpos($content, 'requireAuth') !== false || strpos($content, 'auth->require') !== false) {
        echo "Skipping {$module} - already has authentication\n";
        continue;
    }
    
    // Find the opening PHP tag and add auth after it
    if (preg_match('/^<\?php\s*\n/', $content)) {
        $content = preg_replace('/^<\?php\s*\n/', "<?php\n" . $authHeader, $content, 1);
        file_put_contents($filePath, $content);
        echo "Fixed {$module} - added authentication\n";
    } else {
        echo "Skipping {$module} - unexpected format\n";
    }
}

echo "\nDone!\n";

