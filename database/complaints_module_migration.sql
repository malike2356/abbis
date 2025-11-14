-- Complaints & Feedback Module Migration
-- Creates core tables for complaint tracking workflows

USE `abbis_3_2`;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `complaint_code` VARCHAR(30) NOT NULL,
  `source` VARCHAR(100) DEFAULT NULL,
  `channel` ENUM('phone','email','web','mobile','walk_in','other') DEFAULT 'other',
  `customer_name` VARCHAR(150) DEFAULT NULL,
  `customer_email` VARCHAR(150) DEFAULT NULL,
  `customer_phone` VARCHAR(50) DEFAULT NULL,
  `customer_reference` VARCHAR(120) DEFAULT NULL,
  `category` VARCHAR(120) DEFAULT NULL,
  `subcategory` VARCHAR(120) DEFAULT NULL,
  `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `status` ENUM('new','triage','in_progress','awaiting_customer','resolved','closed','cancelled') DEFAULT 'new',
  `summary` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `satisfaction_rating` TINYINT DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `complaint_code` (`complaint_code`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `complaint_updates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` INT(11) NOT NULL,
  `update_type` ENUM('note','status_change','assignment','escalation') DEFAULT 'note',
  `status_before` VARCHAR(50) DEFAULT NULL,
  `status_after` VARCHAR(50) DEFAULT NULL,
  `update_text` TEXT DEFAULT NULL,
  `internal_only` TINYINT(1) DEFAULT 0,
  `added_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_complaint_id` (`complaint_id`),
  KEY `idx_added_by` (`added_by`),
  CONSTRAINT `fk_complaint_updates_complaints` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `complaint_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` INT(11) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` BIGINT DEFAULT NULL,
  `mime_type` VARCHAR(120) DEFAULT NULL,
  `uploaded_by` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attachment_complaint` (`complaint_id`),
  CONSTRAINT `fk_complaint_attachments_complaints` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `feature_toggles` (`feature_key`, `feature_name`, `description`, `is_enabled`, `is_core`, `category`, `icon`, `menu_position`)
VALUES ('complaints', 'Complaints & Feedback', 'Customer complaints and feedback management workflows', 1, 0, 'operations', '🛠️', 12);

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Complaints module migration executed' AS Status;

