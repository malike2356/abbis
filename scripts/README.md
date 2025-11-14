# POS System Maintenance Scripts

This directory contains utility scripts for maintaining and verifying the POS system.

## Available Scripts

### 1. `verify-pos-schema.php`
Verifies that all required POS database tables and columns exist.

**Usage:**
```bash
php scripts/verify-pos-schema.php
```

**What it does:**
- Checks for required POS tables (pos_sales, pos_products, etc.)
- Verifies required columns exist in each table
- Checks optional tables (pos_cash_drawer_sessions, pos_refunds, etc.)
- Reports any missing tables or columns

**Exit codes:**
- `0` - All required tables/columns present
- `1` - Missing required tables or columns

---

### 2. `cleanup-logs.php`
Removes old log files to prevent disk space issues.

**Usage:**
```bash
# Dry run (see what would be deleted)
php scripts/cleanup-logs.php --dry-run

# Actually delete logs older than 30 days (default)
php scripts/cleanup-logs.php

# Keep logs for 60 days
php scripts/cleanup-logs.php --days=60

# Show help
php scripts/cleanup-logs.php --help
```

**Options:**
- `--days=N` - Keep logs for N days (default: 30)
- `--dry-run` - Show what would be deleted without actually deleting
- `--help` - Show help message

**What it does:**
- Scans the logs directory for `.log` files
- Deletes files older than the specified number of days
- Reports how much space was freed

**Recommended:** Run this script via cron job weekly:
```cron
0 2 * * 0 cd /path/to/abbis3.2 && php scripts/cleanup-logs.php --days=30
```

---

### 3. `check-permissions.php`
Verifies role-based access controls are working correctly.

**Usage:**
```bash
# Check all users
php scripts/check-permissions.php

# Check specific user
php scripts/check-permissions.php admin
```

**What it does:**
- Lists all users and their POS permissions
- Verifies role-based access (admin/manager vs regular users)
- Checks if users can see admin-only features
- Validates permission system is working

---

## Health Check Endpoint

### `/pos/api/health.php`
REST API endpoint that returns system health status.

**Access:** Requires authentication (POS access permission)

**Response format:**
```json
{
    "status": "ok",
    "timestamp": "2025-11-10T12:00:00+00:00",
    "version": "3.2.0",
    "environment": "development",
    "checks": {
        "database": {
            "status": "ok",
            "message": "Database connection successful"
        },
        "tables": {
            "pos_sales": {
                "status": "ok",
                "message": "Table exists"
            }
        },
        "logs": {
            "status": "ok",
            "writable": true,
            "exists": true
        }
    },
    "statistics": {
        "total_sales": 150,
        "active_products": 45,
        "active_stores": 3
    }
}
```

**Status values:**
- `ok` - All systems operational
- `warning` - Some optional features missing (non-critical)
- `error` - Critical systems failing

**HTTP Status Codes:**
- `200` - System is healthy (may have warnings)
- `503` - System is unhealthy (errors detected)

**Usage examples:**
```bash
# Check health via curl
curl -b cookies.txt http://localhost:8080/abbis3.2/pos/api/health.php

# Check health in browser (when logged in)
http://localhost:8080/abbis3.2/pos/api/health.php
```

---

## Quick Start Guide

### Initial Setup Verification
```bash
# 1. Verify database schema
php scripts/verify-pos-schema.php

# 2. Check permissions
php scripts/check-permissions.php

# 3. Test health endpoint
curl http://localhost:8080/abbis3.2/pos/api/health.php
```

### Regular Maintenance
```bash
# Weekly log cleanup (add to cron)
php scripts/cleanup-logs.php --days=30

# Monthly schema verification
php scripts/verify-pos-schema.php
```

---

## Troubleshooting

### Schema Verification Fails
If `verify-pos-schema.php` reports missing tables:
1. Check that database migrations have been run
2. The `PosRepository` class auto-runs migrations on first use
3. Manually run migrations from `database/migrations/pos/` if needed

### Permission Check Shows Issues
If permissions aren't working:
1. Verify user roles in the `users` table
2. Check permission assignments in the permissions system
3. Ensure session is properly initialized

### Health Check Returns Errors
1. Check database connectivity
2. Verify log directory permissions
3. Review error logs for details
4. Run schema verification script

---

## Notes

- All scripts require database access (via `config/database.php`)
- Scripts should be run from the project root directory
- Health check endpoint requires authentication
- Log cleanup is safe to run automatically (use `--dry-run` first to verify)

