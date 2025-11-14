<?php
/**
 * Client Portal Header
 * Supports admin mode from ABBIS SSO
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdminMode = isset($_SESSION['client_portal_admin_mode']) && $_SESSION['client_portal_admin_mode'] === true;
$isAdminRole = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Client Portal'); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo app_url('client-portal/client-styles.css'); ?>">
    <style>
        .admin-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #f59e0b;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
        }
        .admin-indicator {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin: 16px 0;
            border-radius: 4px;
        }
        .admin-indicator strong {
            color: #92400e;
        }
    </style>
</head>
<body>
    <nav class="client-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="dashboard.php"><?php echo APP_NAME; ?> Client Portal</a>
                <?php if ($isAdminMode && $isAdminRole): ?>
                    <span class="admin-badge">Admin Mode</span>
                <?php endif; ?>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
                <a href="quotes.php" class="<?php echo $currentPage === 'quotes.php' ? 'active' : ''; ?>">Quotes</a>
                <a href="invoices.php" class="<?php echo $currentPage === 'invoices.php' ? 'active' : ''; ?>">Invoices</a>
                <a href="payments.php" class="<?php echo $currentPage === 'payments.php' ? 'active' : ''; ?>">Payments</a>
                <a href="projects.php" class="<?php echo $currentPage === 'projects.php' ? 'active' : ''; ?>">Projects</a>
                <a href="profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">Profile</a>
            </div>
            <div class="nav-user">
                <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Client'); ?></span>
                <?php if ($isAdminMode && $isAdminRole): ?>
                    <a href="<?php echo app_url('modules/dashboard.php'); ?>" class="btn-logout" style="margin-right: 8px;">← ABBIS</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <?php if ($isAdminMode && $isAdminRole): ?>
        <?php
        // Get viewing client ID if available (from auth-check.php)
        $viewingClientId = $viewingClientId ?? $_GET['client_id'] ?? null;
        if ($viewingClientId) {
            $viewingClientId = (int)$viewingClientId;
        }
        ?>
        <div class="admin-indicator">
            <strong>🔑 Admin Mode:</strong> You are viewing the client portal as an administrator. 
            <?php if ($viewingClientId): ?>
                Currently viewing client ID: <?php echo $viewingClientId; ?>
            <?php else: ?>
                Viewing all clients overview.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <main class="client-main">

