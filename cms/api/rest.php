<?php
/**
 * REST API Endpoint (WordPress-inspired)
 * JSON API for headless/API access
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/crypto.php';

$pdo = getDBConnection();

// Get API key from headers or query string
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
$apiSecret = $_SERVER['HTTP_X_API_SECRET'] ?? $_GET['api_secret'] ?? null;

// Authenticate API key
$authenticated = false;
$apiKeyData = null;
if ($apiKey) {
    $stmt = $pdo->prepare("SELECT * FROM cms_api_keys WHERE status = 'active'");
    $stmt->execute();
    $candidateKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidateKeys as $candidate) {
        $storedKey = $candidate['api_key'] ?? '';
        if (Crypto::isEncrypted($storedKey)) {
            try {
                $storedKey = Crypto::decrypt($storedKey);
            } catch (RuntimeException $e) {
                error_log('Failed to decrypt API key ID ' . ($candidate['id'] ?? '?') . ': ' . $e->getMessage());
                continue;
            }
        }

        if (hash_equals($storedKey, $apiKey)) {
            $apiKeyData = $candidate;
            $apiKeyData['api_key_plain'] = $storedKey;
            break;
        }
    }

    if ($apiKeyData) {
        if ($apiKeyData['expires_at'] && strtotime($apiKeyData['expires_at']) < time()) {
            echo json_encode(['error' => 'API key has expired']);
            http_response_code(401);
            exit;
        }

        if ($apiSecret) {
            $storedSecret = $apiKeyData['api_secret'] ?? null;
            if ($storedSecret === null) {
                echo json_encode(['error' => 'API secret not configured for this key']);
                http_response_code(401);
                exit;
            }

            if (Crypto::isEncrypted($storedSecret)) {
                try {
                    $storedSecret = Crypto::decrypt($storedSecret);
                } catch (RuntimeException $e) {
                    error_log('Failed to decrypt API secret ID ' . ($apiKeyData['id'] ?? '?') . ': ' . $e->getMessage());
                    echo json_encode(['error' => 'Invalid API secret']);
                    http_response_code(401);
                    exit;
                }
            }

            if (!hash_equals($storedSecret, $apiSecret)) {
                echo json_encode(['error' => 'Invalid API secret']);
                http_response_code(401);
                exit;
            }
        }

        $authenticated = true;
        $pdo->prepare("UPDATE cms_api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$apiKeyData['id']]);
    }
}

// Check authentication
if (!$authenticated) {
    echo json_encode(['error' => 'Unauthorized. Valid API key required.']);
    http_response_code(401);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'cms/api/rest' from path
$pathParts = array_slice($pathParts, array_search('rest', $pathParts) + 1);

// Route handling
$resource = $pathParts[0] ?? 'index';
$id = $pathParts[1] ?? null;

// Get request body
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Helper function to send JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Helper function to get pagination
function getPagination() {
    $page = intval($_GET['page'] ?? 1);
    $perPage = min(intval($_GET['per_page'] ?? 10), 100);
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'per_page' => $perPage, 'offset' => $offset];
}

// Routes
try {
    switch ($resource) {
        case 'index':
            jsonResponse([
                'name' => 'CMS REST API',
                'version' => '1.0.0',
                'endpoints' => [
                    'GET /pages' => 'List all pages',
                    'GET /pages/{id}' => 'Get single page',
                    'POST /pages' => 'Create page',
                    'PUT /pages/{id}' => 'Update page',
                    'DELETE /pages/{id}' => 'Delete page',
                    'GET /posts' => 'List all posts',
                    'GET /posts/{id}' => 'Get single post',
                    'POST /posts' => 'Create post',
                    'PUT /posts/{id}' => 'Update post',
                    'DELETE /posts/{id}' => 'Delete post',
                    'GET /content-types' => 'List content types',
                    'GET /content-types/{id}' => 'Get content type',
                    'GET /views' => 'List views',
                    'GET /views/{id}' => 'Get view',
                ]
            ]);
            break;
            
        case 'pages':
            if ($method === 'GET') {
                $pagination = getPagination();
                $where = [];
                $params = [];
                
                if (isset($_GET['status'])) {
                    $where[] = "status = ?";
                    $params[] = $_GET['status'];
                }
                
                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
                
                // Get total count
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_pages $whereClause");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                // Get pages
                $stmt = $pdo->prepare("SELECT * FROM cms_pages $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $params[] = $pagination['per_page'];
                $params[] = $pagination['offset'];
                $stmt->execute($params);
                $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                jsonResponse([
                    'data' => $pages,
                    'pagination' => [
                        'page' => $pagination['page'],
                        'per_page' => $pagination['per_page'],
                        'total' => $total,
                        'total_pages' => ceil($total / $pagination['per_page'])
                    ]
                ]);
            } elseif ($method === 'GET' && $id) {
                $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE id = ?");
                $stmt->execute([$id]);
                $page = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$page) {
                    jsonResponse(['error' => 'Page not found'], 404);
                }
                
                jsonResponse(['data' => $page]);
            } elseif ($method === 'POST') {
                $title = $input['title'] ?? '';
                $slug = $input['slug'] ?? strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title));
                $content = $input['content'] ?? '';
                $status = $input['status'] ?? 'draft';
                
                if (!$title) {
                    jsonResponse(['error' => 'Title is required'], 400);
                }
                
                $stmt = $pdo->prepare("INSERT INTO cms_pages (title, slug, content, status, created_by) VALUES (?,?,?,?,?)");
                $stmt->execute([$title, $slug, $content, $status, $apiKeyData['user_id'] ?? 1]);
                $id = $pdo->lastInsertId();
                
                jsonResponse(['data' => ['id' => $id, 'message' => 'Page created successfully']], 201);
            } elseif ($method === 'PUT' && $id) {
                $title = $input['title'] ?? null;
                $content = $input['content'] ?? null;
                $status = $input['status'] ?? null;
                
                $updates = [];
                $params = [];
                
                if ($title !== null) {
                    $updates[] = "title = ?";
                    $params[] = $title;
                }
                if ($content !== null) {
                    $updates[] = "content = ?";
                    $params[] = $content;
                }
                if ($status !== null) {
                    $updates[] = "status = ?";
                    $params[] = $status;
                }
                
                if (empty($updates)) {
                    jsonResponse(['error' => 'No fields to update'], 400);
                }
                
                $params[] = $id;
                $stmt = $pdo->prepare("UPDATE cms_pages SET " . implode(", ", $updates) . " WHERE id = ?");
                $stmt->execute($params);
                
                jsonResponse(['data' => ['message' => 'Page updated successfully']]);
            } elseif ($method === 'DELETE' && $id) {
                $pdo->prepare("DELETE FROM cms_pages WHERE id = ?")->execute([$id]);
                jsonResponse(['data' => ['message' => 'Page deleted successfully']]);
            }
            break;
            
        case 'posts':
            if ($method === 'GET') {
                $pagination = getPagination();
                $where = [];
                $params = [];
                
                if (isset($_GET['status'])) {
                    $where[] = "status = ?";
                    $params[] = $_GET['status'];
                }
                
                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
                
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_posts $whereClause");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT * FROM cms_posts $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $params[] = $pagination['per_page'];
                $params[] = $pagination['offset'];
                $stmt->execute($params);
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                jsonResponse([
                    'data' => $posts,
                    'pagination' => [
                        'page' => $pagination['page'],
                        'per_page' => $pagination['per_page'],
                        'total' => $total,
                        'total_pages' => ceil($total / $pagination['per_page'])
                    ]
                ]);
            } elseif ($method === 'GET' && $id) {
                $stmt = $pdo->prepare("SELECT * FROM cms_posts WHERE id = ?");
                $stmt->execute([$id]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$post) {
                    jsonResponse(['error' => 'Post not found'], 404);
                }
                
                jsonResponse(['data' => $post]);
            }
            break;
            
        case 'content-types':
            if ($method === 'GET') {
                $stmt = $pdo->query("SELECT * FROM cms_content_types WHERE status = 'active' ORDER BY label");
                $contentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                jsonResponse(['data' => $contentTypes]);
            } elseif ($method === 'GET' && $id) {
                $stmt = $pdo->prepare("SELECT * FROM cms_content_types WHERE id = ?");
                $stmt->execute([$id]);
                $contentType = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$contentType) {
                    jsonResponse(['error' => 'Content type not found'], 404);
                }
                
                // Get fields
                $fieldsStmt = $pdo->prepare("SELECT * FROM cms_custom_fields WHERE content_type_id = ? ORDER BY display_order");
                $fieldsStmt->execute([$id]);
                $contentType['fields'] = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                jsonResponse(['data' => $contentType]);
            }
            break;
            
        case 'views':
            if ($method === 'GET') {
                $stmt = $pdo->query("SELECT * FROM cms_views WHERE status = 'active' ORDER BY label");
                $views = $stmt->fetchAll(PDO::FETCH_ASSOC);
                jsonResponse(['data' => $views]);
            } elseif ($method === 'GET' && $id) {
                $stmt = $pdo->prepare("SELECT * FROM cms_views WHERE id = ? OR machine_name = ?");
                $stmt->execute([$id, $id]);
                $view = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$view) {
                    jsonResponse(['error' => 'View not found'], 404);
                }
                
                $view['query_config'] = json_decode($view['query_config'], true);
                $view['style_config'] = json_decode($view['style_config'], true);
                
                jsonResponse(['data' => $view]);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Resource not found'], 404);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

