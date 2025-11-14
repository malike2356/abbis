<?php
/**
 * Check AI Setup Status
 * Verifies encryption key, provider configuration, and AI chat readiness
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/AI/bootstrap.php';

$pdo = getDBConnection();

echo "=== AI Setup Status Check ===\n\n";

// 1. Check Encryption Key
echo "1. Encryption Key Status:\n";
$hasEncryptionKey = false;
$keyFile = __DIR__ . '/../config/secrets/encryption.key';
$envKey = getenv('ABBIS_ENCRYPTION_KEY');

if ($envKey) {
    echo "   ✅ Environment variable set\n";
    $hasEncryptionKey = true;
} elseif (file_exists($keyFile) && is_readable($keyFile)) {
    echo "   ✅ Key file exists: $keyFile\n";
    $hasEncryptionKey = true;
} else {
    echo "   ❌ No encryption key found\n";
    echo "   → Set up at: modules/admin/setup-encryption-key.php\n";
}

// 2. Check Secrets Directory
echo "\n2. Secrets Directory:\n";
$secretsDir = __DIR__ . '/../config/secrets';
if (is_dir($secretsDir)) {
    echo "   ✅ Directory exists\n";
    if (is_writable($secretsDir)) {
        echo "   ✅ Directory is writable\n";
    } else {
        echo "   ⚠️  Directory exists but is not writable\n";
        echo "   → Run: chmod 755 $secretsDir\n";
    }
} else {
    echo "   ❌ Directory does not exist\n";
    echo "   → Run: mkdir -p $secretsDir && chmod 755 $secretsDir\n";
}

// 3. Check Provider Configurations
echo "\n3. AI Provider Configurations:\n";
try {
    $stmt = $pdo->query("SELECT provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json FROM ai_provider_config ORDER BY failover_priority ASC");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($providers)) {
        echo "   ⚠️  No providers configured in database\n";
        echo "   → Configure at: modules/ai-governance.php\n";
    } else {
        foreach ($providers as $provider) {
            $key = strtoupper($provider['provider_key']);
            $enabled = (int)($provider['is_enabled'] ?? 0) === 1;
            $settings = json_decode($provider['settings_json'] ?? '{}', true);
            $hasApiKey = !empty($settings['api_key'] ?? '');
            
            echo "   Provider: $key\n";
            echo "      Status: " . ($enabled ? "✅ Enabled" : "❌ Disabled") . "\n";
            echo "      Priority: " . ($provider['failover_priority'] ?? 100) . "\n";
            echo "      API Key: " . ($hasApiKey ? "✅ Configured" : "❌ Missing") . "\n";
            if ($hasApiKey && !$hasEncryptionKey) {
                echo "      ⚠️  WARNING: API key exists but encryption key is missing!\n";
            }
            echo "\n";
        }
    }
} catch (PDOException $e) {
    echo "   ❌ Error checking providers: " . $e->getMessage() . "\n";
}

// 4. Check AI Tables
echo "4. Database Tables:\n";
$tables = ['ai_provider_config', 'ai_usage_logs'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ Table '$table' exists\n";
        } else {
            echo "   ❌ Table '$table' missing\n";
            echo "   → Run migration: database/migrations/phase5/001_create_ai_tables.sql\n";
        }
    } catch (PDOException $e) {
        echo "   ❌ Error checking table '$table': " . $e->getMessage() . "\n";
    }
}

// 5. Summary
echo "\n=== Summary ===\n";
if ($hasEncryptionKey) {
    echo "✅ Encryption key is configured\n";
} else {
    echo "❌ Encryption key is REQUIRED\n";
}

$enabledCount = 0;
$configuredCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_enabled = 1 THEN 1 ELSE 0 END) as enabled FROM ai_provider_config");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $configuredCount = (int)($row['total'] ?? 0);
    $enabledCount = (int)($row['enabled'] ?? 0);
} catch (PDOException $e) {
    // Ignore
}

if ($enabledCount > 0) {
    echo "✅ $enabledCount provider(s) enabled\n";
} else {
    echo "❌ No providers enabled\n";
}

if ($hasEncryptionKey && $enabledCount > 0) {
    echo "\n✅ AI Chat should be ready to use!\n";
    echo "   → Access at: modules/ai-assistant.php\n";
} else {
    echo "\n⚠️  AI Chat is not ready. Please complete the setup above.\n";
}

