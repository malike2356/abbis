# How to Access Receipts and Technical Reports

## Quick Access Guide

### 1. **From Field Reports List** (Main Method)
1. Navigate to **Field Reports** from the main menu
2. You'll see a list of all field reports
3. For each report, there are three action buttons in the **Actions** column:
   - **👁️ View** - View/edit the report
   - **💰 Receipt** - Generate and print Receipt/Invoice (Financial only)
   - **📄 Technical** - Generate and print Technical Report (Technical details only)

### 2. **From Client Management**
1. Go to **Clients** from the main menu
2. Click **View Details** for any client
3. Scroll down to see all transactions for that client
4. Click the **💰** button for Receipt or **📄** button for Technical Report

### 3. **Direct URL Access**
You can also access directly using:
- Receipt: `http://localhost:8080/abbis3.2/modules/receipt.php?report_id=REPORT_ID`
- Technical Report: `http://localhost:8080/abbis3.2/modules/technical-report.php?report_id=REPORT_ID`

Replace `REPORT_ID` with the actual report ID number from the database.

## What Each Report Contains

### Receipt/Invoice 💰
- **Company logo and branding**
- **Structural Reference ID** (for cross-referencing)
- **Financial information only:**
  - Total amount received
  - Contract sum
  - Rig fee collected
  - Materials income
  - Payment details
- **NO technical details**

### Technical Report 📄
- **Company logo and branding**
- **Same Structural Reference ID** (links to receipt)
- **Technical information only:**
  - Site location and GPS coordinates
  - Drilling specifications
  - Materials used
  - Personnel involved
  - Duration and depth
  - Notes and observations
- **NO financial information**

## Printing Reports

Both reports open in a new tab with print-friendly formatting:
1. Click the **💰 Receipt** or **📄 Technical** button
2. The report opens in a new browser tab
3. Click the **🖨️ Print** button at the bottom of the page
4. Or use your browser's print function (Ctrl+P / Cmd+P)

## Logo Upload

To add your company logo:
1. Go to **Configuration** → **Company Info**
2. Click **Choose File** under "Company Logo"
3. Select an image (PNG, JPG, GIF, or SVG - max 2MB)
4. Preview will appear immediately
5. Click **Save Company Info**
6. Logo will appear in:
   - System header
   - Page favicon
   - All receipts
   - All technical reports

