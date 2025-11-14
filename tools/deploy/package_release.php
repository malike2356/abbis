<?php
/**
 * Build a deployable release archive containing application files, database
 * exports, and deployment metadata.
 *
 * Usage:
 *   php tools/deploy/package_release.php --env=staging --tag="v3.2.1"
 *   php tools/deploy/package_release.php --skip-db
 */

require_once __DIR__ . '/helpers.php';

use DeployTool\Logger;
use DeployTool\Util;
use function DeployTool\ensureDiskSpace;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

// -----------------------------------------------------------------------------=
// Bootstrap & options
// -----------------------------------------------------------------------------=

Util::ensureCli();

$opts = getopt('', [
    'env::',
    'tag::',
    'skip-db',
    'skip-files',
    'help',
]);

if (isset($opts['help'])) {
    echo <<<HELP
Release Packager
----------------
Options:
  --env=<name>      Deployment environment label (e.g. staging, production)
  --tag=<label>     Optional version tag appended to artefact name
  --skip-db         Skip database dump (files only)
  --skip-files      Skip file packaging (database only)
  --help            Show this message

HELP;
    exit(0);
}

$config = Util::loadConfig();
$root   = Util::projectRoot();
$env    = $opts['env'] ?? 'production';
$tag    = $opts['tag'] ?? null;
$skipDb = isset($opts['skip-db']);
$skipFiles = isset($opts['skip-files']);

if ($skipDb && $skipFiles) {
    throw new RuntimeException('Both --skip-db and --skip-files cannot be used together.');
}

// Load application constants
require_once $root . '/config/app.php';
require_once $root . '/config/database.php';

// -----------------------------------------------------------------------------=
// Preflight checks
// -----------------------------------------------------------------------------=

Logger::info('Running preflight checks...');

if (!extension_loaded('zip')) {
    throw new RuntimeException('PHP zip extension is required.');
}

ensureDiskSpace($root, 500); // require at least 500 MB

foreach (['mysqldump', 'mysql'] as $binary) {
    $path = $config['mysql']['bin'][$binary] ?? $binary;
    if (!is_executable($path)) {
        Logger::warn(sprintf('%s not found at %s (will attempt to use PATH fallback).', $binary, $path));
    }
}

// -----------------------------------------------------------------------------=
// Prepare directories
// -----------------------------------------------------------------------------=

$paths = $config['paths'];
$buildRoot  = Util::normalisePath($root . '/' . $paths['build_root']);
$releaseDir = Util::normalisePath($root . '/' . $paths['release_dir']);
$tmpDir     = Util::normalisePath($root . '/' . $paths['tmp_dir']);

Util::ensureDirectory($buildRoot);
Util::ensureDirectory($releaseDir);
Util::ensureDirectory($tmpDir);

$timestamp = date('Ymd-His');
$artefactName = sprintf(
    'abbis-%s-%s%s.zip',
    $env,
    $timestamp,
    $tag ? '-' . preg_replace('/[^A-Za-z0-9._-]/', '', $tag) : ''
);
$artefactPath = $releaseDir . '/' . $artefactName;

Logger::info('Artefact will be written to ' . $artefactPath);

$tempWorking = $tmpDir . '/release-' . $timestamp;
Util::ensureDirectory($tempWorking);

// -----------------------------------------------------------------------------=
// Collect metadata
// -----------------------------------------------------------------------------=

$metadata = [
    'app_name'     => APP_NAME ?? 'ABBIS',
    'version'      => APP_VERSION ?? 'unknown',
    'source_url'   => APP_URL ?? '',
    'environment'  => $env,
    'generated_at' => date(DATE_ATOM),
    'git_commit'   => trim(shell_exec('git rev-parse HEAD 2>/dev/null')) ?: null,
    'support'      => $config['support_email'] ?? null,
];

file_put_contents(
    $tempWorking . '/deployment-info.json',
    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// -----------------------------------------------------------------------------=
// Database dump
// -----------------------------------------------------------------------------=

if (!$skipDb) {
    $dbDir = $tempWorking . '/db';
    Util::ensureDirectory($dbDir);

    $mysqldump = $config['mysql']['bin']['mysqldump'] ?? 'mysqldump';
    $dumpOptions = $config['mysql']['options'] ?? '';

    $schemaFile = $dbDir . '/schema.sql';
    $dataFile   = $dbDir . '/data.sql';

    Logger::info('Dumping database schema...');
    $schemaCommand = sprintf(
        '%s --no-data %s -h%s -u%s %s %s > %s',
        escapeshellcmd($mysqldump),
        $dumpOptions,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        DB_PASS !== '' ? '-p' . escapeshellarg(DB_PASS) : '',
        escapeshellarg(DB_NAME),
        escapeshellarg($schemaFile)
    );
    runShell($schemaCommand);

    Logger::info('Dumping database data...');
    $dataCommand = sprintf(
        '%s --no-create-info %s -h%s -u%s %s %s > %s',
        escapeshellcmd($mysqldump),
        $dumpOptions,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        DB_PASS !== '' ? '-p' . escapeshellarg(DB_PASS) : '',
        escapeshellarg(DB_NAME),
        escapeshellarg($dataFile)
    );
    runShell($dataCommand);
} else {
    Logger::info('Skipping database dump per --skip-db flag.');
}

// -----------------------------------------------------------------------------=
// Package files
// -----------------------------------------------------------------------------=

if (!$skipFiles) {
    Logger::info('Collecting release files...');

    $includes = $config['release_includes'] ?? [];
    $excludes = $config['global_excludes'] ?? [];

    $fileList = [];

    foreach ($includes as $entry) {
        $absPath = $root . '/' . $entry;
        if (!file_exists($absPath)) {
            Logger::warn('Include path missing: ' . $entry);
            continue;
        }

        if (is_dir($absPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                $relative = ltrim(str_replace($root, '', $fileInfo->getPathname()), '/');
                if (Util::isExcluded($relative, $excludes)) {
                    continue;
                }
                if ($fileInfo->isFile()) {
                    $fileList[$relative] = $fileInfo->getPathname();
                }
            }
        } else {
            $relative = ltrim(str_replace($root, '', $absPath), '/');
            if (!Util::isExcluded($relative, $excludes)) {
                $fileList[$relative] = $absPath;
            }
        }
    }

    // Add the generated metadata and scripts
    $fileList['deployment-info.json'] = $tempWorking . '/deployment-info.json';
    if (!$skipDb) {
        $fileList['db/schema.sql'] = $tempWorking . '/db/schema.sql';
        $fileList['db/data.sql']   = $tempWorking . '/db/data.sql';
    }

    // Post-deploy helper script
    $postDeployScript = __DIR__ . '/post-deploy.sh';
    if (file_exists($postDeployScript)) {
        $fileList['scripts/post-deploy.sh'] = $postDeployScript;
    }

    Util::createZip($artefactPath, static function ($zip) use ($fileList, $root) {
        foreach ($fileList as $relative => $absolute) {
            $zip->addFile($absolute, $relative);
        }
    });
} else {
    Logger::info('Skipping file packaging per --skip-files flag.');
}

// -----------------------------------------------------------------------------=
// Cleanup and retention
// -----------------------------------------------------------------------------=

Util::removeDir($tempWorking);

Util::enforceRetention($releaseDir, (int) ($config['retention']['releases'] ?? 5));

Logger::info('Release ready: ' . $artefactPath);

exit(0);

// -----------------------------------------------------------------------------=
// Helpers
// -----------------------------------------------------------------------------=

/**
 * Run a shell command with error handling.
 */
function runShell(string $command): void
{
    Logger::info('Executing: ' . $command);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Command failed with exit code ' . $exitCode . ': ' . $command);
    }
}

