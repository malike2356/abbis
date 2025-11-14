-- Requests System Migration
-- Handles both Quotation Requests and Rig Requests

USE `abbis_3_2`;

-- Enhance cms_quote_requests table to include detailed service options
ALTER TABLE `cms_quote_requests`
ADD COLUMN `include_drilling` TINYINT(1) DEFAULT 0 AFTER `service_type`,
ADD COLUMN `include_construction` TINYINT(1) DEFAULT 0 AFTER `include_drilling`,
ADD COLUMN `include_mechanization` TINYINT(1) DEFAULT 0 AFTER `include_construction`,
ADD COLUMN `include_yield_test` TINYINT(1) DEFAULT 0 AFTER `include_mechanization`,
ADD COLUMN `include_chemical_test` TINYINT(1) DEFAULT 0 AFTER `include_yield_test`,
ADD COLUMN `include_polytank_stand` TINYINT(1) DEFAULT 0 AFTER `include_chemical_test`,
ADD COLUMN `pump_preferences` TEXT DEFAULT NULL COMMENT 'JSON array of preferred pump IDs from catalog' AFTER `include_polytank_stand`,
ADD COLUMN `latitude` DECIMAL(10,6) DEFAULT NULL AFTER `location`,
ADD COLUMN `longitude` DECIMAL(10,6) DEFAULT NULL AFTER `latitude`,
ADD COLUMN `address` TEXT DEFAULT NULL AFTER `longitude`,
ADD COLUMN `estimated_budget` DECIMAL(12,2) DEFAULT NULL AFTER `description`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Create rig_requests table
CREATE TABLE IF NOT EXISTS `rig_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_number` VARCHAR(50) NOT NULL COMMENT 'Auto-generated request number',
  `requester_name` VARCHAR(150) NOT NULL,
  `requester_email` VARCHAR(255) NOT NULL,
  `requester_phone` VARCHAR(50) DEFAULT NULL,
  `requester_type` ENUM('agent','contractor','client') DEFAULT 'contractor',
  `company_name` VARCHAR(255) DEFAULT NULL,
  `location_address` TEXT NOT NULL,
  `latitude` DECIMAL(10,6) DEFAULT NULL,
  `longitude` DECIMAL(10,6) DEFAULT NULL,
  `region` VARCHAR(100) DEFAULT NULL,
  `number_of_boreholes` INT(11) DEFAULT 1,
  `estimated_budget` DECIMAL(12,2) DEFAULT NULL,
  `preferred_start_date` DATE DEFAULT NULL,
  `urgency` ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `status` ENUM('new','under_review','negotiating','dispatched','declined','completed','cancelled') DEFAULT 'new',
  `assigned_rig_id` INT(11) DEFAULT NULL COMMENT 'Rig assigned to this request',
  `client_id` INT(11) DEFAULT NULL COMMENT 'Linked client if requester is existing client',
  `field_report_id` INT(11) DEFAULT NULL COMMENT 'Linked field report when job is completed',
  `notes` TEXT DEFAULT NULL,
  `internal_notes` TEXT DEFAULT NULL COMMENT 'Internal notes not visible to requester',
  `assigned_to` INT(11) DEFAULT NULL COMMENT 'User assigned to handle this request',
  `dispatched_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `idx_status` (`status`),
  KEY `idx_requester_email` (`requester_email`),
  KEY `idx_created` (`created_at`),
  KEY `idx_assigned_rig` (`assigned_rig_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_field_report` (`field_report_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  FOREIGN KEY (`assigned_rig_id`) REFERENCES `rigs` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`field_report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create rig_request_followups table to track follow-ups for rig requests
CREATE TABLE IF NOT EXISTS `rig_request_followups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `rig_request_id` INT(11) NOT NULL,
  `type` ENUM('call','email','meeting','visit','negotiation','other') DEFAULT 'call',
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `scheduled_date` DATETIME NOT NULL,
  `completed_date` DATETIME DEFAULT NULL,
  `status` ENUM('scheduled','completed','cancelled','postponed') DEFAULT 'scheduled',
  `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `outcome` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rig_request` (`rig_request_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_date` (`scheduled_date`),
  KEY `idx_assigned_to` (`assigned_to`),
  FOREIGN KEY (`rig_request_id`) REFERENCES `rig_requests` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create trigger to auto-generate request numbers
DELIMITER //
CREATE TRIGGER `generate_rig_request_number` 
BEFORE INSERT ON `rig_requests`
FOR EACH ROW
BEGIN
    IF NEW.request_number IS NULL OR NEW.request_number = '' THEN
        SET NEW.request_number = CONCAT('RR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD((SELECT COALESCE(MAX(SUBSTRING(request_number, -4)), 0) + 1 FROM (SELECT request_number FROM rig_requests WHERE DATE(created_at) = CURDATE()) AS temp), 4, '0'));
    END IF;
END//
DELIMITER ;

