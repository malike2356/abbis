-- Fix missing parent_id column in cms_comments table
-- Run this if automatic migration fails

-- Check if column exists and add it if missing
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cms_comments' 
    AND COLUMN_NAME = 'parent_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cms_comments ADD COLUMN parent_id INT DEFAULT NULL COMMENT ''For threaded comments'' AFTER user_agent',
    'SELECT ''Column parent_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cms_comments' 
    AND CONSTRAINT_NAME = 'fk_comment_parent'
);

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE cms_comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES cms_comments(id) ON DELETE CASCADE',
    'SELECT ''Foreign key fk_comment_parent already exists'' AS message'
);

PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Add index if it doesn't exist
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cms_comments' 
    AND INDEX_NAME = 'idx_parent'
);

SET @sql_idx = IF(@idx_exists = 0,
    'ALTER TABLE cms_comments ADD INDEX idx_parent (parent_id)',
    'SELECT ''Index idx_parent already exists'' AS message'
);

PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SELECT 'Migration completed successfully!' AS result;

