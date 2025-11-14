<?php

declare(strict_types=1);

class PosValidator
{
    public static function validateProductPayload(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (empty($data['sku'])) {
            $errors[] = 'SKU is required.';
        }
        if (empty($data['name'])) {
            $errors[] = 'Product name is required.';
        }
        if (!isset($data['unit_price']) || !is_numeric($data['unit_price'])) {
            $errors[] = 'Unit price must be provided.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'sku' => trim((string) $data['sku']),
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'barcode' => !empty($data['barcode']) ? trim((string) $data['barcode']) : null,
            'unit_price' => number_format((float) $data['unit_price'], 2, '.', ''),
            'cost_price' => isset($data['cost_price']) ? number_format((float) $data['cost_price'], 2, '.', '') : null,
            'tax_rate' => isset($data['tax_rate']) ? number_format((float) $data['tax_rate'], 2, '.', '') : null,
            'track_inventory' => !empty($data['track_inventory']),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'expose_to_shop' => array_key_exists('expose_to_shop', $data) ? (bool) $data['expose_to_shop'] : false,
        ];
    }

    public static function validateInventoryAdjustment(array $data): array
    {
        $required = ['store_id', 'product_id', 'quantity_delta', 'transaction_type'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        return [
            'store_id' => (int) $data['store_id'],
            'product_id' => (int) $data['product_id'],
            'quantity_delta' => (float) $data['quantity_delta'],
            'transaction_type' => (string) $data['transaction_type'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'unit_cost' => isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
            'remarks' => $data['remarks'] ?? null,
            'performed_by' => isset($data['performed_by']) ? (int) $data['performed_by'] : null,
        ];
    }

    public static function validateSalePayload(array $data): array
    {
        $required = ['store_id', 'cashier_id', 'items', 'subtotal_amount', 'total_amount', 'payments'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing sale field: {$field}");
            }
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new InvalidArgumentException('A sale requires at least one item.');
        }

        if (!is_array($data['payments']) || count($data['payments']) === 0) {
            throw new InvalidArgumentException('At least one payment record is required.');
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || !isset($item['unit_price'])) {
                throw new InvalidArgumentException('Each sale item requires product_id, quantity, and unit_price.');
            }
            $items[] = [
                'product_id' => (int) $item['product_id'],
                'description' => $item['description'] ?? null,
                'quantity' => (float) $item['quantity'],
                'unit_price' => number_format((float) $item['unit_price'], 2, '.', ''),
                'discount_amount' => isset($item['discount_amount']) ? number_format((float) $item['discount_amount'], 2, '.', '') : 0,
                'tax_amount' => isset($item['tax_amount']) ? number_format((float) $item['tax_amount'], 2, '.', '') : 0,
                'line_total' => isset($item['line_total']) ? number_format((float) $item['line_total'], 2, '.', '') : number_format((float) $item['quantity'] * (float) $item['unit_price'], 2, '.', ''),
                'cost_amount' => isset($item['cost_amount']) ? number_format((float) $item['cost_amount'], 2, '.', '') : null,
                'inventory_impact' => !empty($item['inventory_impact']),
            ];
        }

        $payments = [];
        foreach ($data['payments'] as $payment) {
            if (empty($payment['payment_method']) || !isset($payment['amount'])) {
                throw new InvalidArgumentException('Each payment requires payment_method and amount.');
            }
            $payments[] = [
                'payment_method' => (string) $payment['payment_method'],
                'amount' => number_format((float) $payment['amount'], 2, '.', ''),
                'reference' => $payment['reference'] ?? null,
                'received_at' => $payment['received_at'] ?? null,
            ];
        }

        return [
            'store_id' => (int) $data['store_id'],
            'cashier_id' => (int) $data['cashier_id'],
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'customer_name' => $data['customer_name'] ?? null,
            'sale_status' => $data['sale_status'] ?? 'completed',
            'payment_status' => $data['payment_status'] ?? 'paid',
            'subtotal_amount' => number_format((float) $data['subtotal_amount'], 2, '.', ''),
            'discount_total' => isset($data['discount_total']) ? number_format((float) $data['discount_total'], 2, '.', '') : 0,
            'tax_total' => isset($data['tax_total']) ? number_format((float) $data['tax_total'], 2, '.', '') : 0,
            'total_amount' => number_format((float) $data['total_amount'], 2, '.', ''),
            'amount_paid' => isset($data['amount_paid']) ? number_format((float) $data['amount_paid'], 2, '.', '') : number_format((float) $data['total_amount'], 2, '.', ''),
            'change_due' => isset($data['change_due']) ? number_format((float) $data['change_due'], 2, '.', '') : 0,
            'notes' => $data['notes'] ?? null,
            'items' => $items,
            'payments' => $payments,
        ];
    }

    public static function validateStorePayload(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (empty($data['store_code'])) {
            $errors[] = 'Store code is required.';
        }

        if (empty($data['store_name'])) {
            $errors[] = 'Store name is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'store_code' => strtoupper(trim((string) $data['store_code'])),
            'store_name' => trim((string) $data['store_name']),
            'location' => isset($data['location']) ? trim((string) $data['location']) : null,
            'contact_phone' => isset($data['contact_phone']) ? trim((string) $data['contact_phone']) : null,
            'contact_email' => isset($data['contact_email']) ? trim((string) $data['contact_email']) : null,
            'is_primary' => !empty($data['is_primary']),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
    }

    public static function validateCategoryPayload(array $data): array
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Category name is required.');
        }

        return [
            'name' => trim((string) $data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'parent_id' => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
    }

    public static function validateSupplier(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (empty($data['code']) && !$isUpdate) {
            $errors[] = 'Supplier code is required.';
        }
        if (empty($data['name'])) {
            $errors[] = 'Supplier name is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $code = !empty($data['code'])
            ? strtoupper(trim((string) $data['code']))
            : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $data['name']), 0, 8));

        return [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'email' => isset($data['email']) ? trim((string) $data['email']) : null,
            'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
            'payment_terms' => isset($data['payment_terms']) ? trim((string) $data['payment_terms']) : null,
            'currency' => isset($data['currency']) ? strtoupper(trim((string) $data['currency'])) : null,
            'tax_number' => isset($data['tax_number']) ? trim((string) $data['tax_number']) : null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
    }

    public static function validatePurchaseOrder(array $payload): array
    {
        $errors = [];
        if (empty($payload['supplier_id'])) {
            $errors[] = 'Supplier is required.';
        }
        if (empty($payload['store_id'])) {
            $errors[] = 'Store is required.';
        }
        if (empty($payload['items']) || !is_array($payload['items'])) {
            $errors[] = 'At least one purchase order item is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $sanitizedItems = [];
        foreach ($payload['items'] as $item) {
            if (empty($item['product_id'])) {
                throw new InvalidArgumentException('Purchase order item requires product_id.');
            }
            $qty = isset($item['ordered_qty']) ? (float) $item['ordered_qty'] : 0;
            if ($qty <= 0) {
                throw new InvalidArgumentException('Ordered quantity must be greater than zero.');
            }
            $cost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : 0;
            $sanitizedItems[] = [
                'product_id' => (int) $item['product_id'],
                'description' => $item['description'] ?? null,
                'ordered_qty' => $qty,
                'unit_cost' => number_format($cost, 4, '.', ''),
                'tax_rate' => isset($item['tax_rate']) ? number_format((float) $item['tax_rate'], 3, '.', '') : null,
                'discount_percent' => isset($item['discount_percent']) ? number_format((float) $item['discount_percent'], 3, '.', '') : null,
                'expected_date' => !empty($item['expected_date']) ? $item['expected_date'] : null,
            ];
        }

        $header = [
            'po_number' => !empty($payload['po_number']) ? trim((string) $payload['po_number']) : null,
            'supplier_id' => (int) $payload['supplier_id'],
            'store_id' => (int) $payload['store_id'],
            'status' => $payload['status'] ?? 'draft',
            'expected_date' => !empty($payload['expected_date']) ? $payload['expected_date'] : null,
            'payment_terms' => $payload['payment_terms'] ?? null,
            'currency' => isset($payload['currency']) ? strtoupper(trim((string) $payload['currency'])) : null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $payload['created_by'] ?? null,
        ];

        return ['header' => $header, 'items' => $sanitizedItems];
    }

    public static function validateGoodsReceipt(array $payload): array
    {
        $errors = [];
        if (empty($payload['po_id'])) {
            $errors[] = 'Purchase order is required.';
        }
        if (empty($payload['store_id'])) {
            $errors[] = 'Store is required.';
        }
        if (empty($payload['received_by'])) {
            $errors[] = 'Received by user is required.';
        }
        if (empty($payload['items']) || !is_array($payload['items'])) {
            $errors[] = 'At least one goods receipt item is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $sanitizedItems = [];
        foreach ($payload['items'] as $item) {
            if (empty($item['po_item_id']) || empty($item['product_id'])) {
                throw new InvalidArgumentException('Goods receipt items require po_item_id and product_id.');
            }
            $qty = isset($item['received_qty']) ? (float) $item['received_qty'] : 0;
            if ($qty < 0) {
                throw new InvalidArgumentException('Received quantity cannot be negative.');
            }
            $sanitizedItems[] = [
                'po_item_id' => (int) $item['po_item_id'],
                'product_id' => (int) $item['product_id'],
                'received_qty' => $qty,
                'rejected_qty' => isset($item['rejected_qty']) ? (float) $item['rejected_qty'] : 0,
                'unit_cost' => number_format((float) ($item['unit_cost'] ?? 0), 4, '.', ''),
                'batch_code' => $item['batch_code'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
            ];
        }

        $header = [
            'grn_number' => !empty($payload['grn_number']) ? trim((string) $payload['grn_number']) : null,
            'po_id' => (int) $payload['po_id'],
            'store_id' => (int) $payload['store_id'],
            'received_by' => (int) $payload['received_by'],
            'received_at' => !empty($payload['received_at']) ? $payload['received_at'] : null,
            'status' => $payload['status'] ?? 'draft',
            'notes' => $payload['notes'] ?? null,
        ];

        return ['header' => $header, 'items' => $sanitizedItems];
    }

    public static function validateSupplierInvoice(array $payload): array
    {
        $errors = [];
        if (empty($payload['supplier_id'])) {
            $errors[] = 'Supplier is required.';
        }
        if (empty($payload['store_id'])) {
            $errors[] = 'Store is required.';
        }
        if (empty($payload['invoice_date'])) {
            $errors[] = 'Invoice date is required.';
        }
        if (empty($payload['items']) || !is_array($payload['items'])) {
            $errors[] = 'At least one invoice item is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $sanitizedItems = [];
        foreach ($payload['items'] as $item) {
            if (empty($item['product_id'])) {
                throw new InvalidArgumentException('Invoice item requires product_id.');
            }
            $qty = isset($item['quantity']) ? (float) $item['quantity'] : 0;
            if ($qty <= 0) {
                throw new InvalidArgumentException('Invoice quantity must be greater than zero.');
            }
            $cost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : 0;
            $sanitizedItems[] = [
                'po_item_id' => !empty($item['po_item_id']) ? (int) $item['po_item_id'] : null,
                'product_id' => (int) $item['product_id'],
                'quantity' => $qty,
                'unit_cost' => number_format($cost, 4, '.', ''),
                'tax_rate' => isset($item['tax_rate']) ? number_format((float) $item['tax_rate'], 3, '.', '') : null,
                'line_total' => number_format($qty * $cost, 2, '.', ''),
                'description' => $item['description'] ?? null,
            ];
        }

        $header = [
            'invoice_number' => !empty($payload['invoice_number']) ? trim((string) $payload['invoice_number']) : null,
            'supplier_id' => (int) $payload['supplier_id'],
            'store_id' => (int) $payload['store_id'],
            'po_id' => !empty($payload['po_id']) ? (int) $payload['po_id'] : null,
            'grn_id' => !empty($payload['grn_id']) ? (int) $payload['grn_id'] : null,
            'invoice_date' => $payload['invoice_date'],
            'due_date' => $payload['due_date'] ?? null,
            'status' => $payload['status'] ?? 'draft',
            'currency' => isset($payload['currency']) ? strtoupper(trim((string) $payload['currency'])) : null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $payload['created_by'] ?? null,
        ];

        return ['header' => $header, 'items' => $sanitizedItems];
    }

    public static function validateRefundPayload(array $data): array
    {
        if (empty($data['original_sale_id'])) {
            throw new InvalidArgumentException('Original sale ID is required');
        }
        if (empty($data['store_id'])) {
            throw new InvalidArgumentException('Store ID is required');
        }
        if (empty($data['total_amount']) || !is_numeric($data['total_amount'])) {
            throw new InvalidArgumentException('Total refund amount is required');
        }
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException('Refund items are required');
        }

        $sanitizedItems = [];
        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || empty($item['unit_price'])) {
                continue; // Skip invalid items
            }
            $sanitizedItems[] = [
                'original_sale_item_id' => (int)($item['original_sale_item_id'] ?? 0),
                'product_id' => (int)$item['product_id'],
                'quantity' => (float)$item['quantity'],
                'unit_price' => number_format((float)$item['unit_price'], 2, '.', ''),
                'tax_amount' => isset($item['tax_amount']) ? number_format((float)$item['tax_amount'], 2, '.', '') : 0,
                'line_total' => number_format((float)$item['line_total'] ?? ((float)$item['unit_price'] * (float)$item['quantity']), 2, '.', ''),
                'restore_inventory' => !empty($item['restore_inventory']),
            ];
        }

        if (empty($sanitizedItems)) {
            throw new InvalidArgumentException('At least one valid refund item is required');
        }

        return [
            'original_sale_id' => (int)$data['original_sale_id'],
            'store_id' => (int)$data['store_id'],
            'customer_id' => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
            'customer_name' => !empty($data['customer_name']) ? trim((string)$data['customer_name']) : null,
            'refund_type' => in_array($data['refund_type'] ?? 'full', ['full', 'partial'], true) ? $data['refund_type'] : 'full',
            'refund_reason' => !empty($data['refund_reason']) ? trim((string)$data['refund_reason']) : null,
            'subtotal_amount' => number_format((float)($data['subtotal_amount'] ?? 0), 2, '.', ''),
            'tax_total' => number_format((float)($data['tax_total'] ?? 0), 2, '.', ''),
            'total_amount' => number_format((float)$data['total_amount'], 2, '.', ''),
            'refund_method' => in_array($data['refund_method'] ?? 'original_method', ['cash', 'card', 'mobile_money', 'store_credit', 'original_method'], true) 
                ? $data['refund_method'] : 'original_method',
            'refund_status' => in_array($data['refund_status'] ?? 'completed', ['pending', 'completed', 'cancelled'], true) 
                ? $data['refund_status'] : 'completed',
            'notes' => !empty($data['notes']) ? trim((string)$data['notes']) : null,
            'items' => $sanitizedItems,
        ];
    }
}


