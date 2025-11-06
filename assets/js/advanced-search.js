/**
 * Advanced Search Functionality
 * Enhanced search with filters and suggestions
 */

(function() {
    'use strict';
    
    let searchTimeout = null;
    let searchResults = [];
    
    /**
     * Initialize advanced search
     */
    function init() {
        // Find search inputs
        const searchInputs = document.querySelectorAll('input[type="search"], input[placeholder*="Search"], #search, input[name="search"]');
        
        searchInputs.forEach(input => {
            // Add autocomplete attribute
            input.setAttribute('autocomplete', 'off');
            
            // Add event listeners
            input.addEventListener('input', handleSearchInput);
            input.addEventListener('keydown', handleSearchKeydown);
            input.addEventListener('focus', showSearchSuggestions);
            input.addEventListener('blur', () => {
                // Delay hiding to allow clicks
                setTimeout(hideSearchSuggestions, 200);
            });
        });
        
        // Global search trigger (Ctrl+K or Cmd+K)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = searchInputs[0];
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    }
    
    /**
     * Handle search input
     */
    function handleSearchInput(e) {
        const query = e.target.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            hideSearchSuggestions();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query, e.target);
        }, 300);
    }
    
    /**
     * Perform search
     */
    async function performSearch(query, input) {
        try {
            const response = await fetch(`../api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.results) {
                searchResults = data.results;
                showSearchResults(searchResults, input);
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }
    
    /**
     * Show search results
     */
    function showSearchResults(results, input) {
        let dropdown = document.getElementById('search-results-dropdown');
        
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.id = 'search-results-dropdown';
            dropdown.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                max-height: 400px;
                overflow-y: auto;
                margin-top: 4px;
            `;
            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(dropdown);
        }
        
        if (results.length === 0) {
            dropdown.innerHTML = '<div style="padding: 16px; text-align: center; color: var(--secondary);">No results found</div>';
            dropdown.style.display = 'block';
            return;
        }
        
        // Group results by type
        const grouped = {};
        results.forEach(result => {
            const type = result.type || 'other';
            if (!grouped[type]) {
                grouped[type] = [];
            }
            grouped[type].push(result);
        });
        
        let html = '';
        Object.keys(grouped).forEach(type => {
            html += `<div style="padding: 8px 12px; background: var(--bg); font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--secondary);">${type}</div>`;
            grouped[type].slice(0, 5).forEach(result => {
                html += `
                    <a href="${result.url}" style="display: block; padding: 12px; border-bottom: 1px solid var(--border); text-decoration: none; color: var(--text); transition: background 0.2s;" 
                       onmouseover="this.style.background='var(--bg)'" 
                       onmouseout="this.style.background='transparent'">
                        <div style="font-weight: 600; margin-bottom: 4px;">${result.title}</div>
                        ${result.subtitle ? `<div style="font-size: 12px; color: var(--secondary);">${result.subtitle}</div>` : ''}
                    </a>
                `;
            });
        });
        
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
    }
    
    /**
     * Show search suggestions (recent searches, quick links)
     */
    function showSearchSuggestions(e) {
        const input = e.target;
        const query = input.value.trim();
        
        if (query.length >= 2) {
            // Already showing results
            return;
        }
        
        // Show quick search suggestions
        let dropdown = document.getElementById('search-results-dropdown');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.id = 'search-results-dropdown';
            dropdown.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                margin-top: 4px;
            `;
            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(dropdown);
        }
        
        dropdown.innerHTML = `
            <div style="padding: 12px; font-size: 12px; color: var(--secondary);">
                <div style="margin-bottom: 8px; font-weight: 600;">Quick Search Tips:</div>
                <div style="margin-bottom: 4px;">• Type to search reports, clients, workers</div>
                <div style="margin-bottom: 4px;">• Use <kbd>Ctrl+K</kbd> or <kbd>Cmd+K</kbd> to focus search</div>
                <div>• Press <kbd>Enter</kbd> to search</div>
            </div>
        `;
        dropdown.style.display = 'block';
    }
    
    /**
     * Hide search suggestions
     */
    function hideSearchSuggestions() {
        const dropdown = document.getElementById('search-results-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
    
    /**
     * Handle search keydown
     */
    function handleSearchKeydown(e) {
        if (e.key === 'Escape') {
            hideSearchSuggestions();
            e.target.blur();
        } else if (e.key === 'Enter') {
            // Trigger search
            const query = e.target.value.trim();
            if (query.length >= 2) {
                window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            }
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

