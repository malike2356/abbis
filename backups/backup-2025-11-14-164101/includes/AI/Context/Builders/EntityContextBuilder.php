<?php

require_once __DIR__ . '/../ContextBuilderInterface.php';
require_once __DIR__ . '/../ContextSlice.php';
require_once __DIR__ . '/../../../../config/database.php';

class EntityContextBuilder implements ContextBuilderInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    public function getKey(): string
    {
        return 'entity';
    }

    public function supports(array $options): bool
    {
        return !empty($options['entity_type']) && !empty($options['entity_id']);
    }

    public function build(array $options): array
    {
        $type = $options['entity_type'];
        $id = (int) $options['entity_id'];
        $data = null;

        switch ($type) {
            case 'field_report':
                $data = $this->loadFieldReport($id);
                break;
            case 'quote_request':
                $data = $this->loadQuoteRequest($id);
                break;
            case 'rig_request':
                $data = $this->loadRigRequest($id);
                break;
            case 'client':
                $data = $this->loadClient($id);
                break;
        }

        if (!$data) {
            return [];
        }

        return [
            new ContextSlice($type, $data, priority: 30, approxTokens: 400),
        ];
    }

    private function loadFieldReport(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT fr.id, fr.report_date, fr.project_name, fr.location, fr.status, fr.total_income, fr.total_expenses, fr.net_profit,
                   c.client_name
            FROM field_reports fr
            LEFT JOIN clients c ON fr.client_id = c.id
            WHERE fr.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['financial_summary'] = [
            'income' => (float) $row['total_income'],
            'expenses' => (float) $row['total_expenses'],
            'net_profit' => (float) $row['net_profit'],
        ];

        return $row;
    }

    private function loadQuoteRequest(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, email, phone, location, status, estimated_budget, created_at, updated_at
            FROM cms_quote_requests
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['status_history'] = $this->loadStatusHistory('quote', $id);
        return $row;
    }

    private function loadRigRequest(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, request_number, requester_name, location_address, status, priority, estimated_budget, number_of_boreholes, created_at
            FROM rig_requests
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['status_history'] = $this->loadStatusHistory('rig', $id);
        return $row;
    }

    private function loadClient(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, client_name, contact_person, email, phone, industry, city, country, created_at
            FROM clients
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['lifetime_value'] = $this->sumClientValue($id);
        $row['recent_reports'] = $this->fetchRecentFieldReports($id);
        return $row;
    }

    private function loadStatusHistory(string $type, int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT new_status, old_status, note, created_at
            FROM crm_request_status_history
            WHERE request_type = :type AND request_id = :id
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([
            ':type' => $type,
            ':id' => $id,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function sumClientValue(int $clientId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(net_profit), 0) 
            FROM field_reports
            WHERE client_id = :clientId
        ");
        $stmt->bindValue(':clientId', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    private function fetchRecentFieldReports(int $clientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, report_date, project_name, status, net_profit
            FROM field_reports
            WHERE client_id = :clientId
            ORDER BY report_date DESC
            LIMIT 5
        ");
        $stmt->bindValue(':clientId', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


