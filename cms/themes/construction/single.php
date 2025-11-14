<?php
/**
 * Construction Theme - Single Post Template
 */
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
$secondaryColor = $themeConfig['secondary_color'] ?? '#0f2440';
$baseUrl = $baseUrl ?? '/abbis3.2';
$themeUrl = $baseUrl . '/cms/themes/construction';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($post['title'] ?? 'Post'); ?> - <?php echo htmlspecialchars($siteTitle ?? 'Construction Company'); ?></title>
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

    <!-- Post Header -->
    <section class="post-header" style="background: linear-gradient(135deg, var(--primary), #2563eb); color: white; padding: 100px 0 80px; text-align: center;">
        <div class="container">
            <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem; color: white;"><?php echo htmlspecialchars($post['title'] ?? 'Post'); ?></h1>
            <div class="post-meta" style="display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; margin-top: 1rem; opacity: 0.9;">
                <?php if (!empty($post['created_at'])): ?>
                    <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($post['created_at'])); ?></span>
                <?php endif; ?>
                <?php if (!empty($post['author'])): ?>
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Post Content -->
    <section class="post-content padding ptb-xs-40" style="padding: 80px 0;">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2">
                    <article class="post-wrapper" style="background: white; padding: 3rem; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                        <?php if (!empty($post['featured_image'])): ?>
                            <div class="post-featured-image" style="margin-bottom: 2rem;">
                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="img-fluid" style="width: 100%; border-radius: 10px;">
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-content-wrapper" style="line-height: 1.8; font-size: 1.1rem; color: #555;">
                            <?php echo $post['content'] ?? ''; ?>
                        </div>
                        
                        <?php if (!empty($post['tags'])): ?>
                            <div class="post-tags" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                                <h4 style="font-size: 1.2rem; margin-bottom: 1rem;">Tags:</h4>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php
                                    $tags = explode(',', $post['tags']);
                                    foreach ($tags as $tag):
                                        $tag = trim($tag);
                                        if (!empty($tag)):
                                    ?>
                                        <span style="background: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem;"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                    
                    <div class="post-navigation" style="margin-top: 3rem; text-align: center;">
                        <a href="<?php echo $baseUrl; ?>/cms/blog" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Blog
                        </a>
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
