-- Client Portal Migration
-- Adds support for client users and portal access

USE `abbis_3_2`;

-- Add 'client' role to users table
ALTER TABLE `users`
MODIFY COLUMN `role` enum('admin','manager','supervisor','clerk','accountant','hr','field_manager','client') DEFAULT 'clerk';

-- Add client_id to users table to link portal users to client records
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `client_id` INT(11) DEFAULT NULL AFTER `role`,
ADD INDEX `idx_client_id` (`client_id`),
ADD CONSTRAINT `fk_user_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

-- Client portal settings
CREATE TABLE IF NOT EXISTS `client_portal_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `config_key` VARCHAR(100) NOT NULL,
  `config_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client quotes table (formal quotes sent to clients)
CREATE TABLE IF NOT EXISTS `client_quotes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `quote_number` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `quote_request_id` INT(11) DEFAULT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` DECIMAL(12,2) DEFAULT '0.00',
  `status` ENUM('draft','sent','viewed','accepted','rejected','expired') DEFAULT 'draft',
  `valid_until` DATE DEFAULT NULL,
  `notes` TEXT,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_number` (`quote_number`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_quote_request` (`quote_request_id`),
  CONSTRAINT `fk_quote_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_quote_request` FOREIGN KEY (`quote_request_id`) REFERENCES `cms_quote_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quote_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quote line items
CREATE TABLE IF NOT EXISTS `quote_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `quote_id` INT(11) NOT NULL,
  `item_description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT '1.00',
  `unit_price` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  `item_type` ENUM('service','material','labor','other') DEFAULT 'service',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quote` (`quote_id`),
  CONSTRAINT `fk_quote_item` FOREIGN KEY (`quote_id`) REFERENCES `client_quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client invoices table
CREATE TABLE IF NOT EXISTS `client_invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `quote_id` INT(11) DEFAULT NULL,
  `field_report_id` INT(11) DEFAULT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` DECIMAL(12,2) DEFAULT '0.00',
  `amount_paid` DECIMAL(12,2) DEFAULT '0.00',
  `balance_due` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `status` ENUM('draft','sent','viewed','partial','paid','overdue','cancelled') DEFAULT 'draft',
  `issue_date` DATE NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `paid_date` DATE DEFAULT NULL,
  `notes` TEXT,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_quote` (`quote_id`),
  KEY `idx_field_report` (`field_report_id`),
  CONSTRAINT `fk_invoice_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_invoice_quote` FOREIGN KEY (`quote_id`) REFERENCES `client_quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_report` FOREIGN KEY (`field_report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice line items
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NOT NULL,
  `item_description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT '1.00',
  `unit_price` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  `item_type` ENUM('service','material','labor','other') DEFAULT 'service',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  CONSTRAINT `fk_invoice_item` FOREIGN KEY (`invoice_id`) REFERENCES `client_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client payments table
CREATE TABLE IF NOT EXISTS `client_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `payment_reference` VARCHAR(100) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `invoice_id` INT(11) DEFAULT NULL,
  `quote_id` INT(11) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method_id` INT(11) DEFAULT NULL,
  `payment_method` ENUM('cash','bank_transfer','mobile_money','card_online','cheque','other') NOT NULL,
  `payment_status` ENUM('pending','processing','completed','failed','refunded') DEFAULT 'pending',
  `gateway_provider` VARCHAR(50) DEFAULT NULL,
  `gateway_transaction_id` VARCHAR(255) DEFAULT NULL,
  `gateway_response` TEXT,
  `payment_date` DATETIME DEFAULT NULL,
  `notes` TEXT,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_reference` (`payment_reference`),
  KEY `idx_client` (`client_id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_gateway_txn` (`gateway_transaction_id`),
  KEY `idx_payment_method` (`payment_method_id`),
  KEY `idx_gateway_provider` (`gateway_provider`),
  CONSTRAINT `fk_payment_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `client_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_quote` FOREIGN KEY (`quote_id`) REFERENCES `client_quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `cms_payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure new columns exist for existing installations
ALTER TABLE `client_payments`
ADD COLUMN IF NOT EXISTS `payment_method_id` INT(11) DEFAULT NULL AFTER `amount`,
ADD COLUMN IF NOT EXISTS `gateway_provider` VARCHAR(50) DEFAULT NULL AFTER `payment_status`,
ADD INDEX IF NOT EXISTS `idx_payment_method` (`payment_method_id`),
ADD INDEX IF NOT EXISTS `idx_gateway_provider` (`gateway_provider`);

-- Quote approvals metadata
ALTER TABLE `client_quotes`
ADD COLUMN IF NOT EXISTS `client_response` ENUM('pending','accepted','declined') DEFAULT 'pending' AFTER `status`,
ADD COLUMN IF NOT EXISTS `client_response_note` TEXT AFTER `client_response`,
ADD COLUMN IF NOT EXISTS `client_response_at` DATETIME DEFAULT NULL AFTER `client_response_note`,
ADD COLUMN IF NOT EXISTS `client_response_ip` VARCHAR(45) DEFAULT NULL AFTER `client_response_at`,
ADD COLUMN IF NOT EXISTS `client_signature_name` VARCHAR(150) DEFAULT NULL AFTER `client_response_ip`,
ADD COLUMN IF NOT EXISTS `client_portal_viewed_at` DATETIME DEFAULT NULL AFTER `client_signature_name`,
ADD COLUMN IF NOT EXISTS `client_portal_downloaded_at` DATETIME DEFAULT NULL AFTER `client_portal_viewed_at`;

-- Invoice portal metadata
ALTER TABLE `client_invoices`
ADD COLUMN IF NOT EXISTS `client_portal_viewed_at` DATETIME DEFAULT NULL AFTER `paid_date`,
ADD COLUMN IF NOT EXISTS `client_portal_downloaded_at` DATETIME DEFAULT NULL AFTER `client_portal_viewed_at`;

-- Client portal activity log
CREATE TABLE IF NOT EXISTS `client_portal_activities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `activity_type` VARCHAR(50) NOT NULL,
  `activity_description` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_type` (`activity_type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_activity_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default portal config
INSERT IGNORE INTO `client_portal_config` (`config_key`, `config_value`) VALUES
('portal_enabled', '1'),
('require_email_verification', '0'),
('allow_self_registration', '0'),
('payment_gateway', ''),
('payment_gateway_key', ''),
('payment_gateway_secret', '');

