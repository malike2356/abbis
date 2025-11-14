# Simple ABBIS Update Guide

## For Non-Technical Users

This guide shows you how to update your online ABBIS system with new changes from your computer.

---

## 🎯 **The Simple Way (3 Steps)**

### **Step 1: Create Update Package** (On Your Computer)

1. Open terminal/command prompt in your ABBIS folder
2. Run this command:
   ```bash
   php scripts/deploy/create-package.php
   ```
3. You'll get a ZIP file in the `deployment-packages` folder
4. That's it! The ZIP file is ready to upload

**What this does:**
- Packages all your updated files into one ZIP file
- Excludes unnecessary files (logs, uploads, config files)
- Creates a ready-to-upload package

---

### **Step 2: Upload to Server** (Via cPanel or FTP)

**Option A: Using cPanel File Manager**
1. Log into your cPanel
2. Go to **File Manager**
3. Navigate to your ABBIS folder (usually `public_html/abbis3.2`)
4. Click **Upload**
5. Select your ZIP file (`abbis-update-YYYY-MM-DD-HHMMSS.zip`)
6. Wait for upload to complete

**Option B: Using FTP**
1. Open your FTP client (FileZilla, etc.)
2. Connect to your server
3. Navigate to your ABBIS folder
4. Upload the ZIP file

---

### **Step 3: Run Update Script** (On Server)

**Option A: Using cPanel Terminal**
1. In cPanel, go to **Terminal** (or **SSH Access**)
2. Navigate to your ABBIS folder:
   ```bash
   cd public_html/abbis3.2
   ```
3. Run the update script:
   ```bash
   php scripts/deploy/update-server.php
   ```
4. Wait for it to complete
5. Done! ✅

**Option B: Using Browser** (If Terminal not available)
1. Upload the ZIP file to your server
2. Extract it (right-click → Extract)
3. Visit: `https://your-domain.com/abbis3.2/scripts/deploy/update-server.php?run=1&confirm=yes`
4. Wait for completion message
5. Done! ✅

**What this does:**
- Backs up your current files (safety first!)
- Extracts new files from the ZIP
- Preserves your configuration files
- Sets proper permissions
- Verifies everything is working

---

## 📋 **Complete Checklist**

### Before Updating:
- [ ] Create update package on your computer
- [ ] Test locally to make sure everything works
- [ ] Backup your database (via phpMyAdmin)
- [ ] Note down any custom configurations

### During Update:
- [ ] Upload ZIP file to server
- [ ] Run update script
- [ ] Wait for completion message

### After Updating:
- [ ] Clear browser cache
- [ ] Test login
- [ ] Test main features
- [ ] Check error logs if any issues

---

## 🔒 **Safety Features**

The update script automatically:
- ✅ **Backs up** your current files before updating
- ✅ **Preserves** your configuration files (won't overwrite)
- ✅ **Preserves** your uploads directory
- ✅ **Verifies** installation after update
- ✅ **Shows** what files were updated

---

## 🆘 **Troubleshooting**

### "No update package found"
- Make sure you uploaded the ZIP file
- Check it's in the `deployment-packages` folder or root directory
- Verify the file name ends with `.zip`

### "Permission denied"
- Contact your hosting provider
- Or set permissions manually via cPanel File Manager

### "Update failed"
- Check the backup folder for your previous files
- Restore from backup if needed
- Contact support with error message

### "Site not working after update"
- Clear browser cache
- Check error logs
- Verify database connection
- Restore from backup if needed

---

## 💡 **Pro Tips**

1. **Always backup first** - The script does this automatically, but having your own backup is safer

2. **Test locally first** - Make sure everything works on your computer before updating production

3. **Update during low traffic** - Choose a time when few users are on the system

4. **Keep the ZIP file** - Save it somewhere safe in case you need to restore

5. **Read the instructions** - The package includes a text file with specific instructions

---

## 📞 **Need Help?**

If you encounter any issues:
1. Check the error message
2. Look in the `backups` folder for your previous version
3. Check `docs/DEPLOYMENT_UPDATE_GUIDE.md` for detailed instructions
4. Contact your developer or hosting support

---

**Last Updated:** November 2025

