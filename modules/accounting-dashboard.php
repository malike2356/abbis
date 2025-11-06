<?php
// Lightweight snapshot derived from journal lines
$pdo = getDBConnection();
$kpis = [
    'accounts' => 0,
    'entries' => 0,
    'debits' => 0,
    'credits' => 0,
];
try { $kpis['accounts'] = (int)$pdo->query("SELECT COUNT(*) FROM chart_of_accounts")->fetchColumn(); } catch (Throwable $e) {}
try { $kpis['entries'] = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn(); } catch (Throwable $e) {}
try { $kpis['debits'] = (float)$pdo->query("SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines")->fetchColumn(); } catch (Throwable $e) {}
try { $kpis['credits'] = (float)$pdo->query("SELECT COALESCE(SUM(credit),0) FROM journal_entry_lines")->fetchColumn(); } catch (Throwable $e) {}
?>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
    <div class="dashboard-card"><div class="stat-info"><h3><?php echo number_format($kpis['accounts']); ?></h3><p>Accounts</p></div></div>
    <div class="dashboard-card"><div class="stat-info"><h3><?php echo number_format($kpis['entries']); ?></h3><p>Journal Entries</p></div></div>
    <div class="dashboard-card"><div class="stat-info"><h3>GHS <?php echo number_format($kpis['debits'],2); ?></h3><p>Total Debits</p></div></div>
    <div class="dashboard-card"><div class="stat-info"><h3>GHS <?php echo number_format($kpis['credits'],2); ?></h3><p>Total Credits</p></div></div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
    <h2>Quick Actions</h2>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:12px;">
        <a href="?action=accounts" class="btn btn-outline">📚 Manage Accounts</a>
        <a href="?action=journal" class="btn btn-outline">🧾 New Journal Entry</a>
        <a href="?action=trial" class="btn btn-outline">🧮 View Trial Balance</a>
        <a href="?action=integrations" class="btn btn-outline">🔌 Integrations</a>
    </div>
</div>


