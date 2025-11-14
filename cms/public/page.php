<?php
/**
 * Custom CMS Page Template
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

// Vacancies module defaults
$vacanciesTablesReady = false;
$vacanciesList = [];
$vacanciesErrors = [];
$isVacanciesPage = false;
$vacanciesIntroHtml = '';
$vacanciesApiEndpoint = rtrim($baseUrl, '/') . '/api/recruitment-submit.php';
$vacancySlugs = ['vacancies', 'vacancy', 'careers', 'career', 'jobs', 'careers-at-abbis'];

// Get page slug from URL - handle both direct access and routed access
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    // Try to get slug from REQUEST_URI
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    $basePath = app_base_path();
    if ($basePath) {
        $requestUri = preg_replace('#^' . preg_quote($basePath, '#') . '#', '', $requestUri);
    }
    $requestUri = strtok($requestUri, '?');
    
    // Normalize the URI - handle both /abbis3.2/cms/slug and /cms/slug
    $requestUri = preg_replace('#^/abbis3.2#', '', $requestUri);
    $requestUri = trim($requestUri, '/');
    
    // Extract slug from cms/slug pattern (most common case)
    if (preg_match('#^cms/([^/]+)/?$#', $requestUri, $matches)) {
        $slug = $matches[1];
    } 
    // If directly accessing page.php, check PATH_INFO
    elseif (isset($_SERVER['PATH_INFO'])) {
        $slug = trim($_SERVER['PATH_INFO'], '/');
        $slug = str_replace('cms/', '', $slug);
    }
    // Last resort - take last segment
    elseif ($requestUri) {
        $parts = explode('/', $requestUri);
        $slug = end($parts);
    }
    
    // Clean slug - remove cms prefix if present
    $slug = trim($slug, '/');
    $slug = preg_replace('#^cms/#', '', $slug);
    
    // Remove any file extensions
    $slug = preg_replace('#\.(php|html)$#', '', $slug);
}

// Ensure slug is not empty and valid
$slug = trim($slug);

// If slug is "index" or "index.php" after processing, redirect to homepage
if ($slug === 'index' || $slug === 'index.php') {
    header('Location: ' . $baseUrl . '/cms/');
    exit;
}

// Debug: Output slug for testing (remove in production)
if (empty($slug)) {
    // Last attempt - try SCRIPT_NAME or any other source
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('#cms/public/page\.php#', $scriptName)) {
        // We're definitely in page.php, try harder to get slug
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/cms/([^/?]+)#', $uri, $m)) {
            $slug = $m[1];
        }
    }
}

// Handle common routes - check slug early (before checking if empty)
if ($slug === 'privacy' || $slug === 'privacy-policy') {
    // Try to find privacy policy page
    $privacyStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE (slug='privacy' OR slug='privacy-policy') AND status='published' LIMIT 1");
    $privacyStmt->execute();
    $page = $privacyStmt->fetch(PDO::FETCH_ASSOC);
    
    // If page exists but has empty content, treat as not found to use default
    if ($page && empty(trim($page['content'] ?? ''))) {
        $page = false;
    }
    
    // Use default content if page not found or has empty content
    if (!$page || (is_array($page) && empty(trim($page['content'] ?? '')))) {
        // Create a default privacy page array (not in DB, but will render)
        $page = [
            'id' => 0,
            'title' => 'Privacy Policy',
            'slug' => 'privacy',
            'content' => '<div style="max-width: 900px; margin: 0 auto; line-height: 1.8;">
                <h1>Privacy Policy</h1>
                <p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>
                
                <h2>1. Introduction</h2>
                <p>We respect your privacy and are committed to protecting your personal data. This privacy policy explains how we collect, use, and safeguard your information when you use our services.</p>
                
                <h2>2. Information We Collect</h2>
                <p>We may collect the following types of personal information:</p>
                <ul>
                    <li>Contact information (name, email, phone number)</li>
                    <li>Account information and credentials</li>
                    <li>Usage data and analytics</li>
                    <li>Technical data (IP address, browser type, device information)</li>
                </ul>
                
                <h2>3. How We Use Your Information</h2>
                <p>We use your personal information to:</p>
                <ul>
                    <li>Provide and maintain our services</li>
                    <li>Process transactions and manage accounts</li>
                    <li>Communicate with you about our services</li>
                    <li>Improve our services and user experience</li>
                    <li>Comply with legal obligations</li>
                </ul>
                
                <h2>4. Data Protection</h2>
                <p>We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction.</p>
                
                <h2>5. Your Rights</h2>
                <p>Under applicable data protection laws (including Ghana Data Protection Act 843 and GDPR), you have the right to:</p>
                <ul>
                    <li>Access your personal data</li>
                    <li>Rectify inaccurate data</li>
                    <li>Request deletion of your data</li>
                    <li>Object to processing of your data</li>
                    <li>Data portability</li>
                </ul>
                
                <h2>6. Contact Us</h2>
                <p>If you have questions about this privacy policy or wish to exercise your rights, please contact us through our website or admin panel.</p>
                
                <p><em>Note: This is a default privacy policy. Administrators can customize this content through the CMS admin panel.</em></p>
            </div>',
            'status' => 'published',
            'seo_title' => 'Privacy Policy',
            'seo_description' => 'Privacy Policy - Learn how we collect, use, and protect your personal information'
        ];
    }
} elseif ($slug === 'terms' || $slug === 'terms-of-service') {
    // Try to find terms page from legal documents first
    try {
        $legalStmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE (slug='terms' OR slug='terms-of-service') AND is_active=1 LIMIT 1");
        $legalStmt->execute();
        $legalDoc = $legalStmt->fetch(PDO::FETCH_ASSOC);
        
        // Only use legal doc if it has content
        if ($legalDoc && !empty(trim($legalDoc['content'] ?? ''))) {
            $page = [
                'id' => 0,
                'title' => $legalDoc['title'],
                'slug' => $legalDoc['slug'],
                'content' => $legalDoc['content'],
                'status' => 'published',
                'seo_title' => $legalDoc['title'],
                'seo_description' => 'Terms of Service'
            ];
        }
    } catch (PDOException $e) {}
    
    // Fallback to CMS pages
    if (!isset($page)) {
        $termsStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE (slug='terms' OR slug='terms-of-service') AND status='published' LIMIT 1");
        $termsStmt->execute();
        $page = $termsStmt->fetch(PDO::FETCH_ASSOC);
        
        // If page exists but has empty content, treat as not found to use default
        if ($page && empty(trim($page['content'] ?? ''))) {
            $page = false;
        }
    }
    
    // Use default content if page not found or has empty content
    if (!isset($page) || !$page || (is_array($page) && empty(trim($page['content'] ?? '')))) {
            $page = [
                'id' => 0,
                'title' => 'Terms of Service',
                'slug' => 'terms',
                'content' => '<div style="max-width: 900px; margin: 0 auto; line-height: 1.8;">
                    <h1>Terms of Service</h1>
                    <p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>
                    
                    <h2>1. Acceptance of Terms</h2>
                    <p>By accessing and using this website and our services, you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to abide by these terms, please do not use our services.</p>
                    
                    <h2>2. Services Provided</h2>
                    <p>We provide professional borehole drilling, water well construction, and related water services. Our services include but are not limited to:</p>
                    <ul>
                        <li>Borehole drilling and construction</li>
                        <li>Water well installation and maintenance</li>
                        <li>Pump installation and servicing</li>
                        <li>Water quality testing and treatment</li>
                        <li>Related consulting and advisory services</li>
                    </ul>
                    
                    <h2>3. Service Agreements and Contracts</h2>
                    <p>All services are subject to a formal drilling agreement or service contract that will specify:</p>
                    <ul>
                        <li>Scope of work and deliverables</li>
                        <li>Pricing and payment terms</li>
                        <li>Timeline and completion dates</li>
                        <li>Warranty terms and conditions</li>
                        <li>Responsibilities of both parties</li>
                    </ul>
                    <p>The formal contract supersedes these general terms of service for specific projects.</p>
                    
                    <h2>4. Quotations and Pricing</h2>
                    <p>All quotations are valid for the period specified and are subject to site conditions and accessibility. Final pricing may vary based on:</p>
                    <ul>
                        <li>Actual ground conditions encountered during drilling</li>
                        <li>Depth required to reach water</li>
                        <li>Additional services requested</li>
                        <li>Site accessibility and location factors</li>
                    </ul>
                    <p>Any changes to quoted prices will be communicated and agreed upon before work proceeds.</p>
                    
                    <h2>5. Payment Terms</h2>
                    <p>Payment terms will be specified in your service agreement. Generally:</p>
                    <ul>
                        <li>Deposits may be required before work commences</li>
                        <li>Progress payments may be required for extended projects</li>
                        <li>Final payment is due upon completion and acceptance of work</li>
                        <li>Late payments may incur additional charges</li>
                    </ul>
                    <p>All prices are in Ghana Cedis (GHS) unless otherwise specified.</p>
                    
                    <h2>6. Site Access and Preparation</h2>
                    <p>You are responsible for:</p>
                    <ul>
                        <li>Providing safe and accessible site access for equipment</li>
                        <li>Obtaining necessary permits and approvals</li>
                        <li>Informing us of any underground utilities or obstacles</li>
                        <li>Ensuring site is prepared according to our specifications</li>
                        <li>Providing access to utilities (water, electricity) if required</li>
                    </ul>
                    
                    <h2>7. Materials and Equipment</h2>
                    <p>Materials may be provided by the client or by us. Specifications will be agreed upon before work begins. We warrant that all materials used meet industry standards and are suitable for the intended purpose.</p>
                    
                    <h2>8. Workmanship and Quality</h2>
                    <p>We commit to:</p>
                    <ul>
                        <li>Performing all work to professional standards</li>
                        <li>Using qualified personnel and certified equipment</li>
                        <li>Complying with applicable safety regulations</li>
                        <li>Following industry best practices and standards</li>
                    </ul>
                    
                    <h2>9. Warranties and Guarantees</h2>
                    <p>We provide warranties on workmanship as specified in your service agreement. Water yield and quality depend on geological conditions beyond our control. We warrant the quality of our work but cannot guarantee specific water yields or quality levels.</p>
                    
                    <h2>10. Limitations of Liability</h2>
                    <p>To the maximum extent permitted by law:</p>
                    <ul>
                        <li>Our liability is limited to the value of the services provided</li>
                        <li>We are not liable for indirect, consequential, or incidental damages</li>
                        <li>We are not responsible for damage to existing structures not caused by our negligence</li>
                        <li>Water yield and quality results are not guaranteed due to geological variability</li>
                    </ul>
                    
                    <h2>11. Force Majeure</h2>
                    <p>We are not liable for delays or failures in performance due to circumstances beyond our reasonable control, including but not limited to natural disasters, extreme weather, government actions, or material shortages.</p>
                    
                    <h2>12. Website Use and Account Registration</h2>
                    <p>If you register for an account on our website:</p>
                    <ul>
                        <li>You agree to provide accurate and complete information</li>
                        <li>You are responsible for maintaining account security</li>
                        <li>You must not share your account credentials with others</li>
                        <li>You agree to comply with all applicable data protection laws (including Ghana Data Protection Act 843 and GDPR)</li>
                    </ul>
                    
                    <h2>13. Intellectual Property</h2>
                    <p>The content, features, and functionality of this website are owned by us and protected by copyright, trademark, and other intellectual property laws. You may not reproduce, distribute, or create derivative works without our written permission.</p>
                    
                    <h2>14. Prohibited Uses</h2>
                    <p>You may not use our website or services:</p>
                    <ul>
                        <li>In any way that violates applicable laws or regulations</li>
                        <li>To transmit malicious code or viruses</li>
                        <li>To interfere with or disrupt our services</li>
                        <li>To impersonate any person or entity</li>
                        <li>For any fraudulent or deceptive purpose</li>
                    </ul>
                    
                    <h2>15. Privacy and Data Protection</h2>
                    <p>We respect your privacy and handle personal data in accordance with our Privacy Policy and applicable data protection laws. Please review our Privacy Policy for details on how we collect, use, and protect your information.</p>
                    
                    <h2>16. Dispute Resolution</h2>
                    <p>In the event of any dispute:</p>
                    <ul>
                        <li>We encourage direct communication to resolve issues amicably</li>
                        <li>If necessary, disputes will be resolved through mediation or arbitration</li>
                        <li>Ghana law governs these terms and any disputes</li>
                    </ul>
                    
                    <h2>17. Modifications to Terms</h2>
                    <p>We reserve the right to modify these terms at any time. Material changes will be communicated through our website or by other means. Your continued use of our services after changes constitutes acceptance of the modified terms.</p>
                    
                    <h2>18. Severability</h2>
                    <p>If any provision of these terms is found to be invalid or unenforceable, the remaining provisions shall continue in full force and effect.</p>
                    
                    <h2>19. Contact Information</h2>
                    <p>If you have any questions about these Terms of Service, please contact us through our website, email, or visit our office. We are committed to addressing your concerns promptly and professionally.</p>
                    
                    <h2>20. Governing Law</h2>
                    <p>These terms are governed by the laws of Ghana. Any legal actions must be brought in the courts of Ghana.</p>
                    
                    <p><em>Note: This is a default terms of service. Administrators can customize this content through the CMS admin panel. For specific project terms, please refer to your signed drilling agreement or service contract.</em></p>
                </div>',
                'status' => 'published',
                'seo_title' => 'Terms of Service',
                'seo_description' => 'Terms of Service - Read our terms and conditions for using our borehole drilling and water services'
            ];
        }
}

// If no slug provided, redirect to CMS homepage
if (!$slug && !isset($page)) {
    header('Location: ' . $baseUrl . '/cms/');
    exit;
}

// Get page from database (if not already set above)
if (!isset($page)) {
    $pageStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE slug=? AND status='published' LIMIT 1");
    $pageStmt->execute([$slug]);
    $page = $pageStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$page && in_array($slug, $vacancySlugs, true)) {
    $vacancyFallback = [
        'id' => 0,
        'title' => 'Join Our Team',
        'slug' => 'vacancies',
        'content' => '',
        'seo_title' => 'Careers',
        'seo_description' => 'Explore open roles and career opportunities with our team.',
        'status' => 'published'
    ];
    
    try {
        $vacancyPageStmt = $pdo->prepare("SELECT id, title, content, seo_title, seo_description, status FROM cms_pages WHERE slug='vacancies' LIMIT 1");
        $vacancyPageStmt->execute();
        $existingVacancyPage = $vacancyPageStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingVacancyPage) {
            $vacancyFallback = array_merge($vacancyFallback, $existingVacancyPage);
            $vacancyFallback['id'] = (int)$existingVacancyPage['id'];
            
            $fieldsToUpdate = [];
            if (empty($existingVacancyPage['title'])) {
                $fieldsToUpdate['title'] = 'Join Our Team';
            }
            if (empty($existingVacancyPage['seo_title'])) {
                $fieldsToUpdate['seo_title'] = 'Careers';
            }
            if (empty($existingVacancyPage['seo_description'])) {
                $fieldsToUpdate['seo_description'] = 'Explore open roles and career opportunities with our team.';
            }
            if ($existingVacancyPage['status'] !== 'published') {
                $fieldsToUpdate['status'] = 'published';
            }
            
            if (!empty($fieldsToUpdate)) {
                $setParts = [];
                $params = [];
                foreach ($fieldsToUpdate as $field => $value) {
                    $setParts[] = "{$field} = ?";
                    $params[] = $value;
                    $vacancyFallback[$field] = $value;
                }
                $params[] = $vacancyFallback['id'];
                $updateSql = "UPDATE cms_pages SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($params);
            }
        } else {
            $insertVacancyStmt = $pdo->prepare("INSERT INTO cms_pages (title, slug, content, status, seo_title, seo_description, created_by, created_at, updated_at) VALUES (:title, :slug, :content, 'published', :seo_title, :seo_description, NULL, NOW(), NOW())");
            $insertVacancyStmt->execute([
                ':title' => $vacancyFallback['title'],
                ':slug' => 'vacancies',
                ':content' => $vacancyFallback['content'],
                ':seo_title' => $vacancyFallback['seo_title'],
                ':seo_description' => $vacancyFallback['seo_description']
            ]);
            $vacancyFallback['id'] = (int)$pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log('[Vacancies Auto Create] ' . $e->getMessage());
    }
    
    $page = $vacancyFallback;
}

if (!$page) {
    // Try one more time with direct database lookup
    $finalStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE (slug=? OR slug=?) AND status='published' LIMIT 1");
    $finalStmt->execute([$slug, str_replace('-', '_', $slug)]);
    $page = $finalStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        header('HTTP/1.0 404 Not Found');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Page Not Found</title></head><body>';
        echo '<h1>Page Not Found</h1>';
        echo '<p>The requested page "' . htmlspecialchars($slug) . '" could not be found.</p>';
        echo '<p><a href="' . $baseUrl . '/">← Back to Home</a></p>';
        echo '</body></html>';
        exit;
    }
}

// Get active theme
$themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
$theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
$themeConfig = json_decode($theme['config'] ?? '{}', true);

// Get menu items
$menuStmt = $pdo->query("SELECT * FROM cms_menu_items WHERE menu_type='primary' ORDER BY menu_order");
$menuItems = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

// Get CMS settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
$cmsSettings = [];
while ($row = $settingsStmt->fetch()) {
    $cmsSettings[$row['setting_key']] = $row['setting_value'];
}

// Load hero banner helper
require_once $rootPath . '/cms/includes/hero-banner-helper.php';

// Determine page type from slug and handle special modules
$pageSlug = $page['slug'] ?? '';
if (in_array($pageSlug, $vacancySlugs, true)) {
    $isVacanciesPage = true;
    $vacanciesHeroTitle = $page['title'] ?? 'Join Our Team';
    $vacanciesHeroSubtitle = $page['seo_description'] ?? '';
    
    if (!$vacanciesHeroSubtitle) {
        $vacanciesHeroSubtitle = 'We are building sustainable water infrastructure backed by data, technology, and passionate people. Explore our open roles and apply to join a team that cares about impact.';
    }
    
    try {
        require_once $rootPath . '/includes/recruitment-utils.php';
        $vacanciesTablesReady = recruitmentEnsureInitialized($pdo);
        
        if ($vacanciesTablesReady) {
            $vacancyStmt = $pdo->query("
                SELECT 
                    v.*,
                    COALESCE(COUNT(a.id), 0) AS application_count,
                    COALESCE(SUM(CASE WHEN a.current_status IN ('hired','onboarding','employed') THEN 1 ELSE 0 END), 0) AS hired_count
                FROM recruitment_vacancies v
                LEFT JOIN recruitment_applications a ON a.vacancy_id = v.id
                WHERE v.status = 'published'
                  AND (v.closing_date IS NULL OR v.closing_date >= CURDATE())
                GROUP BY v.id
                ORDER BY COALESCE(v.opening_date, v.created_at) DESC
            ");
            $vacanciesList = $vacancyStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('[Vacancies] ' . $e->getMessage());
        $vacanciesErrors[] = $e->getMessage();
        $vacanciesList = [];
    }
    
    $vacanciesContent = $page['content'] ?? '';
    if (!empty($vacanciesContent)) {
        $vacanciesHasHtml = (strpos($vacanciesContent, '<') !== false && strpos($vacanciesContent, '>') !== false) ||
                            strpos($vacanciesContent, 'gjs-') !== false ||
                            strpos($vacanciesContent, '<style>') !== false ||
                            strpos($vacanciesContent, '<div') !== false ||
                            strpos($vacanciesContent, '<h1') !== false ||
                            strpos($vacanciesContent, '<h2') !== false ||
                            strpos($vacanciesContent, '<ul>') !== false ||
                            strpos($vacanciesContent, '<p>') !== false;
        if ($vacanciesHasHtml) {
            $vacanciesIntroHtml = $vacanciesContent;
        } else {
            $vacanciesIntroHtml = '<p>' . nl2br(htmlspecialchars($vacanciesContent)) . '</p>';
        }
    }
}

$pageTypeMap = [
    'about' => 'about',
    'services' => 'services',
    'contact' => 'contact',
    'portfolio' => 'portfolio',
    'quote' => 'quote',
    'shop' => 'shop',
    'products' => 'shop',
    'blog' => 'blog',
    'vacancies' => 'vacancies'
];
$currentPageType = $pageTypeMap[$pageSlug] ?? 'homepage';

// Check if hero should be displayed
$shouldShowHero = shouldDisplayHeroBanner($cmsSettings, $currentPageType);

// Disable hero banner for about-us page specifically
if ($pageSlug === 'about-us') {
    $shouldShowHero = false;
}
if ($isVacanciesPage) {
    $shouldShowHero = false;
}

// Get hero banner settings
$heroImage = $cmsSettings['hero_banner_image'] ?? '';
$heroTitle = $cmsSettings['hero_title'] ?? $page['title'];
$heroSubtitle = $cmsSettings['hero_subtitle'] ?? ($page['seo_description'] ?? '');
$heroButton1Text = $cmsSettings['hero_button1_text'] ?? 'CALL US NOW';
$heroButton1Link = $cmsSettings['hero_button1_link'] ?? 'tel:0248518513';
$heroButton2Text = $cmsSettings['hero_button2_text'] ?? 'WHATSAPP US';
$heroButton2Link = $cmsSettings['hero_button2_link'] ?? '';
$heroOverlay = $cmsSettings['hero_overlay_opacity'] ?? '0.4';
$heroImageUrl = $heroImage ? ($baseUrl . '/' . $heroImage) : '';

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Company');
$siteTitle = ($page['seo_title'] ?? $page['title']) . ' - ' . $companyName;
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
$bodyClasses = [];
if ($pageSlug === 'about-us') {
    $bodyClasses[] = 'about-us-page';
}
if ($isVacanciesPage) {
    $bodyClasses[] = 'vacancies-page';
}
$bodyClassAttr = '';
if (!empty($bodyClasses)) {
    $bodyClassAttr = ' class="' . htmlspecialchars(implode(' ', $bodyClasses)) . '"';
}

$activeThemeSlug = $theme['slug'] ?? 'default';
$themeBaseDir = dirname(__DIR__) . '/themes/' . $activeThemeSlug;
$themePageTemplate = $themeBaseDir . '/page.php';

if (is_file($themePageTemplate)) {
    include $themePageTemplate;
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <?php if (!empty($page['seo_description'])): ?>
        <meta name="description" content="<?php echo htmlspecialchars($page['seo_description']); ?>">
    <?php endif; ?>
    <style>
        /* Enhanced Page Styling - WordPress-like beautiful design */
        * {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            padding-top: 0 !important;
        }
        .cms-content {
            background: white;
            padding: 4rem 0 0 0;
            min-height: 70vh;
            margin-bottom: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-hero {
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primaryColor); ?> 0%, #0284c7 100%);
            color: white;
            padding: 5rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
        }
        .page-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .page-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        /* Special full-width container for about-us and other wide pages */
        .page-content-wrapper {
            max-width: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .page-content {
            background: white;
            padding: 4rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        /* Full-width styling for about-us page */
        <?php if ($pageSlug === 'about-us'): ?>
        /* Override page-hero to white background with blue text for about-us */
        .page-hero {
            background: white !important;
            color: <?php echo htmlspecialchars($primaryColor); ?> !important;
            border-bottom: 2px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-top: 20px !important;
        }
        .page-hero h1 {
            color: <?php echo htmlspecialchars($primaryColor); ?> !important;
            text-shadow: none !important;
        }
        .page-hero p {
            color: #64748b !important;
            opacity: 1 !important;
        }
        .page-content-wrapper {
            max-width: 100vw !important;
            width: 100% !important;
            overflow-x: hidden !important;
            margin-bottom: 0 !important;
            margin-top: 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }
        .page-content {
            background: transparent !important;
            padding: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            border: none !important;
            max-width: 100vw !important;
            width: 100% !important;
            overflow-x: hidden !important;
            margin-bottom: 0 !important;
            margin-top: 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }
        .page-content * {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }
        /* Ensure about-us content wrapper doesn't interfere */
        body.about-us-page,
        body.about-us-page .cms-content {
            padding-top: 0 !important;
            margin-top: 0 !important;
            background: transparent !important;
        }
        /* Enhanced styling for Why Choose section feature cards */
        .page-content div[style*="Why Choose Kari Boreholes"] {
            padding: 2.5rem !important;
        }
        /* Ensure grid containers fill their parent divs and look better */
        .page-content div[style*="display: grid"][style*="grid-template-columns"] {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            gap: 2.5rem !important;
            align-items: stretch !important;
        }
        /* Improve 3-column grid to prevent squeezing */
        .page-content div[style*="grid-template-columns: repeat(3"] {
            grid-template-columns: repeat(3, minmax(280px, 1fr)) !important;
            width: 100% !important;
            max-width: 100% !important;
            gap: 2.5rem !important;
        }
        /* Style the individual feature cards for better appearance */
        .page-content div[style*="display: grid"] > div {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
            padding: 2.5rem 2rem !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07) !important;
            transition: transform 0.2s, box-shadow 0.2s !important;
            min-height: auto !important;
            height: 100% !important;
            justify-content: flex-start !important;
            flex: 1 1 auto !important;
        }
        .page-content div[style*="display: grid"] > div:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.12) !important;
        }
        /* Fix icon styling */
        .page-content div[style*="display: grid"] > div > div[style*="font-size:"] {
            margin-bottom: 1.75rem !important;
            display: block !important;
            font-size: 3.5rem !important;
        }
        /* Fix title styling - better line breaks and spacing */
        .page-content div[style*="display: grid"] > div h3 {
            font-size: 1.35rem !important;
            font-weight: 700 !important;
            margin-bottom: 1.25rem !important;
            color: #1e293b !important;
            line-height: 1.4 !important;
            word-break: break-word !important;
            hyphens: auto !important;
            padding: 0 0.5rem !important;
        }
        /* Fix description styling */
        .page-content div[style*="display: grid"] > div p {
            color: #64748b !important;
            font-size: 1rem !important;
            line-height: 1.7 !important;
            margin: 0 !important;
            text-align: center !important;
            padding: 0 0.5rem !important;
        }
        /* Responsive breakpoints for 6-column grid (Why Choose section) */
        @media (max-width: 1400px) {
            .page-content div[style*="grid-template-columns: repeat(6"] {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }
        @media (max-width: 1200px) {
            .page-content div[style*="grid-template-columns: repeat(6"] {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
        @media (max-width: 768px) {
            .page-content div[style*="grid-template-columns: repeat(6"],
            .page-content div[style*="grid-template-columns: repeat(3"] {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1.5rem !important;
            }
            .page-content div[style*="display: grid"] > div {
                min-height: auto !important;
                padding: 1.5rem 1rem !important;
            }
            .page-content div[style*="display: grid"] > div h3 {
                white-space: normal !important;
                font-size: 1.1rem !important;
            }
        }
        @media (max-width: 480px) {
            .page-content div[style*="grid-template-columns: repeat(6"],
            .page-content div[style*="grid-template-columns: repeat(3"] {
                grid-template-columns: 1fr !important;
            }
        }
        /* Additional responsive adjustments for 3-column grid */
        @media (min-width: 1400px) {
            .page-content div[style*="grid-template-columns: repeat(3"] {
                gap: 3rem !important;
            }
            .page-content div[style*="display: grid"] > div {
                padding: 3rem 2.5rem !important;
            }
        }
        .cms-content {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
            padding-top: 2rem !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }
        body {
            overflow-x: hidden !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }
        <?php endif; ?>
        <?php if ($isVacanciesPage): ?>
        body.vacancies-page {
            background: #f8fafc;
            color: #1e293b;
        }
        body.vacancies-page .cms-content {
            background: white;
            padding: 0 !important;
            margin: 0;
        }
        body.vacancies-page .page-content-wrapper,
        body.vacancies-page .page-content {
            background: white !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }
        body.vacancies-page .page-hero {
            display: none !important;
        }
        .vacancies-hero {
            background: linear-gradient(135deg, rgba(14,165,233,0.08) 0%, rgba(59,130,246,0.12) 100%);
            padding: 4.5rem 2rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .vacancies-hero__inner {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }
        .vacancies-hero__eyebrow {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(14,165,233,0.15);
            color: #0369a1;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 0.75rem;
            margin-bottom: 1rem;
        }
        .vacancies-hero__title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #0f172a;
        }
        .vacancies-hero__lead {
            font-size: 1.15rem;
            color: #475569;
            line-height: 1.8;
            margin: 0 auto;
            max-width: 720px;
        }
        .vacancies-section {
            position: relative;
            margin-top: -2.5rem;
            padding-bottom: 4rem;
        }
        .vacancies-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 2rem 4rem;
            position: relative;
            z-index: 2;
        }
        .vacancies-intro {
            background: white;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 18px;
            padding: 2.25rem;
            margin-bottom: 2.75rem;
            color: #475569;
            line-height: 1.8;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }
        .vacancies-intro * {
            color: inherit;
        }
        .vacancies-alert {
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(226, 232, 240, 0.3);
            color: #1e293b;
            margin-bottom: 2rem;
        }
        .vacancies-alert--info {
            border-color: rgba(14, 165, 233, 0.25);
            background: rgba(14, 165, 233, 0.08);
            color: #0f172a;
        }
        .vacancies-alert--warning {
            border-color: rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.12);
            color: #92400e;
        }
        .vacancy-card {
            background: white;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 20px;
            padding: 2.75rem;
            margin-bottom: 2.75rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .vacancy-card:hover {
            transform: translateY(-6px);
            border-color: rgba(14, 165, 233, 0.3);
            box-shadow: 0 30px 70px rgba(14, 165, 233, 0.18);
        }
        .vacancy-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background: rgba(14, 165, 233, 0.12);
            color: #0369a1;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .vacancy-card__title {
            font-size: 1.9rem;
            font-weight: 600;
            color: #0f172a;
            margin: 1rem 0 0.75rem;
        }
        .vacancy-card__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }
        .vacancy-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            background: rgba(226, 232, 240, 0.8);
            color: #1e40af;
        }
        .vacancy-meta-chip--success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        .vacancy-card__body {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 2.5rem;
        }
        .vacancy-section-title {
            font-size: 1.1rem;
            margin-bottom: 0.85rem;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .vacancy-body-copy {
            color: #475569;
            line-height: 1.8;
        }
        .vacancy-section-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .vacancy-section-list li {
            position: relative;
            padding-left: 1.4rem;
            margin-bottom: 0.6rem;
            color: #475569;
        }
        .vacancy-section-list li::before {
            content: "▹";
            position: absolute;
            left: 0;
            color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
        .vacancy-apply-panel {
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            padding-top: 1.5rem;
        }
        .vacancy-apply-panel details {
            background: rgba(241, 245, 249, 0.7);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            border: 1px solid rgba(203, 213, 225, 0.7);
        }
        .vacancy-apply-panel summary {
            cursor: pointer;
            list-style: none;
            font-weight: 600;
            color: <?php echo htmlspecialchars($primaryColor); ?>;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .vacancy-apply-panel summary::marker { display: none; }
        .vacancy-apply-panel summary svg { transition: transform 0.3s ease; }
        .vacancy-apply-panel details[open] summary svg { transform: rotate(180deg); }
        .vacancy-application-form {
            margin-top: 1.25rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .vacancy-application-form label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 500;
            color: #64748b;
            display: block;
            margin-bottom: 0.45rem;
        }
        .vacancy-application-form input[type="text"],
        .vacancy-application-form input[type="email"],
        .vacancy-application-form input[type="tel"],
        .vacancy-application-form input[type="number"],
        .vacancy-application-form input[type="date"],
        .vacancy-application-form textarea,
        .vacancy-application-form input[type="file"] {
            width: 100%;
            padding: 0.7rem 0.85rem;
            border-radius: 14px;
            border: 1px solid rgba(203, 213, 225, 0.8);
            background: white;
            color: #0f172a;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .vacancy-application-form input:focus,
        .vacancy-application-form textarea:focus {
            outline: none;
            border-color: rgba(14, 165, 233, 0.6);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
        }
        .vacancy-application-form textarea {
            min-height: 120px;
            resize: vertical;
        }
        .vacancy-form-actions {
            grid-column: 1 / -1;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .vacancy-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.75rem;
            border-radius: 9999px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primaryColor); ?> 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 15px 30px rgba(59, 130, 246, 0.35);
        }
        .vacancy-btn:hover {
            transform: translateY(-2px);
        }
        .vacancy-status-text {
            font-size: 0.85rem;
            color: #475569;
        }
        .vacancy-apply-text {
            color: #475569;
            margin-bottom: 1rem;
        }
        .vacancy-apply-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.75rem;
            border-radius: 9999px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            background: linear-gradient(135deg, <?php echo htmlspecialchars($primaryColor); ?> 0%, #1d4ed8 100%);
            color: #fff;
            box-shadow: 0 15px 30px rgba(59, 130, 246, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .vacancy-apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 36px rgba(59, 130, 246, 0.35);
        }
        .vacancy-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
        }
        .vacancy-modal.is-open {
            display: flex;
        }
        .vacancy-modal__overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
        }
        .vacancy-modal__dialog {
            position: relative;
            background: #fff;
            border-radius: 24px;
            max-width: 840px;
            width: min(92vw, 840px);
            max-height: 92vh;
            overflow-y: auto;
            padding: 3rem 3rem 2.5rem;
            box-shadow: 0 40px 80px rgba(15, 23, 42, 0.25);
            animation: vacancyModalIn 0.3s ease;
        }
        @keyframes vacancyModalIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .vacancy-modal__close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            border: none;
            background: rgba(226, 232, 240, 0.6);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            color: #1e293b;
            transition: background 0.2s;
        }
        .vacancy-modal__close:hover {
            background: rgba(148, 163, 184, 0.4);
        }
        .vacancy-modal__header {
            margin-bottom: 2rem;
        }
        .vacancy-modal__eyebrow {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(14, 165, 233, 0.12);
            color: #0369a1;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 0.75rem;
            margin-bottom: 1rem;
        }
        .vacancy-modal__title {
            font-size: 2.25rem;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }
        .vacancy-modal__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            color: #475569;
            font-size: 0.95rem;
        }
        .vacancy-modal__meta-item::before {
            content: "•";
            margin-right: 0.4rem;
            color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
        .vacancy-modal__meta-item:first-child::before {
            content: "";
            margin-right: 0;
        }
        .vacancy-modal-form {
            display: block;
        }
        .vacancy-honeypot {
            position: absolute;
            opacity: 0;
            pointer-events: none;
            height: 0;
            width: 0;
        }
        .vacancy-modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem 1.25rem;
        }
        .vacancy-modal-grid label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 500;
            color: #64748b;
            display: block;
            margin-bottom: 0.45rem;
        }
        .vacancy-modal-grid input[type="text"],
        .vacancy-modal-grid input[type="email"],
        .vacancy-modal-grid input[type="tel"],
        .vacancy-modal-grid input[type="number"],
        .vacancy-modal-grid input[type="date"],
        .vacancy-modal-grid input[type="url"],
        .vacancy-modal-grid textarea,
        .vacancy-modal-grid input[type="file"] {
            width: 100%;
            padding: 0.7rem 0.85rem;
            border-radius: 14px;
            border: 1px solid rgba(203, 213, 225, 0.8);
            background: white;
            color: #0f172a;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .vacancy-modal-grid input:focus,
        .vacancy-modal-grid textarea:focus {
            outline: none;
            border-color: rgba(14, 165, 233, 0.6);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18);
        }
        .vacancy-modal-grid textarea {
            min-height: 150px;
            resize: vertical;
        }
        .vacancy-modal-grid input[type="file"] {
            padding: 0.6rem 0.75rem;
        }
        .vacancy-modal-span {
            grid-column: 1 / -1;
        }
        .vacancy-modal-help {
            display: block;
            margin-top: 0.35rem;
            color: #94a3b8;
            font-size: 0.8rem;
        }
        .vacancy-modal-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-top: 1.75rem;
        }
        .vacancy-modal-status {
            font-size: 0.9rem;
            color: #475569;
            min-height: 1.2em;
        }
        body.modal-open {
            overflow: hidden;
        }
        @media (max-width: 880px) {
            .vacancies-hero__title {
                font-size: 2.4rem;
            }
            .vacancies-section {
                margin-top: -3rem;
            }
            .vacancy-card__body {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .vacancy-modal__dialog {
                width: min(94vw, 680px);
                padding: 2.5rem 1.75rem 2rem;
            }
            .vacancy-modal__meta {
                flex-direction: column;
                gap: 0.4rem;
            }
        }
        @media (max-width: 600px) {
            .vacancies-container {
                padding: 0 1.25rem 3rem;
            }
            .vacancies-hero {
                padding: 4rem 1.5rem 5rem;
            }
            .vacancies-hero__title {
                font-size: 2.1rem;
            }
            .vacancies-hero__lead {
                font-size: 1rem;
            }
            .vacancy-modal__dialog {
                width: 96vw;
                max-height: 96vh;
                border-radius: 18px;
                padding: 2.25rem 1.5rem 1.75rem;
            }
            .vacancy-modal-grid {
                grid-template-columns: 1fr;
            }
            .vacancy-modal-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
        <?php endif; ?>
        .page-content h1 {
            color: #1e293b;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            line-height: 1.2;
            border-bottom: 3px solid <?php echo htmlspecialchars($primaryColor); ?>;
            padding-bottom: 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content h2 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 600;
            margin: 2.5rem 0 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content h3 {
            color: #334155;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content p {
            margin-top: 1.5rem;
            line-height: 1.9;
            color: #475569;
            font-size: 1.0625rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content ul, .page-content ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
            line-height: 1.9;
            color: #475569;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content li {
            margin: 0.75rem 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content a {
            color: <?php echo htmlspecialchars($primaryColor); ?>;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: all 0.2s;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .page-content a:hover {
            border-bottom-color: <?php echo htmlspecialchars($primaryColor); ?>;
        }
        .page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .breadcrumb {
            background: rgba(255,255,255,0.1);
            padding: 1rem 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: white;
            opacity: 0.9;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            opacity: 1;
        }
        @media (max-width: 768px) {
            .page-hero h1 { font-size: 2rem; }
            .page-hero { padding: 3rem 1rem; }
            .page-content { padding: 2rem 1.5rem; }
            .container { padding: 0 1rem; }
        }
    </style>
</head>
<body<?php echo $bodyClassAttr; ?>>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content" style="margin-bottom: 0; padding-bottom: 0; <?php if ($pageSlug === 'about-us'): ?>padding-top: 0 !important; background: transparent !important;<?php endif; ?>">
        <?php if ($shouldShowHero): ?>
            <!-- Hero Banner -->
            <section class="hero-banner" style="position: relative; width: 100%; min-height: 500px; display: flex; align-items: center; justify-content: center; text-align: center; color: white; padding: 6rem 2rem; background: <?php echo $heroImageUrl ? 'url(' . htmlspecialchars($heroImageUrl) . ')' : 'linear-gradient(135deg, ' . htmlspecialchars($primaryColor) . ' 0%, #0284c7 100%)'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, <?php echo htmlspecialchars($heroOverlay); ?>); z-index: 1;"></div>
                <div class="container" style="position: relative; z-index: 2; max-width: 1200px; margin: 0 auto;">
                    <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1.5rem; line-height: 1.2; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($heroTitle); ?></h1>
                    <?php if ($heroSubtitle): ?>
                        <p style="font-size: 1.5rem; margin-bottom: 2.5rem; opacity: 0.95; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($heroSubtitle); ?></p>
                    <?php endif; ?>
                    <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                        <?php if ($heroButton1Text && $heroButton1Link): ?>
                            <a href="<?php echo htmlspecialchars($heroButton1Link); ?>" style="padding: 1rem 2.5rem; background: white; color: <?php echo htmlspecialchars($primaryColor); ?>; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 1.1rem; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.3);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"><?php echo htmlspecialchars($heroButton1Text); ?></a>
                        <?php endif; ?>
                        <?php if ($heroButton2Text && $heroButton2Link): ?>
                            <a href="<?php echo htmlspecialchars($heroButton2Link); ?>" style="padding: 1rem 2.5rem; background: transparent; color: white; text-decoration: none; border: 2px solid white; border-radius: 8px; font-weight: 700; font-size: 1.1rem; transition: all 0.2s;" onmouseover="this.style.background='white'; this.style.color='<?php echo htmlspecialchars($primaryColor); ?>'" onmouseout="this.style.background='transparent'; this.style.color='white'"><?php echo htmlspecialchars($heroButton2Text); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php elseif (!$isVacanciesPage): ?>
            <!-- Simple Page Header (if hero not enabled) -->
            <div class="page-hero">
                <div class="container">
                    <h1><?php echo htmlspecialchars($page['title']); ?></h1>
                    <?php if (!empty($page['seo_description'])): ?>
                        <p><?php echo htmlspecialchars($page['seo_description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($pageSlug === 'about-us'): ?>
            <!-- About Us page - full width, no wrapper constraints -->
            <?php 
            $content = $page['content'] ?? '';
            // For about-us, output content directly without wrapper divs
            echo $content;
            ?>
        <?php elseif ($isVacanciesPage): ?>
            <section class="vacancies-hero">
                <div class="vacancies-hero__inner">
                    <span class="vacancies-hero__eyebrow">Grow With ABBIS</span>
                    <h1 class="vacancies-hero__title"><?php echo htmlspecialchars($vacanciesHeroTitle); ?></h1>
                    <p class="vacancies-hero__lead"><?php echo htmlspecialchars($vacanciesHeroSubtitle); ?></p>
                </div>
            </section>
            <section class="vacancies-section">
                <div class="vacancies-container">
                    <?php if (!empty($vacanciesIntroHtml)): ?>
                        <div class="vacancies-intro">
                            <?php echo $vacanciesIntroHtml; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$vacanciesTablesReady): ?>
                        <div class="vacancies-alert vacancies-alert--warning">
                            The careers module is setting up. Please refresh in a moment or contact the site administrator if this message persists.
                        </div>
                    <?php elseif (!empty($vacanciesErrors)): ?>
                        <div class="vacancies-alert vacancies-alert--warning">
                            We're unable to load vacancies right now. Please try again later.
                        </div>
                    <?php elseif (empty($vacanciesList)): ?>
                        <div class="vacancies-alert vacancies-alert--info">
                            We do not have open positions at the moment. You can send your resume to 
                            <a href="mailto:careers@abbis.ai" style="color:#38bdf8;">careers@abbis.ai</a> and we will contact you when a suitable role becomes available.
                        </div>
                    <?php else: ?>
                        <?php foreach ($vacanciesList as $vacancy): ?>
                            <?php
                                $employmentType = !empty($vacancy['employment_type']) ? ucwords(str_replace('_', ' ', $vacancy['employment_type'])) : 'Full Time';
                                $seniorityLevel = !empty($vacancy['seniority_level']) ? ucwords(str_replace('_', ' ', $vacancy['seniority_level'])) : 'Team Member';
                                $closingDateLabel = '';
                                if (!empty($vacancy['closing_date'])) {
                                    $closingTimestamp = strtotime($vacancy['closing_date']);
                                    $closingDateLabel = $closingTimestamp ? date('F j, Y', $closingTimestamp) : $vacancy['closing_date'];
                                }
                                $salaryVisible = !empty($vacancy['salary_visible']) && ($vacancy['salary_min'] || $vacancy['salary_max']);
                                $salaryCurrency = $vacancy['salary_currency'] ?: 'GHS';
                                $salaryRange = '';
                                if ($salaryVisible) {
                                    $salaryParts = [];
                                    if (!empty($vacancy['salary_min'])) {
                                        $salaryParts[] = number_format((float) $vacancy['salary_min'], 0);
                                    }
                                    if (!empty($vacancy['salary_max'])) {
                                        $salaryParts[] = number_format((float) $vacancy['salary_max'], 0);
                                    }
                                    if (!empty($salaryParts)) {
                                        $salaryRange = $salaryCurrency . ' ' . implode(' - ', $salaryParts);
                                    }
                                }
                            ?>
                            <article class="vacancy-card" id="vacancy-<?php echo intval($vacancy['id']); ?>">
                                <div class="vacancy-card__header">
                                    <span class="vacancy-card__badge">Vacancy ID: <?php echo htmlspecialchars($vacancy['vacancy_code']); ?></span>
                                    <h2 class="vacancy-card__title"><?php echo htmlspecialchars($vacancy['title']); ?></h2>
                                    <div class="vacancy-card__meta">
                                        <?php if (!empty($vacancy['location'])): ?>
                                            <span class="vacancy-meta-chip">📍 <?php echo htmlspecialchars($vacancy['location']); ?></span>
                                        <?php endif; ?>
                                        <span class="vacancy-meta-chip">🕒 <?php echo htmlspecialchars($employmentType); ?></span>
                                        <span class="vacancy-meta-chip">🎯 <?php echo htmlspecialchars($seniorityLevel); ?> Level</span>
                                        <?php if ($salaryVisible): ?>
                                            <span class="vacancy-meta-chip vacancy-meta-chip--success">
                                                💰 <?php echo htmlspecialchars($salaryCurrency); ?>
                                                <?php if (!empty($vacancy['salary_min'])): ?>
                                                    <?php echo number_format($vacancy['salary_min'], 0); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($vacancy['salary_max'])): ?>
                                                    - <?php echo number_format($vacancy['salary_max'], 0); ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($closingDateLabel): ?>
                                            <span class="vacancy-meta-chip">🗓 Closes <?php echo htmlspecialchars($closingDateLabel); ?></span>
                                        <?php endif; ?>
                                        <span class="vacancy-meta-chip">📈 <?php echo intval($vacancy['application_count']); ?> applicants</span>
                                    </div>
                                </div>
                                <div class="vacancy-card__body">
                                    <div>
                                        <h3 class="vacancy-section-title">About the Role</h3>
                                        <p class="vacancy-body-copy"><?php echo nl2br(htmlspecialchars($vacancy['description'] ?: 'Help us deliver reliable water services to communities and businesses across Ghana.')); ?></p>
                                        
                                        <?php if (!empty($vacancy['responsibilities'])): ?>
                                            <h3 class="vacancy-section-title">Key Responsibilities</h3>
                                            <ul class="vacancy-section-list">
                                                <?php foreach (preg_split('/\r\n|\r|\n/', trim($vacancy['responsibilities'])) as $item): ?>
                                                    <?php if ($item !== ''): ?>
                                                        <li><?php echo htmlspecialchars($item); ?></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($vacancy['requirements'])): ?>
                                            <h3 class="vacancy-section-title">What We're Looking For</h3>
                                            <ul class="vacancy-section-list">
                                                <?php foreach (preg_split('/\r\n|\r|\n/', trim($vacancy['requirements'])) as $item): ?>
                                                    <?php if ($item !== ''): ?>
                                                        <li><?php echo htmlspecialchars($item); ?></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($vacancy['benefits'])): ?>
                                            <h3 class="vacancy-section-title">Benefits & Growth</h3>
                                            <p class="vacancy-body-copy"><?php echo nl2br(htmlspecialchars($vacancy['benefits'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vacancy-apply-panel">
                                        <h3 class="vacancy-section-title">Ready to Apply?</h3>
                                        <p class="vacancy-apply-text">We review applications daily. Share your details and we'll reach out if you're a match.</p>
                                        <button
                                            type="button"
                                            class="vacancy-apply-btn"
                                            data-id="<?php echo intval($vacancy['id']); ?>"
                                            data-title="<?php echo htmlspecialchars($vacancy['title']); ?>"
                                            data-location="<?php echo htmlspecialchars($vacancy['location'] ?: 'Flexible'); ?>"
                                            data-employment="<?php echo htmlspecialchars($employmentType); ?>"
                                            data-seniority="<?php echo htmlspecialchars($seniorityLevel); ?>"
                                            data-salary="<?php echo htmlspecialchars($salaryRange ?: 'Competitive'); ?>"
                                            data-closing="<?php echo htmlspecialchars($closingDateLabel ?: 'Open until filled'); ?>"
                                        >
                                            Apply Now
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            <div class="vacancy-modal" id="vacancyModal" aria-hidden="true">
                <div class="vacancy-modal__overlay" data-close></div>
                <div class="vacancy-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vacancyModalTitle">
                    <button type="button" class="vacancy-modal__close" data-close aria-label="Close application form">✕</button>
                    <div class="vacancy-modal__header">
                        <span class="vacancy-modal__eyebrow">Now Hiring</span>
                        <h2 id="vacancyModalTitle" class="vacancy-modal__title">Apply</h2>
                        <div class="vacancy-modal__meta">
                            <span class="vacancy-modal__meta-item vacancy-modal__meta-location">Location · Flexible</span>
                            <span class="vacancy-modal__meta-item vacancy-modal__meta-type">Employment · Full Time</span>
                            <span class="vacancy-modal__meta-item vacancy-modal__meta-salary">Salary · Competitive</span>
                            <span class="vacancy-modal__meta-item vacancy-modal__meta-closing">Closes · Open until filled</span>
                        </div>
                    </div>
                    <form class="vacancy-modal-form" enctype="multipart/form-data" data-api="<?php echo htmlspecialchars($vacanciesApiEndpoint); ?>">
                        <input type="hidden" name="vacancy_id" value="">
                        <input type="text" name="company" class="vacancy-honeypot" autocomplete="off">
                        <div class="vacancy-modal-grid">
                            <div>
                                <label>First Name *</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div>
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div>
                                <label>Email *</label>
                                <input type="email" name="email" required>
                            </div>
                            <div>
                                <label>Phone</label>
                                <input type="tel" name="phone" placeholder="+233 000 000 000">
                            </div>
                            <div>
                                <label>Country</label>
                                <input type="text" name="country" placeholder="Ghana">
                            </div>
                            <div>
                                <label>City</label>
                                <input type="text" name="city" placeholder="Accra">
                            </div>
                            <div>
                                <label>LinkedIn URL</label>
                                <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/you">
                            </div>
                            <div>
                                <label>Portfolio URL</label>
                                <input type="url" name="portfolio_url" placeholder="https://yourportfolio.com">
                            </div>
                            <div>
                                <label>Years of Experience</label>
                                <input type="number" name="years_experience" min="0" step="0.5">
                            </div>
                            <div>
                                <label>Highest Education</label>
                                <input type="text" name="highest_education" placeholder="BSc, MSc, etc.">
                            </div>
                            <div>
                                <label>Current Employer</label>
                                <input type="text" name="current_employer">
                            </div>
                            <div>
                                <label>Current Position</label>
                                <input type="text" name="current_position">
                            </div>
                            <div>
                                <label>Expected Salary (optional)</label>
                                <input type="number" name="expected_salary" step="0.01">
                            </div>
                            <div>
                                <label>Availability Date</label>
                                <input type="date" name="availability_date">
                            </div>
                            <div class="vacancy-modal-span">
                                <label>Cover Letter</label>
                                <textarea name="cover_letter" placeholder="Tell us why you would be a great fit for this role."></textarea>
                            </div>
                            <div>
                                <label>Upload Resume *</label>
                                <input type="file" name="resume" accept=".pdf,.doc,.docx" required>
                            </div>
                            <div>
                                <label>Cover Letter (PDF/DOC)</label>
                                <input type="file" name="cover_letter_file" accept=".pdf,.doc,.docx">
                            </div>
                            <div class="vacancy-modal-span">
                                <label>Supporting Documents</label>
                                <input type="file" name="supporting_documents[]" accept=".pdf,.doc,.docx" multiple>
                                <small class="vacancy-modal-help">You can add certificates or references (optional).</small>
                            </div>
                        </div>
                        <div class="vacancy-modal-actions">
                            <button type="submit" class="vacancy-btn">
                                <span>Submit Application</span> 🚀
                            </button>
                            <span class="vacancy-modal-status" aria-live="polite"></span>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Regular pages with wrapper -->
            <div class="page-content-wrapper" style="margin-bottom: 0;">
                <div class="page-content" style="margin-bottom: 0;">
                    <?php 
                    // Check if content contains HTML tags or is from GrapesJS
                    $content = $page['content'] ?? '';
                    $hasHtml = (strpos($content, '<') !== false && strpos($content, '>') !== false) || 
                               strpos($content, 'gjs-') !== false || 
                               strpos($content, '<style>') !== false ||
                               strpos($content, '<div') !== false ||
                               strpos($content, '<h1') !== false ||
                               strpos($content, '<h2') !== false ||
                               strpos($content, '<ul>') !== false ||
                               strpos($content, '<p>') !== false;
                    
                    if ($hasHtml) {
                        // HTML content (from GrapesJS, default pages, or CMS editor) - output as HTML
                        // Content is already sanitized or from trusted sources (default pages)
                        echo $content;
                    } else {
                        // Plain text content - use nl2br and escape
                        echo '<div style="line-height: 1.9; color: #475569; font-size: 1.0625rem;">';
                        echo nl2br(htmlspecialchars($content));
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <?php if ($isVacanciesPage): ?>
    <script>
        (function() {
            const modal = document.getElementById('vacancyModal');
            if (!modal) return;
            
            const body = document.body;
            const form = modal.querySelector('.vacancy-modal-form');
            const statusEl = modal.querySelector('.vacancy-modal-status');
            const titleEl = modal.querySelector('#vacancyModalTitle');
            const metaLocation = modal.querySelector('.vacancy-modal__meta-location');
            const metaType = modal.querySelector('.vacancy-modal__meta-type');
            const metaSalary = modal.querySelector('.vacancy-modal__meta-salary');
            const metaClosing = modal.querySelector('.vacancy-modal__meta-closing');
            const vacancyIdInput = form.querySelector('input[name="vacancy_id"]');
            const applyButtons = document.querySelectorAll('.vacancy-apply-btn');
            const closeSelectors = modal.querySelectorAll('[data-close]');
            const firstField = form.querySelector('input[name="first_name"]');
            const apiUrl = form.dataset.api;
            
            function openModal(data) {
                modal.classList.add('is-open');
                body.classList.add('modal-open');
                modal.setAttribute('aria-hidden', 'false');
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.style.color = '#475569';
                }
                form.reset();
                vacancyIdInput.value = data.id || '';
                titleEl.textContent = data.title || 'Apply';
                metaLocation.textContent = `Location · ${data.location || 'Flexible'}`;
                metaType.textContent = `Employment · ${data.employment || 'Full Time'}`;
                metaSalary.textContent = `Salary · ${data.salary || 'Competitive'}`;
                metaClosing.textContent = `Closes · ${data.closing || 'Open until filled'}`;
                setTimeout(() => {
                    if (firstField) {
                        firstField.focus();
                    }
                }, 150);
            }
            
            function closeModal() {
                modal.classList.remove('is-open');
                body.classList.remove('modal-open');
                modal.setAttribute('aria-hidden', 'true');
            }
            
            applyButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const dataset = btn.dataset || {};
                    openModal({
                        id: dataset.id,
                        title: dataset.title,
                        location: dataset.location,
                        employment: dataset.employment,
                        salary: dataset.salary,
                        closing: dataset.closing
                    });
                });
            });
            
            closeSelectors.forEach(el => {
                el.addEventListener('click', () => {
                    closeModal();
                });
            });
            
            modal.addEventListener('click', function(event) {
                if (event.target.classList.contains('vacancy-modal__overlay')) {
                    closeModal();
                }
            });
            
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
            
            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                if (statusEl) {
                    statusEl.textContent = 'Submitting your application...';
                    statusEl.style.color = '#475569';
                }
                
                const formData = new FormData(form);
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        if (statusEl) {
                            statusEl.textContent = 'Thank you! Your application has been received. Reference: ' + (result.application_code || 'N/A');
                            statusEl.style.color = '#047857';
                        }
                        form.reset();
                        setTimeout(closeModal, 2000);
                    } else {
                        throw new Error(result.message || 'Submission failed.');
                    }
                } catch (err) {
                    if (statusEl) {
                        statusEl.textContent = err.message;
                        statusEl.style.color = '#b91c1c';
                    }
                }
            });
        })();
    </script>
    <?php endif; ?>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

