<?php

require_once __DIR__ . '/../ContextBuilderInterface.php';
require_once __DIR__ . '/../ContextSlice.php';
require_once __DIR__ . '/../../../../config/database.php';

class UserContextBuilder implements ContextBuilderInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    public function getKey(): string
    {
        return 'user';
    }

    public function supports(array $options): bool
    {
        return !empty($options['user_id']);
    }

    public function build(array $options): array
    {
        // Check if last_login_at column exists
        $hasLastLogin = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
            $hasLastLogin = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Column doesn't exist or table doesn't exist
            $hasLastLogin = false;
        }

        // Build SELECT query dynamically based on available columns
        $columns = ['id', 'username', 'email', 'full_name', 'role'];
        if ($hasLastLogin) {
            $columns[] = 'last_login_at';
        }

        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $columns) . "
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', (int) $options['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [];
        }

        $payload = [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? '',
            'full_name' => $user['full_name'] ?? '',
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? '',
        ];

        // Only include last_login_at if the column exists
        if ($hasLastLogin && isset($user['last_login_at'])) {
            $payload['last_login_at'] = $user['last_login_at'];
        }

        return [
            new ContextSlice('user', $payload, priority: 10, approxTokens: 120, sensitive: true),
        ];
    }
}


