<?php
/**
 * Construction Theme - Page Template
 */
$primaryColor = $themeConfig['primary_color'] ?? '#f39c12';
$secondaryColor = $themeConfig['secondary_color'] ?? '#34495e';
$baseUrl = $baseUrl ?? '/abbis3.2';
$themeUrl = $baseUrl . '/cms/themes/construction';
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
    <section class="page-header" style="background: linear-gradient(135deg, var(--primary), #e67e22); color: white; padding: 100px 0 80px; text-align: center;">
        <div class="container">
            <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem; color: white;"><?php echo htmlspecialchars($page['title'] ?? 'Page'); ?></h1>
            <?php if (!empty($page['excerpt'])): ?>
                <p style="font-size: 1.2rem; opacity: 0.9;"><?php echo htmlspecialchars($page['excerpt']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Page Content -->
    <section class="page-content padding ptb-xs-40" style="padding: 80px 0;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="page-content-wrapper" style="background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                        <?php echo $page['content'] ?? ''; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../public/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $themeUrl; ?>/assets/js/theme.js"></script>
</body>
</html>
