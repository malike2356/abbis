<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AIServiceBus.php';
require_once __DIR__ . '/Context/ContextAssembler.php';
require_once __DIR__ . '/Governance/UsageLimiter.php';
require_once __DIR__ . '/Governance/AuditLogger.php';
require_once __DIR__ . '/Prompting/PromptTemplate.php';
require_once __DIR__ . '/AIResponse.php';

class AIInsightManager
{
    private AIServiceBus $bus;
    private ContextAssembler $assembler;
    private UsageLimiter $limiter;
    private AIAuditLogger $auditLogger;
    private PDO $pdo;

    public function __construct(AIServiceBus $bus, ContextAssembler $assembler, UsageLimiter $limiter, AIAuditLogger $auditLogger, ?PDO $pdo = null)
    {
        $this->bus = $bus;
        $this->assembler = $assembler;
        $this->limiter = $limiter;
        $this->auditLogger = $auditLogger;
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * High-level orchestration for assistant chat.
     */
    public function runAssistant(array $request, array $options = []): AIResponse
    {
        $userId = (int) ($options['user_id'] ?? 0);
        $action = $options['action'] ?? 'assistant_chat';

        $this->limiter->assertWithinLimits($userId, $action);

        $contextSlices = $this->assembler->assemble($request);
        $systemPrompt = $this->renderSystemPrompt($contextSlices, $options);

        $messages = $this->buildMessagePayload($systemPrompt, $request['messages'] ?? []);

        $start = microtime(true);
        $response = null;
        $error = null;

        try {
            $response = $this->bus->complete($messages, $options);
            $this->logUsage($response, $contextSlices, $options + [
                'user_id' => $userId,
                'action' => $action,
                'is_success' => 1,
            ]);
        } catch (AIProviderException $e) {
            $error = $e;
            $this->logUsage(null, $contextSlices, $options + [
                'user_id' => $userId,
                'action' => $action,
                'is_success' => 0,
                'error_code' => $e->getCategory(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($response instanceof AIResponse) {
                $latency = (int) round((microtime(true) - $start) * 1000);
                $response = new AIResponse(
                    $response->getProviderKey(),
                    $response->getMessages(),
                    $response->getRawPayload(),
                    $response->getPromptTokens(),
                    $response->getCompletionTokens(),
                    $latency,
                    $response->isFromCache()
                );
            }
        }

        return $response;
    }

    private function buildMessagePayload(string $systemPrompt, array $userMessages): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        foreach ($userMessages as $message) {
            if (!isset($message['role'], $message['content'])) {
                continue;
            }
            $messages[] = [
                'role' => $message['role'],
                'content' => (string) $message['content'],
            ];
        }

        return $messages;
    }

    private function renderSystemPrompt(array $contextSlices, array $options): string
    {
        $templatePath = __DIR__ . '/Prompting/templates/assistant-base.md';
        if (!file_exists($templatePath)) {
            $default = <<<'PROMPT'
You are ABBIS, an enterprise service delivery analyst assistant. Provide clear, concise, and actionable insights.
- Respect data governance, confidentiality, and compliance policies.
- Reference only the data provided in the context.
- Highlight uncertainties and suggest next best actions.
- When users ask about "this page" or "what happens on this page", use the current_page context to explain what the page does, its purpose, and key features.
PROMPT;
            file_put_contents($templatePath, $default);
        }

        $template = PromptTemplate::fromFile($templatePath);

        return $template->render([
            'context_json' => json_encode($contextSlices, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'organisation' => $options['organisation_name'] ?? 'ABBIS Organisation',
        ]);
    }

    private function logUsage(?AIResponse $response, array $contextSlices, array $options): void
    {
        $metadata = [
            'context_types' => array_map(static fn(array $slice) => $slice['type'] ?? 'unknown', $contextSlices),
            'from_cache' => $response ? $response->isFromCache() : false,
            'options' => array_diff_key($options, array_flip(['messages', 'user_id', 'role'])),
        ];

        $totalTokens = $response ? $response->getTotalTokens() : 0;

        $this->auditLogger->log([
            'user_id' => $options['user_id'] ?? null,
            'role' => $options['role'] ?? null,
            'action' => $options['action'] ?? null,
            'provider' => $response ? $response->getProviderKey() : null,
            'prompt_tokens' => $response ? $response->getPromptTokens() : 0,
            'completion_tokens' => $response ? $response->getCompletionTokens() : 0,
            'total_tokens' => $totalTokens,
            'latency_ms' => $response ? $response->getLatencyMs() : null,
            'input_hash' => $this->hashRequest($options['messages'] ?? []),
            'context_summary' => substr(json_encode($contextSlices), 0, 255),
            'is_success' => $options['is_success'] ?? 0,
            'error_code' => $options['error_code'] ?? null,
            'metadata' => $metadata,
        ]);
    }

    private function hashRequest(array $messages): string
    {
        return hash('sha256', json_encode($messages, JSON_UNESCAPED_SLASHES));
    }
}


