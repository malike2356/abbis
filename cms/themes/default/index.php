<?php
/**
 * Default CMS Theme - Homepage Template
 */
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
$secondaryColor = $themeConfig['secondary_color'] ?? '#64748b';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?> - <?php echo htmlspecialchars($siteTagline); ?></title>
    <style>
        :root {
            --primary: <?php echo $primaryColor; ?>;
            --secondary: <?php echo $secondaryColor; ?>;
        }
        * { box-sizing: border-box; }
        html { overflow-x: hidden; width: 100%; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0 !important; overflow-x: hidden; width: 100%; }
        .portfolio-fullwidth-section { 
            width: 100%;
            margin-left: 0;
            margin-right: 0;
            position: relative;
        }
        .header { background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); text-decoration: none; }
        .nav { display: flex; gap: 2rem; list-style: none; }
        .nav a { color: #333; text-decoration: none; font-weight: 500; }
        .nav a:hover { color: var(--primary); }
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .btn { padding: 0.5rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #0284c7; }
        .btn-outline { border: 2px solid var(--primary); color: var(--primary); background: transparent; }
        .cms-site-main {
            display: block;
            padding-top: 0;
        }
.hero {
            background: linear-gradient(135deg, var(--primary), #0284c7);
            color: white;
            padding: calc(4rem + var(--cms-body-offset, 0px)) 2rem 5rem;
            text-align: center;
            margin-top: calc(var(--cms-body-offset, 0px) * -1);
        }
        .hero h1 { font-size: 3.4rem; margin-bottom: 1.2rem; }
        .hero p { font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .section { margin: 4rem 0; }
        .section-title { font-size: 2rem; margin-bottom: 2rem; text-align: center; color: #1e293b; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        .card { background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card:hover { transform: translateY(-8px); box-shadow: 0 12px 24px rgba(0,0,0,0.15); }
        .card-icon { font-size: 3rem; margin-bottom: 1rem; }
        .card h3 { color: var(--primary); margin-bottom: 1rem; }
        .footer { background: #1e293b; color: white; padding: 3rem 2rem; text-align: center; margin-top: 4rem; }
        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .nav { display: none; }
            .header-actions { flex-direction: column; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <?php
    if (!isset($rootPath)) {
        $rootPath = dirname(dirname(dirname(__DIR__)));
    }
    include __DIR__ . '/../../public/header.php';
    ?>

    <main class="cms-site-main">
    <?php
    // Get hero banner settings from CMS settings (passed via $cmsSettings or fetch here)
    if (!isset($cmsSettings)) {
        // Get PDO connection if not available
        if (!isset($pdo)) {
            if (!isset($rootPath)) {
                $rootPath = dirname(dirname(dirname(__DIR__)));
            }
            require_once $rootPath . '/config/app.php';
            require_once $rootPath . '/includes/functions.php';
            require_once $rootPath . '/cms/includes/image-resizer.php';
            $pdo = getDBConnection();
        }
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
        $cmsSettings = [];
        while ($row = $settingsStmt->fetch()) {
            $cmsSettings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Initialize ImageResizer if not already available
    if (!isset($imageResizer) && isset($pdo)) {
        if (!isset($rootPath)) {
            $rootPath = dirname(dirname(dirname(__DIR__)));
        }
        if (!class_exists('ImageResizer')) {
            require_once $rootPath . '/cms/includes/image-resizer.php';
        }
        $imageResizer = new ImageResizer($rootPath, $pdo);
    }
    
    // Load hero banner helper
    require_once dirname(dirname(dirname(__DIR__))) . '/cms/includes/hero-banner-helper.php';
    
    // Check if hero should be displayed
    $currentPageType = getCurrentPageType();
    $shouldShowHero = shouldDisplayHeroBanner($cmsSettings, $currentPageType);
    
    $heroImage = $cmsSettings['hero_banner_image'] ?? '';
    $heroTitle = $cmsSettings['hero_title'] ?? $siteTitle;
    $heroSubtitle = $cmsSettings['hero_subtitle'] ?? $siteTagline ?: 'Drilling & Construction, Mechanization and more!';
    $heroButton1Text = $cmsSettings['hero_button1_text'] ?? 'CALL US NOW';
    $heroButton1Link = $cmsSettings['hero_button1_link'] ?? 'tel:0248518513';
    $heroButton2Text = $cmsSettings['hero_button2_text'] ?? 'WHATSAPP US';
    $heroButton2Link = $cmsSettings['hero_button2_link'] ?? '';
    $heroOverlay = $cmsSettings['hero_overlay_opacity'] ?? '0.4';
    
    // Build hero image URL
    $heroImageUrl = '';
    if ($heroImage) {
        $heroImageUrl = ($baseUrl ?? '') . '/' . $heroImage;
    }
    
    if ($shouldShowHero): ?>
    <section class="hero-banner" style="position: relative; width: 100%; min-height: 500px; display: flex; align-items: center; justify-content: center; text-align: center; color: white; padding: calc(4rem + var(--cms-body-offset, 0px)) 2rem 5rem; margin-top: calc(var(--cms-body-offset, 0px) * -1); background: <?php echo $heroImageUrl ? 'url(' . htmlspecialchars($heroImageUrl) . ')' : 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
        <!-- Dark overlay for text readability -->
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, <?php echo htmlspecialchars($heroOverlay); ?>); z-index: 1;"></div>
        
        <div class="container" style="position: relative; z-index: 2; max-width: 1200px; margin: 0 auto;">
            <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1.5rem; line-height: 1.2; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($heroTitle); ?></h1>
            <?php if ($heroSubtitle): ?>
                <p style="font-size: 1.5rem; margin-bottom: 2.5rem; opacity: 0.95; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($heroSubtitle); ?></p>
            <?php endif; ?>
            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                <?php if ($heroButton1Text && $heroButton1Link): ?>
                    <a href="<?php echo htmlspecialchars($heroButton1Link); ?>" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 600; background: rgba(255, 255, 255, 0.95); color: #1e293b; border: none; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.2s, background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,1)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'">
                        <?php echo htmlspecialchars($heroButton1Text); ?>
                    </a>
                <?php endif; ?>
                <?php if ($heroButton2Text && $heroButton2Link): ?>
                    <a href="<?php echo htmlspecialchars($heroButton2Link); ?>" class="btn btn-outline" style="padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 600; background: #25D366; color: white; border: 2px solid #25D366; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.2s, background 0.2s;" onmouseover="this.style.background='#20ba5a'; this.style.borderColor='#20ba5a'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#25D366'; this.style.borderColor='#25D366'; this.style.transform='translateY(0)'">
                        <?php echo htmlspecialchars($heroButton2Text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Trust Badges / Why Choose Us -->
    <section style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); padding: 4rem 2rem; border-bottom: 1px solid #e2e8f0;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 3rem;">
                <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; color: #1e293b;">Why Choose Us</h2>
                <p style="font-size: 1.1rem; color: #64748b; max-width: 600px; margin: 0 auto;">Trusted by clients across Ghana for quality, reliability, and excellence</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div style="background: white; padding: 2rem; border-radius: 16px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.07); transition: all 0.3s ease; border-top: 4px solid #10b981;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem; display: inline-block; transform: scale(1); transition: transform 0.3s;">✅</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.75rem; color: #1e293b;">100% Guaranteed</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6;">Quality workmanship and reliable service you can trust</p>
                </div>
                <div style="background: white; padding: 2rem; border-radius: 16px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.07); transition: all 0.3s ease; border-top: 4px solid #f59e0b;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem; display: inline-block; transform: scale(1); transition: transform 0.3s;">🏆</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.75rem; color: #1e293b;">Expert Team</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6;">Years of experience and professional expertise</p>
                </div>
                <div style="background: white; padding: 2rem; border-radius: 16px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.07); transition: all 0.3s ease; border-top: 4px solid #3b82f6;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem; display: inline-block; transform: scale(1); transition: transform 0.3s;">⚡</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.75rem; color: #1e293b;">Fast Service</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6;">Quick turnaround without compromising quality</p>
                </div>
                <div style="background: white; padding: 2rem; border-radius: 16px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.07); transition: all 0.3s ease; border-top: 4px solid #8b5cf6;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem; display: inline-block; transform: scale(1); transition: transform 0.3s;">💰</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.75rem; color: #1e293b;">Fair Pricing</h3>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6;">Competitive rates with transparent pricing</p>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <?php if ($homepage && !empty($homepage['content'])): ?>
            <section class="section">
                <?php echo $homepage['content']; ?>
            </section>
        <?php else: ?>
            <!-- Default Services Section -->
            <section class="section" style="padding: 4rem 0;">
                <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 2rem;">
                    <h2 class="section-title" style="text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: #1e293b;">Our Comprehensive Services</h2>
                    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                        <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                            <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🕳️</div>
                            <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Borehole Drilling</h3>
                            <p style="color: #64748b; line-height: 1.6;">Professional borehole drilling and construction services across Ghana. We use state-of-the-art equipment and techniques.</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
                        </div>
                        <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                            <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                            <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Geophysical Survey</h3>
                            <p style="color: #64748b; line-height: 1.6;">Expert site selection and water source identification using advanced geophysical methods to ensure optimal well placement.</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
                        </div>
                        <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                            <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">⚙️</div>
                            <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Pump Installation</h3>
                            <p style="color: #64748b; line-height: 1.6;">Complete pump installation and system automation with smart controls for efficient water delivery.</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Learn More →</a>
                        </div>
                        <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                            <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">💧</div>
                            <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Water Treatment</h3>
                            <p style="color: #64748b; line-height: 1.6;">Filtration, reverse osmosis, UV purification, and complete water treatment solutions for safe, clean water.</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/shop" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Shop Now →</a>
                        </div>
                        <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                            <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                            <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Maintenance & Repair</h3>
                            <p style="color: #64748b; line-height: 1.6;">Regular maintenance, rehabilitation, and repair services to keep your water systems running efficiently.</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/quote" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Request Service →</a>
                        </div>
                        <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #0ea5e9;">
                            <div class="card-icon" style="font-size: 3rem; margin-bottom: 1rem;">🏪</div>
                            <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">Equipment Sales</h3>
                            <p style="color: #64748b; line-height: 1.6;">Quality pumps, tanks, pipes, and complete water systems from trusted manufacturers. Shop our online store.</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/shop" style="color: #0ea5e9; text-decoration: none; font-weight: 600; margin-top: 1rem; display: inline-block;">Shop Now →</a>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Portfolio Slider Section - Full Width (Edge to Edge) -->
    <?php
    try {
        $portfolioStmt = $pdo->query("SELECT p.*, 
            (SELECT image_path FROM cms_portfolio_images WHERE portfolio_id = p.id ORDER BY display_order, id LIMIT 1) as first_image
            FROM cms_portfolio p 
            WHERE p.status='published' 
            ORDER BY p.display_order, p.created_at DESC");
        $portfolioItems = $portfolioStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $portfolioItems = [];
    }
    
    // Ensure ImageResizer is available
    if (!isset($imageResizer) && isset($pdo)) {
        $rootPath = dirname(dirname(dirname(__DIR__)));
        if (!class_exists('ImageResizer')) {
            require_once $rootPath . '/cms/includes/image-resizer.php';
        }
        $imageResizer = new ImageResizer($rootPath, $pdo);
    }
    ?>
    <?php if (!empty($portfolioItems)): ?>
        <section class="portfolio-fullwidth-section" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 6rem 0; margin: 4rem 0; overflow: hidden; position: relative; box-sizing: border-box;">
            <!-- Background Pattern -->
            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.05; background-image: url('data:image/svg+xml,<svg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"><g fill=\"none\" fill-rule=\"evenodd\"><g fill=\"%23ffffff\" fill-opacity=\"1\"><circle cx=\"30\" cy=\"30\" r=\"2\"/></g></svg>');"></div>
            
            <div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem; position: relative; z-index: 1; width: 100%; box-sizing: border-box;">
                    <div style="text-align: center; margin-bottom: 4rem;">
                        <h2 class="section-title" style="font-size: 3rem; margin-bottom: 1rem; color: white; font-weight: 700; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">Our Portfolio</h2>
                        <p style="font-size: 1.2rem; color: rgba(255,255,255,0.9); max-width: 700px; margin: 0 auto; text-shadow: 1px 1px 2px rgba(0,0,0,0.2);">Showcasing our expertise and successful borehole drilling projects across Ghana</p>
                    </div>
                    
                    <!-- Portfolio Slider -->
                    <div class="portfolio-slider-container" style="position: relative; margin-bottom: 3rem; width: 100%; max-width: 100%; overflow: hidden; box-sizing: border-box;">
                        <div class="portfolio-slider-wrapper" style="overflow: hidden; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); width: 100%; max-width: 100%;">
                            <div class="portfolio-slider-track" id="portfolioSliderTrack" style="display: flex; transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1); width: 100%; max-width: 100%;">
                                <?php foreach ($portfolioItems as $index => $item): 
                                    // Determine which image to show: featured_image first, then first_image from gallery
                                    $displayImage = null;
                                    $imageUrl = null;
                                    $rootPath = dirname(dirname(dirname(__DIR__)));
                                    
                                    // Priority: featured_image > first_image from gallery
                                    if (!empty($item['featured_image'])) {
                                        $testPath = $rootPath . '/' . ltrim($item['featured_image'], '/');
                                        if (file_exists($testPath)) {
                                            $displayImage = ltrim($item['featured_image'], '/');
                                        }
                                    }
                                    
                                    // Fallback to first gallery image
                                    if (!$displayImage && !empty($item['first_image'])) {
                                        $testPath = $rootPath . '/' . ltrim($item['first_image'], '/');
                                        if (file_exists($testPath)) {
                                            $displayImage = ltrim($item['first_image'], '/');
                                        }
                                    }
                                    
                                    // Generate image URL with resizer support
                                    if ($displayImage) {
                                        try {
                                            if (isset($imageResizer) && $imageResizer instanceof ImageResizer) {
                                                // Try to get large size first
                                                $imageUrl = $imageResizer->getImageUrl($displayImage, 'large', $baseUrl ?? '');
                                                // Verify the file exists
                                                $resizedPath = $rootPath . '/' . str_replace(($baseUrl ?? '') . '/', '', $imageUrl);
                                                if (!file_exists($resizedPath)) {
                                                    // Fallback to original
                                                    $imageUrl = ($baseUrl ?? '') . '/' . $displayImage;
                                                }
                                            } else {
                                                $imageUrl = ($baseUrl ?? '') . '/' . $displayImage;
                                            }
                                        } catch (Exception $e) {
                                            // Fallback to original if resizer fails
                                            $imageUrl = ($baseUrl ?? '') . '/' . $displayImage;
                                        }
                                    }
                                ?>
                                    <div class="portfolio-slide" data-index="<?php echo $index; ?>" style="min-width: 100%; width: 100%; max-width: 100%; display: flex; align-items: center; background: white; position: relative; flex-shrink: 0;">
                                        <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 0; min-height: 500px; width: 100%; max-width: 100%;">
                                            <!-- Image Side -->
                                            <div style="position: relative; overflow: hidden; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);">
                                                <?php if ($imageUrl): ?>
                                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                                         style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 120px; color: #cbd5e1;">🖼️</div>
                                                <?php else: ?>
                                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 120px; color: #cbd5e1;">🖼️</div>
                                                <?php endif; ?>
                                                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(30, 41, 59, 0.1) 0%, rgba(15, 23, 42, 0.2) 100%);"></div>
                                            </div>
                                            
                                            <!-- Content Side -->
                                            <div style="padding: 4rem; display: flex; flex-direction: column; justify-content: center; background: white;">
                                                <div style="margin-bottom: 1.5rem;">
                                                    <span style="display: inline-block; padding: 0.5rem 1rem; background: var(--primary); color: white; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem;">Project #<?php echo $index + 1; ?></span>
                                                    <h3 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; color: #1e293b; line-height: 1.2;"><?php echo htmlspecialchars($item['title']); ?></h3>
                                                </div>
                                                
                                                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem; font-size: 1rem; color: #64748b;">
                                                    <?php if ($item['location']): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <span style="font-size: 1.2rem;">📍</span>
                                                            <span><?php echo htmlspecialchars($item['location']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($item['client_name']): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <span style="font-size: 1.2rem;">👤</span>
                                                            <span><?php echo htmlspecialchars($item['client_name']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($item['project_date']): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <span style="font-size: 1.2rem;">📅</span>
                                                            <span><?php echo date('F Y', strtotime($item['project_date'])); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($item['description'])): ?>
                                                    <p style="color: #475569; line-height: 1.8; font-size: 1.1rem; margin-bottom: 2rem;"><?php echo htmlspecialchars(substr(strip_tags($item['description']), 0, 200)); ?><?php echo strlen(strip_tags($item['description'])) > 200 ? '...' : ''; ?></p>
                                                <?php endif; ?>
                                                
                                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                                    <a href="<?php echo $baseUrl ?? ''; ?>/cms/portfolio?slug=<?php echo urlencode($item['slug']); ?>" 
                                                       class="btn btn-primary" 
                                                       style="padding: 1rem 2rem; font-size: 1rem; font-weight: 600; text-decoration: none; display: inline-block; border-radius: 8px; background: var(--primary); color: white; transition: all 0.3s;">
                                                        View Full Project →
                                                    </a>
                                                    <a href="<?php echo $baseUrl ?? ''; ?>/cms/portfolio" 
                                                       style="padding: 1rem 2rem; font-size: 1rem; font-weight: 600; text-decoration: none; display: inline-block; border-radius: 8px; border: 2px solid var(--primary); color: var(--primary); background: transparent; transition: all 0.3s;">
                                                        View All Projects
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Slider Navigation -->
                        <?php if (count($portfolioItems) > 1): ?>
                            <button class="slider-nav-btn prev-btn" onclick="changePortfolioSlide(-1)" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 1.5rem; color: #1e293b; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: all 0.3s; z-index: 10; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='white'; this.style.transform='translateY(-50%) scale(1.1)'" onmouseout="this.style.background='rgba(255,255,255,0.9)'; this.style.transform='translateY(-50%) scale(1)'">‹</button>
                            <button class="slider-nav-btn next-btn" onclick="changePortfolioSlide(1)" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 1.5rem; color: #1e293b; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: all 0.3s; z-index: 10; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='white'; this.style.transform='translateY(-50%) scale(1.1)'" onmouseout="this.style.background='rgba(255,255,255,0.9)'; this.style.transform='translateY(-50%) scale(1)'">›</button>
                            
                            <!-- Slider Dots -->
                            <div class="slider-dots-container" style="display: flex; justify-content: center; gap: 0.75rem; margin-top: 2rem;">
                                <?php foreach ($portfolioItems as $index => $item): ?>
                                    <button class="slider-dot" 
                                            onclick="goToPortfolioSlide(<?php echo $index; ?>)" 
                                            data-index="<?php echo $index; ?>"
                                            style="width: 12px; height: 12px; border-radius: 50%; border: none; background: <?php echo $index === 0 ? 'white' : 'rgba(255,255,255,0.4)'; ?>; cursor: pointer; transition: all 0.3s; padding: 0;"
                                            onmouseover="this.style.background='rgba(255,255,255,0.8)'"
                                            onmouseout="if(this.dataset.index == currentPortfolioSlide) this.style.background='white'; else this.style.background='rgba(255,255,255,0.4)'">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
            </div>
        </section>
        
        <script>
                let currentPortfolioSlide = 0;
                const portfolioSlides = <?php echo count($portfolioItems); ?>;
                const sliderTrack = document.getElementById('portfolioSliderTrack');
                const dots = document.querySelectorAll('.slider-dot');
                let autoSlideInterval = null;
                let isPaused = false;
                
                function updatePortfolioSlider() {
                    if (sliderTrack) {
                        sliderTrack.style.transform = `translateX(-${currentPortfolioSlide * 100}%)`;
                    }
                    dots.forEach((dot, index) => {
                        dot.style.background = index === currentPortfolioSlide ? 'white' : 'rgba(255,255,255,0.4)';
                        dot.style.width = index === currentPortfolioSlide ? '24px' : '12px';
                        dot.style.borderRadius = index === currentPortfolioSlide ? '6px' : '50%';
                    });
                }
                
                function changePortfolioSlide(direction) {
                    currentPortfolioSlide = (currentPortfolioSlide + direction + portfolioSlides) % portfolioSlides;
                    updatePortfolioSlider();
                }
                
                function goToPortfolioSlide(index) {
                    currentPortfolioSlide = index;
                    updatePortfolioSlider();
                }
                
                function startAutoSlide() {
                    // Clear any existing interval
                    if (autoSlideInterval) {
                        clearInterval(autoSlideInterval);
                    }
                    // Start auto-slide if there are multiple slides
                    if (portfolioSlides > 1 && !isPaused) {
                        autoSlideInterval = setInterval(() => {
                            changePortfolioSlide(1); // Always move forward (loops automatically via modulo)
                        }, 6000); // Change slide every 6 seconds
                    }
                }
                
                function pauseAutoSlide() {
                    isPaused = true;
                    if (autoSlideInterval) {
                        clearInterval(autoSlideInterval);
                        autoSlideInterval = null;
                    }
                }
                
                function resumeAutoSlide() {
                    isPaused = false;
                    startAutoSlide();
                }
                
                // Initialize auto-play slider (will loop continuously)
                if (portfolioSlides > 1) {
                    startAutoSlide();
                    
                    // Pause on hover, resume on mouse leave
                    const sliderContainer = document.querySelector('.portfolio-slider-container');
                    if (sliderContainer) {
                        sliderContainer.addEventListener('mouseenter', pauseAutoSlide);
                        sliderContainer.addEventListener('mouseleave', resumeAutoSlide);
                    }
                    
                    // Pause when user interacts with controls, then resume
                    const navButtons = document.querySelectorAll('.slider-nav-btn');
                    navButtons.forEach(btn => {
                        btn.addEventListener('click', () => {
                            pauseAutoSlide();
                            setTimeout(resumeAutoSlide, 10000); // Resume after 10 seconds
                        });
                    });
                    
                    const dotButtons = document.querySelectorAll('.slider-dot');
                    dotButtons.forEach(btn => {
                        btn.addEventListener('click', () => {
                            pauseAutoSlide();
                            setTimeout(resumeAutoSlide, 10000); // Resume after 10 seconds
                        });
                    });
                }
                
                // Keyboard navigation
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') changePortfolioSlide(-1);
                    if (e.key === 'ArrowRight') changePortfolioSlide(1);
                });
                
                // Touch/swipe support for mobile
                let touchStartX = 0;
                let touchEndX = 0;
                
                const sliderContainer = document.querySelector('.portfolio-slider-container');
                if (sliderContainer) {
                    sliderContainer.addEventListener('touchstart', (e) => {
                        touchStartX = e.changedTouches[0].screenX;
                    });
                    
                    sliderContainer.addEventListener('touchend', (e) => {
                        touchEndX = e.changedTouches[0].screenX;
                        handleSwipe();
                    });
                    
                    function handleSwipe() {
                        if (touchEndX < touchStartX - 50) changePortfolioSlide(1);
                        if (touchEndX > touchStartX + 50) changePortfolioSlide(-1);
                    }
                }
            </script>
            
            <style>
                .portfolio-fullwidth-section {
                    width: 100%;
                    max-width: 100%;
                    box-sizing: border-box;
                }
                .portfolio-slider-container {
                    width: 100% !important;
                    max-width: 100% !important;
                    overflow: hidden !important;
                }
                .portfolio-slider-wrapper {
                    width: 100% !important;
                    max-width: 100% !important;
                    overflow: hidden !important;
                }
                .portfolio-slider-track {
                    width: 100% !important;
                    max-width: 100% !important;
                }
                .portfolio-slide {
                    width: 100% !important;
                    min-width: 100% !important;
                    max-width: 100% !important;
                    flex: 0 0 100% !important;
                    box-sizing: border-box !important;
                }
                @media (max-width: 1024px) {
                    .portfolio-slide > div {
                        grid-template-columns: 1fr !important;
                    }
                    .portfolio-slide > div > div:first-child {
                        min-height: 300px !important;
                    }
                    .slider-nav-btn {
                        display: none !important;
                    }
                }
                @media (max-width: 768px) {
                    .portfolio-fullwidth-section {
                        padding: 4rem 0 !important;
                    }
                    .portfolio-slide > div > div:last-child {
                        padding: 2rem !important;
                    }
                    .portfolio-slide h3 {
                        font-size: 1.8rem !important;
                    }
                }
            </style>
    <?php endif; ?>

    <div class="container">
        <?php if (!empty($recentPosts)): ?>
            <section class="section" style="padding: 4rem 0;">
                <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 2rem;">
                    <h2 class="section-title" style="text-align: center; font-size: 2.5rem; margin-bottom: 3rem; color: #1e293b; font-weight: 700;">Latest News & Updates</h2>
                    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                        <?php foreach ($recentPosts as $post): ?>
                            <div class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; border-top: 4px solid var(--primary);">
                                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b; font-weight: 700;"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <p style="color: #64748b; line-height: 1.6; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($post['excerpt'] ?? substr(strip_tags($post['content']), 0, 150)); ?>...</p>
                                <a href="<?php echo $baseUrl ?? ''; ?>/cms/post/<?php echo urlencode($post['slug']); ?>" class="btn btn-primary" style="margin-top:1rem; display:inline-block; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px;">Read More →</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    </main>

    <?php
    $footerCandidates = [
        __DIR__ . '/../../public/footer.php',
        $rootPath . '/cms/public/footer.php',
        $rootPath . '/includes/footer.php'
    ];
    $footerFound = false;
    foreach ($footerCandidates as $candidate) {
        if (file_exists($candidate)) {
            include $candidate;
            $footerFound = true;
            break;
        }
    }
    if (!$footerFound) {
        trigger_error('Unable to locate footer include for default theme.', E_USER_WARNING);
    }
    ?>
</body>
</html>

