<?php
/**
 * Quick AI Provider Configuration
 * Configure OpenAI, DeepSeek, and Gemini with provided API keys
 */

$page_title = 'Configure AI Providers';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/crypto.php';

$auth->requireAuth();
$auth->requirePermission('system.admin');

$pdo = getDBConnection();
$messages = [];
$errors = [];

// API Keys - Replace with your actual keys
// IMPORTANT: Never commit real API keys to version control!
$openaiKey = 'YOUR_OPENAI_API_KEY_HERE';
$deepseekKey = 'YOUR_DEEPSEEK_API_KEY_HERE';
$geminiKey = 'YOUR_GEMINI_API_KEY_HERE';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configure'])) {
    try {
        // Test encryption key
        $testEncrypt = Crypto::encrypt('test');
        $testDecrypt = Crypto::decrypt($testEncrypt);
        if ($testDecrypt !== 'test') {
            throw new Exception('Encryption key test failed');
        }
        
        // Configure OpenAI
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
            ':priority' => 1,
            ':settings_json' => json_encode($openaiSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        
        // Configure DeepSeek
        $deepseekSettings = [
            'api_key' => Crypto::encrypt($deepseekKey),
            'model' => 'deepseek-chat',
            'base_url' => 'https://api.deepseek.com/v1',
            'timeout' => 30,
        ];
        
        $stmt->execute([
            ':provider_key' => 'deepseek',
            ':is_enabled' => 1,
            ':daily_limit' => null,
            ':monthly_limit' => null,
            ':priority' => 2,
            ':settings_json' => json_encode($deepseekSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        
        // Configure Gemini
        $geminiSettings = [
            'api_key' => Crypto::encrypt($geminiKey),
            'model' => 'gemini-1.5-flash-latest',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 30,
        ];
        
        $stmt->execute([
            ':provider_key' => 'gemini',
            ':is_enabled' => 1,
            ':daily_limit' => null,
            ':monthly_limit' => null,
            ':priority' => 3,
            ':settings_json' => json_encode($geminiSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        
        $messages[] = '✅ OpenAI, DeepSeek, and Gemini providers configured successfully!';
        $messages[] = 'AI Chat is now ready to use with three providers configured.';
        
    } catch (Exception $e) {
        $errors[] = 'Error: ' . $e->getMessage();
    }
}

// Check current status
$currentProviders = [];
try {
    $stmt = $pdo->query("
        SELECT provider_key, is_enabled, failover_priority, 
               CASE WHEN settings_json LIKE '%api_key%' THEN 1 ELSE 0 END as has_api_key
        FROM ai_provider_config
        WHERE provider_key IN ('openai', 'deepseek', 'gemini')
        ORDER BY failover_priority
    ");
    $currentProviders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔧 Configure AI Providers</h1>
        <p class="lead">Quick setup for OpenAI, DeepSeek, and Gemini providers</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($messages): ?>
        <div class="alert alert-success">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?php echo e($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <h2 style="margin-top: 0;">Current Status</h2>
        
        <?php if (empty($currentProviders)): ?>
            <p style="color: var(--secondary);">No providers configured yet.</p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($currentProviders as $provider): ?>
                    <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 18px;"><?php echo strtoupper(e($provider['provider_key'])); ?></strong>
                                <div style="margin-top: 4px; font-size: 14px; color: var(--secondary);">
                                    Priority: <?php echo (int)($provider['failover_priority'] ?? 100); ?> | 
                                    Status: <?php echo (int)($provider['is_enabled'] ?? 0) === 1 ? '✅ Enabled' : '❌ Disabled'; ?> | 
                                    API Key: <?php echo (int)($provider['has_api_key'] ?? 0) === 1 ? '✅ Configured' : '❌ Missing'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="padding: 24px;">
        <h2 style="margin-top: 0;">Configure Providers</h2>
        <p style="color: var(--secondary); margin-bottom: 20px;">
            This will configure OpenAI (primary), DeepSeek (backup), and Gemini (tertiary) providers with the provided API keys.
        </p>

        <form method="POST">
            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" name="configure" class="btn btn-primary" style="font-size: 16px; padding: 12px 24px;">
                    🔧 Configure AI Providers
                </button>
                <a href="ai-governance.php" class="btn btn-outline" style="text-decoration: none;">
                    ⚙️ Advanced Settings
                </a>
            </div>
        </form>
    </div>

    <div class="card" style="padding: 24px; margin-top: 24px; background: linear-gradient(135deg, rgba(14,165,233,0.05) 0%, rgba(14,165,233,0.02) 100%); border: 1px solid rgba(14,165,233,0.2);">
        <h3 style="margin-top: 0;">✅ Next Steps</h3>
        <ol style="line-height: 1.8;">
            <li>Click "Configure AI Providers" above to set up all three providers</li>
            <li>Test the AI chat by clicking the 🧠 "Assistant" button on any page</li>
            <li>Or visit <a href="../ai-assistant.php" style="color: #0ea5e9;">modules/ai-assistant.php</a> directly</li>
            <li>For advanced settings, visit <a href="ai-governance.php" style="color: #0ea5e9;">AI Governance</a></li>
        </ol>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

