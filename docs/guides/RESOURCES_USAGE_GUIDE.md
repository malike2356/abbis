# 📦 Resources Manager - User Guide

## 🚀 Quick Start

### Access the Resources Manager
1. Log into ABBIS
2. Click on **"Resources"** in the main navigation menu (left sidebar)
3. You'll see the **Resources Management** overview page

---

## 🎯 The Simple Rule (Always Remember)

When deciding where to put something, use this rule:

- **📦 Materials** → If you **USE** it (consumable items like pipes, gravel, supplies)
- **🗂️ Catalog** → If you **SELL** it (products/services you sell to customers with pricing)
- **🏭 Assets** → If you **OWN** it long-term (equipment you own like rigs, vehicles, tools)
- **🔧 Maintenance** → If you **SERVICE** it (keeping assets working, repairs, scheduled service)

---

## 📊 Overview Page

When you first open Resources, you'll see:

### Statistics Cards
- **Materials Card**: Shows total material types, items in stock, and total value
- **Catalog Card**: Shows active items and categories
- **Assets Card**: Shows total assets, active assets, and total value
- **Maintenance Card**: Shows pending tasks, due soon, and overdue items

### Quick Actions
Click any quick action button to:
- ➕ Add Material
- ➕ Add Catalog Item
- ➕ Register Asset
- ➕ Log Maintenance

### Navigation Tabs
At the top, you'll see tabs:
- **📊 Overview** - Dashboard with statistics
- **📦 Materials** - Manage materials inventory
- **🗂️ Catalog** - Manage products and services
- **🏭 Assets** - Manage company assets
- **🔧 Maintenance** - Manage maintenance tasks

---

## 📦 Using Materials

**When to use:** Items you USE/consume in operations

### Adding a Material
1. Go to **Resources → Materials** tab
2. Click **"Add Material"** button
3. Fill in:
   - Material Type (e.g., `screen_pipe`, `plain_pipe`, `gravel`)
   - Material Name (e.g., "Screen Pipe 6 inch")
   - Quantity Received
   - Unit Cost
   - Unit of Measure (pcs, kg, bags, etc.)
   - Supplier (optional)
4. Click **Save**

### Updating Materials
- Materials are automatically updated when used in Field Reports
- You can manually edit quantities and costs from the Materials table

**Example Use Cases:**
- ✅ Adding screen pipes received from supplier
- ✅ Tracking gravel inventory
- ✅ Managing drilling supplies

---

## 🗂️ Using Catalog

**When to use:** Products/services you SELL to customers

### Adding a Catalog Item
1. Go to **Resources → Catalog** tab
2. Click **"Add Item"** button
3. Fill in:
   - Item Name
   - Category (Services & Construction, Materials & Parts, etc.)
   - Item Type (product or service)
   - Cost Price (what you pay)
   - Selling Price (what you charge)
   - Unit (optional)
4. Click **Save**

### Using Catalog in Field Reports
- When creating expenses in Field Reports, you can select from Catalog items
- Pricing is automatically pulled from Catalog
- Helps maintain consistent pricing across all jobs

**Example Use Cases:**
- ✅ Adding a new service (e.g., "Pump Installation")
- ✅ Adding products you sell (e.g., "PVC Pipe 6 inch")
- ✅ Managing your price list

---

## 🏭 Using Assets

**When to use:** Equipment you OWN long-term

### Registering an Asset
1. Go to **Resources → Assets** tab
2. Click **"Add Asset"** button
3. Fill in:
   - Asset Name (e.g., "Drilling Rig #1")
   - Asset Type (rig, vehicle, equipment, tool, building, land)
   - Asset Code (unique identifier)
   - Purchase Date
   - Purchase Price
   - Current Value
   - Status (Active, Maintenance, Inactive, Disposed)
   - Location
4. Click **Save**

### Asset Management
- Track depreciation over time
- Link assets to maintenance records
- Monitor asset value and condition

**Example Use Cases:**
- ✅ Registering a new drilling rig
- ✅ Adding company vehicles
- ✅ Tracking equipment value

---

## 🔧 Using Maintenance

**When to use:** When you SERVICE assets (scheduled or repairs)

### Logging Maintenance
1. Go to **Resources → Maintenance** tab
2. Click **"Add Maintenance"** button
3. Fill in:
   - Maintenance Type (Proactive or Reactive)
   - Asset being serviced
   - Scheduled Date
   - Priority
   - Description
   - Parts needed
4. Click **Save**

### RPM-Based Maintenance (For Rigs)
- System automatically tracks rig RPM from Field Reports
- When RPM threshold is reached, maintenance is auto-scheduled
- Configure RPM intervals in **System → Configuration → Rigs**

### Maintenance Tracking
- Tracks parts used (from Materials inventory)
- Records expenses separately
- Links to assets for history

**Example Use Cases:**
- ✅ Scheduling regular rig service (proactive)
- ✅ Logging breakdown repairs (reactive)
- ✅ Tracking maintenance costs

---

## 🔗 How Resources Connect

### Real-World Workflow Example:

1. **Field Report Created** → Uses Materials (pipes, gravel) → Materials inventory decreases automatically
2. **Catalog Item Sold** → Referenced in Field Report → Pricing from Catalog
3. **Rig Used** → RPM tracked → Auto-schedules Maintenance when threshold reached
4. **Maintenance Performed** → Uses Materials (spare parts) → Links to Asset
5. **Asset Maintenance** → Updates asset condition → Tracks maintenance history

---

## 💡 Tips & Best Practices

### Materials
- ✅ Always update quantities when receiving new stock
- ✅ Set up materials before creating Field Reports
- ✅ Monitor low stock alerts

### Catalog
- ✅ Keep pricing updated
- ✅ Use categories to organize items
- ✅ Import sample list to get started quickly

### Assets
- ✅ Register all major equipment
- ✅ Update asset values periodically
- ✅ Link assets to maintenance records

### Maintenance
- ✅ Set RPM intervals for rigs
- ✅ Log all maintenance activities
- ✅ Track parts and expenses

---

## ❓ Common Questions

**Q: Where do I put a drill bit?**
- A: **Materials** (if it's consumable) or **Assets** (if it's reusable equipment)

**Q: Where do I put a pump installation service?**
- A: **Catalog** (it's a service you sell)

**Q: How do materials and catalog relate?**
- A: Catalog items can link to materials for inventory tracking, but Catalog is for selling, Materials is for using.

**Q: When does maintenance get auto-scheduled?**
- A: When rig RPM reaches the threshold you set in System Configuration → Rigs

**Q: Can I track maintenance for non-rig assets?**
- A: Yes! Maintenance works for any asset (vehicles, equipment, etc.)

---

## 🆘 Need Help?

- Check the **Help** section in ABBIS
- Review the **Simple Rule** at the top of Resources page
- Look at the **Interconnections** section for relationships

---

**Remember: Follow the Simple Rule, and you'll always know where things belong!** 🎯
