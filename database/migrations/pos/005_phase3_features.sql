-- Phase 3 Features: Advanced Reporting, Inventory Management, Employee Tracking, Product Variants

-- Employee Shifts Tracking
CREATE TABLE IF NOT EXISTS `pos_employee_shifts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` INT NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `shift_start` DATETIME NOT NULL,
    `shift_end` DATETIME DEFAULT NULL,
    `opening_cash` DECIMAL(12,2) DEFAULT 0.00,
    `closing_cash` DECIMAL(12,2) DEFAULT NULL,
    `expected_cash` DECIMAL(12,2) DEFAULT NULL,
    `cash_difference` DECIMAL(12,2) DEFAULT NULL,
    `total_sales` DECIMAL(12,2) DEFAULT 0.00,
    `total_transactions` INT DEFAULT 0,
    `status` ENUM('active','completed','cancelled') DEFAULT 'active',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `shifts_employee_idx` (`employee_id`),
    KEY `shifts_store_idx` (`store_id`),
    KEY `shifts_status_idx` (`status`),
    CONSTRAINT `pos_shifts_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_shifts_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Variants
CREATE TABLE IF NOT EXISTS `pos_product_variants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_name` VARCHAR(120) NOT NULL,
    `sku_suffix` VARCHAR(30) DEFAULT NULL,
    `barcode` VARCHAR(100) DEFAULT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `cost_price` DECIMAL(12,2) DEFAULT NULL,
    `quantity_on_hand` DECIMAL(14,3) DEFAULT 0,
    `is_default` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `variants_product_idx` (`product_id`),
    UNIQUE KEY `variant_barcode_unique` (`barcode`),
    CONSTRAINT `pos_variants_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Analytics Cache (for performance)
CREATE TABLE IF NOT EXISTS `pos_sales_analytics_cache` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(100) NOT NULL,
    `cache_date` DATE NOT NULL,
    `store_id` INT UNSIGNED DEFAULT NULL,
    `data_json` JSON NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `analytics_cache_key_date` (`cache_key`, `cache_date`, `store_id`),
    KEY `analytics_cache_date_idx` (`cache_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced inventory alerts tracking
ALTER TABLE `pos_inventory`
ADD COLUMN IF NOT EXISTS `last_alert_sent` DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `alert_frequency_hours` INT DEFAULT 24;

-- Check and add columns if they don't exist
SET @dbname = DATABASE();
SET @tablename = 'pos_inventory';

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'last_alert_sent') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `last_alert_sent` DATETIME DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'alert_frequency_hours') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `alert_frequency_hours` INT DEFAULT 24')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update sale_items to support variants
SET @tablename = 'pos_sale_items';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'variant_id') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `variant_id` INT UNSIGNED DEFAULT NULL,
            ADD KEY `sale_items_variant_idx` (`variant_id`),
            ADD CONSTRAINT `pos_sale_items_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `pos_product_variants` (`id`) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

