<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle logo upload
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $rootPath . '/uploads/site/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file = $_FILES['logo_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (in_array($ext, $allowed) && $file['size'] <= 5000000) {
            $filename = 'logo.' . $ext;
            $filepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $_POST['setting_site_logo'] = 'uploads/site/' . $filename;
            }
        }
    }
    
    // Handle icon upload
    if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $rootPath . '/uploads/site/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file = $_FILES['icon_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg'];
        if (in_array($ext, $allowed) && $file['size'] <= 1000000) {
            $filename = 'icon.' . $ext;
            $filepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $_POST['setting_site_icon'] = 'uploads/site/' . $filename;
            }
        }
    }
    
    // Handle hero banner image upload
    if (isset($_FILES['hero_banner_image']) && $_FILES['hero_banner_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $rootPath . '/uploads/site/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file = $_FILES['hero_banner_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $file['size'] <= 10000000) {
            $filename = 'hero-banner.' . $ext;
            $filepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $_POST['setting_hero_banner_image'] = 'uploads/site/' . $filename;
            }
        }
    }
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = str_replace('setting_', '', $key);
            // Handle checkboxes - if not checked, they won't be in POST, so set to '0'
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->execute([$settingKey, $value, $value]);
        }
    }
    // Handle unchecked checkboxes - set them to '0'
    $checkboxSettings = ['membership', 'default_pingback', 'default_pingback_status', 'allow_comments', 'require_moderation', 'comment_registration', 'discourage_search', 'low_stock_notify', 'payment_test_mode', 'enable_shipping', 'enable_taxes', 'prices_include_tax', 'email_new_order', 'email_completed_order'];
    foreach ($checkboxSettings as $setting) {
        if (!isset($_POST['setting_' . $setting])) {
            $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->execute([$setting, '0', '0']);
        }
    }
    
    // Handle hero image removal
    if (isset($_POST['remove_hero_image']) && $_POST['remove_hero_image']) {
        $oldImagePath = $settings['hero_banner_image'] ?? '';
        if ($oldImagePath) {
            $oldFilePath = $rootPath . '/' . $oldImagePath;
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }
        $stmt = $pdo->prepare("DELETE FROM cms_settings WHERE setting_key='hero_banner_image'");
        $stmt->execute();
    }
    
    // Sync payment gateway keys to payment methods
    try {
        // Reload settings to get latest values
        $reloadSettings = [];
        $reloadStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
        while ($row = $reloadStmt->fetch()) {
            $reloadSettings[$row['setting_key']] = $row['setting_value'];
        }
        
        $testMode = ($reloadSettings['payment_test_mode'] ?? '0') === '1';
        
        // Update Paystack payment method config
        $paystackPublicKey = $reloadSettings['paystack_public_key'] ?? '';
        $paystackSecretKey = $reloadSettings['paystack_secret_key'] ?? '';
        if (!empty($paystackPublicKey) || !empty($paystackSecretKey)) {
            $paystackConfig = [
                'public_key' => $paystackPublicKey,
                'secret_key' => $paystackSecretKey,
                'test_mode' => $testMode,
                'instructions' => 'Pay securely with your card via Paystack.'
            ];
            $paystackConfigJson = json_encode($paystackConfig);
            $pdo->prepare("UPDATE cms_payment_methods SET config=? WHERE provider='paystack'")
                ->execute([$paystackConfigJson]);
        }
        
        // Update Flutterwave payment method config
        $flutterwavePublicKey = $reloadSettings['flutterwave_public_key'] ?? '';
        $flutterwaveSecretKey = $reloadSettings['flutterwave_secret_key'] ?? '';
        if (!empty($flutterwavePublicKey) || !empty($flutterwaveSecretKey)) {
            $flutterwaveConfig = [
                'public_key' => $flutterwavePublicKey,
                'secret_key' => $flutterwaveSecretKey,
                'test_mode' => $testMode,
                'instructions' => 'Pay securely with your card via Flutterwave.'
            ];
            $flutterwaveConfigJson = json_encode($flutterwaveConfig);
            $pdo->prepare("UPDATE cms_payment_methods SET config=? WHERE provider='flutterwave'")
                ->execute([$flutterwaveConfigJson]);
        }
    } catch (PDOException $e) {
        // Payment methods table might not exist yet, ignore
        error_log("Failed to sync payment keys: " . $e->getMessage());
    }
    
    $message = 'Settings saved successfully';
}

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$siteTitle = $settings['site_title'] ?? $companyName;
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'settings';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Settings</h1>
        
        <?php if (isset($message)): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <form method="post" class="settings-form" enctype="multipart/form-data">
            <div class="settings-tabs">
                <a href="#general" class="tab-link active" onclick="showTab(event, 'general')">General</a>
                <a href="#homepage" class="tab-link" onclick="showTab(event, 'homepage')">Homepage</a>
                <a href="#reading" class="tab-link" onclick="showTab(event, 'reading')">Reading</a>
                <a href="#discussion" class="tab-link" onclick="showTab(event, 'discussion')">Discussion</a>
                <a href="#ecommerce" class="tab-link" onclick="showTab(event, 'ecommerce')">E-Commerce</a>
                <a href="#email" class="tab-link" onclick="showTab(event, 'email')">Email</a>
                <a href="#payment" class="tab-link" onclick="showTab(event, 'payment')">Payment</a>
                <a href="#shipping" class="tab-link" onclick="showTab(event, 'shipping')">Shipping</a>
                <a href="#tax" class="tab-link" onclick="showTab(event, 'tax')">Tax</a>
                <a href="#permalinks" class="tab-link" onclick="showTab(event, 'permalinks')">Permalinks</a>
            </div>
            
            <!-- General Settings -->
            <div id="general" class="tab-content active">
                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Site Title</label></th>
                        <td>
                            <input type="text" name="setting_site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" class="large-text" placeholder="Enter your site name">
                            <p class="description">This will be displayed in the website header. Leave empty to use company name from ABBIS system.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Site Tagline</label></th>
                        <td>
                            <input type="text" name="setting_site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? ''); ?>" class="large-text" placeholder="Just another ABBIS CMS site">
                            <p class="description">In a few words, explain what this site is about.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Site Logo</label></th>
                        <td>
                            <?php
                            $logoPath = $settings['site_logo'] ?? '';
                            $logoUrl = $logoPath ? $baseUrl . '/' . $logoPath : '';
                            ?>
                            <?php if ($logoUrl): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Site Logo" style="max-width: 300px; max-height: 100px; border: 1px solid #c3c4c7; padding: 5px; background: white;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="logo_file" accept="image/*" onchange="previewLogo(this)">
                            <input type="hidden" name="setting_site_logo" id="site_logo" value="<?php echo htmlspecialchars($logoPath); ?>">
                            <p class="description">Upload a logo for your site. Recommended size: 300x100px or similar aspect ratio.</p>
                            <div id="logo-preview" style="margin-top: 10px; display: none;">
                                <img id="logo-preview-img" src="" alt="Logo Preview" style="max-width: 300px; max-height: 100px; border: 1px solid #c3c4c7; padding: 5px; background: white;">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Site Icon (Favicon)</label></th>
                        <td>
                            <?php
                            $iconPath = $settings['site_icon'] ?? '';
                            $iconUrl = $iconPath ? $baseUrl . '/' . $iconPath : '';
                            ?>
                            <?php if ($iconUrl): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo htmlspecialchars($iconUrl); ?>" alt="Site Icon" style="width: 32px; height: 32px; border: 1px solid #c3c4c7; padding: 2px; background: white;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="icon_file" accept="image/*" onchange="previewIcon(this)">
                            <input type="hidden" name="setting_site_icon" id="site_icon" value="<?php echo htmlspecialchars($iconPath); ?>">
                            <p class="description">Upload a favicon (32x32px recommended).</p>
                            <div id="icon-preview" style="margin-top: 10px; display: none;">
                                <img id="icon-preview-img" src="" alt="Icon Preview" style="width: 32px; height: 32px; border: 1px solid #c3c4c7; padding: 2px; background: white;">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Site URL</label></th>
                        <td>
                            <input type="url" name="setting_site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>" class="large-text" placeholder="https://example.com">
                            <p class="description">The WordPress address (URL) and Site address (URL) are the same.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Admin Email</label></th>
                        <td>
                            <input type="email" name="setting_admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>" class="regular-text">
                            <p class="description">This address is used for admin purposes, like new user notification.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Membership</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_membership" value="1" <?php echo ($settings['membership'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                Anyone can register
                            </label>
                            <p class="description">Allow visitors to register on your site.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Default User Role</label></th>
                        <td>
                            <select name="setting_default_role">
                                <option value="subscriber" <?php echo ($settings['default_role'] ?? 'subscriber') === 'subscriber' ? 'selected' : ''; ?>>Subscriber</option>
                                <option value="author" <?php echo ($settings['default_role'] ?? '') === 'author' ? 'selected' : ''; ?>>Author</option>
                                <option value="editor" <?php echo ($settings['default_role'] ?? '') === 'editor' ? 'selected' : ''; ?>>Editor</option>
                            </select>
                            <p class="description">New users will be assigned this role.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Timezone</label></th>
                        <td>
                            <select name="setting_timezone">
                                <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="Africa/Accra" <?php echo ($settings['timezone'] ?? '') === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra (GMT+0)</option>
                                <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (GMT-5)</option>
                                <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT+0)</option>
                            </select>
                            <p class="description">Choose a city in the same timezone as you.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Date Format</label></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="setting_date_format" value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'checked' : ''; ?>> 2025-11-03</label><br>
                                <label><input type="radio" name="setting_date_format" value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'checked' : ''; ?>> 11/03/2025</label><br>
                                <label><input type="radio" name="setting_date_format" value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'checked' : ''; ?>> 03/11/2025</label><br>
                                <label><input type="radio" name="setting_date_format" value="custom" <?php echo isset($settings['date_format_custom']) ? 'checked' : ''; ?>> Custom: <input type="text" name="setting_date_format_custom" value="<?php echo htmlspecialchars($settings['date_format_custom'] ?? 'Y-m-d'); ?>" class="small-text"></label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Time Format</label></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="setting_time_format" value="H:i" <?php echo ($settings['time_format'] ?? 'H:i') === 'H:i' ? 'checked' : ''; ?>> 14:30 (24-hour)</label><br>
                                <label><input type="radio" name="setting_time_format" value="g:i A" <?php echo ($settings['time_format'] ?? '') === 'g:i A' ? 'checked' : ''; ?>> 2:30 PM (12-hour)</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Week Starts On</label></th>
                        <td>
                            <select name="setting_start_of_week">
                                <option value="0" <?php echo ($settings['start_of_week'] ?? '0') === '0' ? 'selected' : ''; ?>>Sunday</option>
                                <option value="1" <?php echo ($settings['start_of_week'] ?? '') === '1' ? 'selected' : ''; ?>>Monday</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Homepage Settings -->
            <div id="homepage" class="tab-content">
                <h2>Homepage Hero Banner</h2>
                <p class="description">Configure the hero banner section that appears below the header on your homepage.</p>
                <table class="form-table">
                    <tr>
                        <th><label>Hero Banner Image</label></th>
                        <td>
                            <?php
                            $heroImagePath = $settings['hero_banner_image'] ?? '';
                            $heroImageUrl = $heroImagePath ? $baseUrl . '/' . $heroImagePath : '';
                            ?>
                            <?php if ($heroImageUrl): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo htmlspecialchars($heroImageUrl); ?>?v=<?php echo time(); ?>" alt="Hero Banner" style="max-width: 600px; height: auto; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="hero_banner_image" accept="image/*">
                            <p class="description">Upload a background image for the hero banner (recommended: 1920x800px or similar wide format). Max size: 10MB.</p>
                            <?php if ($heroImagePath): ?>
                                <p class="description" style="color: #d63638;">
                                    <input type="checkbox" name="remove_hero_image" value="1" id="remove_hero_image">
                                    <label for="remove_hero_image">Remove current image</label>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Hero Title</label></th>
                        <td>
                            <input type="text" name="setting_hero_title" value="<?php echo htmlspecialchars($settings['hero_title'] ?? ''); ?>" class="large-text" placeholder="<?php echo htmlspecialchars($siteTitle); ?>">
                            <p class="description">Main headline text displayed on the hero banner. Leave empty to use site title.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Hero Subtitle</label></th>
                        <td>
                            <input type="text" name="setting_hero_subtitle" value="<?php echo htmlspecialchars($settings['hero_subtitle'] ?? ''); ?>" class="large-text" placeholder="Drilling & Construction, Mechanization and more!">
                            <p class="description">Subtitle text displayed below the main headline.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Primary Button Text</label></th>
                        <td>
                            <input type="text" name="setting_hero_button1_text" value="<?php echo htmlspecialchars($settings['hero_button1_text'] ?? 'CALL US NOW'); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Primary Button Link</label></th>
                        <td>
                            <input type="text" name="setting_hero_button1_link" value="<?php echo htmlspecialchars($settings['hero_button1_link'] ?? 'tel:0248518513'); ?>" class="large-text" placeholder="tel:0248518513 or /cms/quote">
                            <p class="description">URL or phone number (use tel: for phone links)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Secondary Button Text</label></th>
                        <td>
                            <input type="text" name="setting_hero_button2_text" value="<?php echo htmlspecialchars($settings['hero_button2_text'] ?? 'WHATSAPP US'); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Secondary Button Link</label></th>
                        <td>
                            <input type="text" name="setting_hero_button2_link" value="<?php echo htmlspecialchars($settings['hero_button2_link'] ?? ''); ?>" class="large-text" placeholder="https://wa.me/233XXXXXXXXX or /cms/shop">
                            <p class="description">URL (WhatsApp links should start with https://wa.me/)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Hero Overlay Opacity</label></th>
                        <td>
                            <input type="range" name="setting_hero_overlay_opacity" value="<?php echo htmlspecialchars($settings['hero_overlay_opacity'] ?? '0.4'); ?>" min="0" max="1" step="0.1" class="regular-text" oninput="document.getElementById('overlay-value').textContent = this.value">
                            <span id="overlay-value"><?php echo htmlspecialchars($settings['hero_overlay_opacity'] ?? '0.4'); ?></span>
                            <p class="description">Dark overlay opacity over background image (0 = transparent, 1 = fully dark). Helps text readability.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Enable Hero Banner</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_hero_enabled" value="1" <?php echo ($settings['hero_enabled'] ?? '1') ? 'checked' : ''; ?>>
                                Show hero banner on homepage
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Reading Settings -->
            <div id="reading" class="tab-content">
                <h2>Reading Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Front Page Displays</label></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="setting_homepage_type" value="cms" <?php echo ($settings['homepage_type'] ?? 'cms') === 'cms' ? 'checked' : ''; ?>> Your latest posts</label><br>
                                <label><input type="radio" name="setting_homepage_type" value="page" <?php echo ($settings['homepage_type'] ?? '') === 'page' ? 'checked' : ''; ?>> A static page</label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Posts Per Page</label></th>
                        <td>
                            <input type="number" name="setting_posts_per_page" value="<?php echo htmlspecialchars($settings['posts_per_page'] ?? '10'); ?>" min="1" class="small-text">
                            <p class="description">The number of posts to show per page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Blog Pages Show At Most</label></th>
                        <td>
                            <input type="number" name="setting_blog_posts_per_page" value="<?php echo htmlspecialchars($settings['blog_posts_per_page'] ?? '10'); ?>" min="1" class="small-text">
                            <p class="description">The number of posts to show on blog pages.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Search Engine Visibility</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_discourage_search" value="1" <?php echo ($settings['discourage_search'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                Discourage search engines from indexing this site
                            </label>
                            <p class="description">It is up to search engines to honor this request.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Discussion Settings -->
            <div id="discussion" class="tab-content">
                <h2>Discussion Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Default Post Settings</label></th>
                        <td>
                            <label><input type="checkbox" name="setting_default_pingback" value="1" <?php echo ($settings['default_pingback'] ?? '1') === '1' ? 'checked' : ''; ?>> Attempt to notify any blogs linked to from the post</label><br>
                            <label><input type="checkbox" name="setting_default_pingback_status" value="1" <?php echo ($settings['default_pingback_status'] ?? '1') === '1' ? 'checked' : ''; ?>> Allow link notifications from other blogs (pingbacks and trackbacks)</label><br>
                            <label><input type="checkbox" name="setting_allow_comments" value="1" <?php echo ($settings['allow_comments'] ?? '1') === '1' ? 'checked' : ''; ?>> Allow people to submit comments on new posts</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Other Comment Settings</label></th>
                        <td>
                            <label><input type="checkbox" name="setting_require_moderation" value="1" <?php echo ($settings['require_moderation'] ?? '0') === '1' ? 'checked' : ''; ?>> Comment must be manually approved</label><br>
                            <label><input type="checkbox" name="setting_comment_registration" value="1" <?php echo ($settings['comment_registration'] ?? '0') === '1' ? 'checked' : ''; ?>> Users must be registered and logged in to comment</label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- E-Commerce Settings -->
            <div id="ecommerce" class="tab-content">
                <h2>E-Commerce Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Store Base Address</label></th>
                        <td>
                            <input type="text" name="setting_store_address" value="<?php echo htmlspecialchars($settings['store_address'] ?? ''); ?>" class="large-text" placeholder="Street address">
                            <p class="description">The physical location of your store.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Store City</label></th>
                        <td>
                            <input type="text" name="setting_store_city" value="<?php echo htmlspecialchars($settings['store_city'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Store Country</label></th>
                        <td>
                            <select name="setting_store_country" class="regular-text">
                                <option value="GH" <?php echo ($settings['store_country'] ?? 'GH') === 'GH' ? 'selected' : ''; ?>>Ghana</option>
                                <option value="US" <?php echo ($settings['store_country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                <option value="UK" <?php echo ($settings['store_country'] ?? '') === 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Currency</label></th>
                        <td>
                            <select name="setting_currency">
                                <option value="GHS" <?php echo ($settings['currency'] ?? 'GHS') === 'GHS' ? 'selected' : ''; ?>>GHS - Ghanaian Cedi</option>
                                <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="GBP" <?php echo ($settings['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                            </select>
                            <p class="description">This controls the currency used for product prices.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Currency Position</label></th>
                        <td>
                            <select name="setting_currency_position">
                                <option value="left" <?php echo ($settings['currency_position'] ?? 'left') === 'left' ? 'selected' : ''; ?>>Left (GHS 100)</option>
                                <option value="right" <?php echo ($settings['currency_position'] ?? '') === 'right' ? 'selected' : ''; ?>>Right (100 GHS)</option>
                                <option value="left_space" <?php echo ($settings['currency_position'] ?? '') === 'left_space' ? 'selected' : ''; ?>>Left with space (GHS 100)</option>
                                <option value="right_space" <?php echo ($settings['currency_position'] ?? '') === 'right_space' ? 'selected' : ''; ?>>Right with space (100 GHS)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Enable Low Stock Notifications</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_low_stock_notify" value="1" <?php echo ($settings['low_stock_notify'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                Enable low stock notifications
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Low Stock Threshold</label></th>
                        <td>
                            <input type="number" name="setting_low_stock_threshold" value="<?php echo htmlspecialchars($settings['low_stock_threshold'] ?? '2'); ?>" min="0" class="small-text">
                            <p class="description">When product stock reaches this amount, you will be notified.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Settings -->
            <div id="email" class="tab-content">
                <h2>Email Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>From Email Address</label></th>
                        <td>
                            <input type="email" name="setting_email_from" value="<?php echo htmlspecialchars($settings['email_from'] ?? ''); ?>" class="regular-text">
                            <p class="description">The sender email address for order and notification emails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>From Name</label></th>
                        <td>
                            <input type="text" name="setting_email_from_name" value="<?php echo htmlspecialchars($settings['email_from_name'] ?? $siteTitle); ?>" class="regular-text">
                            <p class="description">The sender name for order and notification emails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Send Order Emails</label></th>
                        <td>
                            <label><input type="checkbox" name="setting_email_new_order" value="1" <?php echo ($settings['email_new_order'] ?? '1') === '1' ? 'checked' : ''; ?>> New order notification</label><br>
                            <label><input type="checkbox" name="setting_email_completed_order" value="1" <?php echo ($settings['email_completed_order'] ?? '1') === '1' ? 'checked' : ''; ?>> Completed order notification</label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Payment Settings -->
            <div id="payment" class="tab-content">
                <h2>Payment Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Enable Test Mode</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_payment_test_mode" value="1" <?php echo ($settings['payment_test_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                Enable test mode for payment gateways
                            </label>
                            <p class="description">Use test API keys for payment gateways.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Paystack Public Key</label></th>
                        <td>
                            <input type="text" name="setting_paystack_public_key" value="<?php echo htmlspecialchars($settings['paystack_public_key'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Paystack Secret Key</label></th>
                        <td>
                            <input type="password" name="setting_paystack_secret_key" value="<?php echo htmlspecialchars($settings['paystack_secret_key'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Flutterwave Public Key</label></th>
                        <td>
                            <input type="text" name="setting_flutterwave_public_key" value="<?php echo htmlspecialchars($settings['flutterwave_public_key'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Flutterwave Secret Key</label></th>
                        <td>
                            <input type="password" name="setting_flutterwave_secret_key" value="<?php echo htmlspecialchars($settings['flutterwave_secret_key'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Shipping Settings -->
            <div id="shipping" class="tab-content">
                <h2>Shipping Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Enable Shipping</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_enable_shipping" value="1" <?php echo ($settings['enable_shipping'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                Enable shipping calculation
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Shipping Zones</label></th>
                        <td>
                            <textarea name="setting_shipping_zones" rows="5" class="large-text" placeholder="Zone 1: Accra - GHS 50&#10;Zone 2: Kumasi - GHS 75&#10;Zone 3: Other Cities - GHS 100"><?php echo htmlspecialchars($settings['shipping_zones'] ?? ''); ?></textarea>
                            <p class="description">Enter shipping zones and rates (one per line, format: Zone Name - Amount)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Free Shipping Threshold</label></th>
                        <td>
                            <input type="number" name="setting_free_shipping_threshold" value="<?php echo htmlspecialchars($settings['free_shipping_threshold'] ?? '0'); ?>" min="0" step="0.01" class="small-text">
                            <p class="description">Free shipping for orders above this amount (0 to disable).</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Tax Settings -->
            <div id="tax" class="tab-content">
                <h2>Tax Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Enable Taxes</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_enable_taxes" value="1" <?php echo ($settings['enable_taxes'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                Enable tax calculation
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Default Tax Rate (%)</label></th>
                        <td>
                            <input type="number" name="setting_default_tax_rate" value="<?php echo htmlspecialchars($settings['default_tax_rate'] ?? '0'); ?>" min="0" max="100" step="0.01" class="small-text">
                            <p class="description">Default tax rate percentage (e.g., 12.5 for VAT).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Tax Included in Prices</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_prices_include_tax" value="1" <?php echo ($settings['prices_include_tax'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                Prices entered include tax
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Permalink Settings -->
            <div id="permalinks" class="tab-content">
                <h2>Permalink Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label>Common Settings</label></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="setting_permalink_structure" value="plain" <?php echo ($settings['permalink_structure'] ?? '') === 'plain' ? 'checked' : ''; ?>> Plain (/?p=123)</label><br>
                                <label><input type="radio" name="setting_permalink_structure" value="day" <?php echo ($settings['permalink_structure'] ?? '') === 'day' ? 'checked' : ''; ?>> Day and name (/2025/11/03/sample-post/)</label><br>
                                <label><input type="radio" name="setting_permalink_structure" value="month" <?php echo ($settings['permalink_structure'] ?? '') === 'month' ? 'checked' : ''; ?>> Month and name (/2025/11/sample-post/)</label><br>
                                <label><input type="radio" name="setting_permalink_structure" value="postname" <?php echo ($settings['permalink_structure'] ?? 'postname') === 'postname' ? 'checked' : ''; ?>> Post name (/sample-post/)</label><br>
                                <label><input type="radio" name="setting_permalink_structure" value="custom" <?php echo isset($settings['permalink_custom']) ? 'checked' : ''; ?>> Custom Structure: <input type="text" name="setting_permalink_custom" value="<?php echo htmlspecialchars($settings['permalink_custom'] ?? '/%postname%/'); ?>" class="regular-text"></label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" value="Save Changes" class="button button-primary">
            </p>
        </form>
        
        <style>
            .settings-tabs { border-bottom: 1px solid #c3c4c7; margin: 20px 0; }
            .settings-tabs .tab-link { display: inline-block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 2px solid transparent; margin-bottom: -1px; }
            .settings-tabs .tab-link:hover { color: #135e96; }
            .settings-tabs .tab-link.active { color: #2271b1; border-bottom-color: #2271b1; font-weight: 600; }
            .tab-content { display: none; padding: 20px 0; }
            .tab-content.active { display: block; }
            .form-table { width: 100%; }
            .form-table th { width: 200px; padding: 20px 10px 20px 0; vertical-align: top; text-align: left; font-weight: 600; }
            .form-table td { padding: 15px 10px; }
            .form-table input[type="text"], .form-table input[type="email"], .form-table input[type="url"], .form-table input[type="number"], .form-table select, .form-table textarea { width: 100%; max-width: 400px; }
            .form-table .large-text { max-width: 600px; }
            .form-table .regular-text { max-width: 400px; }
            .form-table .small-text { max-width: 100px; }
        </style>
        <script>
            function showTab(event, tabId) {
                event.preventDefault();
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-link').forEach(link => link.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
                event.target.classList.add('active');
            }
            
            function previewLogo(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('logo-preview-img').src = e.target.result;
                        document.getElementById('logo-preview').style.display = 'block';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }
            
            function previewIcon(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('icon-preview-img').src = e.target.result;
                        document.getElementById('icon-preview').style.display = 'block';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }
        </script>
    </div>
</body>
</html>

