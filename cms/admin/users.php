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
    <title>Users - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.2/jquery-ui.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <?php 
    $currentPage = 'users';
    include 'header.php'; 
    ?>
    <style>
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px; }
        .user-status { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .user-status-active { background: #d1fae5; color: #065f46; }
        .user-status-inactive { background: #fee2e2; color: #991b1b; }
        .user-status-pending { background: #fef3c7; color: #92400e; }
        .user-status-suspended { background: #e5e7eb; color: #374151; }
        .user-actions { display: flex; gap: 5px; }
        .user-actions a, .user-actions button { padding: 4px 8px; font-size: 12px; }
        .search-box { margin-bottom: 20px; }
        .filters { display: flex; gap: 15px; margin-bottom: 20px; align-items: flex-end; }
        .filters select, .filters input { padding: 6px 10px; }
        .bulk-actions { margin-bottom: 20px; padding: 10px; background: #f6f7f7; border: 1px solid #c3c4c7; display: none; }
        .bulk-actions.active { display: block; }
        .user-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .user-form-avatar { text-align: center; }
        .user-form-avatar img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #c3c4c7; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; }
        .pagination a, .pagination span { padding: 5px 10px; border: 1px solid #c3c4c7; text-decoration: none; }
        .pagination .current { background: #2271b1; color: white; border-color: #2271b1; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1><?php echo $action === 'edit' ? 'Edit User' : ($action === 'add' ? 'Add New User' : 'Users'); ?></h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'add'): ?>
            <form method="post" enctype="multipart/form-data" class="post-form">
                <input type="hidden" name="user_id" value="<?php echo $user['id'] ?? ''; ?>">
                
                <div class="user-form-grid">
                    <div>
                        <div class="form-group">
                            <label>Username <span style="color: red;">*</span></label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label>Email <span style="color: red;">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label>Display Name</label>
                            <input type="text" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ($user['username'] ?? '')); ?>" class="regular-text">
                            <p class="description">The name displayed on the site.</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Role <span style="color: red;">*</span></label>
                            <select name="role" required>
                                <?php foreach ($roles as $roleValue => $roleName): ?>
                                    <option value="<?php echo $roleValue; ?>" <?php echo ($user['role'] ?? 'subscriber') === $roleValue ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($roleName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span style="color: red;">*</span></label>
                            <select name="status" required>
                                <?php foreach ($statuses as $statusValue => $statusName): ?>
                                    <option value="<?php echo $statusValue; ?>" <?php echo ($user['status'] ?? 'active') === $statusValue ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $user ? 'New ' : ''; ?>Password<?php echo $user ? '' : ' <span style="color: red;">*</span>'; ?></label>
                            <input type="password" name="new_password" class="regular-text" <?php echo $user ? '' : 'required'; ?>>
                            <p class="description"><?php echo $user ? 'Leave blank to keep current password.' : 'Minimum 8 characters.'; ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <div class="user-form-avatar">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($user['avatar']); ?>" alt="Avatar" id="avatar-preview">
                            <?php else: ?>
                                <div style="width: 150px; height: 150px; border-radius: 50%; background: #c3c4c7; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; margin: 0 auto;">
                                    <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 15px;">
                                <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                                <p class="description">Upload a profile picture (JPG, PNG, GIF, max 2MB)</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Biographical Info</label>
                            <textarea name="bio" rows="5" class="large-text"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <p class="description">Share a little biographical information to fill out your profile.</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label>Website</label>
                            <input type="url" name="website" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>" class="regular-text" placeholder="https://">
                        </div>
                        
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" class="regular-text">
                        </div>
                        
                        <div class="form-group">
                            <label>Timezone</label>
                            <select name="timezone" class="regular-text">
                                <option value="UTC" <?php echo ($user['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="Africa/Accra" <?php echo ($user['timezone'] ?? '') === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra</option>
                                <option value="America/New_York" <?php echo ($user['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                <option value="Europe/London" <?php echo ($user['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Language</label>
                            <select name="language">
                                <option value="en" <?php echo ($user['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="fr" <?php echo ($user['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                <option value="es" <?php echo ($user['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                            </select>
                        </div>
                        
                        <?php if ($user): ?>
                            <div class="form-group">
                                <p><strong>Last Login:</strong> <?php echo !empty($user['last_login']) ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : 'Never'; ?></p>
                                <p><strong>Login Count:</strong> <?php echo $user['login_count'] ?? 0; ?></p>
                                <p><strong>Registered:</strong> <?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="save_user" class="button button-primary" value="Save User">
                    <a href="users.php" class="button">Cancel</a>
                </p>
            </form>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <a href="?action=add" class="page-title-action">Add New</a>
                
                <form method="get" class="search-box" style="display: inline-block;">
                    <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users..." class="regular-text">
                    <input type="submit" value="Search Users" class="button">
                    <?php if ($search || $roleFilter || $statusFilter): ?>
                        <a href="users.php" class="button">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="filters">
                <div>
                    <label>Filter by Role:</label>
                    <select name="role" onchange="window.location='?role='+this.value+'&status=<?php echo htmlspecialchars($statusFilter); ?>&s=<?php echo htmlspecialchars($search); ?>'">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $roleValue => $roleName): ?>
                            <option value="<?php echo $roleValue; ?>" <?php echo $roleFilter === $roleValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($roleName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Filter by Status:</label>
                    <select name="status" onchange="window.location='?status='+this.value+'&role=<?php echo htmlspecialchars($roleFilter); ?>&s=<?php echo htmlspecialchars($search); ?>'">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $statusValue => $statusName): ?>
                            <option value="<?php echo $statusValue; ?>" <?php echo $statusFilter === $statusValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($statusName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                    <input type="submit" value="Apply" class="button">
                    <span id="selected-count">0 items selected</span>
                </div>
                
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
                                <td colspan="9" style="text-align: center; padding: 40px; color: #646970;">
                                    No users found.
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
                                            <div class="user-avatar" style="background: #c3c4c7; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <strong><a href="?action=edit&id=<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></a></strong>
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
        
        // Avatar preview
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById('avatar-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'avatar-preview';
                        preview.style.width = '150px';
                        preview.style.height = '150px';
                        preview.style.borderRadius = '50%';
                        preview.style.objectFit = 'cover';
                        preview.style.border = '3px solid #c3c4c7';
                        preview.style.display = 'block';
                        preview.style.margin = '0 auto';
                        input.parentElement.insertBefore(preview, input);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
