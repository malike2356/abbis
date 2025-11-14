# POS Phase 1 Design – Product & Procurement Foundations

## 1. Objectives
- Extend the existing POS schema to support rich product metadata.
- Introduce supplier management and the full procurement cycle (PO → GRN → Supplier Invoice).
- Enhance inventory tracking with batches/serials, stock transfers, and automated replenishment triggers.
- Ensure every movement queues the appropriate accounting journal entries.

## 2. Data Model Extensions

Implemented via `database/migrations/pos/003_procurement.sql`.

### 2.1 Product Master
| Table | Purpose | Key Fields |
| --- | --- | --- |
| `pos_brands` | Optional parent brand catalogue. | `id`, `name`, `slug`, `country` |
| `pos_attributes` | Attribute definitions (e.g. color, size). | `id`, `code`, `label`, `data_type`, `is_variant` |
| `pos_product_attributes` | Attribute values per product. | `product_id`, `attribute_id`, `value_text`, `value_number` |
| `pos_product_suppliers` | Preferred supplier references. | `product_id`, `supplier_id`, `lead_time_days`, `preferred` |
| `pos_product_batches` | Batch/serial tracking. | `id`, `product_id`, `batch_code`, `expiry_date`, `initial_cost` |

### 2.2 Supplier & Procurement
| Table | Purpose | Key Fields |
| --- | --- | --- |
| `pos_suppliers` | Supplier master data. | `id`, `code`, `name`, `contact`, `payment_terms`, `currency` |
| `pos_supplier_contacts` | Supplier contact persons. | `supplier_id`, `name`, `phone`, `email` |
| `pos_purchase_orders` | PO header. | `id`, `po_number`, `supplier_id`, `store_id`, `status`, `expected_date` |
| `pos_purchase_order_items` | PO lines. | `po_id`, `product_id`, `ordered_qty`, `unit_cost`, `tax_rate` |
| `pos_goods_receipts` | GRN header. | `id`, `grn_number`, `po_id`, `received_date`, `status` |
| `pos_goods_receipt_items` | GRN lines (supports partial). | `grn_id`, `po_item_id`, `received_qty`, `batch_id` |
| `pos_supplier_invoices` | Supplier invoices. | `id`, `invoice_number`, `grn_id`, `amount`, `tax_amount`, `status` |
| `pos_supplier_invoice_items` | Invoice details (supports accruals). | `invoice_id`, `product_id`, `billed_qty`, `amount` |

### 2.3 Inventory Movements
| Table | Purpose | Key Fields |
| --- | --- | --- |
| `pos_stock_transfers` | Inter-store transfers. | `id`, `transfer_number`, `source_store_id`, `dest_store_id`, `status` |
| `pos_stock_transfer_items` | Transfer lines. | `transfer_id`, `product_id`, `requested_qty`, `shipped_qty`, `received_qty` |
| `pos_reorder_rules` | Min/max & EOQ thresholds. | `product_id`, `store_id`, `reorder_point`, `reorder_qty`, `safety_stock`, `vendor_id` |

### 2.4 Cash Management (Phase 2 scaffolding)
For completeness, Phase 1 will only create base tables to unblock later work:
| Table | Purpose | Key Fields |
| --- | --- | --- |
| `pos_cash_sessions` | Cash drawer shifts. | `id`, `store_id`, `opened_by`, `opened_at`, `status` |
| `pos_cash_movements` | Cash in/out entries. | `session_id`, `movement_type`, `amount`, `reason`, `reference_type/id` |

## 3. Workflow Overview

Core operations now sit in `includes/pos/ProcurementService.php`.

1. **Create PO** → `pending_approval` → approved by manager.
2. **Receive goods** → create GRN (partial receipts allowed); inventory increments, batches recorded.
3. **Supplier invoice** matches GRN quantities/costs; difference flagged for review.
4. **Landed cost allocation** (optional Phase 1.5) distributes freight/duty across batches.
5. **Accounting queue**: each step pushes a journal payload (`purchase_accrual`, `inventory_adjustment`, `supplier_payable`).

## 4. API & UI Touchpoints

- **Backoffice screens** (new `modules/pos/` pages):
  - Products → advanced detail tab (attributes, vendor links, reorder settings).
  - Suppliers list / detail.
  - Purchase Orders (list, create, approve).
  - Goods Receipts (create from PO, record batches).
  - Supplier Invoices (match GRNs, post to accounting).
- **REST endpoints** under `pos/api/` mirroring the above operations with full validation & permission checks.

## 5. Accounting Integration

| Event | Debit | Credit |
| --- | --- | --- |
| PO approval | (no entry) | (no entry) |
| GRN (not yet invoiced) | Inventory (product) | GRN clearing (liability) |
| Supplier invoice | GRN clearing | Accounts payable (supplier) |
| Price variance | Price variance expense | GRN clearing (if invoice cost > GRN) |
| Stock adjustment - damage | Shrinkage expense | Inventory |
| Stock transfer (source) | Transfer-out expense / in-transit | Inventory |
| Stock transfer (destination) | Inventory | Transfer-in clearing |

Events enqueue payloads for the existing `pos_accounting_queue`.

## 6. Next Steps

1. Finalise ER diagram & migrations.
2. Update seeding scripts (base suppliers, reorder templates).
3. Implement repository/service layers for suppliers, POs, GRNs, invoices.
4. Build UI forms & validations.
5. Extend accounting queue processor to recognise the new transaction types.
6. Update tests & documentation.

This document will evolve as we refine requirements during implementation sprints.


