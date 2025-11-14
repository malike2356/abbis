<?php
/**
 * CMS Public Profile Manager
 * Allows users to manage their profile from the frontend
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';
require_once __DIR__ . '/../admin/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cmsAuth = new CMSAuth();

// Require login
if (!$cmsAuth->isLoggedIn()) {
    header('Location: ' . $baseUrl . '/cms/admin/login.php?redirect=' . urlencode($baseUrl . '/cms/profile'));
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['cms_user_id'];
$currentUser = $cmsAuth->getCurrentUser();

// Ensure user profile fields exist
try {
    $pdo->query("SELECT first_name FROM cms_users LIMIT 1");
} catch (PDOException $e) {
    // Add missing columns
    $columns = [
        "ADD COLUMN first_name VARCHAR(100) DEFAULT NULL",
        "ADD COLUMN last_name VARCHAR(100) DEFAULT NULL",
        "ADD COLUMN display_name VARCHAR(100) DEFAULT NULL",
        "ADD COLUMN bio TEXT DEFAULT NULL",
        "ADD COLUMN avatar VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN phone VARCHAR(20) DEFAULT NULL",
        "ADD COLUMN website VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN location VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    foreach ($columns as $column) {
        try {
            $pdo->exec("ALTER TABLE cms_users " . $column);
        } catch (PDOException $e2) {
            // Column might already exist
        }
    }
}

// Handle form submission
$message = '';
$messageType = '';
$updated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $display_name = sanitizeInput($_POST['display_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $website = sanitizeInput($_POST['website'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        
        // Validate email
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email address';
            $messageType = 'error';
        } else {
            // Check if email is already taken by another user
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id FROM cms_users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    $message = 'Email address is already in use';
                    $messageType = 'error';
                }
            }
            
            if (!$message) {
                // Update profile
                $updateFields = [];
                $params = [];
                
                if (!empty($first_name)) { $updateFields[] = "first_name = ?"; $params[] = $first_name; }
                if (!empty($last_name)) { $updateFields[] = "last_name = ?"; $params[] = $last_name; }
                if (!empty($display_name)) { $updateFields[] = "display_name = ?"; $params[] = $display_name; }
                if (!empty($email)) { $updateFields[] = "email = ?"; $params[] = $email; }
                if (isset($_POST['bio'])) { $updateFields[] = "bio = ?"; $params[] = $bio; }
                if (isset($_POST['phone'])) { $updateFields[] = "phone = ?"; $params[] = $phone; }
                if (isset($_POST['website'])) { $updateFields[] = "website = ?"; $params[] = $website; }
                if (isset($_POST['location'])) { $updateFields[] = "location = ?"; $params[] = $location; }
                
                if (!empty($updateFields)) {
                    $updateFields[] = "updated_at = NOW()";
                    $params[] = $userId;
                    
                    $sql = "UPDATE cms_users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $message = 'Profile updated successfully';
                    $messageType = 'success';
                    $updated = true;
                    
                    // Refresh user data
                    $currentUser = $cmsAuth->getCurrentUser();
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters long';
            $messageType = 'error';
        } elseif (!password_verify($currentPassword, $currentUser['password_hash'])) {
            $message = 'Current password is incorrect';
            $messageType = 'error';
        } else {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE cms_users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newHash, $userId]);
            
            $message = 'Password changed successfully';
            $messageType = 'success';
        }
    } elseif ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                $message = 'Invalid file type. Please upload JPG, PNG, GIF, or WebP';
                $messageType = 'error';
            } elseif ($file['size'] > $maxSize) {
                $message = 'File size exceeds 5MB limit';
                $messageType = 'error';
            } else {
                // Create uploads directory if it doesn't exist
                $uploadDir = $rootPath . '/uploads/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Delete old avatar if exists
                    if (!empty($currentUser['avatar'])) {
                        $oldPath = $rootPath . '/' . ltrim($currentUser['avatar'], '/');
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    
                    // Save relative path
                    $relativePath = 'uploads/profiles/' . $filename;
                    $stmt = $pdo->prepare("UPDATE cms_users SET avatar = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$relativePath, $userId]);
                    
                    $message = 'Profile picture updated successfully';
                    $messageType = 'success';
                    $updated = true;
                    
                    // Refresh user data
                    $currentUser = $cmsAuth->getCurrentUser();
                } else {
                    $message = 'Failed to upload file';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'No file uploaded or upload error occurred';
            $messageType = 'error';
        }
    }
}

// Get updated user data
$currentUser = $cmsAuth->getCurrentUser();

// Get site name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Profile');

// Get theme
$themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
$theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
$themeConfig = json_decode($theme['config'] ?? '{}', true);
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            background: #f5f5f5;
        }
        
        .profile-wrapper {
            min-height: calc(100vh - 200px);
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 2rem 1rem;
        }
        
        .profile-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Hero Banner */
        .profile-hero {
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primaryColor); ?> 0%, #0284c7 100%);
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }
        
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 20s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }
        
        .hero-content {
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        
        .hero-avatar {
            position: relative;
        }
        
        .profile-avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255,255,255,0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: rgba(255,255,255,0.2);
        }
        
        .profile-avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            font-weight: 700;
            border: 5px solid rgba(255,255,255,0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .hero-info {
            flex: 1;
            min-width: 250px;
        }
        
        .hero-info h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero-info p {
            margin: 0.5rem 0;
            opacity: 0.95;
            font-size: 1.1rem;
        }
        
        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .hero-meta-item i {
            font-size: 1.2rem;
        }
        
        /* Main Layout - Two Columns */
        .profile-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 2rem;
        }
        
        /* Sidebar */
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .sidebar-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 30px rgba(0,0,0,0.12);
        }
        
        .sidebar-card h3 {
            margin: 0 0 1rem 0;
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-card h3 i {
            color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .stat-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .quick-info {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .quick-info li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .quick-info li:last-child {
            border-bottom: none;
        }
        
        .quick-info i {
            color: <?php echo htmlspecialchars($primaryColor); ?>;
            margin-top: 0.2rem;
            font-size: 1.1rem;
            min-width: 20px;
        }
        
        .quick-info span {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        /* Main Content Area */
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .profile-tabs {
            background: white;
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }
        
        .profile-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        .profile-tab.active {
            background: <?php echo htmlspecialchars($primaryColor); ?>;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .profile-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .profile-section h2 {
            margin: 0 0 1.5rem 0;
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .profile-section h2 i {
            color: <?php echo htmlspecialchars($primaryColor); ?>;
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #f8fafc;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: <?php echo htmlspecialchars($primaryColor); ?>;
            background: white;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primaryColor); ?> 0%, #0284c7 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert i {
            font-size: 1.3rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #6ee7b7;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }
        
        .avatar-upload-card {
            text-align: center;
            padding: 2rem;
        }
        
        .avatar-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #1e293b;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .file-input-label:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .file-info {
            margin-top: 0.75rem;
            color: #64748b;
            font-size: 0.875rem;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .profile-wrapper {
                padding: 1rem 0.5rem;
            }
            
            .profile-hero {
                padding: 2rem 1.5rem;
                border-radius: 16px;
            }
            
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-info h1 {
                font-size: 2rem;
            }
            
            .hero-meta {
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-tabs {
                padding: 0.25rem;
            }
            
            .profile-tab {
                padding: 0.625rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    $headerPath = __DIR__ . '/header.php';
    if (file_exists($headerPath)) {
        include $headerPath;
    }
    ?>
    
    <main class="cms-site-main profile-wrapper">
        <div class="profile-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Hero Banner -->
            <div class="profile-hero">
                <div class="hero-content">
                    <div class="hero-avatar">
                        <?php
                        $avatar = $currentUser['avatar'] ?? '';
                        $displayName = $currentUser['display_name'] ?? $currentUser['first_name'] ?? $currentUser['username'] ?? 'User';
                        $initials = strtoupper(substr($displayName, 0, 1));
                        $avatarPath = $avatar ? $baseUrl . '/' . ltrim($avatar, '/') : '';
                        ?>
                        <?php if ($avatar && file_exists($rootPath . '/' . ltrim($avatar, '/'))): ?>
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile" class="profile-avatar-large">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder"><?php echo htmlspecialchars($initials); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-info">
                        <h1><?php echo htmlspecialchars($displayName); ?></h1>
                        <?php if (!empty($currentUser['bio'])): ?>
                            <p><?php echo htmlspecialchars(substr($currentUser['bio'], 0, 150)) . (strlen($currentUser['bio']) > 150 ? '...' : ''); ?></p>
                        <?php else: ?>
                            <p>Welcome to your profile! Complete your information to get started.</p>
                        <?php endif; ?>
                        <div class="hero-meta">
                            <div class="hero-meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($currentUser['username'] ?? ''); ?></span>
                            </div>
                            <div class="hero-meta-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></span>
                            </div>
                            <div class="hero-meta-item">
                                <i class="fas fa-shield-alt"></i>
                                <span><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'user')); ?></span>
                            </div>
                            <?php if (!empty($currentUser['location'])): ?>
                            <div class="hero-meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($currentUser['location']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Layout -->
            <div class="profile-layout">
                <!-- Sidebar -->
                <div class="profile-sidebar">
                    <div class="sidebar-card">
                        <h3><i class="fas fa-chart-line"></i> Account Statistics</h3>
                        <div class="stat-item">
                            <span class="stat-label">Member Since</span>
                            <span class="stat-value"><?php echo date('M Y', strtotime($currentUser['created_at'] ?? 'now')); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Last Updated</span>
                            <span class="stat-value"><?php echo !empty($currentUser['updated_at']) ? date('M d, Y', strtotime($currentUser['updated_at'])) : 'Never'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Profile Completion</span>
                            <span class="stat-value"><?php
                                $fields = ['first_name', 'last_name', 'display_name', 'bio', 'phone', 'location', 'website', 'avatar'];
                                $completed = 0;
                                foreach ($fields as $field) {
                                    if (!empty($currentUser[$field])) $completed++;
                                }
                                echo round(($completed / count($fields)) * 100);
                            ?>%</span>
                        </div>
                    </div>
                    
                    <div class="sidebar-card">
                        <h3><i class="fas fa-info-circle"></i> Quick Info</h3>
                        <ul class="quick-info">
                            <li>
                                <i class="fas fa-user-tag"></i>
                                <span><strong>Username:</strong> <?php echo htmlspecialchars($currentUser['username'] ?? 'N/A'); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-envelope"></i>
                                <span><strong>Email:</strong> <?php echo htmlspecialchars($currentUser['email'] ?? 'N/A'); ?></span>
                            </li>
                            <?php if (!empty($currentUser['phone'])): ?>
                            <li>
                                <i class="fas fa-phone"></i>
                                <span><strong>Phone:</strong> <?php echo htmlspecialchars($currentUser['phone']); ?></span>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($currentUser['website'])): ?>
                            <li>
                                <i class="fas fa-globe"></i>
                                <span><strong>Website:</strong> <a href="<?php echo htmlspecialchars($currentUser['website']); ?>" target="_blank" style="color: <?php echo htmlspecialchars($primaryColor); ?>;">Visit</a></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="profile-main">
                    <!-- Tabs -->
                    <div class="profile-tabs">
                        <button class="profile-tab active" onclick="switchTab('personal')">
                            <i class="fas fa-user-edit"></i> Personal Info
                        </button>
                        <button class="profile-tab" onclick="switchTab('avatar')">
                            <i class="fas fa-image"></i> Profile Picture
                        </button>
                        <button class="profile-tab" onclick="switchTab('password')">
                            <i class="fas fa-lock"></i> Security
                        </button>
                    </div>
                    
                    <!-- Tab: Personal Information -->
                    <div id="tab-personal" class="tab-content active">
                        <div class="profile-section">
                            <h2><i class="fas fa-user-edit"></i> Personal Information</h2>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name</label>
                                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" placeholder="Enter your first name">
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" placeholder="Enter your last name">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="display_name">Display Name</label>
                                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($currentUser['display_name'] ?? ''); ?>" placeholder="How your name appears on the site">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required placeholder="your.email@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="bio">Biography</label>
                                    <textarea id="bio" name="bio" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="+1234567890">
                                    </div>
                                    <div class="form-group">
                                        <label for="location">Location</label>
                                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($currentUser['location'] ?? ''); ?>" placeholder="City, Country">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="website">Website</label>
                                    <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($currentUser['website'] ?? ''); ?>" placeholder="https://example.com">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Tab: Profile Picture -->
                    <div id="tab-avatar" class="tab-content">
                        <div class="profile-section">
                            <h2><i class="fas fa-image"></i> Profile Picture</h2>
                            <div class="avatar-upload-card">
                                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                    <input type="hidden" name="action" value="upload_avatar">
                                    <div class="avatar-upload-area">
                                        <div id="avatarPreview">
                                            <?php if ($avatar && file_exists($rootPath . '/' . ltrim($avatar, '/'))): ?>
                                                <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-preview">
                                            <?php else: ?>
                                                <div class="profile-avatar-placeholder" style="width: 120px; height: 120px; font-size: 3rem; background: <?php echo htmlspecialchars($primaryColor); ?>; border: 4px solid #e2e8f0;"><?php echo htmlspecialchars($initials); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="file-input-wrapper">
                                                <input type="file" name="avatar" id="avatarInput" accept="image/*" required>
                                                <label for="avatarInput" class="file-input-label">
                                                    <i class="fas fa-cloud-upload-alt"></i> Choose File
                                                </label>
                                            </div>
                                            <p class="file-info">JPG, PNG, GIF, or WebP. Maximum file size: 5MB</p>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Upload Picture
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab: Change Password -->
                    <div id="tab-password" class="tab-content">
                        <div class="profile-section">
                            <h2><i class="fas fa-lock"></i> Change Password</h2>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Enter your new password">
                                    <p style="margin-top: 0.5rem; color: #64748b; font-size: 0.875rem;">Must be at least 6 characters long.</p>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Confirm your new password">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php 
    $footerPath = __DIR__ . '/footer.php';
    if (file_exists($footerPath)) {
        include $footerPath;
    }
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.profile-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.closest('.profile-tab').classList.add('active');
        }
        
        // Avatar preview
        document.getElementById('avatarInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar" class="avatar-preview">';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Smooth scroll to top on form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            });
        });
    </script>
</body>
</html>
