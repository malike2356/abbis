# URL System - Single Source of Truth ✅

## ✅ Implementation Complete

A centralized URL management system has been implemented for ABBIS. This ensures **easy deployment** - you only need to change **ONE** configuration value, and all URLs throughout the system will automatically update.

## 🎯 What Was Created

### 1. URL Manager System
- **File**: `includes/url-manager.php`
- **Purpose**: Provides helper functions for all URL types
- **Auto-loaded**: Included automatically via `config/app.php`

### 2. Helper Functions Available

**PHP Functions:**
- `site_url($path)` - Full URL for any path
- `module_url($module)` - Module pages
- `api_url($endpoint, $params)` - API endpoints
- `asset_url($path)` - Assets (CSS, JS, images)
- `upload_url($file)` - Uploaded files
- `client_portal_url($page)` - Client portal
- `cms_url($path)` - CMS pages
- `pos_url($path)` - POS system
- `redirect_url($path)` - For redirects

**JavaScript Functions:**
- `ABBIS_URLS.site(path)` - Full URL
- `ABBIS_URLS.api(endpoint, params)` - API calls
- `ABBIS_URLS.module(module)` - Modules
- `ABBIS_URLS.asset(path)` - Assets
- `ABBIS_URLS.clientPortal(page)` - Client portal
- `ABBIS_URLS.pos(path)` - POS system

### 3. Deployment Configuration

**Priority Order (Highest to Lowest):**
1. `config/deployment.php` - Production config (create from `.example`)
2. Environment variable `APP_URL` - Server-level
3. Auto-detected from request - Automatic
4. Default based on `APP_ENV` - Fallback

### 4. Documentation

- `docs/DEPLOYMENT_URL_GUIDE.md` - Complete deployment guide
- `docs/URL_SYSTEM_IMPLEMENTATION.md` - Technical details
- `config/deployment.php.example` - Template for production

## 🚀 How to Deploy

### Option 1: Environment Variable (Recommended)
```bash
# In .htaccess or .env
SetEnv APP_URL "https://yourdomain.com"
```

### Option 2: deployment.php
```bash
cp config/deployment.php.example config/deployment.php
# Edit and set: define('APP_URL', 'https://yourdomain.com');
```

### Option 3: Update environment.php
```php
$defaults = [
    'production' => 'https://yourdomain.com',  // Change this
];
```

## 📋 Next Steps

### Immediate Actions

1. **For New Code**: Always use helper functions
   ```php
   // ✅ Good
   redirect(module_url('dashboard.php'));
   echo '<a href="' . api_url('export.php') . '">Export</a>';
   
   // ❌ Bad
   redirect('http://localhost:8080/abbis3.2/modules/dashboard.php');
   ```

2. **For Existing Code**: Gradually migrate
   - Run `php scripts/find-hardcoded-urls.php` to find hardcoded URLs
   - Replace systematically (we can do this together)
   - Test after each replacement

### Migration Strategy

**Phase 1: Critical URLs** (Do First)
- Login/logout redirects
- API endpoints
- Form actions
- File uploads/downloads

**Phase 2: Navigation URLs** (Do Second)
- Menu links
- Module navigation
- Breadcrumbs

**Phase 3: Content URLs** (Do Third)
- Email links
- Documentation links
- Help links

## ✅ Current Status

- [x] URL Manager system created
- [x] Helper functions implemented
- [x] JavaScript helper created
- [x] Deployment configuration system
- [x] Auto-detection system
- [x] Documentation created
- [x] Integrated into config/app.php
- [x] JavaScript helper in header
- [ ] Replace existing hardcoded URLs (ongoing)

## 🧪 Testing

Test the URL system:

```bash
# Test URL generation
php -r "require 'config/app.php'; echo site_url('modules/dashboard.php');"

# Find hardcoded URLs
php scripts/find-hardcoded-urls.php

# Test links
php scripts/test-links.php
```

## 📖 Usage Examples

### PHP Example
```php
<?php
require_once 'config/app.php';

// Generate URLs
$dashboardUrl = module_url('dashboard.php');
$exportUrl = api_url('export.php', ['format' => 'csv']);
$cssUrl = asset_url('css/style.css');

// Use in HTML
echo '<a href="' . $dashboardUrl . '">Dashboard</a>';
echo '<link rel="stylesheet" href="' . $cssUrl . '">';

// Redirects
redirect(module_url('dashboard.php'));
header('Location: ' . redirect_url('modules/dashboard.php'));
```

### JavaScript Example
```javascript
// API call
fetch(ABBIS_URLS.api('export.php', {format: 'csv'}))
    .then(response => response.json())
    .then(data => console.log(data));

// Navigation
window.location.href = ABBIS_URLS.module('dashboard.php');

// Asset loading
const img = new Image();
img.src = ABBIS_URLS.asset('images/logo.png');
```

## 🎉 Benefits

1. ✅ **Single Source of Truth** - Change one value, all URLs update
2. ✅ **Easy Deployment** - No search/replace needed
3. ✅ **Environment Aware** - Auto-detects production/staging/dev
4. ✅ **Type Safe** - Helper functions prevent errors
5. ✅ **Maintainable** - Centralized URL logic
6. ✅ **Future Proof** - Easy to add new URL types

## 📞 Support

- **Deployment Guide**: `docs/DEPLOYMENT_URL_GUIDE.md`
- **Technical Details**: `docs/URL_SYSTEM_IMPLEMENTATION.md`
- **Find Hardcoded URLs**: `php scripts/find-hardcoded-urls.php`

---

**Status**: ✅ System Ready for Deployment  
**Next**: Replace hardcoded URLs as needed (can be done incrementally)

