<?php
/**
 * Client Portal Dashboard
 * Supports admin mode from ABBIS SSO
 */
require_once __DIR__ . '/auth-check.php';

$pageTitle = 'Dashboard';
$isAdminMode = isset($_SESSION['client_portal_admin_mode']) && $_SESSION['client_portal_admin_mode'] === true;
$isAdminRole = isset($_SESSION['role']) && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_SUPER_ADMIN);

// Get statistics
$stats = [
    'quotes' => ['pending' => 0, 'accepted' => 0, 'total' => 0],
    'invoices' => ['outstanding' => 0, 'paid' => 0, 'total' => 0, 'total_outstanding' => 0.0],
    'payments' => ['total' => 0, 'total_amount' => 0.0],
    'projects' => ['active' => 0, 'total' => 0]
];

try {
    // Ensure tables exist before querying
    // Check if client portal tables exist, create them if they don't
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `client_quotes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `client_id` INT(11) NOT NULL,
            `quote_number` VARCHAR(50) DEFAULT NULL,
            `total_amount` DECIMAL(12,2) DEFAULT 0.00,
            `status` ENUM('draft','sent','viewed','accepted','rejected','expired') DEFAULT 'draft',
            `valid_until` DATE DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `client_id` (`client_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `client_invoices` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `client_id` INT(11) NOT NULL,
            `invoice_number` VARCHAR(50) DEFAULT NULL,
            `total_amount` DECIMAL(12,2) DEFAULT 0.00,
            `balance_due` DECIMAL(12,2) DEFAULT 0.00,
            `status` ENUM('draft','sent','viewed','partial','paid','overdue') DEFAULT 'draft',
            `issue_date` DATE DEFAULT NULL,
            `due_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `client_id` (`client_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `client_payments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `client_id` INT(11) NOT NULL,
            `invoice_id` INT(11) DEFAULT NULL,
            `amount` DECIMAL(12,2) DEFAULT 0.00,
            `payment_status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
            `payment_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `client_id` (`client_id`),
            KEY `invoice_id` (`invoice_id`),
            KEY `payment_status` (`payment_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    if ($isAdminMode && $isAdminRole) {
        // Admin mode - show all clients stats if no specific client selected
        if (!$clientId) {
            // All clients overview
            $stmt = $pdo->query("
                SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'viewed') THEN 1 ELSE 0 END), 0) as pending,
                    COALESCE(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END), 0) as accepted
                FROM client_quotes
            ");
            $quoteStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($quoteStats !== false) {
                $stats['quotes'] = [
                    'total' => (int)($quoteStats['total'] ?? 0),
                    'pending' => (int)($quoteStats['pending'] ?? 0),
                    'accepted' => (int)($quoteStats['accepted'] ?? 0)
                ];
            }
            
            $stmt = $pdo->query("
                SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'viewed', 'partial', 'overdue') THEN 1 ELSE 0 END), 0) as outstanding,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid,
                    COALESCE(SUM(balance_due), 0) as total_outstanding
                FROM client_invoices
            ");
            $invoiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($invoiceStats !== false) {
                $stats['invoices'] = [
                    'total' => (int)($invoiceStats['total'] ?? 0),
                    'outstanding' => (int)($invoiceStats['outstanding'] ?? 0),
                    'paid' => (int)($invoiceStats['paid'] ?? 0),
                    'total_outstanding' => (float)($invoiceStats['total_outstanding'] ?? 0.0)
                ];
            }
            
            $stmt = $pdo->query("
                SELECT 
                    COALESCE(COUNT(*), 0) as total, 
                    COALESCE(SUM(amount), 0) as total_amount
                FROM client_payments 
                WHERE payment_status = 'completed'
            ");
            $paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($paymentStats !== false) {
                $stats['payments'] = [
                    'total' => (int)($paymentStats['total'] ?? 0),
                    'total_amount' => (float)($paymentStats['total_amount'] ?? 0.0)
                ];
            }
            
            $stmt = $pdo->query("SELECT COALESCE(COUNT(*), 0) as total FROM field_reports");
            $projectStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($projectStats !== false) {
                $stats['projects']['total'] = (int)($projectStats['total'] ?? 0);
            }
        } else {
            // Specific client selected - use existing client logic
            // Quotes stats
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'viewed') THEN 1 ELSE 0 END), 0) as pending,
                    COALESCE(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END), 0) as accepted
                FROM client_quotes 
                WHERE client_id = ?
            ");
            $stmt->execute([$clientId]);
            $quoteStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($quoteStats !== false) {
                $stats['quotes'] = [
                    'total' => (int)($quoteStats['total'] ?? 0),
                    'pending' => (int)($quoteStats['pending'] ?? 0),
                    'accepted' => (int)($quoteStats['accepted'] ?? 0)
                ];
            }
            
            // Invoices stats
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'viewed', 'partial', 'overdue') THEN 1 ELSE 0 END), 0) as outstanding,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid,
                    COALESCE(SUM(balance_due), 0) as total_outstanding
                FROM client_invoices 
                WHERE client_id = ?
            ");
            $stmt->execute([$clientId]);
            $invoiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($invoiceStats !== false) {
                $stats['invoices'] = [
                    'total' => (int)($invoiceStats['total'] ?? 0),
                    'outstanding' => (int)($invoiceStats['outstanding'] ?? 0),
                    'paid' => (int)($invoiceStats['paid'] ?? 0),
                    'total_outstanding' => (float)($invoiceStats['total_outstanding'] ?? 0.0)
                ];
            }
            
            // Payments stats
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total, 
                    COALESCE(SUM(amount), 0) as total_amount
                FROM client_payments 
                WHERE client_id = ? AND payment_status = 'completed'
            ");
            $stmt->execute([$clientId]);
            $paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($paymentStats !== false) {
                $stats['payments'] = [
                    'total' => (int)($paymentStats['total'] ?? 0),
                    'total_amount' => (float)($paymentStats['total_amount'] ?? 0.0)
                ];
            }
            
            // Projects stats (field reports)
            $stmt = $pdo->prepare("
                SELECT COALESCE(COUNT(*), 0) as total
                FROM field_reports 
                WHERE client_id = ?
            ");
            $stmt->execute([$clientId]);
            $projectStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($projectStats !== false) {
                $stats['projects']['total'] = (int)($projectStats['total'] ?? 0);
            }
        }
    } elseif ($clientId) {
        // Client mode - show only their data
        // Quotes stats
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total,
                COALESCE(SUM(CASE WHEN status IN ('sent', 'viewed') THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END), 0) as accepted
            FROM client_quotes 
            WHERE client_id = ?
        ");
        $stmt->execute([$clientId]);
        $quoteStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($quoteStats !== false) {
            $stats['quotes'] = [
                'total' => (int)($quoteStats['total'] ?? 0),
                'pending' => (int)($quoteStats['pending'] ?? 0),
                'accepted' => (int)($quoteStats['accepted'] ?? 0)
            ];
        }
        
        // Invoices stats
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total,
                COALESCE(SUM(CASE WHEN status IN ('sent', 'viewed', 'partial', 'overdue') THEN 1 ELSE 0 END), 0) as outstanding,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid,
                COALESCE(SUM(balance_due), 0) as total_outstanding
            FROM client_invoices 
            WHERE client_id = ?
        ");
        $stmt->execute([$clientId]);
        $invoiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($invoiceStats !== false) {
            $stats['invoices'] = [
                'total' => (int)($invoiceStats['total'] ?? 0),
                'outstanding' => (int)($invoiceStats['outstanding'] ?? 0),
                'paid' => (int)($invoiceStats['paid'] ?? 0),
                'total_outstanding' => (float)($invoiceStats['total_outstanding'] ?? 0.0)
            ];
        }
        
        // Payments stats
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total, 
                COALESCE(SUM(amount), 0) as total_amount
            FROM client_payments 
            WHERE client_id = ? AND payment_status = 'completed'
        ");
        $stmt->execute([$clientId]);
        $paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($paymentStats !== false) {
            $stats['payments'] = [
                'total' => (int)($paymentStats['total'] ?? 0),
                'total_amount' => (float)($paymentStats['total_amount'] ?? 0.0)
            ];
        }
        
        // CMS Orders stats (if client has orders)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed,
                    COALESCE(SUM(total_amount), 0) as total_spent
                FROM cms_orders 
                WHERE client_id = ?
            ");
            $stmt->execute([$clientId]);
            $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($orderStats && $orderStats['total'] > 0) {
                $stats['cms_orders'] = [
                    'total' => (int)($orderStats['total'] ?? 0),
                    'completed' => (int)($orderStats['completed'] ?? 0),
                    'total_spent' => (float)($orderStats['total_spent'] ?? 0.0)
                ];
            }
        } catch (PDOException $e) {
            // CMS orders table might not exist
        }
        
        // POS Purchases stats (if client has POS sales)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(total_amount), 0) as total_spent
                FROM pos_sales 
                WHERE customer_id = ? AND status = 'completed'
            ");
            $stmt->execute([$clientId]);
            $posStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($posStats && $posStats['total'] > 0) {
                $stats['pos_purchases'] = [
                    'total' => (int)($posStats['total'] ?? 0),
                    'total_spent' => (float)($posStats['total_spent'] ?? 0.0)
                ];
            }
        } catch (PDOException $e) {
            // POS sales table might not exist
        }
        
        // Projects stats (field reports)
        $stmt = $pdo->prepare("
            SELECT COALESCE(COUNT(*), 0) as total
            FROM field_reports 
            WHERE client_id = ?
        ");
        $stmt->execute([$clientId]);
        $projectStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($projectStats !== false) {
            $stats['projects']['total'] = (int)($projectStats['total'] ?? 0);
        }
    }
} catch (PDOException $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    // Ensure stats array has default values even on error
    $stats = [
        'quotes' => ['pending' => 0, 'accepted' => 0, 'total' => 0],
        'invoices' => ['outstanding' => 0, 'paid' => 0, 'total' => 0, 'total_outstanding' => 0.0],
        'payments' => ['total' => 0, 'total_amount' => 0.0],
        'projects' => ['active' => 0, 'total' => 0]
    ];
}

// Get recent activity
$recentQuotes = [];
$recentInvoices = [];
try {
    if ($isAdminMode && ($isAdminRole || (isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN))) {
        // Admin mode - show all clients or specific client
        if ($clientId) {
            // Specific client
            $stmt = $pdo->prepare("
                SELECT id, quote_number, COALESCE(total_amount, 0) as total_amount, status, created_at, valid_until
                FROM client_quotes 
                WHERE client_id = ?
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$clientId]);
            $recentQuotes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            $stmt = $pdo->prepare("
                SELECT id, invoice_number, COALESCE(total_amount, 0) as total_amount, COALESCE(balance_due, 0) as balance_due, status, issue_date, due_date
                FROM client_invoices 
                WHERE client_id = ?
                ORDER BY COALESCE(issue_date, created_at) DESC 
                LIMIT 5
            ");
            $stmt->execute([$clientId]);
            $recentInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            // All clients
            $stmt = $pdo->query("
                SELECT id, quote_number, COALESCE(total_amount, 0) as total_amount, status, created_at, valid_until
                FROM client_quotes 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $recentQuotes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] : [];
            
            $stmt = $pdo->query("
                SELECT id, invoice_number, COALESCE(total_amount, 0) as total_amount, COALESCE(balance_due, 0) as balance_due, status, issue_date, due_date
                FROM client_invoices 
                ORDER BY COALESCE(issue_date, created_at) DESC 
                LIMIT 5
            ");
            $recentInvoices = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] : [];
        }
    } elseif ($clientId) {
        // Client mode - show only their data
        $stmt = $pdo->prepare("
            SELECT id, quote_number, COALESCE(total_amount, 0) as total_amount, status, created_at, valid_until
            FROM client_quotes 
            WHERE client_id = ?
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$clientId]);
        $recentQuotes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, COALESCE(total_amount, 0) as total_amount, COALESCE(balance_due, 0) as balance_due, status, issue_date, due_date
            FROM client_invoices 
            WHERE client_id = ?
            ORDER BY COALESCE(issue_date, created_at) DESC 
            LIMIT 5
        ");
        $stmt->execute([$clientId]);
        $recentInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (PDOException $e) {
    error_log('Recent activity error: ' . $e->getMessage());
    // Ensure arrays are empty on error
    $recentQuotes = [];
    $recentInvoices = [];
}

include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="dashboard-header">
        <?php if ($isAdminMode && $isAdminRole): ?>
            <h1>Client Portal - Admin View</h1>
            <p>Viewing client portal as administrator</p>
            <?php if (!empty($clientId) && isset($client) && $client): ?>
                <p><strong>Client:</strong> <?php echo htmlspecialchars($client['client_name'] ?? 'Unknown'); ?></p>
            <?php else: ?>
                <p><strong>View:</strong> All Clients Overview</p>
            <?php endif; ?>
        <?php else: ?>
            <h1>Welcome back, <?php echo htmlspecialchars($client['client_name'] ?? $client['name'] ?? $_SESSION['full_name'] ?? 'Client'); ?>!</h1>
            <p>Here's an overview of your account</p>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)($stats['quotes']['total'] ?? 0); ?></div>
                <div class="stat-label">Total Quotes</div>
                <div class="stat-detail"><?php echo (int)($stats['quotes']['pending'] ?? 0); ?> pending</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format((float)($stats['invoices']['total_outstanding'] ?? 0.0), 2); ?></div>
                <div class="stat-label">Outstanding Balance</div>
                <div class="stat-detail"><?php echo (int)($stats['invoices']['outstanding'] ?? 0); ?> invoices</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💳</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)($stats['payments']['total'] ?? 0); ?></div>
                <div class="stat-label">Payments Made</div>
                <div class="stat-detail"><?php echo number_format((float)($stats['payments']['total_amount'] ?? 0.0), 2); ?> total</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🚧</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo (int)($stats['projects']['total'] ?? 0); ?></div>
                <div class="stat-label">Projects</div>
                <div class="stat-detail">View all projects</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h2>Recent Quotes</h2>
                <a href="quotes.php" class="btn-link">View All →</a>
            </div>
            <div class="card-content">
                <?php if (empty($recentQuotes)): ?>
                    <p class="empty-state">No quotes yet</p>
                <?php else: ?>
                    <div class="list-items">
                        <?php foreach ($recentQuotes as $quote): ?>
                            <div class="list-item">
                                <div class="item-main">
                                    <strong><?php echo htmlspecialchars($quote['quote_number'] ?? 'N/A'); ?></strong>
                                    <span class="badge badge-<?php echo htmlspecialchars($quote['status'] ?? 'unknown'); ?>"><?php echo ucfirst($quote['status'] ?? 'Unknown'); ?></span>
                                </div>
                                <div class="item-meta">
                                    <span><?php echo number_format((float)($quote['total_amount'] ?? 0.0), 2); ?> GHS</span>
                                    <span><?php echo !empty($quote['created_at']) ? date('M d, Y', strtotime($quote['created_at'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h2>Recent Invoices</h2>
                <a href="invoices.php" class="btn-link">View All →</a>
            </div>
            <div class="card-content">
                <?php if (empty($recentInvoices)): ?>
                    <p class="empty-state">No invoices yet</p>
                <?php else: ?>
                    <div class="list-items">
                        <?php foreach ($recentInvoices as $invoice): ?>
                            <div class="list-item">
                                <div class="item-main">
                                    <strong><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></strong>
                                    <span class="badge badge-<?php echo htmlspecialchars($invoice['status'] ?? 'unknown'); ?>"><?php echo ucfirst($invoice['status'] ?? 'Unknown'); ?></span>
                                </div>
                                <div class="item-meta">
                                    <span><?php echo number_format((float)($invoice['balance_due'] ?? 0.0), 2); ?> GHS due</span>
                                    <?php if (!empty($invoice['due_date'])): ?>
                                        <span>Due: <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <a href="quotes.php" class="action-btn">
                <span class="action-icon">📋</span>
                <span>View Quotes</span>
            </a>
            <a href="invoices.php" class="action-btn">
                <span class="action-icon">💰</span>
                <span>View Invoices</span>
            </a>
            <a href="payments.php" class="action-btn">
                <span class="action-icon">💳</span>
                <span>Make Payment</span>
            </a>
            <a href="profile.php" class="action-btn">
                <span class="action-icon">👤</span>
                <span>My Profile</span>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

