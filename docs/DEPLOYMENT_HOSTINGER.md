# ABBIS Deployment Guide for Hostinger

## Your Hostinger Setup

Based on your Hostinger account:
- **Website:** kariboreholes.com
- **Database:** u411287710_abbis32
- **Database User:** u411287710_abbisuser
- **Database Size:** 19 MB

---

## 📋 Pre-Deployment Checklist

### 1. **Backup Your Database**
- [ ] Go to **Databases → Management → phpMyAdmin**
- [ ] Select database: `u411287710_abbis32`
- [ ] Click **Export** tab
- [ ] Choose **Quick** export method
- [ ] Click **Go** to download SQL backup
- [ ] Save backup file safely

### 2. **Prepare Deployment Package**
- [ ] You have: `abbis-update-2025-11-14-165044.zip` (2.4 MB)
- [ ] Package is ready on your local computer

---

## 🚀 Deployment Steps

### **Step 1: Upload ZIP File to Hostinger**

1. **Access File Manager:**
   - In Hostinger, go to **Websites → kariboreholes.com → Files**
   - Click **File Manager**

2. **Navigate to Your ABBIS Directory:**
   - Usually: `public_html/abbis3.2` or `public_html/`
   - Or wherever your ABBIS is installed

3. **Upload ZIP File:**
   - Click **Upload** button
   - Select `abbis-update-2025-11-14-165044.zip`
   - Wait for upload to complete (2.4 MB)

4. **Create deployment-packages folder (if needed):**
   - Right-click in File Manager
   - Select **New Folder**
   - Name it: `deployment-packages`
   - Move ZIP file into this folder (optional, but recommended)

---

### **Step 2: Extract ZIP File**

**Option A: Using File Manager**
1. Right-click on `abbis-update-2025-11-14-165044.zip`
2. Select **Extract** or **Extract All**
3. Choose extraction location (your ABBIS root directory)
4. Wait for extraction to complete

**Option B: Using Terminal (if available)**
1. Go to **Advanced → Terminal** in Hostinger
2. Navigate to your ABBIS directory:
   ```bash
   cd public_html/abbis3.2
   ```
3. Extract ZIP:
   ```bash
   unzip deployment-packages/abbis-update-2025-11-14-165044.zip
   ```

---

### **Step 3: Run Update Script**

**Option A: Using Browser**
1. Visit: `https://kariboreholes.com/abbis3.2/scripts/deploy/update-server.php?run=1&confirm=yes`
2. Click the confirmation button
3. Wait for update to complete
4. You'll see success message

**Option B: Using Terminal**
1. In Hostinger Terminal:
   ```bash
   cd public_html/abbis3.2
   php scripts/deploy/update-server.php
   ```
2. Wait for completion

---

### **Step 4: Configure Database (If First Time)**

If this is a fresh deployment, update `config/deployment.php`:

```php
<?php
return [
    'db_host' => 'localhost',
    'db_name' => 'u411287710_abbis32',
    'db_user' => 'u411287710_abbisuser',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'db_charset' => 'utf8mb4',
];
```

**Note:** The update script preserves your existing `config/deployment.php`, so you won't lose your database credentials.

---

### **Step 5: Set File Permissions**

In Hostinger File Manager or Terminal:

```bash
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 storage/
chmod 644 config/*.php
```

Or via File Manager:
- Right-click folder → **Change Permissions**
- Set `uploads/` to `755`
- Set `storage/` to `755`

---

### **Step 6: Verify Installation**

1. **Clear Browser Cache:**
   - Hard refresh: `Ctrl+F5` (Windows) or `Cmd+Shift+R` (Mac)

2. **Test Login:**
   - Visit: `https://kariboreholes.com/abbis3.2/login.php`
   - Log in with your admin credentials

3. **Test Key Features:**
   - Dashboard loads correctly
   - Client portal works
   - CMS works (if enabled)
   - No error messages

---

## 🔧 Hostinger-Specific Notes

### **PHP Version**
- Hostinger usually runs PHP 8.0+ (compatible with ABBIS)
- If issues, check PHP version in **Advanced → PHP Configuration**

### **File Upload Limits**
- Hostinger default: Usually 64MB+ (sufficient for 2.4MB package)
- If upload fails, check upload limits in PHP settings

### **Database Connection**
- Host: `localhost` (standard for Hostinger)
- Database: `u411287710_abbis32`
- User: `u411287710_abbisuser`
- Port: Usually 3306 (default)

### **SSL Certificate**
- Hostinger provides free SSL
- Make sure HTTPS is enabled
- Update `APP_URL` in `config/app.php` to use `https://`

---

## 🆘 Troubleshooting

### **"Permission Denied" Error**
- Check file permissions via File Manager
- Set folders to `755`, files to `644`
- Contact Hostinger support if needed

### **"Database Connection Failed"**
- Verify database credentials in `config/deployment.php`
- Check database user has proper permissions
- Verify database exists in Hostinger panel

### **"Update Script Not Found"**
- Verify ZIP was extracted correctly
- Check `scripts/deploy/update-server.php` exists
- Verify file paths are correct

### **"Site Not Loading After Update"**
- Clear browser cache
- Check error logs in `logs/` directory
- Verify `.htaccess` file exists
- Check PHP error logs in Hostinger

---

## 📞 Hostinger Support

If you encounter issues:
1. Check Hostinger knowledge base
2. Contact Hostinger support via live chat
3. Check error logs in Hostinger panel

---

## ✅ Post-Deployment Checklist

- [ ] Site loads correctly
- [ ] Login works
- [ ] Dashboard displays
- [ ] Client portal accessible
- [ ] CMS works (if enabled)
- [ ] No PHP errors in logs
- [ ] Database connection working
- [ ] File uploads working

---

## 🎯 Quick Reference

**Your Hostinger Details:**
- Website: kariboreholes.com
- Database: u411287710_abbis32
- User: u411287710_abbisuser
- Package: abbis-update-2025-11-14-165044.zip

**Deployment URL (if using browser method):**
```
https://kariboreholes.com/abbis3.2/scripts/deploy/update-server.php?run=1&confirm=yes
```

---

**Last Updated:** November 2025
**Status:** Ready for Hostinger Deployment ✅

