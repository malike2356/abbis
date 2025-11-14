<?php
/**
 * Backup & Restore Utility
 *
 * Usage:
 *   php tools/deploy/backup.php backup --include-uploads
 *   php tools/deploy/backup.php restore --file=backups/abbis-backup-20231101-120000.zip
 *   php tools/deploy/backup.php list
 */

require_once __DIR__ . '/helpers.php';

use DeployTool\Logger;
use DeployTool\Util;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RuntimeException;

Util::ensureCli();

$argvCopy = $argv;
array_shift($argvCopy); // remove script name
$task = $argvCopy[0] ?? 'help';

$config = Util::loadConfig();
$root   = Util::projectRoot();

require_once $root . '/config/app.php';
require_once $root . '/config/database.php';

$backupsDir = Util::normalisePath($root . '/' . ($config['paths']['backups_dir'] ?? 'backups'));
Util::ensureDirectory($backupsDir);

switch ($task) {
    case 'backup':
        backup($root, $backupsDir, $config, $argvCopy);
        break;
    case 'restore':
        restore($root, $backupsDir, $config, $argvCopy);
        break;
    case 'list':
        listBackups($backupsDir);
        break;
    default:
        echo <<<HELP
Backup Utility
--------------
Commands:
  backup [--label=name] [--include-uploads]
  restore --file=<path>
  list

HELP;
        exit(0);
}

/**
 * Create a new backup archive.
 */
function backup(string $root, string $backupsDir, array $config, array $argv): void
{
    $options = getopt('', ['label::', 'include-uploads'], $optind);
    $includeUploads = isset($options['include-uploads']);
    $label = $options['label'] ?? null;

    Logger::info('Creating backup...');

    ensureDiskSpace($backupsDir, 500);

    $timestamp = date('Ymd-His');
    $name = 'abbis-backup-' . $timestamp;
    if ($label) {
        $name .= '-' . preg_replace('/[^A-Za-z0-9._-]/', '', $label);
    }
    $archivePath = $backupsDir . '/' . $name . '.zip';

    $tmpDir = $backupsDir . '/tmp-' . $timestamp;
    Util::ensureDirectory($tmpDir);

    $metadata = [
        'generated_at' => date(DATE_ATOM),
        'app_version'  => APP_VERSION ?? 'unknown',
        'include_uploads' => $includeUploads,
    ];
    file_put_contents($tmpDir . '/backup-info.json', json_encode($metadata, JSON_PRETTY_PRINT));

    // Database dump
    $dbDir = $tmpDir . '/db';
    Util::ensureDirectory($dbDir);

    $mysqldump = $config['mysql']['bin']['mysqldump'] ?? 'mysqldump';
    $optionsString = $config['mysql']['options'] ?? '';

    $command = sprintf(
        '%s %s -h%s -u%s %s %s > %s',
        escapeshellcmd($mysqldump),
        $optionsString,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        DB_PASS !== '' ? '-p' . escapeshellarg(DB_PASS) : '',
        escapeshellarg(DB_NAME),
        escapeshellarg($dbDir . '/backup.sql')
    );
    runShell($command);

    // Files
    $excludes = $config['global_excludes'] ?? [];
    $includes = $config['release_includes'] ?? [];
    if ($includeUploads) {
        $includes[] = 'uploads';
    }

    $fileList = [];
    foreach ($includes as $entry) {
        $full = $root . '/' . $entry;
        if (!file_exists($full)) {
            continue;
        }
        if (is_dir($full)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($full, RecursiveDirectoryIterator::SKIP_DOTS)
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
            $relative = ltrim(str_replace($root, '', $full), '/');
            if (!Util::isExcluded($relative, $excludes)) {
                $fileList[$relative] = $full;
            }
        }
    }

    $fileList['backup-info.json'] = $tmpDir . '/backup-info.json';
    $fileList['db/backup.sql'] = $tmpDir . '/db/backup.sql';

    Util::createZip($archivePath, static function ($zip) use ($fileList) {
        foreach ($fileList as $relative => $absolute) {
            $zip->addFile($absolute, $relative);
        }
    });

    Util::removeDir($tmpDir);
    Util::enforceRetention($backupsDir, (int) ($config['retention']['backups'] ?? 7));

    Logger::info('Backup created: ' . $archivePath);
}

/**
 * Restore from a backup archive.
 */
function restore(string $root, string $backupsDir, array $config, array $argv): void
{
    $options = getopt('', ['file:']);
    $file = $options['file'] ?? null;
    if (!$file) {
        throw new RuntimeException('Restore requires --file parameter.');
    }

    if (!is_file($file)) {
        throw new RuntimeException('Backup not found: ' . $file);
    }

    Logger::info('Restoring from backup ' . $file);

    $extractDir = $backupsDir . '/restore-' . date('Ymd-His');
    Util::ensureDirectory($extractDir);

    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        throw new RuntimeException('Unable to open archive.');
    }
    $zip->extractTo($extractDir);
    $zip->close();

    $metadataPath = $extractDir . '/backup-info.json';
    if (file_exists($metadataPath)) {
        Logger::info('Metadata: ' . file_get_contents($metadataPath));
    }

    // Restore files (excluding config to avoid overwriting environment-specific data)
    $restorePaths = ['api', 'assets', 'cms', 'includes', 'modules', 'offline', 'scripts', 'sw.js'];
    foreach ($restorePaths as $entry) {
        $source = $extractDir . '/' . $entry;
        if (!file_exists($source)) {
            continue;
        }
        Logger::info('Restoring ' . $entry);
        copyDirectory($source, $root . '/' . $entry);
    }

    // Restore database
    $dbDump = $extractDir . '/db/backup.sql';
    if (file_exists($dbDump)) {
        Logger::info('Restoring database from dump...');
        $mysql = $config['mysql']['bin']['mysql'] ?? 'mysql';
        $command = sprintf(
            '%s -h%s -u%s %s %s < %s',
            escapeshellcmd($mysql),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            DB_PASS !== '' ? '-p' . escapeshellarg(DB_PASS) : '',
            escapeshellarg(DB_NAME),
            escapeshellarg($dbDump)
        );
        runShell($command);
    } else {
        Logger::warn('No database dump found in backup.');
    }

    Util::removeDir($extractDir);
    Logger::info('Restore complete. Please verify the system.');
}

/**
 * List existing backup files.
 */
function listBackups(string $dir): void
{
    $files = array_filter(
        array_map(
            static fn ($file) => $dir . '/' . $file,
            array_diff(scandir($dir, SCANDIR_SORT_DESCENDING), ['.', '..'])
        ),
        'is_file'
    );

    if (empty($files)) {
        echo "No backups found in $dir\n";
        return;
    }

    foreach ($files as $file) {
        echo basename($file) . ' (' . round(filesize($file) / 1024 / 1024, 2) . " MB)\n";
    }
}

function runShell(string $command): void
{
    Logger::info('Executing: ' . $command);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Command failed with exit code ' . $exitCode);
    }
}

function copyDirectory(string $source, string $destination): void
{
    Util::ensureDirectory($destination);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $targetPath = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            Util::ensureDirectory($targetPath);
        } else {
            copy($item->getPathname(), $targetPath);
        }
    }
}

