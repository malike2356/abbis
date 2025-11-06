<?php
/**
 * CMS Admin - Products Management (WooCommerce-like)
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_image'])) {
    $uploadDir = $rootPath . '/uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['product_image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $file['size'] <= 5000000) { // 5MB
            $filename = uniqid() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $productImage = 'uploads/products/' . $filename;
            }
        }
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
            
            // Check if image column exists and add image if uploaded
            try {
                $pdo->query("SELECT image FROM catalog_items LIMIT 1");
                if (isset($productImage)) {
                    $updateFields[] = 'image';
                    $updateValues[] = $productImage;
                }
            } catch (PDOException $e) {
                // Image column doesn't exist, will be added below
            }
            
            $updateValues[] = $id; // For WHERE clause
            $setClause = implode('=?, ', $updateFields) . '=?';
            $stmt = $pdo->prepare("UPDATE catalog_items SET $setClause WHERE id=?");
            $stmt->execute($updateValues);
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
            $message = 'Product created';
            $id = $pdo->lastInsertId();
        }
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

$products = $pdo->query("SELECT i.*, c.name as category_name FROM catalog_items i LEFT JOIN catalog_categories c ON c.id=i.category_id WHERE i.item_type='product' ORDER BY i.created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM catalog_categories ORDER BY name")->fetchAll();

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <style>
        .product-image-preview { max-width: 200px; max-height: 200px; margin: 10px 0; border: 1px solid #ddd; padding: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        #gjs-editor { min-height: 400px; }
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
        });
    </script>
</head>
<body>
    <?php include 'footer.php'; ?>
    <div class="wrap">
        <h1><?php echo $action === 'edit' ? 'Edit Product' : ($action === 'add' ? 'Add New Product' : 'Products'); ?></h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required class="large-text">
                </div>
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <div style="margin-bottom: 10px;">
                        <button type="button" id="editor-toggle" class="button" style="margin-right: 10px;">
                            <span id="editor-mode-text">Switch to Visual Builder</span>
                        </button>
                        <span class="description" style="display: inline-block; margin-left: 10px;">
                            Choose between Rich Text Editor or Visual Builder
                        </span>
                    </div>
                    <div id="ckeditor-container" style="display: block;">
                        <textarea name="description" id="description-editor" rows="10" class="large-text"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    <div id="grapesjs-container" style="display: none; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; position: relative;">
                        <div style="background: #f6f7f7; padding: 10px; border-bottom: 1px solid #c3c4c7; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600; color: #1e293b;">Visual Builder</span>
                            <button type="button" id="gjs-save-btn" class="button button-primary" style="display: none; margin: 0;">
                                💾 Save & Continue Editing
                            </button>
                        </div>
                        <div id="gjs-editor"></div>
                        <textarea name="grapesjs-content" id="grapesjs-content" style="display: none;"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cost Price (GHS)</label>
                        <input type="number" step="0.01" name="cost_price" value="<?php echo $product['cost_price'] ?? 0; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Selling Price (GHS) *</label>
                        <input type="number" step="0.01" name="sell_price" value="<?php echo $product['sell_price'] ?? 0; ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" step="1" name="stock_quantity" value="<?php echo intval($product['stock_quantity'] ?? 0); ?>" min="0" pattern="[0-9]*" inputmode="numeric">
                </div>
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="product_image" accept="image/*">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?php echo $baseUrl . '/' . htmlspecialchars($product['image']); ?>" class="product-image-preview">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo ($product['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        Active (visible on website)
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_sellable" value="1" <?php echo ($product['is_sellable'] ?? 1) ? 'checked' : ''; ?>>
                        Sellable (available for purchase)
                    </label>
                </div>
                <p class="submit">
                    <input type="submit" name="save_product" class="button button-primary" value="Save Product">
                    <a href="products.php" class="button">Cancel</a>
                </p>
            </form>
        <?php else: ?>
            <a href="?action=add" class="page-title-action">Add New Product</a>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['image'])): ?>
                                    <img src="<?php echo $baseUrl . '/' . htmlspecialchars($p['image']); ?>" style="width:50px; height:50px; object-fit:cover;">
                                <?php else: ?>
                                    <span style="display:inline-block; width:50px; height:50px; background:#ddd; text-align:center; line-height:50px;">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['sku'] ?? '-'); ?></td>
                            <td>GHS <?php echo number_format($p['sell_price'], 2); ?></td>
                            <td><?php echo $p['stock_quantity'] ?? 0; ?></td>
                            <td>
                                <?php if ($p['is_active'] && $p['is_sellable']): ?>
                                    <span class="status-published">Active</span>
                                <?php else: ?>
                                    <span class="status-draft">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?action=edit&id=<?php echo $p['id']; ?>">Edit</a> |
                                <a href="<?php echo $baseUrl; ?>/cms/public/product.php?id=<?php echo $p['id']; ?>" target="_blank">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

