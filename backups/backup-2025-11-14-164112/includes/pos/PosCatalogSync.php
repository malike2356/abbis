<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

class PosCatalogSync
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function syncAll(): array
    {
        $stmt = $this->pdo->query("SELECT id FROM pos_products");
        $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $results = [];

        foreach ($productIds as $productId) {
            $results[] = $this->syncProduct((int) $productId);
        }

        return $results;
    }

    public function syncProduct(int $productId): array
    {
        $productStmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name
            FROM pos_products p
            LEFT JOIN pos_categories c ON p.category_id = c.id
            WHERE p.id = :id
        ");
        $productStmt->execute([':id' => $productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException("POS product {$productId} not found.");
        }

        if ((int) $product['expose_to_shop'] !== 1) {
            return $this->deactivateCatalogLink($product);
        }

        $this->pdo->beginTransaction();
        try {
            $categoryId = $this->ensureCatalogCategory($product);
            $catalogItemId = $this->upsertCatalogItem($product, $categoryId);
            $this->linkProduct($productId, $catalogItemId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'product_id' => $productId,
            'catalog_item_id' => $catalogItemId,
            'status' => 'synced',
        ];
    }

    private function deactivateCatalogLink(array $product): array
    {
        if (empty($product['catalog_item_id'])) {
            return [
                'product_id' => (int) $product['id'],
                'status' => 'skipped',
                'reason' => 'not_exposed',
            ];
        }

        $stmt = $this->pdo->prepare("
            UPDATE catalog_items
            SET is_active = 0,
                is_sellable = 0,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $product['catalog_item_id']]);

        return [
            'product_id' => (int) $product['id'],
            'catalog_item_id' => (int) $product['catalog_item_id'],
            'status' => 'disabled',
        ];
    }

    private function ensureCatalogCategory(array $product): int
    {
        $categoryName = $product['category_name'] ?: 'POS Merchandise';

        $stmt = $this->pdo->prepare("SELECT id FROM catalog_categories WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $categoryName]);
        $catalogCategoryId = $stmt->fetchColumn();

        if ($catalogCategoryId) {
            return (int) $catalogCategoryId;
        }

        $slug = $this->slugify($categoryName);
        $insert = $this->pdo->prepare("
            INSERT INTO catalog_categories (name, slug, description)
            VALUES (:name, :slug, :description)
        ");
        $insert->execute([
            ':name' => $categoryName,
            ':slug' => $slug,
            ':description' => 'Auto-synced POS merchandise category',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertCatalogItem(array $product, int $categoryId): int
    {
        $catalogItemId = $product['catalog_item_id'] ?? null;

        if (!$catalogItemId) {
            $stmt = $this->pdo->prepare("SELECT id FROM catalog_items WHERE sku = :sku LIMIT 1");
            $stmt->execute([':sku' => $product['sku']]);
            $catalogItemId = $stmt->fetchColumn() ?: null;
        }

        if ($catalogItemId) {
            $update = $this->pdo->prepare("
                UPDATE catalog_items
                SET name = :name,
                    category_id = :category_id,
                    description = :description,
                    cost_price = :cost_price,
                    sell_price = :sell_price,
                    taxable = :taxable,
                    is_purchasable = 1,
                    is_sellable = 1,
                    is_active = :is_active,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                ':name' => $product['name'],
                ':category_id' => $categoryId,
                ':description' => $product['description'],
                ':cost_price' => $product['cost_price'],
                ':sell_price' => $product['unit_price'],
                ':taxable' => $product['tax_rate'] ? 1 : 0,
                ':is_active' => $product['is_active'],
                ':notes' => $product['description'],
                ':id' => $catalogItemId,
            ]);

            return (int) $catalogItemId;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO catalog_items
                (name, sku, item_type, category_id, unit, cost_price, sell_price, taxable, is_purchasable, is_sellable, is_active, notes)
            VALUES
                (:name, :sku, 'product', :category_id, :unit, :cost_price, :sell_price, :taxable, 1, 1, :is_active, :notes)
        ");
        $insert->execute([
            ':name' => $product['name'],
            ':sku' => $product['sku'],
            ':category_id' => $categoryId,
            ':unit' => 'unit',
            ':cost_price' => $product['cost_price'],
            ':sell_price' => $product['unit_price'],
            ':taxable' => $product['tax_rate'] ? 1 : 0,
            ':is_active' => $product['is_active'],
            ':notes' => $product['description'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function linkProduct(int $productId, int $catalogItemId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pos_products
            SET catalog_item_id = :catalog_item_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':catalog_item_id' => $catalogItemId,
            ':id' => $productId,
        ]);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = strtolower(bin2hex(random_bytes(4)));
        }
        return $slug;
    }
}


