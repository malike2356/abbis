<?php
/**
 * Encryption Key Setup Modal Content
 * This file contains the main content for the encryption key setup modal
 */

if (!isset($errors)) $errors = [];
if (!isset($messages)) $messages = [];
if (!isset($keyGenerated)) $keyGenerated = false;
if (!isset($keyToSet)) $keyToSet = '';
if (!isset($currentKey)) $currentKey = getenv('ABBIS_ENCRYPTION_KEY');
if (!isset($keyFileExists)) {
    $rootPath = dirname(__DIR__, 2);
    $keyFile = $rootPath . '/config/secrets/encryption.key';
    $keyFileExists = file_exists($keyFile);
}
if (!isset($isConfigured)) $isConfigured = $currentKey || $keyFileExists;
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul style="margin:0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($messages): ?>
    <div class="alert alert-success">
        <ul style="margin:0; padding-left: 20px;">
            <?php foreach ($messages as $msg): ?>
                <li><?php echo $msg; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Status Banner -->
<div class="dashboard-card" style="margin-bottom: 24px; <?php echo $isConfigured ? 'border-left: 4px solid #10b981;' : 'border-left: 4px solid #f59e0b;'; ?>">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="margin: 0 0 8px 0; font-size: 20px; color: var(--text);">
                <?php echo $isConfigured ? '✅ Encryption Key Configured' : '⚠️ Encryption Key Required'; ?>
            </h2>
            <p style="margin: 0; color: var(--secondary); font-size: 14px;">
                <?php if ($isConfigured): ?>
                    Your encryption key is set up and ready to use. API keys can now be encrypted securely.
                <?php else: ?>
                    An encryption key is required before you can save API keys. Follow the steps below to set it up.
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <span class="badge <?php echo $currentKey ? 'badge-success' : 'badge-danger'; ?>" style="font-size: 12px;">
                <?php echo $currentKey ? '✅' : '❌'; ?> Environment Variable
            </span>
            <span class="badge <?php echo $keyFileExists ? 'badge-success' : 'badge-warning'; ?>" style="font-size: 12px;">
                <?php echo $keyFileExists ? '✅' : '⚠️'; ?> Key File
            </span>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Current Configuration -->
    <div class="dashboard-card">
        <h2 style="margin: 0 0 20px 0; font-size: 18px; color: var(--primary);">📊 Current Configuration</h2>
        
        <div style="margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <strong style="color: var(--text); font-size: 14px;">Environment Variable</strong>
                <?php if ($currentKey): ?>
                    <span class="badge badge-success">✅ Configured</span>
                <?php else: ?>
                    <span class="badge badge-danger">❌ Not Set</span>
                <?php endif; ?>
            </div>
            <?php if ($currentKey): ?>
                <small style="color: var(--secondary); display: block; margin-top: 4px; font-family: monospace; font-size: 12px;">
                    Key detected (first 10 chars: <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;"><?php echo e(substr($currentKey, 0, 10)); ?>...</code>)
                </small>
            <?php else: ?>
                <small style="color: var(--secondary); display: block; margin-top: 4px; font-size: 13px;">
                    <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">ABBIS_ENCRYPTION_KEY</code> environment variable is not set
                </small>
            <?php endif; ?>
        </div>
        
        <div style="border-top: 1px solid var(--border); padding-top: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <strong style="color: var(--text); font-size: 14px;">Key File</strong>
                <?php if ($keyFileExists): ?>
                    <span class="badge badge-success">✅ Found</span>
                <?php else: ?>
                    <span class="badge badge-warning">⚠️ Not Found</span>
                <?php endif; ?>
            </div>
            <small style="color: var(--secondary); display: block; margin-top: 4px; font-family: monospace; word-break: break-all; font-size: 12px;">
                <?php echo e($keyFile ?? 'config/secrets/encryption.key'); ?>
            </small>
        </div>
    </div>

    <!-- Generate Key -->
    <div class="dashboard-card">
        <h2 style="margin: 0 0 20px 0; font-size: 18px; color: var(--primary);">🔑 Generate Encryption Key</h2>
        
        <p style="color: var(--secondary); margin-bottom: 20px; line-height: 1.6; font-size: 14px;">
            Generate a cryptographically secure encryption key. This key will be used to encrypt 
            all API keys and sensitive data stored in ABBIS.
        </p>
        
        <?php if ($keyGenerated): ?>
            <div style="background: #eff6ff; border: 1px solid #3b82f6; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <strong style="display: block; margin-bottom: 12px; color: #1e40af; font-size: 14px;">
                    ✅ Encryption Key Generated
                </strong>
                <p style="margin: 0 0 12px 0; font-size: 13px; color: var(--text);">
                    Your encryption key has been generated. Copy it below and save it to a file.
                </p>
                
                <div style="background: #1e293b; color: #e2e8f0; border: 1px solid #334155; border-radius: 6px; padding: 12px; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; line-height: 1.6; word-break: break-all; margin: 12px 0; position: relative;">
                    <div style="position: absolute; top: -10px; left: 12px; background: #1e293b; padding: 0 6px; font-size: 14px;">🔑</div>
                    <div id="keyDisplay" style="margin-top: 4px;"><?php echo e($keyToSet); ?></div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap;">
                    <button onclick="copyKey()" class="btn btn-primary" id="copyBtn" type="button">
                        📋 Copy Key
                    </button>
                    <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); handleSaveKey(this);">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="encryption_key" value="<?php echo e($keyToSet); ?>">
                        <button type="submit" name="save_to_file" class="btn btn-success">
                            💾 Save to File
                        </button>
                    </form>
                </div>
                <script>
                function handleSaveKey(form) {
                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn ? submitBtn.innerHTML : '';
                    
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '⏳ Saving...';
                    }
                    
                    // Get the base URL from the current page
                    const currentPath = window.location.pathname;
                    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
                    const url = basePath + '/setup-encryption-key.php?modal=1&ajax=1';
                    
                    fetch(url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.html) {
                            // Replace the entire modal body with new content
                            const modalBody = document.getElementById('modalBody') || document.querySelector('[id*="modal"]');
                            if (modalBody) {
                                modalBody.innerHTML = data.html;
                            } else {
                                // If not in modal, reload page
                                window.location.reload();
                            }
                        } else {
                            alert('Error: ' + (data.message || 'Failed to save key'));
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
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
                </script>
            </div>
        <?php else: ?>
            <form method="POST" onsubmit="event.preventDefault(); handleGenerateKey(this);">
                <?php echo CSRF::getTokenField(); ?>
                <button type="submit" name="generate_key" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 15px;">
                    🎲 Generate Encryption Key
                </button>
            </form>
            <script>
            function handleGenerateKey(form) {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : '';
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Generating...';
                }
                
                // Get the base URL from the current page
                const currentPath = window.location.pathname;
                const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
                const url = basePath + '/setup-encryption-key.php?modal=1&ajax=1';
                
                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.html) {
                        // Replace the entire modal body with new content
                        const modalBody = document.getElementById('modalBody') || document.querySelector('[id*="modal"]');
                        if (modalBody) {
                            modalBody.innerHTML = data.html;
                        } else {
                            // If not in modal, reload page
                            window.location.reload();
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Failed to generate key'));
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
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
            </script>
            
            <?php if ($isConfigured): ?>
                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; border-radius: 6px; margin-top: 16px;">
                    <strong style="font-size: 13px;">ℹ️ Note:</strong>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text);">
                        A key is already configured. Generating a new key will replace the existing one. 
                        Make sure to back up the current key first if you need to decrypt existing data.
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Setup Instructions -->
<div class="dashboard-card" style="margin-top: 24px;">
    <h2 style="margin: 0 0 20px 0; font-size: 18px; color: var(--primary);">📖 Setup Instructions</h2>
    
    <!-- Method 1 -->
    <div style="margin-bottom: 32px;">
        <h3 style="display: flex; align-items: center; gap: 12px; margin: 0 0 12px 0; font-size: 16px; color: var(--text);">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; font-weight: 700; font-size: 14px;">1</span>
            <span>Save to File (Recommended for Development)</span>
        </h3>
        <div style="padding-left: 40px;">
            <p style="color: var(--secondary); margin-bottom: 16px; font-size: 14px; line-height: 1.6;">
                The easiest method for local development. The key will be saved to a file that ABBIS can automatically read.
            </p>
            <ol style="margin: 0; padding-left: 20px; color: var(--text); line-height: 1.8; font-size: 14px;">
                <li>Click <strong>"Generate Encryption Key"</strong> in the panel above</li>
                <li>Click <strong>"Save to File"</strong> to automatically save it</li>
                <li>The system will create <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">config/secrets/encryption.key</code> automatically</li>
                <li>You're done! The system will use this key automatically</li>
            </ol>
        </div>
    </div>

    <!-- Method 2 -->
    <div style="border-top: 1px solid var(--border); padding-top: 32px; margin-bottom: 32px;">
        <h3 style="display: flex; align-items: center; gap: 12px; margin: 0 0 12px 0; font-size: 16px; color: var(--text);">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; font-weight: 700; font-size: 14px;">2</span>
            <span>Set Environment Variable (Recommended for Production)</span>
        </h3>
        <div style="padding-left: 40px;">
            <p style="color: var(--secondary); margin-bottom: 16px; font-size: 14px; line-height: 1.6;">
                For production servers, set the key as an environment variable for better security.
            </p>
            
            <div style="margin: 20px 0;">
                <strong style="display: block; margin-bottom: 8px; color: var(--text); font-size: 14px;">Linux / Mac:</strong>
                <p style="margin: 0 0 8px 0; color: var(--secondary); font-size: 13px;">Add to <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">~/.bashrc</code>, <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">~/.zshrc</code>, or <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">/etc/environment</code>:</p>
                <div style="background: #1e293b; color: #e2e8f0; padding: 12px 16px; border-radius: 6px; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; overflow-x: auto; margin: 8px 0;">
export ABBIS_ENCRYPTION_KEY="your-generated-key-here"
                </div>
                <p style="margin: 8px 0 0 0; color: var(--secondary); font-size: 12px;">
                    Then run: <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">source ~/.bashrc</code> or restart your terminal
                </p>
            </div>

            <div style="margin: 20px 0;">
                <strong style="display: block; margin-bottom: 8px; color: var(--text); font-size: 14px;">XAMPP (Windows):</strong>
                <p style="margin: 0; color: var(--secondary); font-size: 13px;">
                    Set as a system environment variable through Windows Settings, or add to your Apache configuration.
                </p>
            </div>

            <div style="margin: 20px 0;">
                <strong style="display: block; margin-bottom: 8px; color: var(--text); font-size: 14px;">Docker:</strong>
                <p style="margin: 0 0 8px 0; color: var(--secondary); font-size: 13px;">Add to <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">docker-compose.yml</code> or <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">.env</code> file:</p>
                <div style="background: #1e293b; color: #e2e8f0; padding: 12px 16px; border-radius: 6px; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; overflow-x: auto; margin: 8px 0;">
ABBIS_ENCRYPTION_KEY=your-generated-key-here
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);">
                <p style="margin: 0; color: var(--secondary); font-size: 13px;">
                    <strong>After setting the environment variable:</strong> Restart your web server for the changes to take effect.
                </p>
            </div>
        </div>
    </div>

    <!-- Security Warning -->
    <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 6px; margin-top: 24px;">
        <strong style="display: block; margin-bottom: 12px; color: #92400e; font-size: 14px;">⚠️ Security Best Practices</strong>
        <ul style="margin: 0; padding-left: 20px; color: var(--text); line-height: 1.8; font-size: 13px;">
            <li><strong>Never commit</strong> the encryption key to version control (Git, SVN, etc.)</li>
            <li><strong>Back up the key securely</strong> - you'll need it to decrypt existing data if you reinstall</li>
            <li><strong>If you lose the key</strong>, all encrypted data (API keys, etc.) cannot be recovered</li>
            <li><strong>Restrict file permissions:</strong> <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">chmod 600 config/secrets/encryption.key</code></li>
            <li><strong>Rotate keys periodically</strong> for enhanced security (maintain old keys to decrypt historical data)</li>
        </ul>
    </div>
    
    <!-- AI Migration Notice -->
    <?php
    $rootPath = dirname(__DIR__, 2);
    try {
        $pdo = getDBConnection();
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'ai_usage_logs'");
        $aiTablesExist = $checkStmt->rowCount() > 0;
    } catch (Exception $e) {
        $aiTablesExist = false;
    }
    ?>
    <?php if (!$aiTablesExist): ?>
        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 6px; margin-top: 24px;">
            <strong style="display: block; margin-bottom: 12px; color: #1e40af; font-size: 14px;">ℹ️ AI Tables Migration Required</strong>
            <p style="margin: 0 0 12px 0; color: var(--text); font-size: 13px; line-height: 1.6;">
                To enable AI usage logging and governance features, you need to run the AI migration to create the required database tables.
            </p>
            <a href="run-ai-migration.php" class="btn btn-primary" style="text-decoration: none; display: inline-block; margin-top: 8px;">
                🔄 Run AI Migration
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<?php if ($isConfigured): ?>
    <div class="dashboard-card" style="margin-top: 24px; border-left: 4px solid #10b981;">
        <div style="text-align: center;">
            <h3 style="margin: 0 0 12px 0; color: var(--text); font-size: 18px;">✅ Setup Complete!</h3>
            <p style="margin: 0 0 20px 0; color: var(--secondary); font-size: 14px;">
                Your encryption key is configured. You can now configure AI providers and save API keys securely.
            </p>
            <a href="../ai-governance.php" class="btn btn-primary">
                → Go to AI Governance
            </a>
        </div>
    </div>
<?php endif; ?>

