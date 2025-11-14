<?php
/**
 * Client Portal - Projects
 */
require_once __DIR__ . '/auth-check.php';

$pageTitle = 'My Projects';

// Get field reports (projects)
$projects = [];
try {
    if ($clientId) {
        $stmt = $pdo->prepare("
            SELECT id, report_id, report_date, site_name, total_income, total_expenses, status
            FROM field_reports 
            WHERE client_id = ?
            ORDER BY report_date DESC
        ");
        $stmt->execute([$clientId]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Projects fetch error: ' . $e->getMessage());
}

include __DIR__ . '/header.php';
?>

<div class="client-container">
    <div class="page-header">
        <h1><?php echo $pageTitle; ?></h1>
        <p>View your project reports and progress</p>
    </div>

    <?php if (empty($projects)): ?>
        <div class="empty-state-card">
            <p>No projects found. Project reports will appear here once work begins.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Site Name</th>
                        <th>Date</th>
                        <th>Income</th>
                        <th>Expenses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($project['report_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($project['site_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($project['report_date'])); ?></td>
                            <td><?php echo number_format($project['total_income'] ?? 0, 2); ?> GHS</td>
                            <td><?php echo number_format($project['total_expenses'] ?? 0, 2); ?> GHS</td>
                            <td><a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn-link">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

