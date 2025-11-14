<?php
/**
 * User Profile Page - Users can edit their own profile
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/cms/includes/media-helper.php';
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
        $result = handleMediaUpload($_FILES['avatar'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], 2000000);
        if ($result['success']) {
            $avatarPath = $result['relative_path'];
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

// Get last login info
$lastLogin = $currentUser['last_login'] ?? null;
$loginCount = $currentUser['login_count'] ?? 0;
$memberSince = $currentUser['created_at'] ?? null;

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();
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
        .profile-header {
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            z-index: 1;
        }
        .profile-avatar-container {
            position: relative;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            background: white;
        }
        .profile-avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2271b1;
            font-size: 64px;
            font-weight: 700;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .profile-info {
            flex: 1;
        }
        .profile-info h2 {
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
            color: white;
        }
        .profile-info p {
            margin: 4px 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }
        .profile-role-badge {
            display: inline-block;
            padding: 6px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 8px;
            backdrop-filter: blur(10px);
        }
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .profile-stat {
            text-align: center;
            background: rgba(255, 255, 255, 0.15);
            padding: 15px 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            min-width: 100px;
        }
        .profile-stat-number {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }
        .profile-stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .profile-content {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .profile-sidebar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .profile-sidebar-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        .profile-sidebar-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .profile-sidebar-title {
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .profile-sidebar-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 12px;
        }
        .profile-sidebar-label {
            font-size: 11px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .profile-sidebar-value {
            font-size: 14px;
            color: #1d2327;
            font-weight: 500;
        }
        .profile-main {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 30px;
        }
        .profile-section {
            margin-bottom: 40px;
        }
        .profile-section:last-child {
            margin-bottom: 0;
        }
        .profile-section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="url"],
        .form-group input[type="password"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #8c8f94;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        }
        .form-group input:disabled {
            background: #f6f7f7;
            color: #646970;
            cursor: not-allowed;
        }
        .form-group .description {
            margin-top: 6px;
            font-size: 13px;
            color: #646970;
        }
        .avatar-upload-area {
            border: 2px dashed #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .avatar-upload-area:hover {
            border-color: #2271b1;
            background: #f0f9ff;
        }
        .avatar-upload-icon {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.6;
        }
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
            border-radius: 2px;
        }
        .password-strength-weak { background: #ef4444; width: 33%; }
        .password-strength-medium { background: #f59e0b; width: 66%; }
        .password-strength-strong { background: #10b981; width: 100%; }
        @media (max-width: 968px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
            .profile-sidebar {
                position: static;
            }
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="margin: 0;">👤 My Profile</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar-container">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="<?php echo $baseUrl . '/' . htmlspecialchars($currentUser['avatar']); ?>" alt="Avatar" class="profile-avatar" id="avatar-preview">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder" id="avatar-preview">
                            <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($currentUser['display_name'] ?? $currentUser['username']); ?></h2>
                    <p><?php echo htmlspecialchars($currentUser['email']); ?></p>
                    <span class="profile-role-badge"><?php echo ucfirst($currentUser['role']); ?></span>
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
                            <div class="profile-stat-number"><?php echo $loginCount; ?></div>
                            <div class="profile-stat-label">Logins</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="profile-content">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-sidebar-section">
                    <div class="profile-sidebar-title">Account Information</div>
                    <div class="profile-sidebar-item">
                        <span class="profile-sidebar-label">Username</span>
                        <span class="profile-sidebar-value"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                    </div>
                    <div class="profile-sidebar-item">
                        <span class="profile-sidebar-label">Member Since</span>
                        <span class="profile-sidebar-value">
                            <?php echo $memberSince ? date('M j, Y', strtotime($memberSince)) : 'N/A'; ?>
                        </span>
                    </div>
                    <div class="profile-sidebar-item">
                        <span class="profile-sidebar-label">Last Login</span>
                        <span class="profile-sidebar-value">
                            <?php echo $lastLogin ? date('M j, Y H:i', strtotime($lastLogin)) : 'Never'; ?>
                        </span>
                    </div>
                    <div class="profile-sidebar-item">
                        <span class="profile-sidebar-label">Account Status</span>
                        <span class="profile-sidebar-value" style="color: #00a32a; font-weight: 600;">
                            ✓ Active
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($currentUser['phone']) || !empty($currentUser['website']) || !empty($currentUser['location'])): ?>
                <div class="profile-sidebar-section">
                    <div class="profile-sidebar-title">Contact Details</div>
                    <?php if (!empty($currentUser['phone'])): ?>
                        <div class="profile-sidebar-item">
                            <span class="profile-sidebar-label">Phone</span>
                            <span class="profile-sidebar-value">
                                <a href="tel:<?php echo htmlspecialchars($currentUser['phone']); ?>" style="color: #2271b1; text-decoration: none;">
                                    <?php echo htmlspecialchars($currentUser['phone']); ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($currentUser['website'])): ?>
                        <div class="profile-sidebar-item">
                            <span class="profile-sidebar-label">Website</span>
                            <span class="profile-sidebar-value">
                                <a href="<?php echo htmlspecialchars($currentUser['website']); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">
                                    <?php echo htmlspecialchars($currentUser['website']); ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($currentUser['location'])): ?>
                        <div class="profile-sidebar-item">
                            <span class="profile-sidebar-label">Location</span>
                            <span class="profile-sidebar-value"><?php echo htmlspecialchars($currentUser['location']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="profile-sidebar-section">
                    <div class="profile-sidebar-title">Preferences</div>
                    <div class="profile-sidebar-item">
                        <span class="profile-sidebar-label">Timezone</span>
                        <span class="profile-sidebar-value"><?php echo htmlspecialchars($currentUser['timezone'] ?? 'UTC'); ?></span>
                    </div>
                    <div class="profile-sidebar-item">
                        <span class="profile-sidebar-label">Language</span>
                        <span class="profile-sidebar-value"><?php echo strtoupper($currentUser['language'] ?? 'en'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-main">
                <form method="post" enctype="multipart/form-data">
                    <!-- Personal Information Section -->
                    <div class="profile-section">
                        <h2 class="profile-section-title">👤 Personal Information</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($currentUser['username']); ?>" disabled>
                                <p class="description">Usernames cannot be changed.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Email <span style="color: red;">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" placeholder="Enter your first name">
                            </div>
                            
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" placeholder="Enter your last name">
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Display Name</label>
                                <input type="text" name="display_name" value="<?php echo htmlspecialchars($currentUser['display_name'] ?? $currentUser['username']); ?>" placeholder="How your name appears on the site">
                                <p class="description">The name displayed on your posts and profile.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Picture Section -->
                    <div class="profile-section">
                        <h2 class="profile-section-title">🖼️ Profile Picture</h2>
                        
                        <div class="avatar-upload-area" onclick="document.getElementById('avatar-input').click();">
                            <div class="avatar-upload-icon">📷</div>
                            <p style="margin: 0; color: #646970; font-weight: 600;">Click to upload or drag and drop</p>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #646970;">JPG, PNG, GIF (max 2MB)</p>
                        </div>
                        <input type="file" name="avatar" id="avatar-input" accept="image/*" onchange="previewAvatar(this)" style="display: none;">
                    </div>
                    
                    <!-- Additional Information Section -->
                    <div class="profile-section">
                        <h2 class="profile-section-title">ℹ️ Additional Information</h2>
                        
                        <div class="form-grid">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Biographical Info</label>
                                <textarea name="bio" rows="5" placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                                <p class="description">Share a little biographical information to fill out your profile.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="+1234567890">
                            </div>
                            
                            <div class="form-group">
                                <label>Website</label>
                                <input type="url" name="website" value="<?php echo htmlspecialchars($currentUser['website'] ?? ''); ?>" placeholder="https://example.com">
                            </div>
                            
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" value="<?php echo htmlspecialchars($currentUser['location'] ?? ''); ?>" placeholder="City, Country">
                            </div>
                            
                            <div class="form-group">
                                <label>Timezone</label>
                                <select name="timezone">
                                    <option value="UTC" <?php echo ($currentUser['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC (Coordinated Universal Time)</option>
                                    <option value="Africa/Accra" <?php echo ($currentUser['timezone'] ?? '') === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra (GMT)</option>
                                    <option value="America/New_York" <?php echo ($currentUser['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST/EDT)</option>
                                    <option value="Europe/London" <?php echo ($currentUser['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT/BST)</option>
                                    <option value="Asia/Dubai" <?php echo ($currentUser['timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai (GST)</option>
                                    <option value="Asia/Tokyo" <?php echo ($currentUser['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo (JST)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Language</label>
                                <select name="language">
                                    <option value="en" <?php echo ($currentUser['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="fr" <?php echo ($currentUser['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Français (French)</option>
                                    <option value="es" <?php echo ($currentUser['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Español (Spanish)</option>
                                    <option value="de" <?php echo ($currentUser['language'] ?? '') === 'de' ? 'selected' : ''; ?>>Deutsch (German)</option>
                                    <option value="pt" <?php echo ($currentUser['language'] ?? '') === 'pt' ? 'selected' : ''; ?>>Português (Portuguese)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Security Section -->
                    <div class="profile-section">
                        <h2 class="profile-section-title">🔒 Account Security</h2>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" id="new-password" placeholder="Enter new password (min 8 characters)">
                            <div class="password-strength" id="password-strength" style="display: none;">
                                <div class="password-strength-bar" id="password-strength-bar"></div>
                            </div>
                            <p class="description">Leave blank to keep current password. Minimum 8 characters with letters and numbers.</p>
                        </div>
                        
                        <div class="form-group" id="current-password-group" style="display: none;">
                            <label>Current Password <span style="color: red;">*</span></label>
                            <input type="password" name="current_password" placeholder="Enter your current password" required>
                            <p class="description">Enter your current password to verify the change.</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; display: flex; gap: 12px;">
                        <button type="submit" name="update_profile" class="button button-primary" style="padding: 12px 24px; font-size: 16px; font-weight: 600;">
                            💾 Save Changes
                        </button>
                        <a href="index.php" class="button" style="padding: 12px 24px;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Password change handler
        const newPasswordInput = document.getElementById('new-password');
        const currentPasswordGroup = document.getElementById('current-password-group');
        const passwordStrength = document.getElementById('password-strength');
        const passwordStrengthBar = document.getElementById('password-strength-bar');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const value = this.value;
                
                // Show/hide current password field
                if (value.length > 0) {
                    currentPasswordGroup.style.display = 'block';
                    currentPasswordGroup.querySelector('input').required = true;
                } else {
                    currentPasswordGroup.style.display = 'none';
                    currentPasswordGroup.querySelector('input').required = false;
                    passwordStrength.style.display = 'none';
                }
                
                // Password strength indicator
                if (value.length > 0) {
                    passwordStrength.style.display = 'block';
                    let strength = 0;
                    
                    if (value.length >= 8) strength++;
                    if (value.length >= 12) strength++;
                    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) strength++;
                    if (/\d/.test(value)) strength++;
                    if (/[^a-zA-Z\d]/.test(value)) strength++;
                    
                    passwordStrengthBar.className = 'password-strength-bar';
                    if (strength <= 2) {
                        passwordStrengthBar.classList.add('password-strength-weak');
                    } else if (strength <= 3) {
                        passwordStrengthBar.classList.add('password-strength-medium');
                    } else {
                        passwordStrengthBar.classList.add('password-strength-strong');
                    }
                }
            });
        }
        
        // Avatar preview
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
                        img.alt = 'Avatar Preview';
                        preview.parentElement.replaceChild(img, preview);
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Drag and drop for avatar
        const avatarUploadArea = document.querySelector('.avatar-upload-area');
        const avatarInput = document.getElementById('avatar-input');
        
        if (avatarUploadArea && avatarInput) {
            avatarUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#2271b1';
                this.style.background = '#f0f9ff';
            });
            
            avatarUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '#c3c4c7';
                this.style.background = '';
            });
            
            avatarUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#c3c4c7';
                this.style.background = '';
                
                if (e.dataTransfer.files.length > 0) {
                    avatarInput.files = e.dataTransfer.files;
                    previewAvatar(avatarInput);
                }
            });
        }
    </script>
</body>
</html>
