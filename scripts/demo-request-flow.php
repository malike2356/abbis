<?php
putenv('DB_CONNECTION=sqlite');
putenv('USE_SQLITE=1');
putenv('MAIL_DRIVER=log');

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "PDO SQLite driver is not available. Run this script on a PHP build with pdo_sqlite enabled.\n");
    exit(1);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/bootstrap-sqlite.php';
require_once __DIR__ . '/../includes/request-response-manager.php';

$pdo = getDBConnection();
$manager = new RequestResponseManager($pdo);

function printHeading(string $title): void
{
    echo "\n==== {$title} ====\n";
}

function printResponseSummary(array $response): void
{
    echo "Response #{$response['id']} ({$response['response_code']}) - Status: {$response['status']} - Total: {$response['total']}\n";
}

// Exercise Quote flow
$quoteId = (int)$pdo->query('SELECT id FROM cms_quote_requests ORDER BY id LIMIT 1')->fetchColumn();
printHeading('Quote Request Flow');
$response = $manager->generateQuoteResponse($quoteId, 1);
printResponseSummary($response);

$manager->addCustomItem($response['id'], [
    'item_name' => 'Custom Mobilisation Surcharge',
    'description' => 'Additional mobilisation cost for remote site.',
    'quantity' => 1,
    'unit_price' => 1500,
    'tax_rate' => 0,
], 1);
$response = $manager->getResponse($response['id']);
printResponseSummary($response);

echo "Submitting for approval...\n";
$manager->submitForApproval($response['id'], 2);
printResponseSummary($manager->getResponse($response['id']));

echo "Approving response...\n";
$manager->approveResponse($response['id'], 3);
printResponseSummary($manager->getResponse($response['id']));

echo "Sending response to client...\n";
$manager->sendResponseEmail($response['id'], 'client+quote@example.com', 1, ['note' => 'Demo quote send']);
printResponseSummary($manager->getResponse($response['id']));

// Exercise Rig flow
printHeading('Rig Request Flow');
$rigId = (int)$pdo->query('SELECT id FROM rig_requests ORDER BY id LIMIT 1')->fetchColumn();
$rigResponse = $manager->generateRigResponse($rigId, 1);
printResponseSummary($rigResponse);

$manager->submitForApproval($rigResponse['id'], 2);
$manager->approveResponse($rigResponse['id'], 3);
$manager->sendResponseEmail($rigResponse['id'], 'client+rig@example.com', 1, ['note' => 'Demo rig send']);
printResponseSummary($manager->getResponse($rigResponse['id']));

// Display request histories
echo "\nQuote history entries:\n";
foreach ($manager->getStatusHistoryForRequest('quote', $quoteId) as $entry) {
    echo " - {$entry['created_at']}: {$entry['old_status']} -> {$entry['new_status']} ({$entry['note']})\n";
}

echo "\nRig history entries:\n";
foreach ($manager->getStatusHistoryForRequest('rig', $rigId) as $entry) {
    echo " - {$entry['created_at']}: {$entry['old_status']} -> {$entry['new_status']} ({$entry['note']})\n";
}

// Summarize logged emails
echo "\nEmail log entries:\n";
$stmt = $pdo->query('SELECT driver, subject, status, created_at FROM email_logs ORDER BY id');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $email) {
    echo " - [{$email['driver']}] {$email['subject']} ({$email['status']}) at {$email['created_at']}\n";
}

echo "\nDemo flow complete.\n";
