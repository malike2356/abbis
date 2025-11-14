-- POS Integration Enhancements

ALTER TABLE field_reports
    MODIFY COLUMN `materials_provided_by` ENUM('client','company','material_shop','store') DEFAULT 'client',
    ADD COLUMN `materials_store_id` INT UNSIGNED NULL AFTER `materials_provided_by`,
    ADD CONSTRAINT `field_reports_store_fk` FOREIGN KEY (`materials_store_id`) REFERENCES pos_stores(`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `pos_material_mappings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `material_type` ENUM('screen_pipe','plain_pipe','gravel','custom') NOT NULL,
    `pos_product_id` INT UNSIGNED NOT NULL,
    `unit_multiplier` DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    `notes` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `material_product_unique` (`material_type`, `pos_product_id`),
    CONSTRAINT `pos_mapping_product_fk` FOREIGN KEY (`pos_product_id`) REFERENCES pos_products(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pos_products
    ADD COLUMN `expose_to_shop` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `catalog_item_id` INT DEFAULT NULL,
    ADD CONSTRAINT `pos_products_catalog_fk` FOREIGN KEY (`catalog_item_id`) REFERENCES catalog_items(`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `pos_cashier_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT NOT NULL,
    `session_code` VARCHAR(40) NOT NULL,
    `opened_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    `opening_float` DECIMAL(12,2) DEFAULT 0.00,
    `closing_amount` DECIMAL(12,2) DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_code_unique` (`session_code`),
    KEY `cashier_session_idx` (`cashier_id`,`closed_at`),
    CONSTRAINT `pos_session_store_fk` FOREIGN KEY (`store_id`) REFERENCES pos_stores(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


