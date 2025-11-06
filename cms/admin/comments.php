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

// Ensure comments table exists
try {
    $pdo->query("SELECT 1 FROM cms_comments LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author_name VARCHAR(100) NOT NULL,
        author_email VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        status ENUM('pending','approved','spam','trash') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES cms_posts(id) ON DELETE CASCADE,
        INDEX idx_post (post_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$comments = $pdo->query("SELECT c.*, p.title as post_title FROM cms_comments c LEFT JOIN cms_posts p ON p.id=c.post_id ORDER BY c.created_at DESC")->fetchAll();

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
    <title>Comments - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'comments';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Comments</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Author</th><th>Comment</th><th>Post</th><th>Date</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($comments)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px;">No comments yet</td></tr>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($c['author_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($c['author_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(substr($c['content'], 0, 100)); ?>...</td>
                            <td><?php echo htmlspecialchars($c['post_title'] ?? 'N/A'); ?></td>
                            <td><?php echo date('Y/m/d', strtotime($c['created_at'])); ?></td>
                            <td><span class="status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

