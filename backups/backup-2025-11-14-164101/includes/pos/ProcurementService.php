<?php

declare(strict_types=1);

require_once __DIR__ . '/PosRepository.php';
require_once __DIR__ . '/PosValidator.php';

class ProcurementService
{
    private PDO $pdo;
    private PosRepository $repository;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->repository = new PosRepository($this->pdo);
    }

    public function createSupplier(array $payload): int
    {
        $data = PosValidator::validateSupplier($payload);
        return $this->repository->createSupplier($data);
    }

    public function updateSupplier(int $supplierId, array $payload): void
    {
        $data = PosValidator::validateSupplier($payload, true);
        $this->repository->updateSupplier($supplierId, $data);
    }

    public function createPurchaseOrder(array $payload): array
    {
        $validated = PosValidator::validatePurchaseOrder($payload);
        $header = $validated['header'];
        $items = $validated['items'];

        if (!$header['po_number']) {
            $header['po_number'] = $this->generateCode('PO', 'pos_purchase_orders', 'po_number');
        }

        $this->pdo->beginTransaction();
        try {
            $poId = $this->repository->createPurchaseOrder($header);
            foreach ($items as $item) {
                $this->repository->addPurchaseOrderItem($poId, $item);
            }
            $this->pdo->commit();
            return ['po_id' => $poId, 'po_number' => $header['po_number']];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function approvePurchaseOrder(int $poId, int $userId): void
    {
        $this->repository->updatePurchaseOrderStatus($poId, 'approved', $userId);
    }

    public function recordGoodsReceipt(array $payload): array
    {
        $validated = PosValidator::validateGoodsReceipt($payload);
        $header = $validated['header'];
        $items = $validated['items'];

        if (!$header['grn_number']) {
            $header['grn_number'] = $this->generateCode('GRN', 'pos_goods_receipts', 'grn_number');
        }

        $this->pdo->beginTransaction();
        try {
            $grnId = $this->repository->createGoodsReceipt($header);

            foreach ($items as $item) {
                $this->repository->addGoodsReceiptItem($grnId, $item);

                if ($item['received_qty'] > 0) {
                    $this->repository->incrementPurchaseOrderItemReceived($item['po_item_id'], $item['received_qty']);
                    $this->repository->adjustStock([
                        'store_id' => $header['store_id'],
                        'product_id' => $item['product_id'],
                        'quantity_delta' => $item['received_qty'],
                        'transaction_type' => 'purchase',
                        'reference_type' => 'grn',
                        'reference_id' => $grnId,
                        'unit_cost' => $item['unit_cost'],
                        'remarks' => 'Goods receipt ' . $header['grn_number'],
                        'performed_by' => $header['received_by'],
                    ]);
                }
            }

            $this->repository->updateGoodsReceiptStatus($grnId, 'completed');
            $this->repository->refreshPurchaseOrderStatus($header['po_id']);

            $this->pdo->commit();

            $this->repository->queueAccountingPayload(
                referenceType: 'goods_receipt',
                referenceId: $grnId,
                payload: [
                    'grn_id' => $grnId,
                    'po_id' => $header['po_id'],
                    'store_id' => $header['store_id'],
                    'items' => $items,
                ],
                saleId: null
            );

            return ['grn_id' => $grnId, 'grn_number' => $header['grn_number']];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createSupplierInvoice(array $payload): array
    {
        $validated = PosValidator::validateSupplierInvoice($payload);
        $header = $validated['header'];
        $items = $validated['items'];

        if (!$header['invoice_number']) {
            $header['invoice_number'] = $this->generateCode('INV', 'pos_supplier_invoices', 'invoice_number');
        }

        $this->pdo->beginTransaction();
        try {
            $invoiceId = $this->repository->createSupplierInvoice($header);
            foreach ($items as $item) {
                $this->repository->addSupplierInvoiceItem($invoiceId, $item);
                if (!empty($item['po_item_id'])) {
                    $this->repository->incrementPurchaseOrderItemBilled($item['po_item_id'], $item['quantity']);
                }
            }

            $this->repository->updateSupplierInvoiceTotals($invoiceId);
            $this->repository->updateSupplierInvoiceStatus($invoiceId, 'pending_approval');
            if (!empty($header['po_id'])) {
                $this->repository->refreshPurchaseOrderStatus($header['po_id']);
            }
            $this->pdo->commit();

            $invoiceRecord = $this->repository->getSupplierInvoice($invoiceId);

            $this->repository->queueAccountingPayload(
                referenceType: 'supplier_invoice',
                referenceId: $invoiceId,
                payload: [
                    'invoice_id' => $invoiceId,
                    'supplier_id' => $header['supplier_id'],
                    'store_id' => $header['store_id'],
                    'items' => $items,
                    'totals' => [
                        'subtotal' => $invoiceRecord['subtotal_amount'] ?? 0,
                        'tax' => $invoiceRecord['tax_amount'] ?? 0,
                        'total' => $invoiceRecord['total_amount'] ?? 0,
                    ],
                ],
                saleId: null
            );

            return ['invoice_id' => $invoiceId, 'invoice_number' => $header['invoice_number']];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function generateCode(string $prefix, string $table, string $column): string
    {
        do {
            $code = sprintf('%s-%s-%04d', strtoupper($prefix), date('Ymd'), random_int(0, 9999));
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :code");
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetchColumn() > 0);

        return $code;
    }
}


