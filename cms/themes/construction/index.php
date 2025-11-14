<?php
/**
 * Construction Theme - Homepage Template
 * Completely rebuilt for clean, professional layout
 */
// Error reporting - hide errors in production but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Ensure all required variables are set with defaults if missing
$primaryColor = isset($themeConfig) && is_array($themeConfig) && isset($themeConfig['primary_color']) ? $themeConfig['primary_color'] : '#0ea5e9';
$secondaryColor = isset($themeConfig) && is_array($themeConfig) && isset($themeConfig['secondary_color']) ? $themeConfig['secondary_color'] : '#0f2440';
$baseUrl = isset($baseUrl) && $baseUrl ? $baseUrl : '/abbis3.2';
$themeUrl = $baseUrl . '/cms/themes/construction';
$siteTitle = isset($siteTitle) && $siteTitle ? $siteTitle : 'Our Company';
$siteTagline = isset($siteTagline) && $siteTagline ? $siteTagline : 'Quality Services';
$homepage = isset($homepage) ? $homepage : null;
$recentPosts = isset($recentPosts) && is_array($recentPosts) ? $recentPosts : [];
$cmsSettings = isset($cmsSettings) && is_array($cmsSettings) ? $cmsSettings : [];

if (!isset($rootPath)) {
    $rootPath = dirname(dirname(dirname(__DIR__)));
}

// Ensure database connection and helper functions
if (!isset($pdo)) {
    try {
        if (file_exists($rootPath . '/config/app.php')) {
            require_once $rootPath . '/config/app.php';
        }
        if (file_exists($rootPath . '/includes/functions.php')) {
            require_once $rootPath . '/includes/functions.php';
        }
        // Include helpers for formatCurrency and other helper functions
        if (file_exists($rootPath . '/includes/helpers.php')) {
            require_once $rootPath . '/includes/helpers.php';
        }
        if (function_exists('getDBConnection')) {
            $pdo = getDBConnection();
        }
    } catch (Throwable $e) {
        $pdo = null;
    }
}

// Ensure formatCurrency function exists (fallback if helpers.php not loaded)
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'GHS ' . number_format((float)($amount ?? 0), 2);
    }
}

// Get CMS settings if not set
if (empty($cmsSettings) && isset($pdo)) {
    try {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
        if ($settingsStmt) {
            $cmsSettings = [];
            while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
                $cmsSettings[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        $cmsSettings = [];
    }
}

$systemConfig = isset($systemConfig) && is_array($systemConfig) ? $systemConfig : [];
if (empty($systemConfig) && isset($pdo)) {
    try {
        $configStmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('company_name','company_logo','company_phone','company_email','company_tagline')");
        while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
            $systemConfig[$row['config_key']] = $row['config_value'];
        }
    } catch (Throwable $e) {
        $systemConfig = [];
    }
}

$contactPhone = trim($cmsSettings['contact_phone'] ?? ($systemConfig['company_phone'] ?? ''));
$contactEmail = trim($cmsSettings['contact_email'] ?? ($systemConfig['company_email'] ?? ''));
$callLink = $contactPhone ? 'tel:' . preg_replace('/\s+/', '', $contactPhone) : '';
$whatsRaw = preg_replace('/\D+/', '', $cmsSettings['whatsapp_number'] ?? $contactPhone ?? '');
$whatsLink = trim($cmsSettings['whatsapp_link'] ?? '');
if (!$whatsLink && $whatsRaw) {
    $normalizedWhats = ltrim($whatsRaw, '+');
    $whatsLink = 'https://wa.me/' . $normalizedWhats;
}
$contactPageLink = $cmsSettings['contact_page_url'] ?? ($baseUrl . '/cms/contact');

// Get services from catalog if not already set
if (!isset($services) || !is_array($services)) {
    $services = [];
    if (isset($pdo)) {
        try {
            $servicesStmt = $pdo->query("SELECT * FROM catalog_items WHERE is_active=1 AND is_sellable=1 AND item_type='product' LIMIT 6");
            if ($servicesStmt) {
                $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Throwable $e) {
            $services = [];
        }
    }
}

// Load hero banner helper
require_once $rootPath . '/cms/includes/hero-banner-helper.php';

// Check if hero should be displayed
$currentPageType = getCurrentPageType();
$shouldShowHero = shouldDisplayHeroBanner($cmsSettings, $currentPageType);

// Hero banner settings
$heroImage = isset($cmsSettings['hero_banner_image']) ? $cmsSettings['hero_banner_image'] : '';
$heroTitle = isset($cmsSettings['hero_title']) ? $cmsSettings['hero_title'] : ($siteTitle ?: 'Welcome to Professional Borehole Services');
$heroSubtitle = isset($cmsSettings['hero_subtitle']) ? $cmsSettings['hero_subtitle'] : ($siteTagline ?: 'Quality Water Well Drilling Services');
$heroButton1Text = isset($cmsSettings['hero_button1_text']) ? $cmsSettings['hero_button1_text'] : 'Contact Us';
$heroButton1Link = isset($cmsSettings['hero_button1_link']) ? $cmsSettings['hero_button1_link'] : $baseUrl . '/cms/contact';
$heroButton2Text = isset($cmsSettings['hero_button2_text']) ? $cmsSettings['hero_button2_text'] : 'Our Services';
$heroButton2Link = isset($cmsSettings['hero_button2_link']) ? $cmsSettings['hero_button2_link'] : $baseUrl . '/cms/services';
$heroOverlay = isset($cmsSettings['hero_overlay_opacity']) ? $cmsSettings['hero_overlay_opacity'] : '0.5';
$heroImageUrl = $heroImage ? ($baseUrl . '/' . $heroImage) : '';
$heroBadge = trim($cmsSettings['hero_badge_text'] ?? ($siteTagline ?? 'Professional Borehole Drilling Services'));
$heroBackgroundStyle = $heroImageUrl ? 'url(' . $heroImageUrl . ')' : 'linear-gradient(135deg, ' . $primaryColor . ', #2563eb)';

// Header and footer paths - FIXED
$headerPath = __DIR__ . '/../../public/header.php';
$footerPath = __DIR__ . '/../../public/footer.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?> - <?php echo htmlspecialchars($siteTagline); ?></title>
    
    <!-- Fonts - Using system fonts for consistency -->
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Theme CSS -->
    <link href="<?php echo $themeUrl; ?>/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($primaryColor); ?>;
            --secondary: <?php echo htmlspecialchars($secondaryColor); ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }
        
        /* Preloader - Hidden */
        #preloader {
            display: none !important;
        }
        
        /* Section Styling */
        .section {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .section-title h2 .highlight {
            color: var(--primary);
        }
        
        .section-title .divider {
            width: 80px;
            height: 4px;
            background: var(--primary);
            margin: 0 auto;
        }
        
        /* Hero Section */
        .hero-section {
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            background-size: cover !important;
            background-repeat: no-repeat !important;
            background-position: center center !important;
            background-attachment: scroll;
            color: white;
            padding: 120px 0;
        }
        
        /* Ensure hero background doesn't tile */
        .hero-section[style*="background"] {
            background-size: cover !important;
            background-repeat: no-repeat !important;
            background-position: center center !important;
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, <?php echo floatval($heroOverlay); ?>);
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-badge {
            display: inline-block;
            background: var(--primary);
            padding: 8px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-content p {
            font-size: 1.25rem;
            margin-bottom: 30px;
            opacity: 0.95;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-hero-primary {
            background: var(--primary);
            color: white;
            padding: 15px 35px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-hero-primary:hover {
            background: #1d4ed8;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.35);
        }
        
        .btn-hero-outline {
            background: transparent;
            color: white;
            padding: 15px 35px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid white;
            transition: all 0.3s;
        }
        
        .btn-hero-outline:hover {
            background: white;
            color: var(--primary);
        }
        
        /* Services Grid - Force Grid Layout */
        .services-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important;
            gap: 30px !important;
            margin-top: 40px;
            width: 100% !important;
            grid-auto-flow: row !important;
        }
        
        .services-grid .service-card {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }
        
        /* Prevent Bootstrap interference */
        .container .services-grid {
            display: grid !important;
        }
        
        .row .services-grid,
        [class*="col-"] .services-grid {
            display: grid !important;
        }
        
        .service-card {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .service-image-wrapper {
            width: 100%;
            height: 220px;
            overflow: hidden;
            position: relative;
        }
        
        .service-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .service-icon-wrapper {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, var(--primary), #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .service-icon-wrapper i {
            font-size: 4.5rem;
            color: white;
        }
        
        .service-card-body {
            padding: 30px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .service-card-body h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .service-card-body p {
            color: #666;
            line-height: 1.7;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        
        .service-price {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .btn-service {
            background: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-service:hover {
            background: #1d4ed8;
            color: white;
            transform: translateY(-2px);
        }
        
        /* About Section */
        .about-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        
        /* Blog Section */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        /* Force grid display - override any Bootstrap or other CSS */
        .services-grid * {
            box-sizing: border-box;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .section {
                padding: 60px 0;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-content p {
                font-size: 1.1rem;
            }
            
            .services-grid,
            .blog-grid {
                grid-template-columns: 1fr !important;
            }
            
            .about-stats {
                grid-template-columns: 1fr;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .btn-hero-primary,
            .btn-hero-outline {
                width: 100%;
            }
        }
        
        /* Additional overrides for Bootstrap interference */
        .services-grid > * {
            max-width: 100% !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php if (file_exists($headerPath)): ?>
        <?php include $headerPath; ?>
    <?php endif; ?>

         <!-- Hero Section -->
     <?php if ($shouldShowHero): ?>
     <section class="hero-section" style="background: <?php echo htmlspecialchars($heroBackgroundStyle); ?>; background-size: cover !important; background-repeat: no-repeat !important; background-position: center center !important;">
         <div class="hero-overlay"></div>
         <div class="container">
             <div class="hero-content">
                 <?php if ($heroBadge): ?>
                 <span class="hero-badge"><?php echo htmlspecialchars($heroBadge); ?></span>
                 <?php endif; ?>
                 <h1><?php echo htmlspecialchars($heroTitle); ?></h1>
                 <p><?php echo htmlspecialchars($heroSubtitle); ?></p>
                 <div class="hero-buttons">
                     <a href="<?php echo htmlspecialchars($heroButton1Link); ?>" class="btn-hero-primary">
                         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;margin-right:8px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.09 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.66 12.66 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.66 12.66 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"></path></svg>
                         <?php echo htmlspecialchars($heroButton1Text); ?>
                     </a>
                     <a href="<?php echo htmlspecialchars($heroButton2Link); ?>" class="btn-hero-outline">
                         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;margin-right:8px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"></path><path d="M14.06 4.94l3.75 3.75"></path></svg>
                         <?php echo htmlspecialchars($heroButton2Text); ?>
                     </a>
                 </div>
             </div>
         </div>
     </section>
     <?php endif; ?>

    <?php if ($contactPhone || $contactEmail || $whatsLink): ?>
    <section class="hero-contact-strip">
        <div class="container">
            <div class="contact-card">
                <div class="contact-label">
                    <span>Talk to a specialist</span>
                    <span><?php echo htmlspecialchars($contactPhone ?: 'Request a callback'); ?></span>
                </div>
                <?php if ($contactEmail): ?>
                <div class="contact-label">
                    <span>Email</span>
                    <span><?php echo htmlspecialchars($contactEmail); ?></span>
                </div>
                <?php endif; ?>
                <div class="contact-actions">
                    <?php if ($contactPhone && $callLink): ?>
                    <a href="<?php echo htmlspecialchars($callLink); ?>" aria-label="Call <?php echo htmlspecialchars($siteTitle); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.09 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.66 12.66 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.66 12.66 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"></path></svg>
                        Call us
                    </a>
                    <?php endif; ?>
                    <?php if ($whatsLink): ?>
                    <a href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener" class="secondary" aria-label="Chat on WhatsApp">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 0-8.94 14.56L2 22l5.58-1.47A10 10 0 1 0 12 2Z"></path><path d="M16.24 16.24l-2.29-.65a1 1 0 0 0-.95.26l-.36.37a.52.52 0 0 1-.66.08 8.58 8.58 0 0 1-3.73-3.73.52.52 0 0 1 .08-.66l.37-.36a1 1 0 0 0 .26-.95L8.76 7.76a1 1 0 0 0-1-.58 2.6 2.6 0 0 0-1.71.91 2.68 2.68 0 0 0-.69 1.86 8.36 8.36 0 0 0 2.45 5.16 8.43 8.43 0 0 0 5.16 2.45 2.68 2.68 0 0 0 1.86-.69 2.6 2.6 0 0 0 .91-1.71 1 1 0 0 0-.5-1.03Z"></path></svg>
                        WhatsApp
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($contactPageLink); ?>" class="secondary" aria-label="Request a project estimate">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10a9 9 0 1 1-9-9"></path><polyline points="21 3 21 10 14 10"></polyline></svg>
                        Request estimate
                    </a>
                </div>
            </div>
            <div class="trust-points">
                <div class="trust-card">
                    <strong>Nationwide crews</strong>
                    <span>Teams stationed across all regions</span>
                </div>
                <div class="trust-card">
                    <strong>24hr response</strong>
                    <span>Rapid support for pump failures &amp; outages</span>
                </div>
                <div class="trust-card">
                    <strong>Quality assured</strong>
                    <span>ISO-aligned drilling &amp; water testing protocols</span>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- About Section -->
    <section class="section" style="background: #f8f9fa;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="section-title" style="text-align: left; margin-bottom: 30px;">
                        <h2><span class="highlight">About</span> Our Company</h2>
                        <div class="divider" style="margin: 0;"></div>
                    </div>
                    <?php if ($homepage && !empty($homepage['content'])): ?>
                        <div style="color: #555; line-height: 1.8; font-size: 1.1rem; margin-bottom: 30px;">
                            <?php echo $homepage['content']; ?>
                        </div>
                    <?php else: ?>
                        <div style="color: #555; line-height: 1.8; font-size: 1.1rem; margin-bottom: 30px;">
                            <p>We are a leading provider of professional borehole drilling services, specializing in water well drilling, geophysical surveys, pump installation, and water system maintenance. With years of experience and state-of-the-art equipment, we deliver reliable water solutions for residential, commercial, and industrial clients.</p>
                            <p>Our team of certified professionals ensures every project meets the highest standards of quality and safety. From site selection to final installation, we provide comprehensive water well solutions tailored to your specific needs.</p>
                        </div>
                    <?php endif; ?>
                    <div class="about-stats">
                        <div class="stat-item">
                            <div class="stat-number">200+</div>
                            <div class="stat-label">Projects Completed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">15+</div>
                            <div class="stat-label">Years Experience</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">100%</div>
                            <div class="stat-label">Client Satisfaction</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="<?php echo $themeUrl; ?>/assets/images/about-image.jpg" class="img-fluid rounded" alt="About Us" style="box-shadow: 0 10px 30px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </section>

    <section class="section" style="background: white;">
        <div class="container">
            <div class="section-title">
                <h2><span class="highlight">Integrated</span> Water Solutions</h2>
                <div class="divider"></div>
                <p style="max-width:780px;margin:12px auto 0;color:#475569;">
                    From first site visit to pump commissioning, Kari Boreholes brings geologists, drilling crews, and automation specialists together under one project team.
                </p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <h4>End-to-end delivery</h4>
                    <ul>
                        <li>Hydrogeology desktop studies &amp; groundwater modeling</li>
                        <li>Environmental &amp; community engagement support</li>
                        <li>Rig scheduling, logistics, and on-site quality control</li>
                    </ul>
                </div>
                <div class="feature-card">
                    <h4>Smart water systems</h4>
                    <ul>
                        <li>Solar, hybrid, and diesel-powered pumping solutions</li>
                        <li>Remote telemetry with ABBIS dashboards</li>
                        <li>Water quality lab testing and treatment design</li>
                    </ul>
                </div>
                <div class="feature-card">
                    <h4>Lifetime support</h4>
                    <ul>
                        <li>Preventive maintenance &amp; emergency repairs</li>
                        <li>Parts inventory and OEM equipment sourcing</li>
                        <li>Operator training and asset documentation</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="section" style="background: white;">
        <div class="container">
            <div class="section-title">
                <h2><span class="highlight">Our</span> Services</h2>
                <div class="divider"></div>
            </div>
            
            <div class="services-grid" style="display: grid !important; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important; gap: 30px !important;">
                <?php
                // Get services/products from catalog
                if (empty($services) || !is_array($services) || count($services) === 0):
                    // Default services
                    $defaultServices = [
                        ['icon' => 'fas fa-hammer', 'title' => 'Borehole Drilling', 'desc' => 'Professional deep well drilling up to 200m with modern rotary drilling rigs'],
                        ['icon' => 'fas fa-map-marked-alt', 'title' => 'Geophysical Survey', 'desc' => 'Site selection using advanced geophysical methods to locate water sources'],
                        ['icon' => 'fas fa-pump', 'title' => 'Pump Installation', 'desc' => 'Submersible and surface pump installation with automation systems'],
                        ['icon' => 'fas fa-wrench', 'title' => 'Maintenance & Repair', 'desc' => 'Borehole rehabilitation, pump servicing, and water system maintenance'],
                        ['icon' => 'fas fa-filter', 'title' => 'Water Treatment', 'desc' => 'Filtration, purification, and water quality testing services'],
                        ['icon' => 'fas fa-shopping-cart', 'title' => 'Equipment Sales', 'desc' => 'Pumps, pipes, tanks, and complete water system equipment'],
                    ];
                    foreach ($defaultServices as $service):
                ?>
                    <div class="service-card">
                        <div class="service-icon-wrapper">
                            <i class="<?php echo htmlspecialchars($service['icon']); ?>"></i>
                        </div>
                        <div class="service-card-body">
                            <h3><?php echo htmlspecialchars($service['title']); ?></h3>
                            <p><?php echo htmlspecialchars($service['desc']); ?></p>
                            <a href="<?php echo $baseUrl; ?>/cms/quote" class="btn-service">Learn More</a>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else:
                    if (is_array($services) && count($services) > 0):
                        foreach ($services as $service):
                            if (!is_array($service)) continue;
                            
                            $serviceImage = isset($service['image']) && !empty($service['image']) ? $service['image'] : '';
                            $serviceName = isset($service['item_name']) && $service['item_name'] ? $service['item_name'] : 'Service';
                            $serviceDesc = isset($service['description']) && !empty($service['description']) ? $service['description'] : 'Quality service';
                            $servicePrice = isset($service['sell_price']) ? (float)$service['sell_price'] : 0;
                            $serviceId = isset($service['id']) ? (int)$service['id'] : 0;
                ?>
                    <div class="service-card">
                        <?php if ($serviceImage): ?>
                            <div class="service-image-wrapper">
                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($serviceImage); ?>" alt="<?php echo htmlspecialchars($serviceName); ?>" class="service-image" onerror="this.style.display='none'; this.parentElement.nextElementSibling.style.display='flex';">
                            </div>
                            <div class="service-icon-wrapper" style="display: none;">
                                <i class="fas fa-water"></i>
                            </div>
                        <?php else: ?>
                            <div class="service-icon-wrapper">
                                <i class="fas fa-water"></i>
                            </div>
                        <?php endif; ?>
                        <div class="service-card-body">
                            <h3><?php echo htmlspecialchars($serviceName); ?></h3>
                            <p><?php echo htmlspecialchars(substr(strip_tags($serviceDesc), 0, 120)); ?>...</p>
                            <?php if ($servicePrice > 0): ?>
                                <div class="service-price"><?php echo formatCurrency($servicePrice); ?></div>
                            <?php endif; ?>
                            <a href="<?php echo $baseUrl; ?>/cms/product/<?php echo urlencode($serviceId); ?>" class="btn-service">View Details</a>
                        </div>
                    </div>
                <?php 
                        endforeach;
                    endif;
                endif;
                ?>
            </div>
        </div>
    </section>

    <!-- Blog/News Section -->
    <?php if (!empty($recentPosts) && is_array($recentPosts) && count($recentPosts) > 0): ?>
    <section class="section" style="background: #f8f9fa;">
        <div class="container">
            <div class="section-title">
                <h2><span class="highlight">Latest</span> News</h2>
                <div class="divider"></div>
            </div>
            <div class="blog-grid">
                <?php foreach (array_slice($recentPosts, 0, 3) as $post): ?>
                    <?php if (!is_array($post)) continue; ?>
                    <div class="service-card">
                        <div class="service-icon-wrapper" style="background: linear-gradient(135deg, var(--primary), #2563eb);">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="service-card-body">
                            <h3>
                                <a href="<?php echo $baseUrl; ?>/cms/post/<?php echo urlencode($post['slug'] ?? ''); ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($post['title'] ?? 'Post Title'); ?>
                                </a>
                            </h3>
                            <p>
                                <?php echo htmlspecialchars(substr(strip_tags($post['content'] ?? ''), 0, 120)); ?>...
                            </p>
                            <a href="<?php echo $baseUrl; ?>/cms/post/<?php echo urlencode($post['slug'] ?? ''); ?>" class="btn-service">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section" style="padding-top: 40px; padding-bottom: 80px; background: transparent;">
        <div class="container">
            <div class="cta-band">
                <div>
                    <h3>Ready to secure reliable water for your project?</h3>
                    <p>Kari Boreholes &amp; Civil Engineering Works delivers dependable groundwater systems for estates, industries, farms, and communities across Ghana.</p>
                </div>
                <div class="cta-actions">
                    <?php if ($contactPhone && $callLink): ?>
                    <a href="<?php echo htmlspecialchars($callLink); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.09 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.66 12.66 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.66 12.66 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"></path></svg>
                        Call now
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($contactPageLink); ?>" class="secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2Z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        Book a site visit
                    </a>
                    <?php if ($whatsLink): ?>
                    <a href="<?php echo htmlspecialchars($whatsLink); ?>" target="_blank" rel="noopener" class="secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 0-8.94 14.56L2 22l5.58-1.47A10 10 0 1 0 12 2Z"></path><path d="M16.24 16.24l-2.29-.65a1 1 0 0 0-.95.26l-.36.37a.52.52 0 0 1-.66.08 8.58 8.58 0 0 1-3.73-3.73.52.52 0 0 1 .08-.66l.37-.36a1 1 0 0 0 .26-.95L8.76 7.76a1 1 0 0 0-1-.58 2.6 2.6 0 0 0-1.71.91 2.68 2.68 0 0 0-.69 1.86 8.36 8.36 0 0 0 2.45 5.16 8.43 8.43 0 0 0 5.16 2.45 2.68 2.68 0 0 0 1.86-.69 2.6 2.6 0 0 0 .91-1.71 1 1 0 0 0-.5-1.03Z"></path></svg>
                        WhatsApp team
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php if (file_exists($footerPath)): ?>
        <?php include $footerPath; ?>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
