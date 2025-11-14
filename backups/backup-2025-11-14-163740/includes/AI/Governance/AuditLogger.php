<?php

require_once __DIR__ . '/../../../config/database.php';

class AIAuditLogger
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    public function log(array $payload): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_usage_logs
                    (user_id, role, action, provider, prompt_tokens, completion_tokens, total_tokens, latency_ms, input_hash, context_summary, is_success, error_code, metadata_json)
                VALUES
                    (:user_id, :role, :action, :provider, :prompt_tokens, :completion_tokens, :total_tokens, :latency_ms, :input_hash, :context_summary, :is_success, :error_code, :metadata_json)
            ");

            $stmt->execute([
                ':user_id' => $payload['user_id'] ?? null,
                ':role' => $payload['role'] ?? null,
                ':action' => $payload['action'] ?? null,
                ':provider' => $payload['provider'] ?? null,
                ':prompt_tokens' => $payload['prompt_tokens'] ?? 0,
                ':completion_tokens' => $payload['completion_tokens'] ?? 0,
                ':total_tokens' => $payload['total_tokens'] ?? 0,
                ':latency_ms' => $payload['latency_ms'] ?? 0,
                ':input_hash' => $payload['input_hash'] ?? null,
                ':context_summary' => $payload['context_summary'] ?? null,
                ':is_success' => $payload['is_success'] ?? 0,
                ':error_code' => $payload['error_code'] ?? null,
                ':metadata_json' => isset($payload['metadata_json']) ? $payload['metadata_json'] : json_encode($payload['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                error_log('[AI] Failed to log usage: ' . $e->getMessage());
            }
        }
    }
}


