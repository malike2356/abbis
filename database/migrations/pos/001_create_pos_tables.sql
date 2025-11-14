-- POS Module Schema
-- Creates core tables for products, inventory, sales, payments, and receipts

CREATE TABLE IF NOT EXISTS `pos_stores` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_code` VARCHAR(30) NOT NULL,
    `store_name` VARCHAR(120) NOT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(40) DEFAULT NULL,
    `contact_email` VARCHAR(120) DEFAULT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `store_code_unique` (`store_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(160) DEFAULT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `category_slug_unique` (`slug`),
    CONSTRAINT `pos_categories_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `pos_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(60) NOT NULL,
    `name` VARCHAR(180) NOT NULL,
    `description` TEXT,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `barcode` VARCHAR(100) DEFAULT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cost_price` DECIMAL(12,2) DEFAULT NULL,
    `tax_rate` DECIMAL(5,2) DEFAULT NULL,
    `track_inventory` TINYINT(1) DEFAULT 1,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sku_unique` (`sku`),
    UNIQUE KEY `barcode_unique` (`barcode`),
    CONSTRAINT `pos_products_category_fk` FOREIGN KEY (`category_id`) REFERENCES `pos_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_inventory` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity_on_hand` DECIMAL(14,3) NOT NULL DEFAULT 0,
    `reorder_level` DECIMAL(14,3) DEFAULT NULL,
    `reorder_quantity` DECIMAL(14,3) DEFAULT NULL,
    `average_cost` DECIMAL(12,4) DEFAULT NULL,
    `last_restocked_at` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `store_product_unique` (`store_id`,`product_id`),
    CONSTRAINT `pos_inventory_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_inventory_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_stock_ledger` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `transaction_type` ENUM('opening_balance','purchase','sale','adjustment','transfer_in','transfer_out','return_in','return_out') NOT NULL,
    `reference_type` VARCHAR(60) DEFAULT NULL,
    `reference_id` VARCHAR(100) DEFAULT NULL,
    `quantity_delta` DECIMAL(14,3) NOT NULL,
    `unit_cost` DECIMAL(12,4) DEFAULT NULL,
    `remarks` VARCHAR(255) DEFAULT NULL,
    `performed_by` INT DEFAULT NULL,
    `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ledger_store_product_idx` (`store_id`,`product_id`),
    KEY `ledger_reference_idx` (`reference_type`,`reference_id`),
    CONSTRAINT `pos_stock_ledger_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_stock_ledger_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_stock_ledger_user_fk` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_number` VARCHAR(40) NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT NOT NULL,
    `customer_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(150) DEFAULT NULL,
    `sale_status` ENUM('completed','voided','refunded','held') DEFAULT 'completed',
    `payment_status` ENUM('paid','partial','unpaid') DEFAULT 'paid',
    `subtotal_amount` DECIMAL(12,2) DEFAULT 0.00,
    `discount_total` DECIMAL(12,2) DEFAULT 0.00,
    `tax_total` DECIMAL(12,2) DEFAULT 0.00,
    `total_amount` DECIMAL(12,2) DEFAULT 0.00,
    `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
    `change_due` DECIMAL(12,2) DEFAULT 0.00,
    `notes` VARCHAR(255) DEFAULT NULL,
    `sale_timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `synced_to_accounting` TINYINT(1) DEFAULT 0,
    `synced_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sale_number_unique` (`sale_number`),
    KEY `sales_store_idx` (`store_id`),
    KEY `sales_cashier_idx` (`cashier_id`),
    CONSTRAINT `pos_sales_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_sales_cashier_fk` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `pos_sales_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_sale_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `quantity` DECIMAL(14,3) NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
    `line_total` DECIMAL(12,2) NOT NULL,
    `cost_amount` DECIMAL(12,2) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sale_items_sale_idx` (`sale_id`),
    KEY `sale_items_product_idx` (`product_id`),
    CONSTRAINT `pos_sale_items_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_sale_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_sale_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `payment_method` ENUM('cash','card','mobile_money','bank_transfer','voucher','other') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `reference` VARCHAR(120) DEFAULT NULL,
    `received_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `sale_payments_sale_idx` (`sale_id`),
    CONSTRAINT `pos_sale_payments_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_receipts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `receipt_number` VARCHAR(50) NOT NULL,
    `format` ENUM('thermal','full_page','email_pdf') DEFAULT 'thermal',
    `content_html` LONGTEXT,
    `printed_by` INT DEFAULT NULL,
    `printed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `receipt_number_unique` (`receipt_number`),
    CONSTRAINT `pos_receipts_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_receipts_user_fk` FOREIGN KEY (`printed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_accounting_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `reference_type` VARCHAR(60) DEFAULT NULL,
    `reference_id` VARCHAR(100) DEFAULT NULL,
    `payload_json` JSON NOT NULL,
    `status` ENUM('pending','processing','synced','error') DEFAULT 'pending',
    `attempts` INT UNSIGNED DEFAULT 0,
    `last_error` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `pos_accounting_sale_unique` (`sale_id`),
    KEY `pos_accounting_reference_idx` (`reference_type`,`reference_id`),
    CONSTRAINT `pos_accounting_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pos_stores (store_code, store_name, is_primary, is_active)
SELECT 'MAIN', 'Main Store', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM pos_stores WHERE store_code = 'MAIN'
);


