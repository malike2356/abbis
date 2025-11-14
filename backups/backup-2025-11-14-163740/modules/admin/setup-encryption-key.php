<?php
/**
 * Setup Encryption Key for ABBIS (Modal Version)
 * This page helps you generate and configure the encryption key needed for storing API keys securely
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('system.admin');

$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';
$errors = [];
$messages = [];
$keyGenerated = false;
$keyToSet = '';

// Check current status
$currentKey = getenv('ABBIS_ENCRYPTION_KEY');
$keyFile = $rootPath . '/config/secrets/encryption.key';
$keyFileExists = file_exists($keyFile);
$isConfigured = $currentKey || $keyFileExists;

// Generate a new key if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        // Generate a secure 32-byte key (256 bits for AES-256)
        $newKey = base64_encode(random_bytes(32));
        $keyGenerated = true;
        $keyToSet = $newKey;
    }
}

// Save key to file if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_to_file'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $keyToSave = trim($_POST['encryption_key'] ?? '');
        if (empty($keyToSave)) {
            $errors[] = 'Encryption key is required.';
        } else {
            // Create secrets directory if it doesn't exist
            $secretsDir = $rootPath . '/config/secrets';
            if (!is_dir($secretsDir)) {
                // Try to create directory with appropriate permissions
                // Use 0777 to ensure web server (running as daemon) can write
                $umask = umask(0);
                $dirCreated = @mkdir($secretsDir, 0777, true);
                umask($umask);
                
                if (!$dirCreated) {
                    // If mkdir fails, provide helpful instructions
                    $errors[] = 'Failed to create secrets directory automatically.';
                    $errors[] = '<strong>Please run one of these commands in your terminal:</strong>';
                    $errors[] = '<div style="margin: 12px 0; padding: 12px; background: rgba(0,0,0,0.05); border-radius: 6px; border-left: 4px solid #3b82f6;">';
                    $errors[] = '<code style="display: block; padding: 8px; background: white; border-radius: 4px; margin: 8px 0; font-family: monospace; font-size: 13px;">mkdir -p ' . $secretsDir . ' && chmod 777 ' . $secretsDir . '</code>';
                    $errors[] = '<strong>Or (recommended for production):</strong>';
                    $errors[] = '<code style="display: block; padding: 8px; background: white; border-radius: 4px; margin: 8px 0; font-family: monospace; font-size: 13px;">sudo mkdir -p ' . $secretsDir . ' && sudo chown daemon:daemon ' . $secretsDir . ' && sudo chmod 775 ' . $secretsDir . '</code>';
                    $errors[] = '</div>';
                    $errors[] = 'The directory must be writable by the web server user (typically "daemon" for XAMPP).';
                } else {
                    // Ensure parent config directory permissions allow access
                    @chmod($rootPath . '/config', 0755);
                    @chmod($secretsDir, 0777); // Allow web server to write
                }
            } else {
                // Directory exists - ensure it's writable
                @chmod($secretsDir, 0777);
            }
            
            if (empty($errors)) {
                // Check if directory is writable
                if (!is_writable($secretsDir)) {
                    $errors[] = '<strong>Secrets directory is not writable by the web server.</strong>';
                    $errors[] = 'Directory: <code>' . $secretsDir . '</code>';
                    $errors[] = '<div style="margin: 12px 0; padding: 12px; background: rgba(239,68,68,0.1); border-radius: 6px; border-left: 4px solid #ef4444;">';
                    $errors[] = '<strong>Fix permissions:</strong>';
                    $errors[] = '<code style="display: block; padding: 8px; background: white; border-radius: 4px; margin: 8px 0; font-family: monospace; font-size: 13px;">chmod 777 ' . $secretsDir . '</code>';
                    $errors[] = '<strong>Or (better for security):</strong>';
                    $errors[] = '<code style="display: block; padding: 8px; background: white; border-radius: 4px; margin: 8px 0; font-family: monospace; font-size: 13px;">sudo chown daemon:daemon ' . $secretsDir . ' && sudo chmod 775 ' . $secretsDir . '</code>';
                    $errors[] = '</div>';
                    $errors[] = '<strong>Alternative:</strong> Set the encryption key as an environment variable instead of saving to a file.';
                } else {
                    // Save key to file
                    $writeResult = @file_put_contents($keyFile, $keyToSave);
                    if ($writeResult !== false) {
                        // Try to set restrictive permissions (may fail if running as different user)
                        @chmod($keyFile, 0600);
                        // Verify permissions were set correctly
                        $currentPerms = @fileperms($keyFile);
                        if ($currentPerms !== false) {
                            $permBits = $currentPerms & 0777;
                            // If file is world-readable or world-writable, try to fix
                            if (($permBits & 0004) || ($permBits & 0002)) {
                                // File is world-readable or world-writable, try to fix
                                @chmod($keyFile, 0640);
                            }
                        }
                        
                        $messages[] = 'Encryption key saved successfully!';
                        $messages[] = 'The system is now ready to encrypt API keys and sensitive data.';
                        $keyFileExists = true;
                        $isConfigured = true;
                        $keyGenerated = false; // Reset after save
                    } else {
                        $errors[] = 'Failed to write encryption key file.';
                        $errors[] = 'Check that the directory is writable: ' . $secretsDir;
                        $errors[] = 'You may need to run: <code>chmod 755 ' . $secretsDir . '</code>';
                        $errors[] = 'Or set the key as an environment variable instead.';
                    }
                }
            }
        }
    }
}

// If modal mode, return JSON for AJAX loading or render standalone modal
if ($isModal) {
    // Check if this is an AJAX request (either X-Requested-With header or ajax=1 parameter)
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || (isset($_GET['ajax']) && $_GET['ajax'] === '1');
    
    if ($isAjax) {
        // Return HTML content for modal
        ob_start();
        include __DIR__ . '/setup-encryption-key-modal-content.php';
        $content = ob_get_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'html' => $content]);
        exit;
    }
    
    // Otherwise render as standalone modal page
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Encryption Key - ABBIS</title>
        <link rel="stylesheet" href="<?php echo $rootPath; ?>/assets/css/styles.css">
        <style>
            body {
                margin: 0;
                padding: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .modal-wrapper {
                background: var(--card);
                border-radius: 12px;
                max-width: 900px;
                width: 95%;
                max-height: 95vh;
                overflow-y: auto;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                position: relative;
            }
            .modal-header {
                padding: 24px;
                border-bottom: 1px solid var(--border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: sticky;
                top: 0;
                background: var(--card);
                z-index: 10;
            }
            .modal-header h1 {
                margin: 0;
                font-size: 24px;
                color: var(--text);
            }
            .modal-close {
                background: none;
                border: none;
                font-size: 32px;
                color: var(--secondary);
                cursor: pointer;
                padding: 0;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                transition: all 0.2s;
            }
            .modal-close:hover {
                background: var(--hover);
                color: var(--text);
            }
            .modal-body {
                padding: 24px;
            }
        </style>
    </head>
    <body>
        <div class="modal-wrapper">
            <div class="modal-header">
                <h1>🔐 Encryption Key Setup</h1>
                <button class="modal-close" onclick="window.close()" title="Close">×</button>
            </div>
            <div class="modal-body">
                <?php include __DIR__ . '/setup-encryption-key-modal-content.php'; ?>
            </div>
        </div>
        <script>
            // Close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.close();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Regular page mode - include header/footer
$page_title = 'Setup Encryption Key';
require_once $rootPath . '/includes/header.php';
?>
<div class="container-fluid">
    <div class="page-header">
        <h1>🔐 Encryption Key Setup</h1>
        <p class="lead">
            Configure the encryption key required to securely store API keys and sensitive data in ABBIS.
        </p>
    </div>

    <?php include __DIR__ . '/setup-encryption-key-modal-content.php'; ?>
</div>

<script>
function copyKey() {
    const keyText = document.getElementById('keyDisplay')?.textContent.trim();
    if (!keyText) return;
    
    const textarea = document.createElement('textarea');
    textarea.value = keyText;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        const btn = document.getElementById('copyBtn');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '✅ Copied!';
            btn.style.background = '#10b981';
            btn.style.borderColor = '#10b981';
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = '';
                btn.style.borderColor = '';
            }, 2000);
        }
    } catch (err) {
        alert('Failed to copy. Please select and copy manually.');
    }
    
    document.body.removeChild(textarea);
}

// Open as modal function
function openEncryptionKeyModal() {
    const modal = document.createElement('div');
    modal.id = 'encryptionKeyModal';
    modal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;';
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: var(--card); border-radius: 12px; max-width: 900px; width: 95%; max-height: 95vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); position: relative;';
    
    modalContent.innerHTML = `
        <div style="padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--card); z-index: 10;">
            <h1 style="margin: 0; font-size: 24px; color: var(--text);">🔐 Encryption Key Setup</h1>
            <button onclick="closeEncryptionKeyModal()" style="background: none; border: none; font-size: 32px; color: var(--secondary); cursor: pointer; padding: 0; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--hover)'; this.style.color='var(--text)';" onmouseout="this.style.background='none'; this.style.color='var(--secondary)';" title="Close">×</button>
        </div>
        <div id="modalBody" style="padding: 24px;">
            <div style="text-align: center; padding: 40px;">
                <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid var(--primary); border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="margin-top: 16px; color: var(--secondary);">Loading...</p>
            </div>
        </div>
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
    `;
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Load content via AJAX
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
    const fetchUrl = basePath + '/setup-encryption-key.php?modal=1&ajax=1';
    
    fetch(fetchUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                document.getElementById('modalBody').innerHTML = data.html;
                // Re-initialize copy function
                if (typeof copyKey === 'function') {
                    // Function is already defined globally
                }
            } else {
                document.getElementById('modalBody').innerHTML = '<div class="alert alert-danger">Failed to load content. Please refresh the page.</div>';
            }
        })
        .catch(error => {
            console.error('Error loading modal:', error);
            document.getElementById('modalBody').innerHTML = '<div class="alert alert-danger">Error loading content. Please try again.</div>';
        });
    
    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeEncryptionKeyModal();
        }
    });
    
    // Close on ESC
    const escHandler = function(e) {
        if (e.key === 'Escape') {
            closeEncryptionKeyModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
    
    // Handle form submissions inside modal via AJAX
    modalContent.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.tagName === 'FORM' && (form.querySelector('[name="generate_key"]') || form.querySelector('[name="save_to_file"]'))) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Processing...';
            }
            
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            const fetchUrl = basePath + '/setup-encryption-key.php?modal=1&ajax=1';
            
            fetch(fetchUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.html) {
                    document.getElementById('modalBody').innerHTML = data.html;
                    // Re-initialize copy function
                    if (typeof copyKey === 'function') {
                        // Function is already defined globally
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to process request'));
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }
    });
}

function closeEncryptionKeyModal() {
    const modal = document.getElementById('encryptionKeyModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
    }
}
</script>

<?php require_once $rootPath . '/includes/footer.php'; ?>
