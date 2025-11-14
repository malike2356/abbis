SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `abbis_3_2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `abbis_3_2`;

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','supervisor','clerk','accountant','hr','field_manager') DEFAULT 'clerk',
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rigs configuration
CREATE TABLE `rigs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rig_name` varchar(100) NOT NULL,
  `rig_code` varchar(20) NOT NULL,
  `truck_model` varchar(100) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rig_code` (`rig_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Workers table
CREATE TABLE `workers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `default_rate` decimal(10,2) DEFAULT '0.00',
  `contact_number` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clients table
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Field Reports main table
CREATE TABLE `field_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` varchar(50) NOT NULL,
  `report_date` date NOT NULL,
  `rig_id` int(11) NOT NULL,
  `job_type` enum('direct','subcontract') NOT NULL,
  `site_name` varchar(200) NOT NULL,
  `plus_code` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `location_description` text,
  `region` varchar(100) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_contact` varchar(100) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `finish_time` time DEFAULT NULL,
  `total_duration` int(11) DEFAULT NULL,
  `start_rpm` decimal(8,2) DEFAULT NULL,
  `finish_rpm` decimal(8,2) DEFAULT NULL,
  `total_rpm` decimal(8,2) DEFAULT NULL,
  `rod_length` decimal(5,2) DEFAULT NULL,
  `rods_used` int(11) DEFAULT NULL,
  `total_depth` decimal(8,2) DEFAULT NULL,
  `screen_pipes_used` int(11) DEFAULT '0',
  `plain_pipes_used` int(11) DEFAULT '0',
  `gravel_used` int(11) DEFAULT '0',
  `construction_depth` decimal(8,2) DEFAULT NULL,
  `materials_provided_by` enum('client','company','material_shop','store') DEFAULT 'client',
  `supervisor` varchar(100) DEFAULT NULL,
  `total_workers` int(11) DEFAULT '0',
  `remarks` text,
  `incident_log` text,
  `solution_log` text,
  `recommendation_log` text,
  `balance_bf` decimal(12,2) DEFAULT '0.00',
  `contract_sum` decimal(12,2) DEFAULT '0.00',
  `rig_fee_charged` decimal(12,2) DEFAULT '0.00',
  `rig_fee_collected` decimal(12,2) DEFAULT '0.00',
  `cash_received` decimal(12,2) DEFAULT '0.00',
  `materials_income` decimal(12,2) DEFAULT '0.00',
  `materials_cost` decimal(12,2) DEFAULT '0.00',
  `momo_transfer` decimal(12,2) DEFAULT '0.00',
  `cash_given` decimal(12,2) DEFAULT '0.00',
  `bank_deposit` decimal(12,2) DEFAULT '0.00',
  `total_income` decimal(12,2) DEFAULT '0.00',
  `total_expenses` decimal(12,2) DEFAULT '0.00',
  `total_wages` decimal(12,2) DEFAULT '0.00',
  `net_profit` decimal(12,2) DEFAULT '0.00',
  `total_money_banked` decimal(12,2) DEFAULT '0.00',
  `days_balance` decimal(12,2) DEFAULT '0.00',
  `outstanding_rig_fee` decimal(12,2) DEFAULT '0.00',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_id` (`report_id`),
  KEY `rig_id` (`rig_id`),
  KEY `client_id` (`client_id`),
  KEY `report_date` (`report_date`),
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payroll entries
CREATE TABLE `payroll_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `worker_name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `wage_type` enum('per_borehole','daily','hourly','custom') NOT NULL,
  `units` decimal(8,2) DEFAULT '1.00',
  `pay_per_unit` decimal(10,2) DEFAULT '0.00',
  `benefits` decimal(10,2) DEFAULT '0.00',
  `loan_reclaim` decimal(10,2) DEFAULT '0.00',
  `amount` decimal(10,2) DEFAULT '0.00',
  `paid_today` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Expense entries
CREATE TABLE `expense_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `description` varchar(200) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `quantity` decimal(8,2) DEFAULT '1.00',
  `amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Loans management
CREATE TABLE `loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_name` varchar(100) NOT NULL,
  `loan_amount` decimal(12,2) NOT NULL,
  `amount_repaid` decimal(12,2) DEFAULT '0.00',
  `outstanding_balance` decimal(12,2) NOT NULL,
  `purpose` text,
  `issue_date` date NOT NULL,
  `status` enum('active','repaid','written_off') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Loan repayments
CREATE TABLE `loan_repayments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `repayment_amount` decimal(10,2) NOT NULL,
  `repayment_date` date NOT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Materials inventory
CREATE TABLE `materials_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_type` enum('screen_pipe','plain_pipe','gravel') NOT NULL,
  `quantity_received` int(11) DEFAULT '0',
  `quantity_used` int(11) DEFAULT '0',
  `quantity_remaining` int(11) DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `total_value` decimal(12,2) DEFAULT '0.00',
  `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_type` (`material_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System configuration
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `config_type` varchar(50) DEFAULT 'string',
  `description` text,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default data
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `full_name`) VALUES
(1, 'admin', 'admin@abbis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator');

INSERT INTO `rigs` (`id`, `rig_name`, `rig_code`, `truck_model`, `registration_number`, `status`) VALUES
(1, 'Main Rig', 'RIG-01', 'Volvo FH16', 'GR-1234-A', 'active'),
(2, 'Backup Rig', 'RIG-02', 'Mercedes Actros', 'GR-5678-B', 'active');

INSERT INTO `workers` (`worker_name`, `role`, `default_rate`, `contact_number`) VALUES
('John Doe', 'Manager', '200.00', '+233 555 0101'),
('Jane Smith', 'Supervisor', '150.00', '+233 555 0102'),
('Mike Johnson', 'Driller (Operator)', '120.00', '+233 555 0103'),
('Samuel Owusu', 'Rig Driver', '100.00', '+233 555 0104'),
('Kwame Mensah', 'Rod Boy (General Labourer)', '80.00', '+233 555 0105');

INSERT INTO `clients` (`client_name`, `contact_person`, `contact_number`, `email`) VALUES
('AquaTech Ltd', 'Mr. James Brown', '+233 555 0201', 'james@aquatech.com'),
('GAMA Water Project', 'Ms. Grace Amoah', '+233 555 0202', 'grace@gamawater.org'),
('Rural Water Initiative', 'Mr. Daniel Asare', '+233 555 0203', 'daniel@ruralwater.org');

INSERT INTO `materials_inventory` (`material_type`, `quantity_received`, `quantity_used`, `quantity_remaining`, `unit_cost`, `total_value`) VALUES
('screen_pipe', 100, 0, 100, '150.00', '15000.00'),
('plain_pipe', 80, 0, 80, '120.00', '9600.00'),
('gravel', 200, 0, 200, '80.00', '16000.00');

INSERT INTO `system_config` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('company_name', 'ABBIS - Advanced Borehole Business Intelligence System', 'string', 'System name'),
('company_tagline', 'Advanced Borehole Business Intelligence System', 'string', 'System tagline'),
('company_address', '123 Engineering Lane, Accra, Ghana', 'string', 'Company address'),
('company_contact', '+233 555 123 456', 'string', 'Contact number'),
('company_email', 'info@abbis.africa', 'string', 'Email address'),
('default_rod_lengths', '5.0,4.5,4.0,3.5,3.0', 'string', 'Available rod lengths in meters'),
('google_sheets_url', '', 'string', 'Google Apps Script URL for sync'),
('backup_interval', '24', 'number', 'Auto backup interval in hours');

-- Login attempts tracking for security
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `attempt_time` timestamp DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username_time` (`username`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cache stats for performance optimization
CREATE TABLE IF NOT EXISTS `cache_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL,
  `cache_value` longtext,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;