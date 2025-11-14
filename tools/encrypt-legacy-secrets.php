#!/usr/bin/env php
<?php
/**
 * Encrypt legacy plaintext secrets in ABBIS databases.
 *
 * Usage: php tools/encrypt-legacy-secrets.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crypto.php';

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$pdo = getDBConnection();
$totalUpdated = 0;

function encryptValue(?string $value): ?string
{
    if ($value === null || $value === '') {
        return $value;
    }
    if (Crypto::isEncrypted($value)) {
        return $value;
    }
    return Crypto::encrypt($value);
}

function updateSystemConfig(PDO $pdo): int
{
    $keys = [
        'google_client_secret',
        'facebook_app_secret',
        'paystack_secret_key',
        'flutterwave_secret_key',
    ];

    $updated = 0;
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM system_config WHERE config_key = ?");
    $updateStmt = $pdo->prepare("UPDATE system_config SET config_value = ? WHERE config_key = ?");

    foreach ($keys as $key) {
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        $encrypted = encryptValue($row['config_value']);
        if ($encrypted !== $row['config_value']) {
            $updateStmt->execute([$encrypted, $key]);
            $updated++;
        }
    }

    return $updated;
}

function updateCmsApiKeys(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT id, api_key, api_secret FROM cms_api_keys");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare("UPDATE cms_api_keys SET api_key = ?, api_secret = ? WHERE id = ?");
    $updated = 0;

    foreach ($rows as $row) {
        $newKey = encryptValue($row['api_key']);
        $newSecret = encryptValue($row['api_secret']);
        if ($newKey !== $row['api_key'] || $newSecret !== $row['api_secret']) {
            $update->execute([$newKey, $newSecret, $row['id']]);
            $updated++;
        }
    }

    return $updated;
}

function updateAccountingIntegrations(PDO $pdo): int
{
    try {
        $stmt = $pdo->query("SELECT id, client_secret, access_token, refresh_token FROM accounting_integrations");
    } catch (PDOException $e) {
        return 0; // table might not exist yet
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare("
        UPDATE accounting_integrations 
        SET client_secret = ?, access_token = ?, refresh_token = ?
        WHERE id = ?
    ");

    $updated = 0;
    foreach ($rows as $row) {
        $newSecret = encryptValue($row['client_secret']);
        $newAccess = encryptValue($row['access_token']);
        $newRefresh = encryptValue($row['refresh_token']);

        if ($newSecret !== $row['client_secret'] ||
            $newAccess !== $row['access_token'] ||
            $newRefresh !== $row['refresh_token']) {
            $update->execute([$newSecret, $newAccess, $newRefresh, $row['id']]);
            $updated++;
        }
    }

    return $updated;
}

try {
    $pdo->beginTransaction();

    $totalUpdated += updateSystemConfig($pdo);
    $totalUpdated += updateCmsApiKeys($pdo);
    $totalUpdated += updateAccountingIntegrations($pdo);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Encryption migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Legacy secret migration complete. {$totalUpdated} record(s) updated." . PHP_EOL;
exit(0);

