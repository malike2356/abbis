<?php
/**
 * CMS Public Router
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';

$pdo = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CMS tables exist
try { $pdo->query("SELECT 1 FROM cms_pages LIMIT 1"); }
catch (Throwable $e) {
    @include_once '../../database/run-sql.php';
    @run_sql_file(__DIR__ . '/../../database/cms_migration.sql');
}

$path = trim($_SERVER['REQUEST_URI'] ?? '', '/');
$path = str_replace('/cms/', '', $path);
$path = str_replace('/abbis3.2/cms/', '', $path);
$segments = explode('/', $path);

$page = $segments[0] ?? 'home';
$slug = $segments[1] ?? '';

// Route handling
switch ($page) {
    case 'shop':
        include __DIR__ . '/shop.php';
        break;
    case 'quote':
        include __DIR__ . '/quote.php';
        break;
    case 'cart':
    case 'checkout':
        include __DIR__ . '/cart.php';
        break;
    case 'blog':
        if ($slug) {
            include __DIR__ . '/post.php';
        } else {
            include __DIR__ . '/blog.php';
        }
        break;
    case 'post':
        include __DIR__ . '/post.php';
        break;
    case 'legal':
        // Legal documents route
        $legalSlug = $slug ?: 'drilling-agreement';
        try {
            $legalStmt = $pdo->prepare("SELECT * FROM cms_legal_documents WHERE slug=? AND is_active=1 LIMIT 1");
            $legalStmt->execute([$legalSlug]);
            $legalDoc = $legalStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($legalDoc) {
                // Check if this is a print request
                if (isset($segments[2]) && $segments[2] === 'print') {
                    include __DIR__ . '/legal-print.php';
                } else {
                    include __DIR__ . '/legal-view.php';
                }
            } else {
                header('HTTP/1.0 404 Not Found');
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Document Not Found</title></head><body>';
                echo '<h1>Document Not Found</h1>';
                $baseUrl = '/abbis3.2';
                if (defined('APP_URL')) {
                    $parsed = parse_url(APP_URL);
                    $baseUrl = $parsed['path'] ?? '/abbis3.2';
                }
                echo '<p><a href="' . $baseUrl . '/">← Back to Home</a></p>';
                echo '</body></html>';
                exit;
            }
        } catch (PDOException $e) {
            header('HTTP/1.0 404 Not Found');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Document Not Found</title></head><body>';
            echo '<h1>Document Not Found</h1>';
            echo '</body></html>';
            exit;
        }
        break;
    default:
        // Try to load custom page
        $pageStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE slug=? AND status='published' LIMIT 1");
        $pageStmt->execute([$page]);
        $cmsPage = $pageStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cmsPage) {
            include __DIR__ . '/page.php';
        } else {
            // 404 or fallback to homepage
            header('Location: /');
            exit;
        }
}

