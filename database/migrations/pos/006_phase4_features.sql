-- Phase 4 Features: Loyalty Programs, Gift Cards, Tax Management, Email Receipts, Promotions

-- Loyalty Programs
CREATE TABLE IF NOT EXISTS `pos_loyalty_programs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_name` VARCHAR(120) NOT NULL,
    `points_per_currency` DECIMAL(10,4) DEFAULT 1.0000,
    `currency_per_point` DECIMAL(10,4) DEFAULT 0.0100,
    `min_points_to_redeem` INT DEFAULT 100,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Loyalty Points
CREATE TABLE IF NOT EXISTS `pos_customer_loyalty` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT NOT NULL,
    `program_id` INT UNSIGNED NOT NULL,
    `points_balance` INT DEFAULT 0,
    `points_earned_lifetime` INT DEFAULT 0,
    `points_redeemed_lifetime` INT DEFAULT 0,
    `last_activity` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customer_program_unique` (`customer_id`, `program_id`),
    KEY `loyalty_customer_idx` (`customer_id`),
    CONSTRAINT `pos_loyalty_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_loyalty_program_fk` FOREIGN KEY (`program_id`) REFERENCES `pos_loyalty_programs` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loyalty Transactions
CREATE TABLE IF NOT EXISTS `pos_loyalty_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT NOT NULL,
    `program_id` INT UNSIGNED NOT NULL,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `transaction_type` ENUM('earned','redeemed','expired','adjusted') NOT NULL,
    `points` INT NOT NULL,
    `currency_value` DECIMAL(12,2) DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `loyalty_txn_customer_idx` (`customer_id`),
    KEY `loyalty_txn_sale_idx` (`sale_id`),
    CONSTRAINT `pos_loyalty_txn_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_loyalty_txn_program_fk` FOREIGN KEY (`program_id`) REFERENCES `pos_loyalty_programs` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_loyalty_txn_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gift Cards
CREATE TABLE IF NOT EXISTS `pos_gift_cards` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_number` VARCHAR(50) NOT NULL,
    `pin` VARCHAR(20) DEFAULT NULL,
    `initial_balance` DECIMAL(12,2) NOT NULL,
    `current_balance` DECIMAL(12,2) NOT NULL,
    `purchased_by_customer_id` INT DEFAULT NULL,
    `purchased_sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('active','used','expired','cancelled') DEFAULT 'active',
    `issued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `gift_card_number_unique` (`card_number`),
    KEY `gift_cards_customer_idx` (`purchased_by_customer_id`),
    KEY `gift_cards_sale_idx` (`purchased_sale_id`),
    KEY `gift_cards_status_idx` (`status`),
    CONSTRAINT `pos_gift_cards_customer_fk` FOREIGN KEY (`purchased_by_customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
    CONSTRAINT `pos_gift_cards_sale_fk` FOREIGN KEY (`purchased_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gift Card Transactions
CREATE TABLE IF NOT EXISTS `pos_gift_card_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gift_card_id` INT UNSIGNED NOT NULL,
    `sale_id` BIGINT UNSIGNED DEFAULT NULL,
    `transaction_type` ENUM('purchase','redemption','refund','adjustment') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after` DECIMAL(12,2) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `gift_card_txn_card_idx` (`gift_card_id`),
    KEY `gift_card_txn_sale_idx` (`sale_id`),
    CONSTRAINT `pos_gift_card_txn_card_fk` FOREIGN KEY (`gift_card_id`) REFERENCES `pos_gift_cards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_gift_card_txn_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tax Rules and Rates
CREATE TABLE IF NOT EXISTS `pos_tax_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rule_name` VARCHAR(120) NOT NULL,
    `tax_rate` DECIMAL(5,2) NOT NULL,
    `tax_type` ENUM('percentage','fixed') DEFAULT 'percentage',
    `applies_to` ENUM('all','category','product','customer') DEFAULT 'all',
    `category_id` INT UNSIGNED DEFAULT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `customer_id` INT DEFAULT NULL,
    `priority` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `tax_rules_category_idx` (`category_id`),
    KEY `tax_rules_product_idx` (`product_id`),
    KEY `tax_rules_customer_idx` (`customer_id`),
    CONSTRAINT `pos_tax_rules_category_fk` FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_tax_rules_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_tax_rules_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Receipts
CREATE TABLE IF NOT EXISTS `pos_email_receipts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` INT DEFAULT NULL,
    `email_address` VARCHAR(255) NOT NULL,
    `email_subject` VARCHAR(255) DEFAULT NULL,
    `email_body_html` LONGTEXT,
    `email_body_text` TEXT,
    `status` ENUM('pending','sent','failed','bounced') DEFAULT 'pending',
    `sent_at` DATETIME DEFAULT NULL,
    `error_message` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `email_receipts_sale_idx` (`sale_id`),
    KEY `email_receipts_customer_idx` (`customer_id`),
    CONSTRAINT `pos_email_receipts_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_email_receipts_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotions and Coupons
CREATE TABLE IF NOT EXISTS `pos_promotions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `promotion_code` VARCHAR(50) NOT NULL,
    `promotion_name` VARCHAR(120) NOT NULL,
    `discount_type` ENUM('percentage','fixed','buy_x_get_y','free_shipping') NOT NULL,
    `discount_value` DECIMAL(12,2) NOT NULL,
    `min_purchase_amount` DECIMAL(12,2) DEFAULT NULL,
    `max_discount_amount` DECIMAL(12,2) DEFAULT NULL,
    `applicable_to` ENUM('all','category','product','customer') DEFAULT 'all',
    `category_id` INT UNSIGNED DEFAULT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `customer_id` INT DEFAULT NULL,
    `usage_limit` INT DEFAULT NULL,
    `usage_count` INT DEFAULT 0,
    `usage_limit_per_customer` INT DEFAULT NULL,
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promotion_code_unique` (`promotion_code`),
    KEY `promotions_category_idx` (`category_id`),
    KEY `promotions_product_idx` (`product_id`),
    KEY `promotions_customer_idx` (`customer_id`),
    CONSTRAINT `pos_promotions_category_fk` FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_promotions_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_promotions_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotion Usage Tracking
CREATE TABLE IF NOT EXISTS `pos_promotion_usage` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `promotion_id` INT UNSIGNED NOT NULL,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` INT DEFAULT NULL,
    `discount_amount` DECIMAL(12,2) NOT NULL,
    `used_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `promotion_usage_promo_idx` (`promotion_id`),
    KEY `promotion_usage_sale_idx` (`sale_id`),
    KEY `promotion_usage_customer_idx` (`customer_id`),
    CONSTRAINT `pos_promotion_usage_promo_fk` FOREIGN KEY (`promotion_id`) REFERENCES `pos_promotions` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_promotion_usage_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_promotion_usage_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add gift card payment method to sale_payments enum
ALTER TABLE `pos_sale_payments`
MODIFY COLUMN `payment_method` ENUM('cash','card','mobile_money','bank_transfer','voucher','gift_card','store_credit','other') NOT NULL;

-- Add promotion_id to sales
SET @dbname = DATABASE();
SET @tablename = 'pos_sales';

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'promotion_id') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `promotion_id` INT UNSIGNED DEFAULT NULL,
            ADD KEY `sales_promotion_idx` (`promotion_id`),
            ADD CONSTRAINT `pos_sales_promotion_fk` FOREIGN KEY (`promotion_id`) REFERENCES `pos_promotions` (`id`) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add loyalty points to sales
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'loyalty_points_earned') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `loyalty_points_earned` INT DEFAULT 0,
            ADD COLUMN `loyalty_points_redeemed` INT DEFAULT 0')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add email receipt sent flag
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'email_receipt_sent') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `email_receipt_sent` TINYINT(1) DEFAULT 0')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insert default loyalty program
INSERT INTO pos_loyalty_programs (program_name, points_per_currency, currency_per_point, min_points_to_redeem, is_active)
SELECT 'Default Loyalty Program', 1.0000, 0.0100, 100, 1
WHERE NOT EXISTS (SELECT 1 FROM pos_loyalty_programs WHERE program_name = 'Default Loyalty Program');

