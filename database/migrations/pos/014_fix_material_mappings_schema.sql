-- Fix pos_material_mappings table schema
-- Adds missing catalog_item_id column and updates structure to match latest schema

-- Check and add catalog_item_id column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'pos_material_mappings';
SET @columnname = 'catalog_item_id';

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1', -- Column exists, do nothing
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` INT DEFAULT NULL COMMENT ''Link to catalog_items'' AFTER `material_type`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add auto_deduct_on_sale column if it doesn't exist
SET @columnname = 'auto_deduct_on_sale';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` TINYINT(1) DEFAULT 1 COMMENT ''Auto-deduct when company sale is made'' AFTER `pos_product_id`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Remove old columns that are no longer needed (if they exist)
-- Check if unit_multiplier exists and remove it
SET @columnname = 'unit_multiplier';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = @columnname) > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `', @columnname, '`'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check if notes exists and remove it (or keep it if you want to preserve data)
-- We'll keep notes for now but you can uncomment to remove:
-- SET @columnname = 'notes';
-- SET @preparedStatement = (SELECT IF(
--     (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
--      WHERE TABLE_SCHEMA = @dbname 
--      AND TABLE_NAME = @tablename 
--      AND COLUMN_NAME = @columnname) > 0,
--     CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `', @columnname, '`'),
--     'SELECT 1'
-- ));
-- PREPARE alterIfNotExists FROM @preparedStatement;
-- EXECUTE alterIfNotExists;
-- DEALLOCATE PREPARE alterIfNotExists;

-- Check if is_active exists and remove it (we don't need it in the new schema)
SET @columnname = 'is_active';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = @columnname) > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `', @columnname, '`'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Modify material_type to allow more values (remove ENUM restriction if it exists)
-- First check if it's an ENUM - we need to drop unique constraints first if they exist
SET @constraintname = 'material_product_unique';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname
     AND CONSTRAINT_TYPE = 'UNIQUE') > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP INDEX `', @constraintname, '`'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Now modify material_type from ENUM to VARCHAR if needed
SET @preparedStatement = (SELECT IF(
    (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'material_type') = 'enum',
    CONCAT('ALTER TABLE `', @tablename, '` MODIFY COLUMN `material_type` VARCHAR(100) NOT NULL COMMENT ''screen_pipe, plain_pipe, gravel'''),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Make pos_product_id nullable if it's not already
SET @preparedStatement = (SELECT IF(
    (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'pos_product_id') = 'NO',
    CONCAT('ALTER TABLE `', @tablename, '` MODIFY COLUMN `pos_product_id` INT UNSIGNED DEFAULT NULL COMMENT ''Link to pos_products'''),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for catalog_item_id if it doesn't exist
SET @indexname = 'idx_catalog_item';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD INDEX `', @indexname, '` (`catalog_item_id`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update unique constraint to allow material_type to be unique (remove material_type + pos_product_id unique if it exists)
-- Drop old unique constraint if it exists
SET @constraintname = 'material_product_unique';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname
     AND CONSTRAINT_TYPE = 'UNIQUE') > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP INDEX `', @constraintname, '`'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add unique constraint on material_type if it doesn't exist
SET @constraintname = 'material_type';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname
     AND CONSTRAINT_TYPE = 'UNIQUE') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @tablename, '` ADD UNIQUE KEY `material_type` (`material_type`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

