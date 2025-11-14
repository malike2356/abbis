-- Material Store System
-- Separate inventory system for materials kept at the Material Store (for field work)
-- This is different from POS (Material Shop) and materials_inventory (Operations)

-- Material Store Inventory Table
CREATE TABLE IF NOT EXISTS `material_store_inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `material_type` VARCHAR(100) NOT NULL COMMENT 'screen_pipe, plain_pipe, gravel',
    `material_name` VARCHAR(255) NOT NULL,
    `quantity_received` DECIMAL(10,2) DEFAULT 0 COMMENT 'Total received from POS/Material Shop',
    `quantity_used` DECIMAL(10,2) DEFAULT 0 COMMENT 'Total used in field work',
    `quantity_remaining` DECIMAL(10,2) DEFAULT 0 COMMENT 'Available for field work',
    `quantity_returned` DECIMAL(10,2) DEFAULT 0 COMMENT 'Returned to POS/Material Shop',
    `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
    `total_value` DECIMAL(12,2) DEFAULT 0.00,
    `unit_of_measure` VARCHAR(20) DEFAULT 'pcs',
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `material_type` (`material_type`),
    INDEX `idx_material_type` (`material_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Material Store Transactions (for tracking movements)
CREATE TABLE IF NOT EXISTS `material_store_transactions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `transaction_type` ENUM('transfer_from_pos', 'usage_in_field', 'return_to_pos', 'adjustment') NOT NULL,
    `material_type` VARCHAR(100) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_cost` DECIMAL(10,2) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'field_report, pos_transfer, material_return',
    `reference_id` INT(11) DEFAULT NULL,
    `field_report_id` INT(11) DEFAULT NULL COMMENT 'Link to field_reports if from field work',
    `performed_by` INT(11) NOT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_material_type` (`material_type`),
    INDEX `idx_transaction_type` (`transaction_type`),
    INDEX `idx_field_report` (`field_report_id`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add material store fields to field_reports for tracking
-- Note: MySQL doesn't support IF NOT EXISTS in ALTER TABLE, so we check first
SET @dbname = DATABASE();
SET @tablename = 'field_reports';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'screen_pipes_remaining') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `screen_pipes_remaining` INT(11) DEFAULT NULL COMMENT ''Remaining after use''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'plain_pipes_remaining') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `plain_pipes_remaining` INT(11) DEFAULT NULL COMMENT ''Remaining after use''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'gravel_remaining') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `gravel_remaining` INT(11) DEFAULT NULL COMMENT ''Remaining after use''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'materials_value_used') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `materials_value_used` DECIMAL(12,2) DEFAULT 0.00 COMMENT ''Total value of materials used''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update materials_provided_by enum to clarify: material_shop = POS, store = Material Store
-- Note: This will be handled in application logic, enum stays the same for backward compatibility

