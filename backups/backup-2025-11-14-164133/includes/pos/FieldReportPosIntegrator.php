<?php

declare(strict_types=1);

require_once __DIR__ . '/PosRepository.php';

class FieldReportPosIntegrator
{
    public static function syncInventory(PDO $pdo, array $payload, ?array $previous = null): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($storeId <= 0) {
            return;
        }

        $repository = new PosRepository($pdo);

        // Handle both legacy 'store' and new 'company_shop' values
        $materialsProvidedBy = $previous['materials_provided_by'] ?? '';
        if ($previous && ($materialsProvidedBy === 'store' || $materialsProvidedBy === 'company_shop') && !empty($previous['store_id'])) {
            $restoreQuantities = self::buildProductUsage($pdo, $previous);
            self::applyAdjustments($repository, $restoreQuantities, [
                'store_id' => (int) $previous['store_id'],
                'reference' => $previous['report_code'] ?? null,
                'report_db_id' => $previous['report_db_id'] ?? null,
                'performed_by' => $payload['performed_by'] ?? null,
                'direction' => 'restore',
            ]);
        }

        // Handle both legacy 'store' and new 'company_shop' values
        $materialsProvidedBy = $payload['materials_provided_by'] ?? '';
        if ($materialsProvidedBy !== 'store' && $materialsProvidedBy !== 'company_shop') {
            return;
        }

        $quantities = self::buildProductUsage($pdo, $payload);
        self::applyAdjustments($repository, $quantities, [
            'store_id' => $storeId,
            'reference' => $payload['report_code'] ?? null,
            'report_db_id' => $payload['report_db_id'] ?? null,
            'performed_by' => $payload['performed_by'] ?? null,
            'direction' => 'consume',
        ]);
    }

    private static function buildProductUsage(PDO $pdo, array $payload): array
    {
        static $mappings = null;

        if ($mappings === null) {
            $stmt = $pdo->query("SELECT material_type, pos_product_id, unit_multiplier FROM pos_material_mappings WHERE is_active = 1");
            $mappings = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        $usage = [];
        $materialCounts = [
            'screen_pipe' => (float) ($payload['screen_pipes_used'] ?? 0),
            'plain_pipe' => (float) ($payload['plain_pipes_used'] ?? 0),
            'gravel' => (float) ($payload['gravel_used'] ?? 0),
        ];

        foreach ($mappings as $mapping) {
            $type = $mapping['material_type'];
            $productId = (int) $mapping['pos_product_id'];
            $multiplier = (float) $mapping['unit_multiplier'];

            if ($productId <= 0 || $multiplier <= 0) {
                continue;
            }

            if (isset($materialCounts[$type])) {
                $quantity = $materialCounts[$type] * $multiplier;
                if ($quantity > 0) {
                    $usage[$productId] = ($usage[$productId] ?? 0) + $quantity;
                }
            }
        }

        return $usage;
    }

    private static function applyAdjustments(PosRepository $repository, array $quantities, array $context): void
    {
        foreach ($quantities as $productId => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $delta = $context['direction'] === 'restore' ? $amount : -$amount;
            $remarks = $context['direction'] === 'restore'
                ? 'Return from field report ' . ($context['reference'] ?? '')
                : 'Field report consumption ' . ($context['reference'] ?? '');

            $repository->adjustStock([
                'store_id' => $context['store_id'],
                'product_id' => (int) $productId,
                'quantity_delta' => $delta,
                'transaction_type' => $context['direction'] === 'restore' ? 'adjustment' : 'transfer_out',
                'reference_type' => 'field_report',
                'reference_id' => $context['report_db_id'],
                'performed_by' => $context['performed_by'],
                'remarks' => $remarks,
            ]);
        }
    }
}


