<?php
/**
 * Fix Catalog Categories - Reorganize to Clear Product/Service Structure
 * 
 * Current Issues:
 * - "Services & Construction" is confusing (mixes services with construction)
 * - "Pumps" and "Materials & Parts" are both products but separated
 * - No clear PRODUCT vs SERVICE distinction
 * 
 * Solution:
 * - Rename "Services & Construction" to "Services" (clear service category)
 * - Merge "Pumps" into "Materials & Parts" OR create "Products" category
 * - Ensure clear distinction: Products (physical items) vs Services (non-physical)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDBConnection();

echo "🔍 Analyzing current catalog structure...\n\n";

// Get current categories
$categories = $pdo->query("SELECT id, name, description, (SELECT COUNT(*) FROM catalog_items WHERE category_id = catalog_categories.id) as item_count FROM catalog_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

echo "Current Categories:\n";
foreach ($categories as $cat) {
    echo "  - ID {$cat['id']}: {$cat['name']} ({$cat['item_count']} items)\n";
    echo "    Description: {$cat['description']}\n";
}

// Get item type distribution
echo "\n📊 Item Type Distribution:\n";
$distribution = $pdo->query("
    SELECT 
        c.id as category_id,
        c.name as category_name,
        i.item_type,
        COUNT(*) as count
    FROM catalog_categories c
    LEFT JOIN catalog_items i ON i.category_id = c.id
    GROUP BY c.id, c.name, i.item_type
    ORDER BY c.id, i.item_type
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($distribution as $dist) {
    if ($dist['item_type']) {
        echo "  - {$dist['category_name']}: {$dist['count']} {$dist['item_type']}(s)\n";
    }
}

echo "\n\n✅ Proposed Solution:\n";
echo "  1. Rename 'Services & Construction' → 'Services' (clear service category)\n";
echo "  2. Rename 'Materials & Parts' → 'Products' (all physical items)\n";
echo "  3. Merge 'Pumps' items into 'Products' category\n";
echo "  4. Delete 'Pumps' category (after migration)\n\n";

echo "⚠️  This will:\n";
echo "  - Update category names and descriptions\n";
echo "  - Move all items from 'Pumps' to 'Products'\n";
echo "  - Preserve all item data\n\n";

// Ask for confirmation
echo "Do you want to proceed? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
    echo "❌ Operation cancelled.\n";
    exit(0);
}

echo "\n🔄 Starting migration...\n\n";

try {
    $pdo->beginTransaction();
    
    // Step 1: Rename "Services & Construction" to "Services"
    echo "1. Renaming 'Services & Construction' to 'Services'...\n";
    $stmt = $pdo->prepare("UPDATE catalog_categories SET name = 'Services', description = 'Service offerings: drilling, construction, labor, maintenance, and related services' WHERE id = 2");
    $stmt->execute();
    echo "   ✅ Updated category ID 2\n";
    
    // Step 2: Rename "Materials & Parts" to "Products"
    echo "2. Renaming 'Materials & Parts' to 'Products'...\n";
    $stmt = $pdo->prepare("UPDATE catalog_categories SET name = 'Products', description = 'Physical products: pumps, materials, parts, equipment, and consumables' WHERE id = 3");
    $stmt->execute();
    echo "   ✅ Updated category ID 3\n";
    
    // Step 3: Move all items from "Pumps" (ID: 1) to "Products" (ID: 3)
    echo "3. Moving items from 'Pumps' to 'Products'...\n";
    $stmt = $pdo->prepare("UPDATE catalog_items SET category_id = 3 WHERE category_id = 1");
    $stmt->execute();
    $movedCount = $stmt->rowCount();
    echo "   ✅ Moved {$movedCount} items\n";
    
    // Step 4: Delete "Pumps" category (now empty)
    echo "4. Deleting empty 'Pumps' category...\n";
    $stmt = $pdo->prepare("DELETE FROM catalog_categories WHERE id = 1");
    $stmt->execute();
    echo "   ✅ Deleted category ID 1\n";
    
    $pdo->commit();
    
    echo "\n✅ Migration completed successfully!\n\n";
    
    // Show final structure
    echo "📋 Final Category Structure:\n";
    $finalCategories = $pdo->query("SELECT id, name, description, (SELECT COUNT(*) FROM catalog_items WHERE category_id = catalog_categories.id) as item_count FROM catalog_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalCategories as $cat) {
        echo "  - {$cat['name']}: {$cat['item_count']} items\n";
        echo "    {$cat['description']}\n";
    }
    
    echo "\n✨ Categories are now clearly organized:\n";
    echo "   • Products: All physical items (pumps, materials, parts, equipment)\n";
    echo "   • Services: All service offerings (drilling, construction, labor, etc.)\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   Rolled back all changes.\n";
    exit(1);
}



