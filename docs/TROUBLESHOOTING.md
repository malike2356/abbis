# Troubleshooting Guide

## White/Blank Page Issues

If you see a blank page:

1. **Check PHP Error Logs:**
   ```bash
   tail -f /opt/lampp/logs/php_error_log
   ```

2. **Restart Apache to clear cache:**
   ```bash
   sudo /opt/lampp/lampp restartapache
   ```

3. **Enable Error Display** (temporarily):
   - The config is already set to show errors in development mode
   - Check `config/app.php` - `APP_ENV` should be 'development'

4. **Check Browser Console:**
   - Open browser Developer Tools (F12)
   - Check Console tab for JavaScript errors
   - Check Network tab for failed requests

## Common Issues Fixed

### ✅ Fixed: json_decode() Error in config-manager.php
- **Issue:** `json_decode()` was receiving an array instead of string
- **Fix:** Added proper type checking and extraction of `config_value` from database row
- **Status:** Fixed in `includes/config-manager.php`

### ✅ Fixed: Constant Redefinition Warnings
- **Issue:** Constants defined in both `config/app.php` and `config/database.php`
- **Fix:** Added `defined()` checks before defining constants
- **Status:** Fixed in both config files

## If Blank Page Persists

1. **Clear browser cache and cookies**
2. **Try incognito/private browsing mode**
3. **Check if you're logged in:**
   - Access should redirect to login if not authenticated
   - Make sure you're accessing while logged in
4. **Check file permissions:**
   ```bash
   ls -la /opt/lampp/htdocs/abbis3.2/modules/field-reports.php
   ```

