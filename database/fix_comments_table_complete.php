<?php
/**
 * Complete fix for cms_comments table - adds all missing columns
 * Run this script to ensure all required columns exist
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();

echo "Checking and fixing cms_comments table...\n\n";

// Required columns with their definitions
$requiredColumns = [
    'parent_id' => [
        'definition' => "INT DEFAULT NULL COMMENT 'For threaded comments'",
        'after' => 'user_agent'
    ],
    'ip_address' => [
        'definition' => "VARCHAR(45) DEFAULT NULL",
        'after' => 'user_agent'
    ],
    'user_agent' => [
        'definition' => "TEXT DEFAULT NULL",
        'after' => 'updated_at'
    ]
];

foreach ($requiredColumns as $columnName => $columnInfo) {
    try {
        // Check if column exists
        $checkCol = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = '$columnName'");
        $colExists = $checkCol->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
        
        if ($colExists) {
            echo "✅ Column '$columnName' already exists.\n";
        } else {
            echo "❌ Column '$columnName' is missing. Adding it now...\n";
            
            // Try with AFTER clause first
            try {
                if (isset($columnInfo['after'])) {
                    $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName {$columnInfo['definition']} AFTER {$columnInfo['after']}");
                } else {
                    $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName {$columnInfo['definition']}");
                }
                echo "✅ Column '$columnName' added successfully.\n";
            } catch (PDOException $e1) {
                // If AFTER fails, try without it
                try {
                    $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName {$columnInfo['definition']}");
                    echo "✅ Column '$columnName' added successfully (without AFTER clause).\n";
                } catch (PDOException $e2) {
                    echo "❌ Failed to add column '$columnName': " . $e2->getMessage() . "\n";
                }
            }
        }
    } catch (PDOException $e) {
        echo "⚠️  Error checking column '$columnName': " . $e->getMessage() . "\n";
    }
}

// Add foreign key for parent_id
echo "\nChecking foreign key constraint...\n";
try {
    $checkFk = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND CONSTRAINT_NAME = 'fk_comment_parent'");
    $fkExists = $checkFk->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if ($fkExists) {
        echo "✅ Foreign key 'fk_comment_parent' already exists.\n";
    } else {
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES cms_comments(id) ON DELETE CASCADE");
            echo "✅ Foreign key constraint added successfully.\n";
        } catch (PDOException $e) {
            echo "⚠️  Could not add foreign key: " . $e->getMessage() . "\n";
        }
    }
} catch (PDOException $e) {
    echo "⚠️  Error checking foreign key: " . $e->getMessage() . "\n";
}

// Add index for parent_id
echo "\nChecking index...\n";
try {
    $checkIdx = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND INDEX_NAME = 'idx_parent'");
    $idxExists = $checkIdx->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if ($idxExists) {
        echo "✅ Index 'idx_parent' already exists.\n";
    } else {
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD INDEX idx_parent (parent_id)");
            echo "✅ Index added successfully.\n";
        } catch (PDOException $e) {
            echo "⚠️  Could not add index: " . $e->getMessage() . "\n";
        }
    }
} catch (PDOException $e) {
    echo "⚠️  Error checking index: " . $e->getMessage() . "\n";
}

echo "\n✅ Migration completed!\n";
echo "All required columns should now exist in cms_comments table.\n";

