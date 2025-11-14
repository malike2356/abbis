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

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

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
    
    $appBaseUrl = rtrim(app_url(), '/');

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = str_replace('setting_', '', $key);
            // Handle checkboxes - if not checked, they won't be in POST, so set to '0'
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            if ($settingKey === 'hero_banner_image' && !empty($value)) {
                if (preg_match('#^https?://#i', $value)) {
                    if (strpos($value, $appBaseUrl . '/') === 0) {
                        $value = substr($value, strlen($appBaseUrl) + 1);
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->execute([$settingKey, $value, $value]);
        }
    }
    
    // If a preset theme color is selected, clear the custom color
    if (isset($_POST['setting_cms_theme_color']) && !empty($_POST['setting_cms_theme_color'])) {
        $stmt = $pdo->prepare("DELETE FROM cms_settings WHERE setting_key='cms_custom_primary_color'");
        $stmt->execute();
    }
    
    // If custom color is explicitly being used, ensure theme color is cleared
    if (isset($_POST['use_custom_color']) && $_POST['use_custom_color'] === '1') {
        $stmt = $pdo->prepare("DELETE FROM cms_settings WHERE setting_key='cms_theme_color'");
        $stmt->execute();
    }
    // Handle unchecked checkboxes - set them to '0'
    $checkboxSettings = ['membership', 'default_pingback', 'default_pingback_status', 'allow_comments', 'require_moderation', 'comment_registration', 'discourage_search', 'low_stock_notify', 'payment_test_mode', 'enable_shipping', 'enable_taxes', 'prices_include_tax', 'email_new_order', 'email_completed_order'];
    foreach ($checkboxSettings as $setting) {
        if (!isset($_POST['setting_' . $setting])) {
            $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->execute([$setting, '0', '0']);
        }
    }
    
    // Handle hero_display_locations - if no locations selected, set to empty and disable hero
    if (!isset($_POST['setting_hero_display_locations']) || empty($_POST['setting_hero_display_locations'])) {
        $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute(['hero_display_locations', '', '']);
        // Also disable hero banner if no locations selected
        $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute(['hero_enabled', '0', '0']);
    } else {
        // Ensure hero is enabled if locations are selected
        $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute(['hero_enabled', '1', '1']);
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
    
    // Use Post-Redirect-Get pattern to prevent infinite reload loop
    $_SESSION['settings_saved_message'] = 'Settings saved successfully';
    $redirectUri = $_SERVER['REQUEST_URI'] ?? '/cms/admin/settings.php';
    if (!preg_match('#^https?://#i', $redirectUri)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $redirectUrl = $scheme . '://' . $host . $redirectUri;
    } else {
        $redirectUrl = $redirectUri;
    }
    header('Location: ' . $redirectUrl);
    exit;
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$siteTitle = $settings['site_title'] ?? $companyName;
$baseUrl = app_url();
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
        
        <?php 
        // Check for flash message from session
        if (isset($_SESSION['settings_saved_message'])) {
            $message = $_SESSION['settings_saved_message'];
            unset($_SESSION['settings_saved_message']); // Clear the message after displaying
            echo '<div class="notice notice-success"><p>' . htmlspecialchars($message) . '</p></div>';
        }
        ?>
        
        <form method="post" class="settings-form" enctype="multipart/form-data">
            <div class="settings-tabs">
                <a href="#general" class="tab-link active" onclick="showTab(event, 'general')">General</a>
                <a href="#theme" class="tab-link" onclick="showTab(event, 'theme')">🎨 Theme & Appearance</a>
                <a href="#contact" class="tab-link" onclick="showTab(event, 'contact')">Contact</a>
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
                
                <!-- Multi-Column Layout -->
                <div class="settings-columns">
                    <!-- Column 1: Site Identity -->
                    <div class="settings-column">
                        <div class="settings-section-card">
                            <h3 class="settings-section-title">🌐 Site Identity</h3>
                            <div class="form-group">
                                <label>Site Title</label>
                                <input type="text" name="setting_site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" class="large-text" placeholder="Enter your site name">
                                <p class="description">This will be displayed in the website header. Leave empty to use company name from ABBIS system.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Site Tagline</label>
                                <input type="text" name="setting_site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? ''); ?>" class="large-text" placeholder="Just another ABBIS CMS site">
                                <p class="description">In a few words, explain what this site is about.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Site URL</label>
                                <input type="url" name="setting_site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>" class="large-text" placeholder="https://example.com">
                                <p class="description">The WordPress address (URL) and Site address (URL) are the same.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Admin Email</label>
                                <input type="email" name="setting_admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>" class="regular-text">
                                <p class="description">This address is used for admin purposes, like new user notification.</p>
                            </div>
                        </div>
                        
                        <!-- Logo & Icon Section -->
                        <div class="settings-section-card">
                            <h3 class="settings-section-title">🖼️ Logo & Icon</h3>
                            
                            <div class="form-group">
                                <label>Site Logo</label>
                                <?php
                                $logoPath = $settings['site_logo'] ?? '';
                                $logoUrl = $logoPath ? $baseUrl . '/' . $logoPath : '';
                                ?>
                                <?php if ($logoUrl): ?>
                                    <div style="margin-bottom: 10px;">
                                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Site Logo" style="max-width: 300px; max-height: 100px; border: 1px solid #c3c4c7; padding: 5px; background: white; border-radius: 6px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo_file" accept="image/*" onchange="previewLogo(this)" style="margin-bottom: 8px;">
                                <input type="hidden" name="setting_site_logo" id="site_logo" value="<?php echo htmlspecialchars($logoPath); ?>">
                                <p class="description">Upload a logo for your site. Recommended size: 300x100px or similar aspect ratio.</p>
                                <div id="logo-preview" style="margin-top: 10px; display: none;">
                                    <img id="logo-preview-img" src="" alt="Logo Preview" style="max-width: 300px; max-height: 100px; border: 1px solid #c3c4c7; padding: 5px; background: white; border-radius: 6px;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Site Icon (Favicon)</label>
                                <?php
                                $iconPath = $settings['site_icon'] ?? '';
                                $iconUrl = $iconPath ? $baseUrl . '/' . $iconPath : '';
                                ?>
                                <?php if ($iconUrl): ?>
                                    <div style="margin-bottom: 10px;">
                                        <img src="<?php echo htmlspecialchars($iconUrl); ?>" alt="Site Icon" style="width: 32px; height: 32px; border: 1px solid #c3c4c7; padding: 2px; background: white; border-radius: 4px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="icon_file" accept="image/*" onchange="previewIcon(this)" style="margin-bottom: 8px;">
                                <input type="hidden" name="setting_site_icon" id="site_icon" value="<?php echo htmlspecialchars($iconPath); ?>">
                                <p class="description">Upload a favicon (32x32px recommended).</p>
                                <div id="icon-preview" style="margin-top: 10px; display: none;">
                                    <img id="icon-preview-img" src="" alt="Icon Preview" style="width: 32px; height: 32px; border: 1px solid #c3c4c7; padding: 2px; background: white; border-radius: 4px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column 2: User & Membership -->
                    <div class="settings-column">
                        <div class="settings-section-card">
                            <h3 class="settings-section-title">👥 User & Membership</h3>
                            
                            <div class="form-group">
                                <label>Membership</label>
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <input type="checkbox" name="setting_membership" value="1" <?php echo ($settings['membership'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span>Anyone can register</span>
                                </label>
                                <p class="description">Allow visitors to register on your site.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Default User Role</label>
                                <select name="setting_default_role" class="regular-text">
                                    <option value="subscriber" <?php echo ($settings['default_role'] ?? 'subscriber') === 'subscriber' ? 'selected' : ''; ?>>Subscriber</option>
                                    <option value="author" <?php echo ($settings['default_role'] ?? '') === 'author' ? 'selected' : ''; ?>>Author</option>
                                    <option value="editor" <?php echo ($settings['default_role'] ?? '') === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                </select>
                                <p class="description">New users will be assigned this role.</p>
                            </div>
                        </div>
                        
                        <!-- Date & Time Settings -->
                        <div class="settings-section-card">
                            <h3 class="settings-section-title">📅 Date & Time</h3>
                            
                            <div class="form-group">
                                <label>Timezone</label>
                                <select name="setting_timezone" class="regular-text">
                                    <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="Africa/Accra" <?php echo ($settings['timezone'] ?? '') === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra (GMT+0)</option>
                                    <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (GMT-5)</option>
                                    <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT+0)</option>
                                </select>
                                <p class="description">Choose a city in the same timezone as you.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Date Format</label>
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="setting_date_format" value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'checked' : ''; ?>> 2025-11-03</label>
                                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="setting_date_format" value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'checked' : ''; ?>> 11/03/2025</label>
                                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="setting_date_format" value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'checked' : ''; ?>> 03/11/2025</label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="radio" name="setting_date_format" value="custom" <?php echo isset($settings['date_format_custom']) ? 'checked' : ''; ?>> 
                                        Custom: <input type="text" name="setting_date_format_custom" value="<?php echo htmlspecialchars($settings['date_format_custom'] ?? 'Y-m-d'); ?>" class="small-text" style="margin-left: 8px;">
                                    </label>
                                </fieldset>
                            </div>
                            
                            <div class="form-group">
                                <label>Time Format</label>
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="setting_time_format" value="H:i" <?php echo ($settings['time_format'] ?? 'H:i') === 'H:i' ? 'checked' : ''; ?>> 14:30 (24-hour)</label>
                                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="setting_time_format" value="g:i A" <?php echo ($settings['time_format'] ?? '') === 'g:i A' ? 'checked' : ''; ?>> 2:30 PM (12-hour)</label>
                                </fieldset>
                            </div>
                            
                            <div class="form-group">
                                <label>Week Starts On</label>
                                <select name="setting_start_of_week" class="regular-text">
                                    <option value="0" <?php echo ($settings['start_of_week'] ?? '0') === '0' ? 'selected' : ''; ?>>Sunday</option>
                                    <option value="1" <?php echo ($settings['start_of_week'] ?? '') === '1' ? 'selected' : ''; ?>>Monday</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Theme & Appearance Settings -->
            <div id="theme" class="tab-content">
                <h2>🎨 Theme & Appearance Settings</h2>
                <p style="color: #646970; margin-bottom: 20px; font-size: 14px;">Customize the color scheme and appearance of your CMS admin panel and frontend website. Changes apply system-wide.</p>
                
                <table class="form-table">
                    <tr>
                        <th><label>Primary Theme Color</label></th>
                        <td>
                            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                                <?php
                                $currentTheme = $settings['cms_theme_color'] ?? 'blue';
                                $themes = [
                                    'blue' => ['name' => 'Blue', 'color' => '#2563eb', 'preview' => '🔵'],
                                    'red' => ['name' => 'Red', 'color' => '#dc2626', 'preview' => '🔴'],
                                    'green' => ['name' => 'Green', 'color' => '#16a34a', 'preview' => '🟢'],
                                    'purple' => ['name' => 'Purple', 'color' => '#9333ea', 'preview' => '🟣'],
                                    'orange' => ['name' => 'Orange', 'color' => '#ea580c', 'preview' => '🟠'],
                                    'teal' => ['name' => 'Teal', 'color' => '#0d9488', 'preview' => '🔷'],
                                    'pink' => ['name' => 'Pink', 'color' => '#db2777', 'preview' => '🌸'],
                                    'indigo' => ['name' => 'Indigo', 'color' => '#4f46e5', 'preview' => '💙'],
                                ];
                                foreach ($themes as $key => $theme):
                                ?>
                                    <label style="display: flex; flex-direction: column; align-items: center; cursor: pointer; padding: 15px; border: 2px solid <?php echo $currentTheme === $key ? $theme['color'] : '#c3c4c7'; ?>; border-radius: 8px; background: <?php echo $currentTheme === $key ? 'rgba(' . hexdec(substr($theme['color'], 1, 2)) . ', ' . hexdec(substr($theme['color'], 3, 2)) . ', ' . hexdec(substr($theme['color'], 5, 2)) . ', 0.1)' : 'white'; ?>; transition: all 0.3s ease; min-width: 100px;">
                                        <input type="radio" name="setting_cms_theme_color" value="<?php echo $key; ?>" <?php echo $currentTheme === $key ? 'checked' : ''; ?> style="margin-bottom: 8px;" onchange="updateThemePreview()">
                                        <span style="font-size: 32px; margin-bottom: 8px;"><?php echo $theme['preview']; ?></span>
                                        <span style="font-weight: 600; color: #1d2327; font-size: 13px;"><?php echo $theme['name']; ?></span>
                                        <span style="font-size: 11px; color: #646970; margin-top: 4px;"><?php echo $theme['color']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top: 15px;">Select a primary color theme for your CMS. This affects buttons, links, highlights, and accents throughout both the admin panel and frontend website.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Custom Primary Color</label></th>
                        <td>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <input type="color" name="setting_cms_custom_primary_color" id="custom_primary_color" value="<?php echo htmlspecialchars($settings['cms_custom_primary_color'] ?? '#2563eb'); ?>" style="width: 80px; height: 40px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer;">
                                <input type="text" name="setting_cms_custom_primary_color_hex" id="custom_primary_color_hex" value="<?php echo htmlspecialchars($settings['cms_custom_primary_color'] ?? '#2563eb'); ?>" placeholder="#2563eb" style="width: 120px; padding: 8px; border: 1px solid #c3c4c7; border-radius: 4px; font-family: monospace;">
                                <button type="button" onclick="useCustomColor()" class="button" style="padding: 8px 16px;">Use Custom Color</button>
                            </div>
                            <p class="description">Choose a custom primary color using the color picker or enter a hex code. Click "Use Custom Color" to apply it as the theme.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Admin Panel Style</label></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="setting_cms_admin_style" value="modern" <?php echo ($settings['cms_admin_style'] ?? 'modern') === 'modern' ? 'checked' : ''; ?>>
                                    Modern (Default) - Clean, minimal design with smooth animations
                                </label><br>
                                <label>
                                    <input type="radio" name="setting_cms_admin_style" value="classic" <?php echo ($settings['cms_admin_style'] ?? '') === 'classic' ? 'checked' : ''; ?>>
                                    Classic - Traditional WordPress-style interface
                                </label><br>
                                <label>
                                    <input type="radio" name="setting_cms_admin_style" value="compact" <?php echo ($settings['cms_admin_style'] ?? '') === 'compact' ? 'checked' : ''; ?>>
                                    Compact - Dense layout for maximum information display
                                </label>
                            </fieldset>
                            <p class="description">Choose the visual style for the admin panel interface.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Sidebar Position</label></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="setting_cms_sidebar_position" value="left" <?php echo ($settings['cms_sidebar_position'] ?? 'left') === 'left' ? 'checked' : ''; ?>>
                                    Left (Default)
                                </label><br>
                                <label>
                                    <input type="radio" name="setting_cms_sidebar_position" value="right" <?php echo ($settings['cms_sidebar_position'] ?? '') === 'right' ? 'checked' : ''; ?>>
                                    Right
                                </label>
                            </fieldset>
                            <p class="description">Choose whether the admin sidebar appears on the left or right side.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Frontend Theme Integration</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="setting_cms_apply_theme_frontend" value="1" <?php echo ($settings['cms_apply_theme_frontend'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                Apply theme colors to frontend website
                            </label>
                            <p class="description">When enabled, the selected theme color will be applied to buttons, links, and accents on the public-facing website.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Admin Dashboard Layout</label></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="setting_cms_dashboard_layout" value="grid" <?php echo ($settings['cms_dashboard_layout'] ?? 'grid') === 'grid' ? 'checked' : ''; ?>>
                                    Grid Layout (Default) - Card-based grid display
                                </label><br>
                                <label>
                                    <input type="radio" name="setting_cms_dashboard_layout" value="list" <?php echo ($settings['cms_dashboard_layout'] ?? '') === 'list' ? 'checked' : ''; ?>>
                                    List Layout - Traditional list view
                                </label>
                            </fieldset>
                            <p class="description">Choose how dashboard widgets and stats are displayed.</p>
                        </td>
                    </tr>
                </table>
                
                <div style="background: #f6f7f7; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #1d2327;">💡 Preview</h3>
                    <p style="margin: 0; color: #646970; font-size: 13px;">Changes will be applied immediately after saving. You can preview the theme by refreshing the page.</p>
                </div>
            </div>
            
            <!-- Contact Settings -->
            <div id="contact" class="tab-content">
                <h2>Contact Information</h2>
                <p class="description">Configure the contact information displayed on the Contact Us page (<a href="<?php echo $baseUrl; ?>/cms/contact" target="_blank">View Page</a>).</p>
                <table class="form-table">
                    <tr>
                        <th><label>Contact Email</label></th>
                        <td>
                            <input type="email" name="setting_contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" class="regular-text" placeholder="info@example.com">
                            <p class="description">Email address displayed on the contact page and used for contact form submissions.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Contact Phone</label></th>
                        <td>
                            <input type="tel" name="setting_contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>" class="regular-text" placeholder="+233 XX XXX XXXX">
                            <p class="description">Phone number displayed on the contact page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Contact Address</label></th>
                        <td>
                            <textarea name="setting_contact_address" rows="3" class="large-text" placeholder="123 Main Street, Accra, Ghana"><?php echo htmlspecialchars($settings['contact_address'] ?? ''); ?></textarea>
                            <p class="description">Physical address or location displayed on the contact page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Business Hours</label></th>
                        <td>
                            <input type="text" name="setting_contact_hours" value="<?php echo htmlspecialchars($settings['contact_hours'] ?? ''); ?>" class="large-text" placeholder="Monday - Friday: 8:00 AM - 5:00 PM">
                            <p class="description">Business hours or availability information.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Homepage Settings -->
            <div id="homepage" class="tab-content">
                <div class="admin-page-header">
                    <h2>🏠 Homepage Hero Banner</h2>
                    <p>Configure the hero banner section that appears below the header on your homepage.</p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
                    <!-- Left Column: Image & Content -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <!-- Hero Banner Image Card -->
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h3>🖼️ Hero Banner Image</h3>
                            </div>
                            <div class="settings-card-body">
                                <?php
                                $heroImagePath = $settings['hero_banner_image'] ?? '';
                                $heroImageUrl = $heroImagePath ? $baseUrl . '/' . $heroImagePath : '';
                                ?>
                                <input type="hidden" name="setting_hero_banner_image" id="hero_banner_image_input" value="<?php echo htmlspecialchars($heroImagePath); ?>">
                                <input type="hidden" name="remove_hero_image" id="remove_hero_image_input" value="0">
                                <div id="hero-banner-preview" style="margin-bottom: 16px; border-radius: 8px; overflow: hidden; border: 2px dashed #c3c4c7; background: #f6f7f7; min-height: 200px; display: flex; align-items: center; justify-content: center; padding: 12px;">
                                    <?php if ($heroImageUrl): ?>
                                        <img src="<?php echo htmlspecialchars($heroImageUrl); ?>?v=<?php echo time(); ?>" alt="Hero Banner" style="width: 100%; height: auto; display: block;">
                                    <?php else: ?>
                                        <div class="hero-banner-preview-placeholder" style="text-align: center; color: #64748b; font-weight: 500;">
                                            No hero banner image selected yet.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="hero-banner-actions" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px;">
                                    <button type="button" class="admin-btn admin-btn-primary" id="hero-media-picker-btn">📁 Select from Media Library</button>
                                    <label for="hero_banner_upload" class="admin-btn admin-btn-outline" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                                        ⬆️ Upload New Image
                                        <input type="file" name="hero_banner_image" id="hero_banner_upload" accept="image/*" style="display: none;">
                                    </label>
                                    <button type="button" class="admin-btn admin-btn-danger" id="hero-remove-image-btn" style="display: <?php echo $heroImagePath ? 'inline-flex' : 'none'; ?>; align-items: center; gap: 6px;">
                                        🗑️ Remove Image
                                    </button>
                                </div>
                                <p class="description">Choose an existing image from the media library or upload a new wide image (1920x800px recommended, max 10MB).</p>
                            </div>
                        </div>
                        
                        <!-- Hero Content Card -->
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h3>📝 Hero Content</h3>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-group">
                                    <label>Hero Title</label>
                                    <input type="text" name="setting_hero_title" value="<?php echo htmlspecialchars($settings['hero_title'] ?? ''); ?>" class="large-text" placeholder="<?php echo htmlspecialchars($siteTitle); ?>">
                                    <p class="description">Main headline text. Leave empty to use site title.</p>
                                </div>
                                <div class="form-group">
                                    <label>Hero Subtitle</label>
                                    <input type="text" name="setting_hero_subtitle" value="<?php echo htmlspecialchars($settings['hero_subtitle'] ?? ''); ?>" class="large-text" placeholder="Drilling & Construction, Mechanization and more!">
                                    <p class="description">Subtitle text displayed below the main headline.</p>
                                </div>
                                <div class="form-group">
                                    <label>Hero Overlay Opacity</label>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <input type="range" name="setting_hero_overlay_opacity" value="<?php echo htmlspecialchars($settings['hero_overlay_opacity'] ?? '0.4'); ?>" min="0" max="1" step="0.1" style="flex: 1;" oninput="document.getElementById('overlay-value').textContent = this.value">
                                        <span id="overlay-value" style="font-weight: 600; min-width: 40px; text-align: center;"><?php echo htmlspecialchars($settings['hero_overlay_opacity'] ?? '0.4'); ?></span>
                                    </div>
                                    <p class="description">Dark overlay opacity (0 = transparent, 1 = fully dark). Helps text readability.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Buttons & Display -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <!-- Primary Button Card -->
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h3>🔘 Primary Button</h3>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-group">
                                    <label>Button Text</label>
                                    <input type="text" name="setting_hero_button1_text" value="<?php echo htmlspecialchars($settings['hero_button1_text'] ?? 'CALL US NOW'); ?>" class="large-text">
                                </div>
                                <div class="form-group">
                                    <label>Button Link</label>
                                    <input type="text" name="setting_hero_button1_link" value="<?php echo htmlspecialchars($settings['hero_button1_link'] ?? 'tel:0248518513'); ?>" class="large-text" placeholder="tel:0248518513 or /cms/quote">
                                    <p class="description">URL or phone number (use tel: for phone links)</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Secondary Button Card -->
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h3>🔘 Secondary Button</h3>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-group">
                                    <label>Button Text</label>
                                    <input type="text" name="setting_hero_button2_text" value="<?php echo htmlspecialchars($settings['hero_button2_text'] ?? 'WHATSAPP US'); ?>" class="large-text">
                                </div>
                                <div class="form-group">
                                    <label>Button Link</label>
                                    <input type="text" name="setting_hero_button2_link" value="<?php echo htmlspecialchars($settings['hero_button2_link'] ?? ''); ?>" class="large-text" placeholder="https://wa.me/233XXXXXXXXX or /cms/shop">
                                    <p class="description">URL (WhatsApp links should start with https://wa.me/)</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Display Locations Card -->
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h3>📍 Display Locations</h3>
                            </div>
                            <div class="settings-card-body">
                                <?php
                                $displayLocations = !empty($settings['hero_display_locations']) ? explode(',', $settings['hero_display_locations']) : ['homepage'];
                                $allLocations = [
                                    'homepage' => 'Homepage',
                                    'all_pages' => 'All Pages',
                                    'blog' => 'Blog Page',
                                    'shop' => 'Shop/Products Page',
                                    'about' => 'About Page',
                                    'services' => 'Services Page',
                                    'contact' => 'Contact Page',
                                    'portfolio' => 'Portfolio Page',
                                    'quote' => 'Quote/Request Page'
                                ];
                                ?>
                                <div style="background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px;">
                                    <p style="margin: 0 0 16px 0; font-weight: 600; color: #1d2327;">Select where to display the hero banner:</p>
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                        <?php foreach ($allLocations as $locationKey => $locationLabel): ?>
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; border-radius: 6px; background: white; border: 2px solid <?php echo in_array($locationKey, $displayLocations) ? 'var(--admin-primary, #2563eb)' : '#c3c4c7'; ?>; transition: all 0.2s;" 
                                                   onmouseover="this.style.borderColor='var(--admin-primary, #2563eb)'; this.style.background='var(--admin-primary-lighter, rgba(37, 99, 235, 0.05))'" 
                                                   onmouseout="this.style.borderColor='<?php echo in_array($locationKey, $displayLocations) ? 'var(--admin-primary, #2563eb)' : '#c3c4c7'; ?>'; this.style.background='white'">
                                                <input type="checkbox" name="setting_hero_display_locations[]" value="<?php echo htmlspecialchars($locationKey); ?>" 
                                                       <?php echo in_array($locationKey, $displayLocations) ? 'checked' : ''; ?>
                                                       onchange="updateHeroEnabled(); this.closest('label').style.borderColor = this.checked ? 'var(--admin-primary, #2563eb)' : '#c3c4c7';"
                                                       style="margin: 0;">
                                                <span style="font-weight: 500; font-size: 13px;"><?php echo htmlspecialchars($locationLabel); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description" style="margin-top: 16px; margin-bottom: 0; padding: 12px; background: rgba(37, 99, 235, 0.05); border-radius: 6px; border-left: 3px solid var(--admin-primary, #2563eb);">
                                        <strong>💡 Tip:</strong> Select multiple locations where you want the hero banner to appear. The banner will automatically show on the selected pages.
                                    </p>
                                </div>
                                <input type="hidden" name="setting_hero_enabled" id="hero_enabled_hidden" value="1">
                                <script>
                                    function updateHeroEnabled() {
                                        const checkboxes = document.querySelectorAll('input[name="setting_hero_display_locations[]"]:checked');
                                        document.getElementById('hero_enabled_hidden').value = checkboxes.length > 0 ? '1' : '0';
                                    }
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
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
            .settings-tabs { 
                border-bottom: 1px solid #c3c4c7; 
                margin: 20px 0; 
                display: flex; 
                flex-wrap: wrap; 
                gap: 4px; 
            }
            .settings-tabs .tab-link { 
                display: inline-block; 
                padding: 10px 15px; 
                text-decoration: none; 
                color: #2563eb; 
                border-bottom: 2px solid transparent; 
                margin-bottom: -1px; 
                transition: all 0.2s ease;
                border-radius: 6px 6px 0 0;
            }
            .settings-tabs .tab-link:hover { 
                color: #1e40af; 
                background: rgba(37, 99, 235, 0.05);
            }
            .settings-tabs .tab-link.active { 
                color: #2563eb; 
                border-bottom-color: #2563eb; 
                font-weight: 600; 
                background: rgba(37, 99, 235, 0.08);
            }
            .tab-content { 
                display: none; 
                padding: 20px 0; 
            }
            .tab-content.active { 
                display: block; 
            }
            
            /* Multi-Column Layout */
            .settings-columns {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
                margin-top: 20px;
            }
            
            .settings-column {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .settings-section-card {
                background: white;
                border: 1px solid #c3c4c7;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
            }
            
            .settings-section-card:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                border-color: #2563eb;
            }
            
            .settings-section-title {
                font-size: 18px;
                font-weight: 700;
                margin: 0 0 20px 0;
                padding-bottom: 12px;
                border-bottom: 2px solid #f0f0f1;
                color: #1d2327;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .settings-section-title::after {
                content: '';
                flex: 1;
                height: 2px;
                background: linear-gradient(90deg, #2563eb 0%, transparent 100%);
                margin-left: 8px;
            }
            
            /* Settings Card Styles for Multi-Column Layout */
            .settings-card {
                background: white;
                border: 1px solid #c3c4c7;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
                overflow: hidden;
            }
            
            .settings-card:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                border-color: var(--admin-primary, #2563eb);
            }
            
            .settings-card-header {
                background: linear-gradient(135deg, #f6f7f7 0%, #ffffff 100%);
                border-bottom: 1px solid #c3c4c7;
                padding: 16px 20px;
            }
            
            .settings-card-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .settings-card-body {
                padding: 20px;
            }
            
            .admin-page-header {
                margin-bottom: 24px;
            }
            
            .admin-page-header h2 {
                font-size: 24px;
                font-weight: 700;
                margin: 0 0 8px 0;
                color: #1d2327;
            }
            
            .admin-page-header p {
                margin: 0;
                color: #646970;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 24px;
            }
            
            .form-group:last-child {
                margin-bottom: 0;
            }
            
            /* Responsive Design for Multi-Column Layout */
            @media (max-width: 1200px) {
                .settings-columns {
                    grid-template-columns: 1fr;
                }
                
                #homepage .tab-content > div[style*="grid-template-columns: 1fr 1fr"] {
                    grid-template-columns: 1fr !important;
                }
            }
            
            @media (max-width: 768px) {
                .settings-card-header h3 {
                    font-size: 14px;
                }
                
                .settings-card-body {
                    padding: 16px;
                }
                
                .admin-page-header h2 {
                    font-size: 20px;
                }
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #1d2327;
                font-size: 14px;
            }
            
            .form-group input[type="text"],
            .form-group input[type="email"],
            .form-group input[type="url"],
            .form-group input[type="number"],
            .form-group input[type="tel"],
            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 6px;
                font-size: 14px;
                font-family: inherit;
                transition: all 0.2s ease;
            }
            
            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            }
            
            .form-group .large-text {
                max-width: 100%;
            }
            
            .form-group .regular-text {
                max-width: 100%;
            }
            
            .form-group .small-text {
                max-width: 150px;
            }
            
            .form-group .description {
                font-size: 13px;
                color: #646970;
                margin-top: 6px;
                line-height: 1.5;
            }
            
            .form-group fieldset {
                border: none;
                padding: 0;
                margin: 0;
            }
            
            .form-group fieldset label {
                display: block;
                margin-bottom: 8px;
                font-weight: 400;
                cursor: pointer;
            }
            
            .form-group fieldset input[type="radio"],
            .form-group fieldset input[type="checkbox"] {
                margin-right: 8px;
            }
            
            /* Responsive Design */
            @media (max-width: 1200px) {
                .settings-columns {
                    grid-template-columns: 1fr;
                }
            }
            
            /* Legacy form-table support for other tabs */
            .form-table { 
                width: 100%; 
            }
            .form-table th { 
                width: 200px; 
                padding: 20px 10px 20px 0; 
                vertical-align: top; 
                text-align: left; 
                font-weight: 600; 
            }
            .form-table td { 
                padding: 15px 10px; 
            }
            .form-table input[type="text"], 
            .form-table input[type="email"], 
            .form-table input[type="url"], 
            .form-table input[type="number"], 
            .form-table select, 
            .form-table textarea { 
                width: 100%; 
                max-width: 400px; 
            }
            .form-table .large-text { 
                max-width: 600px; 
            }
            .form-table .regular-text { 
                max-width: 400px; 
            }
            .form-table .small-text { 
                max-width: 100px; 
            }
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

            document.addEventListener('DOMContentLoaded', function() {
                const heroMediaBtn = document.getElementById('hero-media-picker-btn');
                const heroInput = document.getElementById('hero_banner_image_input');
                const heroPreview = document.getElementById('hero-banner-preview');
                const heroUploadInput = document.getElementById('hero_banner_upload');
                const removeInput = document.getElementById('remove_hero_image_input');
                const removeBtn = document.getElementById('hero-remove-image-btn');

                function escapeAttribute(value) {
                    return String(value || '').replace(/&/g, '&amp;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                }

                function renderHeroPreview(src) {
                    if (!heroPreview) return;
                    if (src) {
                        heroPreview.innerHTML = '<img src="' + escapeAttribute(src) + '" alt="Hero Banner" style="width: 100%; height: auto; display: block;">';
                        if (removeBtn) {
                            removeBtn.style.display = 'inline-flex';
                        }
                    } else {
                        heroPreview.innerHTML = '<div class="hero-banner-preview-placeholder" style="text-align: center; color: #64748b; font-weight: 500;">No hero banner image selected yet.</div>';
                        if (removeBtn) {
                            removeBtn.style.display = 'none';
                        }
                    }
                }

                if (heroMediaBtn) {
                    heroMediaBtn.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (typeof openMediaPicker !== 'function') return;
                        openMediaPicker({
                            allowedTypes: ['image'],
                            baseUrl: '<?php echo $baseUrl; ?>',
                            onSelect: function(selected) {
                                if (!selected) return;
                                if (heroInput) {
                                    let newPath = selected.file_path || '';
                                    const absoluteUrl = selected.url || '';
                                    const baseUrl = '<?php echo rtrim($baseUrl, '/'); ?>';

                                    if (!newPath && absoluteUrl) {
                                        if (absoluteUrl.startsWith(baseUrl + '/')) {
                                            newPath = absoluteUrl.substring(baseUrl.length + 1);
                                        } else {
                                            newPath = absoluteUrl;
                                        }
                                    }

                                    heroInput.value = newPath;
                                }
                                if (removeInput) {
                                    removeInput.value = '0';
                                }
                                if (heroUploadInput) {
                                    heroUploadInput.value = '';
                                }
                                renderHeroPreview(selected.url || '');
                            }
                        });
                    });
                }

                if (heroUploadInput) {
                    heroUploadInput.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                renderHeroPreview(e.target.result || '');
                            };
                            reader.readAsDataURL(this.files[0]);
                            if (removeInput) {
                                removeInput.value = '0';
                            }
                        }
                    });
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', function(event) {
                        event.preventDefault();
                        if (heroInput) {
                            heroInput.value = '';
                        }
                        if (heroUploadInput) {
                            heroUploadInput.value = '';
                        }
                        if (removeInput) {
                            removeInput.value = '1';
                        }
                        renderHeroPreview('');
                    });
                }
            });
            
            // Custom color picker synchronization
            const customColorPicker = document.getElementById('custom_primary_color');
            const customColorHex = document.getElementById('custom_primary_color_hex');
            
            if (customColorPicker && customColorHex) {
                customColorPicker.addEventListener('input', function() {
                    customColorHex.value = this.value.toUpperCase();
                });
                
                customColorHex.addEventListener('input', function() {
                    const hex = this.value.replace('#', '');
                    if (/^[0-9A-Fa-f]{6}$/.test(hex)) {
                        customColorPicker.value = '#' + hex;
                    }
                });
            }
            
            // Use Custom Color function
            function useCustomColor() {
                const customColor = document.getElementById('custom_primary_color').value;
                const customColorHex = document.getElementById('custom_primary_color_hex').value;
                
                // Set the custom color as the theme color
                document.querySelector('input[name="setting_cms_custom_primary_color"]').value = customColor;
                document.querySelector('input[name="setting_cms_custom_primary_color_hex"]').value = customColorHex;
                
                // Uncheck all preset theme colors
                document.querySelectorAll('input[name="setting_cms_theme_color"]').forEach(radio => {
                    radio.checked = false;
                });
                
                // Add a hidden field to indicate custom color is being used
                let hiddenField = document.querySelector('input[name="use_custom_color"]');
                if (!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'use_custom_color';
                    hiddenField.value = '1';
                    document.querySelector('form').appendChild(hiddenField);
                }
                
                alert('Custom color selected! Click "Save Changes" to apply the new theme color.');
            }
            
            // When a preset theme is selected, ensure custom color is cleared and provide visual feedback
            document.querySelectorAll('input[name="setting_cms_theme_color"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        // Clear custom color fields
                        document.getElementById('custom_primary_color').value = '#2563eb';
                        document.getElementById('custom_primary_color_hex').value = '#2563eb';
                        
                        // Remove custom color flag
                        const hiddenField = document.querySelector('input[name="use_custom_color"]');
                        if (hiddenField) {
                            hiddenField.remove();
                        }
                        
                        // Visual feedback - update label styles
                        document.querySelectorAll('input[name="setting_cms_theme_color"]').forEach(r => {
                            const label = r.closest('label');
                            if (label) {
                                if (r === this) {
                                    const color = r.value;
                                    const themeColors = {
                                        'blue': '#2563eb',
                                        'red': '#dc2626',
                                        'green': '#16a34a',
                                        'purple': '#9333ea',
                                        'orange': '#ea580c',
                                        'teal': '#0d9488',
                                        'pink': '#db2777',
                                        'indigo': '#4f46e5'
                                    };
                                    const selectedColor = themeColors[color] || '#2563eb';
                                    label.style.borderColor = selectedColor;
                                    label.style.background = selectedColor + '1A'; // 10% opacity
                                } else {
                                    label.style.borderColor = '#c3c4c7';
                                    label.style.background = 'white';
                                }
                            }
                        });
                    }
                });
            });
            
            // Initialize visual state on page load
            document.querySelectorAll('input[name="setting_cms_theme_color"]').forEach(radio => {
                if (radio.checked) {
                    radio.dispatchEvent(new Event('change'));
                }
            });
            
            // Update theme preview (if needed)
            function updateThemePreview() {
                // This can be used to show a live preview if needed
                console.log('Theme preview updated');
            }
        </script>
    </div>
</body>
</html>

