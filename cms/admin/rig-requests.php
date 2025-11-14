<?php
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

// Update rig request status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $requestId = $_POST['request_id'];
    $status = $_POST['status'];
    $pdo->prepare("UPDATE rig_requests SET status=?, updated_at=NOW() WHERE id=?")->execute([$status, $requestId]);
    
    // Set dispatched_at or completed_at if applicable
    if ($status === 'dispatched') {
        $pdo->prepare("UPDATE rig_requests SET dispatched_at=NOW() WHERE id=?")->execute([$requestId]);
    }
    if ($status === 'completed') {
        $pdo->prepare("UPDATE rig_requests SET completed_at=NOW() WHERE id=?")->execute([$requestId]);
    }
    
    $_SESSION['rig_requests_message'] = 'Rig request status updated successfully';
    header('Location: rig-requests.php' . ($_GET['status'] ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

// Get statistics
$stats = [
    'all' => $pdo->query("SELECT COUNT(*) FROM rig_requests")->fetchColumn(),
    'new' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='new'")->fetchColumn(),
    'under_review' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='under_review'")->fetchColumn(),
    'negotiating' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='negotiating'")->fetchColumn(),
    'dispatched' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='dispatched'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='completed'")->fetchColumn(),
    'declined' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='declined'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status='cancelled'")->fetchColumn(),
];

$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query with proper parameter binding
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(r.requester_name LIKE ? OR r.requester_email LIKE ? OR r.requester_phone LIKE ? OR r.request_number LIKE ? OR r.location_address LIKE ? OR r.company_name LIKE ?)";
    $searchTerm = '%' . $searchQuery . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$where = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
$sql = "SELECT r.*, 
        rig.rig_name, rig.rig_code,
        c.client_name,
        u.full_name as assigned_to_name
        FROM rig_requests r
        LEFT JOIN rigs rig ON rig.id = r.assigned_rig_id
        LEFT JOIN clients c ON c.id = r.client_id
        LEFT JOIN users u ON u.id = r.assigned_to
        $where 
        ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for flash message
$message = null;
if (isset($_SESSION['rig_requests_message'])) {
    $message = $_SESSION['rig_requests_message'];
    unset($_SESSION['rig_requests_message']);
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();

// Helper function for time ago
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rig Requests - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'rig-requests';
    include 'header.php'; 
    ?>
    <style>
        .request-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .request-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .request-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0f0f1;
        }
        .request-card-title {
            flex: 1;
        }
        .request-card-title h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 700;
            color: #1d2327;
        }
        .request-card-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #646970;
            margin-top: 8px;
        }
        .request-card-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .request-card-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .request-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .request-info-label {
            font-size: 12px;
            font-weight: 600;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .request-info-value {
            font-size: 14px;
            color: #1d2327;
            font-weight: 500;
        }
        .request-description {
            background: #f6f7f7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
            color: #3c434a;
            line-height: 1.6;
        }
        .request-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f1;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-new { background: rgba(37, 99, 235, 0.1); color: #2563eb; }
        .status-under_review { background: rgba(251, 191, 36, 0.1); color: #f59e0b; }
        .status-negotiating { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .status-dispatched { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-declined { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .status-cancelled { background: rgba(100, 116, 139, 0.1); color: #64748b; }
        
        .urgency-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .urgency-low { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .urgency-medium { background: rgba(251, 191, 36, 0.1); color: #f59e0b; }
        .urgency-high { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .urgency-urgent { background: rgba(220, 38, 38, 0.2); color: #dc2626; font-weight: 700; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-card.active {
            border-color: var(--admin-primary, #2563eb);
            border-width: 2px;
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.05));
        }
        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--admin-primary, #2563eb);
            margin: 8px 0;
        }
        .stat-card-label {
            font-size: 13px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            background: white;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #c3c4c7;
        }
        .filter-tab {
            padding: 10px 20px;
            border: 2px solid transparent;
            border-radius: 8px;
            background: #f6f7f7;
            color: #646970;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-tab:hover {
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
            color: var(--admin-primary, #2563eb);
        }
        .filter-tab.active {
            background: var(--admin-primary, #2563eb);
            color: white;
            border-color: var(--admin-primary, #2563eb);
        }
        
        .search-bar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .search-bar form {
            display: flex;
            gap: 12px;
        }
        .search-bar input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #c3c4c7;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-bar input:focus {
            outline: none;
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 0 0 3px var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .admin-page-header {
            margin-bottom: 24px;
        }
        .admin-page-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: #1d2327;
        }
        .admin-page-header p {
            color: #646970;
            font-size: 14px;
            margin: 0;
        }
        
        .admin-notice {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .admin-notice-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #16a34a;
        }
        .admin-notice-icon {
            font-size: 20px;
            font-weight: 700;
        }
        
        .admin-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        .admin-btn-primary {
            background: var(--admin-primary, #2563eb);
            color: white;
        }
        .admin-btn-primary:hover {
            background: var(--admin-primary-dark, #1e40af);
        }
        .admin-btn-outline {
            background: white;
            color: var(--admin-primary, #2563eb);
            border: 2px solid var(--admin-primary, #2563eb);
        }
        .admin-btn-outline:hover {
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
        }
        
        @media (max-width: 768px) {
            .request-card-body {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div class="admin-page-header">
            <h1>🚛 Rig Requests</h1>
            <p>Manage and track all rig rental requests from clients and contractors.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="admin-notice admin-notice-success">
                <span class="admin-notice-icon">✓</span>
                <div class="admin-notice-content">
                    <strong>Success!</strong>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="?status=all" class="stat-card <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                <div class="stat-card-value"><?php echo $stats['all']; ?></div>
                <div class="stat-card-label">All Requests</div>
            </a>
            <a href="?status=new" class="stat-card <?php echo $statusFilter === 'new' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #2563eb;"><?php echo $stats['new']; ?></div>
                <div class="stat-card-label">New</div>
            </a>
            <a href="?status=under_review" class="stat-card <?php echo $statusFilter === 'under_review' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #f59e0b;"><?php echo $stats['under_review']; ?></div>
                <div class="stat-card-label">Under Review</div>
            </a>
            <a href="?status=negotiating" class="stat-card <?php echo $statusFilter === 'negotiating' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #8b5cf6;"><?php echo $stats['negotiating']; ?></div>
                <div class="stat-card-label">Negotiating</div>
            </a>
            <a href="?status=dispatched" class="stat-card <?php echo $statusFilter === 'dispatched' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #10b981;"><?php echo $stats['dispatched']; ?></div>
                <div class="stat-card-label">Dispatched</div>
            </a>
            <a href="?status=completed" class="stat-card <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #10b981;"><?php echo $stats['completed']; ?></div>
                <div class="stat-card-label">Completed</div>
            </a>
            <a href="?status=declined" class="stat-card <?php echo $statusFilter === 'declined' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #ef4444;"><?php echo $stats['declined']; ?></div>
                <div class="stat-card-label">Declined</div>
            </a>
            <a href="?status=cancelled" class="stat-card <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #64748b;"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-card-label">Cancelled</div>
            </a>
        </div>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="get" action="rig-requests.php">
                <?php if ($statusFilter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="🔍 Search by name, email, phone, request number, location..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="flex: 1;">
                <button type="submit" class="admin-btn admin-btn-primary">Search</button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="?status=<?php echo htmlspecialchars($statusFilter); ?>" class="admin-btn admin-btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?status=all<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                <span>📋</span> All (<?php echo $stats['all']; ?>)
            </a>
            <a href="?status=new<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'new' ? 'active' : ''; ?>">
                <span>🆕</span> New (<?php echo $stats['new']; ?>)
            </a>
            <a href="?status=under_review<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'under_review' ? 'active' : ''; ?>">
                <span>👀</span> Under Review (<?php echo $stats['under_review']; ?>)
            </a>
            <a href="?status=negotiating<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'negotiating' ? 'active' : ''; ?>">
                <span>🤝</span> Negotiating (<?php echo $stats['negotiating']; ?>)
            </a>
            <a href="?status=dispatched<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'dispatched' ? 'active' : ''; ?>">
                <span>🚚</span> Dispatched (<?php echo $stats['dispatched']; ?>)
            </a>
            <a href="?status=completed<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                <span>✅</span> Completed (<?php echo $stats['completed']; ?>)
            </a>
            <a href="?status=declined<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'declined' ? 'active' : ''; ?>">
                <span>❌</span> Declined (<?php echo $stats['declined']; ?>)
            </a>
            <a href="?status=cancelled<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">
                <span>🚫</span> Cancelled (<?php echo $stats['cancelled']; ?>)
            </a>
        </div>
        
        <!-- Requests List -->
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🚛</div>
                <h3>No rig requests found</h3>
                <p><?php echo !empty($searchQuery) ? 'Try adjusting your search criteria.' : 'No rig requests match the selected filter.'; ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $r): 
                $createdDate = new DateTime($r['created_at']);
                $timeAgo = getTimeAgo($r['created_at']);
            ?>
                <div class="request-card">
                    <div class="request-card-header">
                        <div class="request-card-title">
                            <h3><?php echo htmlspecialchars($r['requester_name']); ?> 
                                <?php if (!empty($r['company_name'])): ?>
                                    <span style="color: #646970; font-weight: 400;">- <?php echo htmlspecialchars($r['company_name']); ?></span>
                                <?php endif; ?>
                            </h3>
                            <div class="request-card-meta">
                                <span>📅 <?php echo $createdDate->format('M d, Y'); ?> (<?php echo $timeAgo; ?>)</span>
                                <span class="status-badge status-<?php echo $r['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
                                </span>
                                <?php if (!empty($r['urgency'])): ?>
                                    <span class="urgency-badge urgency-<?php echo $r['urgency']; ?>">
                                        <?php echo ucfirst($r['urgency']); ?> Priority
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($r['request_number'])): ?>
                                    <span style="font-weight: 600; color: #1d2327;">#<?php echo htmlspecialchars($r['request_number']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="request-card-body">
                        <div class="request-info-item">
                            <div class="request-info-label">📧 Email</div>
                            <div class="request-info-value">
                                <a href="mailto:<?php echo htmlspecialchars($r['requester_email']); ?>" style="color: var(--admin-primary, #2563eb);">
                                    <?php echo htmlspecialchars($r['requester_email']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="request-info-item">
                            <div class="request-info-label">📱 Phone</div>
                            <div class="request-info-value">
                                <?php if (!empty($r['requester_phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($r['requester_phone']); ?>" style="color: var(--admin-primary, #2563eb);">
                                        <?php echo htmlspecialchars($r['requester_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #646970;">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="request-info-item">
                            <div class="request-info-label">📍 Location</div>
                            <div class="request-info-value">
                                <?php echo htmlspecialchars(substr($r['location_address'] ?? '-', 0, 50)); ?>
                                <?php if (strlen($r['location_address'] ?? '') > 50): ?>...<?php endif; ?>
                                <?php if (!empty($r['region'])): ?>
                                    <br><small style="color: #646970;"><?php echo htmlspecialchars($r['region']); ?></small>
                                <?php endif; ?>
                                <?php if ($r['latitude'] !== null && $r['longitude'] !== null): ?>
                                    <br><small style="color: var(--admin-primary, #2563eb);">
                                        Lat: <?php echo number_format($r['latitude'], 6); ?>,
                                        Lng: <?php echo number_format($r['longitude'], 6); ?>
                                        · <a href="https://www.openstreetmap.org/?mlat=<?php echo urlencode($r['latitude']); ?>&mlon=<?php echo urlencode($r['longitude']); ?>#map=16/<?php echo urlencode($r['latitude']); ?>/<?php echo urlencode($r['longitude']); ?>" target="_blank" rel="noopener">View map</a>
                                    </small>
                                <?php else: ?>
                                    <br><small style="color: #b91c1c;">Coordinates missing</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="request-info-item">
                            <div class="request-info-label">🕳️ Boreholes</div>
                            <div class="request-info-value"><?php echo $r['number_of_boreholes'] ?? 1; ?></div>
                        </div>
                        
                        <?php if (!empty($r['preferred_start_date'])): ?>
                        <div class="request-info-item">
                            <div class="request-info-label">📅 Preferred Start</div>
                            <div class="request-info-value"><?php echo date('M d, Y', strtotime($r['preferred_start_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($r['estimated_budget'])): ?>
                        <div class="request-info-item">
                            <div class="request-info-label">💰 Budget</div>
                            <div class="request-info-value">GHS <?php echo number_format($r['estimated_budget'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($r['rig_name'])): ?>
                        <div class="request-info-item">
                            <div class="request-info-label">🚛 Assigned Rig</div>
                            <div class="request-info-value">
                                <?php echo htmlspecialchars($r['rig_name']); ?>
                                <?php if (!empty($r['rig_code'])): ?>
                                    <br><small style="color: #646970;"><?php echo htmlspecialchars($r['rig_code']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($r['assigned_to_name'])): ?>
                        <div class="request-info-item">
                            <div class="request-info-label">👤 Assigned To</div>
                            <div class="request-info-value"><?php echo htmlspecialchars($r['assigned_to_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($r['client_name'])): ?>
                        <div class="request-info-item">
                            <div class="request-info-label">🏢 Linked Client</div>
                            <div class="request-info-value">
                                <a href="<?php echo $baseUrl; ?>/modules/crm.php?action=client-detail&id=<?php echo $r['client_id']; ?>" target="_blank" style="color: var(--admin-primary, #2563eb);">
                                    <?php echo htmlspecialchars($r['client_name']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($r['notes'])): ?>
                        <div class="request-description">
                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($r['notes'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="request-actions">
                        <form method="post" style="display: flex; gap: 8px; flex: 1;">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <select name="status" class="admin-form-group" style="flex: 1; padding: 10px; border: 2px solid #c3c4c7; border-radius: 8px; font-size: 14px;">
                                <option value="new" <?php echo $r['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="under_review" <?php echo $r['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="negotiating" <?php echo $r['status'] === 'negotiating' ? 'selected' : ''; ?>>Negotiating</option>
                                <option value="dispatched" <?php echo $r['status'] === 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                <option value="completed" <?php echo $r['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="declined" <?php echo $r['status'] === 'declined' ? 'selected' : ''; ?>>Declined</option>
                                <option value="cancelled" <?php echo $r['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_status" class="admin-btn admin-btn-primary">Update Status</button>
                        </form>
                        
                        <a href="<?php echo $baseUrl; ?>/modules/requests.php?type=rig&id=<?php echo $r['id']; ?>" class="admin-btn admin-btn-outline" target="_blank">
                            View in ABBIS →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

