<?php
$page_title = 'Contracts';
require_once '../config/app.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$pdo = getDBConnection();
$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

// Ensure table exists (self-init)
try {
    $pdo->query("SELECT 1 FROM contracts LIMIT 1");
} catch (Exception $e) {
    @include_once __DIR__ . '/../database/run-sql.php';
    if (function_exists('run_sql_file')) {
        @run_sql_file(__DIR__ . '/../database/legal_migration.sql');
    }
}

// Handle upload
$message = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    // CSRF protection
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
    try {
        if (!isset($_FILES['contract_file']) || $_FILES['contract_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No file uploaded or upload error.');
        }
        $uploadDir = __DIR__ . '/../uploads/contracts';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $filename = time() . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $_FILES['contract_file']['name']);
        $dest = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($_FILES['contract_file']['tmp_name'], $dest)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        @chmod($dest, 0644);
        $stmt = $pdo->prepare("INSERT INTO contracts (title, contract_type, counterparty, effective_date, file_path, notes) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $_POST['title'] ?? 'Contract',
            $_POST['contract_type'] ?? 'client',
            $_POST['counterparty'] ?? null,
            $_POST['effective_date'] ?: null,
            'uploads/contracts/' . $filename,
            $_POST['notes'] ?? null
        ]);
        $message = 'Contract uploaded successfully.';
    } catch (Throwable $t) {
        $error = $t->getMessage();
    }
    } // End CSRF else block
}

// Fetch contracts
$contracts = [];
try {
    $contracts = $pdo->query("SELECT * FROM contracts ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) { /* ignore */ }

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>📄 Contracts</h1>
        <p>Store and manage signed client/vendor agreements.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="padding:16px; margin-bottom:16px;">
        <h3>Upload Contract</h3>
        <form method="post" enctype="multipart/form-data">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="upload">
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                <div>
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div>
                    <label>Type</label>
                    <select name="contract_type" class="form-control">
                        <option value="client">Client Agreement</option>
                        <option value="vendor">Vendor Agreement</option>
                        <option value="subcontract">Sub-contract</option>
                        <option value="drilling">Drilling Agreement</option>
                    </select>
                </div>
                <div>
                    <label>Counterparty</label>
                    <input type="text" name="counterparty" class="form-control">
                </div>
                <div>
                    <label>Effective Date</label>
                    <input type="date" name="effective_date" class="form-control">
                </div>
                <div>
                    <label>File (PDF)</label>
                    <input type="file" name="contract_file" accept="application/pdf" class="form-control" required>
                </div>
                <div>
                    <label>Notes</label>
                    <input type="text" name="notes" class="form-control">
                </div>
            </div>
            <div style="margin-top:12px;">
                <button class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0;">
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Counterparty</th>
                        <th>Effective</th>
                        <th>File</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$contracts): ?>
                        <tr><td colspan="6" style="text-align:center; padding:12px;">No contracts yet.</td></tr>
                    <?php else: foreach ($contracts as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><?php echo htmlspecialchars($c['contract_type']); ?></td>
                            <td><?php echo htmlspecialchars($c['counterparty'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($c['effective_date'] ?? ''); ?></td>
                            <td><a href="<?php echo htmlspecialchars('../' . $c['file_path']); ?>" target="_blank">View</a></td>
                            <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>


