<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();
$msg = null;
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid security token';
        $msgType = 'error';
    } else {
        if (isset($_POST['close_period'])) {
            try {
                $stmt = $pdo->prepare("UPDATE fiscal_periods SET is_closed=1 WHERE id=?");
                $stmt->execute([intval($_POST['period_id'])]);
                $msg = 'Period closed successfully';
            } catch (PDOException $e) { 
                $msg = 'Error: '.$e->getMessage(); 
                $msgType = 'error';
            }
        } elseif (isset($_POST['create_period'])) {
            try {
                $name = trim($_POST['period_name'] ?? '');
                $startDate = $_POST['start_date'] ?? '';
                $endDate = $_POST['end_date'] ?? '';
                
                if (empty($name) || empty($startDate) || empty($endDate)) {
                    throw new Exception('All fields are required');
                }
                
                if ($startDate >= $endDate) {
                    throw new Exception('End date must be after start date');
                }
                
                // Check for overlapping periods
                $overlapCheck = $pdo->prepare("
                    SELECT COUNT(*) FROM fiscal_periods 
                    WHERE (start_date <= ? AND end_date >= ?) 
                    OR (start_date <= ? AND end_date >= ?)
                    OR (start_date >= ? AND end_date <= ?)
                ");
                $overlapCheck->execute([$startDate, $startDate, $endDate, $endDate, $startDate, $endDate]);
                if ($overlapCheck->fetchColumn() > 0) {
                    throw new Exception('Period overlaps with an existing period');
                }
                
                $stmt = $pdo->prepare("INSERT INTO fiscal_periods (name, start_date, end_date) VALUES (?, ?, ?)");
                $stmt->execute([$name, $startDate, $endDate]);
                $msg = 'Fiscal period created successfully';
            } catch (Exception $e) { 
                $msg = 'Error: '.$e->getMessage(); 
                $msgType = 'error';
            }
        }
    }
}

try { $periods = $pdo->query("SELECT * FROM fiscal_periods ORDER BY start_date DESC")->fetchAll(); }
catch (PDOException $e) { $periods = []; }
?>

<div class="dashboard-card" style="margin-bottom: 24px;">
    <h2>Settings & Fiscal Periods</h2>
    <p style="color: var(--secondary); font-size: 14px; margin-bottom: 20px;">
        Manage your fiscal periods for accounting. Create periods (monthly, quarterly, or annual) and close them when complete to prevent new transactions.
    </p>
    
    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msgType === 'error' ? 'danger' : 'success'; ?>" style="margin-bottom: 20px;">
            <?php echo e($msg); ?>
        </div>
    <?php endif; ?>
    
    <!-- Create New Period Form -->
    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600; color: var(--text);">➕ Create New Fiscal Period</h3>
        <form method="post" class="form-grid" style="grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: end;">
            <?php echo CSRF::getTokenField(); ?>
            <div class="form-group">
                <label class="form-label">Period Name</label>
                <input type="text" name="period_name" class="form-control" 
                       placeholder="e.g., Q1 2025, January 2025, FY 2024" 
                       required>
                <small style="color: var(--secondary); font-size: 11px;">A descriptive name for this period</small>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" name="create_period" class="btn btn-primary" style="white-space: nowrap;">
                    Create Period
                </button>
            </div>
        </form>
    </div>
    
    <!-- Existing Periods Table -->
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($periods)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--secondary);">
                            No fiscal periods created yet. Create your first period above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($periods as $p): 
                        $start = new DateTime($p['start_date']);
                        $end = new DateTime($p['end_date']);
                        $duration = $start->diff($end)->days + 1;
                    ?>
                    <tr style="<?php echo $p['is_closed'] ? 'opacity: 0.7;' : ''; ?>">
                        <td><strong><?php echo e($p['name']); ?></strong></td>
                        <td><?php echo date('M d, Y', strtotime($p['start_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($p['end_date'])); ?></td>
                        <td><?php echo $duration; ?> day<?php echo $duration !== 1 ? 's' : ''; ?></td>
                        <td>
                            <?php if ($p['is_closed']): ?>
                                <span class="badge" style="background: #64748b; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">🔒 Closed</span>
                            <?php else: ?>
                                <span class="badge" style="background: var(--success); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">✅ Open</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!$p['is_closed']): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to close this period? Once closed, no new transactions can be added to it.');">
                                <?php echo CSRF::getTokenField(); ?>
                                <input type="hidden" name="close_period" value="1">
                                <input type="hidden" name="period_id" value="<?php echo $p['id']; ?>">
                                <button class="btn btn-sm btn-outline" type="submit">🔒 Close Period</button>
                            </form>
                            <?php else: ?>
                                <span style="color: var(--secondary); font-size: 12px;">No actions available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


