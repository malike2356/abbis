# Deployment URL Configuration Guide

## Overview

ABBIS now uses a **Single Source of Truth** for all URLs. This means you only need to change **ONE** configuration value when deploying to a new host, and all URLs throughout the system will automatically update.

## Quick Deployment Steps

### 1. Set Environment Variable (Recommended)

The easiest way is to set the `APP_URL` environment variable on your server:

**For cPanel:**
```bash
# Add to .htaccess or .env file
SetEnv APP_URL "https://yourdomain.com"
```

**For Hostinger:**
```bash
# Add to .env file in root directory
APP_URL=https://yourdomain.com
```

### 2. Or Update environment.php

Edit `config/environment.php` and change the default URL:

```php
$defaults = [
    'production'  => 'https://yourdomain.com',  // ← Change this
    'staging'     => 'https://abbis.veloxpsi.com',
    'development' => 'http://localhost:8080/abbis3.2',
];
```

### 3. Or Use deployment.php

1. Copy the example file:
   ```bash
   cp config/deployment.php.example config/deployment.php
   ```

2. Edit `config/deployment.php`:
   ```php
   define('APP_URL', 'https://yourdomain.com');
   ```

3. Update `config/environment.php` to load deployment config:
   ```php
   if (file_exists(__DIR__ . '/deployment.php')) {
       require_once __DIR__ . '/deployment.php';
   }
   ```

## URL Helper Functions

All URLs should be generated using these helper functions:

### PHP Functions

```php
// Base URL
site_url('path/to/file.php')
// Returns: https://yourdomain.com/path/to/file.php

// Modules
module_url('dashboard.php')
// Returns: https://yourdomain.com/modules/dashboard.php

// API endpoints
api_url('export.php', ['format' => 'csv'])
// Returns: https://yourdomain.com/api/export.php?format=csv

// Assets
asset_url('css/style.css')
// Returns: https://yourdomain.com/assets/css/style.css

// Client Portal
client_portal_url('dashboard.php')
// Returns: https://yourdomain.com/client-portal/dashboard.php

// CMS
cms_url('admin/index.php')
// Returns: https://yourdomain.com/cms/admin/index.php

// POS
pos_url('api/sales.php')
// Returns: https://yourdomain.com/pos/api/sales.php

// Uploads
upload_url('profiles/user123.jpg')
// Returns: https://yourdomain.com/uploads/profiles/user123.jpg
```

### JavaScript Functions

After including the URL helper, use:

```javascript
// Base URL
ABBIS_URLS.site('path/to/file.php')

// API calls
ABBIS_URLS.api('export.php', {format: 'csv'})

// Modules
ABBIS_URLS.module('dashboard.php')

// Assets
ABBIS_URLS.asset('css/style.css')

// Client Portal
ABBIS_URLS.clientPortal('dashboard.php')

// POS
ABBIS_URLS.pos('api/sales.php')
```

## Common Deployment Scenarios

### Scenario 1: Root Domain
```
Domain: https://yourdomain.com
APP_URL: 'https://yourdomain.com'
```

### Scenario 2: Subdomain
```
Domain: https://abbis.yourdomain.com
APP_URL: 'https://abbis.yourdomain.com'
```

### Scenario 3: Subdirectory
```
Domain: https://yourdomain.com/abbis3.2
APP_URL: 'https://yourdomain.com/abbis3.2'
```

### Scenario 4: cPanel with Subdirectory
```
Domain: https://yourdomain.com/public_html/abbis3.2
APP_URL: 'https://yourdomain.com/abbis3.2'
```

## Verification After Deployment

Run the verification script:

```bash
php scripts/test-links.php
```

This will check:
- ✅ All files exist
- ✅ No broken links
- ✅ URL generation working correctly

## Troubleshooting

### URLs Still Showing localhost

1. Clear any cached files
2. Check that `APP_URL` is set correctly
3. Verify `config/environment.php` is loading correctly
4. Check for hardcoded URLs (run `php scripts/find-hardcoded-urls.php`)

### Mixed Content Warnings (HTTP/HTTPS)

Ensure `APP_URL` uses `https://` if your site uses SSL:
```php
define('APP_URL', 'https://yourdomain.com');  // Not http://
```

### Subdirectory Issues

If deploying to a subdirectory, include it in `APP_URL`:
```php
define('APP_URL', 'https://yourdomain.com/abbis3.2');
```

## Migration Checklist

- [ ] Set `APP_URL` environment variable or update config
- [ ] Verify all URLs use helper functions (run find-hardcoded-urls.php)
- [ ] Test login/logout redirects
- [ ] Test API endpoints
- [ ] Test file uploads/downloads
- [ ] Test email links (if applicable)
- [ ] Verify JavaScript URLs work
- [ ] Check client portal links
- [ ] Check CMS links
- [ ] Check POS links
- [ ] Run full system test

## Need Help?

If URLs are still broken after deployment:
1. Check the hardcoded URLs report: `docs/HARDCODED_URLS_REPORT.md`
2. Search for remaining hardcoded URLs: `grep -r "localhost:8080" .`
3. Verify `APP_URL` is being used: `grep -r "APP_URL" config/`

