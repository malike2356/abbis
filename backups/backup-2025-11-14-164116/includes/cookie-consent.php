<?php
/**
 * Cookie Consent Banner
 * GDPR/Ghana Data Protection Act Compliance
 */
require_once __DIR__ . '/consent-manager.php';

$consentManager = new ConsentManager();
$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['email'] ?? null;

// Determine if we're in a module (for path resolution)
$is_module = isset($_SESSION['is_module']) ? $_SESSION['is_module'] : (basename(dirname($_SERVER['PHP_SELF'])) === 'modules');

// Check if user has already consented
$hasConsented = false;
if ($userId || $userEmail) {
    $hasConsented = $consentManager->hasConsented($userId, $userEmail, 'cookies');
} else {
    // Check session cookie
    $hasConsented = isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted';
}

if (!$hasConsented):
?>

<div id="cookie-consent-banner" style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    padding: 20px;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
">
    <div style="flex: 1; min-width: 250px;">
        <strong style="display: block; margin-bottom: 8px; font-size: 16px;">🍪 Cookie Consent</strong>
        <p style="margin: 0; font-size: 14px; opacity: 0.9;">
            We use essential cookies to ensure the system works properly. By continuing, you consent to our use of cookies.
            <a href="<?php echo $is_module ? '' : 'modules/'; ?>privacy-policy.php" style="color: #60a5fa; text-decoration: underline;">Learn more</a>
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="acceptCookies()" style="
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        ">Accept</button>
        <button onclick="declineCookies()" style="
            padding: 10px 20px;
            background: transparent;
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        ">Decline</button>
    </div>
</div>

<script>
function acceptCookies() {
    // Set cookie (30 days)
    document.cookie = 'cookie_consent=accepted; path=/; max-age=' + (30 * 24 * 60 * 60) + '; SameSite=Strict';
    
    // Record in database if user is logged in
    <?php if ($userId || $userEmail): ?>
    fetch('<?php echo ($is_module ?? false) ? '../api/record-consent.php' : 'api/record-consent.php'; ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            consent_type: 'cookies',
            consented: true
        })
    }).catch(() => {});
    <?php endif; ?>
    
    document.getElementById('cookie-consent-banner').style.display = 'none';
}

function declineCookies() {
    // Still set cookie to remember choice, but mark as declined
    document.cookie = 'cookie_consent=declined; path=/; max-age=' + (30 * 24 * 60 * 60) + '; SameSite=Strict';
    
    <?php if ($userId || $userEmail): ?>
    fetch('<?php echo ($is_module ?? false) ? '../api/record-consent.php' : 'api/record-consent.php'; ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            consent_type: 'cookies',
            consented: false
        })
    }).catch(() => {});
    <?php endif; ?>
    
    document.getElementById('cookie-consent-banner').style.display = 'none';
}

// Hide banner if consent already given
<?php if ($hasConsented): ?>
document.getElementById('cookie-consent-banner').style.display = 'none';
<?php endif; ?>
</script>

<?php endif; ?>

