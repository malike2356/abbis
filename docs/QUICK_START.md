# Material Store System - Quick Start Guide

## 🚀 3-Minute Quick Start

### Step 1: Run Migration (One Time Only)
```
http://localhost:8080/abbis3.2/modules/admin/run-material-store-migration.php
```
Click **"Run Migration"** button ✅

### Step 2: Access Dashboard
```
http://localhost:8080/abbis3.2/modules/material-store-dashboard.php
```
OR from Resources page → Click **"📊 Material Store Dashboard"**

### Step 3: Transfer Materials from POS
1. Click **"📦 Bulk Transfer from POS"**
2. Add materials (Material Type + Quantity)
3. Click **"Transfer All"** ✅

### Step 4: Use in Field Reports
1. Create Field Report
2. Select **"Company (Store/Warehouse)"** in Materials Provided By
3. Enter received/used quantities
4. System auto-calculates remaining and value ✅

### Step 5: Return Unused Materials
1. In Dashboard, click **"🔄 Return to POS"** on any material
2. Enter quantity
3. Confirm ✅

---

## 📍 Key Locations

| What | Where |
|------|-------|
| **Dashboard** | Resources → Materials → "📊 Material Store Dashboard" |
| **Transfer Materials** | Resources → Materials → "📦 Transfer from POS" |
| **Use Materials** | Field Reports → New Report → Materials Section |
| **View Analytics** | Material Store Dashboard → Analytics Section |

---

## 🎯 Most Common Tasks

### Transfer Materials
**Resources** → **Materials** → **"📦 Transfer from POS"** → Select item → Fill form → Transfer

### Check Inventory
**Material Store Dashboard** → View **Current Inventory** table

### Use in Field Work
**Field Reports** → New Report → Materials Provided By: **"Company (Store/Warehouse)"** → Enter quantities

### Return Materials
**Material Store Dashboard** → Find material → **"🔄 Return to POS"** → Enter quantity

---

## ⚠️ Important Notes

1. **First Time**: Must run migration before using
2. **Materials Flow**: POS → Material Store → Field Work → Return to POS
3. **Auto-Calculations**: Remaining quantities and values calculate automatically
4. **Low Stock**: Dashboard shows alerts when stock is low

---

**Full Guide**: See `MATERIAL_STORE_USER_GUIDE.md` for detailed instructions.
