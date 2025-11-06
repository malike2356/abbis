# ABBIS v3.2 - Quick Start Guide

## 🚀 Quick Setup for XAMPP/LAMPP

### Option 1: Automated Setup (Recommended)

```bash
cd /opt/lampp/htdocs/abbis3.2
chmod +x setup-xampp.sh
./setup-xampp.sh
```

Then open: **http://localhost:8080/abbis3.2/login.php**

**Note:** If your XAMPP Apache is configured to use port 8080 (instead of 80), use `:8080` in the URL.

### Start XAMPP Services First!

**If you get "Connection Refused" error:**

```bash
# Start XAMPP services
sudo /opt/lampp/lampp start

# Or use the quick start script
./start-xampp.sh
```

Then try accessing: **http://localhost:8080/abbis3.2/login.php** (or `http://localhost/abbis3.2/login.php` if Apache uses port 80)

### Option 2: Manual Setup

1. **Start XAMPP:**
   ```bash
   sudo /opt/lampp/lampp start
   ```

2. **Create database:**
   ```bash
   /opt/lampp/bin/mysql -u root -e "CREATE DATABASE abbis_3_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

3. **Import schema:**
   ```bash
   /opt/lampp/bin/mysql -u root abbis_3_2 < database/schema.sql
   ```

4. **Access:** http://localhost/abbis3.2/login.php

## 🔐 Default Login

- **Username:** `admin`
- **Password:** `password`

⚠️ **Change this password immediately after first login!**

## 📁 Important Files

- `config/database.php` - Database configuration
- `DEPLOYMENT.md` - Full deployment guide
- `setup-xampp.sh` - Automated setup script
- `.htaccess` - Apache configuration

## 🔧 Common Issues

### Database Connection Failed
- Check MySQL is running: `sudo /opt/lampp/lampp statusmysql`
- Verify credentials in `config/database.php`
- Ensure database exists

### Permission Denied
```bash
chmod 755 uploads/ logs/
sudo chown daemon:daemon uploads/ logs/  # For XAMPP
```

### 404 Errors
- Check `.htaccess` exists
- Verify `mod_rewrite` is enabled
- Check Apache error logs

## 📚 Full Documentation

See `DEPLOYMENT.md` for:
- Complete setup instructions
- cPanel deployment guide
- Troubleshooting
- Security recommendations

---

**Need Help?** Check `DEPLOYMENT.md` for detailed troubleshooting.

