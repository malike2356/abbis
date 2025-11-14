-- Add receipt number fields to pos_sales

SET @dbname = DATABASE();
SET @tablename = 'pos_sales';

-- Add generated receipt number
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'receipt_number') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `receipt_number` VARCHAR(50) DEFAULT NULL,
            ADD UNIQUE KEY `sales_receipt_number_unique` (`receipt_number`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add paper receipt number (for manual entry to match existing paper system)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'paper_receipt_number') > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `paper_receipt_number` VARCHAR(50) DEFAULT NULL,
            ADD KEY `sales_paper_receipt_idx` (`paper_receipt_number`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

