-- Maintenance RPM-Based Enhancement Migration
-- Implements RPM-based proactive maintenance scheduling for drilling rigs

USE `abbis_3_2`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. ENHANCE RIGS TABLE FOR RPM TRACKING
-- ============================================
ALTER TABLE `rigs`
ADD COLUMN IF NOT EXISTS `current_rpm` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Current total RPM from compressor engine',
ADD COLUMN IF NOT EXISTS `last_maintenance_rpm` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'RPM reading at last maintenance',
ADD COLUMN IF NOT EXISTS `maintenance_due_at_rpm` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM threshold when maintenance is due',
ADD COLUMN IF NOT EXISTS `maintenance_rpm_interval` DECIMAL(10,2) DEFAULT 30.00 COMMENT 'RPM interval between maintenance (e.g., 30.00 means service every 30 RPM)';

-- Add index for RPM-based queries
ALTER TABLE `rigs`
ADD INDEX IF NOT EXISTS `idx_current_rpm` (`current_rpm`),
ADD INDEX IF NOT EXISTS `idx_maintenance_due` (`maintenance_due_at_rpm`);

-- ============================================
-- 2. ENHANCE MAINTENANCE_RECORDS FOR RPM TRACKING
-- ============================================
ALTER TABLE `maintenance_records`
ADD COLUMN IF NOT EXISTS `rpm_at_maintenance` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM reading when maintenance was performed',
ADD COLUMN IF NOT EXISTS `rpm_threshold` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM threshold that triggered this maintenance (for proactive)',
ADD COLUMN IF NOT EXISTS `rpm_interval_used` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM interval between last and current maintenance',
ADD COLUMN IF NOT EXISTS `next_maintenance_rpm` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM when next maintenance is due';

-- Add index for RPM-based queries
ALTER TABLE `maintenance_records`
ADD INDEX IF NOT EXISTS `idx_rpm_at_maintenance` (`rpm_at_maintenance`),
ADD INDEX IF NOT EXISTS `idx_next_maintenance_rpm` (`next_maintenance_rpm`);

-- ============================================
-- 3. ENHANCE MAINTENANCE_SCHEDULES FOR RPM-BASED SCHEDULING
-- ============================================
ALTER TABLE `maintenance_schedules`
ADD COLUMN IF NOT EXISTS `frequency_type` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'hours', 'kilometers', 'rpm', 'custom') NOT NULL DEFAULT 'rpm' COMMENT 'Schedule frequency type - RPM for rigs',
ADD COLUMN IF NOT EXISTS `frequency_value` DECIMAL(10,2) DEFAULT 30.00 COMMENT 'Frequency value (e.g., 30.00 RPM)',
ADD COLUMN IF NOT EXISTS `last_maintenance_rpm` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM reading at last maintenance',
ADD COLUMN IF NOT EXISTS `next_maintenance_rpm` DECIMAL(10,2) DEFAULT NULL COMMENT 'RPM threshold for next maintenance',
ADD COLUMN IF NOT EXISTS `current_rpm` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Current RPM for tracking';

-- Update existing records to use RPM if they're for rigs
UPDATE `maintenance_schedules` ms
INNER JOIN `assets` a ON ms.asset_id = a.id
SET ms.frequency_type = 'rpm'
WHERE a.asset_type = 'rig' AND ms.frequency_type NOT IN ('rpm');

-- ============================================
-- 4. CREATE MAINTENANCE EXPENSES TABLE (SEPARATE TRACKING)
-- ============================================
CREATE TABLE IF NOT EXISTS `maintenance_expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `maintenance_id` INT(11) NOT NULL,
  `expense_type` ENUM('parts', 'labor', 'transport', 'miscellaneous', 'material') NOT NULL DEFAULT 'parts',
  `description` VARCHAR(255) NOT NULL,
  `material_id` INT(11) DEFAULT NULL COMMENT 'Link to materials_inventory if applicable',
  `quantity` DECIMAL(10,2) DEFAULT 1.00,
  `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `supplier` VARCHAR(255) DEFAULT NULL,
  `invoice_number` VARCHAR(100) DEFAULT NULL,
  `purchase_date` DATE DEFAULT NULL,
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `recorded_by` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `maintenance_id` (`maintenance_id`),
  KEY `material_id` (`material_id`),
  KEY `expense_type` (`expense_type`),
  KEY `recorded_at` (`recorded_at`),
  FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_records` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. CREATE MAINTENANCE COMPONENTS TABLE (TRACK WHAT'S SERVICED)
-- ============================================
CREATE TABLE IF NOT EXISTS `maintenance_components` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `maintenance_id` INT(11) NOT NULL,
  `component_name` VARCHAR(255) NOT NULL COMMENT 'e.g., Oil Filter, Air Filter, Compressor Oil, Hydraulic Oil, Hose',
  `component_type` ENUM('filter', 'oil', 'hose', 'hydraulic', 'electrical', 'mechanical', 'other') NOT NULL DEFAULT 'other',
  `action_taken` ENUM('replaced', 'serviced', 'cleaned', 'checked', 'adjusted', 'repaired') NOT NULL DEFAULT 'serviced',
  `condition_before` ENUM('excellent', 'good', 'fair', 'poor', 'critical') DEFAULT NULL,
  `condition_after` ENUM('excellent', 'good', 'fair', 'poor', 'critical') DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maintenance_id` (`maintenance_id`),
  KEY `component_type` (`component_type`),
  FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. CREATE RPM TRACKING HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `rig_rpm_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `rig_id` INT(11) NOT NULL,
  `report_id` INT(11) DEFAULT NULL COMMENT 'Field report that generated this RPM',
  `rpm_value` DECIMAL(10,2) NOT NULL,
  `rpm_type` ENUM('start', 'finish', 'total', 'maintenance') DEFAULT 'total',
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `recorded_by` INT(11) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rig_id` (`rig_id`),
  KEY `report_id` (`report_id`),
  KEY `recorded_at` (`recorded_at`),
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. UPDATE MAINTENANCE_TYPES FOR RIG-SPECIFIC TYPES
-- ============================================
INSERT IGNORE INTO `maintenance_types` (`type_name`, `description`, `is_proactive`) VALUES
('Oil Filter Replacement', 'Replacement of compressor engine oil filter', 1),
('Air Filter Replacement', 'Replacement of air filter', 1),
('Compressor Oil Change', 'Changing compressor engine oil', 1),
('Hydraulic Oil Change', 'Changing hydraulic system oil', 1),
('Hydraulic System Service', 'Full hydraulic system service', 1),
('Hose Replacement', 'Replacing worn or damaged hoses', 0),
('Compressor Service', 'Full compressor engine service', 1),
('Electrical System Check', 'Electrical system inspection and repair', 0),
('Brake System Service', 'Brake system inspection and service', 1),
('Cooling System Service', 'Cooling system maintenance', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 8. INITIALIZE EXISTING RIGS WITH DEFAULT RPM INTERVALS
-- ============================================
UPDATE `rigs`
SET 
  `maintenance_rpm_interval` = 30.00,
  `maintenance_due_at_rpm` = COALESCE(`current_rpm`, 0.00) + 30.00
WHERE `maintenance_due_at_rpm` IS NULL;
