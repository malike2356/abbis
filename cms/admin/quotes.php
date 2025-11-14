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

// Update quote status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $quoteId = $_POST['quote_id'];
    $status = $_POST['status'];
    $pdo->prepare("UPDATE cms_quote_requests SET status=?, updated_at=NOW() WHERE id=?")->execute([$status, $quoteId]);
    $_SESSION['quotes_message'] = 'Quote status updated successfully';
    header('Location: quotes.php' . ($_GET['status'] ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

// Convert quote to client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_client'])) {
    $quoteId = $_POST['quote_id'];
    try {
        // Get quote details
        $quoteStmt = $pdo->prepare("SELECT * FROM cms_quote_requests WHERE id=?");
        $quoteStmt->execute([$quoteId]);
        $quote = $quoteStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($quote) {
            // Check if client already exists
            $clientCheck = $pdo->prepare("SELECT id FROM clients WHERE email=? OR client_name=?");
            $clientCheck->execute([$quote['email'], $quote['name']]);
            $existingClient = $clientCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existingClient) {
                // Link to existing client
                $pdo->prepare("UPDATE cms_quote_requests SET converted_to_client_id=?, status='converted' WHERE id=?")->execute([$existingClient['id'], $quoteId]);
                $_SESSION['quotes_message'] = 'Quote linked to existing client successfully';
            } else {
                // Create new client
                $clientStmt = $pdo->prepare("INSERT INTO clients (client_name, email, contact_number, address, status, source, created_at) VALUES (?,?,?,?,'lead','CMS Quote Request',NOW())");
                $clientStmt->execute([
                    $quote['name'],
                    $quote['email'],
                    $quote['phone'] ?? null,
                    $quote['location'] ?? null
                ]);
                $clientId = $pdo->lastInsertId();
                
                // Link quote to new client
                $pdo->prepare("UPDATE cms_quote_requests SET converted_to_client_id=?, status='converted' WHERE id=?")->execute([$clientId, $quoteId]);
                
                // Create follow-up task
                try {
                    $followupStmt = $pdo->prepare("INSERT INTO client_followups (client_id, followup_type, followup_date, notes, status, created_at) VALUES (?, 'quote_followup', DATE_ADD(NOW(), INTERVAL 3 DAY), ?, 'pending', NOW())");
                    $followupStmt->execute([
                        $clientId,
                        'Follow up on quote request: ' . ($quote['service_type'] ?? 'General inquiry')
                    ]);
                } catch (Exception $e) {
                    // Followups table might not exist
                }
                
                $_SESSION['quotes_message'] = 'Quote converted to client successfully';
            }
        }
    } catch (Exception $e) {
        $_SESSION['quotes_message'] = 'Error converting quote: ' . $e->getMessage();
    }
    header('Location: quotes.php' . ($_GET['status'] ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

// Get statistics
$stats = [
    'all' => $pdo->query("SELECT COUNT(*) FROM cms_quote_requests")->fetchColumn(),
    'new' => $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='new'")->fetchColumn(),
    'contacted' => $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='contacted'")->fetchColumn(),
    'quoted' => $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='quoted'")->fetchColumn(),
    'converted' => $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='converted'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM cms_quote_requests WHERE status='rejected'")->fetchColumn(),
];

$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query with proper parameter binding
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR service_type LIKE ? OR location LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $searchQuery . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$where = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
$sql = "SELECT * FROM cms_quote_requests $where ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();

// Check for flash message
$message = null;
if (isset($_SESSION['quotes_message'])) {
    $message = $_SESSION['quotes_message'];
    unset($_SESSION['quotes_message']);
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = app_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Requests - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'quotes';
    include 'header.php'; 
    ?>
    <style>
        .quote-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .quote-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .quote-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0f0f1;
        }
        .quote-card-title {
            flex: 1;
        }
        .quote-card-title h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 700;
            color: #1d2327;
        }
        .quote-card-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #646970;
            margin-top: 8px;
        }
        .quote-card-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .quote-card-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .quote-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .quote-info-label {
            font-size: 12px;
            font-weight: 600;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .quote-info-value {
            font-size: 14px;
            color: #1d2327;
            font-weight: 500;
        }
        .quote-description {
            background: #f6f7f7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
            color: #3c434a;
            line-height: 1.6;
        }
        .quote-actions {
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
        .status-contacted { background: rgba(251, 191, 36, 0.1); color: #f59e0b; }
        .status-quoted { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .status-converted { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
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
        
        @media (max-width: 768px) {
            .quote-card-body {
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
            <h1>💬 Quote Requests</h1>
            <p>Manage and track all quote requests from potential clients.</p>
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
                <div class="stat-card-label">All Quotes</div>
            </a>
            <a href="?status=new" class="stat-card <?php echo $statusFilter === 'new' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #2563eb;"><?php echo $stats['new']; ?></div>
                <div class="stat-card-label">New</div>
            </a>
            <a href="?status=contacted" class="stat-card <?php echo $statusFilter === 'contacted' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #f59e0b;"><?php echo $stats['contacted']; ?></div>
                <div class="stat-card-label">Contacted</div>
            </a>
            <a href="?status=quoted" class="stat-card <?php echo $statusFilter === 'quoted' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #8b5cf6;"><?php echo $stats['quoted']; ?></div>
                <div class="stat-card-label">Quoted</div>
            </a>
            <a href="?status=converted" class="stat-card <?php echo $statusFilter === 'converted' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #10b981;"><?php echo $stats['converted']; ?></div>
                <div class="stat-card-label">Converted</div>
            </a>
            <a href="?status=rejected" class="stat-card <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #ef4444;"><?php echo $stats['rejected']; ?></div>
                <div class="stat-card-label">Rejected</div>
            </a>
        </div>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="get" action="quotes.php">
                <?php if ($statusFilter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="🔍 Search by name, email, phone, service, location..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="flex: 1;">
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
            <a href="?status=contacted<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'contacted' ? 'active' : ''; ?>">
                <span>📞</span> Contacted (<?php echo $stats['contacted']; ?>)
            </a>
            <a href="?status=quoted<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'quoted' ? 'active' : ''; ?>">
                <span>💰</span> Quoted (<?php echo $stats['quoted']; ?>)
            </a>
            <a href="?status=converted<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'converted' ? 'active' : ''; ?>">
                <span>✅</span> Converted (<?php echo $stats['converted']; ?>)
            </a>
            <a href="?status=rejected<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="filter-tab <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
                <span>❌</span> Rejected (<?php echo $stats['rejected']; ?>)
            </a>
        </div>
        
        <!-- Quotes List -->
        <?php if (empty($quotes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No quotes found</h3>
                <p><?php echo !empty($searchQuery) ? 'Try adjusting your search criteria.' : 'No quote requests match the selected filter.'; ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($quotes as $q): 
                $createdDate = new DateTime($q['created_at']);
                $timeAgo = getTimeAgo($q['created_at']);
            ?>
                <div class="quote-card">
                    <div class="quote-card-header">
                        <div class="quote-card-title">
                            <h3><?php echo htmlspecialchars($q['name']); ?></h3>
                            <div class="quote-card-meta">
                                <span>📅 <?php echo $createdDate->format('M d, Y'); ?> (<?php echo $timeAgo; ?>)</span>
                                <span class="status-badge status-<?php echo $q['status']; ?>">
                                    <?php echo ucfirst($q['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quote-card-body">
                        <div class="quote-info-item">
                            <div class="quote-info-label">📧 Email</div>
                            <div class="quote-info-value">
                                <a href="mailto:<?php echo htmlspecialchars($q['email']); ?>" style="color: var(--admin-primary, #2563eb);">
                                    <?php echo htmlspecialchars($q['email']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="quote-info-item">
                            <div class="quote-info-label">📱 Phone</div>
                            <div class="quote-info-value">
                                <?php if (!empty($q['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($q['phone']); ?>" style="color: var(--admin-primary, #2563eb);">
                                        <?php echo htmlspecialchars($q['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #646970;">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="quote-info-item">
                            <div class="quote-info-label">🔧 Service</div>
                            <div class="quote-info-value"><?php echo htmlspecialchars($q['service_type'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="quote-info-item">
                            <div class="quote-info-label">📍 Location</div>
                            <div class="quote-info-value"><?php echo htmlspecialchars($q['location'] ?? '-'); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($q['description'])): ?>
                        <div class="quote-description">
                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($q['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="quote-actions">
                        <form method="post" style="display: flex; gap: 8px; flex: 1;">
                            <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                            <select name="status" class="admin-form-group" style="flex: 1; padding: 10px; border: 2px solid #c3c4c7; border-radius: 8px; font-size: 14px;">
                                <option value="new" <?php echo $q['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="contacted" <?php echo $q['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="quoted" <?php echo $q['status'] === 'quoted' ? 'selected' : ''; ?>>Quoted</option>
                                <option value="converted" <?php echo $q['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                                <option value="rejected" <?php echo $q['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <button type="submit" name="update_status" class="admin-btn admin-btn-primary">Update Status</button>
                        </form>
                        
                        <?php if ($q['converted_to_client_id']): ?>
                            <a href="<?php echo $baseUrl; ?>/modules/crm.php?action=view&id=<?php echo $q['converted_to_client_id']; ?>" class="admin-btn admin-btn-outline" target="_blank">
                                🔗 View Client →
                            </a>
                        <?php else: ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                <button type="submit" name="convert_to_client" class="admin-btn admin-btn-success" onclick="return confirm('Convert this quote request to an ABBIS client? This will create a new client record and link it to this quote.');">
                                    ➕ Convert to Client
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-submit status change on select (optional enhancement)
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                // Optional: Add confirmation or auto-submit
            });
        });
    </script>
</body>
</html>

<?php
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

