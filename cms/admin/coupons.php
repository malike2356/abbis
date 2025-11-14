<?php
/**
 * CMS Admin - Enhanced Coupons Management
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

// Ensure coupons table exists
try {
    $pdo->query("SELECT * FROM cms_coupons LIMIT 1");
} catch (PDOException $e) {
    // Run migration
    $migrationSQL = file_get_contents($rootPath . '/database/cms_ecommerce_enhancement.sql');
    $statements = explode(';', $migrationSQL);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e2) {}
        }
    }
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = null;
$messageType = 'success';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'bulk_action':
            $bulkAction = $_POST['bulk_action'] ?? '';
            $couponIds = $_POST['coupon_ids'] ?? [];
            
            if (empty($couponIds)) {
                echo json_encode(['success' => false, 'error' => 'No coupons selected']);
                exit;
            }
            
            $placeholders = implode(',', array_fill(0, count($couponIds), '?'));
            
            try {
                if ($bulkAction === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM cms_coupons WHERE id IN ($placeholders)");
                    $stmt->execute($couponIds);
                    echo json_encode(['success' => true, 'message' => count($couponIds) . ' coupon(s) deleted']);
                } elseif ($bulkAction === 'activate') {
                    $stmt = $pdo->prepare("UPDATE cms_coupons SET is_active=1 WHERE id IN ($placeholders)");
                    $stmt->execute($couponIds);
                    echo json_encode(['success' => true, 'message' => count($couponIds) . ' coupon(s) activated']);
                } elseif ($bulkAction === 'deactivate') {
                    $stmt = $pdo->prepare("UPDATE cms_coupons SET is_active=0 WHERE id IN ($placeholders)");
                    $stmt->execute($couponIds);
                    echo json_encode(['success' => true, 'message' => count($couponIds) . ' coupon(s) deactivated']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'duplicate':
            $couponId = intval($_POST['coupon_id'] ?? 0);
            if (!$couponId) {
                echo json_encode(['success' => false, 'error' => 'Invalid coupon ID']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM cms_coupons WHERE id=?");
                $stmt->execute([$couponId]);
                $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$coupon) {
                    echo json_encode(['success' => false, 'error' => 'Coupon not found']);
                    exit;
                }
                
                // Generate new code
                $newCode = $coupon['code'] . '-COPY-' . time();
                $stmt = $pdo->prepare("INSERT INTO cms_coupons (code, description, discount_type, discount_amount, minimum_amount, maximum_amount, usage_limit, expiry_date, is_active) VALUES (?,?,?,?,?,?,?,?,0)");
                $stmt->execute([
                    $newCode,
                    $coupon['description'],
                    $coupon['discount_type'],
                    $coupon['discount_amount'],
                    $coupon['minimum_amount'],
                    $coupon['maximum_amount'],
                    $coupon['usage_limit'],
                    $coupon['expiry_date']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Coupon duplicated', 'id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'toggle_status':
            $couponId = intval($_POST['coupon_id'] ?? 0);
            if (!$couponId) {
                echo json_encode(['success' => false, 'error' => 'Invalid coupon ID']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE cms_coupons SET is_active = NOT is_active WHERE id=?");
                $stmt->execute([$couponId]);
                $stmt = $pdo->prepare("SELECT is_active FROM cms_coupons WHERE id=?");
                $stmt->execute([$couponId]);
                $isActive = $stmt->fetchColumn();
                echo json_encode(['success' => true, 'is_active' => (bool)$isActive]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Handle coupon save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountAmount = floatval($_POST['discount_amount'] ?? 0);
    $minimumAmount = !empty($_POST['minimum_amount']) ? floatval($_POST['minimum_amount']) : null;
    $maximumAmount = !empty($_POST['maximum_amount']) ? floatval($_POST['maximum_amount']) : null;
    $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($code)) {
        $message = 'Error: Coupon code is required';
        $messageType = 'error';
    } elseif ($discountAmount <= 0) {
        $message = 'Error: Discount amount must be greater than 0';
        $messageType = 'error';
    } elseif ($discountType === 'percentage' && $discountAmount > 100) {
        $message = 'Error: Percentage discount cannot exceed 100%';
        $messageType = 'error';
    } else {
        // Check for duplicate code (if creating new or updating with different code)
        $checkStmt = $pdo->prepare("SELECT id FROM cms_coupons WHERE code=? AND id!=?");
        $checkStmt->execute([$code, $id ?: 0]);
        if ($checkStmt->fetch()) {
            $message = 'Error: Coupon code already exists';
            $messageType = 'error';
        } else {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE cms_coupons SET code=?, description=?, discount_type=?, discount_amount=?, minimum_amount=?, maximum_amount=?, usage_limit=?, expiry_date=?, is_active=? WHERE id=?");
                $stmt->execute([$code, $description, $discountType, $discountAmount, $minimumAmount, $maximumAmount, $usageLimit, $expiryDate, $isActive, $id]);
                $message = 'Coupon updated successfully';
            } else {
                $stmt = $pdo->prepare("INSERT INTO cms_coupons (code, description, discount_type, discount_amount, minimum_amount, maximum_amount, usage_limit, expiry_date, is_active) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$code, $description, $discountType, $discountAmount, $minimumAmount, $maximumAmount, $usageLimit, $expiryDate, $isActive]);
                $message = 'Coupon created successfully';
                $id = $pdo->lastInsertId();
                $action = 'edit';
            }
        }
    }
}

// Handle coupon delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $deleteId = intval($_POST['id'] ?? 0);
    if ($deleteId) {
        $pdo->prepare("DELETE FROM cms_coupons WHERE id=?")->execute([$deleteId]);
        $message = 'Coupon deleted successfully';
        header('Location: coupons.php');
        exit;
    }
}

$coupon = null;
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_coupons WHERE id=?");
    $stmt->execute([$id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($filterStatus !== 'all') {
    if ($filterStatus === 'active') {
        $whereConditions[] = "is_active = 1";
    } elseif ($filterStatus === 'inactive') {
        $whereConditions[] = "is_active = 0";
    } elseif ($filterStatus === 'expired') {
        $whereConditions[] = "expiry_date IS NOT NULL AND expiry_date < CURDATE()";
    } elseif ($filterStatus === 'expiring_soon') {
        $whereConditions[] = "expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
}

if ($filterType !== 'all') {
    $whereConditions[] = "discount_type = ?";
    $params[] = $filterType;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(code LIKE ? OR description LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$coupons = $pdo->prepare("SELECT * FROM cms_coupons $whereClause ORDER BY created_at DESC");
$coupons->execute($params);
$coupons = $coupons->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM cms_coupons")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM cms_coupons WHERE is_active = 1")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM cms_coupons WHERE is_active = 0")->fetchColumn(),
    'expired' => $pdo->query("SELECT COUNT(*) FROM cms_coupons WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetchColumn(),
    'total_used' => $pdo->query("SELECT SUM(used_count) FROM cms_coupons")->fetchColumn() ?: 0,
    'total_savings' => $pdo->query("SELECT SUM(discount_amount * used_count) FROM cms_coupons WHERE discount_type = 'fixed'")->fetchColumn() ?: 0
];

require_once dirname(__DIR__) . '/public/get-site-name.php';
$companyName = getCMSSiteName('CMS Admin');
$baseUrl = app_url();
$currentPage = 'coupons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php include 'header.php'; ?>
    <style>
        .coupon-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            color: #2563eb;
            margin: 8px 0;
        }
        
        .stat-card-label {
            font-size: 14px;
            color: #646970;
            font-weight: 600;
        }
        
        .coupon-filters {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .coupon-filters input,
        .coupon-filters select {
            padding: 10px 12px;
            border: 2px solid #c3c4c7;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .coupon-filters input[type="search"] {
            flex: 1;
            min-width: 250px;
        }
        
        .coupon-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 16px;
            color: #2563eb;
            background: rgba(37, 99, 235, 0.1);
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .coupon-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .badge-expired {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-expiring {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-percentage {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-fixed {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .usage-bar {
            width: 100%;
            height: 8px;
            background: #f3f4f6;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        
        .usage-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
            transition: width 0.3s ease;
        }
        
        .usage-bar-fill.warning {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }
        
        .usage-bar-fill.danger {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }
        
        .quick-actions {
            display: flex;
            gap: 8px;
        }
        
        .quick-action-btn {
            padding: 6px 12px;
            border: 1px solid #c3c4c7;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .quick-action-btn:hover {
            background: #f6f7f7;
            border-color: #2563eb;
        }
        
        .bulk-actions-bar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 12px;
        }
        
        .bulk-actions-bar.active {
            display: flex;
        }
        
        .discount-preview {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }
        
        .discount-preview h4 {
            margin: 0 0 12px 0;
            color: #0ea5e9;
        }
        
        .preview-example {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #bae6fd;
        }
        
        .preview-example:last-child {
            border-bottom: none;
        }
        
        .copy-code-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 8px;
            transition: all 0.2s ease;
        }
        
        .copy-code-btn:hover {
            background: #1e40af;
        }
        
        .copy-code-btn.copied {
            background: #10b981;
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <!-- Page Header -->
        <div class="admin-page-header">
            <h1>🎫 Coupon Management</h1>
            <p>
                Create and manage discount coupons for your e-commerce store. Track usage, set limits, and analyze coupon performance.
            </p>
        </div>
        
        <?php if ($message): ?>
            <div class="admin-notice admin-notice-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>" style="margin-bottom: 24px;">
                <div class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠️' : '✅'; ?></div>
                <div class="admin-notice-content"><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <!-- Coupon Form -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><?php echo $action === 'edit' ? '✏️ Edit Coupon' : '➕ Add New Coupon'; ?></h2>
                </div>
                
                <form method="post" class="admin-form" id="coupon-form">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div>
                            <div class="admin-form-group">
                                <label>Coupon Code <span style="color: #d63638;">*</span></label>
                                <input type="text" name="code" id="coupon-code" value="<?php echo htmlspecialchars($coupon['code'] ?? ''); ?>" required style="text-transform: uppercase; font-family: 'Courier New', monospace; font-weight: 700; font-size: 16px;">
                                <div class="admin-form-help">Customers will enter this code at checkout. Must be unique.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Description</label>
                                <textarea name="description" rows="3"><?php echo htmlspecialchars($coupon['description'] ?? ''); ?></textarea>
                                <div class="admin-form-help">Internal description for your reference.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Discount Type <span style="color: #d63638;">*</span></label>
                                <select name="discount_type" id="discount-type" required>
                                    <option value="percentage" <?php echo ($coupon['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : ''; ?>>Percentage discount (%)</option>
                                    <option value="fixed" <?php echo ($coupon['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed cart discount (GHS)</option>
                                </select>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Discount Amount <span style="color: #d63638;">*</span></label>
                                <input type="number" name="discount_amount" id="discount-amount" value="<?php echo htmlspecialchars($coupon['discount_amount'] ?? '0'); ?>" step="0.01" min="0" max="100" required>
                                <div class="admin-form-help">
                                    <span id="discount-help">Enter percentage (e.g., 10 for 10%)</span>
                                </div>
                            </div>
                            
                            <!-- Discount Preview -->
                            <div class="discount-preview" id="discount-preview" style="display: none;">
                                <h4>💡 Discount Preview</h4>
                                <div class="preview-example">
                                    <span>Cart Total: GHS 100.00</span>
                                    <span id="preview-discount">- GHS 0.00</span>
                                </div>
                                <div class="preview-example">
                                    <strong>Final Total:</strong>
                                    <strong id="preview-total">GHS 100.00</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="admin-form-group">
                                <label>Minimum Spend (GHS)</label>
                                <input type="number" name="minimum_amount" value="<?php echo htmlspecialchars($coupon['minimum_amount'] ?? ''); ?>" step="0.01" min="0">
                                <div class="admin-form-help">Minimum order amount required to use this coupon.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Maximum Spend (GHS)</label>
                                <input type="number" name="maximum_amount" value="<?php echo htmlspecialchars($coupon['maximum_amount'] ?? ''); ?>" step="0.01" min="0">
                                <div class="admin-form-help">Maximum order amount allowed when using this coupon.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Usage Limit</label>
                                <input type="number" name="usage_limit" value="<?php echo htmlspecialchars($coupon['usage_limit'] ?? ''); ?>" min="1">
                                <div class="admin-form-help">How many times this coupon can be used. Leave empty for unlimited.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Expiry Date</label>
                                <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($coupon['expiry_date'] ?? ''); ?>">
                                <div class="admin-form-help">Coupon expiry date. Leave empty for no expiry.</div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="is_active" value="1" <?php echo ($coupon['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                    <span>Enable this coupon</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #f0f0f1; display: flex; gap: 12px;">
                        <button type="submit" name="save_coupon" class="admin-btn admin-btn-primary">
                            <span>💾</span> Save Coupon
                        </button>
                        <a href="coupons.php" class="admin-btn admin-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="coupon-stats">
                <div class="stat-card">
                    <div class="stat-card-label">Total Coupons</div>
                    <div class="stat-card-value"><?php echo number_format($stats['total']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Active Coupons</div>
                    <div class="stat-card-value" style="color: #10b981;"><?php echo number_format($stats['active']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Inactive Coupons</div>
                    <div class="stat-card-value" style="color: #6b7280;"><?php echo number_format($stats['inactive']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Expired Coupons</div>
                    <div class="stat-card-value" style="color: #ef4444;"><?php echo number_format($stats['expired']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Total Times Used</div>
                    <div class="stat-card-value" style="color: #f59e0b;"><?php echo number_format($stats['total_used']); ?></div>
                </div>
            </div>
            
            <!-- Filters and Actions -->
            <div class="coupon-filters">
                <input type="search" id="search-input" placeholder="🔍 Search coupons by code or description..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select id="filter-status">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="expired" <?php echo $filterStatus === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="expiring_soon" <?php echo $filterStatus === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                </select>
                <select id="filter-type">
                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="percentage" <?php echo $filterType === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                    <option value="fixed" <?php echo $filterType === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                </select>
                <a href="?action=add" class="admin-btn admin-btn-primary">
                    <span>➕</span> Add New Coupon
                </a>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="bulk-actions-bar">
                <strong id="selected-count">0</strong> coupon(s) selected
                <select id="bulk-action-select" class="admin-form-group select" style="margin: 0;">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" id="apply-bulk-action" class="admin-btn admin-btn-primary">Apply</button>
                <button type="button" id="clear-selection" class="admin-btn admin-btn-outline">Clear</button>
            </div>
            
            <!-- Coupons Table -->
            <?php if (empty($coupons)): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-state-icon">🎫</div>
                    <h3>No coupons found</h3>
                    <p><?php echo !empty($searchQuery) || $filterStatus !== 'all' || $filterType !== 'all' ? 'Try adjusting your filters.' : 'Create your first coupon to get started.'; ?></p>
                    <?php if (empty($searchQuery) && $filterStatus === 'all' && $filterType === 'all'): ?>
                        <a href="?action=add" class="admin-btn admin-btn-primary" style="margin-top: 16px;">
                            <span>➕</span> Create Your First Coupon
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all">
                                    </th>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Discount</th>
                                    <th>Usage</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $c): 
                                    $isExpired = $c['expiry_date'] && strtotime($c['expiry_date']) < time();
                                    $isExpiringSoon = $c['expiry_date'] && strtotime($c['expiry_date']) >= time() && strtotime($c['expiry_date']) <= strtotime('+7 days');
                                    $usagePercent = $c['usage_limit'] ? ($c['used_count'] / $c['usage_limit']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="coupon-checkbox" value="<?php echo $c['id']; ?>">
                                        </td>
                                        <td>
                                            <div class="coupon-code"><?php echo htmlspecialchars($c['code']); ?></div>
                                            <?php if ($c['description']): ?>
                                                <div style="font-size: 12px; color: #646970; margin-top: 4px;">
                                                    <?php echo htmlspecialchars(substr($c['description'], 0, 50)); ?><?php echo strlen($c['description']) > 50 ? '...' : ''; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="coupon-badge badge-<?php echo $c['discount_type']; ?>">
                                                <?php echo ucfirst($c['discount_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php if ($c['discount_type'] === 'percentage'): ?>
                                                    <?php echo number_format($c['discount_amount'], 0); ?>%
                                                <?php else: ?>
                                                    GHS <?php echo number_format($c['discount_amount'], 2); ?>
                                                <?php endif; ?>
                                            </strong>
                                            <?php if ($c['minimum_amount']): ?>
                                                <div style="font-size: 12px; color: #646970;">
                                                    Min: GHS <?php echo number_format($c['minimum_amount'], 2); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 120px;">
                                            <?php echo $c['used_count']; ?>
                                            <?php if ($c['usage_limit']): ?>
                                                / <?php echo $c['usage_limit']; ?>
                                                <div class="usage-bar">
                                                    <div class="usage-bar-fill <?php echo $usagePercent >= 90 ? 'danger' : ($usagePercent >= 70 ? 'warning' : ''); ?>" style="width: <?php echo min($usagePercent, 100); ?>%"></div>
                                                </div>
                                            <?php else: ?>
                                                / ∞
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['expiry_date']): ?>
                                                <?php echo date('M d, Y', strtotime($c['expiry_date'])); ?>
                                                <?php if ($isExpired): ?>
                                                    <div><span class="coupon-badge badge-expired">Expired</span></div>
                                                <?php elseif ($isExpiringSoon): ?>
                                                    <div><span class="coupon-badge badge-expiring">Expiring Soon</span></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #646970;">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="coupon-badge badge-<?php echo $c['is_active'] && !$isExpired ? 'active' : 'inactive'; ?>">
                                                <?php echo $c['is_active'] && !$isExpired ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="quick-actions">
                                                <button type="button" class="quick-action-btn copy-code" data-code="<?php echo htmlspecialchars($c['code']); ?>" title="Copy code">
                                                    📋
                                                </button>
                                                <a href="?action=edit&id=<?php echo $c['id']; ?>" class="quick-action-btn" title="Edit">
                                                    ✏️
                                                </a>
                                                <button type="button" class="quick-action-btn duplicate-coupon" data-id="<?php echo $c['id']; ?>" title="Duplicate">
                                                    📄
                                                </button>
                                                <button type="button" class="quick-action-btn toggle-status" data-id="<?php echo $c['id']; ?>" data-active="<?php echo $c['is_active']; ?>" title="Toggle status">
                                                    <?php echo $c['is_active'] ? '👁️' : '👁️‍🗨️'; ?>
                                                </button>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this coupon?');">
                                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" name="delete_coupon" class="quick-action-btn" style="color: #d63638;" title="Delete">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Discount preview
        function updateDiscountPreview() {
            var type = $('#discount-type').val();
            var amount = parseFloat($('#discount-amount').val()) || 0;
            var preview = $('#discount-preview');
            
            if (amount > 0) {
                preview.show();
                var cartTotal = 100;
                var discount = 0;
                
                if (type === 'percentage') {
                    discount = (cartTotal * amount) / 100;
                } else {
                    discount = amount;
                }
                
                $('#preview-discount').text('- GHS ' + discount.toFixed(2));
                $('#preview-total').text('GHS ' + (cartTotal - discount).toFixed(2));
            } else {
                preview.hide();
            }
        }
        
        $('#discount-type, #discount-amount').on('change input', updateDiscountPreview);
        updateDiscountPreview();
        
        // Update discount help text and max
        $('#discount-type').on('change', function() {
            var type = $(this).val();
            var $amount = $('#discount-amount');
            var $help = $('#discount-help');
            
            if (type === 'percentage') {
                $amount.attr('max', '100');
                $help.text('Enter percentage (e.g., 10 for 10%). Maximum: 100%');
            } else {
                $amount.removeAttr('max');
                $help.text('Enter fixed amount in GHS (e.g., 50.00)');
            }
            updateDiscountPreview();
        });
        
        // Copy coupon code
        $('.copy-code').on('click', function() {
            var code = $(this).data('code');
            var $btn = $(this);
            
            navigator.clipboard.writeText(code).then(function() {
                $btn.addClass('copied').text('✅');
                setTimeout(function() {
                    $btn.removeClass('copied').text('📋');
                }, 2000);
            });
        });
        
        // Duplicate coupon
        $('.duplicate-coupon').on('click', function() {
            var couponId = $(this).data('id');
            if (!confirm('Duplicate this coupon?')) return;
            
            $.post('', {
                ajax_action: 'duplicate',
                coupon_id: couponId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to duplicate coupon'));
                }
            }, 'json');
        });
        
        // Toggle status
        $('.toggle-status').on('click', function() {
            var couponId = $(this).data('id');
            var $btn = $(this);
            
            $.post('', {
                ajax_action: 'toggle_status',
                coupon_id: couponId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to toggle status'));
                }
            }, 'json');
        });
        
        // Bulk actions
        var selectedCoupons = [];
        
        $('#select-all').on('change', function() {
            $('.coupon-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkActions();
        });
        
        $('.coupon-checkbox').on('change', function() {
            updateBulkActions();
        });
        
        function updateBulkActions() {
            selectedCoupons = $('.coupon-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            $('#selected-count').text(selectedCoupons.length);
            $('#bulk-actions-bar').toggleClass('active', selectedCoupons.length > 0);
            $('#select-all').prop('checked', selectedCoupons.length === $('.coupon-checkbox').length && selectedCoupons.length > 0);
        }
        
        $('#clear-selection').on('click', function() {
            $('.coupon-checkbox, #select-all').prop('checked', false);
            updateBulkActions();
        });
        
        $('#apply-bulk-action').on('click', function() {
            var action = $('#bulk-action-select').val();
            if (!action) {
                alert('Please select a bulk action');
                return;
            }
            
            if (selectedCoupons.length === 0) {
                alert('Please select at least one coupon');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to delete ' + selectedCoupons.length + ' coupon(s)?')) {
                return;
            }
            
            $.post('', {
                ajax_action: 'bulk_action',
                bulk_action: action,
                coupon_ids: selectedCoupons
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to perform bulk action'));
                }
            }, 'json');
        });
        
        // Filters
        function applyFilters() {
            var search = $('#search-input').val();
            var status = $('#filter-status').val();
            var type = $('#filter-type').val();
            
            var params = new URLSearchParams();
            if (search) params.set('search', search);
            if (status !== 'all') params.set('status', status);
            if (type !== 'all') params.set('type', type);
            
            window.location.href = 'coupons.php' + (params.toString() ? '?' + params.toString() : '');
        }
        
        $('#search-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyFilters();
            }
        });
        
        $('#filter-status, #filter-type').on('change', applyFilters);
    });
    </script>
</body>
</html>
