<?php
/**
 * AI Service Diagnostics
 * Check AI provider configuration and connectivity
 */

$page_title = 'AI Service Diagnostics';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/crypto.php';

$auth->requireAuth();
$auth->requirePermission('system.admin');

$pdo = getDBConnection();
$diagnostics = [];
$errors = [];
$warnings = [];
$success = [];

// 1. Check Encryption Key
try {
    $testEncrypt = Crypto::encrypt('test');
    $testDecrypt = Crypto::decrypt($testEncrypt);
    if ($testDecrypt === 'test') {
        $success[] = '✅ Encryption key is working';
        $diagnostics['encryption'] = ['status' => 'ok', 'message' => 'Encryption key is configured and working'];
    } else {
        $errors[] = '❌ Encryption key test failed';
        $diagnostics['encryption'] = ['status' => 'error', 'message' => 'Encryption key is not working correctly'];
    }
} catch (Exception $e) {
    $errors[] = '❌ Encryption key error: ' . $e->getMessage();
    $diagnostics['encryption'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// 2. Check Provider Configuration
try {
    $stmt = $pdo->query("
        SELECT provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json, updated_at
        FROM ai_provider_config
        ORDER BY failover_priority ASC
    ");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($providers)) {
        $errors[] = '❌ No AI providers configured';
        $diagnostics['providers'] = ['status' => 'error', 'message' => 'No providers found in database', 'count' => 0];
    } else {
        $enabledCount = 0;
        $configuredCount = 0;
        
        foreach ($providers as $provider) {
            $settings = json_decode($provider['settings_json'] ?? '{}', true);
            $hasApiKey = !empty($settings['api_key'] ?? '');
            $isEnabled = (int)($provider['is_enabled'] ?? 0) === 1;
            
            if ($hasApiKey) {
                $configuredCount++;
                // Try to decrypt the API key to verify it's valid
                try {
                    $decrypted = Crypto::decrypt($settings['api_key']);
                    if (empty($decrypted)) {
                        $warnings[] = '⚠️ ' . strtoupper($provider['provider_key']) . ' API key exists but is empty after decryption';
                    }
                } catch (Exception $e) {
                    $warnings[] = '⚠️ ' . strtoupper($provider['provider_key']) . ' API key decryption failed: ' . $e->getMessage();
                }
            } else {
                $warnings[] = '⚠️ ' . strtoupper($provider['provider_key']) . ' has no API key configured';
            }
            
            if ($isEnabled) {
                $enabledCount++;
            }
        }
        
        if ($enabledCount === 0) {
            $errors[] = '❌ No providers are enabled';
            $diagnostics['providers'] = ['status' => 'error', 'message' => 'No enabled providers', 'count' => count($providers), 'enabled' => 0];
        } else if ($configuredCount === 0) {
            $errors[] = '❌ No providers have API keys configured';
            $diagnostics['providers'] = ['status' => 'error', 'message' => 'No providers with API keys', 'count' => count($providers), 'enabled' => $enabledCount];
        } else {
            $success[] = "✅ {$enabledCount} provider(s) enabled, {$configuredCount} with API keys";
            $diagnostics['providers'] = ['status' => 'ok', 'count' => count($providers), 'enabled' => $enabledCount, 'configured' => $configuredCount];
        }
        
        $diagnostics['provider_list'] = $providers;
    }
} catch (PDOException $e) {
    $errors[] = '❌ Database error: ' . $e->getMessage();
    $diagnostics['providers'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// 3. Check AI Bootstrap
try {
    require_once __DIR__ . '/../../includes/AI/bootstrap.php';
    $success[] = '✅ AI bootstrap loaded successfully';
    $diagnostics['bootstrap'] = ['status' => 'ok', 'message' => 'AI bootstrap is working'];
} catch (Exception $e) {
    $errors[] = '❌ AI bootstrap error: ' . $e->getMessage();
    $diagnostics['bootstrap'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// 4. Check AI Manager Initialization
try {
    $manager = ai_insight_manager();
    $success[] = '✅ AI manager initialized successfully';
    $diagnostics['manager'] = ['status' => 'ok', 'message' => 'AI manager is working'];
} catch (Exception $e) {
    $errors[] = '❌ AI manager error: ' . $e->getMessage();
    $diagnostics['manager'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// 5. Test API Endpoint
$diagnostics['api_endpoint'] = ['status' => 'info', 'message' => 'API endpoint: api/ai-insights.php'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔍 AI Service Diagnostics</h1>
        <p class="lead">Check AI provider configuration and connectivity</p>
    </div>

    <!-- Summary -->
    <div class="card" style="padding: 24px; margin-bottom: 24px; border-left: 4px solid <?php echo empty($errors) ? '#10b981' : '#ef4444'; ?>;">
        <h2 style="margin-top: 0;">📊 Diagnostic Summary</h2>
        
        <?php if (!empty($success)): ?>
            <div style="margin-bottom: 16px;">
                <?php foreach ($success as $msg): ?>
                    <div style="padding: 8px 0; color: #10b981;"><?php echo e($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($warnings)): ?>
            <div style="margin-bottom: 16px;">
                <?php foreach ($warnings as $msg): ?>
                    <div style="padding: 8px 0; color: #f59e0b;"><?php echo e($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div style="margin-bottom: 16px;">
                <?php foreach ($errors as $msg): ?>
                    <div style="padding: 8px 0; color: #ef4444;"><?php echo e($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($errors) && empty($warnings)): ?>
            <div style="padding: 8px 0; color: #10b981; font-weight: 600;">
                ✅ All systems operational! AI service should be working correctly.
            </div>
        <?php endif; ?>
    </div>

    <!-- Detailed Diagnostics -->
    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <h2 style="margin-top: 0;">🔧 Detailed Diagnostics</h2>
        
        <div style="display: grid; gap: 16px;">
            <!-- Encryption -->
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong>Encryption Key</strong>
                    <span class="badge <?php echo $diagnostics['encryption']['status'] === 'ok' ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $diagnostics['encryption']['status'] === 'ok' ? '✅ OK' : '❌ Error'; ?>
                    </span>
                </div>
                <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                    <?php echo e($diagnostics['encryption']['message']); ?>
                </p>
                <?php if ($diagnostics['encryption']['status'] !== 'ok'): ?>
                    <a href="setup-encryption-key.php" class="btn btn-sm btn-primary" style="margin-top: 8px; text-decoration: none;">
                        → Set up encryption key
                    </a>
                <?php endif; ?>
            </div>

            <!-- Providers -->
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong>AI Providers</strong>
                    <span class="badge <?php echo $diagnostics['providers']['status'] === 'ok' ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $diagnostics['providers']['status'] === 'ok' ? '✅ OK' : '❌ Error'; ?>
                    </span>
                </div>
                <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                    <?php echo e($diagnostics['providers']['message'] ?? 'Providers status'); ?>
                    <?php if (isset($diagnostics['providers']['count'])): ?>
                        <br>Total: <?php echo $diagnostics['providers']['count']; ?>, 
                        Enabled: <?php echo $diagnostics['providers']['enabled'] ?? 0; ?>, 
                        Configured: <?php echo $diagnostics['providers']['configured'] ?? 0; ?>
                    <?php endif; ?>
                </p>
                <?php if ($diagnostics['providers']['status'] !== 'ok'): ?>
                    <div style="margin-top: 12px; display: flex; gap: 8px;">
                        <a href="configure-ai-keys.php" class="btn btn-sm btn-primary" style="text-decoration: none;">
                            → Quick Configure
                        </a>
                        <a href="../ai-governance.php" class="btn btn-sm btn-outline" style="text-decoration: none;">
                            → AI Governance
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bootstrap -->
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong>AI Bootstrap</strong>
                    <span class="badge <?php echo $diagnostics['bootstrap']['status'] === 'ok' ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $diagnostics['bootstrap']['status'] === 'ok' ? '✅ OK' : '❌ Error'; ?>
                    </span>
                </div>
                <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                    <?php echo e($diagnostics['bootstrap']['message']); ?>
                </p>
            </div>

            <!-- Manager -->
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong>AI Manager</strong>
                    <span class="badge <?php echo $diagnostics['manager']['status'] === 'ok' ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $diagnostics['manager']['status'] === 'ok' ? '✅ OK' : '❌ Error'; ?>
                    </span>
                </div>
                <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                    <?php echo e($diagnostics['manager']['message']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Provider List -->
    <?php if (!empty($diagnostics['provider_list'])): ?>
        <div class="card" style="padding: 24px; margin-bottom: 24px;">
            <h2 style="margin-top: 0;">🤖 Configured Providers</h2>
            
            <div style="display: grid; gap: 12px;">
                <?php foreach ($diagnostics['provider_list'] as $provider): ?>
                    <?php
                    $settings = json_decode($provider['settings_json'] ?? '{}', true);
                    $hasApiKey = !empty($settings['api_key'] ?? '');
                    $isEnabled = (int)($provider['is_enabled'] ?? 0) === 1;
                    $apiKeyValid = false;
                    if ($hasApiKey) {
                        try {
                            $decrypted = Crypto::decrypt($settings['api_key']);
                            $apiKeyValid = !empty($decrypted);
                        } catch (Exception $e) {
                            $apiKeyValid = false;
                        }
                    }
                    ?>
                    <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; border-left: 4px solid <?php echo $isEnabled && $apiKeyValid ? '#10b981' : '#ef4444'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="font-size: 18px;"><?php echo strtoupper(e($provider['provider_key'])); ?></strong>
                            <div style="display: flex; gap: 8px;">
                                <?php if ($isEnabled): ?>
                                    <span class="badge badge-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Disabled</span>
                                <?php endif; ?>
                                <?php if ($hasApiKey && $apiKeyValid): ?>
                                    <span class="badge badge-success">API Key OK</span>
                                <?php elseif ($hasApiKey): ?>
                                    <span class="badge badge-warning">API Key Invalid</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">No API Key</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="font-size: 14px; color: var(--secondary);">
                            Priority: <?php echo (int)($provider['failover_priority'] ?? 100); ?> | 
                            Model: <?php echo e($settings['model'] ?? 'N/A'); ?> | 
                            Updated: <?php echo date('M j, Y', strtotime($provider['updated_at'])); ?>
                        </div>
                        <?php if (!$isEnabled || !$apiKeyValid): ?>
                            <a href="../ai-governance.php?provider=<?php echo e($provider['provider_key']); ?>" class="btn btn-sm btn-primary" style="margin-top: 8px; text-decoration: none;">
                                → Configure Provider
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card" style="padding: 24px; background: linear-gradient(135deg, rgba(14,165,233,0.05) 0%, rgba(14,165,233,0.02) 100%); border: 1px solid rgba(14,165,233,0.2);">
        <h3 style="margin-top: 0;">⚡ Quick Actions</h3>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="configure-ai-keys.php" class="btn btn-primary" style="text-decoration: none;">
                🔧 Quick Configure Providers
            </a>
            <a href="../ai-governance.php" class="btn btn-outline" style="text-decoration: none;">
                ⚙️ AI Governance
            </a>
            <a href="setup-encryption-key.php" class="btn btn-outline" style="text-decoration: none;">
                🔐 Setup Encryption Key
            </a>
            <a href="../ai-assistant.php" class="btn btn-outline" style="text-decoration: none;">
                🧠 Test AI Assistant
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

