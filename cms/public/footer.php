<?php
/**
 * CMS Public Footer - Persistent Footer
 * This footer persists across all CMS public pages (like WordPress)
 */
if (!defined('CMS_FOOTER_LOADED')) {
    define('CMS_FOOTER_LOADED', true);
    
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
    
    // Get footer menu items - use menu location system
    require_once __DIR__ . '/menu-functions.php';
    if (!isset($footerMenuItems)) {
        $footerMenuItems = getMenuItemsForLocation('footer', $pdo);
    }
    
    // Load widget functions for footer widget areas
    require_once __DIR__ . '/widget-functions.php';
    
    // Get active theme
    if (!isset($theme)) {
        $themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
        $theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
        $themeConfig = json_decode($theme['config'] ?? '{}', true);
    } else {
        $themeConfig = json_decode($theme['config'] ?? '{}', true);
    }
    
    $footerBg = $themeConfig['footer_bg'] ?? '#1e293b';
    $footerTextColor = $themeConfig['footer_text_color'] ?? '#ffffff';
    $primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
    
    $currentYear = date('Y');
    $siteTagline = $cmsSettings['site_tagline'] ?? '';
?>
<footer class="cms-footer" style="background: <?php echo htmlspecialchars($footerBg); ?>; color: <?php echo htmlspecialchars($footerTextColor); ?>;">
    <div class="cms-footer-content">
        <div class="cms-footer-widgets">
            <?php
            // Render footer widget columns (footer-1, footer-2, footer-3, footer-4)
            $footerWidgetColumns = ['footer-1', 'footer-2', 'footer-3', 'footer-4'];
            $hasWidgets = false;
            
            foreach ($footerWidgetColumns as $columnSlug) {
                $widgetsHtml = renderWidgetArea($columnSlug, $pdo);
                if (!empty($widgetsHtml)) {
                    echo '<div class="cms-footer-widget-column">';
                    echo $widgetsHtml;
                    echo '</div>';
                    $hasWidgets = true;
                }
            }
            
            // Fallback to default widgets if no widgets are configured
            if (!$hasWidgets):
            ?>
                <div class="cms-footer-widget">
                    <h3 class="cms-footer-widget-title">About</h3>
                    <p class="cms-footer-widget-text">
                        <?php echo htmlspecialchars($companyName); ?>
                        <?php if ($siteTagline): ?>
                            - <?php echo htmlspecialchars($siteTagline); ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if (!empty($footerMenuItems)): ?>
                <div class="cms-footer-widget">
                    <h3 class="cms-footer-widget-title">Quick Links</h3>
                    <?php echo renderMenuItems($footerMenuItems, null, 0, 'footer'); ?>
                </div>
                <?php endif; ?>
                
                <div class="cms-footer-widget">
                    <h3 class="cms-footer-widget-title">Legal</h3>
                    <ul class="cms-footer-menu">
                        <li><a href="<?php echo $baseUrl; ?>/cms/legal/drilling-agreement">Drilling Agreement</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/cms/privacy">Privacy Policy</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/cms/terms">Terms of Service</a></li>
                    </ul>
                </div>
                
                <div class="cms-footer-widget">
                    <h3 class="cms-footer-widget-title">Contact</h3>
                    <p class="cms-footer-widget-text">
                        <a href="<?php echo $baseUrl; ?>/cms/quote" style="color: <?php echo htmlspecialchars($primaryColor); ?>;">Get a Quote</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="cms-footer-bottom">
            <p class="cms-footer-copyright">
                &copy; <?php echo $currentYear; ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<style>
/* CMS Footer Styles - WordPress-like persistent footer */
.cms-footer {
    background: <?php echo htmlspecialchars($footerBg); ?> !important;
    color: <?php echo htmlspecialchars($footerTextColor); ?> !important;
    margin-top: 4rem;
    padding: 0;
}

.cms-footer-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 3rem 2rem 1.5rem;
}

.cms-footer-widgets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.cms-footer-widget-column {
    min-width: 0; /* Allow flex shrinking */
}

.cms-footer-widget {
    margin-bottom: 1.5rem;
}

.cms-footer-widget-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: <?php echo htmlspecialchars($footerTextColor); ?> !important;
}

.widget {
    margin-bottom: 1.5rem;
}

.widget-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: <?php echo htmlspecialchars($footerTextColor); ?> !important;
}

.widget-content {
    color: rgba(255,255,255,0.8);
    line-height: 1.6;
}

.widget-content ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.widget-content li {
    margin-bottom: 0.5rem;
}

.widget-content a {
    color: rgba(255,255,255,0.8) !important;
    text-decoration: none;
    transition: color 0.2s;
}

.widget-content a:hover {
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
}

.widget-content form {
    display: flex;
    gap: 0.5rem;
}

.widget-content input[type="search"],
.widget-content input[type="text"] {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.1);
    color: <?php echo htmlspecialchars($footerTextColor); ?>;
    border-radius: 4px;
}

.widget-content button {
    padding: 0.5rem 1rem;
    background: <?php echo htmlspecialchars($primaryColor); ?>;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.cms-footer-widget-text {
    color: rgba(255,255,255,0.8);
    line-height: 1.6;
    margin: 0;
}

.cms-footer-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.cms-footer-menu-item {
    margin-bottom: 0.5rem;
}

.cms-footer-link {
    color: rgba(255,255,255,0.8) !important;
    text-decoration: none;
    transition: color 0.2s;
}

.cms-footer-link:hover {
    color: <?php echo htmlspecialchars($primaryColor); ?> !important;
}

.cms-footer-submenu {
    list-style: none;
    padding-left: 1rem;
    margin-top: 0.5rem;
}

.cms-footer-submenu .cms-footer-menu-item {
    margin-bottom: 0.3rem;
}

.cms-footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 1.5rem;
    text-align: center;
}

.cms-footer-copyright {
    margin: 0;
    color: rgba(255,255,255,0.6);
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .cms-footer-widgets {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .cms-footer-content {
        padding: 2rem 1rem 1rem;
    }
}
</style>
<?php } ?>

