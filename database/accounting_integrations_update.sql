-- Update accounting_integrations table to add company_id and updated_at columns
-- For QuickBooks company/realm ID storage

ALTER TABLE `accounting_integrations` 
ADD COLUMN IF NOT EXISTS `company_id` VARCHAR(100) DEFAULT NULL COMMENT 'QuickBooks Company/Realm ID' AFTER `redirect_uri`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Add index for company_id
CREATE INDEX IF NOT EXISTS `idx_company_id` ON `accounting_integrations` (`company_id`);


