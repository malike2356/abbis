-- Rig Tracking Provider API configuration enhancements
-- Adds support for storing remote provider endpoints, authentication method,
-- and flexible response parsing metadata to enable live GPS integrations.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

USE `abbis_3_2`;

ALTER TABLE `rig_tracking_config`
    ADD COLUMN `api_base_url` varchar(255) DEFAULT NULL AFTER `api_secret`,
    ADD COLUMN `auth_method` enum('none','bearer_token','api_key_header','query_param','basic_auth') DEFAULT 'bearer_token' AFTER `api_base_url`,
    ADD COLUMN `config_payload` text DEFAULT NULL COMMENT 'JSON blob containing provider-specific endpoint and field mappings' AFTER `update_frequency`;

-- Track last error timestamp when provider sync fails
ALTER TABLE `rig_tracking_config`
    ADD COLUMN `last_error_at` timestamp NULL DEFAULT NULL AFTER `last_update`;

-- Ensure status column default remains valid after the new fields
UPDATE `rig_tracking_config`
SET `auth_method` = COALESCE(`auth_method`, 'bearer_token');

SELECT 'Rig tracking provider columns added successfully' AS status;

