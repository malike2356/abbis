# Material Store System - User Guide

## Overview

The Material Store is a separate inventory location for materials kept for field work. It sits between:
- **POS (Material Shop)**: Where materials are purchased/sold
- **Material Store**: Where materials are kept for field operations
- **Field Work**: Where materials are actually used

## Setup (First Time Only)

### Step 1: Run the Migration

1. Navigate to: `http://localhost:8080/abbis3.2/modules/admin/run-material-store-migration.php`
2. Click **"Run Migration"** button
3. Wait for success message confirming tables are created

## How to Use

### 1. Transfer Materials from POS to Material Store

**When to use:** When you receive materials at the POS/Material Shop and want to move them to the Material Store for field work.

**Steps:**
1. Go to **Resources** page (`modules/resources.php?action=materials`)
2. Click **"📦 Transfer from POS to Material Store"** button (top right)
3. Select material type:
   - Screen Pipe
   - Plain Pipe
   - Gravel
4. Enter quantity to transfer
5. Add remarks (optional)
6. Click **"Transfer"**

**What happens:**
- ✅ POS inventory decreases
- ✅ Material Store inventory increases
- ✅ Transaction is logged

---

### 2. Use Materials in Field Reports

**When to use:** When materials from the Material Store are used in field work.

**Steps:**
1. Go to **Field Reports** → Create New Report
2. In the **"Drilling / Construction"** tab:
   - Select **"Materials Provided By"** = **"Material Store"**
   - The system will show available Material Store stock
3. Enter materials used:
   - Screen Pipes Used
   - Plain Pipes Used
   - Gravel Used
4. Fill in other report details
5. Submit the report

**What happens:**
- ✅ Material Store inventory decreases by the amount used
- ✅ Remaining quantities are calculated and shown in the report
- ✅ Material value is automatically calculated
- ✅ Field report shows:
   - Screen Pipes Remaining
   - Plain Pipes Remaining
   - Gravel Remaining
   - Materials Value (Assets)

**Example:**
- Material Store has 100 screen pipes
- Field report uses 80 screen pipes
- After submission:
  - Material Store now has 20 screen pipes
  - Field report shows "20 remaining"
  - Value is calculated based on unit cost

---

### 3. Return Unused Materials to POS

**When to use:** When you have unused materials in the Material Store that you want to return to POS.

**Option A: From Resources Page**
1. Go to **Resources** page
2. Scroll to **"🏪 Material Store Inventory"** section
3. Find the material you want to return
4. Click **"🔄 Return to POS"** button
5. Enter quantity to return
6. Confirm

**Option B: From Field Reports**
- After completing a field report, if you have remaining materials, you can return them via the Resources page

**What happens:**
- ✅ Material Store inventory decreases
- ✅ POS inventory increases
- ✅ Transaction is logged

---

### 4. View Material Store Inventory

**Location:** Resources page → Scroll to **"🏪 Material Store Inventory"** section

**Information shown:**
- **Material Name**: Type of material
- **Received**: Total received from POS
- **Used**: Total used in field work
- **Remaining**: Currently available for field work
- **Returned**: Total returned to POS
- **Unit Cost**: Cost per unit
- **Total Value**: Current inventory value
- **Actions**: Return to POS button

---

## Material Flow Diagram

```
┌─────────────────┐
│  POS (Material  │
│     Shop)       │
│                 │
│  [Purchased/    │
│   Received]     │
└────────┬────────┘
         │ Transfer
         │ (Decreases POS)
         ▼
┌─────────────────┐
│ Material Store  │
│                 │
│  [Stored for    │
│   Field Work]   │
└────────┬────────┘
         │ Use in Field
         │ (Decreases Store)
         ▼
┌─────────────────┐
│  Field Reports  │
│                 │
│  [Materials     │
│   Used]         │
└─────────────────┘
         │
         │ Return Unused
         │ (Decreases Store, Increases POS)
         ▼
┌─────────────────┐
│  POS (Material  │
│     Shop)       │
└─────────────────┘
```

## Important Notes

1. **Material Shop (POS) vs Material Store:**
   - **Material Shop (POS)**: Where materials are purchased/sold to customers
   - **Material Store**: Where materials are kept for company field operations

2. **Automatic Calculations:**
   - Remaining quantities are calculated automatically
   - Material values are calculated based on unit costs
   - All inventory updates happen automatically

3. **Field Report Integration:**
   - When "Material Store" is selected, the system checks available stock
   - If insufficient stock, you'll get an error
   - Remaining quantities are saved in the field report

4. **Transaction Logging:**
   - All transfers, usage, and returns are logged
   - You can track the history of all material movements

## Troubleshooting

**Problem:** "Material Store tables not found"
- **Solution:** Run the migration at `/modules/admin/run-material-store-migration.php`

**Problem:** "Insufficient stock in Material Store"
- **Solution:** Transfer more materials from POS to Material Store first

**Problem:** "Materials not showing in Material Store"
- **Solution:** Make sure you've transferred materials from POS first

**Problem:** "Remaining quantities not updating"
- **Solution:** Check that you selected "Material Store" in the field report's "Materials Provided By" dropdown

## Quick Reference

| Action | Location | Button/Menu |
|--------|----------|-------------|
| Transfer from POS | Resources | "📦 Transfer from POS to Material Store" |
| View Inventory | Resources | Scroll to "🏪 Material Store Inventory" |
| Use in Field | Field Reports | Select "Material Store" in dropdown |
| Return to POS | Resources | "🔄 Return to POS" button |

---

**Need Help?** Check the Material Store Inventory section on the Resources page for current stock levels and transaction history.

