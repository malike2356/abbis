<?php
/**
 * Single Blog Post Page
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    $segments = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $slug = end($segments);
}

$postStmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM cms_posts p
    LEFT JOIN cms_categories c ON c.id=p.category_id
    WHERE p.slug=? AND p.status='published'
    LIMIT 1
");
$postStmt->execute([$slug]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    $baseUrl = '/abbis3.2';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '/abbis3.2';
    }
    echo '<h1>Post Not Found</h1><a href="' . $baseUrl . '/cms/blog">← Back to Blog</a>';
    exit;
}

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Blog');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - <?php echo htmlspecialchars($companyName); ?> Blog</title>
    <style>
        /* Enhanced Single Post Styling - Beautiful WordPress-like design */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; line-height: 1.6; }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
        }
        .post-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 5rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
        }
        .post-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            line-height: 1.2;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 0 2rem; }
        .post-content {
            background: white;
            padding: 4rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .post-meta {
            color: #64748b;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .post-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .post-body {
            color: #475569;
            font-size: 1.0625rem;
            line-height: 1.9;
        }
        .post-body h2 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 600;
            margin: 2.5rem 0 1rem;
        }
        .post-body h3 {
            color: #334155;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
        }
        .post-body p {
            margin: 1.5rem 0;
        }
        .post-body img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            padding: 0.875rem 1.75rem;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 2rem;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(14,165,233,0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14,165,233,0.4);
        }
        @media (max-width: 768px) {
            .post-hero h1 { font-size: 2rem; }
            .post-content { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <div class="post-hero">
            <div class="container">
                <h1><?php echo htmlspecialchars($post['title']); ?></h1>
            </div>
        </div>
        
        <div class="container">
            <div class="post-content">
                <div class="post-meta">
                    <?php if ($post['published_at']): ?>
                        <span>📅 <?php echo date('F j, Y', strtotime($post['published_at'])); ?></span>
                    <?php endif; ?>
                    <?php if ($post['category_name']): ?>
                        <span>🏷️ <?php echo htmlspecialchars($post['category_name']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="post-body">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                </div>
                <a href="<?php echo $baseUrl; ?>/cms/blog" class="btn">← Back to Blog</a>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

