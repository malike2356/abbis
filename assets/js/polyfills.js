/**
 * ABBIS Browser Compatibility Polyfills
 * Only loads if needed for older browsers
 */

(function() {
    'use strict';
    
    // Check if fetch API is available
    if (!window.fetch) {
        // Load fetch polyfill from CDN
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/whatwg-fetch@3.6.2/dist/fetch.umd.js';
        script.onload = function() {
            console.log('Fetch polyfill loaded');
        };
        document.head.appendChild(script);
    }
    
    // Check if CSS Variables are supported
    if (!CSS || !CSS.supports || !CSS.supports('color', 'var(--fake-var)')) {
        // CSS Variables polyfill - would need additional library
        console.warn('CSS Variables not supported. Some styling may be affected.');
    }
    
    // Check for localStorage support
    try {
        const test = '__localStorage_test__';
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
    } catch (e) {
        console.warn('localStorage not available. Theme preferences may not persist.');
    }
    
    // Add class to body for browser detection
    // Use a function that waits for body to be available
    function addBrowserClass() {
        if (!document.body) {
            // If body doesn't exist yet, wait for DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addBrowserClass);
            } else {
                // Fallback: try again after a short delay
                setTimeout(addBrowserClass, 50);
            }
            return;
        }
        
        const ua = navigator.userAgent.toLowerCase();
        const isIE = /msie|trident/.test(ua);
        const isEdge = /edg/.test(ua);
        const isFirefox = /firefox/.test(ua);
        const isChrome = /chrome/.test(ua) && !/edge|edg|opr/.test(ua);
        const isSafari = /safari/.test(ua) && !/chrome|chromium|edg/.test(ua);
        
        if (isIE) {
            document.body.classList.add('browser-ie');
            console.warn('Internet Explorer detected. Some features may not work. Please upgrade to a modern browser.');
        } else if (isEdge) {
            document.body.classList.add('browser-edge');
        } else if (isFirefox) {
            document.body.classList.add('browser-firefox');
        } else if (isChrome) {
            document.body.classList.add('browser-chrome');
        } else if (isSafari) {
            document.body.classList.add('browser-safari');
        }
    }
    
    // Call the function
    addBrowserClass();
    
    // Browser version detection (simplified)
    function checkBrowserVersion() {
        if (!document.body) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkBrowserVersion);
            } else {
                setTimeout(checkBrowserVersion, 50);
            }
            return;
        }
        
        const ua = navigator.userAgent.toLowerCase();
        const isIE = /msie|trident/.test(ua);
        
        if (isIE) {
            const match = ua.match(/msie (\d+)/) || ua.match(/rv:(\d+)/);
            if (match) {
                const version = parseInt(match[1]);
                if (version < 11) {
                    document.body.classList.add('browser-unsupported');
                    // Show upgrade message
                    const banner = document.createElement('div');
                    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#ef4444;color:#fff;padding:10px;text-align:center;z-index:10000;font-size:14px;';
                    banner.innerHTML = '⚠️ Your browser is outdated and may not support all features. Please upgrade to Chrome, Firefox, Safari, or Edge for the best experience.';
                    document.body.appendChild(banner);
                    
                    // Hide after 10 seconds
                    setTimeout(function() {
                        banner.style.opacity = '0';
                        banner.style.transition = 'opacity 0.5s';
                        setTimeout(function() {
                            banner.remove();
                        }, 500);
                    }, 10000);
                }
            }
        }
    }
    
    checkBrowserVersion();
})();

