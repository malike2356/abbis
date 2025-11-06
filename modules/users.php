<?php
/**
 * User Management Module
 */
$page_title = 'User Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token');
        redirect('users.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $username = sanitizeInput($_POST['username'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'clerk';
                $fullName = sanitizeInput($_POST['full_name'] ?? '');
                
                // Check if username or email already exists
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkStmt->execute([$username, $email]);
                if ($checkStmt->fetch()) {
                    flash('error', 'Username or email already exists');
                    redirect('users.php');
                }
                
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, full_name) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $passwordHash, $role, $fullName]);
                flash('success', 'User created successfully');
                break;
                
            case 'update':
                $id = intval($_POST['id'] ?? 0);
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'clerk';
                $fullName = sanitizeInput($_POST['full_name'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE users SET email = ?, role = ?, full_name = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$email, $role, $fullName, $isActive, $id]);
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $pwdStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $pwdStmt->execute([$passwordHash, $id]);
                }
                
                flash('success', 'User updated successfully');
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id == $_SESSION['user_id']) {
                    flash('error', 'Cannot delete your own account');
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    flash('success', 'User deleted successfully');
                }
                break;
        }
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }
    
    redirect('users.php');
}

// Get all users
$users = $pdo->query("SELECT id, username, email, role, full_name, is_active, created_at, last_login FROM users ORDER BY created_at DESC")->fetchAll();

require_once '../includes/header.php';
?>

            <div class="page-header">
                <div>
                    <h1>User Management</h1>
                    <p>Manage system users and permissions</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="showUserModal()">Add New User</button>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo e($user['username']); ?></td>
                                <td><?php echo e($user['full_name']); ?></td>
                                <td><?php echo e($user['email']); ?></td>
                                <td><span class="badge"><?php echo e($user['role']); ?></span></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">Edit</button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- User Modal -->
            <div id="userModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="modalTitle">Add New User</h2>
                        <button type="button" class="modal-close" onclick="closeUserModal()">&times;</button>
                    </div>
                    <form method="POST" id="userForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" id="userAction" value="add">
                        <input type="hidden" name="id" id="userId" value="">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role" class="form-label">Role *</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="clerk">Clerk</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label" id="passwordLabel">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <small class="form-text">Leave blank when editing to keep current password</small>
                        </div>
                        
                        <div class="form-group" id="activeGroup" style="display: none;">
                            <label class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                <span>Active</span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button type="button" class="btn btn-outline" onclick="closeUserModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function showUserModal() {
                    document.getElementById('userModal').style.display = 'flex';
                    document.getElementById('userForm').reset();
                    document.getElementById('userAction').value = 'add';
                    document.getElementById('userId').value = '';
                    document.getElementById('modalTitle').textContent = 'Add New User';
                    document.getElementById('username').disabled = false;
                    document.getElementById('passwordLabel').innerHTML = 'Password *';
                    document.getElementById('activeGroup').style.display = 'none';
                }
                
                function editUser(user) {
                    document.getElementById('userModal').style.display = 'flex';
                    document.getElementById('userAction').value = 'update';
                    document.getElementById('userId').value = user.id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('username').disabled = true;
                    document.getElementById('full_name').value = user.full_name;
                    document.getElementById('email').value = user.email;
                    document.getElementById('role').value = user.role;
                    document.getElementById('is_active').checked = user.is_active == 1;
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('passwordLabel').innerHTML = 'Password <small>(leave blank to keep current)</small>';
                    document.getElementById('password').required = false;
                    document.getElementById('activeGroup').style.display = 'block';
                }
                
                function deleteUser(id) {
                    if (confirm('Are you sure you want to delete this user?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${id}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
                
                function closeUserModal() {
                    document.getElementById('userModal').style.display = 'none';
                }
                
                // Close modal when clicking outside
                window.onclick = function(event) {
                    const modal = document.getElementById('userModal');
                    if (event.target == modal) {
                        closeUserModal();
                    }
                }
            </script>

            <style>
                .modal {
                    display: none;
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0,0,0,0.5);
                    align-items: center;
                    justify-content: center;
                }
                
                .modal-content {
                    background-color: var(--card);
                    margin: auto;
                    padding: 0;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 600px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                
                .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid var(--border);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .modal-header h2 {
                    margin: 0;
                }
                
                .modal-close {
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: var(--text);
                }
                
                .modal form {
                    padding: 20px;
                }
                
                .badge {
                    padding: 4px 8px;
                    background: var(--primary);
                    color: white;
                    border-radius: 4px;
                    font-size: 12px;
                    text-transform: uppercase;
                }
            </style>

<?php require_once '../includes/footer.php'; ?>

