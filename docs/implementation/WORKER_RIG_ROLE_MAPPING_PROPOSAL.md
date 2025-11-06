# Worker-Rig-Role Mapping System Proposal

## Current Situation
- Workers have a single `role` field in the `workers` table
- Workers are assigned to rigs via payroll entries in field reports
- No tracking of which workers typically work on which rigs
- No support for workers having multiple roles

## Requirements
1. **Multiple Roles per Worker**: A worker can perform multiple roles (e.g., Driller AND Rig Driver, Rodboy AND Spanner Boy)
2. **Multiple Workers per Role**: Multiple workers can perform the same role on the same rig
3. **Flexible Rig Assignment**: Workers can move between rigs (not fixed)
4. **Role Flexibility**: Workers can perform different roles on different days/reports
5. **Rig Preferences**: Track which workers typically work on which rigs (for suggestions, not enforcement)

## Proposed Solution

### Database Schema Changes

#### 1. Worker Role Assignments (Many-to-Many)
```sql
CREATE TABLE `worker_role_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `default_rate` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_role_unique` (`worker_id`, `role_name`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose**: Allows workers to have multiple roles assigned to them
- `is_primary`: Marks the primary role (for backward compatibility)
- `default_rate`: Role-specific rate (worker may have different rates for different roles)

#### 2. Worker Rig Preferences (Many-to-Many)
```sql
CREATE TABLE `worker_rig_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `rig_id` int(11) NOT NULL,
  `preference_level` enum('primary','secondary','occasional') DEFAULT 'primary',
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_rig_unique` (`worker_id`, `rig_id`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose**: Tracks which rigs a worker typically works on
- `preference_level`: 
  - `primary`: Worker usually works on this rig
  - `secondary`: Worker sometimes works on this rig
  - `occasional`: Worker rarely works on this rig
- This is for **suggestions only**, not enforcement

#### 3. Keep Existing `workers.role` Field
- Keep as "primary role" for backward compatibility
- Auto-populate from `worker_role_assignments` where `is_primary = 1`

### Implementation Approach

#### Phase 1: Database & Backend
1. Create migration to add new tables
2. Migrate existing data:
   - For each worker, create `worker_role_assignments` entry with their current role as primary
3. Update API endpoints to support multiple roles
4. Add functions to manage worker-role and worker-rig assignments

#### Phase 2: UI Updates

##### HR Module - Worker Management
1. **Edit Worker Page**:
   - Show all assigned roles (multi-select or checkboxes)
   - Mark one as primary
   - Set default rate per role
   - Add "Assign to Rigs" section showing rig preferences

2. **Worker List View**:
   - Show primary role prominently
   - Show secondary roles as badges/tags
   - Show associated rigs

##### Field Report - Payroll Entry
1. **Enhanced Worker Selection**:
   - When rig is selected, suggest workers who typically work on that rig
   - When worker is selected, show all their assigned roles
   - Allow selecting ANY role (even if not in worker's assigned roles) for flexibility
   - Show role-specific default rate

2. **Smart Suggestions**:
   - After selecting rig → suggest workers with rig preference
   - After selecting worker → suggest their roles and rates
   - Allow adding same worker multiple times with different roles (if needed)

### Example Use Cases

#### Use Case 1: Atta (Driller for Kyrie)
- Worker: Atta
- Roles: Driller (primary), Rig Driver (secondary)
- Rig Preferences: Kyrie (primary)
- When creating report for Kyrie → Atta appears in suggestions
- Can select Atta as Driller or Rig Driver

#### Use Case 2: Isaac (Rig Driver/Spanner for Kyrie)
- Worker: Isaac
- Roles: Rig Driver (primary), Spanner Boy (secondary)
- Rig Preferences: Kyrie (primary)
- Can perform either role on Kyrie reports

#### Use Case 3: Worker Moves Between Rigs
- Godwin works on both Kyrie and Green Rig
- Green Rig Preference: primary
- Kyrie Preference: secondary
- When creating report → Godwin appears in suggestions for both rigs
- Can assign to either rig with any role

### Benefits
1. ✅ **Flexibility**: Workers can have multiple roles, work on multiple rigs
2. ✅ **Smart Suggestions**: System suggests relevant workers/roles based on rig selection
3. ✅ **Backward Compatible**: Existing data and code continue to work
4. ✅ **No Enforcement**: Preferences are suggestions, not restrictions
5. ✅ **Role-Specific Rates**: Different rates for different roles
6. ✅ **Historical Accuracy**: Each payroll entry records the actual role performed

### Migration Strategy
1. Create new tables (no data loss)
2. Migrate existing `workers.role` to `worker_role_assignments`
3. Update UI to use new system (optional - can use old system alongside)
4. Gradually migrate to new system

### Questions to Consider
1. Should we allow assigning workers to rigs they're not "preferred" for? **YES** - preferences are suggestions only
2. Should we allow assigning roles not in worker's assigned roles? **YES** - for flexibility
3. Should we track role-specific rates? **YES** - worker may have different rates for different roles
4. Should we show historical role assignments? **YES** - in payroll history

---

## Approval Needed
Please review and approve this approach. Once approved, I'll implement:
1. Database migration script
2. Backend API updates
3. HR module UI updates
4. Field report form enhancements

