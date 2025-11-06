/**
 * Main JavaScript Application
 */
class ABBISApp {
    constructor() {
        this.init();
    }

    init() {
        this.initializeTheme();
        this.initializeEventListeners();
    }

    // Theme Management (supports light, dark, system)
    initializeTheme() {
        // Back-compat: migrate old key to new mode if present
        const legacy = localStorage.getItem('abbis_theme');
        if (!localStorage.getItem('abbis_theme_mode') && legacy) {
            localStorage.setItem('abbis_theme_mode', legacy === 'dark' ? 'dark' : 'light');
        }

        const mode = localStorage.getItem('abbis_theme_mode') || 'system';
        const effective = this.resolveThemeFromMode(mode);
        this.applyTheme(effective);
        this.updateThemeIcon(mode, effective);

        // Watch system changes when in system mode
        if (!this._mediaListenerSet) {
            try {
                const mq = window.matchMedia('(prefers-color-scheme: dark)');
                mq.addEventListener('change', () => {
                    const currentMode = localStorage.getItem('abbis_theme_mode') || 'system';
                    if (currentMode === 'system') {
                        const eff = this.resolveThemeFromMode('system');
                        this.applyTheme(eff);
                        this.updateThemeIcon('system', eff);
                        this.saveThemeToSession(eff);
                    }
                });
                this._mediaListenerSet = true;
            } catch (e) { /* older browsers */ }
        }

        // Attach theme toggle listener (cycle modes)
        const themeToggles = document.querySelectorAll('.theme-toggle');
        themeToggles.forEach(themeToggle => {
            if (themeToggle && themeToggle.dataset.themeListener !== 'attached') {
                themeToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleThemeMode();
                });
                themeToggle.dataset.themeListener = 'attached';
            }
        });
    }

    toggleThemeMode() {
        const order = ['system', 'light', 'dark'];
        const current = localStorage.getItem('abbis_theme_mode') || 'system';
        const idx = order.indexOf(current);
        const nextMode = order[(idx + 1) % order.length];
        localStorage.setItem('abbis_theme_mode', nextMode);
        const effective = this.resolveThemeFromMode(nextMode);
        this.applyTheme(effective);
        this.updateThemeIcon(nextMode, effective);
        this.saveThemeToSession(effective);
    }

    resolveThemeFromMode(mode) {
        if (mode === 'light' || mode === 'dark') return mode;
        try {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } catch (e) {
            return 'light';
        }
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body && document.body.setAttribute('data-theme', theme);
    }

    updateThemeIcon(mode, effectiveTheme) {
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            const icon = themeToggle.querySelector('.theme-icon');
            if (icon) {
                if (mode === 'system') {
                    icon.textContent = '🖥️';
                } else {
                    icon.textContent = effectiveTheme === 'dark' ? '☀️' : '🌙';
                }
            }
        }
    }

    async saveThemeToSession(theme) {
        try {
            await fetch('api/save-theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ theme: theme })
            });
        } catch (e) {
            // Silent fail
        }
    }

    // Event Listeners
    initializeEventListeners() {
        // Form submissions with CSRF
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.classList.contains('ajax-form')) {
                e.preventDefault();
                this.submitFormAjax(form);
            }
        });
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        // Mobile menu toggle
        this.initializeMobileMenu();
    }
    
    // Mobile Menu Management
    initializeMobileMenu() {
        const menuToggle = document.getElementById('mobileMenuToggle');
        const mainNav = document.getElementById('mainNav');
        
        if (menuToggle && mainNav) {
            // Function to close menu
            const closeMenu = () => {
                mainNav.classList.remove('active');
                menuToggle.classList.remove('active');
                const icon = menuToggle.querySelector('.hamburger-icon');
                if (icon) {
                    icon.textContent = '☰';
                }
                document.body.style.overflow = '';
                // Hide after transition
                setTimeout(() => {
                    if (!mainNav.classList.contains('active')) {
                        mainNav.style.display = '';
                    }
                }, 400);
            };
            
            // Function to open menu
            const openMenu = () => {
                // Force display first
                mainNav.style.display = 'block';
                // Small delay to ensure DOM update
                setTimeout(() => {
                    mainNav.classList.add('active');
                    menuToggle.classList.add('active');
                    const icon = menuToggle.querySelector('.hamburger-icon');
                    if (icon) {
                        icon.textContent = '✕';
                    }
                    document.body.style.overflow = 'hidden';
                    // Force repaint
                    void mainNav.offsetHeight;
                }, 10);
            };
            
            // Toggle menu on hamburger click
            menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (mainNav.classList.contains('active')) {
                    closeMenu();
                } else {
                    openMenu();
                }
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && 
                    mainNav.classList.contains('active') && 
                    !mainNav.contains(e.target) && 
                    !menuToggle.contains(e.target)) {
                    closeMenu();
                }
            });
            
            // Close menu when clicking a nav item (on mobile)
            mainNav.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        setTimeout(closeMenu, 200);
                    }
                });
            });
            
            // Handle window resize - close menu and reset on desktop
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (window.innerWidth > 768) {
                        closeMenu();
                        document.body.style.overflow = '';
                    }
                }, 250);
            });
        }
    }

    // AJAX Form Submission
    async submitFormAjax(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            this.setLoadingState(submitBtn, true);
            
            const response = await fetch(form.action || form.dataset.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', result.message);
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                } else if (form.dataset.reload) {
                    setTimeout(() => location.reload(), 1500);
                }
            } else {
                this.showAlert('error', result.message || 'An error occurred');
                if (result.errors) {
                    this.displayFieldErrors(result.errors);
                }
            }
        } catch (error) {
            this.showAlert('error', 'Network error. Please try again.');
            console.error('Form submission error:', error);
        } finally {
            this.setLoadingState(submitBtn, false, originalText);
        }
    }

    setLoadingState(button, isLoading, originalText = null) {
        if (isLoading) {
            button.disabled = true;
            button.innerHTML = '<div class="spinner"></div> Processing...';
            button.classList.add('loading');
        } else {
            button.disabled = false;
            button.innerHTML = originalText || button.getAttribute('data-original-text') || 'Submit';
            button.classList.remove('loading');
        }
    }

    showAlert(type, message) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
        alert.innerHTML = `
            <span>${message}</span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Add to page
        const container = document.querySelector('.container-fluid') || document.body;
        container.insertBefore(alert, container.firstChild);
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }

    displayFieldErrors(errors) {
        // Clear previous errors
        document.querySelectorAll('.field-error').forEach(el => el.remove());
        document.querySelectorAll('.form-control.error').forEach(el => el.classList.remove('error'));
        
        // Display new errors
        if (typeof errors === 'object') {
            Object.keys(errors).forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('error');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'field-error';
                    errorDiv.textContent = errors[field];
                    input.parentElement.appendChild(errorDiv);
                }
            });
        }
    }
}

// Utility Functions
class ABBISCalculations {
    static calculateDuration(startTime, finishTime) {
        if (!startTime || !finishTime) return 0;
        
        const start = this.timeToMinutes(startTime);
        const finish = this.timeToMinutes(finishTime);
        
        if (start === 0 || finish === 0) return 0;
        
        let diffMinutes = finish - start;
        if (diffMinutes < 0) diffMinutes += 24 * 60; // Cross midnight
        
        return diffMinutes;
    }

    static formatDuration(minutes) {
        if (!minutes || minutes === 0) return '0h 0m';
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours > 0) {
            return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
        }
        return `${mins}m`;
    }

    static timeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    static calculateTotalRPM(startRPM, finishRPM) {
        return Math.max(0, (finishRPM || 0) - (startRPM || 0));
    }

    static calculateTotalDepth(rodLength, rodsUsed) {
        return (rodLength || 0) * (rodsUsed || 0);
    }

    static calculateConstructionDepth(screenPipes, plainPipes) {
        // Each pipe is 3 meters
        // Formula: (screen pipes + plain pipes) * 3 meters per pipe
        const screen = parseFloat(screenPipes) || 0;
        const plain = parseFloat(plainPipes) || 0;
        return (screen + plain) * 3;
    }

    static calculateWorkerPay(units, rate, benefits, loanReclaim) {
        return Math.max(0, ((units || 0) * (rate || 0)) + (benefits || 0) - (loanReclaim || 0));
    }

    static calculateExpenseAmount(unitCost, quantity) {
        return (unitCost || 0) * (quantity || 0);
    }

    static formatCurrency(amount) {
        return `GHS ${parseFloat(amount || 0).toFixed(2)}`;
    }
}

// Initialize when DOM is loaded
function initializeABBISApp() {
    if (!window.abbisApp) {
        window.abbisApp = new ABBISApp();
    } else {
        // If already initialized, just ensure theme is initialized
        window.abbisApp.initializeTheme();
    }
}

// Run immediately if DOM is already loaded, otherwise wait
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeABBISApp);
} else {
    // DOM is already loaded
    initializeABBISApp();
}

// Export for use in other scripts
window.ABBISCalculations = ABBISCalculations;
