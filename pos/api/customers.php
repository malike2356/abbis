<?php
/**
 * POS Customers API
 * Search and retrieve customer information
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/UnifiedEntitySearch.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDBConnection();
$entitySearch = new UnifiedEntitySearch($pdo);

try {
    if ($method === 'GET') {
        $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
        $search = $_GET['search'] ?? '';
        $limit = min((int) ($_GET['limit'] ?? 20), 50);
        
        // If entity_type and entity_id are provided, get entity details, history, or stats
        if ($entityType && $entityId > 0) {
            $action = $_GET['action'] ?? '';
            
            if ($action === 'history') {
                $history = $entitySearch->getEntityTransactionHistory($entityType, $entityId);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'transactions' => $history,
                    ]
                ]);
                exit;
            }
            
            if ($action === 'stats') {
                $stats = $entitySearch->getEntityPurchaseStats($entityType, $entityId);
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                exit;
            }
            
            // Get entity details (no action specified) - fetch entity by type and ID
            $entity = $entitySearch->getEntityById($entityType, $entityId);
            if ($entity) {
                // Get stats for the entity
                $stats = $entitySearch->getEntityPurchaseStats($entityType, $entityId);
                $entity['stats'] = $stats;
                echo json_encode(['success' => true, 'data' => $entity]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Entity not found']);
            }
            exit;
        }
        
        // Legacy support: If customer_id is provided (for backward compatibility)
        if ($customerId > 0 && !$entityType) {
            $action = $_GET['action'] ?? '';
            
            if ($action === 'history') {
                // Get customer purchase history using unified search
                $history = $entitySearch->getEntityTransactionHistory('client', $customerId);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'transactions' => $history,
                    ]
                ]);
                exit;
            }
            
            // Get customer details (legacy - try to find in clients table)
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    client_name as name,
                    contact_person,
                    contact_number as phone,
                    email,
                    address
                FROM clients
                WHERE id = :customer_id
                LIMIT 1
            ");
            $stmt->execute([':customer_id' => $customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                $stats = $entitySearch->getEntityPurchaseStats('client', $customerId);
                $formatted = [
                    'id' => (int) $customer['id'],
                    'entity_type' => 'client',
                    'entity_id' => (int) $customer['id'],
                    'name' => $customer['name'],
                    'contact_person' => $customer['contact_person'] ?? '',
                    'phone' => $customer['phone'] ?? '',
                    'email' => $customer['email'] ?? '',
                    'address' => $customer['address'] ?? '',
                    'display' => $customer['name'] . ($customer['phone'] ? ' - ' . $customer['phone'] : ''),
                    'source_system' => 'ABBIS Client',
                    'stats' => $stats
                ];
                echo json_encode(['success' => true, 'data' => $formatted]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer not found']);
            }
            exit;
        }
        
        // Unified entity search across all systems
        if (!empty($search)) {
            try {
                $entities = $entitySearch->searchEntities($search, $limit);
                echo json_encode([
                    'success' => true, 
                    'data' => $entities,
                    'debug' => defined('APP_ENV') && APP_ENV === 'development' ? [
                        'search_term' => $search,
                        'results_count' => count($entities)
                    ] : null
                ]);
            } catch (Exception $e) {
                error_log('[POS Customers API] Search error: ' . $e->getMessage());
                error_log('[POS Customers API] Search term: ' . $search);
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Search failed: ' . $e->getMessage(),
                    'data' => []
                ]);
            }
            exit;
        }
        
        // No search term
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    error_log('[POS Customers API] Error: ' . $errorMessage);
    error_log('[POS Customers API] Trace: ' . $errorTrace);
    
    // Return detailed error in development, generic in production
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo json_encode([
            'success' => false, 
            'message' => 'Unable to search customers: ' . $errorMessage,
            'trace' => $errorTrace
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unable to search customers. Please try again.']);
    }
}

