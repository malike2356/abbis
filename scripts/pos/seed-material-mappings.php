<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$materials = [
    'screen_pipe' => [
        'product' => [
            'sku' => 'MAT-SCRN-PIPE',
            'name' => 'Screen Pipe 3m (Field)',
            'description' => 'Screen pipe used for borehole construction (3 metre length)',
            'unit_price' => 150.00,
            'cost_price' => 105.00,
            'track_inventory' => 1,
            'is_active' => 1,
            'expose_to_shop' => 0,
        ],
        'unit_multiplier' => 1.000,
        'reorder_level' => 24,
        'reorder_quantity' => 48,
    ],
    'plain_pipe' => [
        'product' => [
            'sku' => 'MAT-PLN-PIPE',
            'name' => 'Plain Pipe 3m (Field)',
            'description' => 'Plain pipe used for borehole casing (3 metre length)',
            'unit_price' => 135.00,
            'cost_price' => 95.00,
            'track_inventory' => 1,
            'is_active' => 1,
            'expose_to_shop' => 0,
        ],
        'unit_multiplier' => 1.000,
        'reorder_level' => 24,
        'reorder_quantity' => 48,
    ],
    'gravel' => [
        'product' => [
            'sku' => 'MAT-GRAVEL-BAG',
            'name' => 'Gravel Pack (50kg)',
            'description' => 'Gravel pack for well completion (50kg bag)',
            'unit_price' => 45.00,
            'cost_price' => 28.00,
            'track_inventory' => 1,
            'is_active' => 1,
            'expose_to_shop' => 0,
        ],
        'unit_multiplier' => 1.000,
        'reorder_level' => 60,
        'reorder_quantity' => 120,
    ],
];

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        $slug = strtolower(bin2hex(random_bytes(4)));
    }
    return $slug;
}

try {
    $pdo->beginTransaction();

    // Ensure default category exists
    $categoryStmt = $pdo->prepare("SELECT id FROM pos_categories WHERE name = :name LIMIT 1");
    $categoryStmt->execute([':name' => 'Field Materials']);
    $categoryId = $categoryStmt->fetchColumn();

    if (!$categoryId) {
        $insertCategory = $pdo->prepare("
            INSERT INTO pos_categories (name, slug, description, is_active)
            VALUES (:name, :slug, :description, 1)
        ");
        $insertCategory->execute([
            ':name' => 'Field Materials',
            ':slug' => slugify('Field Materials'),
            ':description' => 'Consumables supplied from the POS store for field work',
        ]);
        $categoryId = (int) $pdo->lastInsertId();
        echo "Created POS category 'Field Materials' (ID: {$categoryId})" . PHP_EOL;
    } else {
        echo "Found existing POS category 'Field Materials' (ID: {$categoryId})" . PHP_EOL;
    }

    // Fetch stores
    $storeStmt = $pdo->query("SELECT id, store_name FROM pos_stores WHERE is_active = 1 ORDER BY is_primary DESC, id ASC");
    $stores = $storeStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($stores)) {
        throw new RuntimeException('No active POS stores found. Create at least one store before running this script.');
    }

    $productIdMap = [];

    foreach ($materials as $materialType => $config) {
        $productData = $config['product'];

        $selectProduct = $pdo->prepare("SELECT id FROM pos_products WHERE sku = :sku LIMIT 1");
        $selectProduct->execute([':sku' => $productData['sku']]);
        $productId = $selectProduct->fetchColumn();

        if ($productId) {
            $updateProduct = $pdo->prepare("
                UPDATE pos_products
                SET name = :name,
                    description = :description,
                    category_id = :category_id,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    track_inventory = :track_inventory,
                    is_active = :is_active,
                    expose_to_shop = :expose_to_shop,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateProduct->execute([
                ':name' => $productData['name'],
                ':description' => $productData['description'],
                ':category_id' => $categoryId,
                ':unit_price' => $productData['unit_price'],
                ':cost_price' => $productData['cost_price'],
                ':track_inventory' => $productData['track_inventory'],
                ':is_active' => $productData['is_active'],
                ':expose_to_shop' => $productData['expose_to_shop'],
                ':id' => $productId,
            ]);
            echo "Updated POS product {$productData['sku']} (ID: {$productId})" . PHP_EOL;
        } else {
            $insertProduct = $pdo->prepare("
                INSERT INTO pos_products
                    (sku, name, description, category_id, unit_price, cost_price, track_inventory, is_active, expose_to_shop)
                VALUES
                    (:sku, :name, :description, :category_id, :unit_price, :cost_price, :track_inventory, :is_active, :expose_to_shop)
            ");
            $insertProduct->execute([
                ':sku' => $productData['sku'],
                ':name' => $productData['name'],
                ':description' => $productData['description'],
                ':category_id' => $categoryId,
                ':unit_price' => $productData['unit_price'],
                ':cost_price' => $productData['cost_price'],
                ':track_inventory' => $productData['track_inventory'],
                ':is_active' => $productData['is_active'],
                ':expose_to_shop' => $productData['expose_to_shop'],
            ]);
            $productId = (int) $pdo->lastInsertId();
            echo "Created POS product {$productData['sku']} (ID: {$productId})" . PHP_EOL;
        }

        $productIdMap[$materialType] = $productId;

        // Ensure inventory rows exist with sensible reorder levels
        foreach ($stores as $store) {
            $inventoryStmt = $pdo->prepare("
                INSERT INTO pos_inventory (store_id, product_id, quantity_on_hand, reorder_level, reorder_quantity)
                VALUES (:store_id, :product_id, 0, :reorder_level, :reorder_quantity)
                ON DUPLICATE KEY UPDATE
                    reorder_level = :reorder_level,
                    reorder_quantity = :reorder_quantity
            ");
            $inventoryStmt->execute([
                ':store_id' => $store['id'],
                ':product_id' => $productId,
                ':reorder_level' => $config['reorder_level'],
                ':reorder_quantity' => $config['reorder_quantity'],
            ]);
        }
    }

    // Refresh pos_material_mappings
    $deleteMapping = $pdo->prepare("DELETE FROM pos_material_mappings WHERE material_type = :material_type");
    $insertMapping = $pdo->prepare("
        INSERT INTO pos_material_mappings (material_type, pos_product_id, unit_multiplier, notes)
        VALUES (:material_type, :pos_product_id, :unit_multiplier, :notes)
    ");

    foreach ($materials as $materialType => $config) {
        if (empty($productIdMap[$materialType])) {
            continue;
        }
        $deleteMapping->execute([':material_type' => $materialType]);
        $insertMapping->execute([
            ':material_type' => $materialType,
            ':pos_product_id' => $productIdMap[$materialType],
            ':unit_multiplier' => $config['unit_multiplier'],
            ':notes' => 'Auto-seeded mapping for field report consumption',
        ]);
        echo "Mapped {$materialType} to product ID {$productIdMap[$materialType]}" . PHP_EOL;
    }

    $pdo->commit();
    echo PHP_EOL . "POS material mappings seeded successfully." . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}


