CREATE TABLE IF NOT EXISTS ai_usage_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    role VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NULL,
    prompt_tokens INT DEFAULT 0,
    completion_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    latency_ms INT DEFAULT NULL,
    input_hash CHAR(64) DEFAULT NULL,
    context_summary VARCHAR(255) DEFAULT NULL,
    is_success TINYINT(1) DEFAULT 0,
    error_code VARCHAR(50) DEFAULT NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_action (action),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_response_cache (
    cache_key VARCHAR(191) PRIMARY KEY,
    response_json LONGTEXT NOT NULL,
    metadata_json JSON NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_provider_config (
    provider_key VARCHAR(50) PRIMARY KEY,
    is_enabled TINYINT(1) DEFAULT 1,
    daily_limit INT DEFAULT NULL,
    monthly_limit INT DEFAULT NULL,
    failover_priority INT DEFAULT 100,
    settings_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

