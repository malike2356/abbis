-- Add materials_value column to field_reports
-- This column stores the total value of remaining materials (assets)

ALTER TABLE `field_reports` 
ADD COLUMN IF NOT EXISTS `materials_value` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total value of remaining materials (assets)' 
AFTER `materials_cost`;

