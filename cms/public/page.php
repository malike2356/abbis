<?php
/**
 * Custom CMS Page Template
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();

// Get page slug from URL - handle both direct access and routed access
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    // Try to get slug from REQUEST_URI
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Remove query string
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

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Company');
$siteTitle = ($page['seo_title'] ?? $page['title']) . ' - ' . $companyName;
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
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
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
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
        .page-content {
            background: white;
            padding: 4rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .page-content h1 {
            color: #1e293b;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            line-height: 1.2;
            border-bottom: 3px solid <?php echo htmlspecialchars($primaryColor); ?>;
            padding-bottom: 1rem;
        }
        .page-content h2 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 600;
            margin: 2.5rem 0 1rem;
        }
        .page-content h3 {
            color: #334155;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
        }
        .page-content p {
            margin-top: 1.5rem;
            line-height: 1.9;
            color: #475569;
            font-size: 1.0625rem;
        }
        .page-content ul, .page-content ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
            line-height: 1.9;
            color: #475569;
        }
        .page-content li {
            margin: 0.75rem 0;
        }
        .page-content a {
            color: <?php echo htmlspecialchars($primaryColor); ?>;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: all 0.2s;
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
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <div class="page-hero">
            <div class="container">
                <h1><?php echo htmlspecialchars($page['title']); ?></h1>
                <?php if (!empty($page['seo_description'])): ?>
                    <p><?php echo htmlspecialchars($page['seo_description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="container">
            <div class="page-content">
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
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

