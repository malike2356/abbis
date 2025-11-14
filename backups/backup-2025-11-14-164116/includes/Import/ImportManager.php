<?php
/**
 * CSV Import Manager for ABBIS onboarding datasets.
 *
 * Provides dataset definitions, preview utilities, and import execution that
 * support the onboarding wizard, API endpoints, and CLI tooling.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

class ImportManager
{
    private PDO $pdo;
    private int $previewSampleLimit = 25;
    private string $currentDelimiter = ',';

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Return dataset definitions keyed by dataset slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getDefinitions(): array
    {
        return [
            'clients' => [
                'label' => 'Clients',
                'icon' => '🤝',
                'table' => 'clients',
                'primary_key' => 'id',
                'description' => 'Client names, contacts, and addresses used across CRM, quotes, and field reports.',
                'unique_sets' => [
                    ['client_name', 'contact_number'],
                    ['client_name', 'email'],
                    ['client_name'],
                ],
                'fields' => [
                    'client_name' => [
                        'label' => 'Client Name',
                        'db_column' => 'client_name',
                        'required' => true,
                        'type' => 'string',
                    ],
                    'contact_person' => [
                        'label' => 'Contact Person',
                        'db_column' => 'contact_person',
                        'type' => 'string',
                    ],
                    'contact_number' => [
                        'label' => 'Phone Number',
                        'db_column' => 'contact_number',
                        'type' => 'phone',
                    ],
                    'email' => [
                        'label' => 'Email',
                        'db_column' => 'email',
                        'type' => 'email',
                    ],
                    'address' => [
                        'label' => 'Address / Location',
                        'db_column' => 'address',
                        'type' => 'string',
                    ],
                ],
            ],
            'rigs' => [
                'label' => 'Rigs & Equipment',
                'icon' => '🚜',
                'table' => 'rigs',
                'primary_key' => 'id',
                'description' => 'Rig codes, truck models, and status values used in scheduling and field reports.',
                'unique_sets' => [
                    ['rig_code'],
                    ['rig_name'],
                ],
                'fields' => [
                    'rig_name' => [
                        'label' => 'Rig Name',
                        'db_column' => 'rig_name',
                        'required' => true,
                        'type' => 'string',
                    ],
                    'rig_code' => [
                        'label' => 'Rig Code',
                        'db_column' => 'rig_code',
                        'required' => true,
                        'type' => 'slug',
                    ],
                    'truck_model' => [
                        'label' => 'Truck Model',
                        'db_column' => 'truck_model',
                        'type' => 'string',
                    ],
                    'registration_number' => [
                        'label' => 'Registration Number',
                        'db_column' => 'registration_number',
                        'type' => 'string',
                    ],
                    'status' => [
                        'label' => 'Status (active / inactive / maintenance)',
                        'db_column' => 'status',
                        'type' => 'enum',
                        'allowed_values' => ['active', 'inactive', 'maintenance'],
                        'default' => 'active',
                    ],
                ],
            ],
            'workers' => [
                'label' => 'Workers',
                'icon' => '👷',
                'table' => 'workers',
                'primary_key' => 'id',
                'description' => 'Worker roster with roles, default rates, and contact details.',
                'unique_sets' => [
                    ['worker_name', 'role'],
                    ['worker_name', 'contact_number'],
                    ['worker_name'],
                ],
                'fields' => [
                    'worker_name' => [
                        'label' => 'Worker Name',
                        'db_column' => 'worker_name',
                        'required' => true,
                        'type' => 'string',
                    ],
                    'role' => [
                        'label' => 'Role',
                        'db_column' => 'role',
                        'required' => true,
                        'type' => 'string',
                    ],
                    'default_rate' => [
                        'label' => 'Default Rate',
                        'db_column' => 'default_rate',
                        'type' => 'decimal',
                        'default' => 0,
                    ],
                    'contact_number' => [
                        'label' => 'Phone Number',
                        'db_column' => 'contact_number',
                        'type' => 'phone',
                    ],
                    'status' => [
                        'label' => 'Status (active / inactive)',
                        'db_column' => 'status',
                        'type' => 'enum',
                        'allowed_values' => ['active', 'inactive'],
                        'default' => 'active',
                    ],
                ],
            ],
            'catalog_items' => [
                'label' => 'Catalog Items',
                'icon' => '🗂️',
                'table' => 'catalog_items',
                'primary_key' => 'id',
                'description' => 'Products and services used across materials, POS, and quotation flows.',
                'unique_sets' => [
                    ['sku'],
                    ['name'],
                ],
                'fields' => [
                    'name' => [
                        'label' => 'Item Name',
                        'db_column' => 'name',
                        'required' => true,
                        'type' => 'string',
                    ],
                    'sku' => [
                        'label' => 'SKU / Code',
                        'db_column' => 'sku',
                        'type' => 'string',
                    ],
                    'item_type' => [
                        'label' => 'Item Type (product / service)',
                        'db_column' => 'item_type',
                        'type' => 'enum',
                        'allowed_values' => ['product', 'service'],
                        'default' => 'product',
                    ],
                    'category_name' => [
                        'label' => 'Category Name',
                        'type' => 'lookup_category',
                        'target_column' => 'category_id',
                    ],
                    'category_id' => [
                        'label' => 'Category ID',
                        'db_column' => 'category_id',
                        'type' => 'integer',
                    ],
                    'unit' => [
                        'label' => 'Unit (e.g. pcs, hrs, m)',
                        'db_column' => 'unit',
                        'type' => 'string',
                    ],
                    'cost_price' => [
                        'label' => 'Cost Price',
                        'db_column' => 'cost_price',
                        'type' => 'decimal',
                        'default' => 0,
                    ],
                    'sell_price' => [
                        'label' => 'Sell Price',
                        'db_column' => 'sell_price',
                        'type' => 'decimal',
                        'default' => 0,
                    ],
                    'taxable' => [
                        'label' => 'Taxable (yes/no)',
                        'db_column' => 'taxable',
                        'type' => 'boolean',
                        'default' => 0,
                    ],
                    'is_purchasable' => [
                        'label' => 'Purchasable (yes/no)',
                        'db_column' => 'is_purchasable',
                        'type' => 'boolean',
                        'default' => 1,
                    ],
                    'is_sellable' => [
                        'label' => 'Sellable (yes/no)',
                        'db_column' => 'is_sellable',
                        'type' => 'boolean',
                        'default' => 1,
                    ],
                    'is_active' => [
                        'label' => 'Active (yes/no)',
                        'db_column' => 'is_active',
                        'type' => 'boolean',
                        'default' => 1,
                    ],
                    'notes' => [
                        'label' => 'Notes',
                        'db_column' => 'notes',
                        'type' => 'string',
                    ],
                ],
            ],
            'geology_wells' => [
                'label' => 'Geology Wells',
                'icon' => '🛢️',
                'table' => 'geology_wells',
                'primary_key' => 'id',
                'description' => 'Historical well logs used to power the geology estimator (depth, lithology, aquifer data).',
                'unique_sets' => [
                    ['reference_code'],
                    ['latitude', 'longitude'],
                ],
                'fields' => [
                    'reference_code' => [
                        'label' => 'Reference Code',
                        'db_column' => 'reference_code',
                        'type' => 'string',
                    ],
                    'latitude' => [
                        'label' => 'Latitude',
                        'db_column' => 'latitude',
                        'required' => true,
                        'type' => 'decimal',
                    ],
                    'longitude' => [
                        'label' => 'Longitude',
                        'db_column' => 'longitude',
                        'required' => true,
                        'type' => 'decimal',
                    ],
                    'region' => [
                        'label' => 'Region',
                        'db_column' => 'region',
                        'type' => 'string',
                    ],
                    'district' => [
                        'label' => 'District',
                        'db_column' => 'district',
                        'type' => 'string',
                    ],
                    'community' => [
                        'label' => 'Community / Town',
                        'db_column' => 'community',
                        'type' => 'string',
                    ],
                    'depth_m' => [
                        'label' => 'Drilled Depth (m)',
                        'db_column' => 'depth_m',
                        'required' => true,
                        'type' => 'decimal',
                    ],
                    'static_water_level_m' => [
                        'label' => 'Static Water Level (m)',
                        'db_column' => 'static_water_level_m',
                        'type' => 'decimal',
                    ],
                    'yield_m3_per_hr' => [
                        'label' => 'Yield (m³/hr)',
                        'db_column' => 'yield_m3_per_hr',
                        'type' => 'decimal',
                    ],
                    'aquifer_type' => [
                        'label' => 'Aquifer Type',
                        'db_column' => 'aquifer_type',
                        'type' => 'string',
                    ],
                    'lithology' => [
                        'label' => 'Lithology Description',
                        'db_column' => 'lithology',
                        'type' => 'text',
                    ],
                    'water_quality_notes' => [
                        'label' => 'Water Quality Notes',
                        'db_column' => 'water_quality_notes',
                        'type' => 'text',
                    ],
                    'tds_mg_per_l' => [
                        'label' => 'TDS (mg/L)',
                        'db_column' => 'tds_mg_per_l',
                        'type' => 'decimal',
                    ],
                    'sample_date' => [
                        'label' => 'Sample Date',
                        'db_column' => 'sample_date',
                        'type' => 'date',
                    ],
                    'data_source' => [
                        'label' => 'Data Source',
                        'db_column' => 'data_source',
                        'type' => 'string',
                    ],
                    'confidence_score' => [
                        'label' => 'Confidence Score (0-1)',
                        'db_column' => 'confidence_score',
                        'type' => 'decimal',
                    ],
                ],
            ],
        ];
    }

    /**
     * Retrieve a dataset definition.
     */
    public function getDefinition(string $dataset): ?array
    {
        $definitions = self::getDefinitions();
        return $definitions[$dataset] ?? null;
    }

    /**
     * Generate a preview with detected headers and up to N sample rows.
     *
     * @return array<string, mixed>
     */
    public function buildPreview(string $dataset, string $filePath, string $delimiter = ',', int $sampleLimit = null): array
    {
        $definition = $this->getDefinition($dataset);
        if (!$definition) {
            throw new InvalidArgumentException('Unknown dataset: ' . $dataset);
        }

        $sampleLimit = $sampleLimit ?? $this->previewSampleLimit;

        $handle = $this->openCsv($filePath, $delimiter);
        $headers = $this->readCsvRow($handle);

        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Unable to read CSV header row.');
        }

        $normalizedHeaders = $this->normalizeHeaders($headers);
        $rows = [];
        $totalRows = 0;

        while (($row = $this->readCsvRow($handle)) !== null) {
            $totalRows++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            if (count($rows) < $sampleLimit) {
                $rows[] = $this->combineRow($headers, $row);
            }
        }

        fclose($handle);

        $missingRequiredFields = [];
        foreach ($definition['fields'] as $fieldKey => $field) {
            if (!empty($field['required'])) {
                $suggestedColumn = $this->suggestColumnForField($fieldKey, $field, $headers, $normalizedHeaders);
                if ($suggestedColumn === null) {
                    $missingRequiredFields[] = $field['label'] ?? $fieldKey;
                }
            }
        }

        $suggestedMapping = [];
        foreach ($definition['fields'] as $fieldKey => $field) {
            $suggestedMapping[$fieldKey] = $this->suggestColumnForField($fieldKey, $field, $headers, $normalizedHeaders);
        }

        return [
            'headers' => $headers,
            'sample_rows' => $rows,
            'total_rows' => $totalRows,
            'missing_required_fields' => $missingRequiredFields,
            'suggested_mapping' => $suggestedMapping,
            'definition' => $this->sanitiseDefinitionForOutput($definition),
        ];
    }

    /**
     * Import using a mapping (dataset field => CSV column).
     *
     * @param array<string, string|null> $mapping
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function importFromCsv(
        string $dataset,
        string $filePath,
        string $delimiter,
        array $mapping,
        array $options = []
    ): array {
        $definition = $this->getDefinition($dataset);
        if (!$definition) {
            throw new InvalidArgumentException('Unknown dataset: ' . $dataset);
        }

        $requiredFields = array_keys(array_filter($definition['fields'], fn($f) => !empty($f['required'])));
        $missingMappings = array_values(array_filter($requiredFields, fn($fieldKey) => empty($mapping[$fieldKey])));
        if (!empty($missingMappings)) {
            throw new InvalidArgumentException(
                'Missing mappings for required fields: ' . implode(', ', $missingMappings)
            );
        }

        $handle = $this->openCsv($filePath, $delimiter);
        $headers = $this->readCsvRow($handle);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Unable to read CSV header row.');
        }

        $headerPositions = $this->mapHeaderPositions($headers);

        $summary = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'total_rows' => 0,
            'dataset' => $dataset,
        ];

        $updateExisting = (bool)($options['update_existing'] ?? true);
        $skipBlankUpdates = (bool)($options['skip_blank_updates'] ?? true);

        $rowIndex = 0;
        while (($row = $this->readCsvRow($handle)) !== null) {
            $rowIndex++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $summary['total_rows']++;

            try {
                $datasetRow = $this->extractDatasetRow($definition, $mapping, $headerPositions, $row, $headers);

                $dbRow = $this->mapDatasetRowToDatabaseRow($definition, $datasetRow);
                if ($dbRow === null) {
                    $summary['skipped']++;
                    continue;
                }

                $uniqueKeys = $this->resolveUniqueFilter($definition, $dbRow);
                $existingId = null;
                if ($uniqueKeys !== null) {
                    $existingId = $this->findExistingRecordId(
                        $definition['table'],
                        $definition['primary_key'],
                        $uniqueKeys
                    );
                }

                if ($existingId !== null) {
                    if ($updateExisting) {
                        $updated = $this->updateRecord(
                            $definition['table'],
                            $definition['primary_key'],
                            (int)$existingId,
                            $dbRow,
                            $skipBlankUpdates
                        );
                        if ($updated) {
                            $summary['updated']++;
                        } else {
                            $summary['skipped']++;
                        }
                    } else {
                        $summary['skipped']++;
                    }
                } else {
                    $this->insertRecord($definition['table'], $dbRow);
                    $summary['inserted']++;
                }
            } catch (Throwable $e) {
                $summary['errors'][] = [
                    'row' => $rowIndex + 1, // +1 to account for header row
                    'message' => $e->getMessage(),
                ];
                $summary['skipped']++;
            }
        }

        fclose($handle);

        return $summary;
    }

    /**
     * Combine headers with row values.
     *
     * @param string[] $headers
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $row): array
    {
        $combined = [];
        foreach ($headers as $index => $header) {
            $combined[$header] = isset($row[$index]) ? trim((string)$row[$index]) : null;
        }
        return $combined;
    }

    /**
     * Extract dataset fields using mapping.
     *
     * @param array<string, mixed> $definition
     * @param array<string, string|null> $mapping
     * @param array<string, int> $headerPositions
     * @param array<int, string|null> $row
     * @param string[] $headers
     * @return array<string, mixed>
     */
    private function extractDatasetRow(
        array $definition,
        array $mapping,
        array $headerPositions,
        array $row,
        array $headers
    ): array {
        $datasetRow = [];
        $missingRequired = [];

        foreach ($definition['fields'] as $fieldKey => $fieldDef) {
            $selectedColumn = $mapping[$fieldKey] ?? null;
            if ($selectedColumn === null || $selectedColumn === '__skip__') {
                if (!empty($fieldDef['required'])) {
                    $missingRequired[] = $fieldDef['label'] ?? $fieldKey;
                }
                continue;
            }

            $normalizedColumn = $this->normalizeHeader($selectedColumn);
            if (!isset($headerPositions[$normalizedColumn])) {
                if (!empty($fieldDef['required'])) {
                    $missingRequired[] = $fieldDef['label'] ?? $fieldKey;
                }
                continue;
            }

            $valueIndex = $headerPositions[$normalizedColumn];
            $value = $row[$valueIndex] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '') {
                $value = null;
            }

            if (!empty($fieldDef['required']) && ($value === null || $value === '')) {
                $missingRequired[] = $fieldDef['label'] ?? $fieldKey;
            }

            $datasetRow[$fieldKey] = $value;
        }

        if (!empty($missingRequired)) {
            throw new RuntimeException('Missing required field(s): ' . implode(', ', $missingRequired));
        }

        return $datasetRow;
    }

    /**
     * Map dataset row to database column => value array, applying transformations.
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $datasetRow
     */
    private function mapDatasetRowToDatabaseRow(array $definition, array $datasetRow): ?array
    {
        $dbRow = [];

        foreach ($definition['fields'] as $fieldKey => $fieldDef) {
            $value = $datasetRow[$fieldKey] ?? null;

            if (($value === null || $value === '') && array_key_exists('default', $fieldDef)) {
                $value = $fieldDef['default'];
            }

            $type = $fieldDef['type'] ?? 'string';
            try {
                $value = $this->normaliseValue($value, $type, $fieldDef);
            } catch (Throwable $e) {
                throw new RuntimeException(($fieldDef['label'] ?? $fieldKey) . ': ' . $e->getMessage());
            }

            // Lookup category names if requested
            if ($type === 'lookup_category' && $value !== null) {
                $categoryId = $this->resolveCategoryId((string)$value);
                $targetColumn = $fieldDef['target_column'] ?? 'category_id';
                if ($categoryId === null) {
                    throw new RuntimeException('Unable to resolve category name: ' . $value);
                }
                $dbRow[$targetColumn] = $categoryId;
                continue;
            }

            if (!isset($fieldDef['db_column'])) {
                continue;
            }

            $dbRow[$fieldDef['db_column']] = $value;
        }

        if (empty($dbRow)) {
            return null;
        }

        return $dbRow;
    }

    /**
     * Insert a record.
     *
     * @param array<string, mixed> $data
     */
    private function insertRecord(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($col) => '`' . $col . '`', $columns)),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    }

    /**
     * Update an existing record.
     *
     * @param array<string, mixed> $data
     */
    private function updateRecord(
        string $table,
        string $primaryKey,
        int $recordId,
        array $data,
        bool $skipBlankUpdates
    ): bool {
        $columns = [];
        $params = [];

        foreach ($data as $column => $value) {
            if ($column === $primaryKey) {
                continue;
            }
            if ($skipBlankUpdates && ($value === null || $value === '')) {
                continue;
            }

            $columns[] = sprintf('`%s` = :%s', $column, $column);
            $params[$column] = $value;
        }

        if (empty($columns)) {
            return false;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :record_id',
            $table,
            implode(', ', $columns),
            $primaryKey
        );

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->bindValue(':record_id', $recordId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Resolve unique filter using definition unique sets.
     *
     * @param array<string, mixed> $dbRow
     * @return array<string, mixed>|null
     */
    private function resolveUniqueFilter(array $definition, array $dbRow): ?array
    {
        if (empty($definition['unique_sets']) || !is_array($definition['unique_sets'])) {
            return null;
        }

        foreach ($definition['unique_sets'] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $values = [];
            $allPresent = true;
            foreach ($candidate as $column) {
                if (!array_key_exists($column, $dbRow) || $dbRow[$column] === null || $dbRow[$column] === '') {
                    $allPresent = false;
                    break;
                }
                $values[$column] = $dbRow[$column];
            }

            if ($allPresent && !empty($values)) {
                return $values;
            }
        }

        return null;
    }

    /**
     * Find existing record ID based on unique filter values.
     *
     * @param array<string, mixed> $filters
     */
    private function findExistingRecordId(string $table, string $primaryKey, array $filters): ?int
    {
        $conditions = [];
        foreach ($filters as $column => $value) {
            $conditions[] = sprintf('`%s` = :%s', $column, $column);
        }

        $sql = sprintf(
            'SELECT `%s` FROM `%s` WHERE %s LIMIT 1',
            $primaryKey,
            $table,
            implode(' AND ', $conditions)
        );

        $stmt = $this->pdo->prepare($sql);
        foreach ($filters as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();

        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /**
     * Normalise values based on field type.
     *
     * @param mixed $value
     * @param array<string, mixed> $fieldDef
     * @return mixed
     */
    private function normaliseValue($value, string $type, array $fieldDef)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'string':
                return trim((string)$value) ?: null;
            case 'slug':
                $value = preg_replace('/[^a-zA-Z0-9_\-]/', '-', (string)$value);
                return strtolower(trim((string)$value, '-_ '));
            case 'email':
                $value = strtolower(trim((string)$value));
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Invalid email address');
                }
                return $value ?: null;
            case 'phone':
                $digits = preg_replace('/[^0-9+]/', '', (string)$value);
                return $digits ?: null;
            case 'decimal':
                if ($value === '' || $value === null) {
                    return null;
                }
                $value = str_replace(',', '', (string)$value);
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException('Expected a numeric amount');
                }
                return (float)$value;
            case 'integer':
                if ($value === '' || $value === null) {
                    return null;
                }
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException('Expected a numeric value');
                }
                return (int)$value;
            case 'boolean':
                if (is_bool($value)) {
                    return $value ? 1 : 0;
                }
                $value = strtolower((string)$value);
                $trueValues = ['1', 'true', 'yes', 'y'];
                $falseValues = ['0', 'false', 'no', 'n', ''];
                if (in_array($value, $trueValues, true)) {
                    return 1;
                }
                if (in_array($value, $falseValues, true)) {
                    return 0;
                }
                throw new InvalidArgumentException('Expected yes/no or true/false');
            case 'enum':
                $allowed = $fieldDef['allowed_values'] ?? [];
                $value = strtolower((string)$value);
                if (!in_array($value, $allowed, true)) {
                    throw new InvalidArgumentException(
                        'Value must be one of: ' . implode(', ', $allowed)
                    );
                }
                return $value;
            case 'lookup_category':
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Resolve or create a category by name.
     */
    private function resolveCategoryId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM catalog_categories WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int)$existing;
        }

        $insert = $this->pdo->prepare('INSERT INTO catalog_categories (name, description) VALUES (?, NULL)');
        $insert->execute([$name]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Suggest a CSV header name for a dataset field.
     *
     * @param string[] $headers
     * @param array<int|string, string> $normalizedHeaders
     */
    private function suggestColumnForField(
        string $fieldKey,
        array $fieldDef,
        array $headers,
        array $normalizedHeaders
    ): ?string {
        $candidates = array_filter([
            $fieldKey,
            $fieldDef['label'] ?? null,
            $fieldDef['db_column'] ?? null,
        ]);

        $normalizedHeaderMap = [];
        foreach ($headers as $header) {
            $normalizedHeaderMap[$this->normalizeHeader($header)] = $header;
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalizeHeader((string)$candidate);
            if (isset($normalizedHeaderMap[$normalizedCandidate])) {
                return $normalizedHeaderMap[$normalizedCandidate];
            }
        }

        // Try partial matching
        foreach ($headers as $header) {
            $normalizedHeader = $this->normalizeHeader($header);
            if (strpos($normalizedHeader, $this->normalizeHeader($fieldKey)) !== false) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Prepare definition data for frontend output (remove callbacks, etc).
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function sanitiseDefinitionForOutput(array $definition): array
    {
        $output = $definition;
        foreach ($output['fields'] as &$field) {
            unset($field['resolver'], $field['target_column']);
        }
        return $output;
    }

    /**
     * Normalise headers.
     *
     * @param string[] $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map([$this, 'normalizeHeader'], $headers);
    }

    /**
     * Convert header to lowercase alphanumeric slug.
     */
    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/\x{FEFF}/u', '', $header); // remove BOM
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }

    /**
     * Check if row is empty.
     *
     * @param array<int, string|null> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Map header positions.
     *
     * @param string[] $headers
     * @return array<string, int>
     */
    private function mapHeaderPositions(array $headers): array
    {
        $positions = [];
        foreach ($headers as $index => $header) {
            $positions[$this->normalizeHeader($header)] = $index;
        }
        return $positions;
    }

    /**
     * Read CSV row with current delimiter.
     *
     * @return array<int, string|null>|null
     */
    private function readCsvRow($handle): ?array
    {
        if (!is_resource($handle)) {
            return null;
        }

        $row = fgetcsv($handle, 0, $this->currentDelimiter);

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * Open CSV file with delimiter.
     *
     * @param string $delimiter
     * @return resource
     */
    private function openCsv(string $filePath, string $delimiter)
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('CSV file cannot be read: ' . $filePath);
        }

        $delimiter = $this->normaliseDelimiter($delimiter);
        $this->currentDelimiter = $delimiter;

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Failed to open CSV file: ' . $filePath);
        }

        if (function_exists('stream_filter_prepend')) {
            @stream_filter_prepend($handle, 'convert.iconv.utf-8/utf-8');
        }

        ini_set('auto_detect_line_endings', '1');
        $this->setCsvControl($handle, $delimiter);

        return $handle;
    }

    /**
     * Normalise delimiter symbols.
     */
    private function normaliseDelimiter(string $delimiter): string
    {
        $delimiter = strtolower(trim($delimiter));
        return match ($delimiter) {
            '\t', 'tab' => "\t",
            ';', 'semicolon' => ';',
            '|', 'pipe' => '|',
            default => ',',
        };
    }

    /**
     * Set CSV control on resource.
     *
     * @param resource $handle
     */
    private function setCsvControl($handle, string $delimiter): void
    {
        // fgetcsv uses global setting, so nothing extra required beyond ensuring delimiter is set.
        // This helper exists for future extension (e.g., enclosure detection).
        $GLOBALS['__abbis_csv_delimiter'] = $delimiter;
    }
}


