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

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestUri = strtok($requestUri, '?');
$basePath = app_base_path();
$cmsPrefix = ($basePath ?: '') . '/cms/';
$path = preg_replace('#^' . preg_quote($cmsPrefix, '#') . '#', '', $requestUri);
$path = trim($path, '/');
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
    case 'rig-request':
        include __DIR__ . '/rig-request.php';
        break;
    case 'contact':
    case 'contact-us':
        include __DIR__ . '/contact.php';
        break;
    case 'complaints':
    case 'feedback':
        include __DIR__ . '/complaints.php';
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
    case 'portfolio':
        // Handle portfolio items with slugs: /portfolio/slug
        if ($slug) {
            // Decode URL-encoded slug and pass as GET parameter to portfolio.php
            $_GET['slug'] = urldecode($slug);
        }
        include __DIR__ . '/portfolio.php';
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
                $homeHref = rtrim(app_path(), '/') . '/';
                echo '<p><a href="' . $homeHref . '">← Back to Home</a></p>';
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
            header('Location: ' . rtrim(app_path(), '/') . '/');
            exit;
        }
}

