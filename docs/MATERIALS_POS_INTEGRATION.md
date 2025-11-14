# Materials-POS Integration Architecture

## Overview
Bidirectional sync between Materials Management and POS System for company operations.

## Workflow

### 1. Company Sale → Materials Deduction
**Flow:**
- POS user selects company as customer
- Completes sale with materials (screen_pipe, plain_pipe, gravel)
- System automatically deducts quantities from `materials_inventory`
- Records transaction in `inventory_transactions` with reference to sale

### 2. Materials Return Request
**Flow:**
- Materials manager clicks "Return Materials" button
- Creates return request in `pos_material_returns` table
- Status: `pending`
- Notification appears in POS dashboard

### 3. POS Return Acceptance
**Flow:**
- POS user sees notification in dashboard
- Reviews return request (quantities, materials)
- Verifies quantity and quality
- Accepts or rejects return
- If accepted:
  - Updates `materials_inventory.quantity_remaining`
  - Records transaction in `inventory_transactions`
  - Updates POS inventory if linked
  - Logs activity with remarks

### 4. Direct Return from POS
**Flow:**
- POS user can initiate return directly
- Selects materials and quantities
- Records return transaction
- Updates materials inventory immediately

## Database Schema

### pos_material_returns
- id, request_number, material_type, quantity, status, requested_by, requested_at
- accepted_by, accepted_at, remarks, pos_sale_id (if linked to sale)

### Material-POS Product Mapping
- Link materials_inventory.material_type to catalog_items/pos_products
- Map: screen_pipe → PVC Pipe (Screen), plain_pipe → PVC Pipe (Plain), gravel → Gravels

## Implementation Phases

1. **Database & API** - Return request system
2. **Auto-deduction** - On company sales
3. **POS Notifications** - Dashboard alerts
4. **Return Workflow** - Acceptance UI
5. **Activity Logging** - Transaction history

