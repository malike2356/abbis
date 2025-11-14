<?php
/**
 * Advanced User Management System (WordPress-like)
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure enhanced user tables exist
try {
    $pdo->query("SELECT first_name FROM cms_users LIMIT 1");
} catch (PDOException $e) {
    // Run migration - check each column before adding
    $columns = ['first_name', 'last_name', 'display_name', 'bio', 'avatar', 'status', 'last_login', 'login_count', 
                'email_verified', 'email_verification_token', 'password_reset_token', 'password_reset_expires', 
                'phone', 'website', 'location', 'timezone', 'language', 'updated_at'];
    
    $existingColumns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM cms_users");
        while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $col['Field'];
        }
    } catch (Exception $e) {}
    
    // Add missing columns
    foreach ($columns as $col) {
        if (!in_array($col, $existingColumns)) {
            try {
                switch ($col) {
                    case 'first_name':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN first_name VARCHAR(100) DEFAULT NULL AFTER username");
                        break;
                    case 'last_name':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN last_name VARCHAR(100) DEFAULT NULL AFTER first_name");
                        break;
                    case 'display_name':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN display_name VARCHAR(100) DEFAULT NULL AFTER last_name");
                        break;
                    case 'bio':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN bio TEXT DEFAULT NULL AFTER email");
                        break;
                    case 'avatar':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER bio");
                        break;
                    case 'status':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN status ENUM('active','inactive','pending','suspended') DEFAULT 'active' AFTER role");
                        break;
                    case 'last_login':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER status");
                        break;
                    case 'login_count':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN login_count INT DEFAULT 0 AFTER last_login");
                        break;
                    case 'email_verified':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER login_count");
                        break;
                    case 'email_verification_token':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN email_verification_token VARCHAR(255) DEFAULT NULL AFTER email_verified");
                        break;
                    case 'password_reset_token':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN password_reset_token VARCHAR(255) DEFAULT NULL AFTER email_verification_token");
                        break;
                    case 'password_reset_expires':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN password_reset_expires TIMESTAMP NULL DEFAULT NULL AFTER password_reset_token");
                        break;
                    case 'phone':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER password_reset_expires");
                        break;
                    case 'website':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN website VARCHAR(255) DEFAULT NULL AFTER phone");
                        break;
                    case 'location':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER website");
                        break;
                    case 'timezone':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN timezone VARCHAR(50) DEFAULT 'UTC' AFTER location");
                        break;
                    case 'language':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN language VARCHAR(10) DEFAULT 'en' AFTER timezone");
                        break;
                    case 'updated_at':
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
                        break;
                }
            } catch (PDOException $e2) {
                // Column might already exist or other error
            }
        }
    }
    
    // Update role enum
    try {
        $pdo->exec("ALTER TABLE cms_users MODIFY COLUMN role ENUM('admin','editor','author','contributor','subscriber') DEFAULT 'subscriber'");
    } catch (PDOException $e) {}
    
    // Set default status for existing users
    try {
        $pdo->exec("UPDATE cms_users SET status='active' WHERE status IS NULL OR status=''");
    } catch (PDOException $e) {}
    
    // Create additional tables
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_user_capabilities (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          capability VARCHAR(100) NOT NULL,
          granted TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE CASCADE,
          UNIQUE KEY unique_user_capability (user_id, capability),
          INDEX idx_user (user_id),
          INDEX idx_capability (capability)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_user_meta (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          meta_key VARCHAR(255) NOT NULL,
          meta_value LONGTEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE CASCADE,
          INDEX idx_user (user_id),
          INDEX idx_meta_key (meta_key),
          UNIQUE KEY unique_user_meta (user_id, meta_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_user_activity (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          action VARCHAR(100) NOT NULL,
          description TEXT,
          ip_address VARCHAR(45) DEFAULT NULL,
          user_agent TEXT DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE CASCADE,
          INDEX idx_user (user_id),
          INDEX idx_action (action),
          INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = null;
$error = null;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = $_POST['bulk_action'];
    $userIds = $_POST['users'] ?? [];
    
    if (!empty($userIds)) {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        
        switch ($bulkAction) {
            case 'delete':
                // Prevent deleting yourself
                $userIds = array_filter($userIds, function($uid) {
                    return $uid != $_SESSION['cms_user_id'];
                });
                if (!empty($userIds)) {
                    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                    $pdo->prepare("DELETE FROM cms_users WHERE id IN ($placeholders)")->execute($userIds);
                    $message = count($userIds) . ' user(s) deleted';
                }
                break;
            case 'activate':
                $pdo->prepare("UPDATE cms_users SET status='active' WHERE id IN ($placeholders)")->execute($userIds);
                $message = count($userIds) . ' user(s) activated';
                break;
            case 'deactivate':
                // Prevent deactivating yourself
                $userIds = array_filter($userIds, function($uid) {
                    return $uid != $_SESSION['cms_user_id'];
                });
                if (!empty($userIds)) {
                    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                    $pdo->prepare("UPDATE cms_users SET status='inactive' WHERE id IN ($placeholders)")->execute($userIds);
                    $message = count($userIds) . ' user(s) deactivated';
                }
                break;
            case 'change_role':
                $newRole = $_POST['new_role'] ?? '';
                if ($newRole) {
                    $pdo->prepare("UPDATE cms_users SET role=? WHERE id IN ($placeholders)")->execute(array_merge([$newRole], $userIds));
                    $message = count($userIds) . ' user(s) role changed';
                }
                break;
        }
    }
}

// Handle single user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $userId = $_POST['user_id'] ?? null;
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? 'subscriber';
        $status = $_POST['status'] ?? 'active';
        $bio = trim($_POST['bio'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $timezone = $_POST['timezone'] ?? 'UTC';
        $language = $_POST['language'] ?? 'en';
        $newPassword = $_POST['new_password'] ?? '';
        
        // Handle avatar upload
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $rootPath . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 2000000) { // 2MB
                $filename = 'avatar_' . ($userId ?: uniqid()) . '.' . $ext;
                $filepath = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $avatarPath = 'uploads/avatars/' . $filename;
                }
            }
        }
        
        if ($userId) {
            // Update existing user
            $updateFields = ['username', 'email', 'first_name', 'last_name', 'display_name', 'role', 'status', 'bio', 'phone', 'website', 'location', 'timezone', 'language'];
            $updateValues = [$username, $email, $firstName, $lastName, $displayName, $role, $status, $bio, $phone, $website, $location, $timezone, $language];
            
            if ($avatarPath) {
                $updateFields[] = 'avatar';
                $updateValues[] = $avatarPath;
            }
            
            if (!empty($newPassword)) {
                $updateFields[] = 'password_hash';
                $updateValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            
            $updateValues[] = $userId;
            $setClause = implode('=?, ', $updateFields) . '=?';
            $stmt = $pdo->prepare("UPDATE cms_users SET $setClause WHERE id=?");
            $stmt->execute($updateValues);
            $message = 'User updated successfully';
        } else {
            // Create new user
            if (empty($newPassword)) {
                $error = 'Password is required for new users';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $displayName = $displayName ?: $username;
                
                $fields = ['username', 'email', 'first_name', 'last_name', 'display_name', 'password_hash', 'role', 'status', 'bio', 'phone', 'website', 'location', 'timezone', 'language'];
                $values = [$username, $email, $firstName, $lastName, $displayName, $hash, $role, $status, $bio, $phone, $website, $location, $timezone, $language];
                
                if ($avatarPath) {
                    $fields[] = 'avatar';
                    $values[] = $avatarPath;
                }
                
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $fieldsList = implode(', ', $fields);
                $stmt = $pdo->prepare("INSERT INTO cms_users ($fieldsList) VALUES ($placeholders)");
                $stmt->execute($values);
                $message = 'User created successfully';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $deleteId = $_POST['user_id'];
        if ($deleteId != $_SESSION['cms_user_id']) {
            $pdo->prepare("DELETE FROM cms_users WHERE id=?")->execute([$deleteId]);
            $message = 'User deleted';
        } else {
            $error = 'You cannot delete your own account';
        }
    } elseif (isset($_POST['send_password_reset'])) {
        $resetId = $_POST['user_id'];
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $pdo->prepare("UPDATE cms_users SET password_reset_token=?, password_reset_expires=? WHERE id=?")->execute([$token, $expires, $resetId]);
        $message = 'Password reset email sent (token generated)';
    }
}

// Get user for editing
$user = null;
if ($id && ($action === 'edit' || $action === 'profile')) {
    $stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get users with search and filters
$search = $_GET['s'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['paged'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR display_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($roleFilter) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);
$totalUsers = $pdo->prepare("SELECT COUNT(*) FROM cms_users WHERE $whereClause");
$totalUsers->execute($params);
$totalUsers = $totalUsers->fetchColumn();

// Prepare query params for LIMIT/OFFSET
$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;

try {
    $users = $pdo->prepare("SELECT u.*, 
        (SELECT COUNT(*) FROM cms_posts WHERE created_by=u.id) as post_count,
        (SELECT COUNT(*) FROM cms_pages WHERE created_by=u.id) as page_count
        FROM cms_users u 
        WHERE $whereClause 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?");
    $users->execute($queryParams);
    $users = $users->fetchAll();
} catch (Exception $e) {
    // Fallback if created_by columns don't exist
    $users = $pdo->prepare("SELECT u.* FROM cms_users u WHERE $whereClause ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $users->execute($queryParams);
    $users = $users->fetchAll();
}

// Ensure all expected fields exist with defaults
foreach ($users as &$u) {
    $u['post_count'] = $u['post_count'] ?? 0;
    $u['page_count'] = $u['page_count'] ?? 0;
    $u['last_login'] = $u['last_login'] ?? null;
    $u['login_count'] = $u['login_count'] ?? 0;
    $u['status'] = $u['status'] ?? 'active';
    $u['first_name'] = $u['first_name'] ?? '';
    $u['last_name'] = $u['last_name'] ?? '';
    $u['display_name'] = $u['display_name'] ?? $u['username'];
    $u['avatar'] = $u['avatar'] ?? '';
}
unset($u); // Unset reference

$roles = ['admin' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author', 'contributor' => 'Contributor', 'subscriber' => 'Subscriber'];
$statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending', 'suspended' => 'Suspended'];

// Calculate statistics
$statsQuery = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) as suspended,
    SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role='editor' THEN 1 ELSE 0 END) as editors,
    SUM(CASE WHEN last_login IS NOT NULL AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_last_month
    FROM cms_users");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.2/jquery-ui.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <?php 
    $currentPage = 'users';
    include 'header.php'; 
    ?>
    <style>
        .users-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 20px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .stat-card.active { border-left-color: #00a32a; }
        .stat-card.inactive { border-left-color: #dcdcde; }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.suspended { border-left-color: #dc3232; }
        .stat-card.admins { border-left-color: #9333ea; }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1d2327;
            margin: 10px 0 5px 0;
        }
        .stat-label {
            color: #646970;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .users-search-filters {
            background: white;
            border: 1px solid #c3c4c7;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .search-filters-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .search-filters-row > div {
            flex: 1;
            min-width: 200px;
        }
        .search-filters-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #1d2327;
            font-size: 13px;
        }
        .search-filters-row input,
        .search-filters-row select {
            width: 100%;
            padding: 10px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
        }
        .search-filters-row input:focus,
        .search-filters-row select:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }
        .view-toggle {
            display: flex;
            gap: 5px;
            background: #f6f7f7;
            padding: 4px;
            border-radius: 4px;
        }
        .view-toggle button {
            padding: 8px 16px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .view-toggle button.active {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .user-avatar { 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            object-fit: cover; 
            vertical-align: middle; 
            margin-right: 12px;
            border: 2px solid #e5e7eb;
        }
        .user-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            margin-right: 12px;
            vertical-align: middle;
        }
        .user-status { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .user-status-active { background: #d1fae5; color: #065f46; }
        .user-status-inactive { background: #fee2e2; color: #991b1b; }
        .user-status-pending { background: #fef3c7; color: #92400e; }
        .user-status-suspended { background: #e5e7eb; color: #374151; }
        .user-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .user-actions a, .user-actions button { 
            padding: 6px 12px; 
            font-size: 12px; 
            border-radius: 4px;
            text-decoration: none;
        }
        .bulk-actions { 
            margin-bottom: 20px; 
            padding: 15px; 
            background: #f0f9ff; 
            border: 1px solid #2271b1; 
            border-radius: 8px;
            display: none; 
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .bulk-actions.active { display: flex; }
        .bulk-actions select {
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
        }
        .user-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .user-form-avatar { text-align: center; }
        .user-form-avatar img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #c3c4c7; }
        .pagination { 
            margin-top: 20px; 
            display: flex; 
            gap: 5px; 
            justify-content: center;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span { 
            padding: 8px 12px; 
            border: 1px solid #c3c4c7; 
            text-decoration: none; 
            border-radius: 4px;
            color: #2271b1;
        }
        .pagination .current { 
            background: #2271b1; 
            color: white; 
            border-color: #2271b1; 
        }
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        .user-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            position: relative;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
        }
        .user-card:hover {
            box-shadow: 0 24px 44px rgba(37, 99, 235, 0.15);
            transform: translateY(-6px);
            border-color: #2563eb;
        }
        .user-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 18px;
            gap: 16px;
        }
        .user-card-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(37, 99, 235, 0.25);
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18);
        }
        .user-card-avatar-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 26px;
            flex-shrink: 0;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18);
        }
        .user-card-info {
            flex: 1;
            min-width: 0;
        }
        .user-card-name {
            font-size: 17px;
            font-weight: 600;
            color: #0f172a;
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-card-username {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
        .user-card-meta {
            display: grid;
            gap: 14px;
            margin: 18px 0;
            padding: 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.06) 0%, rgba(14, 116, 144, 0.03) 100%);
            border: 1px solid rgba(148, 163, 184, 0.25);
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
        .user-card-meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .user-card-meta-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.65px;
        }
        .user-card-meta-value {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }
        .user-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #646970;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        .user-edit-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .user-edit-hero {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            flex-wrap: wrap;
            gap: 24px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 40%, #0f172a 100%);
            border-radius: 20px;
            padding: 28px;
            color: #e2e8f0;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
            position: relative;
            overflow: hidden;
        }
        .user-edit-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 50%),
                        radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.28), transparent 55%);
            pointer-events: none;
        }
        .user-hero-primary {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1 1 320px;
        }
        .user-hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .user-hero-pill {
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(6px);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #e2e8f0;
        }
        .user-hero-pill.role {
            background: rgba(124, 58, 237, 0.35);
        }
        .user-hero-pill.status-active {
            background: rgba(34, 197, 94, 0.35);
        }
        .user-hero-pill.status-inactive,
        .user-hero-pill.status-suspended {
            background: rgba(239, 68, 68, 0.35);
        }
        .user-hero-title {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: #fff;
        }
        .user-hero-subtitle {
            margin: 0;
            font-size: 14px;
            color: rgba(226, 232, 240, 0.85);
        }
        .user-hero-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 10px;
            color: rgba(226, 232, 240, 0.75);
            font-size: 13px;
        }
        .user-hero-avatar {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }
        .user-hero-avatar figure {
            width: 140px;
            height: 140px;
            border-radius: 24px;
            border: 3px solid rgba(255, 255, 255, 0.55);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.25);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.4);
            font-size: 52px;
            font-weight: 700;
            color: #fff;
        }
        .user-hero-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-upload-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .avatar-upload-trigger:hover {
            background: rgba(255, 255, 255, 0.22);
        }
        .user-edit-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
            gap: 24px;
        }
        .user-edit-main {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .user-edit-section {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
            padding: 22px 24px;
        }
        .user-edit-section header {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }
        .user-edit-section h2 {
            margin: 0;
            font-size: 20px;
            color: #0f172a;
        }
        .user-edit-section p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }
        .user-field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .user-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .user-field label {
            font-weight: 600;
            font-size: 13px;
            color: #0f172a;
        }
        .user-field input,
        .user-field select,
        .user-field textarea {
            border: 1px solid #cbd5f5;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            color: #1e293b;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #fff;
        }
        .user-field textarea {
            resize: vertical;
            min-height: 120px;
        }
        .user-field input:focus,
        .user-field select:focus,
        .user-field textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .user-field .description {
            font-size: 12px;
            color: #64748b;
        }
        .user-field.full {
            grid-column: 1 / -1;
        }
        .user-edit-sidebar {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .user-side-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 20px 22px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .user-side-card h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: #0f172a;
        }
        .user-side-summary {
            display: grid;
            gap: 10px;
        }
        .user-side-summary span {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #475569;
        }
        .user-side-summary strong {
            color: #0f172a;
        }
        .user-side-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .user-side-btn.primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: #fff;
        }
        .user-side-btn.danger {
            background: linear-gradient(135deg, #f97316 0%, #dc2626 100%);
            color: #fff;
        }
        .user-side-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18);
        }
        .user-edit-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 16px 20px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .user-edit-actions .left {
            font-size: 13px;
            color: #64748b;
        }
        .user-edit-actions .right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .user-edit-save {
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 12px 22px rgba(37, 99, 235, 0.25);
        }
        .user-edit-cancel {
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid #cbd5f5;
            background: #fff;
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
        }
        @media (max-width: 1024px) {
            .user-edit-grid {
                grid-template-columns: 1fr;
            }
            .user-edit-sidebar {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .user-side-card {
                flex: 1 1 280px;
            }
        }
        @media (max-width: 768px) {
            .user-edit-hero {
                padding: 20px;
            }
            .user-hero-title {
                font-size: 26px;
            }
            .user-edit-section {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="margin: 0;"><?php echo $action === 'edit' ? '✏️ Edit User' : ($action === 'add' ? '➕ Add New User' : '👥 Users'); ?></h1>
        </div>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <?php
                $isEditing = $action === 'edit';
                $roleKey = $user['role'] ?? 'subscriber';
                $statusKey = $user['status'] ?? 'active';
                $roleLabel = $roles[$roleKey] ?? ucfirst($roleKey);
                $statusLabel = $statuses[$statusKey] ?? ucfirst($statusKey);
                $userDisplayName = $user['display_name'] ?? ($user['username'] ?? 'New User');
                $userEmail = $user['email'] ?? '';
                $userInitial = strtoupper(substr($userDisplayName, 0, 1));
                $lastLogin = $user['last_login'] ?? null;
                $loginCount = (int) ($user['login_count'] ?? 0);
                $registeredAt = $user['created_at'] ?? null;
            ?>
            <form method="post" enctype="multipart/form-data" class="user-edit-form">
                <input type="hidden" name="user_id" value="<?php echo $user['id'] ?? ''; ?>">

                <div class="user-edit-shell">
                    <section class="user-edit-hero">
                        <div class="user-hero-primary">
                            <div class="user-hero-meta">
                                <span class="user-hero-pill role"><?php echo htmlspecialchars($roleLabel); ?></span>
                                <span class="user-hero-pill status-<?php echo htmlspecialchars($statusKey); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                <?php if ($isEditing): ?>
                                    <span class="user-hero-pill">User ID #<?php echo (int) $user['id']; ?></span>
                                <?php endif; ?>
                            </div>
                            <h2 class="user-hero-title"><?php echo htmlspecialchars($userDisplayName); ?></h2>
                            <p class="user-hero-subtitle"><?php echo $userEmail ? htmlspecialchars($userEmail) : 'Email will be required to notify this user.'; ?></p>
                            <div class="user-hero-inline">
                                <span>Username: <strong><?php echo htmlspecialchars($user['username'] ?? 'pending'); ?></strong></span>
                                <?php if ($isEditing && $lastLogin): ?>
                                    <span>Last Login: <strong><?php echo date('M j, Y H:i', strtotime($lastLogin)); ?></strong></span>
                                <?php endif; ?>
                                <?php if ($isEditing): ?>
                                    <span>Logins: <strong><?php echo $loginCount; ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="user-hero-avatar">
                            <figure>
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="<?php echo $baseUrl . '/' . htmlspecialchars($user['avatar']); ?>" alt="Profile picture" id="avatar-preview">
                                <?php else: ?>
                                    <img src="" alt="Profile picture" id="avatar-preview" style="display:none;">
                                    <span id="avatar-placeholder"><?php echo htmlspecialchars($userInitial); ?></span>
                                <?php endif; ?>
                            </figure>
                            <label class="avatar-upload-trigger" for="avatar-input">
                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                <span><?php echo $isEditing ? 'Change picture' : 'Upload picture'; ?></span>
                            </label>
                            <input type="file" name="avatar" id="avatar-input" accept="image/*" onchange="previewAvatar(this)" style="display:none;">
                            <small style="font-size:11px; color: rgba(226,232,240,0.75);">JPG, PNG, GIF &mdash; max 2MB</small>
                        </div>
                    </section>

                    <div class="user-edit-grid">
                        <div class="user-edit-main">
                            <section class="user-edit-section">
                                <header>
                                    <h2>Account Access</h2>
                                    <p>Credentials and permissions required to sign in.</p>
                                </header>
                                <div class="user-field-grid">
                                    <div class="user-field">
                                        <label>Username <span style="color:#dc2626;">*</span></label>
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                    </div>
                                    <div class="user-field">
                                        <label>Email <span style="color:#dc2626;">*</span></label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="user-field">
                                        <label>Role <span style="color:#dc2626;">*</span></label>
                                        <select name="role" required>
                                            <?php foreach ($roles as $roleValue => $roleName): ?>
                                                <option value="<?php echo $roleValue; ?>" <?php echo $roleKey === $roleValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($roleName); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="user-field">
                                        <label>Status <span style="color:#dc2626;">*</span></label>
                                        <select name="status" required>
                                            <?php foreach ($statuses as $statusValue => $statusName): ?>
                                                <option value="<?php echo $statusValue; ?>" <?php echo $statusKey === $statusValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusName); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="user-field full">
                                        <label><?php echo $isEditing ? 'New password' : 'Password'; ?><?php echo $isEditing ? '' : ' <span style="color:#dc2626;">*</span>'; ?></label>
                                        <input type="password" name="new_password" <?php echo $isEditing ? '' : 'required'; ?> placeholder="<?php echo $isEditing ? 'Leave blank to keep current password' : 'Minimum 8 characters'; ?>">
                                    </div>
                                </div>
                            </section>

                            <section class="user-edit-section">
                                <header>
                                    <h2>Profile Details</h2>
                                    <p>Personal information displayed across internal tools.</p>
                                </header>
                                <div class="user-field-grid">
                                    <div class="user-field">
                                        <label>First name</label>
                                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="user-field">
                                        <label>Last name</label>
                                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                    </div>
                                    <div class="user-field">
                                        <label>Display name</label>
                                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ($user['username'] ?? '')); ?>">
                                        <span class="description">Shown on public-facing areas.</span>
                                    </div>
                                    <div class="user-field full">
                                        <label>Biographical info</label>
                                        <textarea name="bio" rows="4" placeholder="Share a short bio or responsibilities."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </section>

                            <section class="user-edit-section">
                                <header>
                                    <h2>Contact & Preferences</h2>
                                    <p>How we reach the user and their localisation settings.</p>
                                </header>
                                <div class="user-field-grid">
                                    <div class="user-field">
                                        <label>Phone</label>
                                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="user-field">
                                        <label>Website</label>
                                        <input type="url" name="website" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>" placeholder="https://">
                                    </div>
                                    <div class="user-field">
                                        <label>Location</label>
                                        <input type="text" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                                    </div>
                                    <div class="user-field">
                                        <label>Timezone</label>
                                        <select name="timezone">
                                            <?php $timezone = $user['timezone'] ?? 'UTC'; ?>
                                            <option value="UTC" <?php echo $timezone === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            <option value="Africa/Accra" <?php echo $timezone === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra</option>
                                            <option value="America/New_York" <?php echo $timezone === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                            <option value="Europe/London" <?php echo $timezone === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                        </select>
                                    </div>
                                    <div class="user-field">
                                        <label>Language</label>
                                        <select name="language">
                                            <?php $language = $user['language'] ?? 'en'; ?>
                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="fr" <?php echo $language === 'fr' ? 'selected' : ''; ?>>French</option>
                                            <option value="es" <?php echo $language === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                        </select>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <aside class="user-edit-sidebar">
                            <div class="user-side-card">
                                <h3>Account Snapshot</h3>
                                <div class="user-side-summary">
                                    <span>Role <strong><?php echo htmlspecialchars($roleLabel); ?></strong></span>
                                    <span>Status <strong><?php echo htmlspecialchars($statusLabel); ?></strong></span>
                                    <?php if ($isEditing && $registeredAt): ?>
                                        <span>Created <strong><?php echo date('M j, Y H:i', strtotime($registeredAt)); ?></strong></span>
                                    <?php endif; ?>
                                    <?php if ($isEditing): ?>
                                        <span>Logins <strong><?php echo $loginCount; ?></strong></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isEditing): ?>
                                <div class="user-side-card">
                                    <h3>Quick Actions</h3>
                                    <button type="submit" name="send_password_reset" value="1" class="user-side-btn primary">
                                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                                        Send password reset
                                    </button>
                                    <?php if (($user['id'] ?? null) != ($_SESSION['cms_user_id'] ?? null)): ?>
                                        <button type="submit" name="delete_user" value="1" class="user-side-btn danger" onclick="return confirm('Delete this user? This action cannot be undone.');">
                                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                            Delete user
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </aside>
                    </div>

                    <div class="user-edit-actions">
                        <div class="left">
                            <?php echo $isEditing ? 'Last updated automatically when you save.' : 'All fields marked * are required to create the account.'; ?>
                        </div>
                        <div class="right">
                            <a href="users.php" class="user-edit-cancel">Cancel</a>
                            <button type="submit" name="save_user" class="user-edit-save">Save changes</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <!-- Statistics Cards -->
            <div class="users-stats">
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                </div>
                <div class="stat-card active">
                    <div class="stat-label">Active</div>
                    <div class="stat-value"><?php echo $stats['active'] ?? 0; ?></div>
                </div>
                <div class="stat-card inactive">
                    <div class="stat-label">Inactive</div>
                    <div class="stat-value"><?php echo $stats['inactive'] ?? 0; ?></div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                </div>
                <div class="stat-card suspended">
                    <div class="stat-label">Suspended</div>
                    <div class="stat-value"><?php echo $stats['suspended'] ?? 0; ?></div>
                </div>
                <div class="stat-card admins">
                    <div class="stat-label">Administrators</div>
                    <div class="stat-value"><?php echo $stats['admins'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active (30 days)</div>
                    <div class="stat-value"><?php echo $stats['active_last_month'] ?? 0; ?></div>
                </div>
            </div>
            
            <!-- Header with Actions -->
            <div class="users-header">
                <a href="?action=add" class="page-title-action button button-primary">➕ Add New User</a>
                <div class="view-toggle">
                    <button class="view-btn active" onclick="setView('table')" data-view="table">📋 Table</button>
                    <button class="view-btn" onclick="setView('grid')" data-view="grid">🔲 Grid</button>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="users-search-filters">
                <form method="get" class="search-filters-row">
                    <div>
                        <label>Search Users</label>
                        <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, username...">
                    </div>
                    <div>
                        <label>Filter by Role</label>
                        <select name="role" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $roleValue => $roleName): ?>
                                <option value="<?php echo $roleValue; ?>" <?php echo $roleFilter === $roleValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($roleName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Filter by Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $statusValue => $statusName): ?>
                                <option value="<?php echo $statusValue; ?>" <?php echo $statusFilter === $statusValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($statusName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <button type="submit" class="button button-primary">🔍 Search</button>
                        <?php if ($search || $roleFilter || $statusFilter): ?>
                            <a href="users.php" class="button">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <form method="post" id="users-form">
                <div class="bulk-actions" id="bulk-actions">
                    <select name="bulk_action" required>
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="change_role">Change Role</option>
                        <option value="delete">Delete</option>
                    </select>
                    <select name="new_role" id="new_role_select" style="display: none;">
                        <?php foreach ($roles as $roleValue => $roleName): ?>
                            <option value="<?php echo $roleValue; ?>"><?php echo htmlspecialchars($roleName); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">Apply</button>
                    <span id="selected-count" style="color: #646970; font-weight: 600;">0 items selected</span>
                </div>
                
                <!-- Table View -->
                <div id="table-view">
                    <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="select-all"></th>
                            <th>User</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Posts</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 60px 20px; color: #646970;">
                                    <div class="empty-state-icon" style="font-size: 48px; margin-bottom: 15px;">👥</div>
                                    <h3 style="margin: 0 0 10px 0; color: #1d2327;">No Users Found</h3>
                                    <p style="margin: 0;">No users match your search criteria.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="users[]" value="<?php echo $u['id']; ?>" class="user-checkbox">
                                    </td>
                                    <td>
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="<?php echo $baseUrl . '/' . htmlspecialchars($u['avatar']); ?>" alt="" class="user-avatar">
                                        <?php else: ?>
                                            <div class="user-avatar-placeholder">
                                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <strong><a href="?action=edit&id=<?php echo $u['id']; ?>" style="color: #2271b1; text-decoration: none;"><?php echo htmlspecialchars($u['username']); ?></a></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['display_name'] ?? $u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars($roles[$u['role']] ?? $u['role']); ?></td>
                                    <td><?php echo $u['post_count'] ?? 0; ?></td>
                                    <td>
                                        <?php 
                                        $userStatus = $u['status'] ?? 'active';
                                        if (!isset($statuses[$userStatus])) {
                                            $userStatus = 'active'; // Default to active if status not recognized
                                        }
                                        ?>
                                        <span class="user-status user-status-<?php echo $userStatus; ?>">
                                            <?php echo htmlspecialchars($statuses[$userStatus] ?? 'Active'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['last_login'])): ?>
                                            <?php echo date('Y/m/d H:i', strtotime($u['last_login'])); ?>
                                        <?php else: ?>
                                            Never
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="user-actions">
                                            <a href="?action=edit&id=<?php echo $u['id']; ?>" class="button button-small">Edit</a>
                                            <?php if ($u['id'] != $_SESSION['cms_user_id']): ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="submit" name="delete_user" value="Delete" style="background: #d63638; color: white; border: none; padding: 4px 8px; cursor: pointer; border-radius: 3px;">
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
                
                <!-- Grid View -->
                <div id="grid-view" style="display: none;">
                    <div class="users-grid">
                        <?php if (empty($users)): ?>
                            <div class="empty-state" style="grid-column: 1 / -1;">
                                <div class="empty-state-icon">👥</div>
                                <h3>No Users Found</h3>
                                <p>No users match your search criteria.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <div class="user-card">
                                    <div class="user-card-header">
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="<?php echo $baseUrl . '/' . htmlspecialchars($u['avatar']); ?>" alt="" class="user-card-avatar">
                                        <?php else: ?>
                                            <div class="user-card-avatar-placeholder">
                                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="user-card-info">
                                            <h3 class="user-card-name">
                                                <a href="?action=edit&id=<?php echo $u['id']; ?>" style="color: #1d2327; text-decoration: none;">
                                                    <?php echo htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['display_name'] ?? $u['username']); ?>
                                                </a>
                                            </h3>
                                            <p class="user-card-username">@<?php echo htmlspecialchars($u['username']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <?php 
                                        $userStatus = $u['status'] ?? 'active';
                                        if (!isset($statuses[$userStatus])) {
                                            $userStatus = 'active';
                                        }
                                        ?>
                                        <span class="user-status user-status-<?php echo $userStatus; ?>">
                                            <?php echo htmlspecialchars($statuses[$userStatus] ?? 'Active'); ?>
                                        </span>
                                        <span style="margin-left: 8px; color: #646970; font-size: 13px;">
                                            <?php echo htmlspecialchars($roles[$u['role']] ?? $u['role']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="user-card-meta">
                                        <div class="user-card-meta-item">
                                            <span class="user-card-meta-label">Posts</span>
                                            <span class="user-card-meta-value"><?php echo $u['post_count'] ?? 0; ?></span>
                                        </div>
                                        <div class="user-card-meta-item">
                                            <span class="user-card-meta-label">Pages</span>
                                            <span class="user-card-meta-value"><?php echo $u['page_count'] ?? 0; ?></span>
                                        </div>
                                        <div class="user-card-meta-item">
                                            <span class="user-card-meta-label">Logins</span>
                                            <span class="user-card-meta-value"><?php echo $u['login_count'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div style="font-size: 12px; color: #646970; margin: 10px 0;">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($u['email']); ?><br>
                                        <?php if (!empty($u['last_login'])): ?>
                                            <strong>Last Login:</strong> <?php echo date('M j, Y H:i', strtotime($u['last_login'])); ?>
                                        <?php else: ?>
                                            <strong>Last Login:</strong> Never
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="user-card-actions">
                                        <input type="checkbox" name="users[]" value="<?php echo $u['id']; ?>" class="user-checkbox" style="margin-right: auto;">
                                        <a href="?action=edit&id=<?php echo $u['id']; ?>" class="button button-small">Edit</a>
                                        <?php if ($u['id'] != $_SESSION['cms_user_id']): ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" name="delete_user" style="background: #d63638; color: white; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px; font-size: 12px;">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <?php if ($totalUsers > $perPage): ?>
                <div class="pagination">
                    <?php
                    $totalPages = ceil($totalUsers / $perPage);
                    $currentUrl = '?s=' . urlencode($search) . '&role=' . urlencode($roleFilter) . '&status=' . urlencode($statusFilter);
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $currentUrl; ?>&paged=<?php echo $page - 1; ?>">« Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <a href="<?php echo $currentUrl; ?>&paged=<?php echo $i; ?>" class="<?php echo $i == $page ? 'current' : ''; ?>"><?php echo $i; ?></a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo $currentUrl; ?>&paged=<?php echo $page + 1; ?>">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Select all checkbox
        document.getElementById('select-all')?.addEventListener('change', function() {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkActions();
        });
        
        // Update bulk actions visibility
        function updateBulkActions() {
            const checked = document.querySelectorAll('.user-checkbox:checked').length;
            const bulkActions = document.getElementById('bulk-actions');
            if (checked > 0) {
                bulkActions.classList.add('active');
                document.getElementById('selected-count').textContent = checked + ' item(s) selected';
            } else {
                bulkActions.classList.remove('active');
            }
        }
        
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });
        
        // Show/hide role select for bulk change
        document.querySelector('select[name="bulk_action"]')?.addEventListener('change', function() {
            const roleSelect = document.getElementById('new_role_select');
            if (this.value === 'change_role') {
                roleSelect.style.display = 'inline-block';
                roleSelect.required = true;
            } else {
                roleSelect.style.display = 'none';
                roleSelect.required = false;
            }
        });
        
        // View toggle
        function setView(view) {
            localStorage.setItem('usersView', view);
            const tableView = document.getElementById('table-view');
            const gridView = document.getElementById('grid-view');
            const buttons = document.querySelectorAll('.view-btn');
            
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-view') === view) {
                    btn.classList.add('active');
                }
            });
            
            if (view === 'table') {
                tableView.style.display = 'block';
                gridView.style.display = 'none';
            } else {
                tableView.style.display = 'none';
                gridView.style.display = 'block';
            }
        }
        
        // Load saved view preference
        const savedView = localStorage.getItem('usersView') || 'table';
        if (savedView) {
            setView(savedView);
        }
        
        // Avatar preview
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatar-preview');
                    const placeholder = document.getElementById('avatar-placeholder');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
