-- Rig Tracking System Migration
-- Supports both manual location updates and third-party GPS tracking API integration

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

USE `abbis_3_2`;

-- 1. Rig Location Tracking Table
-- Stores current and historical locations of rigs
CREATE TABLE IF NOT EXISTS `rig_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rig_id` int(11) NOT NULL,
  `latitude` decimal(10,6) NOT NULL,
  `longitude` decimal(10,6) NOT NULL,
  `accuracy` decimal(8,2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
  `speed` decimal(8,2) DEFAULT NULL COMMENT 'Speed in km/h',
  `heading` decimal(5,2) DEFAULT NULL COMMENT 'Direction in degrees (0-360)',
  `altitude` decimal(8,2) DEFAULT NULL COMMENT 'Altitude in meters',
  `location_source` enum('manual','gps_device','third_party_api','field_report') DEFAULT 'manual' COMMENT 'How location was obtained',
  `tracking_provider` varchar(100) DEFAULT NULL COMMENT 'Third-party provider name (e.g., Fleet Complete, Samsara)',
  `device_id` varchar(255) DEFAULT NULL COMMENT 'GPS device or vehicle ID from tracking provider',
  `address` text COMMENT 'Reverse geocoded address',
  `notes` text COMMENT 'Additional notes about location',
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When location was recorded',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rig_id` (`rig_id`),
  KEY `recorded_at` (`recorded_at`),
  KEY `location_source` (`location_source`),
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Rig location tracking - current and historical positions';

-- 2. Rig Tracking Configuration Table
-- Stores tracking settings for each rig (GPS device info, API credentials, etc.)
CREATE TABLE IF NOT EXISTS `rig_tracking_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rig_id` int(11) NOT NULL,
  `tracking_enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether tracking is active for this rig',
  `tracking_method` enum('manual','gps_device','third_party_api') DEFAULT 'manual' COMMENT 'How this rig is tracked',
  `tracking_provider` varchar(100) DEFAULT NULL COMMENT 'Third-party provider name',
  `device_id` varchar(255) DEFAULT NULL COMMENT 'GPS device ID or vehicle ID',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'API key for third-party provider',
  `api_secret` varchar(255) DEFAULT NULL COMMENT 'API secret (encrypted)',
  `update_frequency` int(11) DEFAULT 300 COMMENT 'Update frequency in seconds (default 5 minutes)',
  `last_update` timestamp NULL DEFAULT NULL COMMENT 'Last successful location update',
  `last_latitude` decimal(10,6) DEFAULT NULL,
  `last_longitude` decimal(10,6) DEFAULT NULL,
  `status` enum('active','inactive','error') DEFAULT 'active',
  `error_message` text COMMENT 'Last error message if status is error',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rig_id` (`rig_id`),
  KEY `tracking_enabled` (`tracking_enabled`),
  KEY `status` (`status`),
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Rig tracking configuration and settings';

-- 3. Add location fields to rigs table if they don't exist
-- These store the current/last known location
-- Note: MySQL doesn't support IF NOT EXISTS for ALTER TABLE, so check columns first
-- Run these one at a time if columns already exist

-- Check and add current_latitude
SET @dbname = DATABASE();
SET @tablename = 'rigs';
SET @columnname = 'current_latitude';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' decimal(10,6) DEFAULT NULL COMMENT ''Current latitude''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add current_longitude
SET @columnname = 'current_longitude';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' decimal(10,6) DEFAULT NULL COMMENT ''Current longitude''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add current_location_updated_at
SET @columnname = 'current_location_updated_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' timestamp NULL DEFAULT NULL COMMENT ''When current location was last updated''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add tracking_enabled
SET @columnname = 'tracking_enabled';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' tinyint(1) DEFAULT 0 COMMENT ''Whether location tracking is enabled''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. Create indexes for faster location queries
-- Note: MySQL doesn't support IF NOT EXISTS for CREATE INDEX, so check first
-- These will fail silently if indexes already exist

-- Index for rig_locations
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = 'rig_locations')
      AND (table_schema = DATABASE())
      AND (index_name = 'idx_rig_locations_rig_recorded')
  ) > 0,
  'SELECT 1',
  'CREATE INDEX idx_rig_locations_rig_recorded ON rig_locations (rig_id, recorded_at DESC)'
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- Index for rigs
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = 'rigs')
      AND (table_schema = DATABASE())
      AND (index_name = 'idx_rigs_tracking')
  ) > 0,
  'SELECT 1',
  'CREATE INDEX idx_rigs_tracking ON rigs (tracking_enabled, status)'
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- Success message
SELECT 'Rig tracking tables created successfully!' as status;

