<?php
/**
 * AccessControl service
 *
 * Central place to evaluate permissions and expose helper utilities for
 * navigation rendering, page enforcement, and role meta-data.
 */

require_once __DIR__ . '/../config/constants.php';

class AccessControl
{
    /**
     * Singleton instance
     */
    private static $instance;

    /**
     * @var array<string, array> Permission definitions keyed by permission name.
     */
    private $permissions = [];

    /**
     * @var array<string, string> Role => label map.
     */
    private $roles = [];

    /**
     * @var array<string, string[]> Map of page basenames to permission keys.
     */
    private $pagePermissionMap = [];

    private function __construct()
    {
        $configFile = __DIR__ . '/../config/access-control.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->permissions = $config['permissions'] ?? [];
            $this->roles = $config['roles'] ?? [];
        }

        $this->buildPageMap();
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Build lookup index for page -> permission mapping.
     */
    private function buildPageMap(): void
    {
        foreach ($this->permissions as $permissionKey => $meta) {
            $pages = $meta['pages'] ?? [];
            if (!is_array($pages)) {
                continue;
            }

            foreach ($pages as $page) {
                $normalized = $this->normalizePage($page);
                if (!isset($this->pagePermissionMap[$normalized])) {
                    $this->pagePermissionMap[$normalized] = [];
                }
                if (!in_array($permissionKey, $this->pagePermissionMap[$normalized], true)) {
                    $this->pagePermissionMap[$normalized][] = $permissionKey;
                }
            }
        }
    }

    /**
     * Normalize given filename into lowercase basename.
     */
    private function normalizePage(string $page): string
    {
        $page = trim($page);
        $page = basename($page);
        return strtolower($page);
    }

    /**
     * Determine if the provided role has the specified permission.
     */
    public function roleHasPermission(?string $role, string $permissionKey): bool
    {
        if (!$role) {
            return false;
        }

        // Super Admin has all permissions (development bypass)
        if ($role === ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($role === ROLE_ADMIN) {
            return true;
        }

        $meta = $this->permissions[$permissionKey] ?? null;
        if (!$meta) {
            // If permission is undefined, allow by default to preserve backwards compatibility.
            return true;
        }

        $allowedRoles = $meta['roles'] ?? [];
        return in_array($role, $allowedRoles, true);
    }

    /**
     * Checks if given role can access any permission mapped to the page.
     */
    public function roleCanAccessPage(?string $role, string $page): bool
    {
        if (!$role) {
            return false;
        }

        // Super Admin has access to all pages (development bypass)
        if ($role === ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($role === ROLE_ADMIN) {
            return true;
        }

        $pageKey = $this->normalizePage($page);
        $permissionKeys = $this->pagePermissionMap[$pageKey] ?? [];
        if (empty($permissionKeys)) {
            // No explicit guard configured - allow access.
            return true;
        }

        foreach ($permissionKeys as $permissionKey) {
            if ($this->roleHasPermission($role, $permissionKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure current user role can access page. Returns bool if allowed.
     */
    public function ensurePageAccess(string $page, ?string $role): bool
    {
        return $this->roleCanAccessPage($role, $page);
    }

    /**
     * List of roles with labels.
     */
    public function getRoleLabels(): array
    {
        return $this->roles;
    }

    /**
     * Get permission metadata by key.
     */
    public function getPermissionMeta(string $permissionKey): ?array
    {
        return $this->permissions[$permissionKey] ?? null;
    }

    /**
     * Returns true when the currently authenticated user is allowed to use
     * the specified permission key.
     */
    public function currentUserCan(string $permissionKey): bool
    {
        $role = $_SESSION['role'] ?? null;
        return $this->roleHasPermission($role, $permissionKey);
    }

    /**
     * Helper for navigation: determine visibility of a logical menu item.
     */
    public function shouldDisplayNav(string $permissionKey): bool
    {
        return $this->currentUserCan($permissionKey);
    }

    /**
     * Expose configured permissions (read-only).
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}

