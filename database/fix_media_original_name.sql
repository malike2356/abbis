-- Fix missing original_name column in cms_media table
-- Run this if you get "Unknown column 'original_name'" errors

ALTER TABLE cms_media 
ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) DEFAULT NULL AFTER filename;

-- If the above doesn't work (MySQL < 8.0.19), use this instead:
-- ALTER TABLE cms_media ADD COLUMN original_name VARCHAR(255) DEFAULT NULL AFTER filename;

