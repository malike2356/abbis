<?php

require_once __DIR__ . '/AIResponse.php';
require_once __DIR__ . '/Exceptions/AIProviderException.php';

interface AIProviderInterface
{
    /**
     * Unique provider key, e.g. "openai".
     */
    public function getKey(): string;

    /**
     * Whether provider supports server-sent event streaming.
     */
    public function supportsStreaming(): bool;

    /**
     * Execute completion request using structured chat messages.
     *
     * @param array $messages Array of ['role' => system|user|assistant|tool, 'content' => string]
     * @param array $options Model specific options (temperature, max_tokens, etc.)
     *
     * @throws AIProviderException
     */
    public function complete(array $messages, array $options = []): AIResponse;

    /**
     * Streaming interface, optional.
     *
     * @param array    $messages
     * @param callable $onDelta function(array $chunk): void
     * @param array    $options
     *
     * @throws AIProviderException
     */
    public function stream(array $messages, callable $onDelta, array $options = []): AIResponse;
}


