# 📶 How to Use ABBIS Offline Field Reports

## Complete Step-by-Step Guide

### 🎯 Overview
The offline field report system allows you to capture complete field reports **without any internet connection**. All data is saved locally on your device and automatically syncs to the server when you reconnect.

---

## 📋 Step 1: First-Time Setup (When You Have Internet)

### Option A: Access Online First
1. **Open your browser** (Chrome, Firefox, Safari, Edge)
2. **Navigate to:** `http://your-domain/abbis3.2/offline`
   - Replace `your-domain` with your actual domain (e.g., `localhost:8080` or your server IP)
3. **Log in to ABBIS** (if not already logged in)
   - This is important for authentication - you need to be logged in at least once
4. **The page will load** - you'll see the offline form

### Option B: Save Page Locally (Recommended for Offline Use)
1. **While online**, navigate to the offline page
2. **Save the page:**
   - **Chrome/Edge:** Right-click → "Save As" or `Ctrl+S` (Windows) / `Cmd+S` (Mac)
   - **Firefox:** Right-click → "Save Page As" or `Ctrl+S` / `Cmd+S`
   - **Safari:** Right-click → "Save As" or `Cmd+S`
3. **Choose location:** Save to Desktop, Documents, or any folder you prefer
4. **File name:** Keep the default name or rename it (e.g., "ABBIS Offline Report")
5. **Save type:** Make sure it saves as "Web Page, Complete" or "HTML" format

**✅ Now you have the page saved locally!**

---

## 📱 Step 2: Using Offline (No Internet Required)

### Opening the Saved Page
1. **Navigate to where you saved the file** (Desktop, Documents, etc.)
2. **Double-click the HTML file** to open it in your browser
3. **The form will load** - you can now use it completely offline!

### Filling Out a Report
1. **Fill in all required fields** (marked with *):
   - **Management Tab:** Date, Rig, Job Type, Site Name, Client Name
   - **Drilling Tab:** Times, RPM, Depth, Materials
   - **Workers Tab:** Click "+ Add Worker" to add payroll entries
   - **Financial Tab:** Income, Expenses, Deposits
   - **Incidents Tab:** Incident logs, solutions, recommendations

2. **Auto-calculations happen automatically:**
   - Total Duration (from start/finish time)
   - Total RPM (from start/finish RPM)
   - Total Depth (from rod length × rods used)
   - Construction Depth (from pipes used)
   - Worker amounts (from units × rate + benefits - loans)
   - Expense amounts (from unit cost × quantity)

3. **Click "💾 Save Offline"** button
   - You'll see a confirmation: "Report saved offline!"
   - The form clears automatically
   - Report appears in "Pending Reports" section

4. **Repeat** for as many reports as needed

### Viewing Pending Reports
- Scroll down to see the "Pending Reports" section
- You'll see all saved reports with:
  - Report date and site name
  - Client and rig information
  - Status (Pending, Synced, Failed)
  - Edit and Delete buttons

### Editing or Deleting Reports
- **Edit:** Click "Edit" button on any pending report
  - Form will populate with report data
  - Make changes and click "💾 Save Offline" again
- **Delete:** Click "Delete" button
  - Confirmation required
  - Report will be removed from local storage

---

## 🔄 Step 3: Syncing When Online

### Automatic Sync
1. **When you reconnect to the internet:**
   - The sync status indicator (top-right) will change from 🔴 "Offline" to 🟢 "Online"
   - System automatically starts syncing pending reports
   - Happens every 30 seconds when online

2. **Watch the progress:**
   - Status shows "🟡 Syncing..." during upload
   - Progress message: "Syncing 2 of 5..."
   - Success notification when complete

### Manual Sync (Recommended)
1. **Check your connection:**
   - Make sure you have internet
   - Status should show "🟢 Online"

2. **Click "🔄 Sync Now" button:**
   - Located in the top-right corner (sync status box)
   - Button is always visible when online
   - Shows pending count: "🔄 Sync Now (3)" if reports are waiting

3. **Wait for sync to complete:**
   - Progress is shown in real-time
   - Success notification: "✅ X report(s) synced successfully!"
   - "Last sync" timestamp updates

4. **Verify sync:**
   - Check "Last sync" timestamp in sync status box
   - Review pending reports list - synced reports show "Synced" status
   - Reports now appear in your main ABBIS system

### Conflict Resolution
If a duplicate report is detected:
1. **Conflict modal appears automatically**
2. **Compare versions:**
   - Your local version (what you saved offline)
   - Server version (if someone else created a similar report)
3. **Choose an action:**
   - **Use My Version:** Overwrite server with your data
   - **Use Server Version:** Keep server data, discard yours
   - **Skip:** Keep both versions
4. **Click "Resolve"** to apply your choice

---

## 💡 Tips & Best Practices

### Saving Reports
- ✅ **Save frequently** - Don't wait until end of day
- ✅ **Save after each report** - Prevents data loss
- ✅ **Check pending list** - Verify reports are saved

### Using Offline
- ✅ **Save page locally** - Works even without internet
- ✅ **Close browser safely** - All data is saved in localStorage
- ✅ **Reopen anytime** - Reports persist even after closing
- ✅ **Use on any device** - Computer, tablet, or phone

### Syncing
- ✅ **Use manual sync** - Click "Sync Now" when you have internet
- ✅ **Check sync status** - Verify "Last sync" timestamp
- ✅ **Review pending reports** - Make sure all synced successfully
- ✅ **Resolve conflicts** - Don't ignore conflict warnings

### Troubleshooting
- ❌ **Can't sync?** Check internet connection first
- ❌ **Reports not saving?** Check browser console (F12) for errors
- ❌ **Lost data?** Check "Pending Reports" section - data is stored locally
- ❌ **Sync failed?** Check error message in pending reports list

---

## 🔍 Understanding the Interface

### Sync Status Indicator (Top-Right)
- **🟢 Online:** Connected, ready to sync
- **🔴 Offline:** No internet, reports saved locally
- **🟡 Syncing:** Currently uploading reports
- **Pending Count Badge:** Number of reports waiting to sync
- **Sync Now Button:** Click to manually sync
- **Last Sync:** Timestamp of last successful sync

### Info Box (Below Sync Status)
- **💡 How it works:** Quick reference guide
- **Close button (×):** Hide the box
- **"Learn more" link:** Reopen the box if closed

### Form Tabs
1. **Management:** Basic info, client, location
2. **Drilling:** Technical details, materials, depth
3. **Workers:** Payroll entries
4. **Financial:** Income, expenses, deposits
5. **Incidents:** Logs and recommendations

---

## 📱 Mobile Usage

### On Phone/Tablet
1. **Open browser** (Chrome, Safari, Firefox)
2. **Navigate to:** `http://your-domain/abbis3.2/offline`
3. **Add to Home Screen:**
   - **iOS:** Share button → "Add to Home Screen"
   - **Android:** Menu → "Add to Home Screen"
4. **Use like an app:**
   - Works offline
   - All features available
   - Data syncs when online

---

## 🔐 Important Notes

### Authentication
- **First access:** Must be logged into ABBIS
- **After that:** Can use offline (authentication cached)
- **For sync:** Must have valid session or be logged in

### Data Storage
- **Location:** Browser's localStorage
- **Limit:** Typically 5-10MB (hundreds of reports)
- **Persistence:** Data remains even after closing browser
- **Backup:** Export pending reports if needed (coming soon)

### Browser Compatibility
- ✅ **Chrome/Edge:** Full support
- ✅ **Firefox:** Full support
- ✅ **Safari:** Full support
- ✅ **Mobile browsers:** Full support

---

## 🎯 Quick Start Checklist

- [ ] Access offline page while online
- [ ] Log in to ABBIS (first time only)
- [ ] Save page locally (optional but recommended)
- [ ] Fill out first report offline
- [ ] Click "💾 Save Offline"
- [ ] Verify report in "Pending Reports"
- [ ] Connect to internet
- [ ] Click "🔄 Sync Now"
- [ ] Verify sync success
- [ ] Check main ABBIS system for synced report

---

## ❓ Common Questions

**Q: Do I need internet to save reports?**  
A: No! Reports are saved locally. Internet is only needed for syncing.

**Q: What if I close the browser?**  
A: All saved reports remain. Just reopen the page and they'll still be there.

**Q: How many reports can I save offline?**  
A: Hundreds! Limited only by browser storage (typically 5-10MB).

**Q: Can I use it on multiple devices?**  
A: Yes, but each device has its own local storage. Sync from each device separately.

**Q: What if sync fails?**  
A: Check error message in pending reports. Reports remain saved locally and can be synced again.

**Q: Can I edit reports after saving?**  
A: Yes! Click "Edit" on any pending report, make changes, and save again.

**Q: How do I know if sync worked?**  
A: Check "Last sync" timestamp and verify reports appear in main ABBIS system.

---

## 🆘 Need Help?

- Check the **Help page** in ABBIS for detailed documentation
- Look at **browser console** (F12) for error messages
- Verify **internet connection** before syncing
- Check **pending reports** for error messages
- Ensure you're **logged into ABBIS** for sync to work

---

**✅ You're all set! Start capturing field reports offline today!**

