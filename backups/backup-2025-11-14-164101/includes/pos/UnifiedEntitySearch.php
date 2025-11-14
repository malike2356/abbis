<?php

declare(strict_types=1);

require_once __DIR__ . '/PosRepository.php';

/**
 * Unified Entity Search Service
 * Searches across ABBIS clients, CMS customers, workers, and other entities
 * Excludes office workers (users table)
 */
class UnifiedEntitySearch
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Search for entities across all systems
     * 
     * @param string $searchTerm Search query (name, phone, email, etc.)
     * @param int $limit Maximum results to return
     * @return array Array of entities with unified structure
     */
    public function searchEntities(string $searchTerm, int $limit = 20): array
    {
        if (empty(trim($searchTerm))) {
            return [];
        }

        $searchTerm = trim($searchTerm);
        $results = [];
        $perCategoryLimit = max(25, (int)ceil($limit * 1.5));

        try {
            // 1. Search ABBIS Clients
            $clientResults = $this->searchClients($searchTerm, $perCategoryLimit);
            $results = array_merge($results, $clientResults);

            // 2. Search CMS Customers
            $cmsResults = $this->searchCmsCustomers($searchTerm, $perCategoryLimit);
            $results = array_merge($results, $cmsResults);

            // 3. Search Workers (field workers only, not office workers)
            $workerResults = $this->searchWorkers($searchTerm, $perCategoryLimit);
            $results = array_merge($results, $workerResults);

            // 4. Search Client Contacts
            $contactResults = $this->searchClientContacts($searchTerm, $perCategoryLimit);
            $results = array_merge($results, $contactResults);
        } catch (Exception $e) {
            error_log('[UnifiedEntitySearch] Error in searchEntities: ' . $e->getMessage());
            error_log('[UnifiedEntitySearch] Search term: ' . $searchTerm);
            error_log('[UnifiedEntitySearch] Trace: ' . $e->getTraceAsString());
            return [];
        }

        // Remove duplicates based on entity_type and entity_id
        $seen = [];
        $uniqueResults = [];
        foreach ($results as $result) {
            $key = $result['entity_type'] . '_' . $result['entity_id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueResults[] = $result;
            }
        }
        $results = $uniqueResults;

        // Sort by relevance (exact match first, then by name)
        usort($results, function ($a, $b) use ($searchTerm) {
            $searchLower = strtolower($searchTerm);
            $aNameLower = strtolower($a['name']);
            $bNameLower = strtolower($b['name']);
            
            // Exact match first
            if ($aNameLower === $searchLower && $bNameLower !== $searchLower) return -1;
            if ($bNameLower === $searchLower && $aNameLower !== $searchLower) return 1;
            
            // Starts with search term
            if (strpos($aNameLower, $searchLower) === 0 && strpos($bNameLower, $searchLower) !== 0) return -1;
            if (strpos($bNameLower, $searchLower) === 0 && strpos($aNameLower, $searchLower) !== 0) return 1;
            
            // Contains search term (earlier in string is better)
            $aPos = strpos($aNameLower, $searchLower);
            $bPos = strpos($bNameLower, $searchLower);
            if ($aPos !== false && $bPos !== false) {
                if ($aPos < $bPos) return -1;
                if ($bPos < $aPos) return 1;
            }
            if ($aPos !== false && $bPos === false) return -1;
            if ($bPos !== false && $aPos === false) return 1;
            
            // Alphabetical
            return strcmp($aNameLower, $bNameLower);
        });

        // Limit results
        return array_slice($results, 0, $limit);
    }

    /**
     * Search ABBIS Clients
     */
    private function searchClients(string $searchTerm, int $limit): array
    {
        try {
            $searchPattern = '%' . $searchTerm . '%';
            
            $sql = "
                SELECT 
                    id,
                    client_name as name,
                    COALESCE(contact_person, '') as contact_person,
                    COALESCE(contact_number, '') as phone,
                    COALESCE(email, '') as email,
                    COALESCE(address, '') as address,
                    'client' as entity_type,
                    'ABBIS Client' as source_system,
                    client_name as display_name
                FROM clients
                WHERE 
                    LOWER(client_name) LIKE LOWER(:search)
                    OR LOWER(COALESCE(contact_person, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(contact_number, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(email, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(address, '')) LIKE LOWER(:search)
                ORDER BY client_name ASC
                LIMIT :limit
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':search', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'formatClientEntity'], $clients);
        } catch (PDOException $e) {
            error_log('[UnifiedEntitySearch] Error searching clients: ' . $e->getMessage());
            error_log('[UnifiedEntitySearch] SQL: ' . ($sql ?? 'N/A'));
            return [];
        } catch (Exception $e) {
            error_log('[UnifiedEntitySearch] General error searching clients: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search CMS Customers
     */
    private function searchCmsCustomers(string $searchTerm, int $limit): array
    {
        try {
            // Check if table exists
            $tableExists = $this->pdo->query("SHOW TABLES LIKE 'cms_customers'")->rowCount() > 0;
            if (!$tableExists) {
                return [];
            }

            $searchPattern = '%' . $searchTerm . '%';
            
            $sql = "
                SELECT 
                    id,
                    COALESCE(first_name, '') as first_name,
                    COALESCE(last_name, '') as last_name,
                    COALESCE(phone, '') as phone,
                    COALESCE(email, '') as email,
                    COALESCE(billing_address, '') as address,
                    'cms_customer' as entity_type,
                    'CMS Customer' as source_system
                FROM cms_customers
                WHERE 
                    LOWER(COALESCE(first_name, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(last_name, '')) LIKE LOWER(:search)
                    OR LOWER(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) LIKE LOWER(:search)
                    OR LOWER(COALESCE(phone, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(email, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(billing_address, '')) LIKE LOWER(:search)
                ORDER BY last_name ASC, first_name ASC
                LIMIT :limit
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':search', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'formatCmsCustomerEntity'], $customers);
        } catch (PDOException $e) {
            error_log('[UnifiedEntitySearch] Error searching CMS customers: ' . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log('[UnifiedEntitySearch] General error searching CMS customers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search Workers (field workers only)
     */
    private function searchWorkers(string $searchTerm, int $limit): array
    {
        try {
            $searchPattern = '%' . $searchTerm . '%';
            
            $sql = "
                SELECT 
                    id,
                    worker_name as name,
                    COALESCE(contact_number, '') as phone,
                    role,
                    'worker' as entity_type,
                    'ABBIS Worker' as source_system,
                    worker_name as display_name
                FROM workers
                WHERE (
                    LOWER(worker_name) LIKE LOWER(:search)
                    OR LOWER(COALESCE(contact_number, '')) LIKE LOWER(:search)
                    OR LOWER(role) LIKE LOWER(:search)
                )
                AND status = 'active'
                ORDER BY worker_name ASC
                LIMIT :limit
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':search', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'formatWorkerEntity'], $workers);
        } catch (PDOException $e) {
            error_log('[UnifiedEntitySearch] Error searching workers: ' . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log('[UnifiedEntitySearch] General error searching workers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search Client Contacts (contacts within clients)
     */
    private function searchClientContacts(string $searchTerm, int $limit): array
    {
        try {
            // Check if table exists
            $tableExists = $this->pdo->query("SHOW TABLES LIKE 'client_contacts'")->rowCount() > 0;
            if (!$tableExists) {
                return [];
            }

            $searchPattern = '%' . $searchTerm . '%';
            
            $sql = "
                SELECT 
                    cc.id,
                    cc.name,
                    cc.client_id,
                    COALESCE(cc.phone, '') as phone,
                    COALESCE(cc.mobile, '') as mobile,
                    COALESCE(cc.email, '') as email,
                    c.client_name as client_name,
                    'client_contact' as entity_type,
                    'Client Contact' as source_system
                FROM client_contacts cc
                INNER JOIN clients c ON cc.client_id = c.id
                WHERE 
                    LOWER(cc.name) LIKE LOWER(:search)
                    OR LOWER(COALESCE(cc.phone, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(cc.mobile, '')) LIKE LOWER(:search)
                    OR LOWER(COALESCE(cc.email, '')) LIKE LOWER(:search)
                    OR LOWER(c.client_name) LIKE LOWER(:search)
                ORDER BY cc.name ASC
                LIMIT :limit
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':search', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map([$this, 'formatClientContactEntity'], $contacts);
        } catch (PDOException $e) {
            error_log('[UnifiedEntitySearch] Error searching client contacts: ' . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log('[UnifiedEntitySearch] General error searching client contacts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format client entity for unified response
     */
    private function formatClientEntity(array $client): array
    {
        $name = $client['name'] ?? 'Unknown';
        $phone = $client['phone'] ?? '';
        $email = $client['email'] ?? '';
        
        return [
            'id' => (int) $client['id'],
            'entity_type' => 'client',
            'entity_id' => (int) $client['id'],
            'name' => $name,
            'display_name' => $name . ($phone ? ' - ' . $phone : ''),
            'phone' => $phone,
            'email' => $email,
            'address' => $client['address'] ?? '',
            'contact_person' => $client['contact_person'] ?? '',
            'source_system' => 'ABBIS Client',
            'metadata' => [
                'contact_person' => $client['contact_person'] ?? '',
            ]
        ];
    }

    /**
     * Format CMS customer entity for unified response
     */
    private function formatCmsCustomerEntity(array $customer): array
    {
        $firstName = $customer['first_name'] ?? '';
        $lastName = $customer['last_name'] ?? '';
        $name = trim($firstName . ' ' . $lastName) ?: 'Unknown';
        $phone = $customer['phone'] ?? '';
        $email = $customer['email'] ?? '';
        
        return [
            'id' => (int) $customer['id'],
            'entity_type' => 'cms_customer',
            'entity_id' => (int) $customer['id'],
            'name' => $name,
            'display_name' => $name . ($phone ? ' - ' . $phone : ''),
            'phone' => $phone,
            'email' => $email,
            'address' => $customer['address'] ?? '',
            'source_system' => 'CMS Customer',
            'metadata' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]
        ];
    }

    /**
     * Format worker entity for unified response
     */
    private function formatWorkerEntity(array $worker): array
    {
        $name = $worker['name'] ?? 'Unknown';
        $phone = $worker['phone'] ?? '';
        $role = $worker['role'] ?? '';
        
        return [
            'id' => (int) $worker['id'],
            'entity_type' => 'worker',
            'entity_id' => (int) $worker['id'],
            'name' => $name,
            'display_name' => $name . ($role ? ' (' . $role . ')' : '') . ($phone ? ' - ' . $phone : ''),
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'source_system' => 'ABBIS Worker',
            'metadata' => [
                'role' => $role,
            ]
        ];
    }

    /**
     * Format client contact entity for unified response
     */
    private function formatClientContactEntity(array $contact): array
    {
        $name = $contact['name'] ?? 'Unknown';
        $phone = $contact['phone'] ?? $contact['mobile'] ?? '';
        $clientName = $contact['client_name'] ?? '';
        
        return [
            'id' => (int) $contact['client_id'], // Link to parent client
            'entity_type' => 'client',
            'entity_id' => (int) $contact['client_id'],
            'name' => $name . ($clientName ? ' (' . $clientName . ')' : ''),
            'display_name' => $name . ($clientName ? ' - ' . $clientName : '') . ($phone ? ' - ' . $phone : ''),
            'phone' => $phone,
            'email' => $contact['email'] ?? '',
            'address' => '',
            'source_system' => 'Client Contact',
            'metadata' => [
                'contact_id' => (int) $contact['id'],
                'client_name' => $clientName,
            ]
        ];
    }

    /**
     * Get entity transaction history across all systems
     * 
     * @param string $entityType Entity type (client, worker, cms_customer)
     * @param int $entityId Entity ID
     * @return array Transaction history
     */
    public function getEntityTransactionHistory(string $entityType, int $entityId): array
    {
        $history = [];
        
        // Get POS sales
        $salesStmt = $this->pdo->prepare("
            SELECT 
                s.id,
                s.sale_number,
                s.sale_timestamp,
                s.total_amount,
                s.subtotal_amount,
                s.discount_total,
                s.tax_total,
                s.payment_status,
                s.sale_status,
                st.store_name,
                u.full_name as cashier_name,
                'POS' as source_system
            FROM pos_sales s
            LEFT JOIN pos_stores st ON s.store_id = st.id
            LEFT JOIN users u ON s.cashier_id = u.id
            WHERE s.entity_type = :entity_type AND s.entity_id = :entity_id
               OR (s.entity_type IS NULL AND s.customer_id = :entity_id AND :entity_type = 'client')
            ORDER BY s.sale_timestamp DESC
            LIMIT 100
        ");
        $salesStmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ]);
        $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sales as $sale) {
            $history[] = [
                'id' => (int) $sale['id'],
                'transaction_number' => $sale['sale_number'],
                'date' => $sale['sale_timestamp'],
                'amount' => (float) $sale['total_amount'],
                'subtotal' => (float) $sale['subtotal_amount'],
                'discount' => (float) $sale['discount_total'],
                'tax' => (float) $sale['tax_total'],
                'status' => $sale['sale_status'],
                'payment_status' => $sale['payment_status'],
                'store' => $sale['store_name'],
                'cashier' => $sale['cashier_name'],
                'source_system' => 'POS',
                'type' => 'sale'
            ];
        }
        
        // Get CMS orders if entity is CMS customer
        if ($entityType === 'cms_customer') {
            try {
                $tableExists = $this->pdo->query("SHOW TABLES LIKE 'cms_orders'")->rowCount() > 0;
                if ($tableExists) {
                    $ordersStmt = $this->pdo->prepare("
                        SELECT 
                            id,
                            order_number,
                            order_date,
                            total_amount,
                            status,
                            'CMS' as source_system
                        FROM cms_orders
                        WHERE customer_id = :entity_id
                        ORDER BY order_date DESC
                        LIMIT 50
                    ");
                    $ordersStmt->execute([':entity_id' => $entityId]);
                    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($orders as $order) {
                        $history[] = [
                            'id' => (int) $order['id'],
                            'transaction_number' => $order['order_number'],
                            'date' => $order['order_date'],
                            'amount' => (float) $order['total_amount'],
                            'status' => $order['status'],
                            'source_system' => 'CMS',
                            'type' => 'order'
                        ];
                    }
                }
            } catch (PDOException $e) {
                error_log('[UnifiedEntitySearch] Error fetching CMS orders: ' . $e->getMessage());
            }
        }
        
        // Get ABBIS field reports if entity is client
        if ($entityType === 'client') {
            try {
                $tableExists = $this->pdo->query("SHOW TABLES LIKE 'field_reports'")->rowCount() > 0;
                if ($tableExists) {
                    $reportsStmt = $this->pdo->prepare("
                        SELECT 
                            id,
                            report_id,
                            report_date,
                            total_income,
                            'ABBIS' as source_system
                        FROM field_reports
                        WHERE client_id = :entity_id
                        ORDER BY report_date DESC
                        LIMIT 50
                    ");
                    $reportsStmt->execute([':entity_id' => $entityId]);
                    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($reports as $report) {
                        $history[] = [
                            'id' => (int) $report['id'],
                            'transaction_number' => $report['report_id'],
                            'date' => $report['report_date'],
                            'amount' => (float) $report['total_income'],
                            'source_system' => 'ABBIS',
                            'type' => 'field_report'
                        ];
                    }
                }
            } catch (PDOException $e) {
                error_log('[UnifiedEntitySearch] Error fetching field reports: ' . $e->getMessage());
            }
        }
        
        // Sort by date descending
        usort($history, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        return $history;
    }

    /**
     * Get entity purchase statistics
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Purchase statistics
     */
    public function getEntityPurchaseStats(string $entityType, int $entityId): array
    {
        $stats = [
            'total_transactions' => 0,
            'total_spent' => 0.0,
            'avg_transaction_value' => 0.0,
            'first_purchase' => null,
            'last_purchase' => null,
            'top_products' => [],
        ];
        
        // Get POS sales statistics
        $salesStmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(total_amount), 0) as total_spent,
                COALESCE(AVG(total_amount), 0) as avg_transaction_value,
                MIN(sale_timestamp) as first_purchase,
                MAX(sale_timestamp) as last_purchase
            FROM pos_sales
            WHERE (entity_type = :entity_type AND entity_id = :entity_id)
               OR (entity_type IS NULL AND customer_id = :entity_id AND :entity_type = 'client')
              AND sale_status = 'completed'
        ");
        $salesStmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ]);
        $salesStats = $salesStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($salesStats) {
            $stats['total_transactions'] = (int) $salesStats['total_transactions'];
            $stats['total_spent'] = (float) $salesStats['total_spent'];
            $stats['avg_transaction_value'] = (float) $salesStats['avg_transaction_value'];
            $stats['first_purchase'] = $salesStats['first_purchase'];
            $stats['last_purchase'] = $salesStats['last_purchase'];
        }
        
        // Get top products
        $productsStmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.sku,
                SUM(si.quantity) as total_quantity,
                SUM(si.line_total) as total_revenue
            FROM pos_sale_items si
            INNER JOIN pos_sales s ON si.sale_id = s.id
            INNER JOIN pos_products p ON si.product_id = p.id
            WHERE (s.entity_type = :entity_type AND s.entity_id = :entity_id)
               OR (s.entity_type IS NULL AND s.customer_id = :entity_id AND :entity_type = 'client')
              AND s.sale_status = 'completed'
            GROUP BY p.id, p.name, p.sku
            ORDER BY total_quantity DESC
            LIMIT 10
        ");
        $productsStmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ]);
        $topProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stats['top_products'] = array_map(function ($product) {
            return [
                'product_id' => (int) $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'total_quantity' => (float) $product['total_quantity'],
                'total_revenue' => (float) $product['total_revenue'],
            ];
        }, $topProducts);
        
        return $stats;
    }

    /**
     * Get entity by type and ID
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array|null Entity data or null if not found
     */
    public function getEntityById(string $entityType, int $entityId): ?array
    {
        try {
            switch ($entityType) {
                case 'client':
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            id,
                            client_name as name,
                            COALESCE(contact_person, '') as contact_person,
                            COALESCE(contact_number, '') as phone,
                            COALESCE(email, '') as email,
                            COALESCE(address, '') as address,
                            'client' as entity_type,
                            'ABBIS Client' as source_system
                        FROM clients
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([':id' => $entityId]);
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $client ? $this->formatClientEntity($client) : null;
                    
                case 'cms_customer':
                    $tableExists = $this->pdo->query("SHOW TABLES LIKE 'cms_customers'")->rowCount() > 0;
                    if (!$tableExists) {
                        return null;
                    }
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            id,
                            COALESCE(first_name, '') as first_name,
                            COALESCE(last_name, '') as last_name,
                            COALESCE(phone, '') as phone,
                            COALESCE(email, '') as email,
                            COALESCE(billing_address, '') as address,
                            'cms_customer' as entity_type,
                            'CMS Customer' as source_system
                        FROM cms_customers
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([':id' => $entityId]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $customer ? $this->formatCmsCustomerEntity($customer) : null;
                    
                case 'worker':
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            id,
                            worker_name as name,
                            COALESCE(contact_number, '') as phone,
                            role,
                            'worker' as entity_type,
                            'ABBIS Worker' as source_system,
                            worker_name as display_name
                        FROM workers
                        WHERE id = :id AND status = 'active'
                        LIMIT 1
                    ");
                    $stmt->execute([':id' => $entityId]);
                    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $worker ? $this->formatWorkerEntity($worker) : null;
                    
                default:
                    return null;
            }
        } catch (PDOException $e) {
            error_log('[UnifiedEntitySearch] Error getting entity: ' . $e->getMessage());
            return null;
        }
    }
}
