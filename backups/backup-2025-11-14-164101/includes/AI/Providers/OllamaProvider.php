<?php

require_once __DIR__ . '/../AIProviderInterface.php';
require_once __DIR__ . '/../AIResponse.php';
require_once __DIR__ . '/../Exceptions/AIProviderException.php';

/**
 * Provider adapter for self-hosted Ollama instances.
 */
class OllamaProvider implements AIProviderInterface
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $model = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?: (getenv('AI_OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434'), '/');
        $this->model = $model ?: (getenv('AI_OLLAMA_MODEL') ?: 'llama3');
        $this->timeout = $timeout ?? (int) (getenv('AI_OLLAMA_TIMEOUT') ?: 120);

        if ($this->baseUrl === '') {
            throw new AIProviderException('Ollama base URL not configured.', AIProviderException::CODE_INTERNAL);
        }
    }

    public function getKey(): string
    {
        return 'ollama';
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function complete(array $messages, array $options = []): AIResponse
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->normaliseMessages($messages),
            'stream' => false,
        ];

        $this->applyOptions($payload, $options);

        $response = $this->performRequest($payload);

        if (empty($response['message']['content'])) {
            throw new AIProviderException('Ollama returned no content.', AIProviderException::CODE_SERVICE, ['response' => $response]);
        }

        $content = $response['message']['content'];

        return new AIResponse(
            $this->getKey(),
            [
                [
                    'role' => 'assistant',
                    'content' => $content,
                ],
            ],
            $response,
            (int) ($response['prompt_eval_count'] ?? 0),
            (int) ($response['eval_count'] ?? 0),
            (int) ($response['total_duration'] ?? 0),
            false
        );
    }

    public function stream(array $messages, callable $onDelta, array $options = []): AIResponse
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $this->normaliseMessages($messages),
            'stream' => true,
        ];

        $this->applyOptions($payload, $options);

        $endpoint = $this->baseUrl . '/api/chat';
        $ch = curl_init($endpoint);

        $errorRef = null;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onDelta, &$errorRef) {
                $lines = explode("\n", trim($data));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    if (!is_array($decoded)) {
                        continue;
                    }

                    if (!empty($decoded['error'])) {
                        $errorRef = $decoded['error'];
                        return 0;
                    }

                    if (!empty($decoded['message']['content'])) {
                        $onDelta($decoded['message']['content']);
                    }
                }

                return strlen($data);
            },
            CURLOPT_RETURNTRANSFER => false,
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AIProviderException('Ollama stream failed: ' . $error, AIProviderException::CODE_SERVICE);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errorRef !== null) {
            throw new AIProviderException('Ollama streaming error: ' . $errorRef, AIProviderException::CODE_SERVICE);
        }

        if ($status < 200 || $status >= 300) {
            throw new AIProviderException('Ollama streaming request failed with status ' . $status, AIProviderException::CODE_SERVICE);
        }

        return new AIResponse($this->getKey(), [], [], 0, 0, 0, false);
    }

    private function performRequest(array $payload): array
    {
        $endpoint = $this->baseUrl . '/api/chat';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AIProviderException('Ollama HTTP error: ' . $error, AIProviderException::CODE_SERVICE);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new AIProviderException('Ollama returned invalid JSON.', AIProviderException::CODE_SERVICE, ['raw' => $response]);
        }

        if ($status >= 400) {
            $message = $decoded['error'] ?? ('Ollama request failed with status ' . $status);
            throw new AIProviderException($message, AIProviderException::CODE_SERVICE, $decoded);
        }

        return $decoded;
    }

    private function normaliseMessages(array $messages): array
    {
        $normalised = [];

        foreach ($messages as $message) {
            if (!isset($message['role'], $message['content'])) {
                continue;
            }

            $normalised[] = [
                'role' => $message['role'],
                'content' => (string) $message['content'],
            ];
        }

        return $normalised;
    }

    private function applyOptions(array &$payload, array $options): void
    {
        $temperature = $options['temperature'] ?? null;
        $maxTokens = $options['max_tokens'] ?? null;

        if ($temperature !== null || $maxTokens !== null) {
            $payload['options'] = array_filter([
                'temperature' => $temperature !== null ? (float) $temperature : null,
                'num_predict' => $maxTokens !== null ? (int) $maxTokens : null,
            ], static fn($value) => $value !== null);
        }
    }
}


