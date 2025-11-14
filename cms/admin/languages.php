<?php
/**
 * CMS Admin - Multi-language Support (Joomla/Drupal-inspired)
 * Manage languages and translations
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$langId = $_GET['lang_id'] ?? null;
$message = null;
$messageType = 'success';

// Ensure tables exist
require_once dirname(__DIR__) . '/includes/ensure-advanced-tables.php';
if (!ensureAdvancedTablesExist($pdo)) {
    die("❌ Error: Could not create required database tables. Please run: php database/create_advanced_features_tables.php");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_language'])) {
        $code = strtolower(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $nativeName = trim($_POST['native_name'] ?? '');
        $flag = $_POST['flag'] ?? '';
        $rtl = isset($_POST['rtl']) ? 1 : 0;
        $defaultLanguage = isset($_POST['default_language']) ? 1 : 0;
        
        if ($code && $name && $nativeName) {
            // If setting as default, unset others
            if ($defaultLanguage) {
                $pdo->exec("UPDATE cms_languages SET default_language = 0");
            }
            
            if ($langId) {
                $stmt = $pdo->prepare("UPDATE cms_languages SET name=?, native_name=?, flag=?, rtl=?, default_language=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$name, $nativeName, $flag, $rtl, $defaultLanguage, $langId]);
                $message = 'Language updated successfully';
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM cms_languages WHERE code=? LIMIT 1");
                $checkStmt->execute([$code]);
                if ($checkStmt->fetch()) {
                    $message = 'Language with this code already exists';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cms_languages (code, name, native_name, flag, rtl, default_language) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$code, $name, $nativeName, $flag, $rtl, $defaultLanguage]);
                    $message = 'Language added successfully';
                }
            }
        }
    }
    
    if (isset($_POST['delete_language'])) {
        $pdo->prepare("DELETE FROM cms_languages WHERE id=?")->execute([$langId]);
        header('Location: languages.php');
        exit;
    }
}

// Get languages
$languages = $pdo->query("SELECT * FROM cms_languages ORDER BY default_language DESC, name")->fetchAll(PDO::FETCH_ASSOC);

// Get single language for editing
$language = null;
if ($langId && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_languages WHERE id=?");
    $stmt->execute([$langId]);
    $language = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/header.php';
?>

<style>
    .lang-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .lang-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .lang-flag {
        font-size: 2.5rem;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>🌍 Multi-language Support</h1>
        <p>Manage languages and translations (Joomla/Drupal-inspired)</p>
        <div class="admin-page-actions">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add Language</a>
            <?php else: ?>
                <a href="?" class="admin-btn admin-btn-outline">← Back to List</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-notice admin-notice-<?php echo $messageType; ?>">
            <span class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠' : '✓'; ?></span>
            <div class="admin-notice-content">
                <strong><?php echo $messageType === 'error' ? 'Error' : 'Success'; ?>!</strong>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <div style="margin-top: 2rem;">
            <?php if (empty($languages)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🌍</div>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No Languages Configured</h3>
                    <p style="color: #94a3b8; margin-bottom: 2rem;">Add languages to enable multilingual content</p>
                    <a href="?action=add" class="admin-btn admin-btn-primary">➕ Add Language</a>
                </div>
            <?php else: ?>
                <?php foreach ($languages as $lang): ?>
                    <div class="lang-card">
                        <div class="lang-info">
                            <div class="lang-flag"><?php echo htmlspecialchars($lang['flag']); ?></div>
                            <div>
                                <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; color: #1e293b;">
                                    <?php echo htmlspecialchars($lang['name']); ?>
                                    <?php if ($lang['default_language']): ?>
                                        <span style="background: #d1fae5; color: #065f46; padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">Default</span>
                                    <?php endif; ?>
                                </h3>
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <code><?php echo htmlspecialchars($lang['code']); ?></code>
                                    <span style="margin-left: 1rem;">• <?php echo htmlspecialchars($lang['native_name']); ?></span>
                                    <?php if ($lang['rtl']): ?>
                                        <span style="margin-left: 1rem;">• RTL</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?action=edit&lang_id=<?php echo $lang['id']; ?>" class="admin-btn admin-btn-outline">✏️ Edit</a>
                            <?php if (!$lang['default_language']): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this language?');">
                                    <input type="hidden" name="delete_language" value="1">
                                    <input type="hidden" name="lang_id" value="<?php echo $lang['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <form method="post" style="margin-top: 2rem;">
            <div class="editor-section">
                <div class="editor-section-header">
                    <div class="icon">🌍</div>
                    <h3>Language Information</h3>
                </div>
                
                <div class="admin-form-group">
                    <label>Language Code (ISO 639-1) *</label>
                    <input type="text" name="code" value="<?php echo htmlspecialchars($language['code'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., en, fr, es" maxlength="10"
                           <?php echo $language ? 'readonly' : ''; ?>>
                    <div class="admin-form-help">Two-letter language code (e.g., en, fr, es, de)</div>
                </div>
                
                <div class="admin-form-group">
                    <label>English Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($language['name'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., English, French">
                </div>
                
                <div class="admin-form-group">
                    <label>Native Name *</label>
                    <input type="text" name="native_name" value="<?php echo htmlspecialchars($language['native_name'] ?? ''); ?>" 
                           required class="large-text" placeholder="e.g., English, Français">
                </div>
                
                <div class="admin-form-group">
                    <label>Flag Emoji</label>
                    <input type="text" name="flag" value="<?php echo htmlspecialchars($language['flag'] ?? ''); ?>" 
                           class="large-text" placeholder="🇬🇧" maxlength="2">
                    <div class="admin-form-help">Flag emoji or country code</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="admin-form-group">
                        <label>
                            <input type="checkbox" name="rtl" value="1" <?php echo ($language['rtl'] ?? 0) ? 'checked' : ''; ?>>
                            Right-to-Left (RTL) Language
                        </label>
                    </div>
                    <div class="admin-form-group">
                        <label>
                            <input type="checkbox" name="default_language" value="1" <?php echo ($language['default_language'] ?? 0) ? 'checked' : ''; ?>>
                            Default Language
                        </label>
                    </div>
                </div>
                
                <?php if ($language): ?>
                    <input type="hidden" name="lang_id" value="<?php echo $language['id']; ?>">
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="save_language" class="admin-btn admin-btn-primary">💾 Save Language</button>
                    <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

