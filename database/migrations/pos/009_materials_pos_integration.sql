-- Materials-POS Integration
-- Bidirectional sync between materials inventory and POS system

-- Material return requests table
CREATE TABLE IF NOT EXISTS `pos_material_returns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_number` VARCHAR(50) NOT NULL UNIQUE,
    `material_type` VARCHAR(100) NOT NULL COMMENT 'screen_pipe, plain_pipe, gravel',
    `material_name` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_of_measure` VARCHAR(20) DEFAULT 'pcs',
    `status` ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending',
    `requested_by` INT(11) NOT NULL COMMENT 'User ID from materials side',
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `accepted_by` INT(11) DEFAULT NULL COMMENT 'POS user ID who accepted',
    `accepted_at` DATETIME DEFAULT NULL,
    `rejected_by` INT(11) DEFAULT NULL,
    `rejected_at` DATETIME DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL COMMENT 'Reason/notes for return',
    `pos_sale_id` INT(11) DEFAULT NULL COMMENT 'Linked sale if return is from a sale',
    `quality_check` TEXT DEFAULT NULL COMMENT 'Quality verification notes',
    `actual_quantity_received` DECIMAL(10,2) DEFAULT NULL COMMENT 'Actual quantity when accepted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_material_type` (`material_type`),
    INDEX `idx_requested_at` (`requested_at`),
    INDEX `idx_pos_sale` (`pos_sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Material-POS product mapping table
CREATE TABLE IF NOT EXISTS `pos_material_mappings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `material_type` VARCHAR(100) NOT NULL UNIQUE COMMENT 'screen_pipe, plain_pipe, gravel',
    `catalog_item_id` INT DEFAULT NULL COMMENT 'Link to catalog_items',
    `pos_product_id` INT UNSIGNED DEFAULT NULL COMMENT 'Link to pos_products',
    `auto_deduct_on_sale` TINYINT(1) DEFAULT 1 COMMENT 'Auto-deduct when company sale is made',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_material_type` (`material_type`),
    INDEX `idx_catalog_item` (`catalog_item_id`),
    INDEX `idx_pos_product` (`pos_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Material activity log
CREATE TABLE IF NOT EXISTS `pos_material_activity_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `material_type` VARCHAR(100) NOT NULL,
    `activity_type` ENUM('sale_deduction', 'return_request', 'return_accepted', 'return_rejected', 'manual_adjustment', 'stock_sync') NOT NULL,
    `quantity_change` DECIMAL(10,2) NOT NULL COMMENT 'Positive for addition, negative for deduction',
    `quantity_before` DECIMAL(10,2) DEFAULT NULL,
    `quantity_after` DECIMAL(10,2) DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'pos_sale, material_return, manual',
    `reference_id` INT(11) DEFAULT NULL,
    `performed_by` INT(11) NOT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_material_type` (`material_type`),
    INDEX `idx_activity_type` (`activity_type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default material mappings (will be populated based on existing catalog items)
-- These will be set up via the admin interface

