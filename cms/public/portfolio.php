<?php
/**
 * CMS Public - Portfolio/Gallery Page
 * Displays portfolio items with photo slides and descriptions
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/../includes/image-resizer.php';

$pdo = getDBConnection();
$imageResizer = new ImageResizer($rootPath, $pdo);

// Ensure portfolio tables exist
try {
    $pdo->query("SELECT 1 FROM cms_portfolio LIMIT 1");
} catch (PDOException $e) {
    // Run migration
    $migrationPath = $rootPath . '/database/portfolio_migration.sql';
    if (file_exists($migrationPath)) {
        $sql = file_get_contents($migrationPath);
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $ignored) {}
            }
        }
    }
}

// Get base URL
$baseUrl = app_base_path();

// Get single portfolio item or list
$slug = $_GET['slug'] ?? null;
$portfolio = null;
$portfolioImages = [];

if ($slug) {
    // Decode URL-encoded slug (handles spaces and special characters)
    $slug = urldecode($slug);
    $slug = trim($slug);
    
    // Single portfolio item view
    $stmt = $pdo->prepare("SELECT * FROM cms_portfolio WHERE slug=? AND status='published'");
    $stmt->execute([$slug]);
    $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($portfolio) {
        $imgStmt = $pdo->prepare("SELECT * FROM cms_portfolio_images WHERE portfolio_id=? ORDER BY display_order, id");
        $imgStmt->execute([$portfolio['id']]);
        $portfolioImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Portfolio list view - get first image as fallback if no featured_image
    $stmt = $pdo->query("SELECT p.*, 
        (SELECT image_path FROM cms_portfolio_images WHERE portfolio_id = p.id ORDER BY display_order, id LIMIT 1) as first_image
        FROM cms_portfolio p 
        WHERE p.status='published' 
        ORDER BY p.display_order, p.created_at DESC");
    $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get CMS settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
$cmsSettings = [];
while ($row = $settingsStmt->fetch()) {
    $cmsSettings[$row['setting_key']] = $row['setting_value'];
}

// Get site name
require_once __DIR__ . '/get-site-name.php';
$siteTitle = getCMSSiteName('Our Company');
$siteTagline = $cmsSettings['site_tagline'] ?? '';

// Get menu items
$menuStmt = $pdo->query("SELECT * FROM cms_menu_items WHERE menu_type='primary' ORDER BY menu_order");
$menuItems = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

// Get active theme
$themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
$theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
$themeConfig = json_decode($theme['config'] ?? '{}', true);
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $portfolio ? htmlspecialchars($portfolio['title']) . ' - ' : ''; ?>Portfolio - <?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; color: <?php echo $primaryColor; ?>; text-decoration: none; }
        .nav { display: flex; gap: 2rem; list-style: none; }
        .nav a { color: #333; text-decoration: none; font-weight: 500; }
        .nav a:hover { color: <?php echo $primaryColor; ?>; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .page-header { text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, <?php echo $primaryColor; ?>, #0284c7); color: white; margin-top: 20px; /* Additional spacing - body already has padding-top: 80px */ }
        .page-header h1 { font-size: 3rem; margin-bottom: 1rem; }
        .page-header p { font-size: 1.25rem; opacity: 0.9; }
        
        /* Portfolio Grid */
        .portfolio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem; margin-top: 3rem; }
        .portfolio-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; }
        .portfolio-card:hover { transform: translateY(-8px); box-shadow: 0 12px 24px rgba(0,0,0,0.15); }
        .portfolio-card-image { width: 100%; height: 250px; object-fit: cover; }
        .portfolio-card-body { padding: 1.5rem; }
        .portfolio-card-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #1e293b; }
        .portfolio-card-meta { color: #64748b; font-size: 0.9rem; margin-bottom: 1rem; }
        .portfolio-card-description { color: #475569; line-height: 1.6; margin-bottom: 1rem; }
        .portfolio-card-link { color: <?php echo $primaryColor; ?>; text-decoration: none; font-weight: 600; }
        
        /* Single Portfolio View */
        .portfolio-single { max-width: 1400px; margin: 0 auto; }
        .portfolio-single-header { text-align: center; margin-bottom: 4rem; padding: 3rem 2rem; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 16px; }
        .portfolio-single-title { font-size: 3.5rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b; line-height: 1.2; }
        .portfolio-single-meta { display: flex; justify-content: center; gap: 3rem; flex-wrap: wrap; color: #64748b; margin-bottom: 2rem; font-size: 1.1rem; }
        .portfolio-single-meta span { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .portfolio-single-description { font-size: 1.2rem; line-height: 2; color: #475569; margin-bottom: 4rem; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        /* Image Gallery Section */
        .portfolio-images-section { margin-bottom: 4rem; }
        .section-title { font-size: 2.5rem; font-weight: 700; margin-bottom: 2rem; color: #1e293b; text-align: center; }
        
        /* Main Image Slider */
        .image-slider { position: relative; margin-bottom: 3rem; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 32px rgba(0,0,0,0.15); background: #000; }
        .slider-container { 
            position: relative; 
            width: 100%; 
            height: 700px; 
            overflow: hidden; 
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slider-image { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            display: none;
            cursor: pointer;
            max-width: 100%;
            max-height: 100%;
            margin: auto;
        }
        .slider-image.active { 
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        @keyframes fadeIn { 
            from { opacity: 0; } 
            to { opacity: 1; } 
        }
        .slider-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); color: #1e293b; border: none; padding: 1.5rem 1rem; cursor: pointer; font-size: 2rem; z-index: 10; transition: all 0.3s; border-radius: 8px; font-weight: bold; }
        .slider-nav:hover { background: white; transform: translateY(-50%) scale(1.1); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .slider-nav.prev { left: 20px; }
        .slider-nav.next { right: 20px; }
        .slider-dots { display: flex; justify-content: center; gap: 0.75rem; padding: 1.5rem; background: #f8fafc; }
        .slider-dot { width: 14px; height: 14px; border-radius: 50%; background: #cbd5e1; border: none; cursor: pointer; transition: all 0.3s; }
        .slider-dot.active { background: <?php echo $primaryColor; ?>; transform: scale(1.3); width: 28px; border-radius: 7px; }
        .slider-caption { padding: 1.5rem; background: white; border-top: 2px solid #e2e8f0; }
        .slider-caption h4 { font-size: 1.3rem; font-weight: 600; margin-bottom: 0.5rem; color: #1e293b; }
        .slider-caption p { color: #64748b; font-size: 1rem; line-height: 1.6; }
        
        /* Image Gallery Grid */
        .image-gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
        .gallery-image-item { position: relative; border-radius: 12px; overflow: hidden; cursor: pointer; border: 3px solid transparent; transition: all 0.3s; box-shadow: 0 4px 8px rgba(0,0,0,0.1); background: #f8fafc; }
        .gallery-image-item:hover { border-color: <?php echo $primaryColor; ?>; transform: translateY(-8px) scale(1.02); box-shadow: 0 12px 24px rgba(0,0,0,0.2); z-index: 5; }
        .gallery-image-item.active { border-color: <?php echo $primaryColor; ?>; border-width: 4px; }
        .gallery-image-item img { width: 100%; height: 280px; object-fit: cover; display: block; }
        .gallery-image-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 1rem; color: white; opacity: 0; transition: opacity 0.3s; }
        .gallery-image-item:hover .gallery-image-overlay { opacity: 1; }
        .gallery-image-overlay h5 { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; }
        .gallery-image-overlay p { font-size: 0.85rem; opacity: 0.9; }
        
        /* Thumbnail Grid (Alternative view) */
        .thumbnail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; margin-top: 2rem; }
        .thumbnail-item { position: relative; border-radius: 10px; overflow: hidden; cursor: pointer; border: 4px solid transparent; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .thumbnail-item:hover { border-color: <?php echo $primaryColor; ?>; transform: scale(1.08); box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
        .thumbnail-item.active { border-color: <?php echo $primaryColor; ?>; border-width: 4px; }
        .thumbnail-item img { width: 100%; height: 150px; object-fit: cover; display: block; }
        
        /* Portfolio Details Card */
        .portfolio-details-card { background: white; border-radius: 16px; padding: 2.5rem; box-shadow: 0 8px 16px rgba(0,0,0,0.1); margin-bottom: 3rem; }
        .portfolio-details-card h3 { font-size: 2rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b; border-bottom: 3px solid <?php echo $primaryColor; ?>; padding-bottom: 1rem; }
        .detail-row { display: grid; grid-template-columns: 200px 1fr; gap: 1.5rem; padding: 1rem 0; border-bottom: 1px solid #e2e8f0; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #64748b; font-size: 1rem; }
        .detail-value { color: #1e293b; font-size: 1.1rem; }
        
        /* Lightbox Modal */
        .lightbox-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); z-index: 10000; padding: 2rem; }
        .lightbox-modal.active { display: flex; align-items: center; justify-content: center; }
        .lightbox-content { position: relative; max-width: 95vw; max-height: 95vh; }
        .lightbox-image { max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 8px; }
        .lightbox-close { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.9); border: none; color: #1e293b; font-size: 2.5rem; width: 60px; height: 60px; border-radius: 50%; cursor: pointer; font-weight: bold; z-index: 10001; transition: all 0.3s; line-height: 1; display: flex; align-items: center; justify-content: center; }
        .lightbox-close:hover { background: white; transform: scale(1.1); }
        .lightbox-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; color: #1e293b; padding: 1.5rem 1.2rem; cursor: pointer; font-size: 2.5rem; border-radius: 8px; font-weight: bold; z-index: 10001; transition: all 0.3s; }
        .lightbox-nav:hover { background: white; transform: translateY(-50%) scale(1.1); }
        .lightbox-nav.prev { left: 20px; }
        .lightbox-nav.next { right: 20px; }
        .lightbox-caption { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); text-align: center; color: white; background: rgba(0,0,0,0.7); padding: 1rem 2rem; border-radius: 8px; max-width: 80%; }
        .lightbox-caption h4 { font-size: 1.3rem; margin-bottom: 0.5rem; }
        .lightbox-caption p { font-size: 1rem; opacity: 0.9; }
        
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: <?php echo $primaryColor; ?>; text-decoration: none; font-weight: 600; margin-bottom: 2rem; }
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .portfolio-grid { grid-template-columns: 1fr; }
            .slider-container { height: 400px; }
            .portfolio-single-title { font-size: 2rem; }
            .portfolio-single-meta { gap: 1rem; font-size: 0.95rem; }
            .portfolio-single-meta span { padding: 0.5rem 1rem; }
            .image-gallery-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
            .gallery-image-item img { height: 150px; }
            .detail-row { grid-template-columns: 1fr; gap: 0.5rem; }
            .lightbox-nav.prev { left: 10px; }
            .lightbox-nav.next { right: 10px; }
            .lightbox-close { top: 10px; right: 10px; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <?php if ($portfolio): ?>
        <!-- Single Portfolio Item View -->
        <div class="container">
            <a href="<?php echo $baseUrl; ?>/cms/portfolio" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: <?php echo $primaryColor; ?>; text-decoration: none; font-weight: 600; margin-bottom: 2rem; font-size: 1.1rem;">
                ← Back to Portfolio
            </a>
            
            <div class="portfolio-single">
                <!-- Portfolio Header -->
                <div class="portfolio-single-header">
                    <h1 class="portfolio-single-title"><?php echo htmlspecialchars($portfolio['title']); ?></h1>
                    <div class="portfolio-single-meta">
                        <?php if ($portfolio['location']): ?>
                            <span>📍 <?php echo htmlspecialchars($portfolio['location']); ?></span>
                        <?php endif; ?>
                        <?php if ($portfolio['client_name']): ?>
                            <span>👤 <?php echo htmlspecialchars($portfolio['client_name']); ?></span>
                        <?php endif; ?>
                        <?php if ($portfolio['project_date']): ?>
                            <span>📅 <?php echo date('F j, Y', strtotime($portfolio['project_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Portfolio Details Card -->
                <div class="portfolio-details-card">
                    <h3>📋 Project Details</h3>
                    <div class="detail-row">
                        <div class="detail-label">Project Title:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($portfolio['title']); ?></div>
                    </div>
                    <?php if ($portfolio['location']): ?>
                    <div class="detail-row">
                        <div class="detail-label">📍 Location:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($portfolio['location']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($portfolio['client_name']): ?>
                    <div class="detail-row">
                        <div class="detail-label">👤 Client:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($portfolio['client_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($portfolio['project_date']): ?>
                    <div class="detail-row">
                        <div class="detail-label">📅 Project Date:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($portfolio['project_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($portfolioImages)): ?>
                    <div class="detail-row">
                        <div class="detail-label">📸 Images:</div>
                        <div class="detail-value"><strong><?php echo count($portfolioImages); ?></strong> image<?php echo count($portfolioImages) !== 1 ? 's' : ''; ?> available</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Project Description -->
                <?php if (!empty($portfolio['description'])): ?>
                    <div class="portfolio-single-description">
                        <h3 style="font-size: 2rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b; border-bottom: 3px solid <?php echo $primaryColor; ?>; padding-bottom: 1rem;">📝 Project Description</h3>
                        <?php echo nl2br(htmlspecialchars($portfolio['description'])); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Portfolio Images Section -->
                <?php 
                // First, filter out images that don't exist - do this at the top level
                $validGalleryImages = [];
                if (!empty($portfolioImages)) {
                    foreach ($portfolioImages as $img) {
                        $imgPath = trim($img['image_path']);
                        $imgPathForCheck = ltrim($imgPath, '/');
                        $fullImgPath = $rootPath . '/' . $imgPathForCheck;
                        if (file_exists($fullImgPath)) {
                            $validGalleryImages[] = $img;
                        }
                    }
                }
                ?>
                <?php if (!empty($validGalleryImages) || !empty($portfolio['featured_image'])): ?>
                    <div class="portfolio-images-section">
                        <h2 class="section-title">📸 Project Images</h2>
                        
                        <!-- Main Image Slider -->
                        <div class="image-slider" id="imageSlider">
                            <div class="slider-container">
                                <?php 
                                // Use valid gallery images if available
                                if (!empty($validGalleryImages)):
                                    $slideIndex = 0; // Track actual slide index (excluding skipped images)
                                    foreach ($validGalleryImages as $imgIndex => $img): 
                                        // Get image path - ensure it's clean
                                        $imagePath = trim($img['image_path']);
                                        // Remove leading slash if present for path checking
                                        $imagePathForCheck = ltrim($imagePath, '/');
                                        $fullImagePath = $rootPath . '/' . $imagePathForCheck;
                                        
                                        // Double-check file exists (should already be filtered, but be safe)
                                        if (!file_exists($fullImagePath)) {
                                            continue; // Skip if somehow doesn't exist
                                        }
                                        
                                        // Construct URL - ensure single leading slash, no double slashes
                                        $cleanPath = ltrim($imagePath, '/');
                                        $imageUrl = $baseUrl . '/' . $cleanPath;
                                        $fullImageUrl = $imageUrl;
                                        
                                        // Try to get resized version if available
                                        try {
                                            $resizedUrl = $imageResizer->getImageUrl($imagePath, 'large', $baseUrl);
                                            if ($resizedUrl) {
                                                // Remove baseUrl to get relative path
                                                $resizedRelativePath = str_replace($baseUrl . '/', '', $resizedUrl);
                                                $resizedRelativePath = ltrim($resizedRelativePath, '/');
                                                $fullResizedPath = $rootPath . '/' . $resizedRelativePath;
                                                
                                                // Only use resized if it exists and is different from original
                                                if (file_exists($fullResizedPath) && $resizedUrl !== $imageUrl) {
                                                    $imageUrl = $resizedUrl;
                                                }
                                            }
                                        } catch (Exception $e) {
                                            // Use original if resizer fails
                                            error_log('Image resizer error: ' . $e->getMessage());
                                        }
                                ?>
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                         alt="<?php echo htmlspecialchars($img['image_alt'] ?: $portfolio['title']); ?>" 
                                         class="slider-image<?php echo $slideIndex === 0 ? ' active' : ''; ?>"
                                         data-index="<?php echo $slideIndex; ?>"
                                         onclick="openLightbox(<?php echo $slideIndex; ?>)"
                                         data-src="<?php echo htmlspecialchars($fullImageUrl); ?>"
                                         data-alt="<?php echo htmlspecialchars($img['image_alt'] ?: $portfolio['title']); ?>"
                                         data-caption="<?php echo htmlspecialchars($img['image_caption'] ?? ''); ?>"
                                         data-original-index="<?php echo $imgIndex; ?>"
                                         style="<?php echo $slideIndex === 0 ? 'display: block !important; visibility: visible !important; opacity: 1 !important;' : 'display: none !important; visibility: hidden !important; opacity: 0 !important;'; ?>"
                                         onload="console.log('✅ Image loaded successfully:', this.src); this.style.display='block'; this.style.visibility='visible'; this.style.opacity='1';"
                                         onerror="console.error('❌ Image failed to load:', '<?php echo htmlspecialchars($imagePath); ?>', 'Full path:', '<?php echo htmlspecialchars($fullImagePath); ?>', 'URL:', this.src); this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex'; this.classList.remove('active');">
                                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: #000; color: white; font-size: 1.5rem; position: absolute; top: 0; left: 0;">
                                        ❌ Image not found: <?php echo htmlspecialchars($imagePath); ?>
                                        <br><small style="font-size: 0.8rem; opacity: 0.8;">Path: <?php echo htmlspecialchars($fullImagePath); ?></small>
                                    </div>
                                <?php 
                                        $slideIndex++; // Increment slide index
                                    endforeach;
                                elseif (!empty($portfolio['featured_image'])): 
                                    // No gallery images, use featured image if available
                                    $featuredPath = trim($portfolio['featured_image']);
                                    $featuredPathForCheck = ltrim($featuredPath, '/');
                                    $fullFeaturedPath = $rootPath . '/' . $featuredPathForCheck;
                                    if (file_exists($fullFeaturedPath)):
                                        $featuredUrl = $baseUrl . '/' . ltrim($featuredPath, '/');
                                        try {
                                            $resizedUrl = $imageResizer->getImageUrl($featuredPath, 'large', $baseUrl);
                                            if ($resizedUrl && $resizedUrl !== $featuredUrl) {
                                                $resizedPathCheck = str_replace($baseUrl . '/', '', $resizedUrl);
                                                $resizedPathCheck = ltrim($resizedPathCheck, '/');
                                                $fullResizedPath = $rootPath . '/' . $resizedPathCheck;
                                                if (file_exists($fullResizedPath)) {
                                                    $featuredUrl = $resizedUrl;
                                                }
                                            }
                                        } catch (Exception $e) {}
                                ?>
                                        <img src="<?php echo htmlspecialchars($featuredUrl); ?>" 
                                             alt="<?php echo htmlspecialchars($portfolio['title']); ?>" 
                                             class="slider-image active"
                                             data-index="0"
                                             onclick="openLightbox(0)"
                                             data-src="<?php echo htmlspecialchars($baseUrl . '/' . ltrim($featuredPath, '/')); ?>"
                                             data-alt="<?php echo htmlspecialchars($portfolio['title']); ?>"
                                             data-caption=""
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: #000; color: white; font-size: 1.5rem;">
                                            Image not found
                                        </div>
                                <?php 
                                    endif;
                                endif;
                                ?>
                            </div>
                            <?php 
                            // Count actual valid images (already filtered above)
                            $actualImageCount = !empty($validGalleryImages) ? count($validGalleryImages) : 0;
                            
                            // If no gallery images but featured image exists, count it
                            if ($actualImageCount === 0 && !empty($portfolio['featured_image'])) {
                                $featPathCheck = ltrim(trim($portfolio['featured_image']), '/');
                                if (file_exists($rootPath . '/' . $featPathCheck)) {
                                    $actualImageCount = 1;
                                }
                            }
                            ?>
                            <?php if ($actualImageCount > 1): ?>
                                <button class="slider-nav prev" onclick="changeSlide(-1)">‹</button>
                                <button class="slider-nav next" onclick="changeSlide(1)">›</button>
                                <div class="slider-dots" id="sliderDots">
                                    <?php 
                                    if (!empty($validGalleryImages)):
                                        foreach ($validGalleryImages as $dotIndex => $img): ?>
                                            <button class="slider-dot <?php echo $dotIndex === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $dotIndex; ?>)" data-slide-index="<?php echo $dotIndex; ?>"></button>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            <?php endif; ?>
                            <?php 
                            $firstImage = !empty($validGalleryImages) ? $validGalleryImages[0] : (!empty($portfolioImages) ? $portfolioImages[0] : null);
                            if ($firstImage && (!empty($firstImage['image_caption']) || !empty($firstImage['image_alt']))): ?>
                                <div class="slider-caption" id="sliderCaption">
                                    <h4><?php echo htmlspecialchars($firstImage['image_alt'] ?: 'Image'); ?></h4>
                                    <?php if (!empty($firstImage['image_caption'])): ?>
                                        <p><?php echo htmlspecialchars($firstImage['image_caption']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Image Gallery Grid -->
                        <?php if (!empty($validGalleryImages)): ?>
                            <h3 style="font-size: 2rem; font-weight: 700; margin: 3rem 0 1.5rem 0; color: #1e293b; text-align: center;">All Project Images</h3>
                            <div class="image-gallery-grid">
                                <?php foreach ($validGalleryImages as $index => $img): 
                                    // Get thumbnail URL
                                    $imagePath = trim($img['image_path']);
                                    $imagePathForCheck = ltrim($imagePath, '/');
                                    $fullImagePath = $rootPath . '/' . $imagePathForCheck;
                                    $imageExists = file_exists($fullImagePath);
                                    
                                    if ($imageExists) {
                                        // Construct base URL - ensure clean path
                                        $cleanPath = ltrim($imagePath, '/');
                                        $baseImageUrl = $baseUrl . '/' . $cleanPath;
                                        
                                        // Try to get thumbnail version if available
                                        try {
                                            $thumbUrl = $imageResizer->getImageUrl($imagePath, 'thumbnail', $baseUrl);
                                            if ($thumbUrl) {
                                                // Remove baseUrl to get relative path
                                                $thumbRelativePath = str_replace($baseUrl . '/', '', $thumbUrl);
                                                $thumbRelativePath = ltrim($thumbRelativePath, '/');
                                                $fullThumbPath = $rootPath . '/' . $thumbRelativePath;
                                                
                                                // Only use thumbnail if it exists
                                                if (file_exists($fullThumbPath) && $thumbUrl !== $baseImageUrl) {
                                                    // Use thumbnail
                                                } else {
                                                    $thumbUrl = $baseImageUrl;
                                                }
                                            } else {
                                                $thumbUrl = $baseImageUrl;
                                            }
                                        } catch (Exception $e) {
                                            // Use original if resizer fails
                                            $thumbUrl = $baseImageUrl;
                                        }
                                    } else {
                                        // Skip this image if it doesn't exist
                                        continue;
                                    }
                                ?>
                                    <div class="gallery-image-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         onclick="goToSlide(<?php echo $index; ?>); openLightbox(<?php echo $index; ?>);"
                                         data-index="<?php echo $index; ?>"
                                         data-slide-index="<?php echo $index; ?>">
                                        <img src="<?php echo htmlspecialchars($thumbUrl); ?>" 
                                             alt="<?php echo htmlspecialchars($img['image_alt'] ?: $portfolio['title']); ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none; width: 100%; height: 280px; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8; font-size: 32px;">🖼️</div>
                                        <div class="gallery-image-overlay">
                                            <?php if (!empty($img['image_alt'])): ?>
                                                <h5><?php echo htmlspecialchars($img['image_alt']); ?></h5>
                                            <?php endif; ?>
                                            <?php if (!empty($img['image_caption'])): ?>
                                                <p><?php echo htmlspecialchars(substr($img['image_caption'], 0, 80)); ?><?php echo strlen($img['image_caption']) > 80 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (!empty($portfolio['featured_image'])): 
                    $featuredPath = $portfolio['featured_image'];
                    $fullFeaturedPath = $rootPath . '/' . $featuredPath;
                    $featuredExists = file_exists($fullFeaturedPath);
                    
                    if ($featuredExists) {
                        $featuredUrl = $baseUrl . '/' . $featuredPath;
                        $featuredFullUrl = $featuredUrl;
                        
                        // Try to get resized version if available
                        try {
                            $resizedUrl = $imageResizer->getImageUrl($featuredPath, 'large', $baseUrl);
                            $resizedPath = $rootPath . '/' . str_replace($baseUrl . '/', '', $resizedUrl);
                            if (file_exists($resizedPath) && $resizedUrl !== ($baseUrl . '/' . $featuredPath)) {
                                $featuredUrl = $resizedUrl;
                            }
                        } catch (Exception $e) {
                            // Use original if resizer fails
                        }
                    } else {
                        $featuredUrl = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1024\' height=\'768\'%3E%3Crect fill=\'%23f0f0f0\' width=\'1024\' height=\'768\'/%3E%3Ctext fill=\'%23999\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\'%3EImage not found%3C/text%3E%3C/svg%3E';
                        $featuredFullUrl = $featuredUrl;
                    }
                ?>
                    <div class="portfolio-images-section">
                        <h2 class="section-title">📸 Project Image</h2>
                        <div class="image-slider">
                            <div class="slider-container">
                                <img src="<?php echo htmlspecialchars($featuredUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($portfolio['title']); ?>" 
                                     class="slider-image active"
                                     onclick="openLightbox(0)"
                                     data-src="<?php echo htmlspecialchars($featuredFullUrl); ?>"
                                     data-alt="<?php echo htmlspecialchars($portfolio['title']); ?>"
                                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1024\' height=\'768\'%3E%3Crect fill=\'%23f0f0f0\' width=\'1024\' height=\'768\'/%3E%3Ctext fill=\'%23999\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\'%3EImage error%3C/text%3E%3C/svg%3E';">
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="portfolio-images-section">
                        <div style="text-align: center; padding: 4rem 2rem; background: #f8fafc; border-radius: 16px;">
                            <div style="font-size: 64px; margin-bottom: 1rem;">🖼️</div>
                            <h3 style="font-size: 1.5rem; color: #64748b;">No images available for this project</h3>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lightbox Modal -->
        <div class="lightbox-modal" id="lightboxModal" onclick="closeLightbox()">
            <div class="lightbox-content" onclick="event.stopPropagation()">
                <button class="lightbox-close" onclick="closeLightbox()">×</button>
                <img class="lightbox-image" id="lightboxImage" src="" alt="">
                <button class="lightbox-nav prev" onclick="changeLightboxSlide(-1); event.stopPropagation();">‹</button>
                <button class="lightbox-nav next" onclick="changeLightboxSlide(1); event.stopPropagation();">›</button>
                <div class="lightbox-caption" id="lightboxCaption"></div>
            </div>
        </div>
        
        <script>
            let currentSlide = 0;
            let autoSlideInterval = null;
            let isPaused = false;
            const slides = document.querySelectorAll('.slider-image');
            const dots = document.querySelectorAll('.slider-dot');
            const galleryItems = document.querySelectorAll('.gallery-image-item');
            const captions = <?php 
                if (!empty($validGalleryImages)) {
                    echo json_encode(array_column($validGalleryImages, 'image_caption'));
                } else {
                    echo json_encode([]);
                }
            ?>;
            const altTexts = <?php 
                if (!empty($validGalleryImages)) {
                    echo json_encode(array_column($validGalleryImages, 'image_alt'));
                } else {
                    echo json_encode([]);
                }
            ?>;
            const baseUrl = '<?php echo $baseUrl; ?>';
            
            // Debug: Log slides found
            console.log('Portfolio slides found:', slides.length);
            console.log('Portfolio images data:', {
                captions: captions,
                altTexts: altTexts,
                slides: Array.from(slides).map(s => s.src)
            });
            
            // Get full image URLs from data-src attributes
            function getFullImageUrl(index) {
                const slide = slides[index];
                if (slide && slide.dataset.src) {
                    return slide.dataset.src;
                }
                if (slide && slide.src) {
                    return slide.src;
                }
                return '';
            }
            
            function showSlide(index) {
                if (slides.length === 0) {
                    console.warn('No slides found');
                    return;
                }
                
                // Ensure index is within bounds
                if (index < 0) index = slides.length - 1;
                if (index >= slides.length) index = 0;
                
                currentSlide = index;
                
                // Show/hide slides - force display style with !important
                slides.forEach((slide, i) => {
                    if (i === index) {
                        slide.classList.add('active');
                        // Force display with inline style
                        slide.setAttribute('style', 'display: block !important; visibility: visible !important; opacity: 1 !important;');
                    } else {
                        slide.classList.remove('active');
                        // Force hide with inline style
                        slide.setAttribute('style', 'display: none !important; visibility: hidden !important; opacity: 0 !important;');
                    }
                });
                
                // Update dots
                if (dots.length > 0) {
                    dots.forEach((dot, i) => {
                        dot.classList.toggle('active', i === index);
                    });
                }
                
                // Update gallery items
                if (galleryItems.length > 0) {
                    galleryItems.forEach((item, i) => {
                        item.classList.toggle('active', i === index);
                    });
                }
                
                // Update caption
                const captionDiv = document.getElementById('sliderCaption');
                if (captionDiv && captions && altTexts) {
                    if (altTexts[index]) {
                        let captionHtml = `<h4>${escapeHtml(altTexts[index])}</h4>`;
                        if (captions[index]) {
                            captionHtml += `<p>${escapeHtml(captions[index])}</p>`;
                        }
                        captionDiv.innerHTML = captionHtml;
                        captionDiv.style.display = 'block';
                    } else if (captions[index]) {
                        captionDiv.innerHTML = `<p>${escapeHtml(captions[index])}</p>`;
                        captionDiv.style.display = 'block';
                    } else {
                        captionDiv.style.display = 'none';
                    }
                } else if (captionDiv) {
                    captionDiv.style.display = 'none';
                }
                
                // Scroll gallery item into view
                if (galleryItems[index]) {
                    galleryItems[index].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
            
            // HTML escape helper
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function changeSlide(direction) {
                if (slides.length === 0) {
                    console.warn('Cannot change slide: no slides available');
                    return;
                }
                const newIndex = (currentSlide + direction + slides.length) % slides.length;
                showSlide(newIndex);
            }
            
            function goToSlide(index) {
                if (slides.length === 0) {
                    console.warn('Cannot go to slide: no slides available');
                    return;
                }
                if (index >= 0 && index < slides.length) {
                    showSlide(index);
                } else {
                    console.warn('Invalid slide index:', index, 'Total slides:', slides.length);
                }
            }
            
            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Portfolio page loaded');
                console.log('Slides count:', slides.length);
                console.log('Dots count:', dots.length);
                console.log('Gallery items count:', galleryItems.length);
                
                // Log all slide sources for debugging
                slides.forEach((slide, i) => {
                    console.log(`Slide ${i}:`, {
                        src: slide.src,
                        dataset: slide.dataset,
                        classes: slide.className,
                        display: window.getComputedStyle(slide).display
                    });
                });
                
                // Ensure first slide is shown if any exist
                if (slides.length > 0) {
                    // Force first slide to be visible with !important
                    const firstSlide = slides[0];
                    if (firstSlide) {
                        firstSlide.classList.add('active');
                        firstSlide.setAttribute('style', 'display: block !important; visibility: visible !important; opacity: 1 !important;');
                        currentSlide = 0;
                        
                        // Hide other slides with !important
                        for (let i = 1; i < slides.length; i++) {
                            slides[i].classList.remove('active');
                            slides[i].setAttribute('style', 'display: none !important; visibility: hidden !important; opacity: 0 !important;');
                        }
                        
                        // Update dots and gallery items
                        if (dots.length > 0 && dots[0]) {
                            dots[0].classList.add('active');
                        }
                        if (galleryItems.length > 0 && galleryItems[0]) {
                            galleryItems[0].classList.add('active');
                        }
                        
                        console.log('First slide initialized:', {
                            src: firstSlide.src,
                            display: window.getComputedStyle(firstSlide).display,
                            hasActive: firstSlide.classList.contains('active')
                        });
                    }
                } else {
                    console.warn('No slides found in portfolio slider');
                    // Check if we're in the portfolio section but no images
                    const sliderContainer = document.querySelector('.slider-container');
                    if (sliderContainer && sliderContainer.children.length === 0) {
                        console.warn('Slider container is empty - no images to display');
                    }
                }
            });
            
            // Lightbox functions
            function openLightbox(index) {
                const modal = document.getElementById('lightboxModal');
                const lightboxImage = document.getElementById('lightboxImage');
                const lightboxCaption = document.getElementById('lightboxCaption');
                
                if (slides && slides[index]) {
                    const slide = slides[index];
                    const fullUrl = slide.dataset.src || getFullImageUrl(index);
                    const alt = slide.dataset.alt || (altTexts && altTexts[index] ? altTexts[index] : 'Image');
                    const caption = slide.dataset.caption || (captions && captions[index] ? captions[index] : '');
                    
                    lightboxImage.src = fullUrl;
                    lightboxImage.alt = alt;
                    
                    let captionHtml = '';
                    if (alt && alt !== 'Image') {
                        captionHtml += `<h4>${alt}</h4>`;
                    }
                    if (caption) {
                        captionHtml += `<p>${caption}</p>`;
                    }
                    lightboxCaption.innerHTML = captionHtml;
                    
                    currentSlide = index;
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
            
            function closeLightbox() {
                const modal = document.getElementById('lightboxModal');
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            function changeLightboxSlide(direction) {
                if (slides.length === 0) return;
                const newIndex = (currentSlide + direction + slides.length) % slides.length;
                openLightbox(newIndex);
            }
            
            function startAutoSlide() {
                if (autoSlideInterval) {
                    clearInterval(autoSlideInterval);
                }
                if (slides.length > 1 && !isPaused) {
                    autoSlideInterval = setInterval(() => {
                        changeSlide(1);
                    }, 6000); // Auto-advance every 6 seconds
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
            
            // Initialize auto-slide when page loads
            if (slides.length > 1) {
                startAutoSlide();
                
                // Pause on hover, resume on mouse leave
                const sliderContainer = document.querySelector('.image-slider');
                if (sliderContainer) {
                    sliderContainer.addEventListener('mouseenter', pauseAutoSlide);
                    sliderContainer.addEventListener('mouseleave', resumeAutoSlide);
                }
                
                // Pause when user interacts with controls
                const navButtons = document.querySelectorAll('.slider-nav');
                navButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        pauseAutoSlide();
                        setTimeout(resumeAutoSlide, 10000);
                    });
                });
                
                const dotButtons = document.querySelectorAll('.slider-dot');
                dotButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        pauseAutoSlide();
                        setTimeout(resumeAutoSlide, 10000);
                    });
                });
                
                galleryItems.forEach(item => {
                    item.addEventListener('click', () => {
                        pauseAutoSlide();
                        setTimeout(resumeAutoSlide, 10000);
                    });
                });
            }
            
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                const modal = document.getElementById('lightboxModal');
                if (modal && modal.classList.contains('active')) {
                    // Lightbox is open
                    if (e.key === 'Escape') closeLightbox();
                    if (e.key === 'ArrowLeft') changeLightboxSlide(-1);
                    if (e.key === 'ArrowRight') changeLightboxSlide(1);
                } else {
                    // Normal view
                    if (slides.length === 0) return;
                    if (e.key === 'ArrowLeft') {
                        pauseAutoSlide();
                        changeSlide(-1);
                        setTimeout(resumeAutoSlide, 10000);
                    }
                    if (e.key === 'ArrowRight') {
                        pauseAutoSlide();
                        changeSlide(1);
                        setTimeout(resumeAutoSlide, 10000);
                    }
                }
            });
            
            // Clean up on page unload
            window.addEventListener('beforeunload', () => {
                if (autoSlideInterval) {
                    clearInterval(autoSlideInterval);
                }
            });
        </script>
    <?php else: ?>
        <!-- Portfolio List View -->
        <div class="page-header">
            <h1>Our Portfolio</h1>
            <p>Showcasing our borehole drilling projects and company achievements</p>
        </div>
        
        <div class="container">
            <?php if (empty($portfolios)): ?>
                <div style="text-align: center; padding: 4rem 2rem;">
                    <div style="font-size: 64px; margin-bottom: 1rem;">📸</div>
                    <h2>No portfolio items yet</h2>
                    <p style="color: #64748b; margin-top: 0.5rem;">Check back soon for our latest projects!</p>
                </div>
            <?php else: ?>
                <div class="portfolio-grid">
                    <?php foreach ($portfolios as $item): 
                        // Determine which image to show: featured_image first, then first_image from gallery
                        $displayImage = null;
                        $imageUrl = null;
                        
                        if (!empty($item['featured_image']) && file_exists($rootPath . '/' . $item['featured_image'])) {
                            $displayImage = $item['featured_image'];
                        } elseif (!empty($item['first_image']) && file_exists($rootPath . '/' . $item['first_image'])) {
                            $displayImage = $item['first_image'];
                        }
                        
                        if ($displayImage) {
                            $imageUrl = $baseUrl . '/' . $displayImage;
                            
                            // Try to get medium size if available
                            try {
                                $resizedUrl = $imageResizer->getImageUrl($displayImage, 'medium', $baseUrl);
                                $resizedPath = $rootPath . '/' . str_replace($baseUrl . '/', '', $resizedUrl);
                                if (file_exists($resizedPath) && $resizedUrl !== ($baseUrl . '/' . $displayImage)) {
                                    $imageUrl = $resizedUrl;
                                }
                            } catch (Exception $e) {
                                // Use original if resizer fails
                            }
                        }
                    ?>
                        <div class="portfolio-card" onclick="window.location='<?php echo $baseUrl; ?>/cms/portfolio/<?php echo urlencode($item['slug']); ?>'">
                            <?php if ($imageUrl): ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="portfolio-card-image"
                                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'250\'%3E%3Crect fill=\'%23f1f5f9\' width=\'400\' height=\'250\'/%3E%3Ctext fill=\'%23cbd5e1\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'24\'%3E🖼️%3C/text%3E%3C/svg%3E'; this.nextElementSibling.style.display='flex';">
                                <div class="portfolio-card-image" style="display: none; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); align-items: center; justify-content: center; font-size: 64px; color: #cbd5e1;">🖼️</div>
                            <?php else: ?>
                                <div class="portfolio-card-image" style="background: linear-gradient(135deg, #f1f5f9, #e2e8f0); display: flex; align-items: center; justify-content: center; font-size: 64px; color: #cbd5e1;">🖼️</div>
                            <?php endif; ?>
                            <div class="portfolio-card-body">
                                <h3 class="portfolio-card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <div class="portfolio-card-meta">
                                    <?php if ($item['location']): ?>
                                        <div>📍 <?php echo htmlspecialchars($item['location']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($item['client_name']): ?>
                                        <div>👤 <?php echo htmlspecialchars($item['client_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($item['project_date']): ?>
                                        <div>📅 <?php echo date('M Y', strtotime($item['project_date'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['description'])): ?>
                                    <div class="portfolio-card-description">
                                        <?php echo htmlspecialchars(substr(strip_tags($item['description']), 0, 150)); ?>
                                        <?php echo strlen(strip_tags($item['description'])) > 150 ? '...' : ''; ?>
                                    </div>
                                <?php endif; ?>
                                <a href="<?php echo $baseUrl; ?>/cms/portfolio/<?php echo urlencode($item['slug']); ?>" class="portfolio-card-link">View Project →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php include 'footer.php'; ?>
</body>
</html>


