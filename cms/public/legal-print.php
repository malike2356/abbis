<?php
/**
 * Legal Document Print Version
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';

$pdo = getDBConnection();

// Get legal document slug from URL
$legalSlug = $_GET['slug'] ?? '';

// If not in GET, try to extract from REQUEST_URI
if (empty($legalSlug)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestUri = strtok($requestUri, '?'); // Remove query string
    
    // Handle both /abbis3.2/cms/legal/slug/print and /cms/legal/slug/print
    $requestUri = preg_replace('#^/abbis3.2#', '', $requestUri);
    $requestUri = trim($requestUri, '/');
    
    // Extract slug from cms/legal/slug/print pattern
    if (preg_match('#^cms/legal/([^/]+)/print/?$#', $requestUri, $matches)) {
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
    exit;
}

// Get company name
$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($legalDoc['title']); ?> - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        @media print {
            @page { margin: 2cm; }
            body { margin: 0; }
        }
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        .legal-header { border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 2rem; }
        .legal-header h1 { margin: 0; font-size: 24px; }
        .legal-meta { font-size: 12px; color: #666; margin-top: 0.5rem; }
        .legal-content { line-height: 1.8; }
        .legal-content h2 { margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid #ccc; padding-bottom: 0.5rem; }
        .legal-content h3 { margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .legal-content ol, .legal-content ul { margin: 1rem 0; padding-left: 2rem; }
        .legal-content li { margin-bottom: 0.5rem; }
        .legal-footer { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid #ccc; font-size: 12px; color: #666; }
        .no-print { display: none; }
    </style>
</head>
<body>
    <div class="legal-header">
        <h1><?php echo htmlspecialchars($legalDoc['title']); ?></h1>
        <div class="legal-meta">
            <?php echo htmlspecialchars($companyName); ?> | 
            <?php if ($legalDoc['version']): ?>
                Version <?php echo htmlspecialchars($legalDoc['version']); ?> | 
            <?php endif; ?>
            <?php if ($legalDoc['effective_date']): ?>
                Effective: <?php echo date('F j, Y', strtotime($legalDoc['effective_date'])); ?> | 
            <?php endif; ?>
            Printed: <?php echo date('F j, Y g:i A'); ?>
        </div>
    </div>
    
    <div class="legal-content">
        <?php echo $legalDoc['content']; ?>
    </div>
    
    <div class="legal-footer">
        <p><strong><?php echo htmlspecialchars($companyName); ?></strong></p>
        <p>This document is legally binding. Payment (in part or full) constitutes agreement with these terms.</p>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>

