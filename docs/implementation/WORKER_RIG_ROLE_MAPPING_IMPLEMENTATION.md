# Worker-Rig-Role Mapping System - Implementation Complete

## Overview
This system allows workers to have multiple roles and track which rigs they typically work on. This enables smart suggestions in the field report form and better worker management.

## Database Schema

### Tables Created
1. **worker_role_assignments** - Many-to-many relationship between workers and roles
   - `id` (PK)
   - `worker_id` (FK to workers)
   - `role_name` (FK to worker_roles)
   - `is_primary` (boolean) - One primary role per worker
   - `default_rate` (decimal) - Role-specific rate
   - `created_at`, `updated_at`

2. **worker_rig_preferences** - Many-to-many relationship between workers and rigs
   - `id` (PK)
   - `worker_id` (FK to workers)
   - `rig_id` (FK to rigs)
   - `preference_level` (enum: primary, secondary, occasional)
   - `notes` (text)
   - `created_at`, `updated_at`

## Migration

Run the migration file to create the tables:
```bash
mysql -u root -p abbis_3_2 < database/worker_rig_role_mapping_migration.sql
```

Or execute it via phpMyAdmin or the HR module migration interface.

## API Endpoints

### Worker Role Assignments (`api/worker-role-assignments.php`)

**GET Actions:**
- `get_worker_roles` - Get all roles for a worker
- `get_available_roles` - Get all available roles (with assignment status)

**POST Actions:**
- `add_role` - Assign a role to a worker
- `update_role` - Update role assignment (primary status, rate)
- `remove_role` - Remove a role assignment

### Worker Rig Preferences (`api/worker-rig-preferences.php`)

**GET Actions:**
- `get_worker_rigs` - Get all rig preferences for a worker
- `get_rig_workers` - Get all workers who work on a rig
- `get_available_rigs` - Get all available rigs (with assignment status)

**POST Actions:**
- `add_preference` - Add rig preference for a worker
- `update_preference` - Update rig preference (level, notes)
- `remove_preference` - Remove rig preference

## UI Features

### HR Module (`modules/hr.php`)

1. **Employee Form Enhancements:**
   - Role Management Section: Display all assigned roles with badges
   - Rig Preferences Section: Display all rig preferences with levels
   - "Manage Roles" button: Opens modal for full CRUD operations
   - "Manage Rig Preferences" button: Opens modal for full CRUD operations

2. **Employee List:**
   - Shows all roles assigned to each worker (with primary indicator)
   - Shows rig codes (up to 2, with "+X more" indicator)
   - Color-coded badges for primary/secondary/occasional

3. **Role Management Modal:**
   - View current roles with primary indicator and rates
   - Add new roles from available roles list
   - Edit role assignment (set as primary, update rate)
   - Remove role assignments

4. **Rig Preferences Modal:**
   - View current rig preferences with preference levels
   - Add new rig preferences
   - Edit preference level and notes
   - Remove rig preferences

### Field Report Form (`assets/js/field-reports.js`)

1. **Smart Worker Suggestions:**
   - When a rig is selected, automatically loads workers who typically work on that rig
   - Suggested workers appear at the top of worker dropdown with "💡 Suggested Workers" optgroup
   - Shows preference level (primary/secondary/occasional) next to worker name
   - All other workers appear below in "All Workers" optgroup

2. **Dynamic Updates:**
   - When rig selection changes, worker dropdowns in all payroll rows are refreshed
   - Maintains existing selections if they still exist
   - Updates both existing rows and new rows added after rig selection

## Backend Integration

### Employee Save Handlers (`modules/hr.php`)

1. **Add Employee:**
   - Creates worker record
   - Automatically creates initial role assignment if role is provided
   - Sets role as primary if it's the first role

2. **Update Employee:**
   - Updates worker record
   - Updates primary role assignment if role changed
   - Creates new role assignment if role doesn't exist
   - Unsets other primary roles when setting new primary

### Helper Class (`includes/worker-mapping-manager.php`)

Provides utility methods:
- `getWorkerRoles($workerId)` - Get all roles for a worker
- `getWorkerRigs($workerId)` - Get all rigs for a worker
- `getRigWorkers($rigId)` - Get all workers for a rig
- `getWorkerFullProfile($workerId)` - Get complete worker profile with roles and rigs
- `getSuggestedWorkersForRig($rigId)` - Get suggested workers for a rig

## Usage Examples

### Assigning Multiple Roles to a Worker

1. Go to HR Module → Employees
2. Click "Edit" on a worker
3. Scroll to "Role Assignments" section
4. Click "Manage Roles"
5. In the modal, click "Add Role" and select a role
6. Optionally set as primary and specify rate
7. Repeat for additional roles

### Setting Rig Preferences

1. Go to HR Module → Employees
2. Click "Edit" on a worker
3. Scroll to "Rig Preferences" section
4. Click "Manage Rig Preferences"
5. In the modal, select a rig and preference level (primary/secondary/occasional)
6. Add optional notes
7. Repeat for additional rigs

### Using Smart Suggestions in Field Reports

1. Create a new field report
2. Select a rig in the "Rig" dropdown
3. When adding payroll entries, suggested workers appear at the top
4. Workers are grouped by preference level
5. You can still select any worker from the full list

## Data Migration

The migration script automatically:
- Creates the new tables
- Migrates existing worker roles to `worker_role_assignments` table
- Sets migrated roles as primary
- Preserves default rates

## Backward Compatibility

- The system maintains backward compatibility with the existing `workers.role` field
- Workers table still has a primary role field
- When a role is set as primary in the new system, it updates the `workers.role` field
- Existing code that reads `workers.role` continues to work

## Notes

- Workers can have multiple roles, but only one can be primary
- Workers can have multiple rig preferences with different preference levels
- Preference levels are informational only - workers can still be assigned to any rig
- The migration includes foreign key constraints with CASCADE delete
- All operations use transactions for data integrity

