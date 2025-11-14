<?php
/**
 * Documentation Hub
 * Centralized documentation viewer for all system documentation
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$page_title = 'Documentation';

// Get all markdown files from docs directory
function scanDocsDirectory($dir, $basePath = '') {
    $files = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $dir . '/' . $item;
        $relativePath = $basePath ? $basePath . '/' . $item : $item;
        
        if (is_dir($fullPath)) {
            // Recursively scan subdirectories
            $subFiles = scanDocsDirectory($fullPath, $relativePath);
            $files = array_merge($files, $subFiles);
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'md') {
            // Get file metadata
            $content = file_get_contents($fullPath);
            $title = extractTitleFromMarkdown($content);
            $description = extractDescriptionFromMarkdown($content);
            $category = determineCategory($relativePath);
            
            $files[] = [
                'path' => $relativePath,
                'full_path' => $fullPath,
                'name' => $item,
                'title' => $title ?: str_replace(['.md', '-', '_'], ['', ' ', ' '], $item),
                'description' => $description,
                'category' => $category,
                'size' => filesize($fullPath),
                'modified' => filemtime($fullPath),
            ];
        }
    }
    
    return $files;
}

function extractTitleFromMarkdown($content) {
    // Extract first H1 or H2 title
    if (preg_match('/^#+\s+(.+)$/m', $content, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function extractDescriptionFromMarkdown($content) {
    // Extract first paragraph after title
    $lines = explode("\n", $content);
    $inParagraph = false;
    $paragraph = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (preg_match('/^#+\s+/', $line)) continue; // Skip headers
        if (preg_match('/^[-*+]\s+/', $line)) continue; // Skip list items
        if (preg_match('/^```/', $line)) continue; // Skip code blocks
        
        if (strlen($line) > 20) {
            $paragraph = $line;
            break;
        }
    }
    
    return $paragraph ? substr($paragraph, 0, 200) : null;
}

function determineCategory($path) {
    $pathLower = strtolower($path);
    
    if (strpos($pathLower, 'guide') !== false || strpos($pathLower, 'setup') !== false) {
        return 'Guides & Setup';
    } elseif (strpos($pathLower, 'implementation') !== false || strpos($pathLower, 'complete') !== false) {
        return 'Implementation';
    } elseif (strpos($pathLower, 'analysis') !== false || strpos($pathLower, 'report') !== false || strpos($pathLower, 'status') !== false) {
        return 'Reports & Analysis';
    } elseif (strpos($pathLower, 'cms') !== false) {
        return 'CMS Guides';
    } elseif (strpos($pathLower, 'pos') !== false) {
        return 'POS Documentation';
    } elseif (strpos($pathLower, 'ai') !== false || strpos($pathLower, 'assistant') !== false) {
        return 'AI & Automation';
    } elseif (strpos($pathLower, 'accounting') !== false || strpos($pathLower, 'financial') !== false) {
        return 'Accounting & Finance';
    } elseif (strpos($pathLower, 'integration') !== false || strpos($pathLower, 'client_portal') !== false) {
        return 'Integrations';
    } elseif (strpos($pathLower, 'security') !== false || strpos($pathLower, 'iam') !== false || strpos($pathLower, 'admin') !== false) {
        return 'Security & Admin';
    } else {
        return 'General';
    }
}

// Scan docs directory
$docsDir = __DIR__ . '/../docs';
$allDocs = scanDocsDirectory($docsDir);

// Group by category
$docsByCategory = [];
foreach ($allDocs as $doc) {
    $category = $doc['category'];
    if (!isset($docsByCategory[$category])) {
        $docsByCategory[$category] = [];
    }
    $docsByCategory[$category][] = $doc;
}

// Sort categories
ksort($docsByCategory);

// Sort docs within each category by title
foreach ($docsByCategory as &$docs) {
    usort($docs, function($a, $b) {
        return strcasecmp($a['title'], $b['title']);
    });
}

// Get selected document
$selectedDoc = null;
$selectedPath = $_GET['doc'] ?? null;

if ($selectedPath) {
    $fullPath = $docsDir . '/' . $selectedPath;
    if (file_exists($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'md') {
        $selectedDoc = [
            'path' => $selectedPath,
            'content' => file_get_contents($fullPath),
            'title' => extractTitleFromMarkdown(file_get_contents($fullPath)) ?: basename($selectedPath),
        ];
    }
}

require_once '../includes/header.php';
?>

<style>
.documentation-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

.docs-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
    margin-top: 24px;
}

.docs-sidebar {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    height: fit-content;
    position: sticky;
    top: 80px;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
}

.docs-sidebar h3 {
    margin: 0 0 16px 0;
    font-size: 18px;
    color: var(--text);
    border-bottom: 2px solid var(--border);
    padding-bottom: 12px;
}

.category-section {
    margin-bottom: 24px;
}

.category-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.doc-link {
    display: block;
    padding: 10px 12px;
    margin-bottom: 4px;
    border-radius: 6px;
    text-decoration: none;
    color: var(--text);
    font-size: 14px;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.doc-link:hover {
    background: var(--bg);
    border-left-color: var(--primary);
    color: var(--primary);
}

.doc-link.active {
    background: var(--primary);
    color: white;
    border-left-color: var(--primary);
}

.doc-link.active:hover {
    background: var(--primary-hover, var(--primary));
}

.docs-content {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px;
    min-height: 600px;
}

.docs-content h1 {
    margin-top: 0;
    color: var(--text);
    border-bottom: 3px solid var(--primary);
    padding-bottom: 16px;
    margin-bottom: 24px;
}

.docs-content h2 {
    margin-top: 32px;
    margin-bottom: 16px;
    color: var(--text);
    border-bottom: 2px solid var(--border);
    padding-bottom: 8px;
}

.docs-content h3 {
    margin-top: 24px;
    margin-bottom: 12px;
    color: var(--text);
}

.docs-content pre {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 16px;
    overflow-x: auto;
    font-size: 13px;
}

.docs-content code {
    background: var(--bg);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 13px;
    font-family: 'Courier New', monospace;
}

.docs-content pre code {
    background: transparent;
    padding: 0;
}

.docs-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0;
}

.docs-content table th,
.docs-content table td {
    padding: 12px;
    border: 1px solid var(--border);
    text-align: left;
}

.docs-content table th {
    background: var(--bg);
    font-weight: 600;
}

.docs-content ul,
.docs-content ol {
    margin: 16px 0;
    padding-left: 24px;
}

.docs-content li {
    margin: 8px 0;
}

.docs-content blockquote {
    border-left: 4px solid var(--primary);
    padding-left: 16px;
    margin: 16px 0;
    color: var(--secondary);
    font-style: italic;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--secondary);
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.empty-state h2 {
    margin: 0 0 8px 0;
    color: var(--text);
}

.search-box {
    margin-bottom: 20px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 10px 12px 10px 40px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text);
    font-size: 14px;
}

.search-box::before {
    content: '🔍';
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
}

.stats-bar {
    display: flex;
    gap: 24px;
    margin-bottom: 24px;
    padding: 16px;
    background: var(--bg);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.stat-item {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

.stat-label {
    font-size: 12px;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 968px) {
    .docs-layout {
        grid-template-columns: 1fr;
    }
    
    .docs-sidebar {
        position: relative;
        top: 0;
        max-height: 400px;
    }
}
</style>

<div class="documentation-page">
    <div class="page-header">
        <div>
            <h1>📚 Documentation Hub</h1>
            <p>Complete system documentation, guides, and reference materials</p>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-value"><?php echo count($allDocs); ?></div>
            <div class="stat-label">Total Documents</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo count($docsByCategory); ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo number_format(array_sum(array_column($allDocs, 'size')) / 1024, 1); ?> KB</div>
            <div class="stat-label">Total Size</div>
        </div>
    </div>

    <div class="docs-layout">
        <div class="docs-sidebar">
            <div class="search-box">
                <input type="text" id="docSearch" placeholder="Search documentation..." onkeyup="filterDocs()">
            </div>
            
            <h3>Documentation</h3>
            
            <?php foreach ($docsByCategory as $category => $docs): ?>
                <div class="category-section">
                    <h4 class="category-title"><?php echo e($category); ?></h4>
                    <?php foreach ($docs as $doc): ?>
                        <a href="?doc=<?php echo urlencode($doc['path']); ?>" 
                           class="doc-link <?php echo ($selectedPath === $doc['path']) ? 'active' : ''; ?>"
                           data-title="<?php echo strtolower(e($doc['title'])); ?>"
                           data-category="<?php echo strtolower(e($category)); ?>">
                            <?php echo e($doc['title']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="docs-content">
            <?php if ($selectedDoc): ?>
                <?php
                // Better Markdown to HTML conversion
                function markdownToHtml($markdown) {
                    $html = $markdown;
                    
                    // Remove title if it's the first H1 (we display it separately)
                    $html = preg_replace('/^#\s+(.+)$/m', '', $html, 1);
                    
                    // Code blocks (must be done before other replacements)
                    $html = preg_replace_callback('/```(\w+)?\n(.*?)```/s', function($matches) {
                        $lang = $matches[1] ?? '';
                        $code = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
                        return '<pre><code class="language-' . $lang . '">' . $code . '</code></pre>';
                    }, $html);
                    
                    // Inline code
                    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
                    
                    // Headers
                    $html = preg_replace('/^###### (.*)$/m', '<h6>$1</h6>', $html);
                    $html = preg_replace('/^##### (.*)$/m', '<h5>$1</h5>', $html);
                    $html = preg_replace('/^#### (.*)$/m', '<h4>$1</h4>', $html);
                    $html = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $html);
                    $html = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $html);
                    $html = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $html);
                    
                    // Bold and italic
                    $html = preg_replace('/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
                    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
                    $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
                    
                    // Links
                    $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);
                    
                    // Images
                    $html = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1">', $html);
                    
                    // Horizontal rules
                    $html = preg_replace('/^---$/m', '<hr>', $html);
                    $html = preg_replace('/^\*\*\*$/m', '<hr>', $html);
                    
                    // Blockquotes
                    $html = preg_replace('/^>\s+(.*)$/m', '<blockquote>$1</blockquote>', $html);
                    
                    // Tables
                    $lines = explode("\n", $html);
                    $inTable = false;
                    $tableRows = [];
                    $result = [];
                    
                    foreach ($lines as $line) {
                        if (preg_match('/^\|(.+)\|$/', $line)) {
                            if (!$inTable) {
                                $inTable = true;
                                $tableRows = [];
                            }
                            $cells = array_map('trim', explode('|', $line));
                            array_shift($cells);
                            array_pop($cells);
                            
                            if (preg_match('/^:?-+:?$/', implode('', $cells))) {
                                continue;
                            }
                            
                            $tableRows[] = $cells;
                        } else {
                            if ($inTable) {
                                if (!empty($tableRows)) {
                                    $result[] = '<table><thead><tr>';
                                    foreach ($tableRows[0] as $cell) {
                                        $result[] = '<th>' . trim($cell) . '</th>';
                                    }
                                    $result[] = '</tr></thead><tbody>';
                                    
                                    for ($i = 1; $i < count($tableRows); $i++) {
                                        $result[] = '<tr>';
                                        foreach ($tableRows[$i] as $cell) {
                                            $result[] = '<td>' . trim($cell) . '</td>';
                                        }
                                        $result[] = '</tr>';
                                    }
                                    $result[] = '</tbody></table>';
                                }
                                $tableRows = [];
                                $inTable = false;
                            }
                            $result[] = $line;
                        }
                    }
                    
                    if ($inTable && !empty($tableRows)) {
                        $result[] = '<table><thead><tr>';
                        foreach ($tableRows[0] as $cell) {
                            $result[] = '<th>' . trim($cell) . '</th>';
                        }
                        $result[] = '</tr></thead><tbody>';
                        
                        for ($i = 1; $i < count($tableRows); $i++) {
                            $result[] = '<tr>';
                            foreach ($tableRows[$i] as $cell) {
                                $result[] = '<td>' . trim($cell) . '</td>';
                            }
                            $result[] = '</tr>';
                        }
                        $result[] = '</tbody></table>';
                    }
                    
                    $html = implode("\n", $result);
                    
                    // Lists
                    $html = preg_replace('/^(\d+)\.\s+(.*)$/m', '<li>$2</li>', $html);
                    $html = preg_replace('/^[-*+]\s+(.*)$/m', '<li>$1</li>', $html);
                    $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);
                    
                    // Paragraphs - wrap lines that aren't already HTML tags
                    // Match lines that don't start with HTML tags (opening or closing)
                    if ($html !== null && $html !== '') {
                        // Match lines that don't start with HTML tags
                        $html = preg_replace('/^(?!<(\/?)(h[1-6]|ul|ol|p|div|table|blockquote|script|img|a|em|strong|hr|pre|code|li|th|td|tr|thead|tbody|tfoot))(.+)$/m', '<p>$3</p>', $html);
                        // Remove empty paragraphs
                        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
                        // Remove paragraph tags that wrap other HTML elements
                        $html = preg_replace('/<p>(<(h[1-6]|ul|ol|div|table|blockquote|script|img|a|em|strong|hr|pre|code|li|th|td|tr|thead|tbody|tfoot))/', '$1', $html);
                        $html = preg_replace('/(<\/(h[1-6]|ul|ol|div|table|blockquote|script|img|a|em|strong|hr|pre|code|li|th|td|tr|thead|tbody|tfoot)>)<\/p>/', '$1', $html);
                    }
                    
                    return $html ?: '';
                }
                
                $content = markdownToHtml($selectedDoc['content']);
                ?>
                <h1><?php echo e($selectedDoc['title']); ?></h1>
                <div class="markdown-content">
                    <?php echo $content; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📖</div>
                    <h2>Welcome to Documentation Hub</h2>
                    <p>Select a document from the sidebar to view its contents.</p>
                    <p style="margin-top: 16px; color: var(--secondary);">
                        Browse by category or use the search box to find specific documentation.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterDocs() {
    const searchTerm = document.getElementById('docSearch').value.toLowerCase();
    const docLinks = document.querySelectorAll('.doc-link');
    
    docLinks.forEach(link => {
        const title = link.getAttribute('data-title');
        const category = link.getAttribute('data-category');
        const matches = title.includes(searchTerm) || category.includes(searchTerm);
        
        link.style.display = matches ? 'block' : 'none';
    });
    
    // Hide/show category sections
    document.querySelectorAll('.category-section').forEach(section => {
        const visibleLinks = section.querySelectorAll('.doc-link[style="display: block"], .doc-link:not([style*="display: none"])');
        section.style.display = visibleLinks.length > 0 ? 'block' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
