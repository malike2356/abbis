# Catalog Integration Guide

## Where Catalog Items Can Be Used

### 1. **Field Reports** (`modules/field-reports.php`)
   - **Current**: Manual expense entry with free-text description
   - **Integration**: Replace/add dropdown to select catalog items (Products/Services)
   - **Benefits**: 
     - Auto-fill cost/price per item
     - Standardize expense descriptions
     - Link expenses to catalog for pricing consistency
   - **Fields to enhance**:
     - Expenses table (line items)
     - Materials received/used (link to catalog products)

### 2. **Receipts/Invoices** (`modules/receipt.php`)
   - **Current**: Shows aggregated totals from field reports
   - **Integration**: Display detailed line items from catalog
   - **Benefits**:
     - Itemized invoices with catalog items
     - Professional breakdown (quantity × unit price)
     - Automatic totals calculation
   - **New fields needed**:
     - Link `field_reports` → `catalog_items` via junction table

### 3. **Materials Management** (`modules/materials.php`)
   - **Current**: Manual material type selection (screen_pipe, plain_pipe, gravel)
   - **Integration**: 
     - Link materials to catalog products
     - Auto-fill unit cost from catalog
     - Track inventory value using catalog prices
   - **Fields to enhance**:
     - Material type dropdown → catalog item selection
     - Purchase cost → catalog cost_price

### 4. **Finance Module** (`modules/finance.php`)
   - **Current**: Aggregated totals from field reports
   - **Integration**: 
     - Expense categorization by catalog item
     - Revenue breakdown by service/product type
     - Margin analysis (catalog sell_price vs actual)
   - **Benefits**:
     - Better financial reporting
     - Identify most profitable items
     - Track actual vs catalog pricing

### 5. **Accounting System** (`modules/accounting.php`)
   - **Current**: Journal entries for financial transactions
   - **Integration**:
     - Sales entries → catalog items (Revenue accounts)
     - Purchase entries → catalog items (COGS/Expense accounts)
     - Automatic account mapping based on item type
   - **Benefits**:
     - Accurate double-entry bookkeeping
     - Automated revenue/expense categorization

### 6. **CRM - Quotes/Proposals** (Future Enhancement)
   - **Not yet built** but referenced in CRM templates
   - **Integration**:
     - Create quotes using catalog items
     - Send proposals with pricing breakdown
     - Track quote conversion to invoices
   - **New tables needed**:
     - `client_quotes` (id, client_id, items[], total, status)
     - `quote_items` (quote_id, catalog_item_id, quantity, price)

### 7. **Client Detail Pages** (`modules/clients.php`)
   - **Current**: Shows transaction history
   - **Integration**:
     - List catalog items/services used per client
     - Show item-level profit analysis
     - Historical pricing trends per item

### 8. **Advanced Inventory** (`modules/inventory-advanced.php`)
   - **Current**: Stock tracking and transactions
   - **Integration**:
     - Link inventory items to catalog products
     - Auto-calculate stock value using catalog cost_price
     - Reorder points based on catalog data

### 9. **Dashboard Analytics**
   - **Integration**:
     - Top-selling catalog items
     - Most profitable products/services
     - Category-wise revenue breakdown
   - **Benefits**:
     - Business intelligence on product performance
     - Identify items to promote/discontinue

## Implementation Priority

### High Priority (Immediate Value)
1. **Field Reports Expenses** - Most direct usage
2. **Receipts Line Items** - Professional invoicing
3. **Materials Management** - Pricing consistency

### Medium Priority (Better Reporting)
4. **Finance Module** - Enhanced analytics
5. **Client Detail Pages** - Item-level insights
6. **Dashboard** - Product performance KPIs

### Lower Priority (Future Enhancements)
7. **CRM Quotes** - Requires new module
8. **Accounting Integration** - Advanced feature
9. **Advanced Inventory Link** - Nice to have

## Database Schema Additions Needed

```sql
-- Link field reports to catalog items
CREATE TABLE field_report_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  catalog_item_id INT NOT NULL,
  quantity DECIMAL(10,2) DEFAULT 1.00,
  unit_price DECIMAL(12,2) NOT NULL,
  total_amount DECIMAL(12,2) NOT NULL,
  item_type ENUM('expense','revenue','material') DEFAULT 'expense',
  FOREIGN KEY (report_id) REFERENCES field_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id),
  INDEX idx_report (report_id)
);

-- Client quotes (for future CRM quotes)
CREATE TABLE client_quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  quote_number VARCHAR(50) UNIQUE,
  total_amount DECIMAL(12,2) NOT NULL,
  status ENUM('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  valid_until DATE,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Quote line items
CREATE TABLE quote_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  catalog_item_id INT NOT NULL,
  quantity DECIMAL(10,2) DEFAULT 1.00,
  unit_price DECIMAL(12,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (quote_id) REFERENCES client_quotes(id) ON DELETE CASCADE,
  FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id)
);
```

## API Endpoints Needed

```php
// Get catalog items for dropdowns
GET /api/catalog-items.php?type=product&category_id=1&active=1

// Get item details
GET /api/catalog-items.php?id=123

// Calculate quote/invoice totals
POST /api/calculate-totals.php {items: [{item_id, quantity}]}
```

## Next Steps

1. **Phase 1**: Enhance Field Reports expenses section with catalog dropdown
2. **Phase 2**: Add line items to Receipts using catalog data
3. **Phase 3**: Link Materials Management to catalog
4. **Phase 4**: Build CRM Quotes module with catalog integration
5. **Phase 5**: Add catalog analytics to Dashboard

---

**Status**: Catalog module is ready. Integration points identified. Ready for Phase 1 implementation.
