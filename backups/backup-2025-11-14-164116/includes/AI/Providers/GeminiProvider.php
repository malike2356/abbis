<?php

require_once __DIR__ . '/../AIProviderInterface.php';
require_once __DIR__ . '/../AIResponse.php';
require_once __DIR__ . '/../Exceptions/AIProviderException.php';

/**
 * Provider adapter for Google Gemini Generative Language API.
 */
class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $baseUrl = null, int $timeout = 30)
    {
        $this->apiKey = $apiKey ?: getenv('AI_GEMINI_API_KEY') ?: '';
        $this->model = $model ?: getenv('AI_GEMINI_MODEL') ?: 'gemini-1.5-flash-latest';
        $this->baseUrl = rtrim($baseUrl ?: (getenv('AI_GEMINI_BASE_URL') ?: 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->timeout = $timeout;

        if ($this->apiKey === '') {
            throw new AIProviderException('Missing Gemini API key.', AIProviderException::CODE_AUTH);
        }
    }

    public function getKey(): string
    {
        return 'gemini';
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function complete(array $messages, array $options = []): AIResponse
    {
        $systemInstruction = $this->extractSystemInstruction($messages);
        $contents = $this->buildContents($messages);

        if (empty($contents)) {
            throw new AIProviderException('Gemini requires at least one user message.', AIProviderException::CODE_VALIDATION);
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => array_filter([
                'temperature' => isset($options['temperature']) ? (float) $options['temperature'] : 0.3,
                'maxOutputTokens' => isset($options['max_tokens']) ? (int) $options['max_tokens'] : 512,
            ]),
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        $endpoint = sprintf(
            '%s/models/%s:generateContent?key=%s',
            $this->baseUrl,
            rawurlencode($options['model'] ?? $this->model),
            urlencode($this->apiKey)
        );

        $response = $this->performRequest($endpoint, $payload);

        if (empty($response['candidates'][0]['content']['parts'])) {
            throw new AIProviderException('Gemini returned no content.', AIProviderException::CODE_SERVICE, ['response' => $response]);
        }

        $parts = $response['candidates'][0]['content']['parts'];
        $content = $this->partsToText($parts);

        $usage = $response['usageMetadata'] ?? [];

        return new AIResponse(
            $this->getKey(),
            [
                [
                    'role' => 'assistant',
                    'content' => $content,
                ],
            ],
            $response,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            (int) ($usage['totalTokenCount'] ?? 0),
            false
        );
    }

    public function stream(array $messages, callable $onDelta, array $options = []): AIResponse
    {
        throw new AIProviderException('Gemini streaming not supported by this adapter.', AIProviderException::CODE_INTERNAL);
    }

    private function performRequest(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new AIProviderException('Gemini HTTP error: ' . $error, AIProviderException::CODE_SERVICE);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new AIProviderException('Gemini returned invalid JSON.', AIProviderException::CODE_SERVICE, ['raw' => $response]);
        }

        if ($status === 401 || $status === 403) {
            throw new AIProviderException('Gemini authentication failed.', AIProviderException::CODE_AUTH, $decoded);
        }

        if ($status === 429) {
            throw new AIProviderException('Gemini rate limit exceeded.', AIProviderException::CODE_RATE_LIMIT, $decoded);
        }

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? ('Gemini request failed with status ' . $status);
            throw new AIProviderException($message, AIProviderException::CODE_SERVICE, $decoded);
        }

        return $decoded;
    }

    private function extractSystemInstruction(array &$messages): ?string
    {
        $systemParts = [];
        foreach ($messages as $index => $message) {
            if (($message['role'] ?? null) === 'system') {
                $systemParts[] = (string) ($message['content'] ?? '');
                unset($messages[$index]);
            }
        }

        if (empty($systemParts)) {
            return null;
        }

        return implode("\n\n", array_filter($systemParts));
    }

    private function buildContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if ($role === 'assistant') {
                $geminiRole = 'model';
            } else {
                $geminiRole = 'user';
            }

            $contents[] = [
                'role' => $geminiRole,
                'parts' => [
                    ['text' => $content],
                ],
            ];
        }

        return $contents;
    }

    private function partsToText(array $parts): string
    {
        $texts = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $texts[] = (string) $part['text'];
            } elseif (isset($part['inline_data']['data'])) {
                $texts[] = '[binary data omitted]';
            }
        }

        return implode("\n\n", array_filter($texts));
    }
}


