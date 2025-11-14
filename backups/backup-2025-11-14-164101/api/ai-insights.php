<?php
/**
 * AI Insights API
 * Handles AI assistant chat and insights requests
 */

// Start output buffering to catch any unexpected output
ob_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Set JSON header early
header('Content-Type: application/json');

// Clear any output that might have been generated during includes
ob_clean();

try {
require_once __DIR__ . '/../includes/AI/bootstrap.php';
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[AI Insights] Failed to load AI bootstrap: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'AI service is not available. Please check server configuration.',
        'category' => 'bootstrap_error',
        'detail' => APP_ENV === 'development' ? $e->getMessage() : null,
    ], 500);
    exit;
}

// Check authentication - return JSON error if not authenticated (for API endpoints)
if (!$auth->isLoggedIn()) {
    ob_end_clean();
    jsonResponse([
        'success' => false,
        'message' => 'Authentication required.',
        'category' => 'auth_error',
    ], 401);
    exit;
}

// Check permission - allow all authenticated users by default
// Permission check is lenient here - we want AI assistant available to all users
// Individual features can enforce stricter permissions if needed
if (!defined('AI_PERMISSION_KEY')) {
    define('AI_PERMISSION_KEY', 'ai.assistant');
}

// Allow all authenticated users to use AI assistant
// If you want to restrict access, uncomment the permission check below
try {
    // Optional: Check permission (currently disabled to allow all authenticated users)
    // Uncomment the lines below to enforce permission-based access:
    /*
    if (!$auth->userHasPermission(AI_PERMISSION_KEY)) {
        ob_end_clean();
        jsonResponse([
            'success' => false,
            'message' => 'You do not have permission to use AI features. Please contact your administrator.',
            'category' => 'permission_error',
        ], 403);
        exit;
    }
    */
} catch (Throwable $e) {
    // If permission check fails, log but allow access (for better UX)
    error_log('[AI Insights] Permission check error: ' . $e->getMessage());
    // Continue - allow access if permission system fails
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'assistant_chat';

$payload = [];

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
}

$payload = array_merge($payload, $_REQUEST);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? null;

// Initialize manager with error handling
try {
$manager = ai_insight_manager();
    
    // Check if any providers are actually registered
    // We can't directly check the bus, but we can check provider configs
    try {
        $pdo = getDBConnection();
        $providerCheckStmt = $pdo->query("
            SELECT COUNT(*) as enabled_count 
            FROM ai_provider_config 
            WHERE is_enabled = 1
        ");
        $providerCount = $providerCheckStmt ? (int) $providerCheckStmt->fetchColumn() : 0;
        
        if ($providerCount === 0) {
            ob_end_clean();
            jsonResponse([
                'success' => false,
                'message' => 'No AI providers are configured. Please go to AI Governance (modules/ai-governance.php) and set up at least one provider (OpenAI, DeepSeek, Gemini, or Ollama) with valid API keys.',
                'category' => 'no_providers',
                'detail' => 'No enabled providers found in ai_provider_config table.',
            ], 503);
            exit;
        }
    } catch (PDOException $e) {
        // If we can't check, continue - the manager will handle it
        error_log('[AI Insights] Could not check provider count: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[AI Insights] Failed to initialize AI manager: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonResponse([
        'success' => false,
        'message' => 'AI service initialization failed. Please check AI provider configuration in AI Governance.',
        'category' => 'initialization_error',
        'detail' => APP_ENV === 'development' ? $e->getMessage() : null,
    ], 500);
    exit;
}

switch ($action) {
    case 'assistant_chat':
        handleAssistantChat($manager, $payload, $userId, $userRole);
        break;

    case 'insight_summary':
        handleAssistantChat($manager, $payload, $userId, $userRole, 'insight_summary');
        break;

    default:
        ob_end_clean();
        jsonResponse([
            'success' => false,
            'message' => 'Unsupported AI action.',
        ], 400);
        exit;
}

function handleAssistantChat(AIInsightManager $manager, array $payload, int $userId, ?string $userRole, string $action = 'assistant_chat'): void
{
    $messages = normaliseMessages($payload['messages'] ?? [], $payload['prompt'] ?? null);

    if (empty($messages)) {
        ob_end_clean();
        jsonResponse([
            'success' => false,
            'message' => 'Messages array required.',
        ], 422);
        return;
    }

    $entityType = $payload['entity_type'] ?? null;
    $entityId = empty($payload['entity_id']) ? null : (int) $payload['entity_id'];

    $request = [
        'messages' => $messages,
        'user_id' => $userId,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
    ];

    // Safely determine organisation name (with error handling)
    $organisationName = 'ABBIS Organisation'; // Default fallback
    try {
        $organisationName = determineOrganisationName();
    } catch (Throwable $e) {
        error_log('[AI Insights] Failed to determine organisation name: ' . $e->getMessage());
        // Use default fallback
    }

    $options = [
        'user_id' => $userId,
        'role' => $userRole,
        'organisation_name' => $organisationName,
        'action' => $action,
        'messages' => $messages,
    ];

    if (!empty($payload['provider'])) {
        $options['provider'] = $payload['provider'];
    }

    // Check if providers are configured before attempting request
    try {
        $pdo = getDBConnection();
        $checkStmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM ai_provider_config 
            WHERE is_enabled = 1 
            AND settings_json LIKE '%api_key%'
        ");
        $providerCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($providerCount === 0) {
            ob_end_clean();
            jsonResponse([
                'success' => false,
                'message' => '❌ **No AI Providers Configured**\n\n🔧 **Quick Setup:**\n1. Go to: `modules/admin/configure-ai-keys.php`\n2. Click "Configure AI Providers"\n3. This will set up OpenAI and DeepSeek automatically\n\n📋 **Manual Setup:**\n1. Go to: `modules/ai-governance.php`\n2. Select a provider (OpenAI, DeepSeek, Gemini, or Ollama)\n3. Enter your API key\n4. Enable the provider\n5. Save settings',
                'category' => 'no_providers',
                'detail' => APP_ENV === 'development' ? 'No enabled providers with API keys found in database' : null,
            ], 503);
            return;
        }
    } catch (Throwable $e) {
        // If check fails, continue and let the manager handle it
        error_log('[AI Insights] Provider check failed: ' . $e->getMessage());
    }

    try {
        $response = $manager->runAssistant($request, $options);
    } catch (AIProviderException $e) {
        $category = $e->getCategory();
        $message = $e->getMessage();
        
        // Provide user-friendly messages for common errors
        if ($category === AIProviderException::CODE_INTERNAL && 
            (strpos($message, 'No AI providers registered') !== false || 
             strpos($message, 'No providers') !== false)) {
            $message = '❌ **No AI Providers Configured**\n\n🔧 **Quick Setup:**\n1. Go to: `modules/admin/configure-ai-keys.php`\n2. Click "Configure AI Providers"\n3. This will set up OpenAI and DeepSeek automatically\n\n📋 **Manual Setup:**\n1. Go to: `modules/ai-governance.php`\n2. Select a provider and enter API key\n3. Enable and save';
        } elseif ($category === AIProviderException::CODE_AUTH) {
            $message = '❌ **AI Provider Authentication Failed**\n\nYour API keys may be invalid or expired.\n\n🔧 **Fix:**\n1. Go to: `modules/ai-governance.php`\n2. Check your API keys\n3. Update them if necessary\n4. Ensure providers are enabled';
        } elseif ($category === AIProviderException::CODE_RATE_LIMIT) {
            $message = '⏱️ **Rate Limit Exceeded**\n\nYou have reached the rate limit for AI requests. Please try again later.';
        } elseif ($category === AIProviderException::CODE_SERVICE) {
            // Check if it's actually a "no providers" issue disguised as service unavailable
            if (strpos($message, 'No AI providers') !== false || 
                strpos($message, 'All AI providers failed') !== false ||
                strpos($message, 'No providers') !== false ||
                strpos($message, 'No AI providers registered') !== false) {
                $message = '❌ **No AI Providers Configured**\n\n🔧 **Quick Setup:**\n1. Go to: `modules/admin/configure-ai-keys.php`\n2. Click "Configure AI Providers"\n3. This will set up OpenAI and DeepSeek automatically\n\n📋 **Manual Setup:**\n1. Go to: `modules/ai-governance.php`\n2. Select a provider and enter API key\n3. Enable and save';
            } else {
                $message = '❌ **AI Service Temporarily Unavailable**\n\nThis usually means:\n• API keys are invalid\n• Provider service is down\n• Network connectivity issue\n\n🔧 **Fix:**\n1. Check provider status in `modules/ai-governance.php`\n2. Verify API keys are correct\n3. Try again in a few moments';
            }
        }
        
        $status = match ($category) {
            AIProviderException::CODE_RATE_LIMIT => 429,
            AIProviderException::CODE_AUTH => 401,
            AIProviderException::CODE_SERVICE => 503,
            AIProviderException::CODE_INTERNAL => 500,
            default => 500,
        };

        // Clean output buffer before sending error response
        ob_end_clean();

        jsonResponse([
            'success' => false,
            'message' => $message,
            'category' => $category,
            'detail' => APP_ENV === 'development' ? $e->getMessage() : null,
        ], $status);
        return;
    } catch (Throwable $e) {
        // Catch any other unexpected errors
        error_log('[AI Insights] Unexpected error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        // Clean output buffer before sending error response
        ob_end_clean();
        
        $userMessage = 'An error occurred while processing your request.';
        if (APP_ENV === 'development') {
            $userMessage .= ' Error: ' . $e->getMessage();
        } else {
            $userMessage .= ' Please check AI provider configuration in AI Governance.';
        }
        
        jsonResponse([
            'success' => false,
            'message' => $userMessage,
            'category' => 'error',
            'detail' => APP_ENV === 'development' ? $e->getTraceAsString() : null,
        ], 500);
        return;
    }

    // Clean output buffer before sending response
    ob_end_clean();

    jsonResponse([
        'success' => true,
        'data' => [
            'messages' => $response->getMessages(),
            'provider' => $response->getProviderKey(),
            'usage' => [
                'prompt_tokens' => $response->getPromptTokens(),
                'completion_tokens' => $response->getCompletionTokens(),
                'total_tokens' => $response->getTotalTokens(),
                'latency_ms' => $response->getLatencyMs(),
                'from_cache' => $response->isFromCache(),
            ],
        ],
    ]);
}

function normaliseMessages($messages, ?string $promptFallback): array
{
    if (!is_array($messages)) {
        $messages = [];
    }

    if ($promptFallback && empty($messages)) {
        $messages[] = ['role' => 'user', 'content' => $promptFallback];
    }

    $normalized = [];

    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = $message['role'] ?? null;
        $content = $message['content'] ?? null;

        if (!in_array($role, ['user', 'assistant', 'system'], true) || $content === null) {
            continue;
        }

        $normalized[] = [
            'role' => $role,
            'content' => (string) $content,
        ];
    }

    return $normalized;
}

function determineOrganisationName(): string
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }

        // Try system_config table first (most common)
        try {
            $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetchColumn();
            if ($value && trim($value) !== '') {
                $cache = trim((string) $value);
                return $cache;
            }
        } catch (PDOException $e) {
            // Table might not exist or column might not exist, try next option
            error_log('[AI Insights] system_config query failed: ' . $e->getMessage());
        }

        // Try company_profile table (if it exists)
        try {
            // Check if table exists first
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'company_profile'");
            if ($checkStmt && $checkStmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT company_name FROM company_profile LIMIT 1");
        $value = $stmt ? $stmt->fetchColumn() : false;
                if ($value && trim($value) !== '') {
                    $cache = trim((string) $value);
            return $cache;
        }
            }
        } catch (PDOException $e) {
            // Table doesn't exist or query failed, continue to fallback
            error_log('[AI Insights] company_profile query failed: ' . $e->getMessage());
        }

    } catch (Throwable $e) {
        // Log but don't throw - we'll use fallback
        error_log('[AI Insights] determineOrganisationName error: ' . $e->getMessage());
    }

    // Fallback to environment variable or default
    $cache = getenv('APP_COMPANY_NAME') ?: 'ABBIS Organisation';
    return $cache;
}


