# 📦 Resources Management - Complete Integration Guide

## ✅ Issues Fixed

1. **Database Error Fixed**: Added `unit_of_measure` column to `materials_inventory` table
2. **Category Management**: Full CRUD for categories - view, add, edit, delete
3. **Inventory Tracking**: Complete inventory system for both Materials and Catalog items
4. **CMS Integration**: API endpoint ready for shop sales to reduce inventory
5. **Supplier Integration**: Purchase system with supplier tracking

## 🎯 How Everything Works Together

### 1. **Catalog Categories** 📁
- **View**: Go to Resources → Catalog tab
- **See All Categories**: Categories are displayed in a grid at the top of the Catalog page
- **Edit**: Click "Edit" button on any category card
- **Delete**: Click "Delete" button (only if category has no items)

### 2. **Inventory Management** 📊

#### For Catalog Items:
- **View Inventory**: Check the "Inventory" column in the Catalog Items table
- **Manage Inventory**: Click "Manage" button next to any item's inventory quantity
- **Inventory Modal** allows:
  - ➕ **Purchase**: Add stock from suppliers
  - ➖ **Sale**: Record sales (reduces inventory)
  - 🔧 **Use in Project**: Track when items are used in field reports
  - 📝 **Adjustment**: Correct inventory counts
  - ↩️ **Return**: Add returned items back

#### For Materials:
- **View Inventory**: Check the "Remaining" column in Materials table
- **Manage Inventory**: Click "Manage Inventory" button for any material
- Same transaction types as catalog items

### 3. **CMS Shop Integration** 🛒

When items are sold in your CMS shop:

**API Endpoint**: `api/inventory-sale.php`

**How to Use** (from your CMS):
```php
// When order is completed in CMS
$response = file_get_contents('http://your-domain/api/inventory-sale.php', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'order_number' => 'ORDER-123',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'cms_order_id' => 456, // Your CMS order ID
            'order_items' => [
                [
                    'item_type' => 'catalog', // or 'material'
                    'item_id' => 5, // Catalog/Material ID
                    'item_name' => 'Product Name',
                    'quantity' => 2,
                    'unit_price' => 150.00
                ]
            ]
        ])
    ]
]));
```

This will:
- ✅ Reduce inventory automatically
- ✅ Record the sale transaction
- ✅ Link to your CMS order
- ✅ Track all inventory movements

### 4. **Purchasing from Suppliers** 🛍️

#### Scenario 1: Buy for Resale
1. Go to Resources → Catalog
2. Find the item or add new item
3. Click "Manage" button next to inventory
4. Select "Purchase (Add Stock)"
5. Select supplier from dropdown
6. Enter quantity and cost
7. Inventory increases, cost is tracked

#### Scenario 2: Buy for Direct Use (Borehole Project)
1. Same as above, but after purchasing:
2. Go to Resources → Materials
3. Click "Manage Inventory" on the material
4. Select "Use in Project"
5. Enter quantity used
6. Inventory reduces, usage is tracked

### 5. **Field Reports Integration** 🔧

When materials are used in field reports:
- Materials inventory automatically reduces
- Quantity used increases
- Remaining quantity updates
- Cost calculations maintained

### 6. **Inventory Tracking** 📈

All inventory movements are tracked in `inventory_transactions`:
- Who made the transaction
- When it happened
- Transaction type (purchase/sale/use/adjustment/return)
- Reference (which order, field report, etc.)
- Cost information

## 🔄 Complete Workflow Examples

### Example 1: Buy → Sell Flow
1. **Purchase** from supplier:
   - Catalog Item: "Screen Pipe 6 inch"
   - Quantity: 100 pcs @ GHS 150 each
   - Supplier: ABC Suppliers
   - Inventory: 100 pcs

2. **Sell** via CMS shop:
   - Customer buys 10 pcs
   - CMS calls `api/inventory-sale.php`
   - Inventory automatically reduces to 90 pcs
   - Transaction recorded

### Example 2: Buy → Use in Project Flow
1. **Purchase** materials:
   - Material: "Gravel"
   - Quantity: 50 bags @ GHS 80 each
   - Inventory: 50 bags

2. **Use** in field report:
   - Field Report uses 10 bags
   - Inventory reduces to 40 bags
   - Cost tracked in field report

3. **Or manually record usage**:
   - Resources → Materials → Manage Inventory
   - Select "Use in Project"
   - Enter quantity

### Example 3: Self-Purchase for Direct Contract
1. **Add item** to catalog (if not exists)
2. **Purchase** inventory
3. **Use** in project (inventory reduces)
4. **Track** all costs and usage

## 📋 Key Features

### Inventory Management
- ✅ Real-time inventory tracking
- ✅ Low stock alerts (when quantity ≤ reorder level)
- ✅ Weighted average cost calculation
- ✅ Complete transaction history

### Category Management
- ✅ View all categories in grid
- ✅ Edit category name and description
- ✅ Delete categories (only if empty)
- ✅ See item count per category

### Supplier Integration
- ✅ Link purchases to suppliers
- ✅ Track purchase costs
- ✅ Supplier dropdown in purchase transactions

### CMS Integration
- ✅ Automatic inventory reduction on sales
- ✅ Link CMS orders to inventory system
- ✅ Complete audit trail

## 🎓 Quick Reference

### View Categories
**Resources → Catalog tab** - Categories shown at top

### Edit Category
1. Resources → Catalog
2. Find category card
3. Click "Edit"
4. Update name/description
5. Save

### Manage Inventory
1. Resources → Catalog/Materials
2. Click "Manage" or "Manage Inventory" button
3. Select transaction type
4. Enter quantity (and cost if purchasing)
5. Save

### Link CMS Sales
Call `api/inventory-sale.php` when order completes in CMS.

## 🔧 Technical Details

### Database Tables
- `materials_inventory` - Material stock
- `catalog_items` - Catalog items with inventory
- `catalog_categories` - Item categories
- `inventory_transactions` - All inventory movements
- `purchase_orders` - Purchase records
- `sale_orders` - Sale records (CMS linked)
- `suppliers` - Supplier list

### Inventory Calculations
- **Materials**: Uses weighted average cost when purchasing
- **Catalog**: Simple quantity tracking
- **Automatic**: Updates on all transactions

All systems are now integrated and working together! 🎉
