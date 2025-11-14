/**
 * WordPress-style Media Library Picker
 * Use this component anywhere in the CMS to select media
 */

class MediaPicker {
    constructor(options = {}) {
        this.options = {
            targetInput: options.targetInput || null, // Input field to populate with selected URL
            targetPreview: options.targetPreview || null, // Element to show preview
            allowedTypes: options.allowedTypes || ['image'], // 'image', 'document', 'video', 'audio', 'all'
            multiple: options.multiple || false,
            onSelect: options.onSelect || null, // Callback when media is selected
            baseUrl: options.baseUrl || '/abbis3.2',
            ...options
        };
        
        this.selectedMedia = [];
        this.currentPage = 1;
        this.currentFilter = 'all';
        this.currentSearch = '';
        
        this.init();
    }
    
    init() {
        this.createModal();
        this.bindEvents();
    }
    
    createModal() {
        const modal = document.createElement('div');
        modal.className = 'media-picker-modal';
        modal.innerHTML = `
            <div class="media-picker-overlay" onclick="this.closest('.media-picker-modal').classList.remove('active')"></div>
            <div class="media-picker-content">
                <div class="media-picker-header">
                    <h2>📁 Media Library</h2>
                    <button class="media-picker-close" onclick="this.closest('.media-picker-modal').classList.remove('active')">×</button>
                </div>
                <div class="media-picker-toolbar">
                    <div class="media-picker-filters">
                        <button class="filter-btn ${this.currentFilter === 'all' ? 'active' : ''}" data-filter="all">All</button>
                        <button class="filter-btn ${this.currentFilter === 'image' ? 'active' : ''}" data-filter="image">Images</button>
                        <button class="filter-btn ${this.currentFilter === 'document' ? 'active' : ''}" data-filter="document">Documents</button>
                        <button class="filter-btn ${this.currentFilter === 'video' ? 'active' : ''}" data-filter="video">Videos</button>
                        <button class="filter-btn ${this.currentFilter === 'audio' ? 'active' : ''}" data-filter="audio">Audio</button>
                    </div>
                    <div class="media-picker-search">
                        <input type="text" placeholder="🔍 Search media..." class="search-input">
                        <button class="search-btn">Search</button>
                    </div>
                </div>
                <div class="media-picker-grid"></div>
                <div class="media-picker-pagination"></div>
                <div class="media-picker-footer">
                    <div class="selected-count">0 selected</div>
                    <div class="media-picker-actions">
                        <button class="btn-cancel" onclick="this.closest('.media-picker-modal').classList.remove('active')">Cancel</button>
                        <button class="btn-select" onclick="this.dispatchEvent(new CustomEvent('mediaSelect'))">Select Media</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.modal = modal;
        this.grid = modal.querySelector('.media-picker-grid');
        this.pagination = modal.querySelector('.pagination');
        
        // Add styles if not already added
        if (!document.getElementById('media-picker-styles')) {
            this.addStyles();
        }
    }
    
    addStyles() {
        const style = document.createElement('style');
        style.id = 'media-picker-styles';
        style.textContent = `
            .media-picker-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 100000;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .media-picker-modal.active {
                display: flex;
            }
            .media-picker-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                backdrop-filter: blur(4px);
            }
            .media-picker-content {
                position: relative;
                background: white;
                border-radius: 16px;
                width: 90vw;
                max-width: 1200px;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                z-index: 1;
            }
            .media-picker-header {
                padding: 20px 24px;
                border-bottom: 2px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .media-picker-header h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
                color: #1d2327;
            }
            .media-picker-close {
                background: none;
                border: none;
                font-size: 32px;
                cursor: pointer;
                color: #646970;
                padding: 0;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                transition: all 0.2s;
            }
            .media-picker-close:hover {
                background: #f6f7f7;
                color: #1d2327;
            }
            .media-picker-toolbar {
                padding: 16px 24px;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                gap: 16px;
                align-items: center;
                flex-wrap: wrap;
            }
            .media-picker-filters {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .filter-btn {
                padding: 8px 16px;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                background: white;
                color: #646970;
                font-weight: 600;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .filter-btn:hover {
                border-color: #2563eb;
                color: #2563eb;
            }
            .filter-btn.active {
                background: #2563eb;
                color: white;
                border-color: #2563eb;
            }
            .media-picker-search {
                display: flex;
                gap: 8px;
                flex: 1;
                min-width: 300px;
            }
            .search-input {
                flex: 1;
                padding: 10px 16px;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                font-size: 14px;
            }
            .search-input:focus {
                outline: none;
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            }
            .search-btn {
                padding: 10px 20px;
                background: #2563eb;
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .search-btn:hover {
                background: #1e40af;
            }
            .media-picker-grid {
                flex: 1;
                overflow-y: auto;
                padding: 24px;
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 16px;
                min-height: 400px;
            }
            .media-picker-item {
                background: white;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                overflow: hidden;
                cursor: pointer;
                transition: all 0.2s;
                position: relative;
            }
            .media-picker-item:hover {
                border-color: #2563eb;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .media-picker-item.selected {
                border-color: #2563eb;
                border-width: 3px;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            }
            .media-picker-item.selected::after {
                content: '✓';
                position: absolute;
                top: 8px;
                right: 8px;
                background: #2563eb;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 14px;
                z-index: 10;
            }
            .media-picker-thumbnail {
                width: 100%;
                height: 150px;
                object-fit: cover;
                background: #f6f7f7;
            }
            .media-picker-placeholder {
                width: 100%;
                height: 150px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                background: #f6f7f7;
            }
            .media-picker-info {
                padding: 12px;
            }
            .media-picker-name {
                font-size: 12px;
                font-weight: 600;
                color: #1d2327;
                word-break: break-word;
                margin-bottom: 4px;
            }
            .media-picker-meta {
                font-size: 11px;
                color: #646970;
            }
            .media-picker-pagination {
                padding: 16px 24px;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: center;
                gap: 8px;
            }
            .pagination-btn {
                padding: 8px 12px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                background: white;
                color: #1d2327;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.2s;
            }
            .pagination-btn:hover:not(.disabled) {
                background: #f6f7f7;
                border-color: #2563eb;
                color: #2563eb;
            }
            .pagination-btn.active {
                background: #2563eb;
                color: white;
                border-color: #2563eb;
            }
            .pagination-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .media-picker-footer {
                padding: 16px 24px;
                border-top: 2px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .selected-count {
                font-weight: 600;
                color: #646970;
            }
            .media-picker-actions {
                display: flex;
                gap: 12px;
            }
            .btn-cancel, .btn-select {
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                border: none;
            }
            .btn-cancel {
                background: #f6f7f7;
                color: #646970;
            }
            .btn-cancel:hover {
                background: #e2e8f0;
            }
            .btn-select {
                background: #2563eb;
                color: white;
            }
            .btn-select:hover {
                background: #1e40af;
            }
            .btn-select:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
        `;
        document.head.appendChild(style);
    }
    
    bindEvents() {
        // Filter buttons
        this.modal.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.modal.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.currentFilter = e.target.dataset.filter;
                this.currentPage = 1;
                this.loadMedia();
            });
        });
        
        // Search
        const searchInput = this.modal.querySelector('.search-input');
        const searchBtn = this.modal.querySelector('.search-btn');
        
        searchBtn.addEventListener('click', () => {
            this.currentSearch = searchInput.value;
            this.currentPage = 1;
            this.loadMedia();
        });
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.currentSearch = searchInput.value;
                this.currentPage = 1;
                this.loadMedia();
            }
        });
        
        // Select button
        const selectBtn = this.modal.querySelector('.btn-select');
        selectBtn.addEventListener('click', () => {
            this.handleSelect();
        });
        
        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.close();
            }
        });
    }
    
    async loadMedia() {
        this.grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #646970;">Loading...</div>';
        
        const params = new URLSearchParams({
            type: this.currentFilter,
            search: this.currentSearch,
            page: this.currentPage
        });
        
        try {
            const response = await fetch(`${this.options.baseUrl}/cms/admin/media-picker.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderMedia(data.data);
                this.renderPagination(data.pagination);
            } else {
                this.grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #ef4444;">Error loading media</div>';
            }
        } catch (error) {
            this.grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #ef4444;">Error loading media</div>';
        }
    }
    
    renderMedia(mediaFiles) {
        this.grid.innerHTML = '';
        
        if (mediaFiles.length === 0) {
            this.grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #646970;">No media found</div>';
            return;
        }
        
        mediaFiles.forEach(media => {
            const item = document.createElement('div');
            item.className = 'media-picker-item';
            item.dataset.mediaId = media.id;
            item.dataset.mediaUrl = media.url;
            
            const thumbnail = media.is_image 
                ? `<img src="${media.url}" alt="${media.original_name}" class="media-picker-thumbnail" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                : '';
            
            const placeholder = media.is_image 
                ? `<div class="media-picker-placeholder" style="display: none;">🖼️</div>`
                : `<div class="media-picker-placeholder">${this.getFileIcon(media.file_type)}</div>`;
            
            item.innerHTML = `
                ${thumbnail}
                ${placeholder}
                <div class="media-picker-info">
                    <div class="media-picker-name" title="${media.original_name}">${this.truncate(media.original_name, 20)}</div>
                    <div class="media-picker-meta">${this.formatFileSize(media.file_size)}</div>
                </div>
            `;
            
            item.addEventListener('click', () => {
                this.toggleSelect(media);
            });
            
            this.grid.appendChild(item);
        });
        
        this.updateSelectedCount();
    }
    
    renderPagination(pagination) {
        const paginationEl = this.modal.querySelector('.media-picker-pagination');
        paginationEl.innerHTML = '';
        
        if (pagination.total_pages <= 1) return;
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.textContent = '← Previous';
        prevBtn.disabled = pagination.page === 1;
        if (pagination.page === 1) prevBtn.classList.add('disabled');
        prevBtn.addEventListener('click', () => {
            if (pagination.page > 1) {
                this.currentPage = pagination.page - 1;
                this.loadMedia();
            }
        });
        paginationEl.appendChild(prevBtn);
        
        // Page numbers
        const startPage = Math.max(1, pagination.page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = 'pagination-btn';
            if (i === pagination.page) pageBtn.classList.add('active');
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                this.currentPage = i;
                this.loadMedia();
            });
            paginationEl.appendChild(pageBtn);
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.textContent = 'Next →';
        nextBtn.disabled = pagination.page === pagination.total_pages;
        if (pagination.page === pagination.total_pages) nextBtn.classList.add('disabled');
        nextBtn.addEventListener('click', () => {
            if (pagination.page < pagination.total_pages) {
                this.currentPage = pagination.page + 1;
                this.loadMedia();
            }
        });
        paginationEl.appendChild(nextBtn);
    }
    
    toggleSelect(media) {
        if (!this.options.multiple) {
            this.selectedMedia = [media];
            this.modal.querySelectorAll('.media-picker-item').forEach(item => {
                item.classList.remove('selected');
            });
            const item = this.modal.querySelector(`[data-media-id="${media.id}"]`);
            if (item) item.classList.add('selected');
        } else {
            const index = this.selectedMedia.findIndex(m => m.id === media.id);
            if (index > -1) {
                this.selectedMedia.splice(index, 1);
                const item = this.modal.querySelector(`[data-media-id="${media.id}"]`);
                if (item) item.classList.remove('selected');
            } else {
                this.selectedMedia.push(media);
                const item = this.modal.querySelector(`[data-media-id="${media.id}"]`);
                if (item) item.classList.add('selected');
            }
        }
        
        this.updateSelectedCount();
    }
    
    updateSelectedCount() {
        const countEl = this.modal.querySelector('.selected-count');
        const selectBtn = this.modal.querySelector('.btn-select');
        
        const count = this.selectedMedia.length;
        countEl.textContent = `${count} selected`;
        selectBtn.disabled = count === 0;
    }
    
    handleSelect() {
        if (this.selectedMedia.length === 0) return;
        
        const selected = this.options.multiple ? this.selectedMedia : this.selectedMedia[0];
        
        // Update target input
        if (this.options.targetInput) {
            const input = typeof this.options.targetInput === 'string' 
                ? document.querySelector(this.options.targetInput)
                : this.options.targetInput;
            
            if (input) {
                if (this.options.multiple) {
                    input.value = this.selectedMedia.map(m => m.url).join(',');
                } else {
                    input.value = selected.url;
                }
            }
        }
        
        // Update preview
        if (this.options.targetPreview) {
            const preview = typeof this.options.targetPreview === 'string'
                ? document.querySelector(this.options.targetPreview)
                : this.options.targetPreview;
            
            if (preview) {
                if (this.options.multiple) {
                    preview.innerHTML = this.selectedMedia.map(m => 
                        m.is_image 
                            ? `<img src="${m.url}" style="max-width: 100px; margin: 4px; border-radius: 8px;">`
                            : `<div style="padding: 8px; margin: 4px; background: #f6f7f7; border-radius: 8px;">${m.original_name}</div>`
                    ).join('');
                } else {
                    if (selected.is_image) {
                        preview.innerHTML = `<img src="${selected.url}" style="max-width: 100%; border-radius: 8px;">`;
                    } else {
                        preview.innerHTML = `<div style="padding: 12px; background: #f6f7f7; border-radius: 8px;">${selected.original_name}</div>`;
                    }
                }
            }
        }
        
        // Callback
        if (this.options.onSelect) {
            this.options.onSelect(selected);
        }
        
        this.close();
    }
    
    open() {
        this.modal.classList.add('active');
        this.selectedMedia = [];
        this.currentPage = 1;
        this.loadMedia();
    }
    
    close() {
        this.modal.classList.remove('active');
    }
    
    getFileIcon(fileType) {
        if (fileType.includes('pdf')) return '📄';
        if (fileType.includes('word') || fileType.includes('document')) return '📝';
        if (fileType.includes('excel') || fileType.includes('spreadsheet')) return '📊';
        if (fileType.includes('video')) return '🎥';
        if (fileType.includes('audio')) return '🎵';
        if (fileType.includes('zip')) return '📦';
        return '📎';
    }
    
    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    truncate(str, length) {
        return str.length > length ? str.substring(0, length) + '...' : str;
    }
}

// Global function to open media picker
function openMediaPicker(options = {}) {
    const picker = new MediaPicker(options);
    picker.open();
    return picker;
}

