# ABBIS v3.2 - Deployment Guide

This guide covers deploying ABBIS on both **local XAMPP/LAMPP** and **cPanel shared hosting**.

---

## 📋 Table of Contents

1. [Local XAMPP/LAMPP Deployment](#local-xampplampp-deployment)
2. [cPanel Shared Hosting Deployment](#cpanel-shared-hosting-deployment)
3. [Post-Deployment Configuration](#post-deployment-configuration)
4. [Troubleshooting](#troubleshooting)

---

## 🖥️ Local XAMPP/LAMPP Deployment

### Prerequisites

- **XAMPP** or **LAMPP** installed (Linux/Mac) or **XAMPP** (Windows)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser

### Quick Setup (Automated)

1. **Make the setup script executable:**
   ```bash
   chmod +x setup-xampp.sh
   ```

2. **Run the setup script:**
   ```bash
   ./setup-xampp.sh
   ```

   The script will:
   - Check XAMPP installation
   - Start MySQL and Apache if needed
   - Create the database
   - Import the schema
   - Set up directories and permissions
   - Verify configuration

3. **Access the system:**
   - Open your browser and go to: `http://localhost:8080/abbis3.2/login.php`
     - **Note:** If Apache uses port 80, remove `:8080` from the URL
   - Use default credentials:
     - Username: `admin`
     - Password: `password`

### Manual Setup (Step by Step)

If you prefer manual setup or the script doesn't work:

#### Step 1: Start XAMPP Services

**Linux/Mac:**
```bash
sudo /opt/lampp/lampp start
```

**Windows:**
- Open XAMPP Control Panel
- Start Apache and MySQL

#### Step 2: Create Database

**Option A: Using phpMyAdmin**
1. Open: `http://localhost/phpmyadmin`
2. Click "New" to create a database
3. Name it: `abbis_3_2`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

**Option B: Using MySQL Command Line**
```bash
# Linux/Mac
/opt/lampp/bin/mysql -u root -p -e "CREATE DATABASE abbis_3_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Windows (if MySQL is in PATH)
mysql -u root -p -e "CREATE DATABASE abbis_3_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### Step 3: Import Database Schema

**Option A: Using phpMyAdmin**
1. Select the `abbis_3_2` database
2. Click "Import" tab
3. Choose file: `database/schema.sql`
4. Click "Go"

**Option B: Using MySQL Command Line**
```bash
# Linux/Mac
/opt/lampp/bin/mysql -u root -p abbis_3_2 < database/schema.sql

# Windows
mysql -u root -p abbis_3_2 < database/schema.sql
```

#### Step 4: Verify Configuration

Check `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Empty for XAMPP default
define('DB_NAME', 'abbis_3_2');
define('APP_URL', 'http://localhost/abbis3.2');
```

#### Step 5: Set Permissions

**Linux/Mac:**
```bash
chmod 755 uploads/
chmod 755 logs/
chmod 644 config/*.php
```

**Windows:** Permissions are usually set automatically.

#### Step 6: Access the System

Open: `http://localhost/abbis3.2/login.php`

---

## 🌐 cPanel Shared Hosting Deployment

### Prerequisites

- cPanel account with PHP 7.4+ and MySQL
- FTP/SFTP access or File Manager
- Database access

### Deployment Steps

#### Step 1: Prepare Files

1. **Upload all files** to your public_html directory (or subdirectory):
   ```
   public_html/abbis3.2/
   ├── api/
   ├── assets/
   ├── config/
   ├── database/
   ├── includes/
   ├── modules/
   ├── uploads/
   └── ... (all other files)
   ```

2. **Exclude sensitive files** (if any):
   - `.git/`
   - `setup-xampp.sh`
   - Development notes

#### Step 2: Create Database in cPanel

1. Login to cPanel
2. Go to **MySQL Databases**
3. Create a new database: `yourprefix_abbis32` (note the full name)
4. Create a new MySQL user (or use existing)
5. Add user to database with **ALL PRIVILEGES**
6. Note down:
   - Database name (usually `yourprefix_abbis32`)
   - Database user (usually `yourprefix_dbuser`)
   - Database password
   - Database host (usually `localhost`)

#### Step 3: Update Configuration

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');  // Or your cPanel host
define('DB_USER', 'yourprefix_dbuser');  // Your cPanel database user
define('DB_PASS', 'your_secure_password');  // Your database password
define('DB_NAME', 'yourprefix_abbis32');  // Your full database name
define('APP_URL', 'https://yourdomain.com/abbis3.2');  // Your actual URL
```

**Important:** Use the **full database name** and **full username** as shown in cPanel.

#### Step 4: Import Database Schema

**Option A: Using phpMyAdmin in cPanel**
1. Open phpMyAdmin from cPanel
2. Select your database (`yourprefix_abbis32`)
3. Click "Import" tab
4. Choose `database/schema.sql`
5. Click "Go"

**Option B: Using cPanel MySQL Database Wizard**
- Use the SQL tab in phpMyAdmin to paste and execute the SQL from `schema.sql`

#### Step 5: Set Directory Permissions

Using cPanel File Manager or FTP:
```
uploads/     → 755 (or 775)
logs/        → 755 (or 775)
config/      → 755
config/*.php → 644
```

#### Step 6: Configure .htaccess (if needed)

Your `.htaccess` file should work, but you may need to adjust:

1. **If you get 500 errors**, comment out these lines:
   ```apache
   # php_flag display_errors On
   # php_value error_reporting E_ALL
   ```

2. **If mod_rewrite doesn't work**, contact your hosting provider

3. **For subdirectory installation**, ensure paths are relative

#### Step 7: Test and Verify

1. Access: `https://yourdomain.com/abbis3.2/login.php`
2. Login with default credentials:
   - Username: `admin`
   - Password: `password`
3. **Change the password immediately!**

---

## ⚙️ Post-Deployment Configuration

### 1. Change Default Password

**Critical Security Step!**

1. Login as admin
2. Go to **Configuration** module
3. Change admin password
4. Or use MySQL:
   ```sql
   UPDATE users 
   SET password_hash = '$2y$10$YOUR_NEW_HASH' 
   WHERE username = 'admin';
   ```

### 2. Configure System Settings

1. Login and go to **Configuration** module
2. Update:
   - Company information
   - Contact details
   - Rigs (add/edit your rigs)
   - Workers (add your workers)
   - Clients (add your clients)
   - Materials pricing

### 3. Set Up Email (Optional)

If you want email notifications:
1. Update `includes/email.php` with SMTP settings
2. Or configure via cPanel Email accounts

### 4. Enable SSL (cPanel)

For production:
1. Install SSL certificate via cPanel
2. Update `APP_URL` in `config/database.php` to use `https://`
3. Force HTTPS in `.htaccess` (uncomment if needed):
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

---

## 🔧 Troubleshooting

### Connection Refused (ERR_CONNECTION_REFUSED)

**Problem:** "This site can't be reached - localhost refused to connect"

**Solutions:**
1. **Apache is not running:**
   ```bash
   # Start XAMPP services
   sudo /opt/lampp/lampp start
   
   # Or use the helper script
   ./start-xampp.sh
   ```

2. **Apache is running but not listening on port 80:**
   ```bash
   # Restart Apache
   sudo /opt/lampp/lampp restartapache
   
   # Check if Apache is listening
   sudo netstat -tlnp | grep :80
   # Should show Apache listening on port 80
   ```

3. **Port 80 is in use by another service:**
   ```bash
   # Check what's using port 80
   sudo lsof -i :80
   # Or
   sudo netstat -tlnp | grep :80
   
   # Stop conflicting service or configure Apache to use different port
   ```

4. **Check Apache error logs:**
   ```bash
   tail -f /opt/lampp/logs/error_log
   ```

5. **Firewall blocking port 80:**
   ```bash
   # Check firewall status
   sudo ufw status
   # Allow HTTP if needed
   sudo ufw allow 80/tcp
   ```

### Database Connection Errors

**Problem:** "Database connection failed"

**Solutions:**
1. **Local XAMPP:**
   - Ensure MySQL is running: `sudo /opt/lampp/lampp statusmysql`
   - Start MySQL if needed: `sudo /opt/lampp/lampp startmysql`
   - Check credentials in `config/database.php`
   - Verify database exists

2. **cPanel:**
   - Use full database name (with prefix)
   - Verify database user has privileges
   - Check host (may not be `localhost` - ask hosting)

### Permission Denied Errors

**Problem:** Can't write to uploads/ or logs/

**Solutions:**
```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 logs/
chown -R www-data:www-data uploads/ logs/  # or daemon:daemon for XAMPP

# cPanel
# Use File Manager to set permissions to 755 or 775
```

### 404 Not Found Errors

**Problem:** Pages not loading

**Solutions:**
1. Check `.htaccess` is present and readable
2. Verify `mod_rewrite` is enabled (cPanel: ask hosting)
3. Check file paths are correct
4. For subdirectory: ensure all paths are relative

### Session/Login Issues

**Problem:** Can't login or session expires

**Solutions:**
1. Check `config/security.php` session settings
2. Verify `php.ini` session settings
3. Clear browser cookies
4. Check server timezone in `config/app.php`

### White Screen / PHP Errors

**Problem:** Blank page or errors

**Solutions:**
1. **Enable error display temporarily:**
   ```php
   // In config/app.php
   error_reporting(E_ALL);
   ini_set('display_errors', '1');
   ```

2. Check PHP error logs:
   - XAMPP: `/opt/lampp/logs/php_error_log`
   - cPanel: Check error logs in cPanel

3. Verify PHP version: `php -v` (need 7.4+)

### Assets Not Loading (CSS/JS)

**Problem:** Page loads but no styling

**Solutions:**
1. Check browser console for 404 errors
2. Verify `assets/` directory exists and is readable
3. Check file paths in HTML (should be relative)
4. Clear browser cache

---

## 📝 Deployment Checklist

### Local XAMPP Deployment
- [ ] XAMPP installed and running
- [ ] Database created
- [ ] Schema imported
- [ ] Configuration updated
- [ ] Permissions set
- [ ] Can access login page
- [ ] Can login with default credentials
- [ ] Changed default password

### cPanel Deployment
- [ ] Files uploaded to server
- [ ] Database created in cPanel
- [ ] Database user created and granted privileges
- [ ] Schema imported via phpMyAdmin
- [ ] Configuration updated with cPanel credentials
- [ ] Directory permissions set (755/775)
- [ ] SSL certificate installed (recommended)
- [ ] Can access login page via HTTPS
- [ ] Can login with default credentials
- [ ] Changed default password
- [ ] System settings configured
- [ ] Tested key functionality

---

## 🔒 Security Recommendations

1. **Change default password immediately**
2. **Use strong passwords** for database and admin accounts
3. **Enable HTTPS** in production
4. **Regular backups** of database and files
5. **Keep PHP updated** to latest stable version
6. **Restrict file permissions** (644 for files, 755 for directories)
7. **Review and restrict** `.htaccess` if needed
8. **Remove or protect** `setup-xampp.sh` in production

---

## 📞 Support

If you encounter issues:

1. Check the **Troubleshooting** section above
2. Review PHP error logs
3. Check database connection settings
4. Verify file permissions
5. Ensure all required PHP extensions are enabled

---

## 🚀 Post-Deployment Optimization

### For Production (cPanel)

1. **Disable error display:**
   ```php
   // config/app.php
   error_reporting(E_ALL);
   ini_set('display_errors', '0');
   ini_set('log_errors', '1');
   ```

2. **Enable caching** (if available):
   - Use PHP opcache
   - Consider Redis/Memcached for sessions

3. **Set up automated backups:**
   - Use cPanel Backup feature
   - Or cron jobs for database dumps

4. **Monitor performance:**
   - Check cPanel resource usage
   - Optimize database queries if needed

---

**Last Updated:** 2024
**Version:** 3.2

