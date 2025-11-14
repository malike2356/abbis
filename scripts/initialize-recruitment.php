<?php
/**
 * Initialize recruitment module schema and seed sample data.
 * Usage: php scripts/initialize-recruitment.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/recruitment-utils.php';

createDatabaseIfNotExists();

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Initializing recruitment module...\n";

if (!recruitmentEnsureInitialized($pdo)) {
    throw new RuntimeException('Recruitment tables could not be initialized. Check database permissions.');
}

echo "Recruitment schema is ready.\n";

$summary = recruitmentEnsureDemoData($pdo);

echo sprintf(
    "Purged %d legacy vacancies and %d legacy candidates.\n",
    $summary['purged_vacancies'],
    $summary['purged_candidates']
);
echo sprintf(
    "Created %d vacancies, %d candidates, and %d applications.\n",
    $summary['vacancies_created'],
    $summary['candidates_created'],
    $summary['applications_created']
);

echo "Recruitment module initialization complete.\n";

