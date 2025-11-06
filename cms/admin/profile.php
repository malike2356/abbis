<?php
/**
 * User Profile Page - Users can edit their own profile
 */
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
$currentUser = $cmsAuth->getCurrentUser();
$userId = $currentUser['id'];
$message = null;
$error = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $timezone = $_POST['timezone'] ?? 'UTC';
    $language = $_POST['language'] ?? 'en';
    $newPassword = $_POST['new_password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    
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
        if (in_array($ext, $allowed) && $file['size'] <= 2000000) {
            $filename = 'avatar_' . $userId . '.' . $ext;
            $filepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $avatarPath = 'uploads/avatars/' . $filename;
            }
        }
    }
    
    // Verify current password if changing password
    if (!empty($newPassword)) {
        if (empty($currentPassword) || !password_verify($currentPassword, $currentUser['password_hash'])) {
            $error = 'Current password is incorrect';
        } else {
            $updateFields = ['first_name', 'last_name', 'display_name', 'email', 'bio', 'phone', 'website', 'location', 'timezone', 'language', 'password_hash'];
            $updateValues = [$firstName, $lastName, $displayName, $email, $bio, $phone, $website, $location, $timezone, $language, password_hash($newPassword, PASSWORD_DEFAULT)];
            
            if ($avatarPath) {
                $updateFields[] = 'avatar';
                $updateValues[] = $avatarPath;
            }
            
            $updateValues[] = $userId;
            $setClause = implode('=?, ', $updateFields) . '=?';
            $stmt = $pdo->prepare("UPDATE cms_users SET $setClause WHERE id=?");
            $stmt->execute($updateValues);
            $message = 'Profile updated successfully';
            // Reload user
            $currentUser = $cmsAuth->getCurrentUser();
        }
    } else {
        $updateFields = ['first_name', 'last_name', 'display_name', 'email', 'bio', 'phone', 'website', 'location', 'timezone', 'language'];
        $updateValues = [$firstName, $lastName, $displayName, $email, $bio, $phone, $website, $location, $timezone, $language];
        
        if ($avatarPath) {
            $updateFields[] = 'avatar';
            $updateValues[] = $avatarPath;
        }
        
        $updateValues[] = $userId;
        $setClause = implode('=?, ', $updateFields) . '=?';
        $stmt = $pdo->prepare("UPDATE cms_users SET $setClause WHERE id=?");
        $stmt->execute($updateValues);
        $message = 'Profile updated successfully';
        // Reload user
        $currentUser = $cmsAuth->getCurrentUser();
    }
}

// Get user stats
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cms_posts WHERE created_by=?");
    $stmt->execute([$userId]);
    $postCount = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $postCount = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cms_pages WHERE created_by=?");
    $stmt->execute([$userId]);
    $pageCount = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $pageCount = 0;
}

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
    <title>Profile - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'profile';
    include 'header.php'; 
    ?>
    <style>
        .profile-header { background: white; border: 1px solid #c3c4c7; padding: 30px; margin-bottom: 20px; display: flex; align-items: center; gap: 30px; }
        .profile-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #c3c4c7; }
        .profile-info h2 { margin: 0 0 10px 0; }
        .profile-stats { display: flex; gap: 30px; margin-top: 15px; }
        .profile-stat { text-align: center; }
        .profile-stat-number { font-size: 24px; font-weight: 600; color: #2271b1; }
        .profile-stat-label { font-size: 12px; color: #646970; text-transform: uppercase; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Profile</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div>
                <?php if (!empty($currentUser['avatar'])): ?>
                    <img src="<?php echo $baseUrl . '/' . htmlspecialchars($currentUser['avatar']); ?>" alt="Avatar" class="profile-avatar" id="avatar-preview">
                <?php else: ?>
                    <div class="profile-avatar" style="background: #c3c4c7; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: bold;" id="avatar-preview">
                        <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($currentUser['display_name'] ?? $currentUser['username']); ?></h2>
                <p style="color: #646970; margin: 0;"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                <p style="color: #646970; margin: 5px 0;">Role: <strong><?php echo ucfirst($currentUser['role']); ?></strong></p>
                <div class="profile-stats">
                    <div class="profile-stat">
                        <div class="profile-stat-number"><?php echo $postCount; ?></div>
                        <div class="profile-stat-label">Posts</div>
                    </div>
                    <div class="profile-stat">
                        <div class="profile-stat-number"><?php echo $pageCount; ?></div>
                        <div class="profile-stat-label">Pages</div>
                    </div>
                    <div class="profile-stat">
                        <div class="profile-stat-number"><?php echo $currentUser['login_count'] ?? 0; ?></div>
                        <div class="profile-stat-label">Logins</div>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="post" enctype="multipart/form-data" class="post-form" style="background: white; padding: 20px; border: 1px solid #c3c4c7;">
            <h2>Personal Options</h2>
            
            <div class="form-grid">
                <div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($currentUser['username']); ?>" disabled class="regular-text">
                        <p class="description">Usernames cannot be changed.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span style="color: red;">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($currentUser['display_name'] ?? $currentUser['username']); ?>" class="regular-text">
                        <p class="description">The name displayed on your posts and profile.</p>
                    </div>
                </div>
                
                <div>
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                        <p class="description">Upload a new profile picture (JPG, PNG, GIF, max 2MB)</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Biographical Info</label>
                        <textarea name="bio" rows="5" class="large-text"><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                        <p class="description">Share a little biographical information to fill out your profile.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" value="<?php echo htmlspecialchars($currentUser['website'] ?? ''); ?>" class="regular-text" placeholder="https://">
                    </div>
                    
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($currentUser['location'] ?? ''); ?>" class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone" class="regular-text">
                            <option value="UTC" <?php echo ($currentUser['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="Africa/Accra" <?php echo ($currentUser['timezone'] ?? '') === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra</option>
                            <option value="America/New_York" <?php echo ($currentUser['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                            <option value="Europe/London" <?php echo ($currentUser['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Language</label>
                        <select name="language">
                            <option value="en" <?php echo ($currentUser['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="fr" <?php echo ($currentUser['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                            <option value="es" <?php echo ($currentUser['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <h2 style="margin-top: 30px;">Account Management</h2>
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="regular-text">
                <p class="description">Leave blank to keep current password. Minimum 8 characters.</p>
            </div>
            
            <div class="form-group" id="current-password-group" style="display: none;">
                <label>Current Password <span style="color: red;">*</span></label>
                <input type="password" name="current_password" class="regular-text">
                <p class="description">Enter your current password to change it.</p>
            </div>
            
            <p class="submit">
                <input type="submit" name="update_profile" class="button button-primary" value="Update Profile">
            </p>
        </form>
    </div>
    
    <script>
        document.querySelector('input[name="new_password"]')?.addEventListener('input', function() {
            const currentPasswordGroup = document.getElementById('current-password-group');
            if (this.value.length > 0) {
                currentPasswordGroup.style.display = 'block';
                currentPasswordGroup.querySelector('input').required = true;
            } else {
                currentPasswordGroup.style.display = 'none';
                currentPasswordGroup.querySelector('input').required = false;
            }
        });
        
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatar-preview');
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.id = 'avatar-preview';
                        img.className = 'profile-avatar';
                        img.src = e.target.result;
                        preview.parentElement.replaceChild(img, preview);
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
