<?php
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

// Update quote status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $quoteId = $_POST['quote_id'];
    $status = $_POST['status'];
    $pdo->prepare("UPDATE cms_quote_requests SET status=? WHERE id=?")->execute([$status, $quoteId]);
    $message = 'Status updated';
}

$statusFilter = $_GET['status'] ?? 'all';
$where = '';
if ($statusFilter !== 'all') {
    $where = "WHERE status='" . $pdo->quote($statusFilter) . "'";
}

$quotes = $pdo->query("SELECT * FROM cms_quote_requests $where ORDER BY created_at DESC")->fetchAll();

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
    <title>Quote Requests - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'quotes';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Quote Requests</h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <div style="margin:15px 0;">
            <a href="?status=all" class="button">All</a>
            <a href="?status=new" class="button">New</a>
            <a href="?status=contacted" class="button">Contacted</a>
            <a href="?status=quoted" class="button">Quoted</a>
            <a href="?status=converted" class="button">Converted</a>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Service</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($q['name']); ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($q['email']); ?><br>
                            <small><?php echo htmlspecialchars($q['phone'] ?? '-'); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($q['service_type'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($q['location'] ?? '-'); ?></td>
                        <td><span class="status-<?php echo $q['status']; ?>"><?php echo ucfirst($q['status']); ?></span></td>
                        <td><?php echo date('Y/m/d', strtotime($q['created_at'])); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                <select name="status" style="width:120px; padding:4px;">
                                    <option value="new" <?php echo $q['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="contacted" <?php echo $q['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                    <option value="quoted" <?php echo $q['status'] === 'quoted' ? 'selected' : ''; ?>>Quoted</option>
                                    <option value="converted" <?php echo $q['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                                    <option value="rejected" <?php echo $q['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <input type="submit" name="update_status" value="Update" class="button" style="padding:4px 8px; font-size:12px;">
                            </form>
                            <?php if ($q['converted_to_client_id']): ?>
                                <br><small><a href="<?php echo $baseUrl; ?>/modules/crm.php?action=client-detail&id=<?php echo $q['converted_to_client_id']; ?>">View Client</a></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($q['description'])): ?>
                        <tr style="background:#f9f9f9;">
                            <td colspan="7" style="padding-left:30px; font-style:italic;">
                                <strong>Notes:</strong> <?php echo htmlspecialchars($q['description']); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

