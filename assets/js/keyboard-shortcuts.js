/**
 * Keyboard Shortcuts for ABBIS
 * Provides quick navigation and actions
 */

(function() {
    'use strict';
    
    const shortcuts = {
        // Navigation
        'g d': () => window.location.href = 'dashboard.php',
        'g r': () => window.location.href = 'field-reports.php',
        'g f': () => window.location.href = 'financial.php',
        'g c': () => window.location.href = 'crm-clients.php',
        'g w': () => window.location.href = 'resources.php?action=workers',
        'g m': () => window.location.href = 'resources.php?action=materials',
        'g a': () => window.location.href = 'analytics.php',
        
        // Actions
        'n r': () => {
            const btn = document.querySelector('a[href*="field-reports.php"]:not([href*="list"])');
            if (btn) btn.click();
        },
        'n c': () => {
            const btn = document.querySelector('button[onclick*="addClient"], a[href*="add-client"]');
            if (btn) btn.click();
        },
        'n w': () => {
            const btn = document.querySelector('button[onclick*="addWorker"], a[href*="add-worker"]');
            if (btn) btn.click();
        },
        
        // Search
        '/': (e) => {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"], input[placeholder*="Search"], #search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        },
        
        // Escape - close modals
        'Escape': () => {
            const modals = document.querySelectorAll('.modal, [class*="modal"]');
            modals.forEach(modal => {
                if (modal.style.display === 'flex' || modal.style.display === 'block') {
                    modal.style.display = 'none';
                    modal.classList.remove('active');
                }
            });
        },
        
        // Save (Ctrl+S or Cmd+S)
        's': (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const saveBtn = document.querySelector('button[type="submit"], button[onclick*="save"], button[onclick*="Save"]');
                if (saveBtn && !saveBtn.disabled) {
                    saveBtn.click();
                }
            }
        }
    };
    
    let currentKeys = [];
    let keyTimeout;
    
    function handleKeyPress(e) {
        // Don't trigger shortcuts when typing in inputs
        if (e.target.tagName === 'INPUT' || 
            e.target.tagName === 'TEXTAREA' || 
            e.target.tagName === 'SELECT' ||
            e.target.isContentEditable) {
            return;
        }
        
        // Handle Escape key
        if (e.key === 'Escape') {
            shortcuts['Escape'](e);
            return;
        }
        
        // Handle Ctrl/Cmd+S
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            shortcuts['s'](e);
            return;
        }
        
        // Handle / for search
        if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
            shortcuts['/'](e);
            return;
        }
        
        // Handle g + key sequences (e.g., 'g' then 'd' for dashboard)
        if (e.key === 'g' && !e.ctrlKey && !e.metaKey) {
            currentKeys = ['g'];
            clearTimeout(keyTimeout);
            keyTimeout = setTimeout(() => {
                currentKeys = [];
            }, 1000);
            return;
        }
        
        // Handle n + key sequences (e.g., 'n' then 'r' for new report)
        if (e.key === 'n' && !e.ctrlKey && !e.metaKey && currentKeys.length === 0) {
            currentKeys = ['n'];
            clearTimeout(keyTimeout);
            keyTimeout = setTimeout(() => {
                currentKeys = [];
            }, 1000);
            return;
        }
        
        // If we have a key sequence, check for completion
        if (currentKeys.length > 0) {
            const sequence = currentKeys.join(' ') + ' ' + e.key.toLowerCase();
            if (shortcuts[sequence]) {
                e.preventDefault();
                shortcuts[sequence](e);
                currentKeys = [];
                clearTimeout(keyTimeout);
            } else {
                currentKeys = [];
                clearTimeout(keyTimeout);
            }
        }
    }
    
    // Initialize
    document.addEventListener('keydown', handleKeyPress);
    
    // Show shortcuts help (Ctrl+? or Cmd+?)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === '?') {
            e.preventDefault();
            showShortcutsHelp();
        }
    });
    
    function showShortcutsHelp() {
        const help = document.createElement('div');
        help.id = 'shortcuts-help';
        help.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 10000;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        `;
        
        help.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">⌨️ Keyboard Shortcuts</h2>
                <button onclick="this.closest('#shortcuts-help').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div style="display: grid; gap: 12px;">
                <div>
                    <h3 style="margin: 0 0 8px 0; font-size: 14px; color: var(--primary);">Navigation</h3>
                    <div style="display: grid; gap: 6px; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>d</kbd></span>
                            <span>Go to Dashboard</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>r</kbd></span>
                            <span>Go to Reports</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>f</kbd></span>
                            <span>Go to Finance</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>c</kbd></span>
                            <span>Go to Clients</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>w</kbd></span>
                            <span>Go to Workers</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>m</kbd></span>
                            <span>Go to Materials</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>g</kbd> then <kbd>a</kbd></span>
                            <span>Go to Analytics</span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 style="margin: 0 0 8px 0; font-size: 14px; color: var(--primary);">Actions</h3>
                    <div style="display: grid; gap: 6px; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>n</kbd> then <kbd>r</kbd></span>
                            <span>New Report</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>n</kbd> then <kbd>c</kbd></span>
                            <span>New Client</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>n</kbd> then <kbd>w</kbd></span>
                            <span>New Worker</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>/</kbd></span>
                            <span>Focus Search</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>Esc</kbd></span>
                            <span>Close Modal</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><kbd>Ctrl</kbd> + <kbd>S</kbd></span>
                            <span>Save Form</span>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center; font-size: 12px; color: var(--secondary);">
                Press <kbd>Ctrl</kbd> + <kbd>?</kbd> anytime to show this help
            </div>
        `;
        
        // Add styles for kbd tags
        const style = document.createElement('style');
        style.textContent = `
            kbd {
                background: var(--bg);
                border: 1px solid var(--border);
                border-radius: 4px;
                padding: 2px 6px;
                font-family: monospace;
                font-size: 12px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(help);
        
        // Close on click outside
        help.addEventListener('click', (e) => {
            if (e.target === help) {
                help.remove();
            }
        });
        
        // Close on Escape
        const closeHandler = (e) => {
            if (e.key === 'Escape') {
                help.remove();
                document.removeEventListener('keydown', closeHandler);
            }
        };
        document.addEventListener('keydown', closeHandler);
    }
    
    // Make help function globally available
    window.showShortcutsHelp = showShortcutsHelp;
})();

