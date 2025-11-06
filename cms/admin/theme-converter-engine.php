<?php
/**
 * Theme Converter Engine
 * Handles conversion of HTML templates and WordPress themes to CMS themes
 */
class ThemeConverter {
    private $pdo;
    private $tempDir;
    private $themeDir;
    private $conversions = [];
    private $filesConverted = 0;
    private $isWordPress = false;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Main conversion method
     */
    public function convert($zipFile) {
        try {
            // Create temp directory
            $this->tempDir = sys_get_temp_dir() . '/theme_convert_' . uniqid();
            mkdir($this->tempDir, 0755, true);
            
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipFile['tmp_name']) !== TRUE) {
                return ['success' => false, 'error' => 'Failed to extract ZIP file.'];
            }
            $zip->extractTo($this->tempDir);
            $zip->close();
            
            // Analyze structure and find theme root
            $themeRoot = $this->findThemeRoot();
            if (!$themeRoot) {
                $this->cleanup();
                return ['success' => false, 'error' => 'Could not find theme root. Theme must contain index.html, index.php, or style.css.'];
            }
            
            // Detect if WordPress theme
            $this->isWordPress = $this->detectWordPress($themeRoot);
            
            // Read theme info
            $themeInfo = $this->extractThemeInfo($themeRoot);
            
            // Create theme directory
            $themeSlug = $this->slugify($themeInfo['name']);
            $themesDir = dirname(__DIR__) . '/themes/' . $themeSlug;
            if (is_dir($themesDir)) {
                $themeSlug .= '-' . time();
                $themesDir = dirname(__DIR__) . '/themes/' . $themeSlug;
            }
            mkdir($themesDir, 0755, true);
            $this->themeDir = $themesDir;
            
            // Copy and convert files
            $this->convertFiles($themeRoot, $themesDir);
            
            // Create style.css header if not exists
            $this->createStyleHeader($themesDir, $themeInfo);
            
            // Create index.php if needed
            $this->createIndexFile($themesDir, $themeRoot);
            
            // Install to database
            $this->installTheme($themeInfo, $themeSlug);
            
            // Cleanup
            $this->cleanup();
            
            return [
                'success' => true,
                'message' => "Theme '{$themeInfo['name']}' converted and installed successfully!",
                'theme_name' => $themeInfo['name'],
                'theme_slug' => $themeSlug,
                'is_wordpress' => $this->isWordPress,
                'files_converted' => $this->filesConverted,
                'conversions' => $this->conversions
            ];
            
        } catch (Exception $e) {
            $this->cleanup();
            return ['success' => false, 'error' => 'Conversion error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Find theme root directory
     */
    private function findThemeRoot() {
        // Look for common theme indicators
        $indicators = ['style.css', 'index.php', 'index.html', 'header.php', 'footer.php'];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $possibleRoots = [];
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $name = $file->getFilename();
                if (in_array($name, $indicators)) {
                    $path = $file->getPath();
                    $depth = substr_count(str_replace($this->tempDir, '', $path), DIRECTORY_SEPARATOR);
                    $possibleRoots[$path] = $depth;
                }
            }
        }
        
        if (empty($possibleRoots)) {
            // Try root temp directory
            if (file_exists($this->tempDir . '/index.html') || file_exists($this->tempDir . '/index.php')) {
                return $this->tempDir;
            }
            return null;
        }
        
        // Return directory with lowest depth (most likely root)
        asort($possibleRoots);
        return key($possibleRoots);
    }
    
    /**
     * Detect if theme is WordPress-based
     */
    private function detectWordPress($themeRoot) {
        $wordpressIndicators = [
            'functions.php',
            'wp-content/themes',
            'style.css' => '/Theme Name:/i'
        ];
        
        // Check for functions.php
        if (file_exists($themeRoot . '/functions.php')) {
            return true;
        }
        
        // Check style.css for WordPress header
        if (file_exists($themeRoot . '/style.css')) {
            $content = file_get_contents($themeRoot . '/style.css');
            if (preg_match('/Theme Name:/i', $content)) {
                return true;
            }
        }
        
        // Check PHP files for WordPress functions
        $phpFiles = glob($themeRoot . '/*.php');
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/\b(get_header|wp_nav_menu|the_title|the_content|bloginfo|get_option|wp_head|wp_footer)\s*\(/i', $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract theme information
     */
    private function extractThemeInfo($themeRoot) {
        $info = [
            'name' => 'Converted Theme',
            'description' => 'Theme converted from template',
            'version' => '1.0',
            'author' => ''
        ];
        
        // Try style.css
        if (file_exists($themeRoot . '/style.css')) {
            $content = file_get_contents($themeRoot . '/style.css');
            if (preg_match('/Theme Name:\s*(.+)/i', $content, $m)) {
                $info['name'] = trim($m[1]);
            }
            if (preg_match('/Description:\s*(.+)/i', $content, $m)) {
                $info['description'] = trim($m[1]);
            }
            if (preg_match('/Version:\s*(.+)/i', $content, $m)) {
                $info['version'] = trim($m[1]);
            }
            if (preg_match('/Author:\s*(.+)/i', $content, $m)) {
                $info['author'] = trim($m[1]);
            }
        }
        
        // Try to get name from directory
        if ($info['name'] === 'Converted Theme') {
            $dirName = basename($themeRoot);
            if ($dirName !== 'temp' && $dirName !== 'theme_convert_') {
                $info['name'] = ucwords(str_replace(['-', '_'], ' ', $dirName));
            }
        }
        
        return $info;
    }
    
    /**
     * Convert all files
     */
    private function convertFiles($source, $dest) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                // Skip unwanted files
                $ext = strtolower($item->getExtension());
                $name = $item->getFilename();
                
                // Skip system files
                if (in_array($name, ['.DS_Store', 'Thumbs.db', '.gitignore'])) {
                    continue;
                }
                
                // Convert PHP files
                if ($ext === 'php') {
                    $content = $this->convertPhpFile($item->getRealPath());
                    file_put_contents($destPath, $content);
                    $this->filesConverted++;
                    $this->conversions[] = "Converted PHP file: {$name}";
                }
                // Convert HTML files to PHP
                elseif ($ext === 'html' || $ext === 'htm') {
                    $content = $this->convertHtmlFile($item->getRealPath());
                    $destPath = preg_replace('/\.html?$/', '.php', $destPath);
                    file_put_contents($destPath, $content);
                    $this->filesConverted++;
                    $this->conversions[] = "Converted HTML to PHP: {$name}";
                }
                // Copy other files as-is
                else {
                    copy($item->getRealPath(), $destPath);
                }
            }
        }
    }
    
    /**
     * Convert PHP file (WordPress functions to CMS)
     */
    private function convertPhpFile($filePath) {
        $content = file_get_contents($filePath);
        $original = $content;
        
        // WordPress function replacements
        $replacements = [
            // Header/Footer
            '/\bget_header\s*\([^)]*\)\s*;/i' => '<?php include __DIR__ . \'/../../public/header.php\'; ?>',
            '/\bget_footer\s*\([^)]*\)\s*;/i' => '<?php include __DIR__ . \'/../../public/footer.php\'; ?>',
            
            // Title
            '/\bthe_title\s*\([^)]*\)\s*;/i' => '<?php echo htmlspecialchars($page[\'title\'] ?? $post[\'title\'] ?? \'\'); ?>',
            
            // Content
            '/\bthe_content\s*\([^)]*\)\s*;/i' => '<?php echo $page[\'content\'] ?? $post[\'content\'] ?? \'\'; ?>',
            
            // Permalink
            '/\bthe_permalink\s*\([^)]*\)\s*;/i' => '<?php echo $baseUrl . \'/cms/post/\' . urlencode($post[\'slug\'] ?? \'\'); ?>',
            '/\bget_permalink\s*\([^)]*\)\s*;/i' => '$baseUrl . \'/cms/post/\' . urlencode($post[\'slug\'] ?? \'\')',
            
            // Navigation
            '/\bwp_nav_menu\s*\([^)]*\)\s*;/i' => '<?php require_once __DIR__ . \'/../../public/menu-functions.php\'; echo renderMenuItems(getMenuItemsForLocation(\'primary\', $pdo)); ?>',
            
            // Blog info
            '/\bbloginfo\s*\(\s*[\'"]name[\'"]\s*\)\s*;/i' => '<?php echo htmlspecialchars($siteTitle ?? \'Site Title\'); ?>',
            '/\bbloginfo\s*\(\s*[\'"]description[\'"]\s*\)\s*;/i' => '<?php echo htmlspecialchars($siteTagline ?? \'\'); ?>',
            '/\bbloginfo\s*\(\s*[\'"]charset[\'"]\s*\)\s*;/i' => 'UTF-8',
            '/\bget_bloginfo\s*\(\s*[\'"]name[\'"]\s*\)/i' => '($siteTitle ?? \'Site Title\')',
            
                         // Options - include compatibility layer for get_option
             // Note: wp-compatibility.php will be loaded, so get_option will work
            
            // Post meta
            '/\bget_post_meta\s*\([^)]+\)/i' => 'null',
            
            // Hooks (simplified)
            '/\bwp_head\s*\([^)]*\)\s*;/i' => '<?php /* wp_head */ ?>',
            '/\bwp_footer\s*\([^)]*\)\s*;/i' => '<?php /* wp_footer */ ?>',
            
            // Language attributes
            '/\blanguage_attributes\s*\([^)]*\)/i' => 'lang="en"',
            
            // Body class
            '/\bbody_class\s*\([^)]*\)/i' => 'class="cms-body"',
            
            // WP Title
            '/\bwp_title\s*\([^)]*\)/i' => '($page[\'title\'] ?? $post[\'title\'] ?? \'Site Title\')',
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Add required variables at the top if WordPress functions were found
        if ($original !== $content || $this->isWordPress) {
            $header = "<?php\n";
            $header .= "/**\n";
            $header .= " * Converted Theme File\n";
            $header .= " * Auto-converted from " . ($this->isWordPress ? 'WordPress' : 'HTML') . " template\n";
            $header .= " */\n";
            $header .= "// Ensure required variables are set\n";
            $header .= "if (!isset(\$baseUrl)) {\n";
            $header .= "    \$baseUrl = '/abbis3.2';\n";
            $header .= "    if (defined('APP_URL')) {\n";
            $header .= "        \$parsed = parse_url(APP_URL);\n";
            $header .= "        \$baseUrl = \$parsed['path'] ?? '/abbis3.2';\n";
            $header .= "    }\n";
            $header .= "}\n";
            $header .= "if (!isset(\$pdo)) {\n";
            $header .= "    \$rootPath = dirname(dirname(dirname(__DIR__)));\n";
            $header .= "    require_once \$rootPath . '/config/app.php';\n";
            $header .= "    require_once \$rootPath . '/includes/functions.php';\n";
            $header .= "    \$pdo = getDBConnection();\n";
            $header .= "}\n";
            $header .= "if (!isset(\$siteTitle)) {\n";
            $header .= "    require_once __DIR__ . '/../../public/get-site-name.php';\n";
            $header .= "    \$siteTitle = getCMSSiteName('Site Title');\n";
            $header .= "}\n";
            if ($this->isWordPress) {
                $header .= "// Include WordPress compatibility layer for converted themes\n";
                $header .= "if (file_exists(__DIR__ . '/../../wp-compatibility.php')) {\n";
                $header .= "    require_once __DIR__ . '/../../wp-compatibility.php';\n";
                $header .= "}\n";
            }
            $header .= "?>\n\n";
            
            // Insert header if file doesn't start with PHP
            if (!preg_match('/^<\?php/i', trim($content))) {
                $content = $header . $content;
            } else {
                // Insert after opening PHP tag
                $content = preg_replace('/(<\?php\s*)/i', '$1' . substr($header, 5), $content, 1);
            }
        }
        
        return $content;
    }
    
    /**
     * Convert HTML file to PHP
     */
    private function convertHtmlFile($filePath) {
        $content = file_get_contents($filePath);
        
        // Convert common HTML patterns to CMS variables
        $replacements = [
            // Title placeholders
            '/<title>([^<]*)<\/title>/i' => '<title><?php echo htmlspecialchars($page[\'title\'] ?? $siteTitle ?? \'$1\'); ?></title>',
            
            // H1-H6 with common title patterns
            '/<h1>([^<]*)\{\{.*?title.*?\}\}([^<]*)<\/h1>/i' => '<h1>$1<?php echo htmlspecialchars($page[\'title\'] ?? \'$2\'); ?></h1>',
            
            // Common content placeholders
            '/\{\{\s*content\s*\}\}/i' => '<?php echo $page[\'content\'] ?? $post[\'content\'] ?? \'\'; ?>',
            '/\{\{\s*title\s*\}\}/i' => '<?php echo htmlspecialchars($page[\'title\'] ?? $post[\'title\'] ?? \'\'); ?>',
            '/\{\{\s*description\s*\}\}/i' => '<?php echo htmlspecialchars($siteTagline ?? \'\'); ?>',
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Add PHP header
        $header = "<?php\n";
        $header .= "/**\n";
        $header .= " * Converted Theme File\n";
        $header .= " * Auto-converted from HTML template\n";
        $header .= " */\n";
        $header .= "// Ensure required variables are set\n";
        $header .= "if (!isset(\$baseUrl)) {\n";
        $header .= "    \$baseUrl = '/abbis3.2';\n";
        $header .= "    if (defined('APP_URL')) {\n";
        $header .= "        \$parsed = parse_url(APP_URL);\n";
        $header .= "        \$baseUrl = \$parsed['path'] ?? '/abbis3.2';\n";
        $header .= "    }\n";
        $header .= "}\n";
        $header .= "if (!isset(\$siteTitle)) {\n";
        $header .= "    require_once __DIR__ . '/../../public/get-site-name.php';\n";
        $header .= "    \$siteTitle = getCMSSiteName('Site Title');\n";
        $header .= "}\n";
        $header .= "?>\n\n";
        
        // Wrap in PHP if not already
        if (!preg_match('/^<\?php/i', trim($content))) {
            $content = $header . $content;
        }
        
        // Ensure header and footer includes
        if (stripos($content, 'get_header') === false && stripos($content, 'include.*header') === false) {
            // Try to find body tag and add header before it
            if (preg_match('/(<body[^>]*>)/i', $content)) {
                $content = preg_replace('/(<body[^>]*>)/i', "<?php include __DIR__ . '/../../public/header.php'; ?>\n$1", $content);
            }
        }
        
        if (stripos($content, 'get_footer') === false && stripos($content, 'include.*footer') === false) {
            // Try to find closing body tag and add footer before it
            if (preg_match('/(<\/body>)/i', $content)) {
                $content = preg_replace('/(<\/body>)/i', "<?php include __DIR__ . '/../../public/footer.php'; ?>\n$1", $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Create style.css header if needed
     */
    private function createStyleHeader($themeDir, $themeInfo) {
        $stylePath = $themeDir . '/style.css';
        
        if (!file_exists($stylePath)) {
            // Create minimal style.css
            $header = "/*\n";
            $header .= "Theme Name: {$themeInfo['name']}\n";
            $header .= "Description: {$themeInfo['description']}\n";
            $header .= "Version: {$themeInfo['version']}\n";
            if ($themeInfo['author']) {
                $header .= "Author: {$themeInfo['author']}\n";
            }
            $header .= "*/\n\n";
            $header .= "/* Theme styles */\n";
            
            file_put_contents($stylePath, $header);
        } else {
            // Ensure it has proper header
            $content = file_get_contents($stylePath);
            if (!preg_match('/Theme Name:/i', $content)) {
                $header = "/*\n";
                $header .= "Theme Name: {$themeInfo['name']}\n";
                $header .= "Description: {$themeInfo['description']}\n";
                $header .= "Version: {$themeInfo['version']}\n";
                if ($themeInfo['author']) {
                    $header .= "Author: {$themeInfo['author']}\n";
                }
                $header .= "*/\n\n";
                $content = $header . $content;
                file_put_contents($stylePath, $content);
            }
        }
    }
    
    /**
     * Create index.php if needed
     */
    private function createIndexFile($themeDir, $themeRoot) {
        $indexPath = $themeDir . '/index.php';
        
        if (!file_exists($indexPath)) {
            // Try to find existing index file
            $existing = null;
            foreach (['index.php', 'index.html', 'home.php', 'home.html'] as $file) {
                if (file_exists($themeRoot . '/' . $file)) {
                    $existing = $themeRoot . '/' . $file;
                    break;
                }
            }
            
            if ($existing) {
                // Convert existing file
                $ext = pathinfo($existing, PATHINFO_EXTENSION);
                if ($ext === 'php') {
                    $content = $this->convertPhpFile($existing);
                } else {
                    $content = $this->convertHtmlFile($existing);
                }
                file_put_contents($indexPath, $content);
            } else {
                // Create basic index.php
                $content = <<<'PHP'
<?php
/**
 * Theme Homepage Template
 */
if (!isset($baseUrl)) {
    $baseUrl = '/abbis3.2';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        $baseUrl = $parsed['path'] ?? '/abbis3.2';
    }
}
if (!isset($siteTitle)) {
    require_once __DIR__ . '/../../public/get-site-name.php';
    $siteTitle = getCMSSiteName('Site Title');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/cms/themes/<?php echo basename(__DIR__); ?>/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../public/header.php'; ?>
    
    <main>
        <h1><?php echo htmlspecialchars($page['title'] ?? $siteTitle); ?></h1>
        <div>
            <?php echo $page['content'] ?? ''; ?>
        </div>
    </main>
    
    <?php include __DIR__ . '/../../public/footer.php'; ?>
</body>
</html>
PHP;
                file_put_contents($indexPath, $content);
            }
            
            $this->filesConverted++;
            $this->conversions[] = 'Created index.php template';
        }
    }
    
    /**
     * Install theme to database
     */
    private function installTheme($themeInfo, $themeSlug) {
        // Check if version column exists
        $versionColExists = false;
        try {
            $colCheck = $this->pdo->query("SHOW COLUMNS FROM cms_themes LIKE 'version'");
            $versionColExists = $colCheck->rowCount() > 0;
        } catch (Exception $e) {}
        
        // Default config
        $defaultConfig = json_encode([
            'primary_color' => '#0ea5e9',
            'secondary_color' => '#64748b'
        ]);
        
        // Check if already exists
        $exists = $this->pdo->prepare("SELECT id FROM cms_themes WHERE slug=?");
        $exists->execute([$themeSlug]);
        
        if (!$exists->fetch()) {
            if ($versionColExists) {
                $this->pdo->prepare("INSERT INTO cms_themes (name, slug, description, version, config, is_active) VALUES (?, ?, ?, ?, ?, 0)")
                    ->execute([$themeInfo['name'], $themeSlug, $themeInfo['description'], $themeInfo['version'], $defaultConfig]);
            } else {
                $this->pdo->prepare("INSERT INTO cms_themes (name, slug, description, config, is_active) VALUES (?, ?, ?, ?, 0)")
                    ->execute([$themeInfo['name'], $themeSlug, $themeInfo['description'], $defaultConfig]);
            }
        }
    }
    
    /**
     * Helper: Convert string to slug
     */
    private function slugify($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'theme';
    }
    
    /**
     * Cleanup temp files
     */
    private function cleanup() {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir($this->tempDir);
        }
    }
}
