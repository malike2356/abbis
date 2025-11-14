<?php
/**
 * RequestResponseManager
 * ------------------------------------------------------------------
 * Handles generation, persistence and lifecycle of quote/rig responses.
 * Provides helper utilities to create responses from catalog pricing,
 * manage custom line items, approvals, and status history tracking.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email.php';

class RequestResponseManager
{
    private $pdo;
    private $emailService;
    private $databaseDriver = 'mysql';

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->databaseDriver = defined('DB_CONNECTION') ? DB_CONNECTION : 'mysql';
        $this->emailService = new Email();
        $this->ensureTables();
    }

    /**
     * Ensure required tables exist.
     */
    private function ensureTables(): void
    {
        if ($this->isSqlite()) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS crm_request_responses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_type TEXT NOT NULL,
                    request_id INTEGER NOT NULL,
                    response_code TEXT NOT NULL,
                    status TEXT DEFAULT 'draft',
                    subject TEXT DEFAULT NULL,
                    intro TEXT DEFAULT NULL,
                    terms TEXT DEFAULT NULL,
                    subtotal REAL DEFAULT 0.0,
                    discount_total REAL DEFAULT 0.0,
                    tax_total REAL DEFAULT 0.0,
                    total REAL DEFAULT 0.0,
                    currency TEXT DEFAULT 'GHS',
                    approval_required INTEGER DEFAULT 0,
                    approval_requested_by INTEGER DEFAULT NULL,
                    approval_requested_at TEXT DEFAULT NULL,
                    approved_by INTEGER DEFAULT NULL,
                    approved_at TEXT DEFAULT NULL,
                    sent_by INTEGER DEFAULT NULL,
                    sent_at TEXT DEFAULT NULL,
                    sent_to TEXT DEFAULT NULL,
                    internal_notes TEXT DEFAULT NULL,
                    external_notes TEXT DEFAULT NULL,
                    meta_json TEXT DEFAULT NULL,
                    created_by INTEGER DEFAULT NULL,
                    updated_by INTEGER DEFAULT NULL,
                    created_at TEXT DEFAULT (datetime('now')),
                    updated_at TEXT DEFAULT (datetime('now'))
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_crm_responses_request ON crm_request_responses (request_type, request_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_crm_responses_status ON crm_request_responses (status)");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS crm_request_response_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    response_id INTEGER NOT NULL,
                    catalog_item_id INTEGER DEFAULT NULL,
                    is_custom INTEGER DEFAULT 0,
                    item_name TEXT NOT NULL,
                    description TEXT DEFAULT NULL,
                    quantity REAL DEFAULT 1.0,
                    unit_price REAL DEFAULT 0.0,
                    discount_amount REAL DEFAULT 0.0,
                    tax_rate REAL DEFAULT 0.0,
                    total REAL DEFAULT 0.0,
                    sort_order INTEGER DEFAULT 0,
                    meta_json TEXT DEFAULT NULL,
                    created_at TEXT DEFAULT (datetime('now')),
                    updated_at TEXT DEFAULT (datetime('now')),
                    FOREIGN KEY(response_id) REFERENCES crm_request_responses(id) ON DELETE CASCADE
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_crm_response_items_response ON crm_request_response_items (response_id)");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS crm_request_status_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_type TEXT NOT NULL,
                    request_id INTEGER NOT NULL,
                    old_status TEXT DEFAULT NULL,
                    new_status TEXT NOT NULL,
                    changed_by INTEGER DEFAULT NULL,
                    note TEXT DEFAULT NULL,
                    created_at TEXT DEFAULT (datetime('now'))
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_crm_status_history_request ON crm_request_status_history (request_type, request_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_crm_status_history_status ON crm_request_status_history (new_status)");
            return;
        }

        // Responses table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_request_responses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_type ENUM('quote','rig') NOT NULL,
                request_id INT NOT NULL,
                response_code VARCHAR(50) NOT NULL,
                status ENUM('draft','pending_approval','approved','sent','declined','cancelled') DEFAULT 'draft',
                subject VARCHAR(255) DEFAULT NULL,
                intro TEXT DEFAULT NULL,
                terms TEXT DEFAULT NULL,
                subtotal DECIMAL(12,2) DEFAULT 0.00,
                discount_total DECIMAL(12,2) DEFAULT 0.00,
                tax_total DECIMAL(12,2) DEFAULT 0.00,
                total DECIMAL(12,2) DEFAULT 0.00,
                currency VARCHAR(10) DEFAULT 'GHS',
                approval_required TINYINT(1) DEFAULT 0,
                approval_requested_by INT DEFAULT NULL,
                approval_requested_at DATETIME DEFAULT NULL,
                approved_by INT DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                sent_by INT DEFAULT NULL,
                sent_at DATETIME DEFAULT NULL,
                sent_to TEXT DEFAULT NULL,
                internal_notes TEXT DEFAULT NULL,
                external_notes TEXT DEFAULT NULL,
                meta_json LONGTEXT DEFAULT NULL,
                created_by INT DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_response_code (response_code),
                KEY idx_request (request_type, request_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Response items table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_request_response_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                response_id INT NOT NULL,
                catalog_item_id INT DEFAULT NULL,
                is_custom TINYINT(1) DEFAULT 0,
                item_name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                quantity DECIMAL(12,3) DEFAULT 1.000,
                unit_price DECIMAL(12,2) DEFAULT 0.00,
                discount_amount DECIMAL(12,2) DEFAULT 0.00,
                tax_rate DECIMAL(5,2) DEFAULT 0.00,
                total DECIMAL(12,2) DEFAULT 0.00,
                sort_order INT DEFAULT 0,
                meta_json LONGTEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_response (response_id),
                KEY idx_catalog_item (catalog_item_id),
                CONSTRAINT fk_response_item_response FOREIGN KEY (response_id)
                    REFERENCES crm_request_responses(id) ON DELETE CASCADE,
                CONSTRAINT fk_response_item_catalog FOREIGN KEY (catalog_item_id)
                    REFERENCES catalog_items(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Status history table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_request_status_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_type ENUM('quote','rig') NOT NULL,
                request_id INT NOT NULL,
                old_status VARCHAR(50) DEFAULT NULL,
                new_status VARCHAR(50) NOT NULL,
                changed_by INT DEFAULT NULL,
                note TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_request (request_type, request_id),
                KEY idx_status (new_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Retrieve responses with their items for a specific request.
     */
    public function getResponsesForRequest(string $requestType, int $requestId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM crm_request_responses
            WHERE request_type = ? AND request_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$requestType, $requestId]);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($responses)) {
            return [];
        }

        $responseIds = array_column($responses, 'id');
        $items = $this->fetchItemsForResponses($responseIds);

        $groupedItems = [];
        foreach ($items as $item) {
            $groupedItems[$item['response_id']][] = $item;
        }

        foreach ($responses as &$response) {
            $response['items'] = $groupedItems[$response['id']] ?? [];
        }

        return $responses;
    }

    /**
     * Generate a quote response from catalog pricing.
     */
    public function generateQuoteResponse(int $quoteId, int $userId, array $options = []): array
    {
        $quote = $this->loadQuote($quoteId);
        if (!$quote) {
            throw new RuntimeException("Quote request #{$quoteId} not found.");
        }

        $responseData = [
            'request_type' => 'quote',
            'request_id' => $quoteId,
            'response_code' => $this->generateResponseCode('Q'),
            'status' => 'draft',
            'subject' => sprintf('Quote for %s', $quote['name']),
            'currency' => $options['currency'] ?? 'GHS',
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        $responseId = $this->insertResponse($responseData);

        $items = $this->buildQuoteItemsFromCatalog($quote);
        if (empty($items)) {
            // Provide at least one placeholder item for manual editing
            $items[] = [
                'item_name' => 'Custom Borehole Package',
                'description' => 'Please update with appropriate catalog items or custom pricing.',
                'quantity' => 1,
                'unit_price' => $quote['estimated_budget'] ?: 0,
                'is_custom' => 1,
            ];
        }

        $this->persistItems($responseId, $items);
        $this->recalculateTotals($responseId);

        return $this->getResponse($responseId);
    }

    /**
     * Generate a rig response from catalog pricing.
     */
    public function generateRigResponse(int $rigRequestId, int $userId, array $options = []): array
    {
        $rigRequest = $this->loadRigRequest($rigRequestId);
        if (!$rigRequest) {
            throw new RuntimeException("Rig request #{$rigRequestId} not found.");
        }

        $responseData = [
            'request_type' => 'rig',
            'request_id' => $rigRequestId,
            'response_code' => $this->generateResponseCode('R'),
            'status' => 'draft',
            'subject' => sprintf('Rig Deployment Proposal - %s', $rigRequest['request_number']),
            'currency' => $options['currency'] ?? 'GHS',
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        $responseId = $this->insertResponse($responseData);

        $items = $this->buildRigItemsFromCatalog($rigRequest);
        if (empty($items)) {
            $items[] = [
                'item_name' => 'Rig Deployment Package',
                'description' => 'Update with mobilisation, drilling rate, and support services.',
                'quantity' => max(1, (int)$rigRequest['number_of_boreholes']),
                'unit_price' => $rigRequest['estimated_budget'] ?: 0,
                'is_custom' => 1,
            ];
        }

        $this->persistItems($responseId, $items);
        $this->recalculateTotals($responseId);

        return $this->getResponse($responseId);
    }

    /**
     * Add a custom item to a response.
     */
    public function addCustomItem(int $responseId, array $data, int $userId): array
    {
        $response = $this->getResponse($responseId);
        if (!$response) {
            throw new RuntimeException('Response not found.');
        }

        $itemData = [
            'response_id' => $responseId,
            'catalog_item_id' => null,
            'is_custom' => 1,
            'item_name' => trim($data['item_name'] ?? 'Custom Line Item'),
            'description' => trim($data['description'] ?? ''),
            'quantity' => max(0.001, (float)($data['quantity'] ?? 1)),
            'unit_price' => (float)($data['unit_price'] ?? 0),
            'discount_amount' => (float)($data['discount_amount'] ?? 0),
            'tax_rate' => (float)($data['tax_rate'] ?? 0),
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ];

        $this->insertItem($itemData);
        $this->recalculateTotals($responseId);

        return $this->getResponse($responseId);
    }

    /**
     * Update an existing item.
     */
    public function updateItem(int $itemId, array $data): bool
    {
        $item = $this->getItem($itemId);
        if (!$item) {
            throw new RuntimeException('Response item not found.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE crm_request_response_items
            SET item_name = ?, description = ?, quantity = ?, unit_price = ?, discount_amount = ?, tax_rate = ?, sort_order = ?, updated_at = ?
            WHERE id = ?
        ");

        $stmt->execute([
            trim($data['item_name'] ?? $item['item_name']),
            trim($data['description'] ?? $item['description']),
            max(0.001, (float)($data['quantity'] ?? $item['quantity'])),
            (float)($data['unit_price'] ?? $item['unit_price']),
            (float)($data['discount_amount'] ?? $item['discount_amount']),
            (float)($data['tax_rate'] ?? $item['tax_rate']),
            (int)($data['sort_order'] ?? $item['sort_order']),
            $this->currentTimestamp(),
            $itemId,
        ]);

        $this->recalculateTotals($item['response_id']);

        return true;
    }

    /**
     * Delete an item from a response.
     */
    public function deleteItem(int $itemId): bool
    {
        $item = $this->getItem($itemId);
        if (!$item) {
            throw new RuntimeException('Response item not found.');
        }

        $stmt = $this->pdo->prepare("DELETE FROM crm_request_response_items WHERE id = ?");
        $stmt->execute([$itemId]);

        $this->recalculateTotals($item['response_id']);

        return true;
    }

    /**
     * Submit a response for approval.
     */
    public function submitForApproval(int $responseId, int $userId): bool
    {
        $response = $this->getResponse($responseId);
        if (!$response) {
            throw new RuntimeException('Response not found.');
        }

        if ($response['status'] !== 'draft') {
            throw new RuntimeException('Only draft responses can be submitted for approval.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE crm_request_responses
            SET status = 'pending_approval',
                approval_required = 1,
                approval_requested_by = ?,
                approval_requested_at = ?,
                updated_by = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $now = $this->currentTimestamp();
        $stmt->execute([$userId, $now, $userId, $now, $responseId]);

        $this->recordStatusHistory($response['request_type'], $response['request_id'], $response['status'], 'pending_approval', $userId, 'Submitted for approval');

        return true;
    }

    /**
     * Approve a response.
     */
    public function approveResponse(int $responseId, int $userId): bool
    {
        $response = $this->getResponse($responseId);
        if (!$response) {
            throw new RuntimeException('Response not found.');
        }

        if (!in_array($response['status'], ['pending_approval', 'draft'], true)) {
            throw new RuntimeException('Only pending or draft responses can be approved.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE crm_request_responses
            SET status = 'approved',
                approval_required = 0,
                approved_by = ?,
                approved_at = ?,
                updated_by = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $timestamp = $this->currentTimestamp();
        $stmt->execute([$userId, $timestamp, $userId, $timestamp, $responseId]);

        $this->recordStatusHistory($response['request_type'], $response['request_id'], $response['status'], 'approved', $userId, 'Response approved');

        return true;
    }

    /**
     * Mark a response as sent.
     */
    public function markSent(int $responseId, int $userId, array $sentTo, ?string $note = null): bool
    {
        $response = $this->getResponse($responseId);
        if (!$response) {
            throw new RuntimeException('Response not found.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE crm_request_responses
            SET status = 'sent',
                sent_by = ?,
                sent_at = ?,
                sent_to = ?,
                updated_by = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $timestamp = $this->currentTimestamp();
        $stmt->execute([$userId, $timestamp, implode(',', $sentTo), $userId, $timestamp, $responseId]);

        $this->recordStatusHistory($response['request_type'], $response['request_id'], $response['status'], 'sent', $userId, $note ?? 'Response emailed to client');
        $this->updateRequestStatus($response['request_type'], $response['request_id'], $response['request_type'] === 'quote' ? 'quoted' : 'dispatched', $userId, 'Response sent to requester');

        return true;
    }

    /**
     * Send response to client and mark as sent.
     */
    public function sendResponseEmail(int $responseId, string $recipientEmail, int $userId, array $options = []): bool
    {
        $response = $this->getResponse($responseId);
        if (!$response) {
            throw new RuntimeException('Response not found.');
        }

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient email address.');
        }

        $requestData = $response['request_type'] === 'quote'
            ? $this->loadQuote($response['request_id'])
            : $this->loadRigRequest($response['request_id']);

        $subject = $options['subject'] ?? ($response['subject'] ?: 'Proposal ' . $response['response_code']);
        $body = $this->renderResponseHtml($response, $requestData, $options);

        $emailOptions = [
            'type' => $response['request_type'] . '_response',
            'template_label' => 'Automated Request Response',
        ];

        if (!empty($options['cc'])) {
            $emailOptions['cc'] = $options['cc'];
        }
        if (!empty($options['bcc'])) {
            $emailOptions['bcc'] = $options['bcc'];
        }

        $sent = $this->emailService->send($recipientEmail, $subject, $body, $emailOptions);
        if (!$sent) {
            throw new RuntimeException('Failed to send response email.');
        }

        $this->markSent($responseId, $userId, [$recipientEmail], $options['note'] ?? null);

        return true;
    }

    /**
     * Update the parent request status following defined workflow.
     */
    public function updateRequestStatus(string $requestType, int $requestId, string $newStatus, int $userId, ?string $note = null): bool
    {
        if ($requestType === 'quote') {
            $current = $this->pdo->prepare("SELECT status FROM cms_quote_requests WHERE id = ?");
            $current->execute([$requestId]);
            $currentStatus = $current->fetchColumn();

            if (!$this->isValidQuoteTransition($currentStatus, $newStatus)) {
                throw new RuntimeException("Invalid quote status transition from {$currentStatus} to {$newStatus}");
            }

            $stmt = $this->pdo->prepare("UPDATE cms_quote_requests SET status = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$newStatus, $this->currentTimestamp(), $requestId]);
        } else {
            $current = $this->pdo->prepare("SELECT status FROM rig_requests WHERE id = ?");
            $current->execute([$requestId]);
            $currentStatus = $current->fetchColumn();

            if (!$this->isValidRigTransition($currentStatus, $newStatus)) {
                throw new RuntimeException("Invalid rig status transition from {$currentStatus} to {$newStatus}");
            }

            $stmt = $this->pdo->prepare("UPDATE rig_requests SET status = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$newStatus, $this->currentTimestamp(), $requestId]);
        }

        $this->recordStatusHistory($requestType, $requestId, $currentStatus ?? null, $newStatus, $userId, $note);

        return true;
    }

    /**
     * Record request status history.
     */
    public function recordStatusHistory(string $requestType, int $requestId, ?string $oldStatus, string $newStatus, ?int $userId, ?string $note = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_request_status_history (request_type, request_id, old_status, new_status, changed_by, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$requestType, $requestId, $oldStatus, $newStatus, $userId, $note]);
    }

    /**
     * Recalculate monetary totals for a response.
     */
    public function recalculateTotals(int $responseId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id, quantity, unit_price, discount_amount, tax_rate
            FROM crm_request_response_items
            WHERE response_id = ?
        ");
        $stmt->execute([$responseId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subtotal = 0;
        $discountTotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $lineBase = $item['quantity'] * $item['unit_price'];
            $lineDiscount = $item['discount_amount'];
            $lineNet = max(0, $lineBase - $lineDiscount);
            $lineTax = $lineNet * ($item['tax_rate'] / 100);

            $subtotal += $lineBase;
            $discountTotal += $lineDiscount;
            $taxTotal += $lineTax;

            $updateLine = $this->pdo->prepare("
                UPDATE crm_request_response_items
                SET total = ?, updated_at = ?
                WHERE id = ?
            ");
            $updateLine->execute([$lineNet + $lineTax, $this->currentTimestamp(), $item['id']]);
        }

        $total = max(0, $subtotal - $discountTotal + $taxTotal);

        $update = $this->pdo->prepare("
            UPDATE crm_request_responses
            SET subtotal = ?, discount_total = ?, tax_total = ?, total = ?, updated_at = ?
            WHERE id = ?
        ");
        $update->execute([$subtotal, $discountTotal, $taxTotal, $total, $this->currentTimestamp(), $responseId]);
    }

    /**
     * Helper: insert response and return ID.
     */
    private function insertResponse(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_request_responses
            (request_type, request_id, response_code, status, subject, currency, created_by, updated_by, intro, terms, meta_json)
            VALUES (:request_type, :request_id, :response_code, :status, :subject, :currency, :created_by, :updated_by, :intro, :terms, :meta_json)
        ");
        $stmt->execute([
            ':request_type' => $data['request_type'],
            ':request_id' => $data['request_id'],
            ':response_code' => $data['response_code'],
            ':status' => $data['status'],
            ':subject' => $data['subject'] ?? null,
            ':currency' => $data['currency'] ?? 'GHS',
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
            ':intro' => $data['intro'] ?? null,
            ':terms' => $data['terms'] ?? null,
            ':meta_json' => $data['meta_json'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Persist response items.
     */
    private function persistItems(int $responseId, array $items): void
    {
        foreach ($items as $index => $item) {
            $data = [
                'response_id' => $responseId,
                'catalog_item_id' => $item['catalog_item_id'] ?? null,
                'is_custom' => $item['is_custom'] ?? ($item['catalog_item_id'] ? 0 : 1),
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'quantity' => max(0.001, (float)($item['quantity'] ?? 1)),
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'discount_amount' => (float)($item['discount_amount'] ?? 0),
                'tax_rate' => (float)($item['tax_rate'] ?? 0),
                'sort_order' => $item['sort_order'] ?? $index,
                'meta_json' => $item['meta_json'] ?? null,
            ];
            $this->insertItem($data);
        }
    }

    /**
     * Insert single item.
     */
    private function insertItem(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_request_response_items
            (response_id, catalog_item_id, is_custom, item_name, description, quantity, unit_price, discount_amount, tax_rate, sort_order, meta_json)
            VALUES (:response_id, :catalog_item_id, :is_custom, :item_name, :description, :quantity, :unit_price, :discount_amount, :tax_rate, :sort_order, :meta_json)
        ");
        $stmt->execute([
            ':response_id' => $data['response_id'],
            ':catalog_item_id' => $data['catalog_item_id'],
            ':is_custom' => $data['is_custom'],
            ':item_name' => $data['item_name'],
            ':description' => $data['description'],
            ':quantity' => $data['quantity'],
            ':unit_price' => $data['unit_price'],
            ':discount_amount' => $data['discount_amount'],
            ':tax_rate' => $data['tax_rate'],
            ':sort_order' => $data['sort_order'],
            ':meta_json' => $data['meta_json'],
        ]);
    }

    private function fetchItemsForResponses(array $responseIds): array
    {
        if (empty($responseIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($responseIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT * FROM crm_request_response_items
            WHERE response_id IN ({$placeholders})
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute($responseIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchItemsForResponse(int $responseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM crm_request_response_items
            WHERE response_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$responseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getResponse(int $responseId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM crm_request_responses WHERE id = ?");
        $stmt->execute([$responseId]);
        $response = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$response) {
            return null;
        }

        $response['items'] = $this->fetchItemsForResponse($responseId);

        return $response;
    }

    public function getItem(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM crm_request_response_items WHERE id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function loadQuote(int $quoteId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cms_quote_requests WHERE id = ?");
        $stmt->execute([$quoteId]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quote) {
            return null;
        }

        $quote['services'] = [
            'include_drilling' => (bool)$quote['include_drilling'],
            'include_construction' => (bool)$quote['include_construction'],
            'include_mechanization' => (bool)$quote['include_mechanization'],
            'include_yield_test' => (bool)$quote['include_yield_test'],
            'include_chemical_test' => (bool)$quote['include_chemical_test'],
            'include_polytank_stand' => (bool)$quote['include_polytank_stand'],
        ];

        if (!empty($quote['pump_preferences'])) {
            $prefs = json_decode($quote['pump_preferences'], true);
            $quote['pump_preferences'] = is_array($prefs) ? $prefs : [];
        } else {
            $quote['pump_preferences'] = [];
        }

        return $quote;
    }

    private function loadRigRequest(int $rigRequestId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rig_requests WHERE id = ?");
        $stmt->execute([$rigRequestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function generateResponseCode(string $prefix): string
    {
        return sprintf('%s-%s-%04d', $prefix, date('Ymd'), random_int(1000, 9999));
    }

    /**
     * Build quote items from catalog based on service selections.
     */
    private function buildQuoteItemsFromCatalog(array $quote): array
    {
        $items = [];

        $serviceMap = [
            'include_drilling' => [
                'label' => 'Borehole Drilling Service',
                'keywords' => ['drill', 'drilling', 'borehole drilling'],
                'description' => 'Drilling to desired depth including crew and rig operations.',
            ],
            'include_construction' => [
                'label' => 'Borehole Construction & Casing',
                'keywords' => ['construction', 'casing', 'screen pipe'],
                'description' => 'Supply and installation of screen, casing, and gravel pack.',
            ],
            'include_mechanization' => [
                'label' => 'Mechanization & Pump Installation',
                'keywords' => ['mechanization', 'pump installation'],
                'description' => 'Installation of pump, controls, and electrical connections.',
            ],
            'include_yield_test' => [
                'label' => 'Yield / Pump Test',
                'keywords' => ['yield test', 'pump test'],
                'description' => 'Comprehensive pumping test with detailed reporting.',
            ],
            'include_chemical_test' => [
                'label' => 'Water Chemical Analysis',
                'keywords' => ['chemical test', 'water analysis'],
                'description' => 'Laboratory water quality analysis for potability.',
            ],
            'include_polytank_stand' => [
                'label' => 'Polytank Stand Construction',
                'keywords' => ['polytank', 'tank stand'],
                'description' => 'Fabrication and installation of elevated polytank stand.',
            ],
        ];

        foreach ($serviceMap as $flag => $definition) {
            if (!empty($quote['services'][$flag])) {
                $catalogItem = $this->findCatalogItemByKeywords($definition['keywords']);
                if ($catalogItem) {
                    $items[] = $this->catalogItemToResponseItem($catalogItem, $definition['description']);
                } else {
                    $items[] = [
                        'item_name' => $definition['label'],
                        'description' => $definition['description'],
                        'quantity' => 1,
                        'unit_price' => 0,
                        'is_custom' => 1,
                    ];
                }
            }
        }

        // Add pump preferences if provided
        if (!empty($quote['pump_preferences'])) {
            foreach ($quote['pump_preferences'] as $pumpId) {
                $catalogItem = $this->fetchCatalogItemById((int)$pumpId);
                if ($catalogItem) {
                    $items[] = $this->catalogItemToResponseItem($catalogItem, 'Preferred pump option selected by client.');
                }
            }
        }

        return $items;
    }

    /**
     * Build rig request items from catalog.
     */
    private function buildRigItemsFromCatalog(array $rigRequest): array
    {
        $items = [];

        $baseMappings = [
            [
                'label' => 'Rig Mobilisation & Demobilisation',
                'keywords' => ['mobilisation', 'mobilization', 'demobilisation', 'demobilization'],
                'description' => 'Transportation of rig, crew, and support equipment to site.',
            ],
            [
                'label' => 'Rig Daily Drilling Rate',
                'keywords' => ['daily rate', 'rig rental', 'rig day'],
                'description' => 'Daily rig operations charge covering crew, fuel, and maintenance.',
                'quantity' => max(1, (int)$rigRequest['number_of_boreholes']),
            ],
            [
                'label' => 'Support Logistics & Consumables',
                'keywords' => ['logistics', 'consumables', 'fuel surcharge'],
                'description' => 'Consumables, fuel, and logistics support during deployment.',
            ],
        ];

        foreach ($baseMappings as $mapping) {
            $catalogItem = $this->findCatalogItemByKeywords($mapping['keywords']);
            if ($catalogItem) {
                $items[] = $this->catalogItemToResponseItem(
                    $catalogItem,
                    $mapping['description'],
                    $mapping['quantity'] ?? 1
                );
            } else {
                $items[] = [
                    'item_name' => $mapping['label'],
                    'description' => $mapping['description'],
                    'quantity' => $mapping['quantity'] ?? 1,
                    'unit_price' => 0,
                    'is_custom' => 1,
                ];
            }
        }

        return $items;
    }

    private function catalogItemToResponseItem(array $catalogItem, ?string $description = null, float $quantity = 1.0): array
    {
        $unitPrice = $catalogItem['sale_price'] ?? $catalogItem['sell_price'] ?? 0;
        return [
            'catalog_item_id' => $catalogItem['id'],
            'item_name' => $catalogItem['name'],
            'description' => $description ?? $catalogItem['notes'] ?? null,
            'quantity' => max(0.001, $quantity),
            'unit_price' => (float)$unitPrice,
            'discount_amount' => 0,
            'tax_rate' => $catalogItem['taxable'] ? ($this->getDefaultTaxRate() ?? 0) : 0,
            'is_custom' => 0,
        ];
    }

    private function findCatalogItemByKeywords(array $keywords): ?array
    {
        if (empty($keywords)) {
            return null;
        }

        $conditions = [];
        $params = [];

        foreach ($keywords as $keyword) {
            $conditions[] = "(LOWER(name) LIKE ? OR LOWER(notes) LIKE ?)";
            $params[] = '%' . strtolower($keyword) . '%';
            $params[] = '%' . strtolower($keyword) . '%';
        }

        $where = implode(' OR ', $conditions);
        $sql = "
            SELECT id, name, sell_price, sale_price, taxable, notes
            FROM catalog_items
            WHERE is_active = 1 AND is_sellable = 1 AND ({$where})
            ORDER BY sale_price DESC, sell_price DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function fetchCatalogItemById(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, sell_price, sale_price, taxable, notes FROM catalog_items WHERE id = ? LIMIT 1");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getStatusHistoryForRequest(string $requestType, int $requestId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, old_status, new_status, changed_by, note, created_at
            FROM crm_request_status_history
            WHERE request_type = ? AND request_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$requestType, $requestId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getDefaultTaxRate(): float
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $stmt = $this->pdo->query("SELECT setting_value FROM cms_settings WHERE setting_key = 'default_tax_rate' LIMIT 1");
            $value = $stmt->fetchColumn();
            if ($value !== false && $value !== null && $value !== '') {
                $cache = (float)$value;
                return $cache;
            }
        } catch (PDOException $e) {
            // ignore and fallback
        }

        $cache = 0.0;
        return $cache;
    }

    private function isSqlite(): bool
    {
        return strtolower($this->databaseDriver) === 'sqlite';
    }

    private function currentTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function isValidQuoteTransition(?string $current, string $next): bool
    {
        $transitions = [
            null => ['new'],
            'new' => ['contacted', 'quoted', 'rejected'],
            'contacted' => ['quoted', 'rejected'],
            'quoted' => ['converted', 'rejected'],
            'converted' => [],
            'rejected' => ['contacted'],
        ];

        $current = $current ?? null;
        return in_array($next, $transitions[$current] ?? [], true);
    }

    private function isValidRigTransition(?string $current, string $next): bool
    {
        $transitions = [
            null => ['new'],
            'new' => ['under_review', 'negotiating', 'declined'],
            'under_review' => ['negotiating', 'declined'],
            'negotiating' => ['dispatched', 'declined', 'cancelled'],
            'dispatched' => ['completed', 'cancelled'],
            'completed' => [],
            'declined' => [],
            'cancelled' => [],
        ];

        $current = $current ?? null;
        return in_array($next, $transitions[$current] ?? [], true);
    }

    private function renderResponseHtml(array $response, ?array $requestData, array $options = []): string
    {
        $items = $response['items'] ?? [];
        $currency = $response['currency'] ?? 'GHS';
        $clientName = '';
        $projectSummary = '';

        if ($response['request_type'] === 'quote' && $requestData) {
            $clientName = $requestData['name'] ?? '';
            $projectSummary = $requestData['location'] ?? $requestData['service_type'] ?? '';
        } elseif ($response['request_type'] === 'rig' && $requestData) {
            $clientName = $requestData['requester_name'] ?? '';
            $projectSummary = $requestData['location_address'] ?? '';
        }

        $rowsHtml = '';
        foreach ($items as $item) {
            $rowsHtml .= sprintf(
                '<tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0;">
                        <strong>%s</strong>%s
                    </td>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0; text-align:center;">%s</td>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0; text-align:right;">%s %s</td>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0; text-align:right;">%s %s</td>
                </tr>',
                htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'),
                !empty($item['description'])
                    ? '<div style="margin-top:6px; color:#64748b; font-size:13px;">' . nl2br(htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8')) . '</div>'
                    : '',
                rtrim(rtrim(number_format($item['quantity'], 3), '0'), '.'),
                $currency,
                number_format($item['unit_price'], 2),
                $currency,
                number_format($item['total'], 2)
            );
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="4" style="padding: 16px; text-align:center; color:#94a3b8;">No line items available. Please update the response with pricing details.</td></tr>';
        }

        $totalsHtml = sprintf(
            '<tr><td colspan="3" style="text-align:right; padding:8px 16px; color:#475569;">Subtotal</td><td style="text-align:right; padding:8px 16px;">%s %s</td></tr>
             <tr><td colspan="3" style="text-align:right; padding:8px 16px; color:#475569;">Discounts</td><td style="text-align:right; padding:8px 16px;">%s %s</td></tr>
             <tr><td colspan="3" style="text-align:right; padding:8px 16px; color:#475569;">Tax</td><td style="text-align:right; padding:8px 16px;">%s %s</td></tr>
             <tr><td colspan="3" style="text-align:right; padding:12px 16px; font-weight:600; color:#0f172a;">Total</td><td style="text-align:right; padding:12px 16px; font-weight:600;">%s %s</td></tr>',
            $currency, number_format($response['subtotal'], 2),
            $currency, number_format($response['discount_total'], 2),
            $currency, number_format($response['tax_total'], 2),
            $currency, number_format($response['total'], 2)
        );

        $intro = '';
        if (!empty($response['intro'])) {
            $intro = '<p style="margin-bottom:16px;">' . nl2br(htmlspecialchars($response['intro'], ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $terms = '';
        if (!empty($response['terms'])) {
            $terms = '
                <div style="margin-top:32px; border-top:1px solid #e2e8f0; padding-top:16px;">
                    <h3 style="margin:0 0 8px 0; color:#0f172a; font-size:16px;">Terms & Conditions</h3>
                    <div style="color:#475569; font-size:14px;">' . nl2br(htmlspecialchars($response['terms'], ENT_QUOTES, 'UTF-8')) . '</div>
                </div>';
        }

        return '
            <div style="font-family:\'-apple-system\',BlinkMacSystemFont,\'Segoe UI\',sans-serif; color:#1f2937; line-height:1.6;">
                <div style="margin-bottom:24px;">
                    <p style="margin:0 0 4px 0;">Dear ' . htmlspecialchars($clientName ?: 'Client', ENT_QUOTES, 'UTF-8') . ',</p>
                    <p style="margin:0;">Please find below our proposal for ' . htmlspecialchars($projectSummary ?: 'your request', ENT_QUOTES, 'UTF-8') . '.</p>
                </div>
                ' . $intro . '
                <div style="overflow:hidden; border:1px solid #e2e8f0; border-radius:12px;">
                    <table style="width:100%; border-collapse:collapse; min-width:100%;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:12px 16px; text-align:left; color:#0f172a;">Line Item</th>
                                <th style="padding:12px 16px; text-align:center; color:#0f172a; width:80px;">Qty</th>
                                <th style="padding:12px 16px; text-align:right; color:#0f172a; width:140px;">Unit Price</th>
                                <th style="padding:12px 16px; text-align:right; color:#0f172a; width:140px;">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>' . $rowsHtml . '</tbody>
                        <tfoot style="background:#f1f5f9;">' . $totalsHtml . '</tfoot>
                    </table>
                </div>
                ' . $terms . '
                <p style="margin-top:24px;">We appreciate the opportunity to support your project. Please contact us if you require further clarification.</p>
            </div>
        ';
    }
}


