# Material Store System - User Guide

## 📋 Table of Contents
1. [Getting Started](#getting-started)
2. [Transferring Materials from POS](#transferring-materials-from-pos)
3. [Using Materials in Field Reports](#using-materials-in-field-reports)
4. [Returning Materials to POS](#returning-materials-to-pos)
5. [Viewing Dashboard & Analytics](#viewing-dashboard--analytics)
6. [Bulk Operations](#bulk-operations)
7. [Low Stock Alerts](#low-stock-alerts)

---

## 🚀 Getting Started

### Step 1: Run the Migration (First Time Only)

**If you haven't run the migration yet:**

1. Open your browser and go to:
   ```
   http://localhost:8080/abbis3.2/modules/admin/run-material-store-migration.php
   ```

2. Click the **"Run Migration"** button

3. Wait for the success message - you should see "✅ All tables exist!"

### Step 2: Access the Material Store

**Option A: From Resources Page**
- Go to: `Resources` → `Materials` tab
- Click **"📊 Material Store Dashboard"** button

**Option B: Direct Access**
- Go to: `http://localhost:8080/abbis3.2/modules/material-store-dashboard.php`

---

## 📦 Transferring Materials from POS

### Method 1: Single Transfer (From Resources Page)

1. Go to **Resources** → **Materials** tab
2. Click **"📦 Transfer from POS"** button
3. A modal will open showing available POS inventory
4. Click **"Transfer"** on the item you want to move
5. Fill in the form:
   - **Material Type**: Select (Screen Pipe, Plain Pipe, Gravel, etc.)
   - **Quantity**: Enter how much to transfer
   - **Remarks**: Optional notes
6. Click **"Transfer to Materials"**
7. ✅ Done! Materials are now in Material Store

### Method 2: Bulk Transfer (From Dashboard)

1. Go to **Material Store Dashboard**
2. Click **"📦 Bulk Transfer from POS"** button
3. Click **"➕ Add Material"** to add each material
4. For each material:
   - Select **Material Type**
   - Enter **Quantity**
   - Add **Remarks** (optional)
5. Click **"Transfer All"** when done
6. ✅ All materials transferred at once!

---

## 🔨 Using Materials in Field Reports

When creating a field report that uses materials from the Material Store:

1. Go to **Field Reports** → **New Report**
2. In the **"Materials Provided By"** dropdown, select:
   - **"Company (Store/Warehouse)"** - This means materials from Material Store
3. Fill in material quantities:
   - **Screen Pipes Received**: How many you received
   - **Screen Pipes Used**: How many you used
   - **Plain Pipes Received**: How many you received
   - **Plain Pipes Used**: How many you used
   - **Gravel Received**: How many you received
   - **Gravel Used**: How many you used
4. The system will automatically:
   - ✅ Calculate remaining materials
   - ✅ Calculate materials value (assets)
   - ✅ Deduct used materials from Material Store
   - ✅ Update inventory

**Note:** The **Materials Value** field shows the total value of **remaining** materials (your assets).

---

## 🔄 Returning Materials to POS

### From Material Store Dashboard

1. Go to **Material Store Dashboard**
2. Find the material you want to return in the inventory table
3. Click **"🔄 Return to POS"** button
4. Enter the quantity to return
5. Confirm the return
6. ✅ Materials are returned to POS inventory

### From Resources Page

1. Go to **Resources** → **Materials** tab
2. Scroll to **"Material Store Inventory"** section
3. Click **"🔄 Return to POS"** on the material
4. Enter quantity and confirm
5. ✅ Done!

---

## 📊 Viewing Dashboard & Analytics

### Accessing the Dashboard

1. Go to **Resources** → **Materials** tab
2. Click **"📊 Material Store Dashboard"**

OR

Direct URL: `modules/material-store-dashboard.php`

### What You Can See

#### 1. **Summary Statistics**
- Total material types
- Total units available
- Total inventory value
- Low stock alerts count

#### 2. **Current Inventory**
- All materials in Material Store
- Received, Used, Remaining, Returned quantities
- Unit cost and total value
- Stock status (OK, Low, Critical, Out of Stock)

#### 3. **Usage Analytics**
- **Usage by Material**: See how much of each material was received, used, and returned
- **Daily Usage**: View daily usage trends for the last 30 days

#### 4. **Recent Transactions**
- All material movements
- Transfer from POS
- Usage in field work
- Returns to POS
- Filterable by:
  - Material type
  - Transaction type
  - Date range

### Using Filters

1. In the dashboard, use the **Filters** section:
   - **Material Type**: Filter by specific material
   - **Transaction Type**: Filter by transfer, usage, or return
   - **Date From/To**: Select date range
2. Click **"Apply Filters"**
3. All tables and analytics update automatically

---

## 📦 Bulk Operations

### Bulk Transfer from POS

1. Go to **Material Store Dashboard**
2. Click **"📦 Bulk Transfer from POS"**
3. Click **"➕ Add Material"** for each material you want to transfer
4. Fill in details for each:
   - Material Type
   - Quantity
   - Remarks (optional)
5. To remove a row, click the **✕** button
6. Click **"Transfer All"** when ready
7. ✅ All materials transferred in one operation!

**Benefits:**
- Transfer multiple materials at once
- Faster workflow
- Single transaction (all or nothing)

---

## ⚠️ Low Stock Alerts

### How It Works

The system automatically monitors Material Store inventory and alerts you when:
- **Out of Stock**: No materials remaining
- **Critical**: Less than 10% remaining
- **Low**: Less than 20% remaining

### Viewing Alerts

1. **Dashboard Banner**: Alerts appear at the top of the dashboard
2. **Inventory Table**: Each material shows its status badge
3. **Summary Card**: Total number of low stock items

### What to Do

When you see a low stock alert:
1. Check the **Remaining** quantity
2. Transfer more materials from POS if needed
3. Or plan to restock from suppliers

---

## 🔍 Quick Reference

### Material Flow

```
POS (Material Shop)
    ↓ Transfer
Material Store (Warehouse)
    ↓ Use in Field
Field Reports
    ↓ Return unused
POS (Material Shop)
```

### Key Pages

| Page | URL | Purpose |
|------|-----|---------|
| Material Store Dashboard | `modules/material-store-dashboard.php` | View all inventory, analytics, transactions |
| Resources - Materials | `modules/resources.php?action=materials` | Transfer materials, view inventory |
| Field Reports | `modules/field-reports.php` | Use materials in field work |
| Migration Runner | `modules/admin/run-material-store-migration.php` | Run database migration (first time) |

### Material Types

Common material types:
- `screen_pipe` - Screen Pipe
- `plain_pipe` - Plain Pipe
- `gravel` - Gravel
- `rod` - Rod
- `other` - Other materials

---

## 💡 Tips & Best Practices

1. **Regular Monitoring**: Check the dashboard weekly to monitor inventory levels

2. **Use Bulk Transfer**: When transferring multiple materials, use bulk transfer to save time

3. **Track Remaining Materials**: Always check "Remaining" quantities before starting field work

4. **Return Unused Materials**: Return unused materials to POS to keep inventory accurate

5. **Check Low Stock Alerts**: Set up a routine to check alerts and restock before running out

6. **Use Filters**: Use date filters to analyze usage patterns over time

7. **Review Analytics**: Check usage analytics monthly to understand material consumption trends

---

## ❓ Troubleshooting

### "Table not found" Error
- **Solution**: Run the migration first (see Getting Started)

### "Insufficient stock" Error
- **Solution**: Check POS inventory - you may not have enough materials to transfer

### Materials not showing in dashboard
- **Solution**: Make sure you've transferred materials from POS first

### Analytics showing no data
- **Solution**: 
  - Check date range filters
  - Make sure you have transactions in that period
  - Try expanding the date range

---

## 🎯 Common Workflows

### Workflow 1: New Field Project

1. **Transfer Materials**: Bulk transfer needed materials from POS to Material Store
2. **Create Field Report**: Use materials in field report (select "Company (Store/Warehouse)")
3. **Track Usage**: System automatically deducts used materials
4. **Return Unused**: Return any unused materials to POS

### Workflow 2: Weekly Inventory Check

1. **Open Dashboard**: Check current inventory levels
2. **Review Alerts**: Check for low stock alerts
3. **Review Analytics**: Check usage trends
4. **Plan Restocking**: Transfer more materials if needed

### Workflow 3: End of Month Review

1. **Set Date Range**: Filter analytics for the month
2. **Review Usage**: Check usage by material type
3. **Review Transactions**: Check all material movements
4. **Plan Next Month**: Based on usage, plan material needs

---

**Need Help?** Check the test page: `test-material-store-system.php` to verify everything is working correctly.

