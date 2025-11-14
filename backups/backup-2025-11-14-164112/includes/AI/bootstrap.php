<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../crypto.php';
require_once __DIR__ . '/AIServiceBus.php';
require_once __DIR__ . '/AIInsightManager.php';
require_once __DIR__ . '/Exceptions/AIProviderException.php';
require_once __DIR__ . '/Providers/OpenAIProvider.php';
require_once __DIR__ . '/Providers/DeepSeekProvider.php';
require_once __DIR__ . '/Providers/GeminiProvider.php';
require_once __DIR__ . '/Providers/OllamaProvider.php';
require_once __DIR__ . '/Context/ContextAssembler.php';
require_once __DIR__ . '/Context/Builders/UserContextBuilder.php';
require_once __DIR__ . '/Context/Builders/OrganisationContextBuilder.php';
require_once __DIR__ . '/Context/Builders/EntityContextBuilder.php';
require_once __DIR__ . '/Context/Builders/BusinessIntelligenceContextBuilder.php';
require_once __DIR__ . '/Context/Builders/PageContextBuilder.php';
require_once __DIR__ . '/Governance/UsageLimiter.php';
require_once __DIR__ . '/Governance/AuditLogger.php';

/**
 * Load provider configuration overrides from the database.
 *
 * @param bool $forceRefresh Refresh cached values if true.
 * @return array<string, array>
 */
function ai_load_provider_configs(bool $forceRefresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$forceRefresh) {
        return $cache;
    }

    $cache = [];

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json, updated_at FROM ai_provider_config");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $providerKey = strtolower(trim((string) ($row['provider_key'] ?? '')));
            if ($providerKey === '') {
                continue;
            }

            $settings = [];
            if (!empty($row['settings_json'])) {
                $decoded = json_decode($row['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }

            $apiKeyEncrypted = $settings['api_key'] ?? null;
            $apiKeyPlain = null;

            if ($apiKeyEncrypted) {
                try {
                    $apiKeyPlain = Crypto::decrypt($apiKeyEncrypted);
                } catch (Throwable $e) {
                    if (APP_ENV === 'development') {
                        error_log('[AI] Failed to decrypt API key for ' . $providerKey . ': ' . $e->getMessage());
                    }
                    $apiKeyPlain = null;
                }
            }

            unset($settings['api_key']);

            $cache[$providerKey] = [
                'provider_key' => $providerKey,
                'is_enabled' => (int) ($row['is_enabled'] ?? 0),
                'daily_limit' => isset($row['daily_limit']) ? (int) $row['daily_limit'] : null,
                'monthly_limit' => isset($row['monthly_limit']) ? (int) $row['monthly_limit'] : null,
                'failover_priority' => isset($row['failover_priority']) ? (int) $row['failover_priority'] : 100,
                'settings' => $settings,
                'api_key_encrypted' => $apiKeyEncrypted,
                'api_key_plain' => $apiKeyPlain,
                'has_api_key' => !empty($apiKeyEncrypted),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
    } catch (Throwable $e) {
        if (APP_ENV === 'development') {
            error_log('[AI] Failed to load provider configuration: ' . $e->getMessage());
        }
        $cache = [];
    }

    return $cache;
}

/**
 * Factory helper to build the AI insight manager with default providers and builders.
 */
function ai_insight_manager(): AIInsightManager
{
    static $instance = null;

    if ($instance instanceof AIInsightManager) {
        return $instance;
    }

    $providerConfigs = ai_load_provider_configs();

    $enabledProviders = array_values(array_filter($providerConfigs, static function (array $config): bool {
        return (int) ($config['is_enabled'] ?? 0) === 1;
    }));

    usort($enabledProviders, static function (array $a, array $b): int {
        $priorityCompare = ($a['failover_priority'] ?? 100) <=> ($b['failover_priority'] ?? 100);
        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }
        return strcmp($a['provider_key'] ?? '', $b['provider_key'] ?? '');
    });

    $failoverFromEnv = getenv('AI_PROVIDER_FAILOVER');
    if (is_string($failoverFromEnv) && trim($failoverFromEnv) !== '') {
        $failoverOrder = $failoverFromEnv;
    } elseif (!empty($enabledProviders)) {
        $failoverOrder = implode(',', array_column($enabledProviders, 'provider_key'));
    } else {
        $failoverOrder = implode(',', AI_DEFAULT_PROVIDER_FAILOVER);
    }

    $config = [
        'failover_order' => $failoverOrder,
    ];

    $bus = new AIServiceBus($config);

    $configuredProviders = getenv('AI_PROVIDERS');
    if (is_string($configuredProviders) && trim($configuredProviders) !== '') {
        $providerOrder = array_values(array_filter(array_map('trim', explode(',', $configuredProviders))));
    } elseif (!empty($enabledProviders)) {
        $providerOrder = array_column($enabledProviders, 'provider_key');
    } else {
        $providerOrder = AI_DEFAULT_PROVIDER_FAILOVER;
    }

    $registeredProviders = 0;

    foreach ($providerOrder as $providerKey) {
        $providerKey = strtolower(trim((string) $providerKey));
        if ($providerKey === '') {
            continue;
        }

        $configRow = $providerConfigs[$providerKey] ?? null;
        if ($configRow && (int) ($configRow['is_enabled'] ?? 0) !== 1) {
            continue;
        }

        // Skip providers that require API keys but don't have them
        $apiKeyPlain = $configRow['api_key_plain'] ?? null;
        $requiresApiKey = in_array($providerKey, ['openai', 'deepseek', 'gemini'], true);
        if ($requiresApiKey && (empty($apiKeyPlain) || trim($apiKeyPlain) === '')) {
            if (APP_ENV === 'development') {
                error_log(sprintf('[AI] Skipping provider "%s": API key not configured.', $providerKey));
            }
            continue;
        }

        $settings = $configRow['settings'] ?? [];

        try {
            switch ($providerKey) {
                case 'openai':
                    $timeout = isset($settings['timeout']) ? max(5, (int) $settings['timeout']) : 30;
                    $bus->registerProvider(new OpenAIProvider(
                        $apiKeyPlain,
                        $settings['model'] ?? null,
                        $settings['base_url'] ?? null,
                        $timeout
                    ));
                    $registeredProviders++;
                    break;

                case 'deepseek':
                    $timeout = isset($settings['timeout']) ? max(5, (int) $settings['timeout']) : 30;
                    $bus->registerProvider(new DeepSeekProvider(
                        $apiKeyPlain,
                        $settings['model'] ?? null,
                        $settings['base_url'] ?? null,
                        $timeout
                    ));
                    $registeredProviders++;
                    break;

                case 'gemini':
                    $timeout = isset($settings['timeout']) ? max(5, (int) $settings['timeout']) : 30;
                    $bus->registerProvider(new GeminiProvider(
                        $apiKeyPlain,
                        $settings['model'] ?? null,
                        $settings['base_url'] ?? null,
                        $timeout
                    ));
                    $registeredProviders++;
                    break;

                case 'ollama':
                    $timeout = isset($settings['timeout']) ? max(5, (int) $settings['timeout']) : null;
                    $bus->registerProvider(new OllamaProvider(
                        $settings['base_url'] ?? null,
                        $settings['model'] ?? null,
                        $timeout
                    ));
                    $registeredProviders++;
                    break;

                default:
                    if (APP_ENV === 'development') {
                        error_log('[AI] Unknown AI provider key: ' . $providerKey);
                    }
                    break;
            }
        } catch (AIProviderException $e) {
            if (APP_ENV === 'development') {
                error_log(sprintf('[AI] Failed to register provider "%s": %s', $providerKey, $e->getMessage()));
            }
        }
    }

    if ($registeredProviders === 0 && APP_ENV === 'development') {
        error_log('[AI] Warning: No AI providers were registered. Check provider configuration and credentials.');
    }

    // Increased budget to accommodate Business Intelligence context
    // Default: 8000 tokens (was 3200) to include BI data
    $assembler = new ContextAssembler((int) (getenv('AI_CONTEXT_TOKEN_BUDGET') ?: 8000));
    $assembler->registerBuilder(new UserContextBuilder());
    $assembler->registerBuilder(new OrganisationContextBuilder());
    $assembler->registerBuilder(new PageContextBuilder()); // Page context before entity context
    $assembler->registerBuilder(new EntityContextBuilder());
    $assembler->registerBuilder(new BusinessIntelligenceContextBuilder());

    $limiter = new UsageLimiter();
    $auditLogger = new AIAuditLogger();

    $instance = new AIInsightManager($bus, $assembler, $limiter, $auditLogger);
    return $instance;
}


