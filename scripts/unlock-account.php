<?php
/**
 * Unlock Account Utility
 * Clears login attempts for a specific username
 * 
 * Usage: php scripts/unlock-account.php <username>
 * Or via web: scripts/unlock-account.php?username=admin
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Get username from command line or web request
$username = null;
if (php_sapi_name() === 'cli') {
    // Command line mode
    if (isset($argv[1])) {
        $username = $argv[1];
    } else {
        echo "Usage: php unlock-account.php <username>\n";
        exit(1);
    }
} else {
    // Web mode - check authentication
    require_once __DIR__ . '/../includes/auth.php';
    $auth = new Auth();
    
    // Require admin authentication for web access
    if (!$auth->isLoggedIn() || $_SESSION['role'] !== ROLE_ADMIN) {
        http_response_code(403);
        die('Access denied. Admin authentication required.');
    }
    
    // Get username from query parameter
    $username = $_GET['username'] ?? $_POST['username'] ?? null;
    
    if (empty($username)) {
        http_response_code(400);
        die('Username is required. Usage: unlock-account.php?username=admin');
    }
}

if (empty($username)) {
    if (php_sapi_name() === 'cli') {
        echo "Error: Username is required.\n";
        echo "Usage: php unlock-account.php <username>\n";
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username is required']);
    }
    exit(1);
}

try {
    $pdo = getDBConnection();
    
    // Ensure login_attempts table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            INDEX idx_username_time (username, attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        if (php_sapi_name() === 'cli') {
            echo "Error: User '$username' not found.\n";
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => "User '$username' not found"]);
        }
        exit(1);
    }
    
    // Get lockout status before clearing
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
        FROM login_attempts 
        WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 900 SECOND)
    ");
    $stmt->execute([$username]);
    $lockoutInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $wasLocked = ($lockoutInfo['attempts'] ?? 0) >= 5;
    
    // Clear all login attempts for this username
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
    $stmt->execute([$username]);
    $deletedCount = $stmt->rowCount();
    
    // Verify unlock
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 900 SECOND)
    ");
    $stmt->execute([$username]);
    $remainingAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'] ?? 0;
    $isUnlocked = $remainingAttempts < 5;
    
    if (php_sapi_name() === 'cli') {
        echo "Account unlock for user: $username\n";
        echo "Status: " . ($wasLocked ? "WAS LOCKED" : "Not locked") . "\n";
        echo "Deleted login attempts: $deletedCount\n";
        echo "Remaining attempts (last 15 min): $remainingAttempts\n";
        echo "Account status: " . ($isUnlocked ? "UNLOCKED" : "Still locked") . "\n";
        echo "\nUser can now login.\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Account unlocked for user '$username'",
            'username' => $username,
            'was_locked' => $wasLocked,
            'deleted_attempts' => $deletedCount,
            'remaining_attempts' => $remainingAttempts,
            'is_unlocked' => $isUnlocked
        ]);
    }
    
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

