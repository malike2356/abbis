<?php
/**
 * CMS Admin Panel
 */
require_once '../includes/header.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure tables exist
try { $pdo->query("SELECT 1 FROM cms_pages LIMIT 1"); }
catch (Throwable $e) {
    @include_once '../database/run-sql.php';
    @run_sql_file(__DIR__ . '/../database/cms_migration.sql');
}

$action = $_GET['action'] ?? 'dashboard';
$tab = $_GET['tab'] ?? 'pages';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Admin - ABBIS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .cms-admin { display: flex; gap: 2rem; margin-top: 2rem; }
        .cms-sidebar { width: 250px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; }
        .cms-sidebar a { display: block; padding: 0.75rem; color: var(--text); text-decoration: none; border-radius: 6px; margin-bottom: 0.5rem; }
        .cms-sidebar a:hover { background: var(--hover); }
        .cms-sidebar a.active { background: var(--primary); color: white; }
        .cms-content { flex: 1; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); padding: 1.5rem; border-radius: 8px; }
        .stat-card h3 { color: var(--primary); margin-bottom: 0.5rem; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .data-table th, .data-table td { padding: 0.75rem; border-bottom: 1px solid var(--border); text-align: left; }
        .data-table th { background: var(--hover); font-weight: 600; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> / <span>CMS Admin</span>
        </div>

        <h1>CMS Administration</h1>

        <div class="cms-admin">
            <div class="cms-sidebar">
                <a href="?tab=dashboard" class="<?php echo $tab === 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
                <a href="?tab=pages" class="<?php echo $tab === 'pages' ? 'active' : ''; ?>">📄 Pages</a>
                <a href="?tab=posts" class="<?php echo $tab === 'posts' ? 'active' : ''; ?>">📝 Posts</a>
                <a href="?tab=categories" class="<?php echo $tab === 'categories' ? 'active' : ''; ?>">🏷️ Categories</a>
                <a href="?tab=themes" class="<?php echo $tab === 'themes' ? 'active' : ''; ?>">🎨 Themes</a>
                <a href="?tab=menu" class="<?php echo $tab === 'menu' ? 'active' : ''; ?>">🔗 Menu</a>
                <a href="?tab=quotes" class="<?php echo $tab === 'quotes' ? 'active' : ''; ?>">📋 Quote Requests</a>
                <a href="?tab=orders" class="<?php echo $tab === 'orders' ? 'active' : ''; ?>">🛒 Orders</a>
                <a href="?tab=settings" class="<?php echo $tab === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a>
            </div>

            <div class="cms-content">
                <?php
                switch ($tab) {
                    case 'dashboard':
                        // Stats
                        $pagesCount = $pdo->query("SELECT COUNT(*) FROM cms_pages")->fetchColumn();
                        $postsCount = $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='published'")->fetchColumn();
                        $quotesCount = $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='new'")->fetchColumn();
                        $ordersCount = $pdo->query("SELECT COUNT(*) FROM cms_orders WHERE status='pending'")->fetchColumn();
                        ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h3><?php echo $pagesCount; ?></h3>
                                <p>Total Pages</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $postsCount; ?></h3>
                                <p>Published Posts</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $quotesCount; ?></h3>
                                <p>New Quote Requests</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $ordersCount; ?></h3>
                                <p>Pending Orders</p>
                            </div>
                        </div>
                        <p><a href="?tab=quotes" class="btn btn-primary">View Quote Requests →</a></p>
                        <?php break;
                    case 'pages':
                        $pages = $pdo->query("SELECT * FROM cms_pages ORDER BY created_at DESC")->fetchAll();
                        ?>
                        <h2>Pages</h2>
                        <a href="?tab=page-edit" class="btn btn-primary" style="margin-bottom:1rem;">+ New Page</a>
                        <table class="data-table">
                            <thead>
                                <tr><th>Title</th><th>Slug</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($page['title']); ?></td>
                                        <td><?php echo htmlspecialchars($page['slug']); ?></td>
                                        <td><span class="badge"><?php echo $page['status']; ?></span></td>
                                        <td><?php echo date('Y-m-d', strtotime($page['created_at'])); ?></td>
                                        <td><a href="?tab=page-edit&id=<?php echo $page['id']; ?>">Edit</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break;
                    case 'quotes':
                        $quotes = $pdo->query("SELECT * FROM cms_quote_requests ORDER BY created_at DESC LIMIT 50")->fetchAll();
                        ?>
                        <h2>Quote Requests</h2>
                        <table class="data-table">
                            <thead>
                                <tr><th>Name</th><th>Email</th><th>Service</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotes as $q): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($q['name']); ?></td>
                                        <td><?php echo htmlspecialchars($q['email']); ?></td>
                                        <td><?php echo htmlspecialchars($q['service_type']); ?></td>
                                        <td><span class="badge"><?php echo $q['status']; ?></span></td>
                                        <td><?php echo date('Y-m-d', strtotime($q['created_at'])); ?></td>
                                        <td>
                                            <a href="crm.php?action=client-detail&email=<?php echo urlencode($q['email']); ?>">View in CRM</a>
                                            <?php if ($q['status'] === 'new'): ?>
                                                | <a href="?tab=quotes&mark=contacted&id=<?php echo $q['id']; ?>">Mark Contacted</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break;
                    case 'orders':
                        $orders = $pdo->query("SELECT * FROM cms_orders ORDER BY created_at DESC LIMIT 50")->fetchAll();
                        ?>
                        <h2>Orders</h2>
                        <table class="data-table">
                            <thead>
                                <tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>GHS <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="badge"><?php echo $order['status']; ?></span></td>
                                        <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                                        <td><a href="?tab=order-detail&id=<?php echo $order['id']; ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break;
                    default:
                        echo '<p>Content for ' . htmlspecialchars($tab) . ' coming soon...</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>

