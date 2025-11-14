-- Enhanced Accounting Accounts for POS
-- Add new accounts for better financial tracking

-- Discount Expense Account
INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
SELECT '5201', 'Discount Expense', 'Expense', 1
WHERE NOT EXISTS (
    SELECT 1 FROM chart_of_accounts WHERE account_code = '5201'
);

-- Payment Processing Fees Account
INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
SELECT '5202', 'Payment Processing Fees', 'Expense', 1
WHERE NOT EXISTS (
    SELECT 1 FROM chart_of_accounts WHERE account_code = '5202'
);

-- Card Receivables Account (for card payments that settle to bank)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
SELECT '1101', 'Card Receivables', 'Asset', 1
WHERE NOT EXISTS (
    SELECT 1 FROM chart_of_accounts WHERE account_code = '1101'
);

-- Checks Receivable Account
INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
SELECT '1102', 'Checks Receivable', 'Asset', 1
WHERE NOT EXISTS (
    SELECT 1 FROM chart_of_accounts WHERE account_code = '1102'
);

-- Gift Card Liability Account
INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
SELECT '2101', 'Gift Card Liability', 'Liability', 1
WHERE NOT EXISTS (
    SELECT 1 FROM chart_of_accounts WHERE account_code = '2101'
);

-- Service Revenue Account (for service-based stores)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
SELECT '4010', 'Service Revenue', 'Revenue', 1
WHERE NOT EXISTS (
    SELECT 1 FROM chart_of_accounts WHERE account_code = '4010'
);

-- Add system config for processing fees
INSERT INTO system_config (config_key, config_value, config_description)
SELECT 'pos_card_processing_fee_rate', '0.03', 'Card processing fee rate (e.g., 0.03 for 3%)'
WHERE NOT EXISTS (
    SELECT 1 FROM system_config WHERE config_key = 'pos_card_processing_fee_rate'
);

INSERT INTO system_config (config_key, config_value, config_description)
SELECT 'pos_momo_processing_fee_rate', '0.01', 'Mobile money processing fee rate (e.g., 0.01 for 1%)'
WHERE NOT EXISTS (
    SELECT 1 FROM system_config WHERE config_key = 'pos_momo_processing_fee_rate'
);

-- Add metadata table for POS sale accounting details (optional enhancement)
CREATE TABLE IF NOT EXISTS `pos_sale_accounting_metadata` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `journal_entry_id` INT NOT NULL,
    `customer_id` INT DEFAULT NULL,
    `store_id` INT DEFAULT NULL,
    `total_items` INT DEFAULT 0,
    `payment_methods` JSON DEFAULT NULL,
    `product_categories` JSON DEFAULT NULL,
    `discount_details` JSON DEFAULT NULL,
    `tax_breakdown` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `sale_id_unique` (`sale_id`),
    INDEX `journal_entry_id_idx` (`journal_entry_id`),
    INDEX `customer_id_idx` (`customer_id`),
    INDEX `store_id_idx` (`store_id`),
    FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`store_id`) REFERENCES `pos_stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

