#!/usr/bin/env php
<?php
/**
 * ABBIS Deployment Smoke Test
 *
 * Quick sanity checks for database connectivity, required tables, configuration,
 * and basic HTTP responses. Returns exit code 0 when all checks pass.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/helpers.php';

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$pdo = getDBConnection();
$checks = [];
$failures = 0;

function run_check(string $label, callable $callback): void
{
    global $checks, $failures;
    $start = microtime(true);
    try {
        $message = $callback();
        $elapsed = microtime(true) - $start;
        $checks[] = [
            'label' => $label,
            'status' => 'PASS',
            'message' => $message ?: 'OK',
            'time' => $elapsed,
        ];
    } catch (Throwable $e) {
        $elapsed = microtime(true) - $start;
        $checks[] = [
            'label' => $label,
            'status' => 'FAIL',
            'message' => $e->getMessage(),
            'time' => $elapsed,
        ];
        $failures++;
    }
}

run_check('Database connection', function () use ($pdo) {
    $pdo->query('SELECT 1');
    return 'Connected';
});

run_check('Required tables', function () use ($pdo) {
    $required = ['users', 'access_control_logs', 'system_config', 'cms_api_keys', 'accounting_integrations'];
    $missing = [];
    foreach ($required as $table) {
        try {
            $pdo->query("SELECT 1 FROM {$table} LIMIT 0");
        } catch (PDOException $e) {
            $missing[] = $table;
        }
    }
    if ($missing) {
        throw new RuntimeException('Missing tables: ' . implode(', ', $missing));
    }
    return 'Tables present: ' . implode(', ', $required);
});

run_check('Encryption key available', function () {
    $test = Crypto::encrypt('smoke-test');
    $plain = Crypto::decrypt($test);
    if ($plain !== 'smoke-test') {
        throw new RuntimeException('Encryption/decryption mismatch');
    }
    return 'Encryption operational';
});

run_check('Access-control logging writable', function () use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_logs (
        id INT AUTO_INCREMENT PRIMARY KEY
    ) ENGINE=InnoDB");
    $pdo->exec("ALTER TABLE access_control_logs
        ADD COLUMN IF NOT EXISTS user_id INT NULL");
    return 'Schema accessible';
});

run_check('HTTP: login page', function () {
    $url = app_url('login.php');
    $status = http_head($url);
    if (!in_array($status, [200, 302], true)) {
        throw new RuntimeException("Unexpected status {$status} for {$url}");
    }
    return "HTTP {$status} {$url}";
});

run_check('HTTP: offline form', function () {
    $url = app_url('offline/index.html');
    $status = http_head($url);
    if (!in_array($status, [200, 302], true)) {
        throw new RuntimeException("Unexpected status {$status} for {$url}");
    }
    return "HTTP {$status} {$url}";
});

run_check('System config sanity', function () use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_config");
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();
    if ($count === 0) {
        throw new RuntimeException('system_config table is empty');
    }
    return "system_config rows: {$count}";
});

echo PHP_EOL . "ABBIS Smoke Test Results" . PHP_EOL;
echo str_repeat('=', 28) . PHP_EOL;
foreach ($checks as $check) {
    $status = $check['status'] === 'PASS' ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
    $time = number_format($check['time'], 4);
    echo "[{$status}] {$check['label']} ({$time}s)" . PHP_EOL;
    echo "    {$check['message']}" . PHP_EOL;
}
echo PHP_EOL;

if ($failures > 0) {
    echo "{$failures} check(s) failed." . PHP_EOL;
    exit(1);
}

echo "All checks passed." . PHP_EOL;
exit(0);

function http_head(string $url): int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status === 0) {
        throw new RuntimeException('Unable to reach ' . $url . ' (' . curl_error($ch) . ')');
    }
    curl_close($ch);
    return (int) $status;
}

