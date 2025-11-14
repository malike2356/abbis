<?php

declare(strict_types=1);

class EnvironmentalSamplingService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Create or update a sampling project.
     *
     * @param array<string,mixed> $data
     * @return int project id
     */
    public function saveProject(array $data): int
    {
        $projectCode = $data['project_code'] ?? $this->generateProjectCode();
        $projectId = isset($data['id']) ? (int)$data['id'] : 0;

        $payload = [
            ':project_code' => $projectCode,
            ':project_name' => $data['project_name'] ?? null,
            ':client_id' => $data['client_id'] ?? null,
            ':field_report_id' => $data['field_report_id'] ?? null,
            ':site_name' => $data['site_name'] ?? null,
            ':location_address' => $data['location_address'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':sampling_type' => $data['sampling_type'] ?? 'water',
            ':status' => $data['status'] ?? 'draft',
            ':scheduled_date' => $data['scheduled_date'] ?? null,
            ':collected_date' => $data['collected_date'] ?? null,
            ':submitted_to_lab_at' => $data['submitted_to_lab_at'] ?? null,
            ':completed_at' => $data['completed_at'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
            ':notes' => $data['notes'] ?? null,
        ];

        if ($projectId > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE env_sampling_projects
                SET project_code = :project_code,
                    project_name = :project_name,
                    client_id = :client_id,
                    field_report_id = :field_report_id,
                    site_name = :site_name,
                    location_address = :location_address,
                    latitude = :latitude,
                    longitude = :longitude,
                    sampling_type = :sampling_type,
                    status = :status,
                    scheduled_date = :scheduled_date,
                    collected_date = :collected_date,
                    submitted_to_lab_at = :submitted_to_lab_at,
                    completed_at = :completed_at,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $payload[':id'] = $projectId;
            $stmt->execute($payload);
            return $projectId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO env_sampling_projects
            (project_code, project_name, client_id, field_report_id, site_name, location_address, latitude, longitude,
             sampling_type, status, scheduled_date, collected_date, submitted_to_lab_at, completed_at, created_by, notes)
            VALUES
            (:project_code, :project_name, :client_id, :field_report_id, :site_name, :location_address, :latitude, :longitude,
             :sampling_type, :status, :scheduled_date, :collected_date, :submitted_to_lab_at, :completed_at, :created_by, :notes)
        ");
        $stmt->execute($payload);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Create or update sample.
     *
     * @param array<string,mixed> $data
     */
    public function saveSample(array $data): int
    {
        $sampleId = isset($data['id']) ? (int)$data['id'] : 0;
        $sampleCode = $data['sample_code'] ?? $this->generateSampleCode($data['project_id'] ?? null);

        $payload = [
            ':project_id' => (int)$data['project_id'],
            ':sample_code' => $sampleCode,
            ':sample_type' => $data['sample_type'] ?? null,
            ':matrix' => $data['matrix'] ?? 'water',
            ':collection_method' => $data['collection_method'] ?? null,
            ':container_type' => $data['container_type'] ?? null,
            ':preservative' => $data['preservative'] ?? null,
            ':collected_by' => $data['collected_by'] ?? null,
            ':collected_at' => $data['collected_at'] ?? null,
            ':temperature_c' => $data['temperature_c'] ?? null,
            ':weather_notes' => $data['weather_notes'] ?? null,
            ':field_observations' => $data['field_observations'] ?? null,
            ':status' => $data['status'] ?? 'pending',
        ];

        if ($sampleId > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE env_samples
                SET sample_code = :sample_code,
                    sample_type = :sample_type,
                    matrix = :matrix,
                    collection_method = :collection_method,
                    container_type = :container_type,
                    preservative = :preservative,
                    collected_by = :collected_by,
                    collected_at = :collected_at,
                    temperature_c = :temperature_c,
                    weather_notes = :weather_notes,
                    field_observations = :field_observations,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $payload[':id'] = $sampleId;
            $stmt->execute($payload);
            return $sampleId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO env_samples
            (project_id, sample_code, sample_type, matrix, collection_method, container_type, preservative,
             collected_by, collected_at, temperature_c, weather_notes, field_observations, status)
            VALUES
            (:project_id, :sample_code, :sample_type, :matrix, :collection_method, :container_type, :preservative,
             :collected_by, :collected_at, :temperature_c, :weather_notes, :field_observations, :status)
        ");
        $stmt->execute($payload);
        return (int)$this->pdo->lastInsertId();
    }

    public function addChainEntry(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO env_sample_chain
            (sample_id, custody_step, handler_name, handler_role, handler_signature, transfer_action,
             transfer_at, condition_notes, temperature_c, received_by_lab)
            VALUES
            (:sample_id, :custody_step, :handler_name, :handler_role, :handler_signature, :transfer_action,
             :transfer_at, :condition_notes, :temperature_c, :received_by_lab)
        ");
        $stmt->execute([
            ':sample_id' => (int)$data['sample_id'],
            ':custody_step' => $data['custody_step'] ?? $this->nextCustodyStep((int)$data['sample_id']),
            ':handler_name' => $data['handler_name'],
            ':handler_role' => $data['handler_role'] ?? null,
            ':handler_signature' => $data['handler_signature'] ?? null,
            ':transfer_action' => $data['transfer_action'] ?? 'transferred',
            ':transfer_at' => $data['transfer_at'] ?? date('Y-m-d H:i:s'),
            ':condition_notes' => $data['condition_notes'] ?? null,
            ':temperature_c' => $data['temperature_c'] ?? null,
            ':received_by_lab' => isset($data['received_by_lab']) ? (int)$data['received_by_lab'] : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function addLabResult(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO env_lab_results
            (sample_id, parameter_name, parameter_group, parameter_unit, result_value, detection_limit,
             method_reference, analyst_name, analyzed_at, qa_qc_flag, remarks, attachment_path)
            VALUES
            (:sample_id, :parameter_name, :parameter_group, :parameter_unit, :result_value, :detection_limit,
             :method_reference, :analyst_name, :analyzed_at, :qa_qc_flag, :remarks, :attachment_path)
        ");
        $stmt->execute([
            ':sample_id' => (int)$data['sample_id'],
            ':parameter_name' => $data['parameter_name'],
            ':parameter_group' => $data['parameter_group'] ?? null,
            ':parameter_unit' => $data['parameter_unit'] ?? null,
            ':result_value' => $data['result_value'] ?? null,
            ':detection_limit' => $data['detection_limit'] ?? null,
            ':method_reference' => $data['method_reference'] ?? null,
            ':analyst_name' => $data['analyst_name'] ?? null,
            ':analyzed_at' => $data['analyzed_at'] ?? null,
            ':qa_qc_flag' => $data['qa_qc_flag'] ?? 'not_applicable',
            ':remarks' => $data['remarks'] ?? null,
            ':attachment_path' => $data['attachment_path'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Fetch paginated project list.
     *
     * @return array<string,mixed>
     */
    public function listProjects(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $params = [];
        $where = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int)$filters['client_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(p.project_code LIKE :search OR p.project_name LIKE :search OR p.site_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM env_sampling_projects p {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT p.*, c.client_name, fr.report_id,
                   (SELECT COUNT(*) FROM env_samples s WHERE s.project_id = p.id) AS sample_count
            FROM env_sampling_projects p
            LEFT JOIN clients c ON c.id = p.client_id
            LEFT JOIN field_reports fr ON fr.id = p.field_report_id
            {$whereSql}
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Load project with samples and results.
     *
     * @return array<string,mixed>|null
     */
    public function getProject(int $projectId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.client_name, fr.report_id
            FROM env_sampling_projects p
            LEFT JOIN clients c ON c.id = p.client_id
            LEFT JOIN field_reports fr ON fr.id = p.field_report_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) {
            return null;
        }

        $project['samples'] = $this->getSamplesByProject($projectId);
        return $project;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getSamplesByProject(int $projectId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, COUNT(DISTINCT r.id) AS result_count
            FROM env_samples s
            LEFT JOIN env_lab_results r ON r.sample_id = s.id
            WHERE s.project_id = ?
            GROUP BY s.id
            ORDER BY s.collected_at ASC, s.id ASC
        ");
        $stmt->execute([$projectId]);
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($samples as &$sample) {
            $sample['chain'] = $this->getChainBySample((int)$sample['id']);
            $sample['results'] = $this->getResultsBySample((int)$sample['id']);
        }
        return $samples;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getChainBySample(int $sampleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM env_sample_chain
            WHERE sample_id = ?
            ORDER BY custody_step ASC, transfer_at ASC
        ");
        $stmt->execute([$sampleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getResultsBySample(int $sampleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM env_lab_results
            WHERE sample_id = ?
            ORDER BY parameter_group, parameter_name
        ");
        $stmt->execute([$sampleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateProjectStatus(int $projectId, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE env_sampling_projects SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $projectId]);
    }

    public function deleteProject(int $projectId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM env_sampling_projects WHERE id = ?");
        return $stmt->execute([$projectId]);
    }

    private function generateProjectCode(): string
    {
        return 'ENV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function generateSampleCode(?int $projectId = null): string
    {
        $prefix = $projectId ? 'S-' . str_pad((string)$projectId, 4, '0', STR_PAD_LEFT) : 'SMP';
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    private function nextCustodyStep(int $sampleId): int
    {
        $stmt = $this->pdo->prepare("SELECT MAX(custody_step) FROM env_sample_chain WHERE sample_id = ?");
        $stmt->execute([$sampleId]);
        $max = (int)$stmt->fetchColumn();
        return $max + 1;
    }
}

