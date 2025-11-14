<?php
/**
 * Construction Theme - Page Template
 */
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
$secondaryColor = $themeConfig['secondary_color'] ?? '#0f2440';
$baseUrl = $baseUrl ?? '/abbis3.2';
$themeUrl = $baseUrl . '/cms/themes/construction';

$pageContent = $page['content'] ?? '';
$hasBuilderMarkup = false;
if (is_string($pageContent) && $pageContent !== '') {
    $lowerContent = strtolower($pageContent);
    $builderMarkers = ['gjs-', '<section', '<div class="container', '<div class="row', '<style', '<script', 'data-gjs'];
    foreach ($builderMarkers as $marker) {
        if (strpos($lowerContent, $marker) !== false) {
            $hasBuilderMarkup = true;
            break;
        }
    }
}

$heroTitle = $page['title'] ?? 'Page';
$heroSubtitle = $page['excerpt'] ?? ($page['seo_description'] ?? '');
$contentPadding = $hasBuilderMarkup ? 'calc(var(--cms-body-offset, 0px) + 24px) 0 48px' : 'calc(48px + var(--cms-body-offset, 0px)) 0 64px';
$contentMarginTop = $hasBuilderMarkup ? 'margin-top: calc(var(--cms-body-offset, 0px) * -1);' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page['title'] ?? 'Page'); ?> - <?php echo htmlspecialchars($siteTitle ?? 'Construction Company'); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $themeUrl; ?>/assets/images/favicon.ico">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,700" rel="stylesheet">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $themeUrl; ?>/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: <?php echo $primaryColor; ?>;
            --secondary: <?php echo $secondaryColor; ?>;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../public/header.php'; ?>

    <!-- Page Header -->
    <?php if (!$hasBuilderMarkup): ?>
        <section class="page-header" style="background: linear-gradient(135deg, var(--primary), #2563eb); color: white; padding: calc(56px + var(--cms-body-offset, 0px)) 0 40px; text-align: center; margin-top: calc(var(--cms-body-offset, 0px) * -1);">
            <div class="container">
                <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem; color: white;"><?php echo htmlspecialchars($heroTitle); ?></h1>
                <?php if (!empty($heroSubtitle)): ?>
                    <p style="font-size: 1.2rem; opacity: 0.9;"><?php echo htmlspecialchars($heroSubtitle); ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Page Content -->
    <section class="page-content padding ptb-xs-40" style="padding: <?php echo $contentPadding; ?>; <?php echo $contentMarginTop; ?>">
        <?php if ($hasBuilderMarkup): ?>
            <div class="page-builder-wrapper" style="width: 100%; margin: 0 auto;">
                <?php echo $pageContent; ?>
            </div>
        <?php else: ?>
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="page-content-wrapper" style="background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                            <?php echo $pageContent ?: '<p style="margin:0;">Content coming soon.</p>'; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php include __DIR__ . '/../../public/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $themeUrl; ?>/assets/js/theme.js"></script>
</body>
</html>
