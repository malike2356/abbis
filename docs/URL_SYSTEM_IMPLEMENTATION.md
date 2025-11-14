# URL System Implementation - Single Source of Truth

## Overview

ABBIS now has a **centralized URL management system** that serves as a single source of truth for all URLs. This ensures easy deployment - you only need to change **ONE** configuration value, and all URLs throughout the system will automatically update.

## Architecture

### Core Components

1. **`config/environment.php`** - Defines `APP_URL` constant (single source of truth)
2. **`includes/url-manager.php`** - Provides URL helper functions
3. **`config/app.php`** - Loads URL manager automatically

### URL Priority (Highest to Lowest)

1. `config/deployment.php` - Production deployment config (if exists)
2. Environment variable `APP_URL` - Server-level config
3. Auto-detected from request - Automatic detection
4. Default based on `APP_ENV` - Fallback defaults

## Available Functions

### PHP Functions

All functions are available after including `config/app.php`:

```php
// Base URL functions
site_url('path/to/file.php')           // Full URL
base_url()                              // Base URL only
app_url('path/to/file.php')            // Alias for site_url()

// Specific URL types
module_url('dashboard.php')            // Module pages
api_url('export.php', ['format'=>'csv']) // API endpoints
asset_url('css/style.css')             // Assets (CSS, JS, images)
upload_url('profiles/user.jpg')        // Uploaded files
client_portal_url('dashboard.php')     // Client portal
cms_url('admin/index.php')             // CMS pages
pos_url('api/sales.php')               // POS system

// Utility functions
current_url()                           // Current page URL
redirect_url('path')                    // For redirects
relative_url('path')                    // Relative paths
```

### JavaScript Functions

Include the URL helper in your page header:

```php
<?php echo url_js_helper(); ?>
```

Then use in JavaScript:

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

## Deployment

### Quick Setup

1. **Set environment variable** (recommended):
   ```bash
   # In .htaccess or .env
   SetEnv APP_URL "https://yourdomain.com"
   ```

2. **Or create deployment.php**:
   ```bash
   cp config/deployment.php.example config/deployment.php
   # Edit and set APP_URL
   ```

3. **Or update environment.php defaults**:
   ```php
   $defaults = [
       'production' => 'https://yourdomain.com',
   ];
   ```

### Verification

After deployment, verify URLs:

```bash
php scripts/test-links.php
php scripts/find-hardcoded-urls.php
```

## Migration Status

### ✅ Completed

- [x] URL Manager system created
- [x] Helper functions implemented
- [x] JavaScript URL helper created
- [x] Deployment configuration system
- [x] Environment detection
- [x] Documentation created

### 🔄 In Progress

- [ ] Replace hardcoded URLs in PHP files
- [ ] Replace hardcoded URLs in JavaScript files
- [ ] Update all redirect() calls
- [ ] Update all header('Location: ...') calls
- [ ] Update all form actions
- [ ] Update all href attributes
- [ ] Update all fetch/ajax calls

### 📋 Next Steps

1. Run `php scripts/find-hardcoded-urls.php` to identify all hardcoded URLs
2. Systematically replace hardcoded URLs with helper functions
3. Test all URL generation
4. Deploy and verify

## Examples

### Before (Hardcoded)
```php
// ❌ Bad - Hardcoded URL
header('Location: http://localhost:8080/abbis3.2/modules/dashboard.php');
echo '<a href="http://localhost:8080/abbis3.2/api/export.php">Export</a>';
```

### After (Using Helpers)
```php
// ✅ Good - Uses URL manager
redirect(module_url('dashboard.php'));
echo '<a href="' . api_url('export.php') . '">Export</a>';
```

### JavaScript Before
```javascript
// ❌ Bad - Hardcoded URL
fetch('http://localhost:8080/abbis3.2/api/export.php')
```

### JavaScript After
```javascript
// ✅ Good - Uses URL helper
fetch(ABBIS_URLS.api('export.php'))
```

## Benefits

1. **Single Source of Truth** - Change one value, all URLs update
2. **Easy Deployment** - No need to search/replace URLs
3. **Environment Aware** - Automatically detects production/staging/dev
4. **Type Safety** - Helper functions prevent errors
5. **Maintainability** - Centralized URL logic
6. **Flexibility** - Easy to add new URL types

## Support

For deployment help, see: `docs/DEPLOYMENT_URL_GUIDE.md`

For finding hardcoded URLs, run: `php scripts/find-hardcoded-urls.php`

