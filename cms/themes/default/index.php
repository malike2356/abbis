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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
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
        .hero { background: linear-gradient(135deg, var(--primary), #0284c7); color: white; padding: 4rem 2rem; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 1rem; }
        .hero p { font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .section { margin: 4rem 0; }
        .section-title { font-size: 2rem; margin-bottom: 2rem; text-align: center; color: #1e293b; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        .card { background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
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
    <?php include __DIR__ . '/../../public/header.php'; ?>

    <?php
    // Get hero banner settings from CMS settings (passed via $cmsSettings or fetch here)
    if (!isset($cmsSettings)) {
        // Get PDO connection if not available
        if (!isset($pdo)) {
            $rootPath = dirname(dirname(dirname(__DIR__)));
            require_once $rootPath . '/config/app.php';
            require_once $rootPath . '/includes/functions.php';
            $pdo = getDBConnection();
        }
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
        $cmsSettings = [];
        while ($row = $settingsStmt->fetch()) {
            $cmsSettings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    $heroEnabled = $cmsSettings['hero_enabled'] ?? '1';
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
    
    if ($heroEnabled): ?>
    <section class="hero-banner" style="position: relative; width: 100%; min-height: 500px; display: flex; align-items: center; justify-content: center; text-align: center; color: white; padding: 6rem 2rem; background: <?php echo $heroImageUrl ? 'url(' . htmlspecialchars($heroImageUrl) . ')' : 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
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
    <section style="background: white; padding: 3rem 2rem; border-bottom: 1px solid #e2e8f0;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
            <div>
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">✅</div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">100% Guaranteed</h3>
                <p style="color: #64748b; font-size: 0.9rem;">Quality workmanship</p>
            </div>
            <div>
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🏆</div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Expert Team</h3>
                <p style="color: #64748b; font-size: 0.9rem;">Years of experience</p>
            </div>
            <div>
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">⚡</div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Fast Service</h3>
                <p style="color: #64748b; font-size: 0.9rem;">Quick turnaround</p>
            </div>
            <div>
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💰</div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Fair Pricing</h3>
                <p style="color: #64748b; font-size: 0.9rem;">Competitive rates</p>
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

        <?php if (!empty($recentPosts)): ?>
            <section class="section">
                <h2 class="section-title">Latest News</h2>
                <div class="grid">
                    <?php foreach ($recentPosts as $post): ?>
                        <div class="card">
                            <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                            <p><?php echo htmlspecialchars($post['excerpt'] ?? substr(strip_tags($post['content']), 0, 150)); ?>...</p>
                            <a href="<?php echo $baseUrl ?? ''; ?>/cms/post/<?php echo urlencode($post['slug']); ?>" class="btn btn-primary" style="margin-top:1rem; display:inline-block;">Read More</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../public/footer.php'; ?>
</body>
</html>

