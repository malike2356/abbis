<?php
/**
 * CMS Admin - Products Management (WooCommerce-like)
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/cms/includes/media-helper.php';
require_once $rootPath . '/includes/pos/UnifiedInventoryService.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle file upload
$productImage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = $rootPath . '/uploads/products/';
    $result = handleMediaUpload($_FILES['product_image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5000000);
    if ($result['success']) {
        $productImage = $result['relative_path'];
    }
}

// Ensure catalog_items has description and stock_quantity columns
try {
    $pdo->query("SELECT description FROM catalog_items LIMIT 1");
} catch (PDOException $e) {
    // Add description column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE catalog_items ADD COLUMN description TEXT DEFAULT NULL AFTER name");
    } catch (PDOException $e2) {}
}

try {
    $pdo->query("SELECT stock_quantity FROM catalog_items LIMIT 1");
} catch (PDOException $e) {
    // Add stock_quantity column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE catalog_items ADD COLUMN stock_quantity INT(11) DEFAULT 0 AFTER notes");
    } catch (PDOException $e2) {}
}

// Handle manual sync action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_product'])) {
    $syncId = (int) ($_POST['product_id'] ?? 0);
    if ($syncId) {
        try {
            require_once $rootPath . '/includes/pos/UnifiedCatalogSyncService.php';
            $syncService = new UnifiedCatalogSyncService($pdo);
            $syncService->syncCatalogToPos($syncId);
            $message = 'Product synced to POS successfully';
        } catch (Throwable $e) {
            $message = 'Sync failed: ' . $e->getMessage();
            $messageType = 'error';
            error_log('[CMS Products] Manual sync failed for product ' . $syncId . ': ' . $e->getMessage());
        }
    }
}

// Handle product save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $name = trim($_POST['name'] ?? '');
    $description = $_POST['description'] ?? '';
    
    // Handle GrapesJS content if submitted
    if (isset($_POST['grapesjs-content']) && !empty($_POST['grapesjs-content'])) {
        $description = $_POST['grapesjs-content'];
    }
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $sell_price = floatval($_POST['sell_price'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $category_id = $_POST['category_id'] ?? null;
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_sellable = isset($_POST['is_sellable']) ? 1 : 0;
    
    if ($name && $sell_price > 0) {
        if ($id) {
            // Build UPDATE query dynamically based on available columns
            $updateFields = ['name', 'cost_price', 'sell_price', 'sku', 'category_id', 'is_active', 'is_sellable'];
            $updateValues = [$name, $cost_price, $sell_price, $sku, $category_id ?: null, $is_active, $is_sellable];
            
            // Check if description column exists
            try {
                $pdo->query("SELECT description FROM catalog_items LIMIT 1");
                $updateFields[] = 'description';
                $updateValues[] = $description;
            } catch (PDOException $e) {}
            
            // Check if stock_quantity column exists
            try {
                $pdo->query("SELECT stock_quantity FROM catalog_items LIMIT 1");
                $updateFields[] = 'stock_quantity';
                $updateValues[] = $stock_quantity;
            } catch (PDOException $e) {}
            
            // Check if image column exists and add image if uploaded or removed
            try {
                $pdo->query("SELECT image FROM catalog_items LIMIT 1");
                if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                    // Remove image
                    $updateFields[] = 'image';
                    $updateValues[] = null;
                } elseif (isset($productImage)) {
                    // Update image
                    $updateFields[] = 'image';
                    $updateValues[] = $productImage;
                }
            } catch (PDOException $e) {
                // Image column doesn't exist, will be added below
            }
            
            // Get old stock quantity before update
            $oldStockStmt = $pdo->prepare("SELECT COALESCE(stock_quantity, 0) FROM catalog_items WHERE id = ?");
            $oldStockStmt->execute([$id]);
            $oldStock = (float) ($oldStockStmt->fetchColumn() ?: 0);
            
            $updateValues[] = $id; // For WHERE clause
            $setClause = implode('=?, ', $updateFields) . '=?';
            $stmt = $pdo->prepare("UPDATE catalog_items SET $setClause WHERE id=?");
            $stmt->execute($updateValues);
            
            // Sync product details and inventory to POS
            try {
                require_once $rootPath . '/includes/pos/UnifiedCatalogSyncService.php';
                $syncService = new UnifiedCatalogSyncService($pdo);
                
                // Sync product details (name, price, etc.) to POS
                // This will create/link POS product if it doesn't exist and update all fields
                $syncService->syncCatalogToPos($id);
                
                // Sync inventory if stock_quantity was updated
                if (in_array('stock_quantity', $updateFields)) {
                    $inventoryService = new UnifiedInventoryService($pdo);
                    $newStock = (float) $stock_quantity;
                    $inventoryService->setCatalogStock($id, $newStock, 'CMS product stock update');
                }
            } catch (Throwable $e) {
                error_log('[CMS Products] Sync failed for product ' . $id . ': ' . $e->getMessage());
                error_log('[CMS Products] Stack trace: ' . $e->getTraceAsString());
                // Don't fail the update, just log the error
            }
            
            $message = 'Product updated';
        } else {
            // Build INSERT query dynamically
            $insertFields = ['name', 'cost_price', 'sell_price', 'sku', 'category_id', 'is_active', 'is_sellable', 'item_type'];
            $insertValues = [$name, $cost_price, $sell_price, $sku, $category_id ?: null, $is_active, $is_sellable, 'product'];
            $placeholders = [];
            
            // Check if description column exists
            try {
                $pdo->query("SELECT description FROM catalog_items LIMIT 1");
                $insertFields[] = 'description';
                $insertValues[] = $description;
            } catch (PDOException $e) {}
            
            // Check if stock_quantity column exists
            try {
                $pdo->query("SELECT stock_quantity FROM catalog_items LIMIT 1");
                $insertFields[] = 'stock_quantity';
                $insertValues[] = $stock_quantity;
            } catch (PDOException $e) {}
            
            // Check if image column exists
            try {
                $pdo->query("SELECT image FROM catalog_items LIMIT 1");
                if (isset($productImage)) {
                    $insertFields[] = 'image';
                    $insertValues[] = $productImage;
                }
            } catch (PDOException $e) {}
            
            $placeholders = str_repeat('?,', count($insertFields) - 1) . '?';
            $fieldsList = implode(', ', $insertFields);
            $stmt = $pdo->prepare("INSERT INTO catalog_items ($fieldsList) VALUES ($placeholders)");
            $stmt->execute($insertValues);
            $id = $pdo->lastInsertId();
            
            // Sync product details and inventory to POS
            try {
                require_once $rootPath . '/includes/pos/UnifiedCatalogSyncService.php';
                $syncService = new UnifiedCatalogSyncService($pdo);
                
                // Sync product details to POS
                $syncService->syncCatalogToPos($id);
                
                // Sync inventory if stock_quantity was set
                if (in_array('stock_quantity', $insertFields)) {
                    $inventoryService = new UnifiedInventoryService($pdo);
                    $inventoryService->setCatalogStock($id, (float) $stock_quantity, 'CMS product creation');
                }
            } catch (Throwable $e) {
                error_log('[CMS Products] Sync failed: ' . $e->getMessage());
                // Don't fail the insert, just log the error
            }
            
            $message = 'Product created';
        }
    }
}

// Handle product delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $deleteId = intval($_POST['id'] ?? 0);
    if ($deleteId) {
        try {
            // Check if product is in any orders
            $orderCheck = $pdo->prepare("SELECT COUNT(*) FROM cms_order_items WHERE catalog_item_id=?");
            $orderCheck->execute([$deleteId]);
            $orderCount = $orderCheck->fetchColumn();
            
            if ($orderCount > 0) {
                $message = 'Cannot delete product: It is associated with ' . $orderCount . ' order(s). Consider deactivating instead.';
                $messageType = 'error';
            } else {
                $pdo->prepare("DELETE FROM catalog_items WHERE id=?")->execute([$deleteId]);
                $message = 'Product deleted successfully';
                header('Location: products.php');
                exit;
            }
        } catch (PDOException $e) {
            $message = 'Error deleting product: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle clone action
if (isset($_GET['action']) && $_GET['action'] === 'clone' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM catalog_items WHERE id=?");
    $stmt->execute([$id]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($original) {
        // Generate new SKU
        $newSku = $original['sku'] ? ($original['sku'] . '-COPY-' . time()) : 'SKU-' . time();
        $newName = $original['name'] . ' (Copy)';
        
        // Ensure SKU uniqueness
        $baseSku = $newSku;
        $counter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM catalog_items WHERE sku=? LIMIT 1");
            $checkStmt->execute([$newSku]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $newSku = $baseSku . '-' . $counter;
            $counter++;
        }
        
        // Create clone
        $stmt = $pdo->prepare("INSERT INTO catalog_items (name, description, cost_price, sell_price, sku, category_id, stock_quantity, image, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $newName,
            $original['description'],
            $original['cost_price'],
            $original['sell_price'],
            $newSku,
            $original['category_id'],
            $original['stock_quantity'] ?? 0,
            $original['image'],
            'inactive', // Always clone as inactive
            $original['notes']
        ]);
        
        $newId = $pdo->lastInsertId();
        header('Location: products.php?action=edit&id=' . $newId);
        exit;
    }
}

// Ensure catalog_items has image column
try {
    $pdo->query("SELECT image FROM catalog_items LIMIT 1");
} catch (PDOException $e) {
    // Add image column if it doesn't exist
    try {
        // Try to add after stock_quantity if it exists, otherwise after notes
        $colStmt = $pdo->query("SHOW COLUMNS FROM catalog_items");
        $hasStockQty = false;
        $hasNotes = false;
        while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($col['Field'] === 'stock_quantity') $hasStockQty = true;
            if ($col['Field'] === 'notes') $hasNotes = true;
        }
        
        if ($hasStockQty) {
            $pdo->exec("ALTER TABLE catalog_items ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER stock_quantity");
        } elseif ($hasNotes) {
            $pdo->exec("ALTER TABLE catalog_items ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER notes");
        } else {
            $pdo->exec("ALTER TABLE catalog_items ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        }
    } catch (PDOException $e2) {
        // Column might already exist or there's another issue
    }
}

$product = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM catalog_items WHERE id=?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

$products = $pdo->query("SELECT i.*, c.name as category_name, COALESCE(i.inventory_quantity, i.stock_quantity, 0) as display_stock FROM catalog_items i LEFT JOIN catalog_categories c ON c.id=i.category_id WHERE i.item_type='product' ORDER BY i.created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM catalog_categories ORDER BY name")->fetchAll();

// Calculate statistics
$statsQuery = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active=1 AND is_sellable=1 THEN 1 ELSE 0 END) as active_sellable,
    SUM(CASE WHEN is_active=0 OR is_sellable=0 THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN (COALESCE(inventory_quantity, stock_quantity, 0) = 0) THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN COALESCE(inventory_quantity, stock_quantity, 0) > 0 THEN 1 ELSE 0 END) as in_stock,
    AVG(sell_price) as avg_price,
    SUM(sell_price) as total_value
    FROM catalog_items WHERE item_type='product'");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <style>
        /* Products Hero Section */
        .products-hero {
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 48px 40px;
            margin: -10px -20px 32px -20px;
            overflow: hidden;
            color: white;
        }
        .products-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        .products-hero-content {
            position: relative;
            z-index: 1;
        }
        .products-hero-kicker {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin: 0 0 12px 0;
        }
        .products-hero-content h1 {
            font-size: 42px;
            font-weight: 700;
            margin: 0 0 12px 0;
            color: white;
        }
        .products-hero-content > p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0 0 24px 0;
            max-width: 600px;
        }
        .products-hero-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .products-hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .products-hero-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            color: white;
        }
        .products-hero-btn.primary {
            background: white;
            color: #667eea;
            border-color: white;
        }
        .products-hero-btn.primary:hover {
            background: #f9fafb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .products-hero-actions .view-toggle {
            display: flex;
            gap: 4px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .products-hero-actions .view-btn {
            padding: 8px 12px;
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .products-hero-actions .view-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .products-hero-actions .view-btn.active {
            background: white;
            color: #667eea;
        }

        /* Modern Statistics Cards */
        .products-stats-modern {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 0 0 32px 0;
        }
        .products-stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .products-stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            border-color: #c3c4c7;
        }
        .products-stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .products-stat-content {
            flex: 1;
        }
        .products-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1d2327;
            margin: 0 0 4px 0;
            line-height: 1.2;
        }
        .products-stat-label {
            font-size: 13px;
            color: #646970;
            font-weight: 500;
        }
        .products-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        .products-header-left h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1d2327;
        }
        .products-header-left p {
            margin: 4px 0 0 0;
            font-size: 14px;
            color: #646970;
        }
        .products-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 20px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .stat-card.active { border-left-color: #00a32a; }
        .stat-card.inactive { border-left-color: #dcdcde; }
        .stat-card.stock { border-left-color: #f59e0b; }
        .stat-card.price { border-left-color: #9333ea; }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1d2327;
            margin: 10px 0 5px 0;
        }
        .stat-label {
            color: #646970;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .products-search-filters {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .search-filters-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .search-filters-row > div {
            flex: 1;
            min-width: 200px;
        }
        .search-filters-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #1d2327;
            font-size: 13px;
        }
        .search-filters-row input,
        .search-filters-row select {
            width: 100%;
            padding: 10px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
        }
        .search-filters-row input:focus,
        .search-filters-row select:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }
        .view-toggle {
            display: flex;
            gap: 5px;
            background: #f6f7f7;
            padding: 4px;
            border-radius: 4px;
        }
        .view-toggle button {
            padding: 8px 16px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .view-toggle button.active {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .product-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .product-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
            border-color: #c3c4c7;
        }
        .product-card-image {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .product-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-card-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 48px;
        }
        .product-card-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-out-of-stock {
            background: #fef3c7;
            color: #92400e;
        }
        .product-card-body {
            padding: 20px;
        }
        .product-card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }
        .product-card-category {
            font-size: 13px;
            color: #646970;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .product-card-price {
            font-size: 24px;
            font-weight: 700;
            color: #2271b1;
            margin: 12px 0;
        }
        .product-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-top: 1px solid #f3f4f6;
            margin-top: 12px;
            font-size: 13px;
            color: #646970;
        }
        .product-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f3f4f6;
        }
        .product-card-actions a,
        .product-card-actions button {
            flex: 1;
            padding: 10px 12px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .product-card-actions .button-primary {
            background: #2271b1;
            color: white;
            border-color: #2271b1;
        }
        .product-card-actions .button-primary:hover {
            background: #135e96;
            border-color: #135e96;
            transform: translateY(-1px);
        }
        .product-card-actions .button {
            background: white;
            color: #2271b1;
            border-color: #e5e7eb;
        }
        .product-card-actions .button:hover {
            background: #f6f7f7;
            border-color: #2271b1;
            color: #135e96;
        }
        .product-card-actions button[style*="background: #d63638"] {
            background: #d63638 !important;
            color: white !important;
            border-color: #d63638 !important;
        }
        .product-card-actions button[style*="background: #d63638"]:hover {
            background: #b32d2e !important;
            border-color: #b32d2e !important;
        }
        .product-image-preview { 
            max-width: 200px; 
            max-height: 200px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            padding: 5px; 
            border-radius: 4px;
        }
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 15px; 
        }
        #gjs-editor { 
            min-height: 400px; 
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #646970;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        /* Product Edit Form Styles */
        .product-edit-form {
            margin-top: 20px;
        }
        .product-edit-shell {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            overflow: hidden;
        }
        .product-edit-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 32px;
            color: white;
        }
        .product-hero-primary {
            display: flex;
            gap: 24px;
            align-items: center;
        }
        .product-hero-image {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        .product-hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-hero-image-placeholder {
            font-size: 48px;
            opacity: 0.8;
        }
        .product-hero-meta {
            flex: 1;
        }
        .product-hero-name {
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: white;
        }
        .product-hero-price {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 12px 0;
            opacity: 0.95;
        }
        .product-hero-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-right: 8px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
        }
        .product-hero-pill.stock-in {
            background: rgba(16, 185, 129, 0.3);
        }
        .product-hero-pill.stock-out {
            background: rgba(239, 68, 68, 0.3);
        }
        .product-edit-main-content {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            padding: 24px;
        }
        .product-edit-fields {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .product-section-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
        }
        .product-section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: #1d2327;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        .product-field-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .product-field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .product-field-group.full-width {
            grid-column: 1 / -1;
        }
        .product-field-group label {
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
        }
        .product-field-group input,
        .product-field-group select,
        .product-field-group textarea {
            padding: 10px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .product-field-group input:focus,
        .product-field-group select:focus,
        .product-field-group textarea:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        }
        .product-field-group .description {
            font-size: 13px;
            color: #646970;
            margin: 0;
        }
        .product-edit-sidebar {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .product-side-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
        }
        .product-side-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 16px 0;
            color: #1d2327;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .product-meta-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .product-meta-item:last-child {
            border-bottom: none;
        }
        .product-meta-item .label {
            font-size: 13px;
            color: #646970;
        }
        .product-meta-item span:last-child {
            font-size: 14px;
            color: #1d2327;
            font-weight: 500;
        }
        .product-side-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            color: #1d2327;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .product-side-btn:hover {
            background: #f6f7f7;
            border-color: #2271b1;
            color: #2271b1;
        }
        .product-side-btn.primary {
            background: #2271b1;
            color: white;
            border-color: #2271b1;
        }
        .product-side-btn.primary:hover {
            background: #135e96;
            border-color: #135e96;
        }
        .product-edit-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .product-edit-actions .left {
            font-size: 13px;
            color: #646970;
        }
        .product-edit-actions .right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .product-edit-cancel {
            padding: 10px 20px;
            color: #1d2327;
            text-decoration: none;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .product-edit-cancel:hover {
            background: #f6f7f7;
            border-color: #8c8f94;
        }
        .product-edit-save {
            padding: 10px 24px;
            background: #2271b1;
            color: white;
            border: 1px solid #2271b1;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .product-edit-save:hover {
            background: #135e96;
            border-color: #135e96;
        }
        @media (max-width: 1200px) {
            .product-edit-main-content {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .product-hero-primary {
                flex-direction: column;
                text-align: center;
            }
            .product-field-grid {
                grid-template-columns: 1fr;
            }
            .product-edit-actions {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }
            .product-edit-actions .right {
                flex-direction: column;
            }
            .product-edit-cancel,
            .product-edit-save {
                width: 100%;
                text-align: center;
            }
        }
    </style>
    <!-- CKEditor 5 -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
    <!-- GrapesJS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.5/dist/css/grapes.min.css">
    <script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.5"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-preset-webpage@1.0.3"></script>
    <?php 
    $currentPage = 'products';
    include 'header.php'; 
    ?>
    <script>
        let editorInstance = null;
        let grapesEditor = null;
        let currentMode = 'ckeditor';
        const initialContent = <?php echo json_encode($product['description'] ?? ''); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.querySelector('textarea[name="description"]');
            const toggleBtn = document.getElementById('editor-toggle');
            const ckeditorContainer = document.getElementById('ckeditor-container');
            const grapesjsContainer = document.getElementById('grapesjs-container');
            const grapesjsTextarea = document.getElementById('grapesjs-content');
            const modeText = document.getElementById('editor-mode-text');
            const saveBtn = document.getElementById('gjs-save-btn');
            
            if (contentTextarea && toggleBtn) {
                ClassicEditor.create(contentTextarea, {
                    toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', 'undo', 'redo']
                }).then(editor => {
                    editorInstance = editor;
                    if (initialContent && !initialContent.includes('gjs-')) {
                        editor.setData(initialContent);
                    }
                }).catch(error => console.error('CKEditor error:', error));
                
                function initGrapesJS() {
                    if (grapesEditor) return;
                    grapesEditor = grapesjs.init({
                        container: '#gjs-editor',
                        plugins: ['gjs-preset-webpage'],
                        pluginsOpts: {
                            'gjs-preset-webpage': {
                                blocksBasicOpts: { flexGrid: true }
                            }
                        },
                        height: '400px',
                        width: '100%'
                    });
                    if (initialContent && initialContent.includes('gjs-')) {
                        grapesEditor.setComponents(initialContent);
                    } else if (initialContent) {
                        grapesEditor.setComponents(initialContent);
                    }
                    if (saveBtn) saveBtn.style.display = 'inline-block';
                    let updateTimeout;
                    grapesEditor.on('update', () => {
                        clearTimeout(updateTimeout);
                        updateTimeout = setTimeout(() => {
                            const html = grapesEditor.getHtml();
                            const css = grapesEditor.getCss();
                            const grapesContent = html + '<style>' + css + '</style>';
                            grapesjsTextarea.value = grapesContent;
                            if (contentTextarea) contentTextarea.value = grapesContent;
                            if (saveBtn) {
                                saveBtn.textContent = '💾 Changes Saved';
                                saveBtn.style.background = '#00a32a';
                                setTimeout(() => {
                                    saveBtn.textContent = '💾 Save & Continue Editing';
                                    saveBtn.style.background = '';
                                }, 2000);
                            }
                        }, 500);
                    });
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const html = grapesEditor.getHtml();
                            const css = grapesEditor.getCss();
                            const grapesContent = html + '<style>' + css + '</style>';
                            grapesjsTextarea.value = grapesContent;
                            if (contentTextarea) contentTextarea.value = grapesContent;
                            saveBtn.textContent = '✅ Saved!';
                            saveBtn.style.background = '#00a32a';
                            setTimeout(() => {
                                saveBtn.textContent = '💾 Save & Continue Editing';
                                saveBtn.style.background = '';
                            }, 2000);
                        });
                    }
                }
                
                toggleBtn.addEventListener('click', function() {
                    if (currentMode === 'ckeditor') {
                        currentMode = 'grapesjs';
                        modeText.textContent = 'Switch to Rich Text Editor';
                        ckeditorContainer.style.display = 'none';
                        grapesjsContainer.style.display = 'block';
                        if (editorInstance) {
                            const ckeditorContent = editorInstance.getData();
                            if (contentTextarea) contentTextarea.value = ckeditorContent;
                        }
                        if (!grapesEditor) initGrapesJS();
                    } else {
                        currentMode = 'ckeditor';
                        modeText.textContent = 'Switch to Visual Builder';
                        ckeditorContainer.style.display = 'block';
                        grapesjsContainer.style.display = 'none';
                        if (grapesEditor) {
                            const html = grapesEditor.getHtml();
                            const css = grapesEditor.getCss();
                            const grapesContent = html + '<style>' + css + '</style>';
                            grapesjsTextarea.value = grapesContent;
                            if (contentTextarea) contentTextarea.value = grapesContent;
                            if (editorInstance) editorInstance.setData(grapesContent);
                        }
                    }
                });
                
                const form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        if (currentMode === 'grapesjs' && grapesEditor) {
                            const html = grapesEditor.getHtml();
                            const css = grapesEditor.getCss();
                            grapesjsTextarea.value = html + '<style>' + css + '</style>';
                            if (contentTextarea) contentTextarea.value = html + '<style>' + css + '</style>';
                        } else if (currentMode === 'ckeditor' && editorInstance) {
                            const content = editorInstance.getData();
                            if (contentTextarea) contentTextarea.value = content;
                        }
                    });
                }
            }
            
            // Image preview handler
            window.previewProductImage = function(input) {
                if (input.files && input.files[0]) {
                    // Reset remove flag if new image is uploaded
                    document.getElementById('remove_image').value = '0';
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Update preview in media section
                        let previewImg = document.getElementById('product-image-preview-img');
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                        } else {
                            const previewContainer = document.getElementById('product_image_preview');
                            if (previewContainer) {
                                const img = document.createElement('img');
                                img.id = 'product-image-preview-img';
                                img.src = e.target.result;
                                img.className = 'product-image-preview';
                                img.style.cssText = 'max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid #c3c4c7;';
                                previewContainer.appendChild(img);
                            }
                        }
                        
                        // Update hero image
                        let heroImg = document.getElementById('product-hero-image-preview');
                        const heroPlaceholder = document.getElementById('product-hero-image-placeholder');
                        
                        if (heroImg) {
                            heroImg.src = e.target.result;
                            heroImg.style.display = 'block';
                        } else if (heroPlaceholder) {
                            const heroContainer = heroPlaceholder.parentElement;
                            heroPlaceholder.style.display = 'none';
                            const img = document.createElement('img');
                            img.id = 'product-hero-image-preview';
                            img.src = e.target.result;
                            img.alt = 'Product Image';
                            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                            heroContainer.appendChild(img);
                        }
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            };
            
            // Remove image handler
            window.removeProductImage = function() {
                if (confirm('Are you sure you want to remove this product image?')) {
                    // Set remove flag
                    document.getElementById('remove_image').value = '1';
                    
                    // Clear image input
                    document.getElementById('product_image').value = '';
                    
                    // Remove preview images
                    const previewImg = document.getElementById('product-image-preview-img');
                    if (previewImg) {
                        previewImg.remove();
                    }
                    
                    // Update hero image to placeholder
                    const heroImg = document.getElementById('product-hero-image-preview');
                    const heroPlaceholder = document.getElementById('product-hero-image-placeholder');
                    if (heroImg) {
                        heroImg.remove();
                    }
                    if (heroPlaceholder) {
                        heroPlaceholder.style.display = 'flex';
                    } else {
                        const heroContainer = document.querySelector('.product-hero-image');
                        if (heroContainer) {
                            const placeholder = document.createElement('div');
                            placeholder.className = 'product-hero-image-placeholder';
                            placeholder.id = 'product-hero-image-placeholder';
                            placeholder.textContent = '📦';
                            heroContainer.appendChild(placeholder);
                        }
                    }
                    
                    // Hide remove button
                    const removeBtn = document.querySelector('button[onclick="removeProductImage();"]');
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                    }
                    
                    // Show message
                    const previewContainer = document.getElementById('product_image_preview');
                    if (previewContainer) {
                        const message = document.createElement('div');
                        message.style.cssText = 'padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404; font-size: 14px;';
                        message.textContent = '✓ Image will be removed when you save the product.';
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(message);
                    }
                }
            };
        });
    </script>
</head>
<body>
    <?php include 'footer.php'; ?>
    <div class="wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="margin: 0;">
                <?php echo $action === 'edit' ? '✏️ Edit Product' : ($action === 'add' ? '➕ Add New Product' : '🛍️ Products'); ?>
                <?php if ($action === 'edit' && $product): ?>
                    <a href="?action=clone&id=<?php echo $id; ?>" class="button" onclick="return confirm('This will create a copy of this product. Continue?');" style="background: #f0f0f1; color: #1d2327; border-color: #c3c4c7; margin-left: 12px; font-size: 14px; padding: 6px 12px;">📋 Clone</a>
                <?php endif; ?>
            </h1>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-<?php echo (isset($messageType) && $messageType === 'error') ? 'error' : 'success'; ?>">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <?php
            $isEditing = $action === 'edit';
            $productName = $product['name'] ?? 'New Product';
            $productPrice = $product['sell_price'] ?? 0;
            $productStock = (float) ($product['display_stock'] ?? $product['inventory_quantity'] ?? $product['stock_quantity'] ?? 0);
            $productImage = !empty($product['image']) ? $baseUrl . '/' . htmlspecialchars($product['image']) : '';
            $createdAt = $product['created_at'] ?? 'N/A';
            $categoryName = null;
            if (!empty($product['category_id'])) {
                foreach ($categories as $cat) {
                    if ($cat['id'] == $product['category_id']) {
                        $categoryName = $cat['name'];
                        break;
                    }
                }
            }
            ?>
            <form method="post" enctype="multipart/form-data" class="product-edit-form">
                <div class="product-edit-shell">
                    <!-- Hero Header -->
                    <section class="product-edit-hero">
                        <div class="product-hero-primary">
                            <div class="product-hero-image">
                                <?php if ($productImage): ?>
                                    <img src="<?php echo $productImage; ?>" alt="Product Image" id="product-hero-image-preview">
                                <?php else: ?>
                                    <div class="product-hero-image-placeholder" id="product-hero-image-placeholder">
                                        📦
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="product-hero-meta">
                                <h1 class="product-hero-name" id="product-name-preview"><?php echo htmlspecialchars($productName); ?></h1>
                                <p class="product-hero-price">GHS <?php echo number_format($productPrice, 2); ?></p>
                                <?php if ($categoryName): ?>
                                    <span class="product-hero-pill category">📁 <?php echo htmlspecialchars($categoryName); ?></span>
                                <?php endif; ?>
                                <?php if ($isEditing): ?>
                                    <span class="product-hero-pill stock-<?php echo $productStock > 0 ? 'in' : 'out'; ?>">
                                        <?php echo $productStock > 0 ? '✅ In Stock (' . $productStock . ')' : '❌ Out of Stock'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <div class="product-edit-main-content">
                        <div class="product-edit-fields">
                            <!-- Basic Information -->
                            <div class="product-section-card">
                                <h2 class="product-section-title">📝 Basic Information</h2>
                                <div class="product-field-grid">
                                    <div class="product-field-group full-width">
                                        <label for="name">Product Name *</label>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required oninput="document.getElementById('product-name-preview').textContent = this.value || 'New Product';">
                                        <p class="description">The name of your product as it will appear on your website.</p>
                                    </div>
                                    <div class="product-field-group">
                                        <label for="sku">SKU (Stock Keeping Unit)</label>
                                        <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" placeholder="e.g., PROD-001">
                                        <p class="description">A unique identifier for this product.</p>
                                    </div>
                                    <div class="product-field-group">
                                        <label for="category_id">Category</label>
                                        <select id="category_id" name="category_id">
                                            <option value="">No Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="product-section-card">
                                <h2 class="product-section-title">📄 Description</h2>
                                <div class="product-field-group full-width">
                                    <div style="margin-bottom: 12px; display: flex; gap: 8px; align-items: center;">
                                        <button type="button" id="editor-toggle" class="button" style="margin: 0;">
                                            <span id="editor-mode-text">Switch to Visual Builder</span>
                                        </button>
                                        <span class="description" style="margin: 0;">
                                            Choose between Rich Text Editor or Visual Builder
                                        </span>
                                    </div>
                                    <div id="ckeditor-container" style="display: block;">
                                        <textarea name="description" id="description-editor" rows="10" class="large-text"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div id="grapesjs-container" style="display: none; border: 1px solid #c3c4c7; border-radius: 8px; overflow: hidden; position: relative;">
                                        <div style="background: #f6f7f7; padding: 12px; border-bottom: 1px solid #c3c4c7; display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-weight: 600; color: #1e293b;">Visual Builder</span>
                                            <button type="button" id="gjs-save-btn" class="button button-primary" style="display: none; margin: 0;">
                                                💾 Save & Continue Editing
                                            </button>
                                        </div>
                                        <div id="gjs-editor"></div>
                                        <textarea name="grapesjs-content" id="grapesjs-content" style="display: none;"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Pricing -->
                            <div class="product-section-card">
                                <h2 class="product-section-title">💰 Pricing</h2>
                                <div class="product-field-grid">
                                    <div class="product-field-group">
                                        <label for="cost_price">Cost Price (GHS)</label>
                                        <input type="number" step="0.01" id="cost_price" name="cost_price" value="<?php echo $product['cost_price'] ?? 0; ?>" min="0">
                                        <p class="description">The cost you paid for this product.</p>
                                    </div>
                                    <div class="product-field-group">
                                        <label for="sell_price">Selling Price (GHS) *</label>
                                        <input type="number" step="0.01" id="sell_price" name="sell_price" value="<?php echo $product['sell_price'] ?? 0; ?>" required min="0" oninput="document.querySelector('.product-hero-price').textContent = 'GHS ' + parseFloat(this.value || 0).toFixed(2);">
                                        <p class="description">The price customers will pay.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Inventory -->
                            <div class="product-section-card">
                                <h2 class="product-section-title">📦 Inventory</h2>
                                <div class="product-field-group">
                                    <label for="stock_quantity">Stock Quantity</label>
                                    <input type="number" step="1" id="stock_quantity" name="stock_quantity" value="<?php echo intval($product['stock_quantity'] ?? 0); ?>" min="0" pattern="[0-9]*" inputmode="numeric">
                                    <p class="description">Current stock level. This syncs to POS and ABBIS inventory.</p>
                                </div>
                            </div>

                            <!-- Media -->
                            <div class="product-section-card">
                                <h2 class="product-section-title">🖼️ Product Image</h2>
                                <div class="product-field-group full-width">
                                    <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                                        <input type="text" name="image" id="product_image" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>" placeholder="Image URL or path" style="flex: 1; min-width: 200px;">
                                        <button type="button" class="button" onclick="openMediaPicker({
                                            targetInput: '#product_image',
                                            targetPreview: '#product_image_preview',
                                            allowedTypes: ['image'],
                                            baseUrl: '<?php echo $baseUrl; ?>'
                                        }); return false;">📁 Select from Media Library</button>
                                        <?php if (!empty($product['image'])): ?>
                                            <button type="button" class="button" onclick="removeProductImage();" style="background: #d63638; color: white; border-color: #d63638;">
                                                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                                                Remove Image
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="product_image" accept="image/*" style="margin-bottom: 8px;" onchange="previewProductImage(this);">
                                    <input type="hidden" name="remove_image" id="remove_image" value="0">
                                    <p class="description">Enter an image URL, select from media library, or upload a new image. Recommended size: 800x800px.</p>
                                    <div id="product_image_preview" style="margin-top: 16px;">
                                        <?php if (!empty($product['image'])): ?>
                                            <div style="position: relative; display: inline-block;">
                                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($product['image']); ?>" class="product-image-preview" style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid #c3c4c7;" onerror="this.style.display='none'" id="product-image-preview-img">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="product-section-card">
                                <h2 class="product-section-title">⚙️ Status & Visibility</h2>
                                <div class="product-field-group">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="is_active" value="1" <?php echo ($product['is_active'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                                        <span><strong>Active</strong> - Visible on website</span>
                                    </label>
                                    <p class="description">Inactive products are hidden from customers.</p>
                                </div>
                                <div class="product-field-group">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="is_sellable" value="1" <?php echo ($product['is_sellable'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                                        <span><strong>Sellable</strong> - Available for purchase</span>
                                    </label>
                                    <p class="description">Non-sellable products cannot be added to cart.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <aside class="product-edit-sidebar">
                            <?php if ($isEditing): ?>
                                <div class="product-side-card">
                                    <h3>Product Info</h3>
                                    <div class="product-meta-item">
                                        <span class="label">Created</span>
                                        <span><?php echo date('M j, Y H:i', strtotime($createdAt)); ?></span>
                                    </div>
                                    <div class="product-meta-item">
                                        <span class="label">Product ID</span>
                                        <span><strong>#<?php echo $id; ?></strong></span>
                                    </div>
                                    <?php if ($productStock > 0): ?>
                                        <div class="product-meta-item">
                                            <span class="label">Current Stock</span>
                                            <span><strong style="color: #00a32a;"><?php echo number_format($productStock); ?></strong></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="product-side-card">
                                <h3>Quick Actions</h3>
                                <?php if ($isEditing): ?>
                                    <a href="<?php echo $baseUrl; ?>/cms/public/product.php?id=<?php echo $id; ?>" target="_blank" class="product-side-btn primary">
                                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                        View on Website
                                    </a>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                        <button type="submit" name="sync_product" class="product-side-btn" style="width: 100%; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-bottom: 8px; color: #1d2327; text-decoration: none; font-size: 14px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                                            Sync to POS & ABBIS
                                        </button>
                                    </form>
                                    <a href="?action=clone&id=<?php echo $id; ?>" class="product-side-btn" onclick="return confirm('This will create a copy of this product. Continue?');">
                                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        Duplicate Product
                                    </a>
                                <?php endif; ?>
                                <a href="products.php" class="product-side-btn">
                                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                                    Back to Products
                                </a>
                            </div>
                        </aside>
                    </div>

                    <!-- Form Actions -->
                    <div class="product-edit-actions">
                        <div class="left">
                            <?php echo $isEditing ? 'Last updated automatically when you save.' : 'All fields marked * are required to create the product.'; ?>
                        </div>
                        <div class="right">
                            <a href="products.php" class="product-edit-cancel">Cancel</a>
                            <button type="submit" name="save_product" class="product-edit-save"><?php echo $isEditing ? 'Update Product' : 'Create Product'; ?></button>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <!-- Hero Header -->
            <div class="products-hero">
                <div class="products-hero-overlay"></div>
                <div class="products-hero-content">
                    <p class="products-hero-kicker">E-Commerce Management</p>
                    <h1>🛍️ Products</h1>
                    <p>Manage your product catalog, inventory, and pricing. Create, edit, and organize products for your online store.</p>
                    <div class="products-hero-actions">
                        <a href="?action=add" class="products-hero-btn primary">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Add New Product
                        </a>
                        <div class="view-toggle">
                            <button class="view-btn active" onclick="setView('grid')" data-view="grid" title="Grid View">
                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            </button>
                            <button class="view-btn" onclick="setView('table')" data-view="table" title="Table View">
                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="products-stats-modern">
                <div class="products-stat-card">
                    <div class="products-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    </div>
                    <div class="products-stat-content">
                        <div class="products-stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
                        <div class="products-stat-label">Total Products</div>
                    </div>
                </div>
                <div class="products-stat-card">
                    <div class="products-stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <div class="products-stat-content">
                        <div class="products-stat-value"><?php echo number_format($stats['active_sellable'] ?? 0); ?></div>
                        <div class="products-stat-label">Active & Sellable</div>
                    </div>
                </div>
                <div class="products-stat-card">
                    <div class="products-stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    </div>
                    <div class="products-stat-content">
                        <div class="products-stat-value"><?php echo number_format($stats['in_stock'] ?? 0); ?></div>
                        <div class="products-stat-label">In Stock</div>
                    </div>
                </div>
                <div class="products-stat-card">
                    <div class="products-stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <div class="products-stat-content">
                        <div class="products-stat-value"><?php echo number_format($stats['out_of_stock'] ?? 0); ?></div>
                        <div class="products-stat-label">Out of Stock</div>
                    </div>
                </div>
                <div class="products-stat-card">
                    <div class="products-stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                    <div class="products-stat-content">
                        <div class="products-stat-value">GHS <?php echo number_format($stats['avg_price'] ?? 0, 2); ?></div>
                        <div class="products-stat-label">Average Price</div>
                    </div>
                </div>
            </div>
            
            <!-- Header with Actions -->
            <div class="products-header-modern">
                <div class="products-header-left">
                    <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #1d2327;">All Products</h2>
                    <p style="margin: 4px 0 0 0; font-size: 14px; color: #646970;"><?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?> in catalog</p>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="products-search-filters">
                <form method="get" class="search-filters-row">
                    <div>
                        <label>Search Products</label>
                        <input type="text" name="s" value="<?php echo htmlspecialchars($_GET['s'] ?? ''); ?>" placeholder="Search by name, SKU..." id="productSearch" onkeyup="filterProducts()">
                    </div>
                    <div>
                        <label>Filter by Category</label>
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Filter by Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active & Sellable</option>
                            <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="out_of_stock" <?php echo (isset($_GET['status']) && $_GET['status'] == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <button type="submit" class="button button-primary">🔍 Search</button>
                        <?php if (isset($_GET['s']) || isset($_GET['category']) || isset($_GET['status'])): ?>
                            <a href="products.php" class="button">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Grid View -->
            <div id="grid-view">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🛍️</div>
                        <h3>No Products Found</h3>
                        <p>Get started by creating your first product.</p>
                        <a href="?action=add" class="button button-primary" style="margin-top: 20px; display: inline-block;">Create Product</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $p): ?>
                            <div class="product-card" 
                                 data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>"
                                 data-sku="<?php echo strtolower(htmlspecialchars($p['sku'] ?? '')); ?>"
                                 data-category="<?php echo strtolower(htmlspecialchars($p['category_name'] ?? '')); ?>">
                                <div class="product-card-image">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?php echo $baseUrl . '/' . htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                                    <?php else: ?>
                                        <div class="product-card-image-placeholder">📦</div>
                                    <?php endif; ?>
                                    <?php 
                                    $stockQty = (float) ($p['display_stock'] ?? $p['inventory_quantity'] ?? $p['stock_quantity'] ?? 0);
                                    $isActive = $p['is_active'] ?? 0;
                                    $isSellable = $p['is_sellable'] ?? 0;
                                    ?>
                                    <?php if ($stockQty <= 0): ?>
                                        <span class="product-card-badge badge-out-of-stock">Out of Stock</span>
                                    <?php elseif ($isActive && $isSellable): ?>
                                        <span class="product-card-badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="product-card-badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card-body">
                                    <h3 class="product-card-title"><?php echo htmlspecialchars($p['name']); ?></h3>
                                    <?php if (!empty($p['category_name'])): ?>
                                        <div class="product-card-category">📁 <?php echo htmlspecialchars($p['category_name']); ?></div>
                                    <?php endif; ?>
                                    <div class="product-card-price">GHS <?php echo number_format($p['sell_price'], 2); ?></div>
                                    <div class="product-card-meta">
                                        <span><strong>SKU:</strong> <?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?></span>
                                        <span><strong>Stock:</strong> <?php echo $stockQty; ?></span>
                                    </div>
                                    <div class="product-card-actions">
                                        <a href="?action=edit&id=<?php echo $p['id']; ?>" class="button-primary">✏️ Edit</a>
                                        <a href="<?php echo $baseUrl; ?>/cms/public/product.php?id=<?php echo $p['id']; ?>" target="_blank" class="button">👁️ View</a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="delete_product" class="button" style="background: #d63638; color: white; border-color: #d63638;">🗑️ Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Table View -->
            <div id="table-view" style="display: none;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 60px 20px; color: #646970;">
                                    <div class="empty-state-icon" style="font-size: 48px; margin-bottom: 15px;">🛍️</div>
                                    <h3 style="margin: 0 0 10px 0; color: #1d2327;">No Products Found</h3>
                                    <p style="margin: 0;">No products match your search criteria.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($p['image'])): ?>
                                            <img src="<?php echo $baseUrl . '/' . htmlspecialchars($p['image']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div style="display:inline-block; width:50px; height:50px; background:#f6f7f7; text-align:center; line-height:50px; border-radius: 4px; color: #9ca3af;">📦</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['sku'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                                    <td><strong style="color: #2271b1;">GHS <?php echo number_format($p['sell_price'], 2); ?></strong></td>
                                    <td>
                                        <?php 
                                        $tableStockQty = (float) ($p['display_stock'] ?? $p['inventory_quantity'] ?? $p['stock_quantity'] ?? 0);
                                        ?>
                                        <span style="color: <?php echo $tableStockQty > 0 ? '#00a32a' : '#d63638'; ?>; font-weight: 600;">
                                            <?php echo number_format($tableStockQty, 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $stockQty = (float) ($p['display_stock'] ?? $p['inventory_quantity'] ?? $p['stock_quantity'] ?? 0);
                                        $isActive = $p['is_active'] ?? 0;
                                        $isSellable = $p['is_sellable'] ?? 0;
                                        ?>
                                        <?php if ($stockQty <= 0): ?>
                                            <span class="badge-out-of-stock product-card-badge">Out of Stock</span>
                                        <?php elseif ($isActive && $isSellable): ?>
                                            <span class="badge-active product-card-badge">Active</span>
                                        <?php else: ?>
                                            <span class="badge-inactive product-card-badge">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="?action=edit&id=<?php echo $p['id']; ?>" class="button button-small">Edit</a>
                                            <a href="<?php echo $baseUrl; ?>/cms/public/product.php?id=<?php echo $p['id']; ?>" target="_blank" class="button button-small">View</a>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" name="delete_product" class="button button-small" style="background: #d63638; color: white; border-color: #d63638;">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                function setView(view) {
                    localStorage.setItem('productsView', view);
                    const gridView = document.getElementById('grid-view');
                    const tableView = document.getElementById('table-view');
                    const buttons = document.querySelectorAll('.view-btn');
                    
                    buttons.forEach(btn => {
                        btn.classList.remove('active');
                        if (btn.getAttribute('data-view') === view) {
                            btn.classList.add('active');
                        }
                    });
                    
                    if (view === 'grid') {
                        gridView.style.display = 'block';
                        tableView.style.display = 'none';
                    } else {
                        gridView.style.display = 'none';
                        tableView.style.display = 'block';
                    }
                }
                
                // Load saved view preference
                const savedView = localStorage.getItem('productsView') || 'grid';
                if (savedView) {
                    setView(savedView);
                }
                
                function filterProducts() {
                    var input = document.getElementById('productSearch');
                    var filter = input.value.toLowerCase();
                    var cards = document.querySelectorAll('.product-card');
                    
                    cards.forEach(function(card) {
                        var name = card.getAttribute('data-name');
                        var sku = card.getAttribute('data-sku');
                        var category = card.getAttribute('data-category');
                        
                        if (name.indexOf(filter) > -1 || sku.indexOf(filter) > -1 || category.indexOf(filter) > -1) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>

