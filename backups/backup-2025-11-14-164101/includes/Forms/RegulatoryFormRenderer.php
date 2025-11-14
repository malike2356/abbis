<?php

declare(strict_types=1);

class RegulatoryFormRenderer
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Render a regulatory form template using provided reference.
     *
     * @param array<string,mixed> $template
     * @param string $referenceType
     * @param int|null $referenceId
     * @return array<string,mixed>
     */
    public function render(array $template, string $referenceType, ?int $referenceId, array $context = []): array
    {
        $dataSets = $this->buildDataSets($referenceType, $referenceId, $context);
        $html = $this->mergeTemplate($template['html_template'], $dataSets);

        return [
            'html' => $html,
            'datasets' => $dataSets,
        ];
    }

    /**
     * @param array<string,mixed> $dataSets
     */
    private function mergeTemplate(string $template, array $dataSets): string
    {
        $replacements = [];
        $this->flattenData($dataSets, '', $replacements);

        $output = strtr($template, $replacements);
        $output = preg_replace('/\{\{[a-zA-Z0-9\._]+\}\}/', '&mdash;', (string)$output);

        return $output;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $replacements
     */
    private function flattenData(array $data, string $prefix, array &$replacements): void
    {
        foreach ($data as $key => $value) {
            $placeholder = trim($prefix . $key, '.');
            if (is_array($value)) {
                $this->flattenData($value, $placeholder . '.', $replacements);
            } else {
                $replacements['{{' . $placeholder . '}}'] = $this->formatValue($value);
            }
        }
    }

    private function formatValue($value): string
    {
        if (is_null($value)) {
            return '';
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.2f', $value), '0'), '.');
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return implode(', ', array_filter(array_map([$this, 'formatValue'], $value)));
        }
        return (string)$value;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDataSets(string $referenceType, ?int $referenceId, array $context): array
    {
        $data = [
            'generated_at' => date('Y-m-d H:i'),
            'company' => $this->getCompanyConfig(),
        ];

        if ($referenceType === 'field_report' && $referenceId) {
            $data['field_report'] = $this->loadFieldReport($referenceId);
        } elseif ($referenceType === 'rig' && $referenceId) {
            $data['rig'] = $this->loadRig($referenceId);
        } elseif ($referenceType === 'client' && $referenceId) {
            $data['client'] = $this->loadClient($referenceId);
        }

        if (!empty($context)) {
            $data['context'] = $context;
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function getCompanyConfig(): array
    {
        $config = [];
        try {
            $stmt = $this->pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key LIKE 'company_%'");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
        } catch (PDOException $e) {
            // ignore
        }
        return $config;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadFieldReport(int $id): ?array
    {
        $sql = "
            SELECT fr.*, c.client_name, c.contact_person, c.contact_number, c.address,
                   r.rig_name, r.rig_code
            FROM field_reports fr
            LEFT JOIN clients c ON c.id = fr.client_id
            LEFT JOIN rigs r ON r.id = fr.rig_id
            WHERE fr.id = ?
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($report) {
            $report['report_date_formatted'] = $report['report_date'] ? date('Y-m-d', strtotime((string)$report['report_date'])) : null;
        }

        return $report ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadRig(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rigs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadClient(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

