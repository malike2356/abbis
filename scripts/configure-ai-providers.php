<?php
/**
 * Configure AI Providers with API Keys
 * This script sets up OpenAI and DeepSeek providers with the provided API keys
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crypto.php';

$pdo = getDBConnection();

// API Keys - Replace with your actual keys
// IMPORTANT: Never commit real API keys to version control!
$openaiKey = 'YOUR_OPENAI_API_KEY_HERE';
$deepseekKey = 'YOUR_DEEPSEEK_API_KEY_HERE';

echo "=== Configuring AI Providers ===\n\n";

// Test encryption key first
try {
    $testEncrypt = Crypto::encrypt('test');
    $testDecrypt = Crypto::decrypt($testEncrypt);
    if ($testDecrypt !== 'test') {
        throw new Exception('Encryption test failed');
    }
    echo "✅ Encryption key is working\n\n";
} catch (Exception $e) {
    echo "❌ Encryption key error: " . $e->getMessage() . "\n";
    echo "   Make sure the encryption key is set up correctly.\n";
    exit(1);
}

// Configure OpenAI
echo "1. Configuring OpenAI...\n";
try {
    $openaiSettings = [
        'api_key' => Crypto::encrypt($openaiKey),
        'model' => 'gpt-4o-mini',
        'base_url' => 'https://api.openai.com/v1',
        'timeout' => 30,
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO ai_provider_config (provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json)
        VALUES (:provider_key, :is_enabled, :daily_limit, :monthly_limit, :priority, :settings_json)
        ON DUPLICATE KEY UPDATE
            is_enabled = VALUES(is_enabled),
            daily_limit = VALUES(daily_limit),
            monthly_limit = VALUES(monthly_limit),
            failover_priority = VALUES(failover_priority),
            settings_json = VALUES(settings_json),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        ':provider_key' => 'openai',
        ':is_enabled' => 1,
        ':daily_limit' => null,
        ':monthly_limit' => null,
        ':priority' => 1, // Primary provider
        ':settings_json' => json_encode($openaiSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    
    echo "   ✅ OpenAI configured (Priority: 1, Enabled)\n";
} catch (Exception $e) {
    echo "   ❌ Error configuring OpenAI: " . $e->getMessage() . "\n";
}

// Configure DeepSeek
echo "\n2. Configuring DeepSeek...\n";
try {
    $deepseekSettings = [
        'api_key' => Crypto::encrypt($deepseekKey),
        'model' => 'deepseek-chat',
        'base_url' => 'https://api.deepseek.com/v1',
        'timeout' => 30,
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO ai_provider_config (provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json)
        VALUES (:provider_key, :is_enabled, :daily_limit, :monthly_limit, :priority, :settings_json)
        ON DUPLICATE KEY UPDATE
            is_enabled = VALUES(is_enabled),
            daily_limit = VALUES(daily_limit),
            monthly_limit = VALUES(monthly_limit),
            failover_priority = VALUES(failover_priority),
            settings_json = VALUES(settings_json),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        ':provider_key' => 'deepseek',
        ':is_enabled' => 1,
        ':daily_limit' => null,
        ':monthly_limit' => null,
        ':priority' => 2, // Backup provider
        ':settings_json' => json_encode($deepseekSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    
    echo "   ✅ DeepSeek configured (Priority: 2, Enabled)\n";
} catch (Exception $e) {
    echo "   ❌ Error configuring DeepSeek: " . $e->getMessage() . "\n";
}

// Verify configuration
echo "\n3. Verifying configuration...\n";
try {
    $stmt = $pdo->query("
        SELECT provider_key, is_enabled, failover_priority, 
               CASE WHEN settings_json LIKE '%api_key%' THEN 'Yes' ELSE 'No' END as has_api_key
        FROM ai_provider_config
        WHERE provider_key IN ('openai', 'deepseek')
        ORDER BY failover_priority
    ");
    
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($providers)) {
        echo "   ⚠️  No providers found in database\n";
    } else {
        foreach ($providers as $provider) {
            $status = (int)($provider['is_enabled'] ?? 0) === 1 ? '✅ Enabled' : '❌ Disabled';
            $apiKeyStatus = $provider['has_api_key'] === 'Yes' ? '✅ Has API Key' : '❌ No API Key';
            echo "   " . strtoupper($provider['provider_key']) . ": $status | Priority: {$provider['failover_priority']} | $apiKeyStatus\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error verifying: " . $e->getMessage() . "\n";
}

echo "\n=== Configuration Complete ===\n";
echo "✅ AI Chat is now ready to use!\n";
echo "   → Access the assistant via the 🧠 button on any page\n";
echo "   → Or go to: modules/ai-assistant.php\n";

