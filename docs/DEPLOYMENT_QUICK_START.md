# Quick Deployment Checklist - cPanel/Hostinger

## After Uploading Files and Importing SQL

### ✅ Step 1: Create Deployment Config (2 minutes)
1. In File Manager, go to `config/` folder
2. Copy `deployment.php.example` → rename to `deployment.php`
3. Edit `deployment.php` and update:
   ```php
   define('APP_URL', 'https://yourdomain.com/abbis3.2');
   ```
   **Replace with your actual domain!**

### ✅ Step 2: Set Permissions (1 minute)
1. Right-click `uploads/` folder
2. **Change Permissions** → Set to **755**
3. Check **Recurse into subdirectories**
4. Click **Change Permissions**

### ✅ Step 3: Test (1 minute)
1. Open browser: `https://yourdomain.com/abbis3.2`
2. Try to log in
3. If you see login page → ✅ Success!

---

## Common Issues

### ❌ "404 Not Found"
- Check `.htaccess` file exists
- Verify `RewriteBase` in `.htaccess` matches your path
- Enable `mod_rewrite` in cPanel

### ❌ "Database Connection Failed"
- Check database credentials in `config/environment.php`
- Verify database user has permissions
- Check database name is correct

### ❌ "Permission Denied" on uploads
- Set `uploads/` folder to **755** or **777**
- Check parent directory permissions

### ❌ URLs are broken
- **Most Important:** Update `APP_URL` in `config/deployment.php`
- Clear browser cache
- Verify `deployment.php` exists

---

## Full Guide
See `docs/DEPLOYMENT_STEPS.md` for complete instructions.

