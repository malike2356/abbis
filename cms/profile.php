<?php
/**
 * CMS Front-end Profile Manager
 * Allows logged-in users to view and manage their profile
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/admin/auth.php';

$cmsAuth = new CMSAuth();
$baseUrl = app_base_path();

// Redirect if not logged in
if (!$cmsAuth->isLoggedIn()) {
    header('Location: ' . app_url('cms/admin/login.php'));
    exit;
}

$pdo = getDBConnection();
$currentUser = $cmsAuth->getCurrentUser();
$message = null;
$error = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM cms_users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $currentUser['id']]);
        if ($stmt->fetch()) {
            $error = 'Email address is already in use';
        } else {
            // Update profile
            try {
                // Check if columns exist, if not, we'll use ALTER TABLE
                $updateFields = ['email' => $email];
                
                // Try to update full_name if column exists
                try {
                    $stmt = $pdo->prepare("UPDATE cms_users SET email = ?, full_name = ? WHERE id = ?");
                    $stmt->execute([$email, $fullName, $currentUser['id']]);
                } catch (PDOException $e) {
                    // full_name column might not exist, try without it
                    $stmt = $pdo->prepare("UPDATE cms_users SET email = ? WHERE id = ?");
                    $stmt->execute([$email, $currentUser['id']]);
                }
                
                // Update bio if column exists
                if ($bio) {
                    try {
                        $stmt = $pdo->prepare("UPDATE cms_users SET bio = ? WHERE id = ?");
                        $stmt->execute([$bio, $currentUser['id']]);
                    } catch (PDOException $e) {
                        // bio column doesn't exist, ignore
                    }
                }
                
                // Update phone if column exists
                if ($phone) {
                    try {
                        $stmt = $pdo->prepare("UPDATE cms_users SET phone = ? WHERE id = ?");
                        $stmt->execute([$phone, $currentUser['id']]);
                    } catch (PDOException $e) {
                        // phone column doesn't exist, ignore
                    }
                }
                
                $message = 'Profile updated successfully';
                // Refresh user data
                $currentUser = $cmsAuth->getCurrentUser();
            } catch (PDOException $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Verify current password
    if (!password_verify($currentPassword, $currentUser['password_hash'])) {
        $error = 'Current password is incorrect';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } else {
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE cms_users SET password_hash = ? WHERE id = ?");
        if ($stmt->execute([$newHash, $currentUser['id']])) {
            $message = 'Password changed successfully';
        } else {
            $error = 'Failed to change password';
        }
    }
}

// Get user's posts count
try {
    $postsCount = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE author_id = ? AND status = 'published'");
    $postsCount->execute([$currentUser['id']]);
    $postsCount = $postsCount->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $postsCount = 0;
}

// Get user's pages count
try {
    $pagesCount = $pdo->prepare("SELECT COUNT(*) FROM cms_pages WHERE author_id = ?");
    $pagesCount->execute([$currentUser['id']]);
    $pagesCount = $pagesCount->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $pagesCount = 0;
}

// Get site settings for header/footer
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
    $cmsSettings = [];
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        $cmsSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $cmsSettings = [];
}

$siteTitle = $cmsSettings['site_title'] ?? 'CMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars($siteTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            padding-top: 20px;
        }
        .profile-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        .profile-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav-tabs {
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            padding: 12px 20px;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            background: transparent;
        }
    </style>
</head>
<body>
    <?php 
    // Include header if exists
    $headerPath = __DIR__ . '/public/header.php';
    if (file_exists($headerPath)) {
        include $headerPath;
    }
    ?>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)); ?>
            </div>
            <h2 class="text-center"><?php echo htmlspecialchars($currentUser['username'] ?? 'User'); ?></h2>
            <p class="text-center text-muted"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $postsCount; ?></div>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $pagesCount; ?></div>
                    <div class="stat-label">Pages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'user')); ?></div>
                    <div class="stat-label">Role</div>
                </div>
            </div>
        </div>
        
        <div class="profile-content">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#profile">Profile Information</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#password">Change Password</a>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Profile Tab -->
                <div id="profile" class="tab-pane fade show active">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                        </div>
                        
                        <?php
                        // Check if full_name column exists
                        $hasFullName = false;
                        try {
                            $pdo->query("SELECT full_name FROM cms_users LIMIT 1");
                            $hasFullName = true;
                        } catch (PDOException $e) {}
                        
                        if ($hasFullName):
                        ?>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        // Check if bio column exists
                        $hasBio = false;
                        try {
                            $pdo->query("SELECT bio FROM cms_users LIMIT 1");
                            $hasBio = true;
                        } catch (PDOException $e) {}
                        
                        if ($hasBio):
                        ?>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        // Check if phone column exists
                        $hasPhone = false;
                        try {
                            $pdo->query("SELECT phone FROM cms_users LIMIT 1");
                            $hasPhone = true;
                        } catch (PDOException $e) {}
                        
                        if ($hasPhone):
                        ?>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'user')); ?>" disabled>
                            <small class="text-muted">Role cannot be changed</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="<?php echo $baseUrl; ?>/cms/admin/index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </form>
                </div>
                
                <!-- Password Tab -->
                <div id="password" class="tab-pane fade">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                            <small class="text-muted">Must be at least 6 characters long</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
    // Include footer if exists
    $footerPath = __DIR__ . '/public/footer.php';
    if (file_exists($footerPath)) {
        include $footerPath;
    }
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
