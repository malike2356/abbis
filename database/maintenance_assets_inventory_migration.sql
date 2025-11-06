-- Maintenance, Asset Management, and Enhanced Inventory Migration
-- ABBIS System of Systems

USE `abbis_3_2`;

-- Disable foreign key checks temporarily to avoid constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. FEATURE TOGGLE SYSTEM
-- ============================================
CREATE TABLE IF NOT EXISTS `feature_toggles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `feature_key` VARCHAR(50) NOT NULL UNIQUE COMMENT 'e.g., maintenance, assets, inventory_advanced',
  `feature_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `is_core` TINYINT(1) DEFAULT 0 COMMENT 'Core features cannot be disabled',
  `category` VARCHAR(50) DEFAULT 'general',
  `icon` VARCHAR(50) DEFAULT NULL,
  `menu_position` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_key` (`feature_key`),
  KEY `is_enabled` (`is_enabled`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default feature toggles (ignore duplicates)
INSERT IGNORE INTO `feature_toggles` (`feature_key`, `feature_name`, `description`, `is_enabled`, `is_core`, `category`, `icon`, `menu_position`) VALUES
('field_reports', 'Field Reports', 'Core field reporting system', 1, 1, 'core', '📊', 1),
('financial', 'Financial Management', 'Finance, Payroll, Loans', 1, 1, 'core', '💰', 2),
('clients_crm', 'Clients & CRM', 'Client and relationship management', 1, 1, 'core', '👥', 3),
('materials', 'Materials Management', 'Materials and inventory', 1, 1, 'core', '📦', 4),
('maintenance', 'Maintenance Management', 'Equipment maintenance tracking', 1, 0, 'operations', '🔧', 5),
('assets', 'Asset Management', 'Company assets and equipment tracking', 1, 0, 'operations', '🏭', 6),
('inventory_advanced', 'Advanced Inventory', 'Enhanced inventory management', 1, 0, 'operations', '📋', 7),
('loans', 'Loans Management', 'Loan tracking and management', 1, 0, 'financial', '💳', 8),
('analytics', 'Advanced Analytics', 'Business intelligence and analytics', 1, 0, 'business', '📈', 9);

-- ============================================
-- 2. ASSET MANAGEMENT SYSTEM
-- ============================================
CREATE TABLE IF NOT EXISTS `assets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique asset identifier',
  `asset_name` VARCHAR(255) NOT NULL,
  `asset_type` ENUM('rig', 'vehicle', 'equipment', 'tool', 'building', 'land', 'other') NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL COMMENT 'Drilling rig, Truck, Pump, etc.',
  `brand` VARCHAR(100) DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `manufacture_date` DATE DEFAULT NULL,
  `purchase_date` DATE DEFAULT NULL,
  `purchase_cost` DECIMAL(15,2) DEFAULT 0.00,
  `current_value` DECIMAL(15,2) DEFAULT 0.00,
  `depreciation_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Annual depreciation %',
  `location` VARCHAR(255) DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL COMMENT 'Worker/User ID',
  `status` ENUM('active', 'inactive', 'maintenance', 'retired', 'sold', 'lost') DEFAULT 'active',
  `condition` ENUM('excellent', 'good', 'fair', 'poor', 'critical') DEFAULT 'good',
  `warranty_expiry` DATE DEFAULT NULL,
  `insurance_policy` VARCHAR(100) DEFAULT NULL,
  `insurance_expiry` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `asset_type` (`asset_type`),
  KEY `status` (`status`),
  KEY `assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asset depreciation history
CREATE TABLE IF NOT EXISTS `asset_depreciation` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_id` INT(11) NOT NULL,
  `depreciation_date` DATE NOT NULL,
  `depreciation_amount` DECIMAL(15,2) NOT NULL,
  `book_value_before` DECIMAL(15,2) NOT NULL,
  `book_value_after` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `depreciation_date` (`depreciation_date`),
  FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. MAINTENANCE MANAGEMENT SYSTEM
-- ============================================
CREATE TABLE IF NOT EXISTS `maintenance_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `is_proactive` TINYINT(1) DEFAULT 1,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default maintenance types (ignore duplicates)
INSERT IGNORE INTO `maintenance_types` (`type_name`, `description`, `is_proactive`) VALUES
('Preventive Maintenance', 'Scheduled maintenance to prevent failures', 1),
('Predictive Maintenance', 'Maintenance based on condition monitoring', 1),
('Breakdown Repair', 'Repair after equipment failure', 0),
('Emergency Repair', 'Urgent repair to restore operation', 0),
('Inspection', 'Routine inspection and assessment', 1),
('Calibration', 'Calibration of equipment', 1),
('Cleaning', 'Deep cleaning and sanitation', 1),
('Lubrication', 'Lubrication service', 1),
('Parts Replacement', 'Replacement of worn parts', 0),
('Upgrade/Modification', 'Equipment upgrade or modification', 0);

-- Main maintenance records
CREATE TABLE IF NOT EXISTS `maintenance_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `maintenance_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique maintenance ID',
  `maintenance_type_id` INT(11) NOT NULL,
  `maintenance_category` ENUM('proactive', 'reactive') NOT NULL,
  `asset_id` INT(11) NOT NULL COMMENT 'Equipment/asset being maintained',
  `rig_id` INT(11) DEFAULT NULL COMMENT 'If maintenance is rig-specific',
  `scheduled_date` DATETIME DEFAULT NULL,
  `started_date` DATETIME DEFAULT NULL,
  `completed_date` DATETIME DEFAULT NULL,
  `due_date` DATE DEFAULT NULL COMMENT 'For proactive maintenance',
  `status` ENUM('logged', 'scheduled', 'in_progress', 'on_hold', 'completed', 'cancelled') DEFAULT 'logged',
  `priority` ENUM('low', 'medium', 'high', 'urgent', 'critical') DEFAULT 'medium',
  `performed_by` INT(11) DEFAULT NULL COMMENT 'User/Worker ID who did the work',
  `supervised_by` INT(11) DEFAULT NULL COMMENT 'Supervisor/Manager ID',
  `description` TEXT DEFAULT NULL COMMENT 'What maintenance was needed',
  `work_performed` TEXT DEFAULT NULL COMMENT 'What was actually done',
  `parts_required` TEXT DEFAULT NULL COMMENT 'Parts needed/used',
  `parts_cost` DECIMAL(15,2) DEFAULT 0.00,
  `labor_cost` DECIMAL(15,2) DEFAULT 0.00,
  `total_cost` DECIMAL(15,2) DEFAULT 0.00,
  `downtime_hours` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Equipment downtime',
  `effect` TEXT DEFAULT NULL COMMENT 'Effect/result of maintenance',
  `effectiveness_rating` INT(1) DEFAULT NULL COMMENT '1-5 rating',
  `notes` TEXT DEFAULT NULL,
  `attachments` TEXT DEFAULT NULL COMMENT 'JSON array of file paths',
  `next_maintenance_date` DATE DEFAULT NULL COMMENT 'When next maintenance is due',
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `maintenance_code` (`maintenance_code`),
  KEY `maintenance_type_id` (`maintenance_type_id`),
  KEY `asset_id` (`asset_id`),
  KEY `rig_id` (`rig_id`),
  KEY `status` (`status`),
  KEY `scheduled_date` (`scheduled_date`),
  KEY `performed_by` (`performed_by`),
  FOREIGN KEY (`maintenance_type_id`) REFERENCES `maintenance_types` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maintenance parts/inventory used
CREATE TABLE IF NOT EXISTS `maintenance_parts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `maintenance_id` INT(11) NOT NULL,
  `material_id` INT(11) DEFAULT NULL COMMENT 'Link to materials inventory if exists',
  `part_name` VARCHAR(255) NOT NULL,
  `part_number` VARCHAR(100) DEFAULT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `supplier` VARCHAR(255) DEFAULT NULL,
  `warranty_period` INT(11) DEFAULT NULL COMMENT 'Days',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `maintenance_id` (`maintenance_id`),
  KEY `material_id` (`material_id`),
  FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maintenance status history/audit trail
CREATE TABLE IF NOT EXISTS `maintenance_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `maintenance_id` INT(11) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `changed_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `maintenance_id` (`maintenance_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_records` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maintenance schedules (for proactive maintenance)
CREATE TABLE IF NOT EXISTS `maintenance_schedules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_id` INT(11) NOT NULL,
  `maintenance_type_id` INT(11) NOT NULL,
  `frequency_type` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'hours', 'kilometers', 'custom') NOT NULL,
  `frequency_value` INT(11) NOT NULL DEFAULT 1 COMMENT 'Number of frequency_type units',
  `last_maintenance_date` DATE DEFAULT NULL,
  `next_maintenance_date` DATE NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `next_maintenance_date` (`next_maintenance_date`),
  KEY `is_active` (`is_active`),
  FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`maintenance_type_id`) REFERENCES `maintenance_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. ENHANCED INVENTORY MANAGEMENT
-- ============================================
-- Create materials table if it doesn't exist (for advanced inventory features)
-- This table is used by inventory_transactions and inventory_stock_snapshots
CREATE TABLE IF NOT EXISTS `materials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_name` VARCHAR(255) NOT NULL,
  `material_type` VARCHAR(100) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `unit_of_measure` VARCHAR(20) DEFAULT 'pcs',
  `reorder_level` DECIMAL(10,2) DEFAULT 0.00,
  `reorder_quantity` DECIMAL(10,2) DEFAULT 0.00,
  `supplier` VARCHAR(255) DEFAULT NULL,
  `supplier_contact` VARCHAR(100) DEFAULT NULL,
  `last_purchased_date` DATE DEFAULT NULL,
  `last_purchased_price` DECIMAL(15,2) DEFAULT NULL,
  `average_cost` DECIMAL(15,2) DEFAULT 0.00,
  `location` VARCHAR(255) DEFAULT NULL COMMENT 'Storage location',
  `barcode` VARCHAR(100) DEFAULT NULL,
  `is_trackable` TINYINT(1) DEFAULT 1 COMMENT 'Track inventory levels',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_type` (`material_type`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory movements/transactions
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transaction_code` VARCHAR(50) NOT NULL UNIQUE,
  `material_id` INT(11) NOT NULL,
  `transaction_type` ENUM('purchase', 'sale', 'usage', 'adjustment', 'transfer', 'return', 'damage', 'expiry') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL COMMENT 'Positive for in, negative for out',
  `unit_cost` DECIMAL(15,2) DEFAULT 0.00,
  `total_cost` DECIMAL(15,2) DEFAULT 0.00,
  `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'field_report, maintenance, sale, etc.',
  `reference_id` INT(11) DEFAULT NULL COMMENT 'ID of related record',
  `location_from` VARCHAR(255) DEFAULT NULL,
  `location_to` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_code` (`transaction_code`),
  KEY `material_id` (`material_id`),
  KEY `transaction_type` (`transaction_type`),
  KEY `created_at` (`created_at`),
  KEY `created_by` (`created_by`)
  -- Note: Foreign keys removed to avoid dependency issues - they can be added later if needed
  -- FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT,
  -- FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory stock levels (snapshot for reporting)
CREATE TABLE IF NOT EXISTS `inventory_stock_snapshots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `value` DECIMAL(15,2) NOT NULL,
  `snapshot_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  KEY `snapshot_date` (`snapshot_date`)
  -- Note: Foreign key removed to avoid dependency issues - can be added later if needed
  -- FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. LINK MAINTENANCE TO EXPENSES
-- ============================================
-- Maintenance expenses are tracked in maintenance_records
-- Link maintenance to financial records for reporting
-- Note: These ALTER statements will fail gracefully if columns/indexes already exist
-- The migration runner catches these errors and continues

-- Add expense columns (errors ignored if columns already exist)
ALTER TABLE `maintenance_records`
ADD COLUMN `expense_recorded` TINYINT(1) DEFAULT 0 COMMENT 'Whether cost was recorded in financial system';

ALTER TABLE `maintenance_records`
ADD COLUMN `financial_entry_id` INT(11) DEFAULT NULL COMMENT 'Link to financial entries if exists';

-- Create indexes for financial linking (errors ignored if indexes already exist)
-- Note: These will fail if columns don't exist (from previous failed ALTER), but errors are caught
ALTER TABLE `maintenance_records`
ADD INDEX `idx_expense_recorded` (`expense_recorded`);

ALTER TABLE `maintenance_records`
ADD INDEX `idx_financial_entry_id` (`financial_entry_id`);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

