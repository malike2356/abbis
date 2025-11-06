<?php
/**
 * CMS Public Header - Persistent Navigation
 * This header persists across all CMS public pages (like WordPress)
 */
if (!defined('CMS_HEADER_LOADED')) {
    define('CMS_HEADER_LOADED', true);
    
    // Only load data if not already loaded
    if (!isset($pdo)) {
        $rootPath = dirname(dirname(__DIR__));
        require_once $rootPath . '/config/app.php';
        require_once $rootPath . '/includes/functions.php';
        $pdo = getDBConnection();
    }
    
    // Get base URL if not set
    if (!isset($baseUrl)) {
        require_once __DIR__ . '/base-url.php';
    }
    
    // Get CMS settings FIRST (needed for site name)
    if (!isset($cmsSettings)) {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
        $cmsSettings = [];
        while ($row = $settingsStmt->fetch()) {
            $cmsSettings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Get site name from CMS settings (backend) - use consistent helper
    require_once __DIR__ . '/get-site-name.php';
    if (!isset($companyName) || empty($companyName)) {
        $companyName = getCMSSiteName('Our Company');
    }
    
    // Get active theme
    if (!isset($theme)) {
        $themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
        $theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
        $themeConfig = json_decode($theme['config'] ?? '{}', true);
    } else {
        $themeConfig = json_decode($theme['config'] ?? '{}', true);
    }
    
    // Get menu items (primary navigation) - use menu location system
    require_once __DIR__ . '/menu-functions.php';
    if (!isset($menuItems)) {
        $menuItems = getMenuItemsForLocation('primary', $pdo);
    }
    
    // Get cart count for shop/cart links
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $cartCount = 0;
    if (isset($_SESSION['cart_id'])) {
        try {
            $cartStmt = $pdo->prepare("SELECT SUM(quantity) FROM cms_cart_items WHERE session_id=?");
            $cartStmt->execute([$_SESSION['cart_id']]);
            $cartCount = (int)$cartStmt->fetchColumn() ?: 0;
        } catch (Throwable $e) {
            $cartCount = 0;
        }
    }
    
    // Theme colors
    $primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
    $headerBg = $themeConfig['header_bg'] ?? '#ffffff';
    $headerTextColor = $themeConfig['header_text_color'] ?? '#1e293b';
    
    // Current page detection for active menu items
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $currentPath = preg_replace('#\?.*$#', '', $currentPath);
?>
<header class="cms-header" style="background: <?php echo htmlspecialchars($headerBg); ?>; color: <?php echo htmlspecialchars($headerTextColor); ?>;">
    <div class="cms-header-content">
        <div class="cms-header-main">
            <a href="<?php echo $baseUrl; ?>/" class="cms-logo">
                <?php 
                $logoPath = $cmsSettings['site_logo'] ?? '';
                $logoUrl = $logoPath ? $baseUrl . '/' . $logoPath : '';
                if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="cms-logo-image" style="max-height: 50px; max-width: 200px; object-fit: contain;">
                <?php else: ?>
                    <span class="cms-logo-icon">💦</span>
                    <span class="cms-logo-text"><?php echo htmlspecialchars($companyName); ?></span>
                <?php endif; ?>
            </a>
            
            <nav class="cms-primary-nav">
                <?php if (!empty($menuItems)): ?>
                    <?php echo renderMenuItems($menuItems, null, 0, 'header'); ?>
                <?php endif; ?>
            </nav>
            
            <div class="cms-header-actions">
                <a href="<?php echo $baseUrl; ?>/cms/cart" class="cms-cart-link">
                    <span class="cms-cart-icon">🛒</span>
                    <span class="cms-cart-text">Cart</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cms-cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <?php
                // Check if user is logged into CMS
                require_once __DIR__ . '/../admin/auth.php';
                $cmsAuth = new CMSAuth();
                $isLoggedIntoCMS = $cmsAuth->isLoggedIn();
                $currentUser = $isLoggedIntoCMS ? $cmsAuth->getCurrentUser() : null;
                
                if ($isLoggedIntoCMS && $currentUser):
                    // Display avatar or initials
                    $avatar = $currentUser['avatar'] ?? '';
                    $displayName = $currentUser['display_name'] ?? $currentUser['first_name'] ?? $currentUser['username'] ?? 'User';
                    $initials = strtoupper(substr($displayName, 0, 1));
                    $avatarPath = '';
                    if ($avatar) {
                        $fullPath = str_replace($baseUrl . '/', '', $avatar);
                        $fullPath = dirname(dirname(__DIR__)) . '/' . ltrim($fullPath, '/');
                        if (file_exists($fullPath)) {
                            $avatarPath = $baseUrl . '/' . ltrim($avatar, '/');
                        }
                    }
                ?>
                <!-- Account Dropdown (Logged In) -->
                <div class="cms-account-dropdown">
                    <button class="cms-account-toggle" onclick="toggleAccountDropdown()" aria-label="Account Menu">
                        <?php if ($avatarPath): ?>
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile" class="cms-account-avatar">
                        <?php else: ?>
                            <span class="cms-account-initials"><?php echo htmlspecialchars($initials); ?></span>
                        <?php endif; ?>
                        <span class="cms-account-name"><?php echo htmlspecialchars($displayName); ?></span>
                        <span class="cms-dropdown-arrow">▼</span>
                    </button>
                    <div class="cms-account-menu" id="accountDropdown">
                        <a href="<?php echo $baseUrl; ?>/cms/profile" class="cms-menu-item">
                            <span class="cms-menu-icon">👤</span>
                            <span>View Profile</span>
                        </a>
                        <a href="<?php echo $baseUrl; ?>/cms/admin/" class="cms-menu-item">
                            <span class="cms-menu-icon">⚙️</span>
                            <span>Dashboard</span>
                        </a>
                        <div class="cms-menu-divider"></div>
                        <a href="<?php echo $baseUrl; ?>/cms/admin/logout.php" class="cms-menu-item cms-menu-item-danger">
                            <span class="cms-menu-icon">🚪</span>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <!-- Account Button (Not Logged In) -->
                <div class="cms-account-dropdown">
                    <a href="<?php echo $baseUrl; ?>/cms/admin/login.php" class="cms-account-login">
                        <span class="cms-menu-icon">🔐</span>
                        <span>Login</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <button class="cms-mobile-menu-toggle" aria-label="Toggle Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>
</header>

<style>
/* CMS Header Styles - WordPress-like persistent navigation */
.cms-header {
    background: <?php echo htmlspecialchars($headerBg); ?> !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: sticky !important;
    top: 0 !important;
    z-index: 1000 !important;
    width: 100% !important;
}

.cms-header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}

.cms-header-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 0;
    gap: 2rem;
}

.cms-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
    text-decoration: none;
    transition: opacity 0.2s;
}

.cms-logo:hover {
    opacity: 0.8;
}

.cms-logo-icon {
    font-size: 1.75rem;
}

.cms-primary-nav {
    flex: 1;
    display: flex;
    justify-content: center;
}

.cms-nav-menu {
    display: flex;
    list-style: none;
    gap: 2rem;
    margin: 0;
    padding: 0;
    align-items: center;
}

.cms-nav-item {
    margin: 0;
    padding: 0;
}

.cms-nav-link {
    color: <?php echo htmlspecialchars($headerTextColor); ?> !important;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    padding: 0.5rem 0;
    position: relative;
    transition: color 0.2s;
}

.cms-nav-link:hover {
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
}

.cms-nav-item.active .cms-nav-link {
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
    font-weight: 600;
}

.cms-nav-item.active .cms-nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: <?php echo htmlspecialchars($primaryColor); ?>;
}

/* Nested menu styles (dropdown) */
.cms-nav-item {
    position: relative;
}

.cms-submenu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 4px;
    min-width: 200px;
    padding: 0.5rem 0;
    margin-top: 0.5rem;
    z-index: 1000;
    list-style: none;
    flex-direction: column;
    gap: 0;
}

.cms-nav-item:hover > .cms-submenu {
    display: block;
}

.cms-submenu-item {
    margin: 0;
}

.cms-submenu-item .cms-nav-link {
    display: block;
    padding: 0.75rem 1.5rem;
    color: #2c3338 !important;
    font-weight: 400;
}

.cms-submenu-item .cms-nav-link:hover {
    background: #f6f7f7;
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
}

.cms-submenu .cms-submenu {
    left: 100%;
    top: 0;
    margin-top: 0;
}

.cms-header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.cms-cart-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: <?php echo htmlspecialchars($headerTextColor); ?> !important;
    text-decoration: none;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: background 0.2s;
}

.cms-cart-link:hover {
    background: rgba(0,0,0,0.05);
}

/* Account Dropdown Styles */
.cms-account-dropdown {
    position: relative;
    display: inline-block;
}

.cms-account-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: none;
    border: 1px solid rgba(0,0,0,0.1);
    color: <?php echo htmlspecialchars($headerTextColor); ?> !important;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.2s;
    font-family: inherit;
}

.cms-account-toggle:hover {
    background: rgba(0,0,0,0.05);
    border-color: <?php echo htmlspecialchars($primaryColor); ?>;
}

.cms-account-toggle:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.cms-account-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.cms-account-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: <?php echo htmlspecialchars($primaryColor); ?>;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.cms-account-name {
    font-weight: 500;
}

.cms-dropdown-arrow {
    font-size: 0.75rem;
    opacity: 0.7;
    transition: transform 0.2s;
}

.cms-account-dropdown.active .cms-dropdown-arrow {
    transform: rotate(180deg);
}

.cms-account-menu {
    display: none;
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background: white;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    padding: 0.5rem 0;
    z-index: 1000;
}

.cms-account-dropdown.active .cms-account-menu {
    display: block;
}

.cms-menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #1e293b !important;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
}

.cms-menu-item:hover {
    background: #f1f5f9;
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
}

.cms-menu-item-danger {
    color: #dc2626 !important;
}

.cms-menu-item-danger:hover {
    background: #fee2e2;
    color: #991b1b !important;
}

.cms-menu-icon {
    font-size: 1.125rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.cms-menu-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 0.5rem 0;
}

.cms-account-login {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: <?php echo htmlspecialchars($headerTextColor); ?> !important;
    text-decoration: none;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid <?php echo htmlspecialchars($primaryColor); ?>;
    transition: all 0.2s;
}

.cms-account-login:hover {
    background: <?php echo htmlspecialchars($primaryColor); ?>;
    color: white !important;
}

.cms-cart-badge {
    background: #ef4444;
    color: white;
    border-radius: 50%;
    padding: 0.125rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 1.25rem;
    text-align: center;
}

.cms-mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 4px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
}

.cms-mobile-menu-toggle span {
    width: 24px;
    height: 2px;
    background: <?php echo htmlspecialchars($headerTextColor); ?>;
    transition: all 0.3s;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .cms-primary-nav {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: <?php echo htmlspecialchars($headerBg); ?>;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s;
    }
    
    .cms-primary-nav.active {
        max-height: 500px;
    }
    
    .cms-nav-menu {
        flex-direction: column;
        gap: 0;
        padding: 1rem;
        align-items: stretch;
    }
    
    .cms-nav-link {
        padding: 1rem;
        border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    
    .cms-mobile-menu-toggle {
        display: flex;
    }
    
    .cms-cart-text, .cms-account-name {
        display: none;
    }
    
    .cms-account-menu {
        right: auto;
        left: 0;
    }
}

/* Ensure header persists on all pages */
body {
    padding-top: 0 !important;
}

/* Prevent page content from going under header */
main, .cms-content, .container {
    margin-top: 0 !important;
}
</style>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.querySelector('.cms-mobile-menu-toggle');
    const nav = document.querySelector('.cms-primary-nav');
    
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('active');
            toggle.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('active');
                toggle.classList.remove('active');
            }
        });
    }
});

// Account dropdown toggle
function toggleAccountDropdown() {
    const dropdown = document.querySelector('.cms-account-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Close account dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.querySelector('.cms-account-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
    }
});
</script>
<?php } ?>

