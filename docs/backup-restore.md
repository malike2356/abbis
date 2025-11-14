# Backup & Restore Strategy

This guide describes how to create verified backups of ABBIS and restore them
when needed.

## Key Components

- **Backup archive** (`backups/abbis-backup-*.zip`) contains:
  - Application files listed in `tools/deploy/config.php` (`release_includes`)
  - Optional `uploads/` directory (enable with `--include-uploads`)
  - A database dump (`db/backup.sql`)
  - `backup-info.json` metadata
- **Retention**: latest 7 archives kept (configurable).
- **Location**: stored inside the project at `backups/`. Copy to off-site
  storage (S3, NAS) for additional safety.

## Backups

Run from project root:

```bash
php tools/deploy/backup.php backup --label=nightly --include-uploads
```

Options:

- `--label=<name>` attaches a human-friendly suffix.
- `--include-uploads` adds the `uploads/` directory to the archive (larger).

### Scheduling

Add a cron job:

```
0 2 * * * /usr/bin/php /var/www/abbis/tools/deploy/backup.php backup --include-uploads >> /var/log/abbis-backup.log 2>&1
```

Ensure cron environment has access to MySQL binaries specified in
`tools/deploy/config.php`.

### Verification

- The script stops on errors (non-zero exit code).
- `backup-info.json` records timestamp and options.
- For deeper verification, periodically restore to a staging database and run
  automated smoke tests.

## Restore

1. Locate the archive:
   ```bash
   php tools/deploy/backup.php list
   ```
2. Restore application files and database:
   ```bash
   php tools/deploy/backup.php restore --file=backups/abbis-backup-20240101-020000-nightly.zip
   ```
3. Update environment-specific settings afterwards (e.g. `config/app.php`,
   `.env`, caching directories).

> **Note:** Database credentials are taken from the local `config/database.php`.
> Ensure it points to the target database before running restore.

## Import / Export (Data-Only)

- To share datasets between environments without full file backup, use
  `db/backup.sql` produced by the backup tool.
- Alternatively, run `php tools/deploy/backup.php backup --skip-files` (planned)
  to export only database as a lightweight option.

## Best Practices

| Scenario | Recommendation |
|----------|----------------|
| Production nightly backups | Run backup with `--include-uploads`, copy archive off-site |
| Pre-deployment snapshot | Run backup immediately before deploying new release |
| Disaster recovery | Restore onto clean environment using latest archive |
| Compliance | Store backups for (n) months depending on policy |

## Manual Cleanup

The backup tool enforces retention automatically. To adjust:

1. Edit `retention.backups` in `tools/deploy/config.php`.
2. Remove or archive additional files manually if necessary.

