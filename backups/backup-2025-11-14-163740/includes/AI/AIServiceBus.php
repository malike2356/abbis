<?php

require_once __DIR__ . '/AIProviderInterface.php';
require_once __DIR__ . '/AIResponse.php';
require_once __DIR__ . '/Exceptions/AIProviderException.php';

/**
 * Central registry & router for AI providers.
 */
class AIServiceBus
{
    /**
     * @var AIProviderInterface[]
     */
    private array $providers = [];

    /**
     * @var array<string, mixed>
     */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function registerProvider(AIProviderInterface $provider): void
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    /**
     * Execute request using configured failover order.
     *
     * @throws AIProviderException
     */
    public function complete(array $messages, array $options = []): AIResponse
    {
        $order = $this->resolveFailoverOrder($options);
        $exceptions = [];

        foreach ($order as $providerKey) {
            if (!isset($this->providers[$providerKey])) {
                continue;
            }

            $provider = $this->providers[$providerKey];

            try {
                return $provider->complete($messages, $options);
            } catch (AIProviderException $e) {
                $exceptions[$providerKey] = $e;

                if ($e->getCategory() === AIProviderException::CODE_VALIDATION) {
                    throw $e;
                }

                // Continue to next provider for retryable categories.
                continue;
            }
        }

        if (!empty($exceptions)) {
            $last = end($exceptions);
            throw new AIProviderException(
                'All AI providers failed: ' . implode(', ', array_keys($exceptions)),
                $last->getCategory(),
                [
                    'errors' => array_map(fn(AIProviderException $ex) => [
                        'provider' => $ex->getMessage(),
                        'category' => $ex->getCategory(),
                        'context' => $ex->getContext(),
                    ], $exceptions),
                ],
                $last->getCode(),
                $last
            );
        }

        throw new AIProviderException('No AI providers registered.', AIProviderException::CODE_INTERNAL);
    }

    private function resolveFailoverOrder(array $options): array
    {
        $override = $options['providers'] ?? $options['provider'] ?? null;
        if (is_string($override) && $override !== '') {
            return [$override];
        }

        if (is_array($override) && !empty($override)) {
            return array_values(array_unique(array_filter($override)));
        }

        $configOrder = $this->config['failover_order'] ?? AI_DEFAULT_PROVIDER_FAILOVER;
        if (is_string($configOrder)) {
            $configOrder = array_map('trim', explode(',', $configOrder));
        }

        if (!is_array($configOrder) || empty($configOrder)) {
            $configOrder = AI_DEFAULT_PROVIDER_FAILOVER;
        }

        return array_values(array_unique(array_filter($configOrder, fn($item) => is_string($item) && $item !== '')));
    }
}


