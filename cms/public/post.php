<?php
/**
 * Single Blog Post Page with Comments
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        parent_id INT DEFAULT NULL COMMENT 'For threaded comments',
        FOREIGN KEY (post_id) REFERENCES cms_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES cms_comments(id) ON DELETE CASCADE,
        INDEX idx_post (post_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure all required columns exist in cms_comments table
$requiredColumns = [
    'parent_id' => "INT DEFAULT NULL COMMENT 'For threaded comments'",
    'ip_address' => "VARCHAR(45) DEFAULT NULL",
    'user_agent' => "TEXT DEFAULT NULL"
];

foreach ($requiredColumns as $columnName => $columnDefinition) {
    try {
        // Check if column exists using information_schema
        $checkCol = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = '$columnName'");
        $colExists = $checkCol->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
        
        if (!$colExists) {
            // Column doesn't exist, add it
            try {
                if ($columnName === 'parent_id') {
                    // Try with AFTER clause first for parent_id
                    $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER user_agent");
                } elseif ($columnName === 'ip_address') {
                    // Add ip_address after user_agent or at the end
                    try {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER user_agent");
                    } catch (PDOException $e) {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                    }
                } elseif ($columnName === 'user_agent') {
                    // Add user_agent after updated_at
                    try {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER updated_at");
                    } catch (PDOException $e) {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                    }
                } else {
                    $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                }
            } catch (PDOException $e1) {
                // If AFTER fails, try without it
                try {
                    $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                } catch (PDOException $e2) {
                    error_log("Failed to add $columnName column: " . $e2->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
        // If information_schema query fails, try direct column check
        try {
            $pdo->query("SELECT $columnName FROM cms_comments LIMIT 1");
        } catch (PDOException $e2) {
            // Column doesn't exist, try to add it
            try {
                $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
            } catch (PDOException $e3) {
                error_log("Failed to add $columnName column: " . $e3->getMessage());
            }
        }
    }
}

// Add foreign key and index for parent_id if it exists
try {
    $checkParent = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = 'parent_id'");
    $parentExists = $checkParent->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if ($parentExists) {
        // Add foreign key if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES cms_comments(id) ON DELETE CASCADE");
        } catch (PDOException $fkError) {
            // Foreign key might already exist, ignore
        }
        
        // Add index if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE cms_comments ADD INDEX idx_parent (parent_id)");
        } catch (PDOException $idxError) {
            // Index might already exist, ignore
        }
    }
} catch (PDOException $e) {
    // Ignore errors in foreign key/index creation
}

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    $segments = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $slug = end($segments);
}

$postStmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           u.username as author_username, 
           COALESCE(u.full_name, u.username) as author_display_name
    FROM cms_posts p
    LEFT JOIN cms_categories c ON c.id=p.category_id
    LEFT JOIN users u ON u.id=p.created_by
    WHERE p.slug=? AND p.status='published'
    LIMIT 1
");
$postStmt->execute([$slug]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    $baseUrl = app_base_path();
    echo '<h1>Post Not Found</h1><a href="' . $baseUrl . '/cms/blog">← Back to Blog</a>';
    exit;
}

$postId = $post['id'];
$message = null;
$error = null;

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $authorName = trim($_POST['author_name'] ?? '');
    $authorEmail = trim($_POST['author_email'] ?? '');
    $commentContent = trim($_POST['comment'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Check if comments are allowed
    $allowComments = true;
    try {
        $settingsStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='allow_comments'");
        $allowComments = $settingsStmt->fetchColumn() !== '0';
    } catch (Exception $e) {
        // Default to allowing comments
    }
    
    if (!$allowComments) {
        $error = 'Comments are currently disabled.';
    } elseif (empty($authorName) || empty($authorEmail) || empty($commentContent)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if moderation is required
        $requireModeration = false;
        try {
            $modStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='require_moderation'");
            $requireModeration = $modStmt->fetchColumn() === '1';
        } catch (Exception $e) {
            // Default to requiring moderation
            $requireModeration = true;
        }
        
        $status = $requireModeration ? 'pending' : 'approved';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO cms_comments (post_id, author_name, author_email, content, status, parent_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$postId, $authorName, $authorEmail, $commentContent, $status, $parentId, $ipAddress, $userAgent]);
        
        if ($status === 'pending') {
            $message = 'Your comment is awaiting moderation.';
        } else {
            $message = 'Your comment has been posted successfully!';
        }
    }
}

// Get approved comments for this post
$commentsStmt = $pdo->prepare("
    SELECT * FROM cms_comments
    WHERE post_id = ? AND status = 'approved'
    ORDER BY created_at ASC
");
$commentsStmt->execute([$postId]);
$allComments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize comments into threaded structure
function organizeComments($comments, $parentId = null) {
    $result = [];
    foreach ($comments as $comment) {
        if ($comment['parent_id'] == $parentId) {
            $comment['replies'] = organizeComments($comments, $comment['id']);
            $result[] = $comment;
        }
    }
    return $result;
}
$comments = organizeComments($allComments);

// Get comment count
$commentCount = count($allComments);

// Get company name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Blog');

// Get author name
$authorName = $post['author_display_name'] ?? $post['author_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - <?php echo htmlspecialchars($companyName); ?> Blog</title>
    <meta name="description" content="<?php echo htmlspecialchars($post['excerpt'] ?? substr(strip_tags($post['content']), 0, 160)); ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            line-height: 1.7;
            color: #333;
            padding-top: 0 !important;
        }
        
        /* Header */
        .post-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6rem 2rem 4rem 2rem;
            margin-top: 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .post-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        .post-header-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }
        .post-category {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1.25rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }
        .post-header h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .post-meta-header {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            font-size: 0.95rem;
            opacity: 0.95;
        }
        .post-meta-header span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Featured Image */
        .featured-image-container {
            max-width: 1200px;
            margin: -3rem auto 3rem;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }
        .featured-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 4px solid white;
        }
        
        /* Main Content */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        .post-content-wrapper {
            background: white;
            border-radius: 16px;
            padding: 4rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
        }
        .post-body {
            font-size: 1.125rem;
            line-height: 1.9;
            color: #4a5568;
        }
        .post-body h2 {
            color: #1a202c;
            font-size: 2rem;
            font-weight: 700;
            margin: 2.5rem 0 1rem;
            line-height: 1.3;
        }
        .post-body h3 {
            color: #2d3748;
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
            margin: 2.5rem 0;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .post-body ul, .post-body ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
        }
        .post-body li {
            margin: 0.75rem 0;
        }
        .post-body blockquote {
            border-left: 4px solid #667eea;
            padding-left: 1.5rem;
            margin: 2rem 0;
            font-style: italic;
            color: #718096;
        }
        .post-body a {
            color: #667eea;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }
        .post-body a:hover {
            border-bottom-color: #667eea;
        }
        
        /* Back Button */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #764ba2;
            transform: translateX(-4px);
        }
        
        /* Comments Section */
        .comments-section {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
        }
        .comments-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .comment-count {
            color: #718096;
            font-size: 1rem;
            font-weight: 400;
        }
        
        /* Comment Form */
        .comment-form {
            background: #f7fafc;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 3rem;
        }
        .comment-form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        
        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .message-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        /* Comments List */
        .comments-list {
            margin-top: 2rem;
        }
        .comment {
            padding: 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .comment:last-child {
            border-bottom: none;
        }
        .comment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .comment-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .comment-author {
            font-weight: 600;
            color: #1a202c;
            font-size: 1rem;
        }
        .comment-date {
            color: #718096;
            font-size: 0.875rem;
            margin-left: auto;
        }
        .comment-content {
            color: #4a5568;
            line-height: 1.7;
            margin-left: 64px;
        }
        .comment-reply-link {
            display: inline-block;
            margin-top: 0.75rem;
            margin-left: 64px;
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        .comment-reply-link:hover {
            text-decoration: underline;
        }
        
        /* Nested Comments */
        .comment-replies {
            margin-left: 2rem;
            margin-top: 1.5rem;
            padding-left: 2rem;
            border-left: 3px solid #e2e8f0;
        }
        .comment-reply-form {
            display: none;
            margin-top: 1.5rem;
            margin-left: 64px;
            background: #f7fafc;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .comment-reply-form.active {
            display: block;
        }
        
        /* No Comments */
        .no-comments {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }
        .no-comments-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .post-header h1 {
                font-size: 2rem;
            }
            .featured-image {
                height: 300px;
            }
            .post-content-wrapper,
            .comments-section {
                padding: 2rem 1.5rem;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .comment-content,
            .comment-reply-link {
                margin-left: 0;
            }
            .comment-replies {
                margin-left: 0;
                padding-left: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main>
        <!-- Post Header -->
        <div class="post-header">
            <div class="post-header-content">
                <?php if ($post['category_name']): ?>
                    <div class="post-category"><?php echo htmlspecialchars($post['category_name']); ?></div>
                <?php endif; ?>
                <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="post-meta-header">
                    <span>👤 <?php echo htmlspecialchars($authorName); ?></span>
                    <?php if ($post['published_at']): ?>
                        <span>📅 <?php echo date('F j, Y', strtotime($post['published_at'])); ?></span>
                    <?php endif; ?>
                    <span>💬 <?php echo $commentCount; ?> Comment<?php echo $commentCount !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Featured Image -->
        <?php if (!empty($post['featured_image'])): ?>
            <div class="featured-image-container">
                <img src="<?php echo htmlspecialchars($baseUrl . '/' . $post['featured_image']); ?>" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>" 
                     class="featured-image"
                     onerror="this.style.display='none'">
            </div>
        <?php endif; ?>
        
        <div class="container">
            <!-- Back Link -->
            <a href="<?php echo $baseUrl; ?>/cms/blog" class="back-link">
                ← Back to Blog
            </a>
            
            <!-- Post Content -->
            <div class="post-content-wrapper">
                <div class="post-body">
                    <?php 
                    // Check if content contains HTML
                    $content = $post['content'] ?? '';
                    $hasHtml = (strpos($content, '<') !== false && strpos($content, '>') !== false);
                    
                    if ($hasHtml) {
                        // HTML content - output as HTML (already sanitized from admin)
                        echo $content;
                    } else {
                        // Plain text - use nl2br and escape
                        echo nl2br(htmlspecialchars($content));
                    }
                    ?>
                </div>
            </div>
            
            <!-- Comments Section -->
            <div class="comments-section">
                <h2 class="comments-title">
                    Comments
                    <span class="comment-count">(<?php echo $commentCount; ?>)</span>
                </h2>
                
                <?php if ($message): ?>
                    <div class="message message-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="message message-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- Comment Form -->
                <div class="comment-form">
                    <h3 class="comment-form-title">Leave a Comment</h3>
                    <form method="post" id="comment-form">
                        <input type="hidden" name="parent_id" id="parent_id" value="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="author_name">Name <span style="color: red;">*</span></label>
                                <input type="text" id="author_name" name="author_name" required 
                                       value="<?php echo htmlspecialchars($_POST['author_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="author_email">Email <span style="color: red;">*</span></label>
                                <input type="email" id="author_email" name="author_email" required
                                       value="<?php echo htmlspecialchars($_POST['author_email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="comment">Comment <span style="color: red;">*</span></label>
                            <textarea id="comment" name="comment" required><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="submit-btn">Post Comment</button>
                    </form>
                </div>
                
                <!-- Comments List -->
                <?php if (empty($comments)): ?>
                    <div class="no-comments">
                        <div class="no-comments-icon">💬</div>
                        <p>No comments yet. Be the first to comment!</p>
                    </div>
                <?php else: ?>
                    <div class="comments-list">
                        <?php
                        function renderComment($comment, $baseUrl) {
                            $initial = strtoupper(substr($comment['author_name'], 0, 1));
                            $date = date('F j, Y \a\t g:i A', strtotime($comment['created_at']));
                            ?>
                            <div class="comment" id="comment-<?php echo $comment['id']; ?>">
                                <div class="comment-header">
                                    <div class="comment-avatar"><?php echo htmlspecialchars($initial); ?></div>
                                    <div>
                                        <div class="comment-author"><?php echo htmlspecialchars($comment['author_name']); ?></div>
                                    </div>
                                    <div class="comment-date"><?php echo $date; ?></div>
                                </div>
                                <div class="comment-content">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                                <a href="#" class="comment-reply-link" onclick="replyToComment(<?php echo $comment['id']; ?>); return false;">
                                    Reply
                                </a>
                                
                                <!-- Reply Form (hidden by default) -->
                                <div class="comment-reply-form" id="reply-form-<?php echo $comment['id']; ?>">
                                    <h4 style="margin-bottom: 1rem; font-size: 1rem;">Reply to <?php echo htmlspecialchars($comment['author_name']); ?></h4>
                                    <form method="post">
                                        <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="reply_name_<?php echo $comment['id']; ?>">Name <span style="color: red;">*</span></label>
                                                <input type="text" id="reply_name_<?php echo $comment['id']; ?>" name="author_name" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="reply_email_<?php echo $comment['id']; ?>">Email <span style="color: red;">*</span></label>
                                                <input type="email" id="reply_email_<?php echo $comment['id']; ?>" name="author_email" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reply_comment_<?php echo $comment['id']; ?>">Comment <span style="color: red;">*</span></label>
                                            <textarea id="reply_comment_<?php echo $comment['id']; ?>" name="comment" required></textarea>
                                        </div>
                                        <button type="submit" name="submit_comment" class="submit-btn">Post Reply</button>
                                        <button type="button" class="submit-btn" style="background: #718096; margin-left: 0.5rem;" onclick="cancelReply(<?php echo $comment['id']; ?>); return false;">Cancel</button>
                                    </form>
                                </div>
                                
                                <!-- Nested Replies -->
                                <?php if (!empty($comment['replies'])): ?>
                                    <div class="comment-replies">
                                        <?php foreach ($comment['replies'] as $reply): ?>
                                            <?php renderComment($reply, $baseUrl); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                        
                        foreach ($comments as $comment) {
                            renderComment($comment, $baseUrl);
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
    
    <script>
        function replyToComment(commentId) {
            // Hide all other reply forms
            document.querySelectorAll('.comment-reply-form').forEach(form => {
                form.classList.remove('active');
            });
            
            // Show the reply form for this comment
            const replyForm = document.getElementById('reply-form-' + commentId);
            if (replyForm) {
                replyForm.classList.add('active');
                replyForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                
                // Focus on the name field
                const nameField = replyForm.querySelector('input[type="text"]');
                if (nameField) {
                    setTimeout(() => nameField.focus(), 100);
                }
            }
        }
        
        function cancelReply(commentId) {
            const replyForm = document.getElementById('reply-form-' + commentId);
            if (replyForm) {
                replyForm.classList.remove('active');
                replyForm.querySelector('form').reset();
            }
        }
        
        // Scroll to comment form if there's an error or message
        <?php if ($error || $message): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const commentForm = document.querySelector('.comment-form');
                if (commentForm) {
                    commentForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
