# ABBIS Update System - Complete Guide

## Overview

This guide explains the simple update system for ABBIS that allows you to update your production server from your local computer with minimal technical knowledge.

---

## 🎯 **The Update Process (3 Simple Steps)**

### **Step 1: Create Update Package** (Local Computer)

**Method A: Using Command Line**
```bash
cd /path/to/abbis3.2
php scripts/deploy/create-package.php
```

**Method B: Using Quick Script** (Linux/Mac)
```bash
cd /path/to/abbis3.2
./scripts/deploy/quick-update.sh
```

**What You Get:**
- A ZIP file in `deployment-packages/` folder
- Name format: `abbis-update-YYYY-MM-DD-HHMMSS.zip`
- Includes all updated files
- Excludes unnecessary files (logs, uploads, config)

---

### **Step 2: Upload to Server**

**Via cPanel File Manager:**
1. Log into cPanel
2. Open **File Manager**
3. Navigate to your ABBIS directory (`public_html/abbis3.2` or similar)
4. Click **Upload**
5. Select your ZIP file
6. Wait for upload to complete

**Via FTP:**
1. Connect to your server via FTP
2. Navigate to ABBIS directory
3. Upload the ZIP file

**Recommended Location:**
- Upload to: `deployment-packages/` folder (create if doesn't exist)
- OR upload to root ABBIS directory

---

### **Step 3: Run Update Script** (Server)

**Method A: Using cPanel Terminal**
```bash
cd public_html/abbis3.2
php scripts/deploy/update-server.php
```

**Method B: Using Browser** (If terminal not available)
1. Make sure ZIP is extracted
2. Visit: `https://your-domain.com/abbis3.2/scripts/deploy/update-server.php?run=1&confirm=yes`
3. Click the confirmation button
4. Wait for completion

**What Happens:**
1. ✅ Creates backup of current files
2. ✅ Extracts new files from ZIP
3. ✅ Preserves your configuration
4. ✅ Sets proper permissions
5. ✅ Verifies installation

---

## 📦 **What's Included in the Package**

### **Included:**
- All PHP files (api, modules, includes, etc.)
- All assets (CSS, JS, images)
- All system files (client-portal, cms, pos)
- Documentation
- Database migration files
- Update scripts

### **Excluded (Preserved on Server):**
- `config/deployment.php` - Your production config
- `config/secrets/` - Your API keys
- `uploads/` - User uploaded files
- `storage/` - Application storage
- `logs/` - Log files
- `.git/` - Version control

---

## 🔒 **Safety Features**

The update system includes multiple safety features:

1. **Automatic Backup**
   - Backs up critical files before updating
   - Stored in `backups/` folder
   - Timestamped for easy identification

2. **Configuration Preservation**
   - Never overwrites `config/deployment.php`
   - Never overwrites `config/secrets/`
   - Preserves your production settings

3. **File Preservation**
   - Never overwrites `uploads/` directory
   - Never overwrites user data
   - Preserves all user-generated content

4. **Verification**
   - Checks critical files after update
   - Reports any missing files
   - Verifies installation integrity

---

## 🚀 **Quick Start for Non-Technical Users**

### **On Your Computer:**

1. **Create Package:**
   - Open terminal/command prompt
   - Navigate to ABBIS folder
   - Run: `php scripts/deploy/create-package.php`
   - Find ZIP file in `deployment-packages/` folder

2. **Upload Package:**
   - Log into cPanel
   - Go to File Manager
   - Upload the ZIP file

3. **Update Server:**
   - In cPanel, go to Terminal
   - Run: `php scripts/deploy/update-server.php`
   - Done!

---

## 📋 **Pre-Update Checklist**

Before updating your production server:

- [ ] **Test Locally First**
  - Make sure all changes work on your local computer
  - Test all major features
  - Fix any issues before deploying

- [ ] **Backup Database**
  - Export database via phpMyAdmin
  - Save backup file safely
  - Note the backup date/time

- [ ] **Review Changes**
  - Check what files were changed
  - Review `docs/CHANGES_IMPACT_ANALYSIS.md`
  - Understand what's new

- [ ] **Choose Update Time**
  - Pick low-traffic period
  - Notify users if needed
  - Have time to fix issues if any

---

## 📋 **Post-Update Checklist**

After updating:

- [ ] **Clear Browser Cache**
  - Hard refresh (Ctrl+F5 or Cmd+Shift+R)
  - Or clear browser cache completely

- [ ] **Test Login**
  - Log in as admin
  - Verify dashboard loads
  - Check navigation works

- [ ] **Test Key Features**
  - Create a test field report
  - Check client portal
  - Verify payments work
  - Test CMS if enabled

- [ ] **Check Error Logs**
  - Look in `logs/` directory
  - Check for any PHP errors
  - Verify no critical issues

- [ ] **Monitor Performance**
  - Check page load times
  - Monitor for any slowdowns
  - Watch for error messages

---

## 🆘 **Troubleshooting**

### **Issue: "No update package found"**

**Solution:**
- Verify ZIP file is uploaded
- Check file is in correct location
- Ensure file name ends with `.zip`
- Try uploading to root directory instead

### **Issue: "Permission denied"**

**Solution:**
- Check file permissions via cPanel
- Contact hosting provider
- Or set permissions manually:
  ```bash
  chmod 755 scripts/deploy/update-server.php
  ```

### **Issue: "Update failed"**

**Solution:**
1. Check error message for details
2. Look in `backups/` folder for previous version
3. Restore from backup if needed:
   ```bash
   cp -r backups/backup-YYYY-MM-DD-HHMMSS/* ./
   ```
4. Contact support with error details

### **Issue: "Site not working after update"**

**Solution:**
1. Clear browser cache
2. Check `logs/` for error messages
3. Verify database connection in `config/deployment.php`
4. Check file permissions
5. Restore from backup if needed

---

## 💡 **Best Practices**

1. **Always Test Locally First**
   - Never deploy untested code
   - Test all features before updating production

2. **Keep Backups**
   - The script creates backups automatically
   - But keep your own backups too
   - Store backups safely

3. **Update Regularly**
   - Don't let updates accumulate
   - Smaller updates are easier to manage
   - Easier to troubleshoot if issues arise

4. **Document Changes**
   - Note what changed in each update
   - Keep changelog
   - Helps with troubleshooting

5. **Monitor After Update**
   - Watch for errors
   - Check user feedback
   - Monitor performance

---

## 🔄 **Alternative: Using Git** (For Advanced Users)

If you're comfortable with Git, you can also update via:

```bash
# On server
cd public_html/abbis3.2
git pull origin main
```

**Advantages:**
- Faster updates
- Version control
- Easy rollback

**Disadvantages:**
- Requires Git knowledge
- Need to handle config files manually
- More technical

---

## 📞 **Support**

If you need help:
1. Check error messages
2. Review this guide
3. Check `docs/` folder for more documentation
4. Contact your developer or hosting support

---

**Last Updated:** November 2025
**Version:** 1.0

