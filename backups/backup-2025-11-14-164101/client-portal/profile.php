<?php
/**
 * Client Portal Profile
 */
require_once __DIR__ . '/auth-check.php';

$pageTitle = 'My Profile';
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        try {
            // Update user
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $userId]);
            
            // Update client record
            if ($clientId) {
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET client_name = ?, email = ?, contact_number = ?, address = ?
                    WHERE id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $address, $clientId]);
            } else {
                // Create client record if doesn't exist
                $stmt = $pdo->prepare("
                    INSERT INTO clients (client_name, email, contact_number, address, status, source)
                    VALUES (?, ?, ?, ?, 'active', 'portal')
                ");
                $stmt->execute([$fullName, $email, $phone, $address]);
                $newClientId = $pdo->lastInsertId();
                
                // Link user to client
                $stmt = $pdo->prepare("UPDATE users SET client_id = ? WHERE id = ?");
                $stmt->execute([$newClientId, $userId]);
                $clientId = $newClientId;
            }
            
            // Refresh client data
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update session
            $_SESSION['full_name'] = $fullName;
            
            $success = 'Profile updated successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to update profile: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($currentPassword, $user['password_hash'])) {
                    // Update password
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$newHash, $userId]);
                    
                    $success = 'Password changed successfully.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to change password: ' . $e->getMessage();
            }
        }
    }
}

// Get user data
$userData = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Profile user data error: ' . $e->getMessage());
}

include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="page-header">
        <h1><?php echo $pageTitle; ?></h1>
        <p>Manage your account information and preferences</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="profile-grid">
        <div class="profile-card">
            <div class="card-header">
                <h2>Personal Information</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required 
                           value="<?php echo htmlspecialchars($userData['full_name'] ?? $client['client_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required 
                           value="<?php echo htmlspecialchars($userData['email'] ?? $client['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($client['contact_number'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-primary">Update Profile</button>
            </form>
        </div>

        <div class="profile-card">
            <div class="card-header">
                <h2>Change Password</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8">
                    <small class="form-text">Must be at least 8 characters long</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>

                <button type="submit" class="btn-primary">Change Password</button>
            </form>
        </div>
    </div>

    <div class="profile-card" style="margin-top: 24px;">
        <div class="card-header">
            <h2>Account Information</h2>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Username:</span>
                <span class="info-value"><?php echo htmlspecialchars($userData['username'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Account Created:</span>
                <span class="info-value"><?php echo $userData['created_at'] ? date('F d, Y', strtotime($userData['created_at'])) : 'N/A'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Last Login:</span>
                <span class="info-value"><?php echo $userData['last_login'] ? date('F d, Y g:i A', strtotime($userData['last_login'])) : 'Never'; ?></span>
            </div>
            <?php if ($clientId): ?>
            <div class="info-item">
                <span class="info-label">Client ID:</span>
                <span class="info-value"><?php echo htmlspecialchars($clientId); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 32px;
}

.page-header h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
}

.page-header p {
    color: var(--text-light);
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.profile-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text);
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
}

.form-text {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--text-light);
}

.btn-primary {
    padding: 10px 20px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
}

.btn-primary:hover {
    transform: translateY(-1px);
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.alert-success {
    background: #c6f6d5;
    color: #22543d;
    border: 1px solid #9ae6b4;
}

.alert-error {
    background: #fed7d7;
    color: #c53030;
    border: 1px solid #fc8181;
}

.info-grid {
    display: grid;
    gap: 16px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    background: var(--bg);
    border-radius: 6px;
}

.info-label {
    font-weight: 600;
    color: var(--text-light);
}

.info-value {
    color: var(--text);
}
</style>

<?php include __DIR__ . '/footer.php'; ?>

