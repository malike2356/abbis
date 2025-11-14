<?php
/**
 * Authentication System with Enhanced Security
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/super-admin.php';
require_once __DIR__ . '/access-control.php';

class Auth {
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes
    private $accessControl;
    private $accessLogTableEnsured = false;
    private $ldapConfig = [];
    private $ldapAvailable = false;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->accessControl = AccessControl::getInstance();
        $this->ldapConfig = require __DIR__ . '/../config/ldap.php';
        $this->ldapAvailable = !empty($this->ldapConfig['enabled']) && function_exists('ldap_connect');
    }
    
    /**
     * Login with security enhancements
     * Supports Super Admin bypass in development mode
     */
    public function login($username, $password) {
        // Super Admin Bypass (Development/Maintenance only)
        if (isSuperAdminBypassEnabled() && validateSuperAdminCredentials($username, $password)) {
            error_log("SUPER ADMIN BYPASS: Login from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return $this->finalizeSuperAdminLogin($username);
        }
        
        if ($this->isLockedOut($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked. Please try again later.'];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($user && isset($user['is_active']) && !$user['is_active']) {
            return ['success' => false, 'message' => 'Account is disabled. Contact your administrator.'];
        }

        if ($this->ldapAvailable) {
            $ldapResult = $this->ldapAuthenticate($username, $password);
            if ($ldapResult['success']) {
                if (!$user) {
                    if (!empty($this->ldapConfig['auto_provision'])) {
                        $user = $this->provisionLdapUser($username, $ldapResult['attributes']);
                    } else {
                        return ['success' => false, 'message' => 'LDAP authentication succeeded but user is not provisioned in ABBIS.'];
                    }
                }

                if (!$user) {
                    return ['success' => false, 'message' => 'Unable to provision LDAP user. Contact administrator.'];
                }

                $this->clearLoginAttempts($username);
                return $this->finalizeLogin($user, 'ldap');
            }

            if (empty($this->ldapConfig['allow_local_fallback'])) {
                $this->recordLoginAttempt($username);
                return ['success' => false, 'message' => $ldapResult['message'] ?? 'Invalid credentials'];
            }
        }

        if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            $this->clearLoginAttempts($username);
            return $this->finalizeLogin($user, 'local');
        }

        $this->recordLoginAttempt($username);
        return ['success' => false, 'message' => 'Invalid username or password'];
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
     * Super Admin bypass is always considered logged in
     */
    public function isLoggedIn() {
        // Super Admin bypass check
        if ($this->isSuperAdmin()) {
            return true;
        }
        
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
     * Check if current user is Super Admin (development bypass)
     */
    public function isSuperAdmin(): bool {
        if (!isSuperAdminBypassEnabled()) {
            return false;
        }
        
        return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN;
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
     * Require specific role (or one of several roles)
     * Super Admin bypasses all role checks
     *
     * @param string|array $requiredRole
     */
    public function requireRole($requiredRole) {
        $this->requireAuth();

        // Super Admin bypasses all role checks
        if ($this->isSuperAdmin()) {
            return;
        }

        $allowedRoles = is_array($requiredRole) ? $requiredRole : [$requiredRole];
        $allowedRoles[] = ROLE_ADMIN; // Admins always have access.
        $allowedRoles = array_unique(array_filter($allowedRoles));

        $userRole = $this->getUserRole();
        if (!in_array($userRole, $allowedRoles, true)) {
            $this->logAccessEvent(null, false, 'role:' . implode(',', $allowedRoles));
            $this->denyAccess();
        }
    }
    
    /**
     * Require a logical permission defined in the access-control matrix.
     * Super Admin bypasses all permission checks
     */
    public function requirePermission(string $permissionKey) {
        $this->requireAuth();

        // Super Admin bypasses all permission checks
        if ($this->isSuperAdmin()) {
            return;
        }

        if (!$this->userHasPermission($permissionKey)) {
            $this->logAccessEvent($permissionKey, false);
            $this->denyAccess();
        }
    }
    
    /**
     * Determine if the authenticated user has a given permission.
     * Super Admin always returns true
     */
    public function userHasPermission(string $permissionKey): bool {
        // Super Admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->accessControl->currentUserCan($permissionKey);
    }

    /**
     * Enforce access based on the current page.
     * Super Admin bypasses all page access checks
     */
    public function enforcePageAccess(?string $page = null): void {
        $this->requireAuth();

        // Super Admin bypasses all page access checks
        if ($this->isSuperAdmin()) {
            return;
        }

        $page = $page ?: basename($_SERVER['PHP_SELF'] ?? '');
        if (!$this->accessControl->ensurePageAccess($page, $this->getUserRole())) {
            $this->logAccessEvent(null, false, 'page:' . $page);
            $this->denyAccess();
        }
    }

    /**
     * Expose underlying access control service.
     */
    public function getAccessControl(): AccessControl {
        return $this->accessControl;
    }

    /**
     * Abort current request with 403 response.
     */
    private function denyAccess(string $message = 'Access denied. Insufficient permissions.') {
        http_response_code(403);

        $homeUrl = app_url('modules/dashboard.php');
        $supportEmail = getenv('ABBIS_SUPPORT_EMAIL') ?: 'support@abbis.com';

        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied · ' . APP_NAME . '</title>
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            margin: 0;
            font-family: "Segoe UI", Roboto, -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif;
            background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(99,102,241,0.18));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
            max-width: 460px;
            width: 100%;
            padding: 36px;
            text-align: center;
            color: #0f172a;
        }
        .emoji {
            font-size: 56px;
            line-height: 1;
            margin-bottom: 16px;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 28px;
            letter-spacing: -0.02em;
        }
        p {
            margin: 0 0 22px;
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        a.button, button.button {
            appearance: none;
            border: none;
            border-radius: 999px;
            padding: 12px 22px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        a.button.primary {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 12px 24px rgba(37,99,235,0.18);
        }
        a.button.primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 32px rgba(37,99,235,0.22);
        }
        button.button.secondary {
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
        }
        button.button.secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(37,99,235,0.18);
        }
        small {
            display: block;
            margin-top: 28px;
            font-size: 12px;
            color: rgba(15, 23, 42, 0.6);
        }
        @media (max-width: 480px) {
            .card {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="emoji">🔒</div>
        <h1>Access Restricted</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <div class="actions">
            <a href="' . htmlspecialchars($homeUrl) . '" class="button primary">Go to Dashboard</a>
            <button class="button secondary" onclick="history.back();">Go Back</button>
        </div>
        <small>If you believe this is a mistake, please contact your administrator or email ' . htmlspecialchars($supportEmail) . '.</small>
    </div>
</body>
</html>';
        exit;
    }

    /**
     * Record access control decisions for auditing.
     */
    private function logAccessEvent(?string $permissionKey, bool $allowed, ?string $context = null): void {
        try {
            $this->ensureAccessLogTable();

            $stmt = $this->pdo->prepare("
                INSERT INTO access_control_logs (
                    user_id,
                    username,
                    role,
                    permission_key,
                    context_details,
                    page,
                    is_allowed,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $_SESSION['username'] ?? null,
                $_SESSION['role'] ?? null,
                $permissionKey,
                $context,
                $_SERVER['REQUEST_URI'] ?? null,
                $allowed ? 1 : 0,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('Access log insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Finalize Super Admin login (development bypass)
     */
    private function finalizeSuperAdminLogin(string $username): array {
        if (!isSuperAdminBypassEnabled()) {
            return ['success' => false, 'message' => 'Super Admin bypass is disabled'];
        }
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        session_regenerate_id(true);
        
        // Set Super Admin session
        $_SESSION['user_id'] = 0; // Special ID for super admin
        $_SESSION['username'] = $username;
        $_SESSION['role'] = ROLE_SUPER_ADMIN;
        $_SESSION['full_name'] = 'Super Admin (Development)';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['super_admin'] = true;
        $_SESSION['auth_source'] = 'super_admin_bypass';
        
        error_log("SUPER ADMIN BYPASS: Session created for {$username} from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        return [
            'success' => true,
            'message' => 'Super Admin login successful (Development Mode)',
            'role' => ROLE_SUPER_ADMIN,
            'super_admin' => true,
            'source' => 'super_admin_bypass'
        ];
    }
    
    /**
     * Finalize login process and set session state.
     */
    private function finalizeLogin(array $user, string $source = 'local'): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['auth_source'] = $source;

        try {
            $updateStmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
        } catch (PDOException $e) {
            error_log('Failed to update last_login: ' . $e->getMessage());
        }

        return ['success' => true, 'source' => $source];
    }

    /**
     * Attempt LDAP authentication.
     */
    private function ldapAuthenticate(string $username, string $password): array
    {
        if (!$this->ldapAvailable) {
            return ['success' => false];
        }

        if ($password === '') {
            return ['success' => false, 'message' => 'Password required'];
        }

        $connection = @ldap_connect($this->ldapConfig['host'], $this->ldapConfig['port']);
        if (!$connection) {
            return ['success' => false, 'message' => 'Cannot connect to directory server'];
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        if (!empty($this->ldapConfig['timeout'])) {
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, (int) $this->ldapConfig['timeout']);
        }

        if (!empty($this->ldapConfig['use_tls'])) {
            if (!@ldap_start_tls($connection)) {
                $error = ldap_error($connection);
                ldap_close($connection);
                return ['success' => false, 'message' => 'Failed to start LDAP TLS: ' . $error];
            }
        }

        $bindDnTemplate = $this->ldapConfig['bind_dn'] ?? '{username}';
        $bindDn = str_replace('{username}', $username, $bindDnTemplate);

        $bind = @ldap_bind($connection, $bindDn, $password);
        if (!$bind) {
            $error = ldap_error($connection);
            ldap_close($connection);
            return ['success' => false, 'message' => $error ?: 'LDAP authentication failed'];
        }

        $attributes = [];
        $requestedAttributes = array_values(array_filter($this->ldapConfig['attributes'] ?? []));

        $searchBaseTemplate = $this->ldapConfig['search_base'] ?? '';
        $searchFilterTemplate = $this->ldapConfig['search_filter'] ?? '(sAMAccountName={username})';

        if ($searchBaseTemplate !== '') {
            $searchBase = str_replace('{username}', $username, $searchBaseTemplate);
            $searchFilter = str_replace('{username}', $this->escapeLdapValue($username), $searchFilterTemplate);

            $search = @ldap_search($connection, $searchBase, $searchFilter, $requestedAttributes ?: null, 0, 1);
            if ($search !== false) {
                $entries = ldap_get_entries($connection, $search);
                if ($entries !== false && $entries['count'] > 0) {
                    $entry = $entries[0];
                    foreach ($requestedAttributes as $attr) {
                        $lowerAttr = strtolower($attr);
                        if (isset($entry[$lowerAttr]) && $entry[$lowerAttr]['count'] > 0) {
                            $attributes[$lowerAttr] = $entry[$lowerAttr][0];
                        }
                    }
                }
            }
        }

        ldap_unbind($connection);

        return [
            'success' => true,
            'attributes' => $attributes,
        ];
    }

    /**
     * Provision an LDAP user locally if permitted.
     */
    private function provisionLdapUser(string $username, array $attributes): ?array
    {
        $roles = $this->accessControl->getRoleLabels();
        $defaultRole = $this->ldapConfig['default_role'] ?? ROLE_CLERK;
        if (!isset($roles[$defaultRole])) {
            $defaultRole = ROLE_CLERK;
        }

        $emailAttr = strtolower($this->ldapConfig['attributes']['email'] ?? '');
        $fullNameAttr = strtolower($this->ldapConfig['attributes']['full_name'] ?? '');
        $firstNameAttr = strtolower($this->ldapConfig['attributes']['first_name'] ?? '');
        $lastNameAttr = strtolower($this->ldapConfig['attributes']['last_name'] ?? '');

        $email = $attributes[$emailAttr] ?? (str_contains($username, '@') ? $username : null);
        $fullName = $attributes[$fullNameAttr] ?? trim(($attributes[$firstNameAttr] ?? '') . ' ' . ($attributes[$lastNameAttr] ?? ''));
        $fullName = $fullName ?: $username;

        $randomPassword = bin2hex(random_bytes(32));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $username,
                $email,
                $passwordHash,
                $fullName,
                $defaultRole,
            ]);
        } catch (PDOException $e) {
            // Duplicate or other error; attempt to fetch existing record
            error_log('LDAP provisioning notice: ' . $e->getMessage());
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function escapeLdapValue(string $value): string
    {
        $search = ['\\', '*', '(', ')', "\0"];
        $replace = ['\\5c', '\\2a', '\\28', '\\29', '\\00'];
        return str_replace($search, $replace, $value);
    }

    /**
     * Ensure audit table exists.
     */
    private function ensureAccessLogTable(): void {
        if ($this->accessLogTableEnsured) {
            return;
        }

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS access_control_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    username VARCHAR(100) NULL,
                    role VARCHAR(50) NULL,
                    permission_key VARCHAR(100) NULL,
                    context_details VARCHAR(255) NULL,
                    page VARCHAR(255) NULL,
                    is_allowed TINYINT(1) DEFAULT 0,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_permission (permission_key),
                    INDEX idx_role (role),
                    INDEX idx_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $this->accessLogTableEnsured = true;
        } catch (PDOException $e) {
            error_log('Failed to ensure access_control_logs table: ' . $e->getMessage());
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
    
    /**
     * Get lockout status for a username (public method for admin use)
     */
    public function getLockoutStatus($username) {
        try {
            $this->ensureLoginAttemptsTable();
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as attempts, 
                    MAX(attempt_time) as last_attempt,
                    MIN(attempt_time) as first_attempt
                FROM login_attempts 
                WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$username, $this->lockoutDuration]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $attempts = (int)($result['attempts'] ?? 0);
            $isLocked = $attempts >= $this->maxLoginAttempts;
            $remainingAttempts = max(0, $this->maxLoginAttempts - $attempts);
            
            // Calculate time until unlock
            $timeUntilUnlock = null;
            if ($isLocked && isset($result['last_attempt'])) {
                $lastAttempt = strtotime($result['last_attempt']);
                $unlockTime = $lastAttempt + $this->lockoutDuration;
                $timeUntilUnlock = max(0, $unlockTime - time());
            }
            
            return [
                'is_locked' => $isLocked,
                'attempts' => $attempts,
                'max_attempts' => $this->maxLoginAttempts,
                'remaining_attempts' => $remainingAttempts,
                'lockout_duration' => $this->lockoutDuration,
                'last_attempt' => $result['last_attempt'] ?? null,
                'first_attempt' => $result['first_attempt'] ?? null,
                'time_until_unlock' => $timeUntilUnlock,
                'unlock_time' => $timeUntilUnlock ? date('Y-m-d H:i:s', time() + $timeUntilUnlock) : null
            ];
        } catch (PDOException $e) {
            error_log("Failed to get lockout status: " . $e->getMessage());
            return [
                'is_locked' => false,
                'attempts' => 0,
                'max_attempts' => $this->maxLoginAttempts,
                'remaining_attempts' => $this->maxLoginAttempts,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clear login attempts for a username (public method for admin use)
     */
    public function unlockAccount($username) {
        try {
            $this->ensureLoginAttemptsTable();
            $stmt = $this->pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
            $stmt->execute([$username]);
            return [
                'success' => true,
                'deleted_count' => $stmt->rowCount(),
                'message' => "Login attempts cleared for user '$username'"
            ];
        } catch (PDOException $e) {
            error_log("Failed to unlock account: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to unlock account: ' . $e->getMessage()
            ];
        }
    }
}

// Initialize auth instance
$auth = new Auth();
?>
