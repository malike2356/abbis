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
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
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
                if ($columnName === 'updated_at') {
                    // Add updated_at after created_at
                    try {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER created_at");
                    } catch (PDOException $e) {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                    }
                } elseif ($columnName === 'parent_id') {
                    // Try with AFTER clause first for parent_id
                    try {
                        // Check if user_agent exists first
                        $checkUserAgent = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = 'user_agent'");
                        $userAgentExists = $checkUserAgent->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
                        if ($userAgentExists) {
                            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER user_agent");
                        } else {
                            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                        }
                    } catch (PDOException $e) {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                    }
                } elseif ($columnName === 'ip_address') {
                    // Add ip_address after updated_at or user_agent
                    try {
                        // Check if updated_at exists first
                        $checkUpdatedAt = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = 'updated_at'");
                        $updatedAtExists = $checkUpdatedAt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
                        if ($updatedAtExists) {
                            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER updated_at");
                        } else {
                            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                        }
                    } catch (PDOException $e) {
                        $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                    }
                } elseif ($columnName === 'user_agent') {
                    // Add user_agent after updated_at
                    try {
                        // Check if updated_at exists first
                        $checkUpdatedAt = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_comments' AND COLUMN_NAME = 'updated_at'");
                        $updatedAtExists = $checkUpdatedAt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
                        if ($updatedAtExists) {
                            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition AFTER updated_at");
                        } else {
                            $pdo->exec("ALTER TABLE cms_comments ADD COLUMN $columnName $columnDefinition");
                        }
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

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$commentIds = $_POST['comment_ids'] ?? [];

if ($action && !empty($commentIds)) {
    $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
    $stmt = $pdo->prepare("UPDATE cms_comments SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
    
    switch ($action) {
        case 'approve':
            $stmt->execute(array_merge(['approved'], $commentIds));
            $message = count($commentIds) . ' comment(s) approved.';
            break;
        case 'unapprove':
            $stmt->execute(array_merge(['pending'], $commentIds));
            $message = count($commentIds) . ' comment(s) unapproved.';
            break;
        case 'spam':
            $stmt->execute(array_merge(['spam'], $commentIds));
            $message = count($commentIds) . ' comment(s) marked as spam.';
            break;
        case 'trash':
            $stmt->execute(array_merge(['trash'], $commentIds));
            $message = count($commentIds) . ' comment(s) moved to trash.';
            break;
        case 'delete':
            $deleteStmt = $pdo->prepare("DELETE FROM cms_comments WHERE id IN ($placeholders)");
            $deleteStmt->execute($commentIds);
            $message = count($commentIds) . ' comment(s) permanently deleted.';
            break;
    }
}

// Handle single comment action
$singleAction = $_GET['action'] ?? '';
$commentId = $_GET['comment_id'] ?? 0;
if ($singleAction && $commentId) {
    switch ($singleAction) {
        case 'approve':
            $pdo->prepare("UPDATE cms_comments SET status = 'approved', updated_at = NOW() WHERE id = ?")->execute([$commentId]);
            $message = 'Comment approved.';
            break;
        case 'unapprove':
            $pdo->prepare("UPDATE cms_comments SET status = 'pending', updated_at = NOW() WHERE id = ?")->execute([$commentId]);
            $message = 'Comment unapproved.';
            break;
        case 'spam':
            $pdo->prepare("UPDATE cms_comments SET status = 'spam', updated_at = NOW() WHERE id = ?")->execute([$commentId]);
            $message = 'Comment marked as spam.';
            break;
        case 'trash':
            $pdo->prepare("UPDATE cms_comments SET status = 'trash', updated_at = NOW() WHERE id = ?")->execute([$commentId]);
            $message = 'Comment moved to trash.';
            break;
        case 'delete':
            $pdo->prepare("DELETE FROM cms_comments WHERE id = ?")->execute([$commentId]);
            $message = 'Comment permanently deleted.';
            break;
    }
}

// Get filter and search
$filterStatus = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($filterStatus !== 'all') {
    $where[] = "c.status = ?";
    $params[] = $filterStatus;
}

if (!empty($search)) {
    $where[] = "(c.author_name LIKE ? OR c.author_email LIKE ? OR c.content LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_comments c $whereClause");
$countStmt->execute($params);
$totalComments = $countStmt->fetchColumn();
$totalPages = ceil($totalComments / $perPage);

// Get comments
$query = "SELECT c.*, p.title as post_title, p.slug as post_slug 
          FROM cms_comments c 
          LEFT JOIN cms_posts p ON p.id = c.post_id 
          $whereClause 
          ORDER BY c.created_at DESC 
          LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'all' => $pdo->query("SELECT COUNT(*) FROM cms_comments")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status='pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status='approved'")->fetchColumn(),
    'spam' => $pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status='spam'")->fetchColumn(),
    'trash' => $pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status='trash'")->fetchColumn(),
];

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();

// Helper function to get avatar initials
function getAvatarInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Helper function to get status color
function getStatusColor($status) {
    switch ($status) {
        case 'approved': return '#16a34a';
        case 'pending': return '#ea580c';
        case 'spam': return '#dc2626';
        case 'trash': return '#64748b';
        default: return '#64748b';
    }
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
    <style>
        .comments-wrapper {
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .comments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .comments-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #1d2327;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stat-card.active {
            border-color: var(--admin-primary);
            background: linear-gradient(135deg, var(--admin-primary-lighter) 0%, white 100%);
        }

        .stat-card.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--admin-primary);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 13px;
            color: #646970;
            font-weight: 500;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1d2327;
            line-height: 1;
        }

        .stat-icon {
            font-size: 24px;
            opacity: 0.6;
        }

        .filters-bar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 13px;
        }

        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
        }

        .bulk-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .bulk-actions select {
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 13px;
            background: white;
        }

        .bulk-actions button {
            padding: 8px 16px;
            background: var(--admin-primary);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .bulk-actions button:hover {
            background: var(--admin-primary-dark);
        }

        .bulk-actions button:disabled {
            background: #c3c4c7;
            cursor: not-allowed;
        }

        .comments-table-wrapper {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            overflow: hidden;
        }

        .comments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .comments-table thead {
            background: #f6f7f7;
            border-bottom: 2px solid #c3c4c7;
        }

        .comments-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #1d2327;
        }

        .comments-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #dcdcde;
            vertical-align: top;
        }

        .comments-table tbody tr:hover {
            background: #f6f7f7;
        }

        .comment-checkbox {
            width: 20px;
        }

        .comment-author {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .comment-author-info {
            flex: 1;
            min-width: 0;
        }

        .comment-author-name {
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .comment-author-email {
            font-size: 12px;
            color: #646970;
            word-break: break-all;
        }

        .comment-content {
            max-width: 500px;
            line-height: 1.6;
            color: #1d2327;
            font-size: 13px;
        }

        .comment-content-preview {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .comment-content-full {
            display: none;
        }

        .comment-expand {
            color: var(--admin-primary);
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .comment-expand:hover {
            text-decoration: underline;
        }

        .comment-post {
            font-size: 13px;
        }

        .comment-post-link {
            color: var(--admin-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .comment-post-link:hover {
            text-decoration: underline;
        }

        .comment-date {
            font-size: 12px;
            color: #646970;
        }

        .comment-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .comment-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .comment-status.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .comment-status.spam {
            background: #fee2e2;
            color: #991b1b;
        }

        .comment-status.trash {
            background: #f1f5f9;
            color: #475569;
        }

        .comment-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .comment-action-btn {
            padding: 4px 10px;
            font-size: 12px;
            border: 1px solid #c3c4c7;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #1d2327;
            transition: all 0.2s;
            display: inline-block;
        }

        .comment-action-btn:hover {
            background: #f6f7f7;
            border-color: #8c8f94;
        }

        .comment-action-btn.approve {
            color: #16a34a;
            border-color: #16a34a;
        }

        .comment-action-btn.approve:hover {
            background: #d1fae5;
        }

        .comment-action-btn.spam {
            color: #dc2626;
            border-color: #dc2626;
        }

        .comment-action-btn.spam:hover {
            background: #fee2e2;
        }

        .comment-action-btn.delete {
            color: #dc2626;
            border-color: #dc2626;
        }

        .comment-action-btn.delete:hover {
            background: #fee2e2;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 16px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            text-decoration: none;
            color: #1d2327;
            font-size: 13px;
            background: white;
        }

        .pagination a:hover {
            background: #f6f7f7;
            border-color: var(--admin-primary);
        }

        .pagination .current {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #646970;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: #1d2327;
            margin-bottom: 8px;
        }

        .message {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #16a34a;
        }

        .comment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .comment-modal.active {
            display: flex;
        }

        .comment-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .comment-modal-header {
            padding: 20px;
            border-bottom: 1px solid #c3c4c7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .comment-modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: #1d2327;
        }

        .comment-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #646970;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .comment-modal-close:hover {
            background: #f6f7f7;
        }

        .comment-modal-body {
            padding: 20px;
        }

        .comment-modal-section {
            margin-bottom: 24px;
        }

        .comment-modal-section h3 {
            font-size: 14px;
            color: #646970;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .comment-modal-section p {
            color: #1d2327;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 768px) {
            .comments-header {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-bar {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .comments-table {
                font-size: 12px;
            }

            .comment-author {
                min-width: auto;
            }

            .comment-content {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div class="comments-wrapper">
            <div class="comments-header">
                <h1>Comments</h1>
            </div>

            <?php if (isset($message)): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <a href="?status=all" class="stat-card <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">All Comments</div>
                            <div class="stat-value"><?php echo number_format($stats['all']); ?></div>
                        </div>
                        <div class="stat-icon">💬</div>
                    </div>
                </a>
                <a href="?status=pending" class="stat-card <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Pending</div>
                            <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                        </div>
                        <div class="stat-icon">⏳</div>
                    </div>
                </a>
                <a href="?status=approved" class="stat-card <?php echo $filterStatus === 'approved' ? 'active' : ''; ?>">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Approved</div>
                            <div class="stat-value"><?php echo number_format($stats['approved']); ?></div>
                        </div>
                        <div class="stat-icon">✅</div>
                    </div>
                </a>
                <a href="?status=spam" class="stat-card <?php echo $filterStatus === 'spam' ? 'active' : ''; ?>">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Spam</div>
                            <div class="stat-value"><?php echo number_format($stats['spam']); ?></div>
                        </div>
                        <div class="stat-icon">🚫</div>
                    </div>
                </a>
                <a href="?status=trash" class="stat-card <?php echo $filterStatus === 'trash' ? 'active' : ''; ?>">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Trash</div>
                            <div class="stat-value"><?php echo number_format($stats['trash']); ?></div>
                        </div>
                        <div class="stat-icon">🗑️</div>
                    </div>
                </a>
            </div>

            <form method="get" action="" class="filters-bar">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                <div class="search-box">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search comments...">
                </div>
                <button type="submit" style="padding: 8px 16px; background: var(--admin-primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="?status=<?php echo htmlspecialchars($filterStatus); ?>" style="padding: 8px 16px; background: #c3c4c7; color: #1d2327; text-decoration: none; border-radius: 4px;">Clear</a>
                <?php endif; ?>
            </form>

            <form method="post" action="" id="bulk-form">
                <input type="hidden" name="action" id="bulk-action" value="">
                <div class="filters-bar">
                    <div class="bulk-actions">
                        <select id="bulk-action-select" style="padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px;">
                            <option value="">Bulk Actions</option>
                            <?php if ($filterStatus !== 'approved'): ?>
                                <option value="approve">Approve</option>
                            <?php endif; ?>
                            <?php if ($filterStatus !== 'pending'): ?>
                                <option value="unapprove">Unapprove</option>
                            <?php endif; ?>
                            <?php if ($filterStatus !== 'spam'): ?>
                                <option value="spam">Mark as Spam</option>
                            <?php endif; ?>
                            <?php if ($filterStatus !== 'trash'): ?>
                                <option value="trash">Move to Trash</option>
                            <?php endif; ?>
                            <?php if ($filterStatus === 'trash'): ?>
                                <option value="delete">Delete Permanently</option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" id="bulk-submit" disabled>Apply</button>
                    </div>
                </div>

                <div class="comments-table-wrapper">
                    <?php if (empty($comments)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💬</div>
                            <h3>No comments found</h3>
                            <p><?php echo $filterStatus !== 'all' ? 'Try changing the filter or search term.' : 'Comments will appear here once visitors start commenting on your posts.'; ?></p>
                        </div>
                    <?php else: ?>
                        <table class="comments-table">
                            <thead>
                                <tr>
                                    <th class="comment-checkbox">
                                        <input type="checkbox" id="select-all">
                                    </th>
                                    <th>Author</th>
                                    <th>Comment</th>
                                    <th>In Response To</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comments as $comment): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="comment_ids[]" value="<?php echo $comment['id']; ?>" class="comment-checkbox-input">
                                        </td>
                                        <td>
                                            <div class="comment-author">
                                                <div class="comment-avatar"><?php echo htmlspecialchars(getAvatarInitials($comment['author_name'])); ?></div>
                                                <div class="comment-author-info">
                                                    <div class="comment-author-name"><?php echo htmlspecialchars($comment['author_name']); ?></div>
                                                    <div class="comment-author-email"><?php echo htmlspecialchars($comment['author_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="comment-content">
                                                <div class="comment-content-preview" id="preview-<?php echo $comment['id']; ?>">
                                                    <?php echo nl2br(htmlspecialchars(substr($comment['content'], 0, 150))); ?>
                                                    <?php if (strlen($comment['content']) > 150): ?>...<?php endif; ?>
                                                </div>
                                                <div class="comment-content-full" id="full-<?php echo $comment['id']; ?>">
                                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                                </div>
                                                <?php if (strlen($comment['content']) > 150): ?>
                                                    <a href="#" class="comment-expand" onclick="toggleComment(<?php echo $comment['id']; ?>); return false;">Show more</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="comment-post">
                                                <?php if ($comment['post_title']): ?>
                                                    <a href="<?php echo $baseUrl; ?>/cms/public/post.php?slug=<?php echo htmlspecialchars($comment['post_slug'] ?? ''); ?>" target="_blank" class="comment-post-link">
                                                        <?php echo htmlspecialchars($comment['post_title']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #646970;">N/A</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="comment-date">
                                                <?php echo date('M j, Y g:i a', strtotime($comment['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="comment-status <?php echo $comment['status']; ?>">
                                                <?php echo ucfirst($comment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="comment-actions">
                                                <?php if ($comment['status'] !== 'approved'): ?>
                                                    <a href="?action=approve&comment_id=<?php echo $comment['id']; ?>&status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page; ?>" class="comment-action-btn approve">Approve</a>
                                                <?php endif; ?>
                                                <?php if ($comment['status'] === 'approved'): ?>
                                                    <a href="?action=unapprove&comment_id=<?php echo $comment['id']; ?>&status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page; ?>" class="comment-action-btn">Unapprove</a>
                                                <?php endif; ?>
                                                <?php if ($comment['status'] !== 'spam'): ?>
                                                    <a href="?action=spam&comment_id=<?php echo $comment['id']; ?>&status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page; ?>" class="comment-action-btn spam">Spam</a>
                                                <?php endif; ?>
                                                <?php if ($comment['status'] !== 'trash'): ?>
                                                    <a href="?action=trash&comment_id=<?php echo $comment['id']; ?>&status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page; ?>" class="comment-action-btn">Trash</a>
                                                <?php endif; ?>
                                                <?php if ($comment['status'] === 'trash'): ?>
                                                    <a href="?action=delete&comment_id=<?php echo $comment['id']; ?>&status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page; ?>" class="comment-action-btn delete" onclick="return confirm('Are you sure you want to permanently delete this comment?');">Delete</a>
                                                <?php endif; ?>
                                                <a href="#" class="comment-action-btn" onclick="viewComment(<?php echo htmlspecialchars(json_encode($comment)); ?>); return false;">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page - 1; ?>">« Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $i; ?>" class="<?php echo $i === $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?status=<?php echo htmlspecialchars($filterStatus); ?>&search=<?php echo urlencode($search); ?>&p=<?php echo $page + 1; ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Comment Detail Modal -->
    <div class="comment-modal" id="comment-modal">
        <div class="comment-modal-content">
            <div class="comment-modal-header">
                <h2>Comment Details</h2>
                <button class="comment-modal-close" onclick="closeCommentModal()">&times;</button>
            </div>
            <div class="comment-modal-body" id="comment-modal-body">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Select all checkbox
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.comment-checkbox-input');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkButton();
        });

        // Update bulk action button state
        function updateBulkButton() {
            const checked = document.querySelectorAll('.comment-checkbox-input:checked');
            const submitBtn = document.getElementById('bulk-submit');
            const actionSelect = document.getElementById('bulk-action-select');
            
            if (submitBtn && actionSelect) {
                submitBtn.disabled = checked.length === 0 || !actionSelect.value;
            }
        }

        // Update on checkbox change
        document.querySelectorAll('.comment-checkbox-input').forEach(cb => {
            cb.addEventListener('change', updateBulkButton);
        });

        document.getElementById('bulk-action-select')?.addEventListener('change', updateBulkButton);

        // Handle bulk form submission
        document.getElementById('bulk-form')?.addEventListener('submit', function(e) {
            const action = document.getElementById('bulk-action-select').value;
            const checked = document.querySelectorAll('.comment-checkbox-input:checked');
            
            if (!action || checked.length === 0) {
                e.preventDefault();
                return false;
            }

            if (action === 'delete' && !confirm('Are you sure you want to permanently delete ' + checked.length + ' comment(s)?')) {
                e.preventDefault();
                return false;
            }

            document.getElementById('bulk-action').value = action;
        });

        // Toggle comment preview/full
        function toggleComment(id) {
            const preview = document.getElementById('preview-' + id);
            const full = document.getElementById('full-' + id);
            const link = preview?.nextElementSibling;
            
            if (preview && full && link) {
                if (preview.style.display === 'none') {
                    preview.style.display = '-webkit-box';
                    full.style.display = 'none';
                    link.textContent = 'Show more';
                } else {
                    preview.style.display = 'none';
                    full.style.display = 'block';
                    link.textContent = 'Show less';
                }
            }
        }

        // View comment details
        function viewComment(comment) {
            const modal = document.getElementById('comment-modal');
            const body = document.getElementById('comment-modal-body');
            
            if (!modal || !body) return;

            const statusColors = {
                'approved': '#16a34a',
                'pending': '#ea580c',
                'spam': '#dc2626',
                'trash': '#64748b'
            };

            body.innerHTML = `
                <div class="comment-modal-section">
                    <h3>Author</h3>
                    <p><strong>${escapeHtml(comment.author_name)}</strong><br>
                    ${escapeHtml(comment.author_email)}</p>
                </div>
                <div class="comment-modal-section">
                    <h3>Comment</h3>
                    <p style="white-space: pre-wrap;">${escapeHtml(comment.content)}</p>
                </div>
                <div class="comment-modal-section">
                    <h3>In Response To</h3>
                    <p>${comment.post_title ? escapeHtml(comment.post_title) : 'N/A'}</p>
                </div>
                <div class="comment-modal-section">
                    <h3>Date</h3>
                    <p>${new Date(comment.created_at).toLocaleString()}</p>
                </div>
                <div class="comment-modal-section">
                    <h3>Status</h3>
                    <p><span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: ${statusColors[comment.status] || '#64748b'}20; color: ${statusColors[comment.status] || '#64748b'};">${comment.status.charAt(0).toUpperCase() + comment.status.slice(1)}</span></p>
                </div>
                ${comment.ip_address ? `<div class="comment-modal-section"><h3>IP Address</h3><p>${escapeHtml(comment.ip_address)}</p></div>` : ''}
            `;
            
            modal.classList.add('active');
        }

        function closeCommentModal() {
            document.getElementById('comment-modal')?.classList.remove('active');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('comment-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCommentModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCommentModal();
            }
        });
    </script>
</body>
</html>
