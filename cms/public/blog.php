<?php
/**
 * Blog Listing Page
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Blog');

// Get posts
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$postsStmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM cms_posts p
    LEFT JOIN cms_categories c ON c.id=p.category_id
    WHERE p.status='published'
    ORDER BY p.published_at DESC
    LIMIT ? OFFSET ?
");
$postsStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$postsStmt->bindValue(2, $offset, PDO::PARAM_INT);
$postsStmt->execute();
$posts = $postsStmt->fetchAll();

$totalStmt = $pdo->query("SELECT COUNT(*) FROM cms_posts WHERE status='published'");
$totalPosts = $totalStmt->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        /* Enhanced Blog Styling - Beautiful WordPress-like design */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
        }
        .blog-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 5rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
        }
        .blog-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .blog-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 2rem; }
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .post-card {
            background: white;
            padding: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .post-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        .post-card-content {
            padding: 2rem;
        }
        .post-card h2 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        .post-card h2 a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s;
        }
        .post-card h2 a:hover {
            color: #0ea5e9;
        }
        .post-meta {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .post-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .post-excerpt {
            color: #475569;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-block;
            padding: 0.875rem 1.75rem;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(14,165,233,0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14,165,233,0.4);
        }
        .pagination {
            text-align: center;
            margin: 3rem 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
        }
        .empty-state {
            background: white;
            padding: 5rem 2rem;
            text-align: center;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .blog-hero h1 { font-size: 2.5rem; }
            .posts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <div class="blog-hero">
            <div class="container">
                <h1>Our Blog</h1>
                <p>Stay updated with the latest news, tips, and insights</p>
            </div>
        </div>
        
        <div class="container">
        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h2 style="color: #1e293b; margin-bottom: 1rem;">No posts yet</h2>
                <p style="color: #64748b;">Check back soon for exciting content!</p>
            </div>
        <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card">
                        <div class="post-card-content">
                            <h2><a href="<?php echo $baseUrl; ?>/cms/post/<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                            <div class="post-meta">
                                <?php if ($post['published_at']): ?>
                                    <span>📅 <?php echo date('M j, Y', strtotime($post['published_at'])); ?></span>
                                <?php endif; ?>
                                <?php if ($post['category_name']): ?>
                                    <span>🏷️ <?php echo htmlspecialchars($post['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="post-excerpt">
                                <?php echo htmlspecialchars($post['excerpt'] ?? substr(strip_tags($post['content']), 0, 150)); ?>...
                            </div>
                            <a href="<?php echo $baseUrl; ?>/cms/post/<?php echo urlencode($post['slug']); ?>" class="btn">Read More →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn">← Previous</a>
                    <?php endif; ?>
                    <span style="padding: 0.875rem 1.75rem; background: white; border-radius: 8px; color: #475569; font-weight: 500;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

