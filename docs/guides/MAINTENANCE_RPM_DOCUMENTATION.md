# 🔧 RPM-Based Maintenance System Documentation

## Overview

The ABBIS Maintenance System uses **RPM (Revolutions Per Minute)** of the compressor engine as the primary metric for proactive maintenance scheduling on drilling rigs. This document explains how the system works and how all components integrate.

---

## 🎯 Core Concept

### RPM-Based Maintenance Scheduling

**How it works:**
1. Before maintenance is performed, the current RPM of the rig's compressor engine is recorded
2. A maintenance interval is set (e.g., 30.00 RPM)
3. The system automatically schedules maintenance when the rig reaches the threshold RPM
4. When maintenance is completed, the RPM reading is recorded and the next threshold is calculated

**Example:**
- Initial RPM: 24.55
- Maintenance Interval: 30.00 RPM
- Next Maintenance Due: 54.55 RPM (24.55 + 30.00)

When the rig reaches 54.55 RPM, proactive maintenance is automatically scheduled.

---

## 📊 Database Structure

### 1. **Rigs Table Enhancements**

```sql
current_rpm                  -- Current total RPM from all field reports
last_maintenance_rpm         -- RPM reading at last maintenance
maintenance_due_at_rpm       -- RPM threshold when maintenance is due
maintenance_rpm_interval     -- RPM interval between maintenance (default: 30.00)
```

**Flow:**
- Field reports update `current_rpm` after each job
- When `current_rpm >= maintenance_due_at_rpm`, maintenance alert is triggered
- After maintenance, `last_maintenance_rpm` is set to `current_rpm`
- Next `maintenance_due_at_rpm` = `current_rpm + maintenance_rpm_interval`

### 2. **Maintenance Records Enhancements**

```sql
rpm_at_maintenance      -- RPM reading when maintenance was performed
rpm_threshold          -- RPM threshold that triggered this maintenance
rpm_interval_used      -- RPM interval between last and current maintenance
next_maintenance_rpm   -- RPM when next maintenance is due
```

**Flow:**
- When maintenance is logged/scheduled, `rpm_threshold` is set
- When maintenance is completed, `rpm_at_maintenance` is recorded
- `rpm_interval_used` = `rpm_at_maintenance - last_maintenance_rpm`
- `next_maintenance_rpm` = `rpm_at_maintenance + maintenance_rpm_interval`

### 3. **Maintenance Expenses Table** (Separate Tracking)

```sql
maintenance_expenses
  - expense_type: parts, labor, transport, miscellaneous, material
  - Links to materials_inventory (if applicable)
  - Tracks all costs separately before reflecting in overall company expenses
```

**Purpose:**
- Track maintenance expenses separately from operational expenses
- Link expenses to specific maintenance records
- Provide detailed cost breakdown for analysis
- Only after approval, expenses reflect in overall company financials

### 4. **Maintenance Components Table**

```sql
maintenance_components
  - component_name: Oil Filter, Air Filter, Compressor Oil, Hydraulic Oil, Hose, etc.
  - component_type: filter, oil, hose, hydraulic, electrical, mechanical
  - action_taken: replaced, serviced, cleaned, checked, adjusted, repaired
  - condition_before/after: excellent, good, fair, poor, critical
```

**Purpose:**
- Track exactly what components were serviced during maintenance
- Monitor component condition over time
- Plan proactive replacement schedules

---

## 🔄 System Workflow

### **Proactive Maintenance Flow:**

1. **Field Report Completion**
   - Field report records `finish_rpm`
   - System updates rig's `current_rpm` = `current_rpm + total_rpm`
   - System checks: `if current_rpm >= maintenance_due_at_rpm`
   - If true: Auto-create maintenance record with status 'scheduled'

2. **Maintenance Logging**
   - Record current RPM: `rpm_at_maintenance = current_rpm`
   - Record RPM threshold: `rpm_threshold = maintenance_due_at_rpm`
   - Select maintenance type (e.g., Oil Filter Replacement)
   - Select components to service
   - Set priority based on RPM threshold proximity

3. **Maintenance Execution**
   - Update status to 'in_progress'
   - Record parts/materials used (links to `materials_inventory`)
   - Record expenses (parts, labor, transport)
   - Record components serviced
   - Record work performed and results

4. **Maintenance Completion**
   - Update status to 'completed'
   - Record `completed_date`
   - Calculate `rpm_interval_used`
   - Update rig: `last_maintenance_rpm = rpm_at_maintenance`
   - Calculate next maintenance: `maintenance_due_at_rpm = rpm_at_maintenance + maintenance_rpm_interval`
   - Update `next_maintenance_rpm` in maintenance record
   - Record effectiveness rating

### **Reactive Maintenance Flow:**

1. **Breakdown Occurs**
   - Component fails or breaks (e.g., hose breaks)
   - Log maintenance with category 'reactive'
   - Priority automatically set to 'urgent' or 'critical'
   - No RPM threshold involved

2. **Emergency Repair**
   - Record breakdown details
   - Record parts needed/used
   - Record expenses
   - Complete repair

3. **Post-Repair Analysis**
   - Record RPM at repair: `rpm_at_maintenance = current_rpm`
   - Analyze if proactive maintenance could have prevented failure
   - Update maintenance schedules if needed

---

## 🔗 Component Relationships

### **Maintenance Components Serviced:**

**Common Rig Components:**
- **Oil Filters** - Replaced regularly (proactive)
- **Air Filters** - Replaced regularly (proactive)
- **Compressor Oil** - Changed at intervals (proactive)
- **Hydraulic Oil** - Changed at intervals (proactive)
- **Hydraulic System** - Serviced regularly (proactive)
- **Hoses** - Replaced when worn (reactive/proactive)
- **Electrical System** - Checked and repaired (reactive)
- **Brake System** - Serviced regularly (proactive)
- **Cooling System** - Maintained regularly (proactive)

**Each component can be:**
- Replaced
- Serviced
- Cleaned
- Checked
- Adjusted
- Repaired

---

## 💰 Expense Tracking

### **Expense Categories:**

1. **Parts** - Components purchased (oil filters, hoses, etc.)
2. **Labor** - Labor costs for maintenance work
3. **Transport** - Transportation costs for parts/supplies
4. **Material** - Materials from inventory used
5. **Miscellaneous** - Other maintenance-related expenses

### **Expense Flow:**

1. **Record During Maintenance**
   - Add expense entries as maintenance progresses
   - Link to materials inventory if applicable
   - Record supplier, invoice number, purchase date

2. **Separate Tracking**
   - All maintenance expenses stored in `maintenance_expenses`
   - NOT immediately reflected in operational expenses
   - Provides detailed breakdown for analysis

3. **Approval & Integration**
   - Maintenance expenses reviewed separately
   - After approval, can be integrated into overall company expenses
   - Allows for better cost analysis and budgeting

---

## 📈 RPM Tracking from Field Reports

### **Automatic RPM Updates:**

When a field report is saved:
1. System calculates: `total_rpm = finish_rpm - start_rpm`
2. Updates rig: `current_rpm = current_rpm + total_rpm`
3. Records in `rig_rpm_history` for audit trail
4. Checks maintenance threshold
5. Auto-schedules maintenance if threshold reached

**Example:**
- Rig current_rpm: 45.00
- Field report total_rpm: 12.50
- New current_rpm: 57.50
- Maintenance due at: 54.55
- **Result:** Auto-create maintenance record (threshold exceeded)

---

## 🎯 Best Practices

### **Setting Maintenance Intervals:**

- **Oil Filters:** Every 20-30 RPM
- **Air Filters:** Every 25-35 RPM
- **Compressor Oil:** Every 30-40 RPM
- **Hydraulic Oil:** Every 40-50 RPM
- **Full Service:** Every 50-60 RPM

### **Component Monitoring:**

- Check component condition before and after service
- Track component lifespan
- Adjust maintenance intervals based on condition
- Use reactive maintenance data to improve proactive schedules

### **Expense Management:**

- Record all expenses immediately during maintenance
- Link parts to materials inventory when possible
- Review maintenance expenses monthly
- Analyze cost trends to optimize maintenance schedules

---

## 🔍 Integration Points

### **1. Field Reports → RPM Tracking**
- Field reports automatically update rig RPM
- Triggers maintenance alerts when threshold reached

### **2. Maintenance → Materials Inventory**
- Parts used in maintenance deducted from inventory
- New parts purchased added to inventory
- Links via `maintenance_expenses.material_id`

### **3. Maintenance → Assets**
- Maintenance linked to specific assets (rigs)
- Updates asset condition and status
- Tracks asset value through maintenance costs

### **4. Maintenance → Financial System**
- Expenses tracked separately
- Integrated after approval
- Provides detailed cost analysis

---

## 📝 Maintenance Record Fields Explained

### **RPM Fields:**
- `rpm_at_maintenance`: Actual RPM when maintenance was done
- `rpm_threshold`: RPM threshold that triggered this maintenance
- `rpm_interval_used`: How many RPM since last maintenance
- `next_maintenance_rpm`: RPM when next maintenance is due

### **Status Workflow:**
- `logged`: Maintenance need identified
- `scheduled`: Maintenance scheduled for specific time/RPM
- `in_progress`: Maintenance work in progress
- `completed`: Maintenance completed
- `on_hold`: Maintenance paused
- `cancelled`: Maintenance cancelled

### **Priority Levels:**
- `low`: Routine maintenance, no urgency
- `medium`: Normal scheduled maintenance
- `high`: Important maintenance, schedule soon
- `urgent`: Needs attention soon
- `critical`: Immediate attention required (reactive)

---

## 🚀 Quick Start Guide

### **Setting Up RPM-Based Maintenance:**

1. **Configure Rig:**
   - Set `maintenance_rpm_interval` (default: 30.00)
   - Set initial `current_rpm` from last known reading
   - System calculates `maintenance_due_at_rpm`

2. **Log First Maintenance:**
   - Record current RPM
   - Select components to service
   - Record expenses
   - Complete maintenance

3. **System Auto-Schedules:**
   - As field reports come in, RPM updates automatically
   - When threshold reached, maintenance is auto-scheduled
   - Receive alerts for upcoming maintenance

### **Performing Maintenance:**

1. **Check RPM Status:**
   - View current RPM vs. threshold
   - See what components are due

2. **Log Maintenance:**
   - Record RPM at maintenance
   - Select components to service
   - Record parts/materials used
   - Record expenses

3. **Complete Maintenance:**
   - Mark components as serviced
   - Record work performed
   - Update effectiveness rating
   - System calculates next maintenance threshold

---

## 📊 Reporting & Analytics

### **Available Reports:**
- Maintenance cost by component type
- RPM intervals analysis
- Component lifespan tracking
- Reactive vs. Proactive maintenance ratio
- Maintenance effectiveness ratings
- Expense trends over time

---

**This system ensures proactive maintenance is performed at the right time, preventing costly breakdowns and extending rig lifespan. All expenses are tracked separately for detailed analysis before integration into overall company financials.**
