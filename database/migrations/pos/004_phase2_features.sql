-- Phase 2 Features: Refunds, Cash Drawer, Price Overrides

-- Refunds/Returns table
CREATE TABLE IF NOT EXISTS `pos_refunds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_number` VARCHAR(40) NOT NULL,
    `original_sale_id` BIGINT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT NOT NULL,
    `customer_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(150) DEFAULT NULL,
    `refund_type` ENUM('full','partial') NOT NULL DEFAULT 'full',
    `refund_reason` VARCHAR(255) DEFAULT NULL,
    `subtotal_amount` DECIMAL(12,2) DEFAULT 0.00,
    `tax_total` DECIMAL(12,2) DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) DEFAULT 0.00,
    `refund_method` ENUM('cash','card','mobile_money','store_credit','original_method') NOT NULL DEFAULT 'original_method',
    `refund_status` ENUM('pending','completed','cancelled') DEFAULT 'completed',
    `notes` TEXT,
    `refund_timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `refund_number_unique` (`refund_number`),
    KEY `refunds_sale_idx` (`original_sale_id`),
    KEY `refunds_store_idx` (`store_id`),
    KEY `refunds_cashier_idx` (`cashier_id`),
    CONSTRAINT `pos_refunds_sale_fk` FOREIGN KEY (`original_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_refunds_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_refunds_cashier_fk` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_refunds_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refund items table
CREATE TABLE IF NOT EXISTS `pos_refund_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_id` BIGINT UNSIGNED NOT NULL,
    `original_sale_item_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(14,3) NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
    `line_total` DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `refund_items_refund_idx` (`refund_id`),
    KEY `refund_items_product_idx` (`product_id`),
    CONSTRAINT `pos_refund_items_refund_fk` FOREIGN KEY (`refund_id`) REFERENCES `pos_refunds` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_refund_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash drawer management
CREATE TABLE IF NOT EXISTS `pos_cash_drawer_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT NOT NULL,
    `opening_amount` DECIMAL(12,2) DEFAULT 0.00,
    `expected_amount` DECIMAL(12,2) DEFAULT 0.00,
    `counted_amount` DECIMAL(12,2) DEFAULT NULL,
    `difference` DECIMAL(12,2) DEFAULT NULL,
    `status` ENUM('open','closed','counted') DEFAULT 'open',
    `opened_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    `counted_at` DATETIME DEFAULT NULL,
    `notes` TEXT,
    PRIMARY KEY (`id`),
    KEY `drawer_store_idx` (`store_id`),
    KEY `drawer_cashier_idx` (`cashier_id`),
    KEY `drawer_status_idx` (`status`),
    CONSTRAINT `pos_drawer_sessions_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_drawer_sessions_cashier_fk` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price overrides tracking
-- Check and add columns if they don't exist
SET @dbname = DATABASE();
SET @tablename = 'pos_sale_items';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'price_override') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `price_override` TINYINT(1) DEFAULT 0')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'original_price') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `original_price` DECIMAL(12,2) DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'override_reason') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `override_reason` VARCHAR(255) DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'override_approved_by') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `override_approved_by` INT DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'override_approved_at') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `override_approved_at` DATETIME DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for price overrides (if not exists)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'price_override_idx') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD KEY `price_override_idx` (`price_override`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key for override approver (if not exists)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = 'pos_sale_items_approver_fk') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT `pos_sale_items_approver_fk` FOREIGN KEY (`override_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

