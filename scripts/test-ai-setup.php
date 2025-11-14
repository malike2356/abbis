<?php
/**
 * Test AI Setup - Quick diagnostic script
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== AI Setup Diagnostic ===\n\n";

// Check encryption key
echo "1. Encryption Key:\n";
$encKey = getenv('ABBIS_ENCRYPTION_KEY');
$keyFile = __DIR__ . '/../config/secrets/encryption.key';
if ($encKey) {
    echo "   ✅ Environment variable set\n";
} elseif (file_exists($keyFile)) {
    echo "   ✅ Key file exists\n";
} else {
    echo "   ❌ NOT CONFIGURED - Go to setup-encryption-key.php\n";
}
echo "\n";

// Check database connection
echo "2. Database Connection:\n";
try {
    $pdo = getDBConnection();
    echo "   ✅ Connected\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n";
    exit;
}
echo "\n";

// Check AI provider configs
echo "3. AI Provider Configurations:\n";
try {
    $stmt = $pdo->query("SELECT provider_key, is_enabled, settings_json FROM ai_provider_config");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($configs)) {
        echo "   ⚠️  No providers configured\n";
    } else {
        foreach ($configs as $config) {
            $key = $config['provider_key'];
            $enabled = (int)$config['is_enabled'] === 1;
            $settings = json_decode($config['settings_json'] ?? '{}', true);
            $hasApiKey = !empty($settings['api_key']);
            
            $status = $enabled ? '✅' : '⚠️';
            $apiStatus = $hasApiKey ? '✅ Has API Key' : '❌ Missing API Key';
            
            echo "   {$status} {$key}: " . ($enabled ? 'Enabled' : 'Disabled') . " - {$apiStatus}\n";
        }
    }
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test AI manager initialization
echo "4. AI Manager Initialization:\n";
try {
    require_once __DIR__ . '/../includes/AI/bootstrap.php';
    $manager = ai_insight_manager();
    echo "   ✅ Manager initialized successfully\n";
} catch (AIProviderException $e) {
    echo "   ❌ Provider Error: " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo "\n";

echo "=== End Diagnostic ===\n";

