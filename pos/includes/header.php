<?php
/**
 * POS System Header - Dark Sidebar Layout
 */
if (!isset($auth)) {
    require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
}

$pdo = getDBConnection();
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $configStmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

$companyName = $config['company_name'] ?? 'POS System';
$currentUser = $_SESSION['username'] ?? 'User';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentAction = $_GET['action'] ?? '';
$baseUrl = app_base_path();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : ''; ?><?php echo e($companyName); ?> POS</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/pos/assets/css/pos-styles.css?v=<?php echo time(); ?>">
    <?php if (isset($additional_css)): foreach ($additional_css as $css): ?>
        <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; endif; ?>
</head>
<body class="pos-system">
    <div class="pos-layout">
        <!-- Dark Sidebar -->
        <aside class="pos-sidebar">
            <div class="pos-sidebar-header">
                <div class="pos-logo">
                    <span class="pos-logo-icon">K</span>
                    <span class="pos-logo-text">KARI P.O.S.</span>
                </div>
            </div>
            
            <nav class="pos-nav">
                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=terminal" 
                   class="pos-nav-item <?php echo ($currentPage === 'terminal.php' || ($currentPage === 'index.php' && ($_GET['action'] ?? 'terminal') === 'terminal')) ? 'active' : ''; ?>">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                        <line x1="6" y1="10" x2="10" y2="10"></line>
                        <line x1="6" y1="14" x2="8" y2="14"></line>
                        <line x1="14" y1="14" x2="18" y2="14"></line>
                    </svg>
                    <span>Terminal</span>
                </a>
                
                <?php if ($auth->userHasPermission('pos.inventory.manage')): ?>
                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin" 
                   class="pos-nav-item <?php echo ($currentPage === 'index.php' && $currentAction === 'admin') ? 'active' : ''; ?>">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                    <span>Admin</span>
                </a>
                <?php endif; ?>
                
                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=sales" 
                   class="pos-nav-item <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'sales') ? 'active' : ''; ?>">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <span>Sales</span>
                </a>
                
                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=inventory" 
                   class="pos-nav-item <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'inventory') ? 'active' : ''; ?>">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                        <line x1="15" y1="3" x2="15" y2="21"></line>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="3" y1="15" x2="21" y2="15"></line>
                    </svg>
                    <span>Inventory</span>
                </a>
                
                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=admin&tab=catalog" 
                   class="pos-nav-item <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'catalog') ? 'active' : ''; ?>">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Catalog</span>
                </a>
                
                <div class="pos-nav-divider"></div>
                
                <a href="<?php echo $baseUrl; ?>/modules/dashboard.php" class="pos-nav-item">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>ABBIS</span>
                </a>
                
                <a href="<?php echo $baseUrl; ?>/cms/admin/index.php" class="pos-nav-item">
                    <svg class="pos-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                    <span>CMS</span>
                </a>
            </nav>
            
            <div class="pos-sidebar-footer">
                <a href="<?php echo $baseUrl; ?>/pos/index.php?action=profile" class="pos-user-info" style="text-decoration: none; color: inherit; display: flex; align-items: center; flex: 1; cursor: pointer;">
                    <div class="pos-user-avatar">
                        <?php echo strtoupper(substr($currentUser, 0, 1)); ?>
                    </div>
                    <div class="pos-user-details">
                        <div class="pos-user-name"><?php echo e($currentUser); ?></div>
                        <div class="pos-user-role"><?php echo e($_SESSION['role'] ?? 'User'); ?></div>
                    </div>
                </a>
                <a href="<?php echo $baseUrl; ?>/logout.php" class="pos-logout-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="pos-main">
            <div class="pos-content">
