-- Add manager approval tracking to pos_sales
ALTER TABLE `pos_sales`
ADD COLUMN `requires_approval` TINYINT(1) DEFAULT 0 AFTER `notes`,
ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `requires_approval`,
ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `approved_by`,
ADD COLUMN `approval_reason` TEXT DEFAULT NULL AFTER `approved_at`,
ADD COLUMN `discount_requires_approval` TINYINT(1) DEFAULT 0 AFTER `approval_reason`,
ADD COLUMN `discount_approved_by` INT DEFAULT NULL AFTER `discount_requires_approval`,
ADD COLUMN `price_override_requires_approval` TINYINT(1) DEFAULT 0 AFTER `discount_approved_by`,
ADD COLUMN `price_override_approved_by` INT DEFAULT NULL AFTER `price_override_requires_approval`;

-- Add foreign keys for approval tracking
SET @dbname = DATABASE();
SET @tablename = 'pos_sales';
SET @constraintname1 = 'pos_sales_approved_by_fk';
SET @constraintname2 = 'pos_sales_discount_approved_by_fk';
SET @constraintname3 = 'pos_sales_price_override_approved_by_fk';

-- approved_by foreign key
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename) > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'approved_by') > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname1) = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD CONSTRAINT `', @constraintname1, '` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- discount_approved_by foreign key
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename) > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'discount_approved_by') > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname2) = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD CONSTRAINT `', @constraintname2, '` FOREIGN KEY (`discount_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- price_override_approved_by foreign key
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename) > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'price_override_approved_by') > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname3) = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD CONSTRAINT `', @constraintname3, '` FOREIGN KEY (`price_override_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add tax exemption fields to clients table
ALTER TABLE `clients`
ADD COLUMN `tax_exempt` TINYINT(1) DEFAULT 0 AFTER `notes`,
ADD COLUMN `tax_exemption_certificate` VARCHAR(255) DEFAULT NULL AFTER `tax_exempt`,
ADD COLUMN `tax_exemption_expiry` DATE DEFAULT NULL AFTER `tax_exemption_certificate`;

-- Create table for pending approvals (for real-time approval workflow)
CREATE TABLE IF NOT EXISTS `pos_pending_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT DEFAULT NULL,
    `approval_type` VARCHAR(50) NOT NULL COMMENT 'discount, price_override, general',
    `requested_by` INT NOT NULL,
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_by` INT DEFAULT NULL,
    `rejected_at` DATETIME DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    `reason` TEXT DEFAULT NULL,
    `approval_notes` TEXT DEFAULT NULL,
    `metadata` JSON DEFAULT NULL COMMENT 'Stores approval-specific data (discount amount, override price, etc.)',
    INDEX `idx_sale_id` (`sale_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_requested_by` (`requested_by`),
    INDEX `idx_approval_type` (`approval_type`),
    FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

