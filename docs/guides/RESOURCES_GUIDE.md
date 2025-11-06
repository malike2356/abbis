# 📦 ABBIS Resources Management - Simple Guide

## 🎯 The Big Picture

Everything in your business falls into **4 simple categories**. Here's what each one does:

---

## 📦 **MATERIALS** - Operational Consumables

**What it is:**
- Physical items you use in daily operations
- Things that get consumed/used up
- Stock that needs to be tracked

**Examples:**
- Screen pipes
- Plain pipes  
- Gravel
- Other supplies

**When to use:**
- ✅ Adding items you use on jobs
- ✅ Tracking stock levels (received, used, remaining)
- ✅ Items consumed in field reports
- ✅ Simple inventory tracking

**Table:** `materials_inventory`

---

## 🗂️ **CATALOG** - Products & Services

**What it is:**
- Products you sell to customers
- Services you offer
- Items with pricing (cost price & sell price)

**Examples:**
- Pump installation service
- PVC pipe product
- Well drilling service
- Reverse osmosis plant

**When to use:**
- ✅ Products/services you sell
- ✅ Building quotes for customers
- ✅ Creating invoices
- ✅ Managing your product/service list
- ✅ Items with pricing information

**Table:** `catalog_items`

---

## 🏭 **ASSETS** - Fixed Equipment

**What it is:**
- Equipment you own (not consumed)
- Items that have long-term value
- Things that appreciate/depreciate
- Equipment that needs maintenance

**Examples:**
- Drilling rigs
- Trucks/vehicles
- Pumps
- Tools
- Buildings

**When to use:**
- ✅ Equipment you own
- ✅ Items that depreciate in value
- ✅ Things that need maintenance
- ✅ Tracking asset value over time
- ✅ Insurance & warranty tracking

**Table:** `assets`

---

## 🔧 **MAINTENANCE** - Service Your Assets

**What it is:**
- Keeping your assets working
- Scheduling service/repairs
- Tracking maintenance costs
- Recording parts used

**Examples:**
- Servicing a drilling rig
- Replacing parts on a truck
- Preventive maintenance
- Emergency repairs

**When to use:**
- ✅ Scheduling service for assets
- ✅ Tracking repair costs
- ✅ Recording parts used (from Materials)
- ✅ Monitoring asset condition
- ✅ Planning preventive maintenance

**Tables:** `maintenance_records`, `maintenance_parts`, `maintenance_schedules`

---

## 🔗 **How They Connect**

### The Flow:

```
Field Reports
    ↓
    Uses → 📦 MATERIALS (pipes, gravel, supplies)
    Can reference → 🗂️ CATALOG ITEMS (products sold)

🏭 ASSETS (equipment)
    ↓
    Needs → 🔧 MAINTENANCE
    ↓
    Uses → 📦 MATERIALS (spare parts)

🔧 MAINTENANCE
    ↓
    Services → 🏭 ASSETS
    ↓
    Uses → 📦 MATERIALS (parts from inventory)
```

### Real-World Examples:

1. **Drilling a Well:**
   - Field Report uses **Materials** (screen pipes, gravel)
   - Field Report can sell **Catalog Item** (pump installation service)

2. **Maintaining a Rig:**
   - **Maintenance** scheduled for an **Asset** (drilling rig)
   - **Maintenance** uses **Materials** (spare parts, oil, filters)

3. **Selling to Customer:**
   - Quote uses **Catalog Items** (products/services)
   - Delivery might use **Materials** from inventory

---

## 💡 **Quick Decision Guide**

### "Should I add this to Materials or Catalog?"

- **Materials:** If it's something you **use** in operations (consumables)
- **Catalog:** If it's something you **sell** to customers (products/services)

### "Is this an Asset or Material?"

- **Asset:** If it's equipment you own that **lasts a long time** (rig, truck, pump)
- **Material:** If it's something you **use up** (pipes, gravel, supplies)

### "When do I use Maintenance?"

- When you need to **service, repair, or maintain** an Asset
- Maintenance **uses Materials** (spare parts) from inventory

---

## 🎯 **Summary**

- **📦 Materials** = What you USE (consumables)
- **🗂️ Catalog** = What you SELL (products/services)
- **🏭 Assets** = What you OWN (equipment)
- **🔧 Maintenance** = How you KEEP assets working

**One simple rule:** 
- If simple rule:** 
- If you USE it → Materials
- If you SELL it → Catalog  
- If you OWN it long-term → Assets
- If you SERVICE it → Maintenance

---

That's it! Simple and clear. 🎉
