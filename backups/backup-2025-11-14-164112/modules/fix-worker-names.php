<?php
/**
 * Fix Worker Names - Admin Tool
 * Updates worker names to match the corrected list
 */
$page_title = 'Fix Worker Names';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    flash('error', 'Access denied. Admin privileges required.');
    redirect('dashboard.php');
}

$pdo = getDBConnection();
$message = '';
$error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_fix'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Step 1: Remove workers not in the corrected list
            $deleteStmt = $pdo->prepare("DELETE FROM workers WHERE worker_name IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $deleteStmt->execute(['Peter', 'Jawich', 'Razak', 'Anthony Emma', 'Mtw', 'BOSS', 'new', 'Finest', 'Giet', 'Linest', 'linef', 'Internal', 'Castro']);
            $deletedCount = $deleteStmt->rowCount();
            
            // Step 2: Update roles to match corrected list
            $updates = [
                ['Atta', 'Driller'],
                ['Isaac', 'Rig Driver / Spanner'],
                ['Tawiah', 'Rodboy'],
                ['Godwin', 'Rodboy'],
                ['Asare', 'Rodboy'],
                ['Earnest', 'Driller'],
                ['Owusua', 'Rig Driver'],
                ['Rasta', 'Spanner boy / Table boy'],
                ['Chief', 'Rodboy'],
                ['Kwesi', 'Rodboy']
            ];
            
            $updateStmt = $pdo->prepare("UPDATE workers SET role = ? WHERE worker_name = ?");
            $updatedCount = 0;
            foreach ($updates as $update) {
                $updateStmt->execute([$update[1], $update[0]]);
                $updatedCount += $updateStmt->rowCount();
            }
            
            // Step 3: Remove workers with incorrect roles
            $cleanupStmt = $pdo->prepare("
                DELETE FROM workers WHERE 
                (worker_name = 'Atta' AND role NOT LIKE '%Driller%') OR
                (worker_name = 'Isaac' AND role NOT LIKE '%Rig Driver%' AND role NOT LIKE '%Spanner%') OR
                (worker_name = 'Tawiah' AND role NOT LIKE '%Rodboy%') OR
                (worker_name = 'Godwin' AND role NOT LIKE '%Rodboy%') OR
                (worker_name = 'Asare' AND role NOT LIKE '%Rodboy%') OR
                (worker_name = 'Earnest' AND role NOT LIKE '%Driller%') OR
                (worker_name = 'Owusua' AND role NOT LIKE '%Rig Driver%') OR
                (worker_name = 'Rasta' AND (role NOT LIKE '%Spanner%' AND role NOT LIKE '%Table%')) OR
                (worker_name = 'Chief' AND role NOT LIKE '%Rodboy%') OR
                (worker_name = 'Kwesi' AND role NOT LIKE '%Rodboy%')
            ");
            $cleanupStmt->execute();
            $cleanedCount = $cleanupStmt->rowCount();
            
            // Get final results
            $verifyStmt = $pdo->query("
                SELECT worker_name, role, status, COUNT(*) as count 
                FROM workers 
                GROUP BY worker_name, role, status 
                ORDER BY worker_name
            ");
            $results = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $pdo->commit();
            
            $message = "✅ Worker names fixed successfully! Deleted: $deletedCount, Updated: $updatedCount, Cleaned: $cleanedCount";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get current workers
$currentStmt = $pdo->query("
    SELECT worker_name, role, status, COUNT(*) as count 
    FROM workers 
    GROUP BY worker_name, role, status 
    ORDER BY worker_name
");
$currentWorkers = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-card">
        <h1>Fix Worker Names</h1>
        <p>This tool updates worker names to match the corrected list:</p>
        
        <div style="background: var(--card); padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h3>Corrected List:</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                <div>
                    <strong>Red Rig:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Atta - Driller</li>
                        <li>Isaac - Rig Driver / Spanner</li>
                        <li>Tawiah - Rodboy</li>
                        <li>Godwin - Rodboy</li>
                        <li>Asare - Rodboy</li>
                    </ul>
                </div>
                <div>
                    <strong>Green Rig:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Earnest - Driller</li>
                        <li>Owusua - Rig Driver</li>
                        <li>Rasta - Spanner boy / Table boy</li>
                        <li>Chief - Rodboy</li>
                        <li>Godwin - Rodboy</li>
                        <li>Kwesi - Rodboy</li>
                    </ul>
                </div>
            </div>
            <p style="margin-top: 10px; color: var(--secondary);"><strong>Note:</strong> Only the names in the corrected list above will be kept. All other names will be removed.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo e($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" style="margin: 20px 0;">
            <?php echo CSRF::getTokenField(); ?>
            <button type="submit" name="run_fix" class="btn btn-primary" onclick="return confirm('Are you sure you want to fix worker names? This will delete workers not in the corrected list and update roles.')">
                🔧 Run Fix Worker Names
            </button>
            <a href="hr.php?action=employees" class="btn btn-outline">← Back to HR</a>
        </form>
        
        <h2>Current Workers (Before Fix)</h2>
        <?php if (empty($currentWorkers)): ?>
            <p>No workers found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentWorkers as $worker): ?>
                        <tr>
                            <td><?php echo e($worker['worker_name']); ?></td>
                            <td><?php echo e($worker['role']); ?></td>
                            <td><span class="badge badge-<?php echo $worker['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($worker['status']); ?></span></td>
                            <td><?php echo $worker['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <h2 style="margin-top: 30px;">After Fix:</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $worker): ?>
                        <tr>
                            <td><?php echo e($worker['worker_name']); ?></td>
                            <td><?php echo e($worker['role']); ?></td>
                            <td><span class="badge badge-<?php echo $worker['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($worker['status']); ?></span></td>
                            <td><?php echo $worker['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

