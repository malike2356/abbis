<?php

require_once __DIR__ . '/../AIProviderInterface.php';
require_once __DIR__ . '/../AIResponse.php';
require_once __DIR__ . '/../Exceptions/AIProviderException.php';

/**
 * Provider adapter for DeepSeek (OpenAI compatible) endpoints.
 */
class DeepSeekProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $baseUrl = null, int $timeout = 30)
    {
        $this->apiKey = $apiKey ?: getenv('AI_DEEPSEEK_API_KEY') ?: '';
        $this->model = $model ?: getenv('AI_DEEPSEEK_MODEL') ?: 'deepseek-chat';
        $this->baseUrl = rtrim($baseUrl ?: (getenv('AI_DEEPSEEK_BASE_URL') ?: 'https://api.deepseek.com/v1'), '/');
        $this->timeout = $timeout;

        if ($this->apiKey === '') {
            throw new AIProviderException('Missing DeepSeek API key.', AIProviderException::CODE_AUTH);
        }
    }

    public function getKey(): string
    {
        return 'deepseek';
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function complete(array $messages, array $options = []): AIResponse
    {
        $endpoint = $this->baseUrl . '/chat/completions';
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 512,
        ];

        $response = $this->performRequest($endpoint, $payload);

        if (empty($response['choices'][0]['message']['content'])) {
            throw new AIProviderException('DeepSeek returned no content.', AIProviderException::CODE_SERVICE, ['response' => $response]);
        }

        $content = $response['choices'][0]['message']['content'];
        $usage = $response['usage'] ?? [];

        return new AIResponse(
            $this->getKey(),
            [
                [
                    'role' => 'assistant',
                    'content' => $content,
                ],
            ],
            $response,
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
            0,
            false
        );
    }

    public function stream(array $messages, callable $onDelta, array $options = []): AIResponse
    {
        $endpoint = $this->baseUrl . '/chat/completions';
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'stream' => true,
        ];

        $ch = curl_init($endpoint);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onDelta) {
                $lines = explode("\n", trim($data));
                foreach ($lines as $line) {
                    if (stripos($line, 'data:') !== 0) {
                        continue;
                    }

                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        continue;
                    }

                    $decoded = json_decode($json, true);
                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        $onDelta($decoded['choices'][0]['delta']['content']);
                    }
                }

                return strlen($data);
            },
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AIProviderException('DeepSeek stream error: ' . $error, AIProviderException::CODE_SERVICE);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new AIProviderException('DeepSeek streaming request failed with status ' . $status, AIProviderException::CODE_SERVICE);
        }

        return new AIResponse($this->getKey(), [], [], 0, 0, 0, false);
    }

    private function performRequest(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AIProviderException('DeepSeek HTTP error: ' . $error, AIProviderException::CODE_SERVICE);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new AIProviderException('DeepSeek returned invalid JSON.', AIProviderException::CODE_SERVICE, ['raw' => $response]);
        }

        if ($status === 401) {
            throw new AIProviderException('DeepSeek authentication failed.', AIProviderException::CODE_AUTH, $decoded);
        }

        if ($status === 429) {
            throw new AIProviderException('DeepSeek rate limit exceeded.', AIProviderException::CODE_RATE_LIMIT, $decoded);
        }

        if ($status >= 400) {
            throw new AIProviderException('DeepSeek request failed with status ' . $status, AIProviderException::CODE_SERVICE, $decoded);
        }

        return $decoded;
    }
}


