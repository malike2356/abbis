# ABBIS Deployment Guide - cPanel/Hostinger

This guide walks you through deploying ABBIS to cPanel or Hostinger after uploading your files and importing the database.

## Prerequisites

✅ Project files zipped  
✅ SQL database exported from phpMyAdmin  
✅ cPanel/Hostinger account access  
✅ Domain name configured

---

## Step 1: Upload and Extract Files

### 1.1 Upload ZIP File

1. Log into **cPanel** or **Hostinger hPanel**
2. Navigate to **File Manager**
3. Go to your domain's **public_html** directory (or subdirectory if using subdomain)
4. Click **Upload** and select your project ZIP file
5. Wait for upload to complete

### 1.2 Extract Files

1. Right-click the uploaded ZIP file
2. Select **Extract** or **Extract All**
3. Wait for extraction to complete
4. **Delete the ZIP file** after extraction (for security)

### 1.3 Verify File Structure

Your directory should look like:

```
public_html/
├── abbis3.2/          (or your project folder name)
│   ├── api/
│   ├── modules/
│   ├── config/
│   ├── includes/
│   ├── client-portal/
│   ├── cms/
│   ├── pos/
│   └── ...
```

---

## Step 2: Import Database

### 2.1 Access phpMyAdmin

1. In cPanel, find **phpMyAdmin** under **Databases**
2. Click to open phpMyAdmin

### 2.2 Create Database (if not exists)

1. Click **Databases** tab
2. Create a new database (e.g., `yourdomain_abbis`)
3. Note the database name

### 2.3 Create Database User (if not exists)

1. Go to **User Accounts** tab
2. Click **Add user account**
3. Create username and strong password
4. Grant **ALL PRIVILEGES** to the database
5. Note the username and password

### 2.4 Import SQL File

1. Select your database from the left sidebar
2. Click **Import** tab
3. Click **Choose File** and select your exported SQL file
4. Click **Go** to import
5. Wait for "Import has been successfully finished" message

---

## Step 3: Configure Application

### 3.1 Create Configuration File

**Option A: Copy from example (Recommended)**

```bash
# In File Manager, navigate to config/
# Copy deployment.php.example to deployment.php
```

**Option B: Create manually**

1. In File Manager, go to `config/` directory
2. Create new file: `deployment.php`
3. Add the following content:

```php
<?php
/**
 * Deployment Configuration
 * Update these values for your production environment
 */

// Production URL - CRITICAL: Update this!
// Examples:
// - Root domain: 'https://yourdomain.com'
// - Subdirectory: 'https://yourdomain.com/abbis3.2'
// - Subdomain: 'https://abbis.yourdomain.com'
define('APP_URL', 'https://yourdomain.com/abbis3.2');

// Environment (development, staging, production)
define('APP_ENV', 'production');

// Debug mode (should be false in production)
define('DEBUG', false);
```

**Note:** Database credentials are typically configured in `config/environment.php` or through cPanel's database interface. If you need to override database settings, add them to `deployment.php`.

### 3.2 Update Database Credentials (if needed)

**Note:** Database credentials are usually configured in `config/environment.php` or through cPanel's database interface.

If you need to override database settings in `deployment.php`, add:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourdomain_abbis');
define('DB_USER', 'yourdomain_dbuser');
define('DB_PASS', 'your_strong_password');
```

**Most hosting providers:** Database credentials are automatically detected from cPanel, so you may not need to set these manually.

### 3.3 Update APP_URL (CRITICAL!)

**This is the most important step!**

Update `APP_URL` in `config/deployment.php`:

- If deployed to root: `https://yourdomain.com`
- If deployed to subdirectory: `https://yourdomain.com/abbis3.2`
- If using subdomain: `https://abbis.yourdomain.com`

**Example:**

```php
define('APP_URL', 'https://yourdomain.com/abbis3.2');
```

### 3.4 Generate Encryption Key

Generate a secure encryption key:

```bash
# Option 1: Use online generator
# Visit: https://randomkeygen.com/ (use "CodeIgniter Encryption Keys")

# Option 2: Use PHP
php -r "echo bin2hex(random_bytes(32));"
```

Update `ENCRYPTION_KEY` in `config/deployment.php`

---

## Step 4: Set File Permissions

### 4.1 Set Directory Permissions

In File Manager, set these permissions:

**Directories (755):**

- `uploads/` → 755
- `uploads/profiles/` → 755
- `uploads/payslips/` → 755
- `uploads/receipts/` → 755
- `cms/uploads/` → 755
- `logs/` (if exists) → 755

**Files (644):**

- All PHP files → 644
- All CSS/JS files → 644

### 4.2 Make Uploads Writable

1. Right-click `uploads/` folder
2. Select **Change Permissions**
3. Set to **755** (or **777** if 755 doesn't work)
4. Check **Recurse into subdirectories**
5. Click **Change Permissions**

---

## Step 5: Update .htaccess (if needed)

### 5.1 Check .htaccess File

1. Navigate to your project root in File Manager
2. Verify `.htaccess` file exists
3. If missing, create it with:

```apache
RewriteEngine On
RewriteBase /abbis3.2/

# Redirect to index if file doesn't exist
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Important:** Update `RewriteBase` to match your installation path:

- Root: `/`
- Subdirectory: `/abbis3.2/` (or your folder name)

### 5.2 Enable mod_rewrite

If you get 500 errors:

1. Contact Hostinger support to enable `mod_rewrite`
2. Or check cPanel → **Apache Modules** → Enable `mod_rewrite`

---

## Step 6: Test Installation

### 6.1 Access Application

1. Open browser
2. Navigate to: `https://yourdomain.com/abbis3.2`
3. You should see the login page

### 6.2 Test Login

1. Use your admin credentials
2. Verify you can log in successfully

### 6.3 Test Key Features

- ✅ Dashboard loads
- ✅ Modules accessible
- ✅ File uploads work (profile photo, etc.)
- ✅ Client Portal accessible
- ✅ CMS accessible

### 6.4 Check for Errors

1. Check browser console (F12 → Console)
2. Check for PHP errors in:
   - `logs/` directory (if exists)
   - cPanel → **Errors** section
   - phpMyAdmin → Check database connection

---

## Step 7: Common Issues & Fixes

### Issue 1: "Database Connection Failed"

**Solution:**

- Verify database credentials in `config/deployment.php`
- Check database user has proper permissions
- Ensure database exists

### Issue 2: "404 Not Found" or "Page Not Found"

**Solution:**

- Check `.htaccess` file exists
- Verify `RewriteBase` matches your path
- Enable `mod_rewrite` in cPanel

### Issue 3: "Permission Denied" on Uploads

**Solution:**

- Set `uploads/` directory to **755** or **777**
- Check parent directory permissions
- Verify web server user can write to directory

### Issue 4: "APP_URL incorrect" or URLs broken

**Solution:**

- Update `APP_URL` in `config/deployment.php`
- Clear browser cache
- Check `config/app.php` loads `deployment.php`

### Issue 5: "White Screen" or "500 Error"

**Solution:**

- Check PHP error logs in cPanel
- Verify PHP version (requires PHP 7.4+)
- Check file permissions
- Verify `.htaccess` syntax

### Issue 6: "Session errors" or "Can't login"

**Solution:**

- Check `session.save_path` in PHP settings
- Verify cookies are enabled
- Check SSL certificate (if using HTTPS)

---

## Step 8: Security Checklist

### 8.1 Remove Development Files

Delete or protect:

- ❌ `config/deployment.php.example` (or rename)
- ❌ `scripts/` directory (or move outside public_html)
- ❌ `docs/` directory (or move outside public_html)
- ❌ `.git/` directory (if exists)

### 8.2 Secure Configuration

- ✅ `config/deployment.php` should have **600** permissions (if possible)
- ✅ Never commit `deployment.php` to version control
- ✅ Use strong database passwords

### 8.3 Enable HTTPS

1. Install SSL certificate (Let's Encrypt is free)
2. Force HTTPS in `.htaccess`:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 8.4 Update Admin Password

1. Log into application
2. Go to **Profile** → **Change Password**
3. Set a strong password

---

## Step 9: Post-Deployment Tasks

### 9.1 Update Email Configuration

1. Go to **Modules** → **Config** → **Email Settings**
2. Update SMTP settings for production
3. Test email sending

### 9.2 Configure Payment Gateways

1. Go to **CMS** → **Admin** → **Payment Methods**
2. Update payment gateway credentials
3. Test payment processing

### 9.3 Set Up Backups

1. Configure automatic database backups in cPanel
2. Set up file backups
3. Test restore process

### 9.4 Monitor Performance

1. Check cPanel → **Metrics** for resource usage
2. Monitor error logs
3. Set up uptime monitoring

---

## Quick Reference

### File Locations

- **Config:** `config/deployment.php`
- **Database:** cPanel → phpMyAdmin
- **Logs:** `logs/` directory (if exists)
- **Uploads:** `uploads/` directory

### Important URLs

- **Application:** `https://yourdomain.com/abbis3.2`
- **Client Portal:** `https://yourdomain.com/abbis3.2/client-portal/login.php`
- **CMS Admin:** `https://yourdomain.com/abbis3.2/cms/admin/`

### Support Resources

- **Hostinger Support:** https://www.hostinger.com/contact
- **cPanel Docs:** https://docs.cpanel.net/
- **PHP Version:** Check in cPanel → **Select PHP Version**

---

## Deployment Checklist

Use this checklist to ensure nothing is missed:

- [ ] Files uploaded and extracted
- [ ] ZIP file deleted
- [ ] Database created
- [ ] Database user created with privileges
- [ ] SQL file imported successfully
- [ ] `config/deployment.php` created
- [ ] Database credentials updated
- [ ] `APP_URL` updated correctly
- [ ] Encryption key generated and set
- [ ] File permissions set (755 for dirs, 644 for files)
- [ ] Uploads directory writable (755 or 777)
- [ ] `.htaccess` file exists and configured
- [ ] `mod_rewrite` enabled
- [ ] Application accessible in browser
- [ ] Login works
- [ ] File uploads work
- [ ] No PHP errors in logs
- [ ] HTTPS configured (if available)
- [ ] Development files removed
- [ ] Email configuration updated
- [ ] Payment gateways configured
- [ ] Backups configured

---

## Need Help?

If you encounter issues:

1. Check **Step 7: Common Issues & Fixes**
2. Review error logs in cPanel
3. Contact Hostinger support
4. Check browser console for JavaScript errors

---

**Last Updated:** $(date)  
**Version:** 3.2
