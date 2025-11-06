<?php
/**
 * Legal Document View Page
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';

$pdo = getDBConnection();

// Get base URL
require_once __DIR__ . '/base-url.php';

// Get legal document slug from URL
$legalSlug = $_GET['slug'] ?? '';

// If not in GET, try to extract from REQUEST_URI
if (empty($legalSlug)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestUri = strtok($requestUri, '?'); // Remove query string
    
    // Handle both /abbis3.2/cms/legal/slug and /cms/legal/slug
    $requestUri = preg_replace('#^/abbis3.2#', '', $requestUri);
    $requestUri = trim($requestUri, '/');
    
    // Extract slug from cms/legal/slug pattern
    if (preg_match('#^cms/legal/([^/]+)/?$#', $requestUri, $matches)) {
        $legalSlug = $matches[1];
    } elseif (preg_match('#/legal/([^/]+)#', $requestUri, $matches)) {
        $legalSlug = $matches[1];
    }
}

// Default to drilling-agreement if still empty
if (empty($legalSlug)) {
    $legalSlug = 'drilling-agreement';
}

try {
    $legalStmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE slug=? AND is_active=1 LIMIT 1");
    $legalStmt->execute([$legalSlug]);
    $legalDoc = $legalStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $legalDoc = null;
}

if (!$legalDoc) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Document Not Found</title></head><body>';
    echo '<h1>Document Not Found</h1>';
    echo '<p><a href="' . $baseUrl . '/">← Back to Home</a></p>';
    echo '</body></html>';
    exit;
}

// Get CMS settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
$cmsSettings = [];
while ($row = $settingsStmt->fetch()) {
    $cmsSettings[$row['setting_key']] = $row['setting_value'];
}

// Get company name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Company');

// Get active theme
$themeStmt = $pdo->query("SELECT * FROM cms_themes WHERE is_active=1 LIMIT 1");
$theme = $themeStmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>'default','config'=>'{}'];
$themeConfig = json_decode($theme['config'] ?? '{}', true);

$siteTitle = $legalDoc['title'] . ' - ' . $companyName;
$primaryColor = $themeConfig['primary_color'] ?? '#0ea5e9';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <?php include __DIR__ . '/header.php'; ?>
    <style>
        .legal-document { max-width: 900px; margin: 0 auto; padding: 2rem; background: white; }
        .legal-header { border-bottom: 2px solid #e5e7eb; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .legal-header h1 { margin: 0 0 0.5rem 0; color: #1e293b; }
        .legal-meta { color: #646970; font-size: 0.875rem; }
        .legal-content { line-height: 1.8; color: #374151; }
        .legal-content h2 { color: #1e293b; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
        .legal-content h3 { color: #374151; margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .legal-content ol, .legal-content ul { margin: 1rem 0; padding-left: 2rem; }
        .legal-content li { margin-bottom: 0.5rem; }
        .legal-content strong { color: #1e293b; }
        .legal-actions { margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; }
        @media print {
            .legal-actions { display: none; }
            header, footer { display: none; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <div class="legal-document">
        <div class="legal-header">
            <h1><?php echo htmlspecialchars($legalDoc['title']); ?></h1>
            <div class="legal-meta">
                <?php if ($legalDoc['version']): ?>
                    <strong>Version:</strong> <?php echo htmlspecialchars($legalDoc['version']); ?>
                <?php endif; ?>
                <?php if ($legalDoc['effective_date']): ?>
                    | <strong>Effective Date:</strong> <?php echo date('F j, Y', strtotime($legalDoc['effective_date'])); ?>
                <?php endif; ?>
                | <strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($legalDoc['updated_at'])); ?>
            </div>
        </div>
        
        <div class="legal-content">
            <?php echo $legalDoc['content']; ?>
        </div>
        
        <div class="legal-actions">
            <button onclick="window.print()" class="button button-primary">Print Document</button>
            <a href="<?php echo $baseUrl; ?>/cms/legal/<?php echo htmlspecialchars($legalDoc['slug']); ?>/print" target="_blank" class="button">Print Version</a>
            <a href="<?php echo $baseUrl; ?>/" class="button">Back to Home</a>
        </div>
    </div>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

