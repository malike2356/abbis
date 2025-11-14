<?php

require_once __DIR__ . '/../Exceptions/AIProviderException.php';
require_once __DIR__ . '/../../../config/database.php';

class UsageLimiter
{
    private PDO $pdo;
    private int $hourlyLimit;
    private int $dailyLimit;

    public function __construct(?PDO $pdo = null, ?int $hourlyLimit = null, ?int $dailyLimit = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->hourlyLimit = $hourlyLimit ?? (int) (getenv('AI_HOURLY_LIMIT') ?: AI_DEFAULT_HOURLY_LIMIT);
        $this->dailyLimit = $dailyLimit ?? (int) (getenv('AI_DAILY_LIMIT') ?: AI_DEFAULT_DAILY_LIMIT);
    }

    public function assertWithinLimits(int $userId, string $action): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Usage limiter requires authenticated user.');
        }

        if ($this->hourlyLimit > 0 && !$this->checkWindow($userId, $action, 1, $this->hourlyLimit)) {
            throw new AIProviderException(
                'Hourly AI usage limit reached. Please try again later.',
                AIProviderException::CODE_RATE_LIMIT,
                ['window' => 'hourly']
            );
        }

        if ($this->dailyLimit > 0 && !$this->checkWindow($userId, $action, 24, $this->dailyLimit)) {
            throw new AIProviderException(
                'Daily AI usage limit reached. Contact an administrator to increase quota.',
                AIProviderException::CODE_RATE_LIMIT,
                ['window' => 'daily']
            );
        }
    }

    private function checkWindow(int $userId, string $action, int $hours, int $limit): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM ai_usage_logs
                WHERE user_id = :user_id
                    AND action = :action
                    AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :hours HOUR)
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':action', $action);
            $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();

            $count = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Table not ready; log in development and allow request.
            if (APP_ENV === 'development') {
                error_log('[AI] Usage limiter query failed: ' . $e->getMessage());
            }
            return true;
        }

        return $count < $limit;
    }
}


