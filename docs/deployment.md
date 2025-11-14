# Deployment Guide

This document explains how to generate a deployable package for ABBIS and move
it to a new environment (e.g. cPanel shared hosting or a production server).

## Prerequisites

- PHP CLI with the `zip` extension enabled.
- MySQL client tools (`mysqldump`, `mysql`). When running within XAMPP these
  are available under `/opt/lampp/bin/`.
- At least **500 MB** of free disk space for packaging.
- Git (optional) to record the commit hash in deployment metadata.

## 1. Build a Release Archive

From the project root:

```bash
php tools/deploy/package_release.php --env=staging --tag="v3.2.1"
```

Options:

- `--env=<name>` &mdash; label stored in `deployment-info.json`.
- `--tag=<label>` &mdash; append a version string to the archive name.
- `--skip-db` or `--skip-files` &mdash; create partial artefacts.

Once complete, the file `build/releases/abbis-<env>-<timestamp>.zip` is ready
for upload. The archive contains:

- Application code (per `release_includes` in `tools/deploy/config.php`).
- Database schema (`db/schema.sql`) and data (`db/data.sql`).
- `deployment-info.json` with metadata.
- `scripts/post-deploy.sh` helper for the target server.

### Retention

Only the last **5** release archives are kept by default. Adjust this via
`retention.releases` in `tools/deploy/config.php`.

## 2. Deploy on the Target Server

1. Upload the release archive to the web root and extract it:
   ```bash
   unzip abbis-production-20240101-120000.zip
   ```
2. Run the helper script:
   ```bash
   bash scripts/post-deploy.sh
   ```
   The script will:
   - Prompt for the new base URL and update `config/app.php`.
   - Optionally import the `db/schema.sql` and `db/data.sql` dumps.
   - Clear caches and normalise permissions.

3. Configure the web server (Apache virtual host, SSL, etc.).
4. Test the application end-to-end (login, create record, send email).

## 3. Customising the Packaging

Edit `tools/deploy/config.php` to adjust:

- `release_includes` &mdash; directories/files copied into the archive.
- `global_excludes` &mdash; patterns skipped during packaging/backups.
- MySQL binary locations and command options.
- Output directories (`build/` and `backups/`).

## 4. Troubleshooting

| Issue | Fix |
|-------|-----|
| `zip` extension missing | Install `php-zip` and restart CLI |
| `mysqldump not found` | Update `mysql.bin.mysqldump` path in config |
| Permission denied during post-deploy | Run `chmod -R 755 <app>` manually |
| URL still points to old host | Manually edit `config/app.php` and clear browser cache |

For support email the contact configured in `tools/deploy/config.php`
(`support@abbis.com` by default).

