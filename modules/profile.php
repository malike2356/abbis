<?php
/**
 * User Profile Management
 */
$page_title = 'My Profile';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$message = null;
$messageType = null;

// Auto-create profile columns if they don't exist
try {
    $checkStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    if ($checkStmt->rowCount() == 0) {
        // Add profile columns automatically
        $columns = [
            "ADD COLUMN `phone_number` VARCHAR(20) DEFAULT NULL",
            "ADD COLUMN `date_of_birth` DATE DEFAULT NULL",
            "ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL",
            "ADD COLUMN `bio` TEXT DEFAULT NULL",
            "ADD COLUMN `address` TEXT DEFAULT NULL",
            "ADD COLUMN `city` VARCHAR(100) DEFAULT NULL",
            "ADD COLUMN `country` VARCHAR(100) DEFAULT 'Ghana'",
            "ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL",
            "ADD COLUMN `emergency_contact_name` VARCHAR(100) DEFAULT NULL",
            "ADD COLUMN `emergency_contact_phone` VARCHAR(20) DEFAULT NULL",
            "ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($columns as $column) {
            try {
                $pdo->exec("ALTER TABLE users " . $column);
            } catch (PDOException $e) {
                // Column might already exist, ignore
                if (strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Error adding column: " . $e->getMessage());
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error checking/creating profile columns: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'update_profile':
                    $fullName = sanitizeInput($_POST['full_name'] ?? '');
                    $email = sanitizeInput($_POST['email'] ?? '');
                    
                    // Columns are auto-created above
$hasNewColumns = true;
                    
                    if ($hasNewColumns) {
                        $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');
                        $dateOfBirth = $_POST['date_of_birth'] ?? null;
                        $bio = sanitizeInput($_POST['bio'] ?? '');
                        $address = sanitizeInput($_POST['address'] ?? '');
                        $city = sanitizeInput($_POST['city'] ?? '');
                        $country = sanitizeInput($_POST['country'] ?? 'Ghana');
                        $postalCode = sanitizeInput($_POST['postal_code'] ?? '');
                        $emergencyContactName = sanitizeInput($_POST['emergency_contact_name'] ?? '');
                        $emergencyContactPhone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
                        
                        $stmt = $pdo->prepare("
                            UPDATE users SET
                            full_name = ?, email = ?, phone_number = ?, date_of_birth = ?,
                            bio = ?, address = ?, city = ?, country = ?, postal_code = ?,
                            emergency_contact_name = ?, emergency_contact_phone = ?,
                            updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $fullName, $email, $phoneNumber, $dateOfBirth ?: null,
                            $bio, $address, $city, $country, $postalCode,
                            $emergencyContactName, $emergencyContactPhone,
                            $userId
                        ]);
                    } else {
                        // Basic update only (before migration)
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                        $stmt->execute([$fullName, $email, $userId]);
                    }
                    
                    $_SESSION['full_name'] = $fullName;
                    $message = 'Profile updated successfully';
                    $messageType = 'success';
                    break;
                    
                case 'upload_photo':
                    // Profile photo column should already exist (auto-created above)
                    
                    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES['profile_photo'];
                        
                        // Validate
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        $maxSize = 5 * 1024 * 1024; // 5MB
                        
                        if (!in_array($file['type'], $allowedTypes)) {
                            throw new Exception('Invalid file type. Only JPEG, PNG, GIF allowed.');
                        }
                        
                        if ($file['size'] > $maxSize) {
                            throw new Exception('File size exceeds 5MB limit.');
                        }
                        
                        // Create uploads/profiles directory
                        $uploadDir = __DIR__ . '/../uploads/profiles/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        // Generate unique filename
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
                        $filepath = $uploadDir . $filename;
                        
                        // Delete old photo
                        try {
                            $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                            $stmt->execute([$userId]);
                            $oldPhoto = $stmt->fetchColumn();
                            if ($oldPhoto && file_exists(__DIR__ . '/../' . $oldPhoto)) {
                                @unlink(__DIR__ . '/../' . $oldPhoto);
                            }
                        } catch (PDOException $e) {
                            // Ignore if column doesn't exist
                        }
                        
                        // Move uploaded file
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            $relativePath = 'uploads/profiles/' . $filename;
                            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                            $stmt->execute([$relativePath, $userId]);
                            $message = 'Profile photo updated successfully';
                            $messageType = 'success';
                        } else {
                            throw new Exception('Failed to upload file');
                        }
                    }
                    break;
                    
                case 'change_password':
                    $currentPassword = $_POST['current_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    if (empty($currentPassword) || empty($newPassword)) {
                        throw new Exception('All password fields required');
                    }
                    
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('New passwords do not match');
                    }
                    
                    if (strlen($newPassword) < 8) {
                        throw new Exception('Password must be at least 8 characters');
                    }
                    
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $currentHash = $stmt->fetchColumn();
                    
                    if (!password_verify($currentPassword, $currentHash)) {
                        throw new Exception('Current password is incorrect');
                    }
                    
                    // Update password
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$newHash, $userId]);
                    
                    $message = 'Password changed successfully';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get user data - columns are auto-created above
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Initialize defaults for any missing fields
    if (!isset($user['profile_photo'])) $user['profile_photo'] = null;
    if (!isset($user['phone_number'])) $user['phone_number'] = null;
    if (!isset($user['date_of_birth'])) $user['date_of_birth'] = null;
    if (!isset($user['bio'])) $user['bio'] = null;
    if (!isset($user['address'])) $user['address'] = null;
    if (!isset($user['city'])) $user['city'] = null;
    if (!isset($user['country'])) $user['country'] = 'Ghana';
    if (!isset($user['postal_code'])) $user['postal_code'] = null;
    if (!isset($user['emergency_contact_name'])) $user['emergency_contact_name'] = null;
    if (!isset($user['emergency_contact_phone'])) $user['emergency_contact_phone'] = null;
    if (!isset($user['email_verified'])) $user['email_verified'] = 0;
    if (!isset($user['phone_verified'])) $user['phone_verified'] = 0;
} catch (PDOException $e) {
    // Fallback if error
    $user = ['id' => $userId, 'full_name' => $_SESSION['full_name'] ?? 'User', 'email' => $_SESSION['email'] ?? ''];
    error_log("Profile error: " . $e->getMessage());
}

// Columns are auto-created above, so they exist
$hasNewColumns = true;
try {
    // Columns are auto-created above
    $hasNewColumns = true;
} catch (PDOException $e) {
    $hasNewColumns = true; // Columns should exist
}

// Get social accounts - safely handle if table doesn't exist
$socialAccounts = [];
try {
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'user_social_auth'");
    if ($checkStmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT provider FROM user_social_auth WHERE user_id = ?");
        $stmt->execute([$userId]);
        $socialAccounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    // Table doesn't exist yet
    $socialAccounts = [];
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>👤 My Profile</h1>
        <p>Manage your profile information and account settings</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-grid">
        <!-- Profile Photo -->
        <div class="dashboard-card">
            <h2>📷 Profile Photo</h2>
            <div style="text-align: center; margin: 20px 0;">
                <?php 
                $photoPath = $user['profile_photo'] ?? '';
                $photoUrl = '';
                $absolutePhotoPath = $photoPath ? __DIR__ . '/../' . ltrim($photoPath, '/') : '';
                if ($photoPath && $absolutePhotoPath && file_exists($absolutePhotoPath)) {
                    $photoUrl = '../' . ltrim($photoPath, '/');
                } else {
                    $displayName = isset($user['full_name']) && $user['full_name'] ? $user['full_name'] : 'User';
                    $photoUrl = 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&size=200&background=007bff&color=fff';
                }
                ?>
                <img src="<?php echo e($photoUrl); ?>" alt="Profile Photo" 
                     style="width: 200px; height: 200px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border);">
            </div>
            <?php
            // Check if profile_photo column exists
            // Profile photo column is auto-created above
            $hasProfilePhoto = true;
            ?>
            <?php if ($hasProfilePhoto): ?>
                <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/gif" class="form-control" required>
                    <small class="form-text">JPEG, PNG, or GIF - Max 5MB</small>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Upload Photo</button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Personal Information -->
        <div class="dashboard-card">
            <h2>ℹ️ Personal Information</h2>
            <?php if (!$hasNewColumns): ?>
                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <strong>📋 Note:</strong> Some fields require database migration. 
                    Run <code>database/user_profiles_migration.sql</code> to enable all profile features.
                </div>
            <?php endif; ?>
            <form method="POST" class="form-grid-compact">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?php echo e($user['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo e($user['email'] ?? ''); ?>" required>
                    <?php if ($hasNewColumns && ($user['email_verified'] ?? 0)): ?>
                        <small class="form-text" style="color: var(--success);">✓ Verified</small>
                    <?php elseif ($hasNewColumns): ?>
                        <small class="form-text" style="color: var(--warning);">⚠ Not verified</small>
                    <?php endif; ?>
                </div>
                
                <?php if ($hasNewColumns): ?>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone_number" class="form-control" 
                               value="<?php echo e($user['phone_number'] ?? ''); ?>" 
                               placeholder="+233 555 123 456">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" 
                               value="<?php echo e($user['date_of_birth'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="3" 
                                  placeholder="Tell us about yourself"><?php echo e($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" 
                                  placeholder="Street address"><?php echo e($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" 
                               value="<?php echo e($user['city'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" 
                               value="<?php echo e($user['country'] ?? 'Ghana'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" 
                               value="<?php echo e($user['postal_code'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" class="form-control" 
                               value="<?php echo e($user['emergency_contact_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Phone</label>
                        <input type="tel" name="emergency_contact_phone" class="form-control" 
                               value="<?php echo e($user['emergency_contact_phone'] ?? ''); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="form-group full-width">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="dashboard-card">
            <h2>🔒 Change Password</h2>
            <form method="POST">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8">
                    <small class="form-text">Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Change Password</button>
                </div>
            </form>
        </div>
        
        <!-- Connected Accounts -->
        <div class="dashboard-card">
            <h2>🔗 Connected Accounts</h2>
            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px;">
                    <div>
                        <strong>Google</strong>
                        <?php if (in_array('google', $socialAccounts)): ?>
                            <span style="color: var(--success); margin-left: 8px;">✓ Connected</span>
                        <?php else: ?>
                            <span style="color: var(--secondary); margin-left: 8px;">Not connected</span>
                        <?php endif; ?>
                    </div>
                    <?php if (in_array('google', $socialAccounts)): ?>
                        <form method="POST" style="display: inline;">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="disconnect_social">
                            <input type="hidden" name="provider" value="google">
                            <button type="submit" class="btn btn-sm btn-outline">Disconnect</button>
                        </form>
                    <?php else: ?>
                        <a href="../api/social-auth.php?action=google_auth" class="btn btn-sm btn-primary">Connect</a>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px;">
                    <div>
                        <strong>Facebook</strong>
                        <?php if (in_array('facebook', $socialAccounts)): ?>
                            <span style="color: var(--success); margin-left: 8px;">✓ Connected</span>
                        <?php else: ?>
                            <span style="color: var(--secondary); margin-left: 8px;">Not connected</span>
                        <?php endif; ?>
                    </div>
                    <?php if (in_array('facebook', $socialAccounts)): ?>
                        <form method="POST" style="display: inline;">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="disconnect_social">
                            <input type="hidden" name="provider" value="facebook">
                            <button type="submit" class="btn btn-sm btn-outline">Disconnect</button>
                        </form>
                    <?php else: ?>
                        <a href="../api/social-auth.php?action=facebook_auth" class="btn btn-sm btn-primary">Connect</a>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid var(--border); border-radius: 6px;">
                    <div>
                        <strong>Phone Number</strong>
                        <?php if (in_array('phone', $socialAccounts)): ?>
                            <span style="color: var(--success); margin-left: 8px;">✓ Connected</span>
                        <?php else: ?>
                            <span style="color: var(--secondary); margin-left: 8px;">Not connected</span>
                        <?php endif; ?>
                    </div>
                    <?php if (in_array('phone', $socialAccounts)): ?>
                        <span style="color: var(--secondary); font-size: 12px;">Connected via profile</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

