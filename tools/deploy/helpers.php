<?php
/**
 * Shared helpers for deployment utilities.
 */

namespace DeployTool;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RuntimeException;

/**
 * Console logger with level support.
 */
class Logger
{
    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warn(string $message): void
    {
        self::write('WARN', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    private static function write(string $level, string $message): void
    {
        fwrite(STDOUT, sprintf("[%s] %s\n", $level, $message));
    }
}

/**
 * Utility collection.
 */
class Util
{
    public static function ensureCli(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Deployment tools must be run from the command line.');
        }
    }

    public static function projectRoot(): string
    {
        return realpath(__DIR__ . '/..//..');
    }

    public static function loadConfig(): array
    {
        $configPath = __DIR__ . '/config.php';
        if (!file_exists($configPath)) {
            throw new RuntimeException('Configuration file not found: ' . $configPath);
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('Deployment config must return an array.');
        }

        return $config;
    }

    public static function normalisePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public static function isExcluded(string $relativePath, array $excludes): bool
    {
        foreach ($excludes as $needle) {
            if ($needle === '') {
                continue;
            }
            if (strpos($relativePath, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Failed to create directory: ' . $path);
        }
    }

    public static function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($path);
    }

    public static function createZip(string $target, callable $populator): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open archive: ' . $target);
        }

        $populator($zip);

        $zip->close();
    }

    /**
     * Retain only newest $limit files in a directory.
     */
    public static function enforceRetention(string $dir, int $limit): void
    {
        if (!is_dir($dir) || $limit < 1) {
            return;
        }

        $files = array_filter(
            array_map(
                static fn ($file) => $dir . '/' . $file,
                array_diff(scandir($dir, SCANDIR_SORT_DESCENDING), ['.', '..'])
            ),
            'is_file'
        );

        $filesToRemove = array_slice($files, $limit);
        foreach ($filesToRemove as $file) {
            @unlink($file);
        }
    }
}

/**
 * Simple disk space guard (in MB).
 */
function ensureDiskSpace(string $path, int $requiredMb): void
{
    $freeMb = (int) floor(disk_free_space($path) / 1024 / 1024);
    if ($freeMb < $requiredMb) {
        throw new RuntimeException(sprintf(
            'Insufficient disk space. Required: %d MB, available: %d MB',
            $requiredMb,
            $freeMb
        ));
    }
}

