<?php
/**
 * CMS Authentication - Separate from ABBIS
 */
class CMSAuth {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->ensureUsersTable();
    }
    
    private function ensureUsersTable() {
        try {
            $this->pdo->query("SELECT 1 FROM cms_users LIMIT 1");
        } catch (PDOException $e) {
            // Create table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS cms_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('admin','editor','author') DEFAULT 'author',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            // Create default admin (username: admin, password: admin)
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            try {
                $this->pdo->exec("INSERT IGNORE INTO cms_users (username, email, password_hash, role) VALUES ('admin', 'admin@example.com', " . $this->pdo->quote($hash) . ", 'admin')");
            } catch (PDOException $e) {
                // Table might already have data
            }
        }
    }
    
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM cms_users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['cms_user_id'] = $user['id'];
            $_SESSION['cms_username'] = $user['username'];
            $_SESSION['cms_role'] = $user['role'];
            
            // Update last login and login count
            try {
                $this->pdo->prepare("UPDATE cms_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?")->execute([$user['id']]);
            } catch (Exception $e) {
                // Ignore if columns don't exist yet
            }
            
            // Log activity
            try {
                $this->pdo->prepare("INSERT INTO cms_user_activity (user_id, action, description, ip_address, user_agent) VALUES (?, 'login', 'User logged in', ?, ?)")
                    ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
            
            return true;
        }
        return false;
    }
    
    public function logout() {
        // Log activity before logout
        if (isset($_SESSION['cms_user_id'])) {
            try {
                $this->pdo->prepare("INSERT INTO cms_user_activity (user_id, action, description, ip_address, user_agent) VALUES (?, 'logout', 'User logged out', ?, ?)")
                    ->execute([$_SESSION['cms_user_id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
        }
        
        unset($_SESSION['cms_user_id']);
        unset($_SESSION['cms_username']);
        unset($_SESSION['cms_role']);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['cms_user_id']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        $stmt = $this->pdo->prepare("SELECT * FROM cms_users WHERE id = ?");
        $stmt->execute([$_SESSION['cms_user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function isAdmin() {
        return isset($_SESSION['cms_role']) && $_SESSION['cms_role'] === 'admin';
    }
}

