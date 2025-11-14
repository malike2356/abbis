#!/usr/bin/env php
<?php
/**
 * CLI helper for importing onboarding datasets into ABBIS.
 *
 * Usage:
 *   php scripts/import-dataset.php clients /path/to/clients.csv --delimiter="," --update --skip-blank
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Import/ImportManager.php';

$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];

$usage = <<<USAGE
Usage:
  php scripts/import-dataset.php <dataset> <csv_path> [--delimiter=","|";"|"\\t"|"|"] [--no-update] [--allow-blank-overwrite]

Examples:
  php scripts/import-dataset.php clients storage/import/clients.csv
  php scripts/import-dataset.php rigs rigs.csv --delimiter=";" --no-update

Flags:
  --delimiter=VALUE           CSV delimiter (default ",")
  --no-update                 Do not update existing rows (insert only)
  --allow-blank-overwrite     Allow blank cells to overwrite existing values

Datasets:
  clients, rigs, workers, catalog_items
USAGE;

if ($argc < 3) {
    fwrite(STDERR, $usage . PHP_EOL);
    exit(1);
}

$dataset = trim($argv[1]);
$csvPath = $argv[2];
$delimiter = ',';
$updateExisting = true;
$skipBlankUpdates = true;

for ($i = 3; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--delimiter=') === 0) {
        $delimiter = substr($arg, strlen('--delimiter='));
    } elseif ($arg === '--no-update') {
        $updateExisting = false;
    } elseif ($arg === '--allow-blank-overwrite') {
        $skipBlankUpdates = false;
    } elseif ($arg === '--help') {
        fwrite(STDOUT, $usage . PHP_EOL);
        exit(0);
    }
}

if (!file_exists($csvPath)) {
    fwrite(STDERR, "CSV file not found: {$csvPath}" . PHP_EOL);
    exit(1);
}

$manager = new ImportManager();
$definition = $manager->getDefinition($dataset);
if (!$definition) {
    fwrite(STDERR, "Unknown dataset: {$dataset}" . PHP_EOL);
    exit(1);
}

try {
    fwrite(STDOUT, "📄 Previewing {$dataset} import from {$csvPath}" . PHP_EOL);
    $preview = $manager->buildPreview($dataset, $csvPath, $delimiter);
    $mapping = $preview['suggested_mapping'] ?? [];

    $missing = [];
    foreach ($definition['fields'] as $fieldKey => $field) {
        if (!empty($field['required']) && (empty($mapping[$fieldKey]) || $mapping[$fieldKey] === '__skip__')) {
            $missing[] = $field['label'] ?? $fieldKey;
        }
    }

    if (!empty($missing)) {
        fwrite(STDERR, "Missing required mappings. CSV headers should match field names or labels." . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . implode(', ', $missing) . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, '🔁 Update existing: ' . ($updateExisting ? 'yes' : 'no') . PHP_EOL);
    fwrite(STDOUT, '🧹 Skip blank cells on update: ' . ($skipBlankUpdates ? 'yes' : 'no') . PHP_EOL);

    $summary = $manager->importFromCsv($dataset, $csvPath, $delimiter, $mapping, [
        'update_existing' => $updateExisting,
        'skip_blank_updates' => $skipBlankUpdates,
    ]);

    fwrite(STDOUT, PHP_EOL . "✅ Import complete" . PHP_EOL);
    fwrite(STDOUT, "Inserted: {$summary['inserted']} | Updated: {$summary['updated']} | Skipped: {$summary['skipped']} | Processed: {$summary['total_rows']}" . PHP_EOL);

    if (!empty($summary['errors'])) {
        fwrite(STDOUT, PHP_EOL . "Errors:" . PHP_EOL);
        foreach ($summary['errors'] as $error) {
            fwrite(STDOUT, " - Row {$error['row']}: {$error['message']}" . PHP_EOL);
        }
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}


