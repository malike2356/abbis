-- POS Procurement & Supplier Extensions

CREATE TABLE IF NOT EXISTS `pos_suppliers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(180) NOT NULL,
    `email` VARCHAR(180) DEFAULT NULL,
    `phone` VARCHAR(60) DEFAULT NULL,
    `payment_terms` VARCHAR(120) DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT NULL,
    `tax_number` VARCHAR(80) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `supplier_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_supplier_contacts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `role` VARCHAR(120) DEFAULT NULL,
    `phone` VARCHAR(60) DEFAULT NULL,
    `email` VARCHAR(180) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_contacts_supplier_idx` (`supplier_id`),
    CONSTRAINT `supplier_contacts_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `pos_suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_purchase_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `po_number` VARCHAR(40) NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `status` ENUM('draft','pending_approval','approved','partially_received','completed','cancelled') NOT NULL DEFAULT 'draft',
    `expected_date` DATE DEFAULT NULL,
    `payment_terms` VARCHAR(120) DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `po_number_unique` (`po_number`),
    KEY `po_supplier_idx` (`supplier_id`),
    KEY `po_store_idx` (`store_id`),
    CONSTRAINT `po_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `pos_suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `po_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `po_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `po_approved_by_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_purchase_order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `po_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ordered_qty` DECIMAL(14,3) NOT NULL,
    `received_qty` DECIMAL(14,3) NOT NULL DEFAULT 0,
    `billed_qty` DECIMAL(14,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `tax_rate` DECIMAL(8,3) DEFAULT NULL,
    `discount_percent` DECIMAL(6,3) DEFAULT NULL,
    `expected_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `poi_po_idx` (`po_id`),
    KEY `poi_product_idx` (`product_id`),
    CONSTRAINT `poi_po_fk` FOREIGN KEY (`po_id`) REFERENCES `pos_purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `poi_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_goods_receipts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `grn_number` VARCHAR(40) NOT NULL,
    `po_id` BIGINT UNSIGNED DEFAULT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `received_by` INT DEFAULT NULL,
    `received_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('draft','pending_inspection','completed','cancelled') NOT NULL DEFAULT 'draft',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `grn_number_unique` (`grn_number`),
    KEY `grn_po_idx` (`po_id`),
    KEY `grn_store_idx` (`store_id`),
    CONSTRAINT `grn_po_fk` FOREIGN KEY (`po_id`) REFERENCES `pos_purchase_orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `grn_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `grn_received_by_fk` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_goods_receipt_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `grn_id` BIGINT UNSIGNED NOT NULL,
    `po_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `received_qty` DECIMAL(14,3) NOT NULL,
    `rejected_qty` DECIMAL(14,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `batch_code` VARCHAR(80) DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `grn_item_grn_idx` (`grn_id`),
    KEY `grn_item_po_item_idx` (`po_item_id`),
    KEY `grn_item_product_idx` (`product_id`),
    CONSTRAINT `grn_item_grn_fk` FOREIGN KEY (`grn_id`) REFERENCES `pos_goods_receipts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `grn_item_po_item_fk` FOREIGN KEY (`po_item_id`) REFERENCES `pos_purchase_order_items` (`id`) ON DELETE SET NULL,
    CONSTRAINT `grn_item_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_supplier_invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(60) NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `po_id` BIGINT UNSIGNED DEFAULT NULL,
    `grn_id` BIGINT UNSIGNED DEFAULT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE DEFAULT NULL,
    `status` ENUM('draft','pending_approval','approved','paid','void') NOT NULL DEFAULT 'draft',
    `currency` VARCHAR(10) DEFAULT NULL,
    `subtotal_amount` DECIMAL(14,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(14,2) DEFAULT 0.00,
    `total_amount` DECIMAL(14,2) DEFAULT 0.00,
    `balance_due` DECIMAL(14,2) DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoice_number_unique` (`invoice_number`),
    KEY `invoice_supplier_idx` (`supplier_id`),
    KEY `invoice_store_idx` (`store_id`),
    KEY `invoice_po_idx` (`po_id`),
    CONSTRAINT `supplier_invoice_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `pos_suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `supplier_invoice_store_fk` FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `supplier_invoice_po_fk` FOREIGN KEY (`po_id`) REFERENCES `pos_purchase_orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `supplier_invoice_grn_fk` FOREIGN KEY (`grn_id`) REFERENCES `pos_goods_receipts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `supplier_invoice_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_supplier_invoice_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(14,3) NOT NULL,
    `unit_cost` DECIMAL(12,4) NOT NULL,
    `tax_rate` DECIMAL(8,3) DEFAULT NULL,
    `line_total` DECIMAL(14,2) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `invoice_item_invoice_idx` (`invoice_id`),
    KEY `invoice_item_product_idx` (`product_id`),
    CONSTRAINT `invoice_item_invoice_fk` FOREIGN KEY (`invoice_id`) REFERENCES `pos_supplier_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invoice_item_product_fk` FOREIGN KEY (`product_id`) REFERENCES `pos_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend accounting queue to handle non-sale references
ALTER TABLE `pos_accounting_queue`
    DROP FOREIGN KEY `pos_accounting_sale_fk`,
    DROP INDEX `pos_accounting_sale_unique`,
    MODIFY COLUMN `sale_id` BIGINT UNSIGNED DEFAULT NULL;

ALTER TABLE `pos_accounting_queue`
    ADD COLUMN `reference_type` VARCHAR(40) NOT NULL DEFAULT 'sale' AFTER `sale_id`,
    ADD COLUMN `reference_id` BIGINT UNSIGNED DEFAULT NULL AFTER `reference_type`,
    ADD KEY `accounting_reference_idx` (`reference_type`, `reference_id`);

ALTER TABLE `pos_accounting_queue`
    ADD CONSTRAINT `pos_accounting_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE;



