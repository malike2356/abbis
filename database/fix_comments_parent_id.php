<?php
/**
 * Fix missing parent_id column in cms_comments table
 * Run this script manually if automatic migration fails
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();

echo "Checking cms_comments table for parent_id column...\n\n";

try {
    // Check if column exists
    $checkCol = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = 'parent_id'");
    $colExists = $checkCol->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if ($colExists) {
        echo "✅ Column 'parent_id' already exists in cms_comments table.\n";
    } else {
        echo "❌ Column 'parent_id' is missing. Adding it now...\n";
        
        // Add column
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN parent_id INT DEFAULT NULL COMMENT 'For threaded comments' AFTER user_agent");
            echo "✅ Column 'parent_id' added successfully.\n";
        } catch (PDOException $e) {
            // Try without AFTER clause
            try {
                $pdo->exec("ALTER TABLE cms_comments ADD COLUMN parent_id INT DEFAULT NULL");
                echo "✅ Column 'parent_id' added successfully (without AFTER clause).\n";
            } catch (PDOException $e2) {
                echo "❌ Failed to add column: " . $e2->getMessage() . "\n";
                exit(1);
            }
        }
        
        // Add foreign key
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES cms_comments(id) ON DELETE CASCADE");
            echo "✅ Foreign key constraint added successfully.\n";
        } catch (PDOException $e) {
            echo "⚠️  Foreign key might already exist or couldn't be added: " . $e->getMessage() . "\n";
        }
        
        // Add index
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD INDEX idx_parent (parent_id)");
            echo "✅ Index added successfully.\n";
        } catch (PDOException $e) {
            echo "⚠️  Index might already exist or couldn't be added: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Comments should now work properly.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

