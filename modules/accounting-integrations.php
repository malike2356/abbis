<?php
$pdo = getDBConnection();
$msg = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['provider'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO accounting_integrations (provider, client_id, client_secret, redirect_uri, is_active) VALUES (?,?,?,?,1)
            ON DUPLICATE KEY UPDATE client_id=VALUES(client_id), client_secret=VALUES(client_secret), redirect_uri=VALUES(redirect_uri), is_active=1");
        $stmt->execute([
            $_POST['provider'], trim($_POST['client_id']), trim($_POST['client_secret']), trim($_POST['redirect_uri'])
        ]);
        $msg = 'Integration saved';
    } catch (PDOException $e) { $msg = 'Error: '.$e->getMessage(); }
}

try { $rows = $pdo->query("SELECT * FROM accounting_integrations ORDER BY provider")->fetchAll(); }
catch (PDOException $e) { $rows = []; }
?>

<div class="dashboard-card">
    <h2>Integrations (QuickBooks / Zoho Books)</h2>
    <?php if ($msg): ?><div class="alert alert-success"><?php echo e($msg); ?></div><?php endif; ?>
    <form method="post" style="display:grid; grid-template-columns: repeat(4,1fr); gap:12px; align-items:end;">
        <?php echo CSRF::getTokenField(); ?>
        <div>
            <label class="form-label">Provider</label>
            <select name="provider" class="form-control" required>
                <option value="QuickBooks">QuickBooks</option>
                <option value="ZohoBooks">ZohoBooks</option>
            </select>
        </div>
        <div>
            <label class="form-label">Client ID</label>
            <input name="client_id" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Client Secret</label>
            <input name="client_secret" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Redirect URI</label>
            <input name="redirect_uri" class="form-control" placeholder="https://yourapp/callback">
        </div>
        <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
            <button class="btn btn-primary">Save Integration</button>
        </div>
    </form>

    <div style="margin-top:16px; overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Provider</th><th>Active</th><th>Redirect URI</th><th>Last Updated</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo e($r['provider']); ?></td>
                    <td><?php echo $r['is_active'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo e($r['redirect_uri']); ?></td>
                    <td><?php echo e($r['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top:10px; color: var(--secondary); font-size: 13px;">Use the provider's OAuth flow to obtain tokens. Mapping to ABBIS data will follow our integration adapters.</p>
</div>


