<?php
/**
 * Deployment Toolkit Configuration
 *
 * Central place to adjust paths, exclusions, and behaviour for the release
 * packaging and backup utilities.
 */

return [
    /*
     * Path to the MySQL utilities. Adjust if running outside XAMPP.
     */
    'mysql' => [
        'bin' => [
            'mysqldump' => '/opt/lampp/bin/mysqldump',
            'mysql'     => '/opt/lampp/bin/mysql',
        ],
        'options' => '--routines --triggers --single-transaction --set-charset',
    ],

    /*
     * Paths relative to the project root that should be bundled in releases.
     * Directories are added recursively. Individual files can also be listed.
     */
    'release_includes' => [
        'api',
        'assets',
        'cms',
        'config',
        'database',
        'docs',
        'includes',
        'modules',
        'offline',
        'scripts',
        'sw.js',
        'index.php',
        'login.php',
        'logout.php',
        'manifest.webmanifest',
        'README.md',
    ],

    /*
     * Global exclude patterns (case-sensitive substrings). If a path contains
     * any entry here, it will be skipped when building releases or backups.
     */
    'global_excludes' => [
        '.git',
        '.gitignore',
        '.DS_Store',
        'node_modules',
        'vendor',
        'storage/cache',
        'storage/logs',
        'logs',
        'uploads/payslips',
        'build',
        'backups',
        'tools/deploy',
    ],

    /*
     * Destination directories for build artefacts and backups.
     */
    'paths' => [
        'build_root'   => 'build',
        'release_dir'  => 'build/releases',
        'tmp_dir'      => 'build/tmp',
        'backups_dir'  => 'backups',
    ],

    /*
     * Retention policy in number of artefacts to keep. Older files will be
     * deleted automatically after a successful job.
     */
    'retention' => [
        'releases' => 5,
        'backups'  => 7,
    ],

    /*
     * Optional email for deployment metadata.
     */
    'support_email' => getenv('ABBIS_SUPPORT_EMAIL') ?: 'support@abbis.com',
];

