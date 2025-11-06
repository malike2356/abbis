-- CRM System Migration
-- Comprehensive CRM functionality for client management

USE `abbis_3_2`;

-- Extend clients table with CRM fields
ALTER TABLE `clients` 
ADD COLUMN `company_type` VARCHAR(50) DEFAULT NULL AFTER `client_name`,
ADD COLUMN `website` VARCHAR(255) DEFAULT NULL AFTER `email`,
ADD COLUMN `tax_id` VARCHAR(50) DEFAULT NULL AFTER `website`,
ADD COLUMN `industry` VARCHAR(100) DEFAULT NULL AFTER `tax_id`,
ADD COLUMN `status` ENUM('active', 'inactive', 'lead', 'prospect', 'customer') DEFAULT 'lead' AFTER `industry`,
ADD COLUMN `source` VARCHAR(100) DEFAULT NULL COMMENT 'How client was acquired' AFTER `status`,
ADD COLUMN `rating` INT(1) DEFAULT 0 COMMENT '1-5 star rating' AFTER `source`,
ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `rating`,
ADD COLUMN `assigned_to` INT(11) DEFAULT NULL COMMENT 'User ID assigned to manage this client' AFTER `notes`,
ADD COLUMN `last_contact_date` DATE DEFAULT NULL AFTER `assigned_to`,
ADD COLUMN `next_followup_date` DATE DEFAULT NULL AFTER `last_contact_date`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Client contacts (multiple contacts per client)
CREATE TABLE IF NOT EXISTS `client_contacts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  `department` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Follow-ups and tasks
CREATE TABLE IF NOT EXISTS `client_followups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `contact_id` INT(11) DEFAULT NULL COMMENT 'Specific contact if applicable',
  `type` ENUM('call', 'email', 'meeting', 'visit', 'quote', 'proposal', 'other') DEFAULT 'call',
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `scheduled_date` DATETIME NOT NULL,
  `completed_date` DATETIME DEFAULT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `reminder_sent` TINYINT(1) DEFAULT 0,
  `outcome` TEXT DEFAULT NULL COMMENT 'Result of follow-up',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `contact_id` (`contact_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `scheduled_date` (`scheduled_date`),
  KEY `status` (`status`),
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email communications
CREATE TABLE IF NOT EXISTS `client_emails` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `contact_id` INT(11) DEFAULT NULL,
  `direction` ENUM('inbound', 'outbound') NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `from_email` VARCHAR(255) NOT NULL,
  `to_email` VARCHAR(255) NOT NULL,
  `cc_emails` TEXT DEFAULT NULL COMMENT 'Comma-separated',
  `bcc_emails` TEXT DEFAULT NULL COMMENT 'Comma-separated',
  `attachments` TEXT DEFAULT NULL COMMENT 'JSON array of file paths',
  `status` ENUM('draft', 'sent', 'delivered', 'failed', 'opened', 'replied') DEFAULT 'draft',
  `sent_at` DATETIME DEFAULT NULL,
  `opened_at` DATETIME DEFAULT NULL,
  `replied_at` DATETIME DEFAULT NULL,
  `related_followup_id` INT(11) DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `contact_id` (`contact_id`),
  KEY `direction` (`direction`),
  KEY `status` (`status`),
  KEY `sent_at` (`sent_at`),
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `client_contacts` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`related_followup_id`) REFERENCES `client_followups` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client notes and activities (general activity log)
CREATE TABLE IF NOT EXISTS `client_activities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `type` ENUM('note', 'call', 'email', 'meeting', 'document', 'status_change', 'update', 'system') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `related_id` INT(11) DEFAULT NULL COMMENT 'ID of related record (followup, email, etc.)',
  `related_type` VARCHAR(50) DEFAULT NULL COMMENT 'Type of related record',
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `type` (`type`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email templates for CRM
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `category` VARCHAR(50) DEFAULT 'general',
  `variables` TEXT DEFAULT NULL COMMENT 'JSON array of available variables',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default email templates
INSERT INTO `email_templates` (`name`, `subject`, `body`, `category`, `variables`) VALUES
('Welcome New Client', 'Welcome to ABBIS - {{company_name}}', 'Dear {{contact_name}},\n\nWelcome to ABBIS! We are excited to work with {{client_name}}.\n\nBest regards,\n{{sender_name}}', 'welcome', '["company_name","contact_name","client_name","sender_name"]'),
('Follow-up After Job', 'Follow-up: {{job_title}}', 'Dear {{contact_name}},\n\nWe wanted to follow up on the recent job at {{site_name}}.\n\nPlease let us know if you have any questions.\n\nBest regards,\n{{sender_name}}', 'followup', '["contact_name","job_title","site_name","sender_name"]'),
('Quote Request', 'Quote Request for {{service_type}}', 'Dear {{contact_name}},\n\nThank you for your interest in our {{service_type}} services.\n\nWe will prepare a detailed quote for you.\n\nBest regards,\n{{sender_name}}', 'quote', '["contact_name","service_type","sender_name"]');

-- User consent tracking (for compliance)
CREATE TABLE IF NOT EXISTS `user_consents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'NULL for anonymous users',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'For non-registered users',
  `consent_type` VARCHAR(50) NOT NULL COMMENT 'privacy_policy, terms, marketing, cookies',
  `version` VARCHAR(20) DEFAULT NULL COMMENT 'Version of policy/terms',
  `consented` TINYINT(1) DEFAULT 1,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`),
  KEY `consent_type` (`consent_type`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

