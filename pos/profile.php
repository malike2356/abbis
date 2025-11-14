<?php
$page_title = 'User Profile';

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

$pdo = getDBConnection();
$baseUrl = app_base_path();

// Get current user details
$userId = (int)($_SESSION['user_id'] ?? 0);
$userStmt = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.email,
        u.full_name,
        u.role,
        u.created_at,
        u.last_login,
        u.is_active,
        COUNT(DISTINCT s.id) AS total_sales,
        COALESCE(SUM(s.total_amount), 0) AS total_revenue,
        MAX(s.sale_timestamp) AS last_sale_date
    FROM users u
    LEFT JOIN pos_sales s ON s.cashier_id = u.id AND s.sale_status = 'completed'
    WHERE u.id = :user_id
    GROUP BY u.id
");
$userStmt->execute([':user_id' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ' . $baseUrl . '/pos/index.php?action=terminal');
    exit;
}

// Get user permissions (handle if tables don't exist)
$permissions = [];
try {
    $permissionsStmt = $pdo->prepare("
        SELECT p.permission_name 
        FROM user_permissions up
        INNER JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = :user_id
    ");
    $permissionsStmt->execute([':user_id' => $userId]);
    $permissions = $permissionsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Permissions table might not exist, use role-based permissions
    $permissions = [];
}

// Get recent activity
$activityStmt = $pdo->prepare("
    SELECT 
        'Sale' AS activity_type,
        s.sale_number AS reference,
        s.sale_timestamp AS activity_date,
        s.total_amount AS amount
    FROM pos_sales s
    WHERE s.cashier_id = :user_id
    ORDER BY s.sale_timestamp DESC
    LIMIT 10
");
$activityStmt->execute([':user_id' => $userId]);
$recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's sales stats
$todayStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS sales_count,
        COALESCE(SUM(total_amount), 0) AS revenue
    FROM pos_sales
    WHERE cashier_id = :user_id 
    AND sale_status = 'completed'
    AND DATE(sale_timestamp) = CURDATE()
");
$todayStmt->execute([':user_id' => $userId]);
$todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.profile-container {
    max-width: 1400px;
    margin: 0;
    padding: 0;
}

.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 2px solid #e5e7eb;
    flex-wrap: wrap;
    gap: 16px;
}

.profile-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 200px;
}

.profile-header h1::before {
    content: '👤';
    font-size: 36px;
    line-height: 1;
}

.profile-hero {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 24px;
    margin-bottom: 32px;
    min-height: 0;
}

.profile-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.profile-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 32px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    color: white;
    min-height: auto;
    width: 100%;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 700;
    margin-bottom: 16px;
    border: 4px solid rgba(255, 255, 255, 0.4);
    color: white;
    flex-shrink: 0;
}

.profile-name {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
}

.profile-role {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 12px;
}

.profile-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.profile-status.active::before {
    content: '●';
    color: #10b981;
    font-size: 12px;
}

.profile-info-grid {
    display: grid;
    gap: 20px;
}

.profile-info-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.profile-info-label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-info-value {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    transition: transform 0.2s, box-shadow 0.2s;
    min-width: 0;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}

.stat-icon.sales {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.revenue {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-icon.today {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-icon.activity {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1d2327;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.permissions-section {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    margin-bottom: 32px;
    overflow: hidden;
}

.permissions-section h3 {
    margin: 0 0 20px 0;
    font-size: 20px;
    font-weight: 700;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 8px;
}

.permissions-section h3::before {
    content: '🔐';
    font-size: 24px;
}

.permissions-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.permission-badge {
    padding: 8px 16px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.activity-section {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.activity-section h3 {
    margin: 0 0 20px 0;
    font-size: 20px;
    font-weight: 700;
    color: #1d2327;
    display: flex;
    align-items: center;
    gap: 8px;
}

.activity-section h3::before {
    content: '📊';
    font-size: 24px;
}

.activity-table {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    table-layout: auto;
}

.activity-table thead {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
}

.activity-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.activity-table td {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
    color: #374151;
}

.activity-table tbody tr:hover {
    background: #f9fafb;
}

.activity-table tbody tr:last-child td {
    border-bottom: none;
}

.activity-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: #dbeafe;
    color: #1e40af;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.activity-amount {
    font-weight: 700;
    color: #059669;
}

.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #6b7280;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state-text {
    font-size: 16px;
    font-weight: 500;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    color: #374151;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-back:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Ensure proper layout containment */
.profile-container * {
    box-sizing: border-box;
}

@media (max-width: 768px) {
    .profile-hero {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .profile-container {
        padding: 0;
    }
}
</style>

<div class="profile-container">
    <div class="profile-header">
        <h1>User Profile</h1>
        <a href="<?php echo $baseUrl; ?>/pos/index.php?action=terminal" class="btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Terminal
        </a>
    </div>

    <div class="profile-hero">
        <!-- Profile Avatar Card -->
        <div class="profile-card">
            <div class="profile-avatar-section">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo e($user['full_name'] ?: $user['username']); ?></div>
                <div class="profile-role"><?php echo e(ucfirst($user['role'])); ?></div>
                <div class="profile-status <?php echo $user['is_active'] ? 'active' : ''; ?>">
                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
        </div>

        <!-- Profile Information -->
        <div class="profile-card">
            <h3 style="margin: 0 0 24px 0; font-size: 20px; font-weight: 700; color: #1d2327;">Account Information</h3>
            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <div class="profile-info-label">Username</div>
                    <div class="profile-info-value"><?php echo e($user['username']); ?></div>
                </div>
                <div class="profile-info-item">
                    <div class="profile-info-label">Email Address</div>
                    <div class="profile-info-value"><?php echo e($user['email'] ?: 'Not set'); ?></div>
                </div>
                <div class="profile-info-item">
                    <div class="profile-info-label">Member Since</div>
                    <div class="profile-info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                </div>
                <?php if ($user['last_login']): ?>
                <div class="profile-info-item">
                    <div class="profile-info-label">Last Login</div>
                    <div class="profile-info-value"><?php echo date('F j, Y g:i A', strtotime($user['last_login'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon sales">💰</div>
            <div class="stat-value"><?php echo number_format($user['total_sales']); ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon revenue">💵</div>
            <div class="stat-value">GHS <?php echo number_format($user['total_revenue'], 2); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon today">📅</div>
            <div class="stat-value"><?php echo number_format($todayStats['sales_count'] ?? 0); ?></div>
            <div class="stat-label">Today's Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon activity">📈</div>
            <div class="stat-value">GHS <?php echo number_format($todayStats['revenue'] ?? 0, 2); ?></div>
            <div class="stat-label">Today's Revenue</div>
        </div>
    </div>

    <!-- Permissions Section -->
    <div class="permissions-section">
        <h3>Permissions</h3>
        <?php if (empty($permissions)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔒</div>
                <div class="empty-state-text">No specific permissions assigned. Using default role-based access.</div>
            </div>
        <?php else: ?>
            <div class="permissions-list">
                <?php foreach ($permissions as $permission): ?>
                    <span class="permission-badge">
                        <?php echo e(str_replace(['pos.', '.'], ['', ' '], $permission)); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity Section -->
    <div class="activity-section">
        <h3>Recent Activity</h3>
        <?php if (empty($recentActivity)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-text">No recent activity to display</div>
            </div>
        <?php else: ?>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Date & Time</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td>
                                <span class="activity-type">
                                    <?php echo e($activity['activity_type']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo e($activity['reference']); ?></strong></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?></td>
                            <td class="activity-amount">GHS <?php echo number_format($activity['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
