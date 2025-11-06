<?php
/**
 * Authentication System with Enhanced Security
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Auth {
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Login with security enhancements
     */
    public function login($username, $password) {
        // Check login attempts
        if ($this->isLockedOut($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked. Please try again later.'];
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Successful login
            $this->clearLoginAttempts($username);
            
            // Regenerate session ID on login
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $updateStmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return ['success' => true];
        } else {
            // Failed login
            $this->recordLoginAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        session_unset();
        session_destroy();
        redirect('login.php');
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Get user role
     */
    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Require authentication
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            redirect('login.php');
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($requiredRole) {
        $this->requireAuth();
        if ($this->getUserRole() !== $requiredRole && $this->getUserRole() !== ROLE_ADMIN) {
            http_response_code(403);
            die('Access denied. Insufficient permissions.');
        }
    }
    
    /**
     * Create new user
     */
    public function createUser($username, $email, $password, $fullName, $role = ROLE_CLERK) {
        // Validate password strength
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash, $fullName, $role]);
            return ['success' => true, 'user_id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'User creation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Ensure login_attempts table exists
     */
    private function ensureLoginAttemptsTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL,
                    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45),
                    INDEX idx_username_time (username, attempt_time)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            // Table creation failed, but we'll handle it gracefully
            error_log("Failed to create login_attempts table: " . $e->getMessage());
        }
    }
    
    /**
     * Check if account is locked out
     */
    private function isLockedOut($username) {
        try {
            // Ensure table exists before querying
            $this->ensureLoginAttemptsTable();
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                FROM login_attempts 
                WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$username, $this->lockoutDuration]);
            $result = $stmt->fetch();
            
            return ($result['attempts'] ?? 0) >= $this->maxLoginAttempts;
        } catch (PDOException $e) {
            // If table doesn't exist or query fails, allow login (fail open for usability)
            error_log("Login attempt check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record login attempt
     */
    private function recordLoginAttempt($username) {
        try {
            // Ensure table exists
            $this->ensureLoginAttemptsTable();
            
            $stmt = $this->pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
            $stmt->execute([$username, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (PDOException $e) {
            // Log error but don't fail login process
            error_log("Failed to record login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Clear login attempts
     */
    private function clearLoginAttempts($username) {
        try {
            $this->ensureLoginAttemptsTable();
            $this->pdo->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
        } catch (PDOException $e) {
            // Log error but don't fail login process
            error_log("Failed to clear login attempts: " . $e->getMessage());
        }
    }
}

// Initialize auth instance
$auth = new Auth();
?>
