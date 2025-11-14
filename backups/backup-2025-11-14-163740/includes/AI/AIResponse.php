<?php

/**
 * Value object representing a normalised AI response.
 */
class AIResponse
{
    private string $providerKey;
    private array $messages;
    private array $rawPayload;
    private int $promptTokens;
    private int $completionTokens;
    private int $latencyMs;
    private bool $fromCache;

    public function __construct(
        string $providerKey,
        array $messages,
        array $rawPayload = [],
        int $promptTokens = 0,
        int $completionTokens = 0,
        int $latencyMs = 0,
        bool $fromCache = false
    ) {
        $this->providerKey = $providerKey;
        $this->messages = $messages;
        $this->rawPayload = $rawPayload;
        $this->promptTokens = $promptTokens;
        $this->completionTokens = $completionTokens;
        $this->latencyMs = $latencyMs;
        $this->fromCache = $fromCache;
    }

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    public function getLatencyMs(): int
    {
        return $this->latencyMs;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->providerKey,
            'messages' => $this->messages,
            'raw' => $this->rawPayload,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->getTotalTokens(),
            'latency_ms' => $this->latencyMs,
            'from_cache' => $this->fromCache,
        ];
    }
}


