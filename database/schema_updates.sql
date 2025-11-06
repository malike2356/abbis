-- Schema Updates for Enhanced ABBIS System
-- Run this after the main schema.sql

USE `abbis_3_2`;

-- Cache table for dashboard stats
CREATE TABLE IF NOT EXISTS `cache_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(100) NOT NULL,
  `cache_value` text,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rig fee debts tracking
CREATE TABLE IF NOT EXISTS `rig_fee_debts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rig_id` int(11) NOT NULL,
  `report_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `amount_charged` decimal(12,2) NOT NULL,
  `amount_collected` decimal(12,2) DEFAULT '0.00',
  `outstanding_balance` decimal(12,2) NOT NULL,
  `status` enum('pending','partially_paid','paid','bad_debt') DEFAULT 'pending',
  `issue_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rig_id` (`rig_id`),
  KEY `report_id` (`report_id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email notifications queue
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT '0',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compliance documents
CREATE TABLE IF NOT EXISTS `compliance_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `document_type` enum('survey','contract') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Material transactions log
CREATE TABLE IF NOT EXISTS `material_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_type` enum('screen_pipe','plain_pipe','gravel') NOT NULL,
  `transaction_type` enum('received','used','sold','purchased') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `total_cost` decimal(10,2) DEFAULT '0.00',
  `report_id` int(11) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_type` (`material_type`),
  KEY `report_id` (`report_id`),
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for better query performance
ALTER TABLE `field_reports` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `field_reports` ADD INDEX `idx_report_date` (`report_date`);
ALTER TABLE `payroll_entries` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `loans` ADD INDEX `idx_status` (`status`);
ALTER TABLE `materials_inventory` ADD INDEX `idx_material_type` (`material_type`);

-- Add column for materials received at site (from field reports)
ALTER TABLE `field_reports` 
ADD COLUMN IF NOT EXISTS `screen_pipes_received` int(11) DEFAULT '0' AFTER `screen_pipes_used`,
ADD COLUMN IF NOT EXISTS `plain_pipes_received` int(11) DEFAULT '0' AFTER `plain_pipes_used`,
ADD COLUMN IF NOT EXISTS `gravel_received` int(11) DEFAULT '0' AFTER `gravel_used`;

-- Add compliance fields
ALTER TABLE `field_reports`
ADD COLUMN IF NOT EXISTS `survey_document` varchar(255) DEFAULT NULL AFTER `recommendation_log`,
ADD COLUMN IF NOT EXISTS `contract_document` varchar(255) DEFAULT NULL AFTER `survey_document`;

-- Add rod lengths configuration
ALTER TABLE `system_config` 
ADD COLUMN IF NOT EXISTS `config_category` varchar(50) DEFAULT 'general' AFTER `config_type`;

-- Update config to support rod lengths as JSON
UPDATE `system_config` SET `config_value` = '["3.0","3.5","4.0","4.2","4.5","5.0","5.2","5.5"]' WHERE `config_key` = 'default_rod_lengths';

COMMIT;

