-- Add catalog link support to inventory transactions

ALTER TABLE `inventory_transactions`
  ADD COLUMN `catalog_item_id` INT NULL AFTER `material_id`;

-- Allow null material_id for generic catalog-linked entries
ALTER TABLE `inventory_transactions`
  MODIFY `material_id` INT(11) NULL;

-- Optional index for reporting
ALTER TABLE `inventory_transactions`
  ADD INDEX `idx_catalog_item_id` (`catalog_item_id`);


