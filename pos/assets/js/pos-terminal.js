(() => {
    const app = document.getElementById('posApp');
    if (!app) return;

    const catalogUrl = app.dataset.apiCatalog;
    const inventoryUrl = app.dataset.apiInventory;
    const salesUrl = app.dataset.apiSales;
    const settingsUrl = app.dataset.apiSettings;
    const customersUrl = app.dataset.apiCustomers;
    const holdsUrl = app.dataset.apiHolds;
    const refundsUrl = app.dataset.apiRefunds;
    const drawerUrl = app.dataset.apiDrawer;
    const promotionsUrl = app.dataset.apiPromotions;
    const giftCardsUrl = app.dataset.apiGiftCards;
    const loyaltyUrl = app.dataset.apiLoyalty;
    const receiptUrl = app.dataset.receiptUrl || app.dataset.apiReceipt || '';
    const cashierId = parseInt(app.dataset.cashierId || '0', 10);
    const defaultStoreName = app.dataset.defaultStoreName || '';
    const companyName = app.dataset.companyName || 'ABBIS POS';

    const productGrid = document.getElementById('posProductGrid');
    const searchInput = document.getElementById('posSearch');
    const categorySelect = document.getElementById('posCategoryFilter');
    const storeSelect = document.getElementById('posStoreSelect');

    const cartContainer = document.getElementById('posCart');
    const itemCountEl = document.getElementById('cartItemCount');
    const subtotalEl = document.getElementById('cartSubtotal');
    const discountEl = document.getElementById('cartDiscount');
    const discountRow = document.getElementById('discountRow');
    const taxEl = document.getElementById('cartTax');
    const totalEl = document.getElementById('cartTotal');

    const customerSearchInput = document.getElementById('posCustomerSearch');
    const customerDropdown = document.getElementById('customerDropdown');
    const customerIdInput = document.getElementById('posCustomerId');
    const customerNameInput = document.getElementById('posCustomerName');
    const clearCustomerBtn = document.getElementById('clearCustomerBtn');
    const discountTypeSelect = document.getElementById('posDiscountType');
    const discountValueInput = document.getElementById('posDiscountValue');
    const applyDiscountBtn = document.getElementById('applyDiscountBtn');
    const paymentMethodSelect = document.getElementById('posPaymentMethod');
    const amountReceivedInput = document.getElementById('posAmountReceived');
    const notesInput = document.getElementById('posNotes');
    const holdSaleBtn = document.getElementById('holdSaleBtn');
    const resumeSaleBtn = document.getElementById('resumeSaleBtn');
    const clearSaleBtn = document.getElementById('clearSaleBtn');
    const completeSaleBtn = document.getElementById('completeSaleBtn');
    const toggleSplitPaymentsBtn = document.getElementById('toggleSplitPaymentsBtn');
    const paymentsList = document.getElementById('paymentsList');
    const totalPaidAmount = document.getElementById('totalPaidAmount');
    const openDrawerBtn = document.getElementById('openDrawerBtn');
    const closeDrawerBtn = document.getElementById('closeDrawerBtn');
    const promotionCodeInput = document.getElementById('posPromotionCode');
    const giftCardNumberInput = document.getElementById('posGiftCardNumber');
    const applyGiftCardBtn = document.getElementById('applyGiftCardBtn');
    const loyaltyPointsDisplay = document.getElementById('loyaltyPointsDisplay');
    const loyaltyPointsBalance = document.getElementById('loyaltyPointsBalance');
    const redeemLoyaltyPointsBtn = document.getElementById('redeemLoyaltyPointsBtn');
    const giftCardSection = document.getElementById('giftCardSection');
    const paperReceiptNumberInput = document.getElementById('posPaperReceiptNumber');

    let products = [];
    let filteredProducts = [];
    let categories = [];
    let searchQuery = '';
    let cart = [];
    let currentDiscount = { type: null, value: 0 };
    let lastSaleData = null; // Store last completed sale for receipt printing
    let barcodeBuffer = '';
    let barcodeTimeout = null;
    let customerSearchTimeout = null;
    let splitPaymentsMode = false;
    let paymentIdCounter = 0;
    let currentPromotion = null;
    let appliedGiftCard = null;
    let customerLoyaltyPoints = 0;

    function formatMoney(amount) {
        return 'GHS ' + Number(amount || 0).toFixed(2);
    }

    // Barcode scanning support
    function handleBarcodeInput(value) {
        // Clear timeout if user is typing
        if (barcodeTimeout) {
            clearTimeout(barcodeTimeout);
        }
        
        // If input is very fast (scanner) or ends with Enter, treat as barcode
        barcodeTimeout = setTimeout(() => {
            if (value.length >= 3) {
                searchByBarcode(value);
            }
        }, 100);
    }

    async function searchByBarcode(barcode) {
        try {
            // Search products by SKU or barcode
            const response = await fetch(`${catalogUrl}?search=${encodeURIComponent(barcode)}&limit=10`);
            const json = await response.json();
            
            if (json.success && json.data && json.data.length > 0) {
                const product = json.data[0];
                addToCart(product);
                searchInput.value = '';
                searchQuery = '';
                applyFilters();
                showToast(`Added: ${product.name}`);
            } else {
                showToast('Product not found', true);
            }
        } catch (error) {
            console.error('Barcode search error:', error);
        }
    }

    function renderProducts(list) {
        if (!list || !Array.isArray(list)) {
            console.error('renderProducts: Invalid list provided', list);
            productGrid.innerHTML = '<div class="pos-empty" style="grid-column: 1 / -1; color: var(--pos-danger);">Error: Invalid product data</div>';
            return;
        }
        
        if (!list.length) {
            productGrid.innerHTML = '<div class="pos-empty" style="grid-column: 1 / -1; padding: 20px; text-align: center;">No products found matching your filters.</div>';
            return;
        }

        try {
            const fragment = document.createDocumentFragment();
            list.forEach(product => {
                if (!product || !product.id) {
                    console.warn('Skipping invalid product:', product);
                    return;
                }
                
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'pos-product-card';
                card.dataset.productId = product.id;
                card.dataset.sku = product.sku || '';
                card.dataset.name = product.name || 'Unknown';
                card.dataset.price = product.unit_price || 0;
                card.dataset.trackInventory = product.track_inventory ? '1' : '0';

                card.innerHTML = `
                    <div class="pos-product-name">${escapeHtml(product.name || 'Unknown Product')}</div>
                    <div class="pos-product-meta">
                        <span>${formatMoney(product.unit_price || 0)}</span>
                        <span>${escapeHtml(product.sku || '')}</span>
                    </div>
                    <div class="pos-chips">
                        ${product.category_name ? `<span class="pos-chip">${escapeHtml(product.category_name)}</span>` : ''}
                        ${product.track_inventory ? '<span class="pos-chip">Tracked</span>' : ''}
                    </div>
                `;

                card.addEventListener('click', () => addToCart(product));
                fragment.appendChild(card);
            });

            productGrid.innerHTML = '';
            productGrid.appendChild(fragment);
            console.log(`Rendered ${list.length} products`);
        } catch (error) {
            console.error('Error rendering products:', error);
            productGrid.innerHTML = `<div class="pos-empty" style="grid-column: 1 / -1; color: var(--pos-danger);">Error rendering products: ${error.message}</div>`;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function applyFilters() {
        const categoryFilter = categorySelect ? (categorySelect.value || '') : '';
        filteredProducts = products.filter(product => {
            const matchesCategory = !categoryFilter || String(product.category_id ?? '') === categoryFilter;
            if (!matchesCategory) {
                return false;
            }
            if (!searchQuery) {
                return true;
            }
            const name = (product.name || '').toLowerCase();
            const sku = (product.sku || '').toLowerCase();
            const barcode = (product.barcode || '').toLowerCase();
            return name.includes(searchQuery) || sku.includes(searchQuery) || barcode.includes(searchQuery);
        });
        renderProducts(filteredProducts);
    }

    async function loadProducts() {
        try {
            productGrid.innerHTML = '<div class="pos-empty" style="grid-column: 1 / -1;">Loading products...</div>';
            const response = await fetch(`${catalogUrl}?limit=200`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const json = await response.json();
            
            if (!json.success) {
                console.error('Catalog API error:', json);
                throw new Error(json.message || 'Failed to load catalog');
            }
            
            products = Array.isArray(json.data) ? json.data : [];
            console.log(`Loaded ${products.length} products from catalog`);
            
            if (products.length === 0) {
                productGrid.innerHTML = '<div class="pos-empty" style="grid-column: 1 / -1; padding: 40px; text-align: center;"><div style="font-size: 48px; margin-bottom: 16px;">📦</div><div style="font-weight: 600; margin-bottom: 8px;">No Products Found</div><div style="color: var(--pos-secondary); font-size: 14px;">No active products are available. Please add products in Admin → Products.</div></div>';
                return;
            }
            
            applyFilters();
        } catch (error) {
            console.error('Error loading products:', error);
            productGrid.innerHTML = `<div class="pos-empty" style="grid-column: 1 / -1; color: var(--pos-danger); padding: 40px; text-align: center;"><div style="font-size: 48px; margin-bottom: 16px;">⚠️</div><div style="font-weight: 600; margin-bottom: 8px;">Error Loading Products</div><div style="font-size: 14px;">${error.message}</div><div style="margin-top: 16px; font-size: 12px; color: var(--pos-secondary);">Check the browser console for more details.</div></div>`;
        }
    }

    async function loadCategories() {
        if (!categorySelect) return;
        categorySelect.innerHTML = '<option value="">Loading categories...</option>';
        try {
            const response = await fetch(`${catalogUrl}?mode=categories`);
            const json = await response.json();
            if (!json.success) {
                throw new Error(json.message || 'Failed to load categories');
            }
            categories = Array.isArray(json.data) ? json.data : [];
            const fragment = document.createDocumentFragment();
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'All categories';
            fragment.appendChild(defaultOption);
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = String(category.id);
                option.textContent = category.name;
                fragment.appendChild(option);
            });
            categorySelect.innerHTML = '';
            categorySelect.appendChild(fragment);
        } catch (error) {
            categorySelect.innerHTML = '<option value="">All categories</option>';
            console.warn('POS categories could not be loaded:', error);
        }
    }

    function addToCart(product, overridePrice = null) {
        const existing = cart.find(item => item.id === product.id);
        const finalPrice = overridePrice !== null ? overridePrice : product.unit_price;
        
        if (existing) {
            existing.quantity = Math.round(existing.quantity + 1);
            if (overridePrice !== null) {
                existing.unit_price = finalPrice;
                existing.price_override = true;
                existing.original_price = existing.original_price || product.unit_price;
            }
        } else {
            cart.push({
                id: product.id,
                sku: product.sku,
                name: product.name,
                unit_price: parseFloat(finalPrice),
                quantity: 1,
                track_inventory: !!product.track_inventory,
                price_override: overridePrice !== null,
                original_price: overridePrice !== null ? product.unit_price : null,
            });
        }
        renderCart();
    }

    function updateQuantity(productId, quantity) {
        const item = cart.find(i => i.id === productId);
        if (!item) return;
        const parsed = Number.isFinite(Number(quantity)) ? Number(quantity) : 0;
        const qty = Math.max(Math.round(parsed), 0);
        if (qty === 0) {
            cart = cart.filter(i => i.id !== productId);
        } else {
            item.quantity = qty;
        }
        renderCart();
    }

    function removeItem(productId) {
        cart = cart.filter(item => item.id !== productId);
        renderCart();
    }

    function calculateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
        let discount = 0;
        
        if (currentDiscount.type === 'percent' && currentDiscount.value > 0) {
            discount = subtotal * (currentDiscount.value / 100);
        } else if (currentDiscount.type === 'fixed' && currentDiscount.value > 0) {
            discount = Math.min(currentDiscount.value, subtotal);
        } else if (currentDiscount.type === 'coupon' && currentDiscount.value > 0) {
            discount = Math.min(currentDiscount.value, subtotal);
        } else if (currentDiscount.type === 'promotion' && currentDiscount.value > 0) {
            discount = Math.min(currentDiscount.value, subtotal);
        } else if (currentDiscount.type === 'loyalty' && currentDiscount.value > 0) {
            discount = Math.min(currentDiscount.value, subtotal);
        }
        
        const tax = (subtotal - discount) * 0.0; // Placeholder for tax configuration
        const total = Math.max(0, subtotal - discount + tax);
        return { subtotal, discount, tax, total };
    }

    function renderCart() {
        if (!cart.length) {
            cartContainer.innerHTML = '<div class="pos-empty">No items yet. Add products from the list.</div>';
            updateTotals();
            return;
        }

        const fragment = document.createDocumentFragment();
        cart.forEach(item => {
            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div>
                    <strong>${item.name}</strong>
                    <div style="font-size:0.75rem; color:var(--pos-secondary);">${item.sku || ''}</div>
                </div>
                <input type="number" min="0" step="1" value="${Math.round(item.quantity)}" style="width: 60px;">
                <div style="text-align:right;">
                    <div>${formatMoney(item.unit_price)}</div>
                    <div style="font-size:0.8rem; color:var(--pos-secondary);">${formatMoney(item.unit_price * item.quantity)}</div>
                </div>
                <button type="button" class="btn btn-outline btn-sm">×</button>
            `;

            const qtyInput = row.querySelector('input');
            qtyInput.addEventListener('change', (e) => updateQuantity(item.id, e.target.value));
            const removeBtn = row.querySelector('button');
            removeBtn.addEventListener('click', () => removeItem(item.id));

            fragment.appendChild(row);
        });

        cartContainer.innerHTML = '';
        cartContainer.appendChild(fragment);
        updateTotals();
    }

    function updateTotals() {
        const { subtotal, discount, tax, total } = calculateTotals();
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        itemCountEl.textContent = Number.isInteger(totalItems) ? String(totalItems) : totalItems.toFixed(2);
        subtotalEl.textContent = formatMoney(subtotal);
        
        if (discount > 0) {
            discountRow.style.display = 'flex';
            discountEl.textContent = '-' + formatMoney(discount);
        } else {
            discountRow.style.display = 'none';
        }
        
        taxEl.textContent = formatMoney(tax);
        totalEl.textContent = formatMoney(total);
        if (amountReceivedInput) {
            amountReceivedInput.placeholder = total ? total.toFixed(2) : 'Amount received';
        }
    }

    // Customer search functionality
    async function searchCustomers(query) {
        if (!query || query.trim().length < 1) {
            customerDropdown.style.display = 'none';
            return;
        }

        const searchQuery = query.trim();
        console.log('Searching for:', searchQuery);

        try {
            const url = `${customersUrl}?search=${encodeURIComponent(searchQuery)}&limit=20`;
            console.log('Search URL:', url);
            
            const response = await fetch(url);
            console.log('Search response status:', response.status, response.statusText);
            
            if (!response.ok) {
                // Try to get error message from response
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorJson = await response.json();
                    errorMessage = errorJson.message || errorMessage;
                    console.error('Customer search API error response:', errorJson);
                } catch (e) {
                    console.error('Failed to parse error response:', e);
                }
                console.error('Customer search API error:', errorMessage);
                
                // Show error to user
                customerDropdown.innerHTML = `<div class="customer-dropdown-item" style="padding: 12px; text-align: center; color: #d63638;">Search error: ${errorMessage}. You can type a name manually below.</div>`;
                customerDropdown.style.display = 'block';
                return;
            }
            
            const json = await response.json();
            console.log('Search response:', json);
            
            if (!json.success) {
                console.error('Customer search failed:', json.message || 'Unknown error');
                customerDropdown.innerHTML = `<div class="customer-dropdown-item" style="padding: 12px; text-align: center; color: #d63638;">Search failed: ${json.message || 'Unknown error'}. You can type a name manually below.</div>`;
                customerDropdown.style.display = 'block';
                return;
            }
            
            const entities = json.data || [];
            console.log('Found entities:', entities.length);
            
            if (entities.length > 0) {
                customerDropdown.innerHTML = '';
                entities.forEach(entity => {
                    const item = document.createElement('div');
                    item.className = 'customer-dropdown-item';
                    const meta = [];
                    if (entity.phone) meta.push(`📞 ${entity.phone}`);
                    if (entity.email) meta.push(`✉️ ${entity.email}`);
                    if (entity.source_system) meta.push(`🏷️ ${entity.source_system}`);
                    
                    // Highlight different entity types with icons
                    let icon = '👤';
                    if (entity.entity_type === 'worker') icon = '👷';
                    else if (entity.entity_type === 'cms_customer') icon = '🛒';
                    else if (entity.entity_type === 'client') icon = '🏢';
                    
                    item.innerHTML = `
                        <div class="customer-name">${icon} ${escapeHtml(entity.name || entity.display_name || 'Unknown')}</div>
                        <div class="customer-meta">${meta.join(' • ') || 'No contact info'}</div>
                    `;
                    item.addEventListener('click', () => selectCustomer(entity));
                    customerDropdown.appendChild(item);
                });
                customerDropdown.style.display = 'block';
            } else {
                customerDropdown.innerHTML = '<div class="customer-dropdown-item" style="padding: 12px; text-align: center; color: #646970;">No matches found for "' + escapeHtml(searchQuery) + '". You can type a name manually below.</div>';
                customerDropdown.style.display = 'block';
            }
        } catch (error) {
            console.error('Customer search error:', error);
            console.error('Error stack:', error.stack);
            // Show error to user
            customerDropdown.innerHTML = `<div class="customer-dropdown-item" style="padding: 12px; text-align: center; color: #d63638;">Network error: ${error.message}. Check browser console for details.</div>`;
            customerDropdown.style.display = 'block';
        }
    }

    function selectCustomer(customer) {
        // Support both old format (id only) and new format (entity_type, entity_id)
        if (customer.entity_type && customer.entity_id) {
            // New unified entity format
            customerIdInput.value = customer.entity_id;
            customerIdInput.dataset.entityType = customer.entity_type;
            customerIdInput.dataset.entityId = customer.entity_id;
        } else {
            // Legacy format - assume client
            customerIdInput.value = customer.id;
            customerIdInput.dataset.entityType = 'client';
            customerIdInput.dataset.entityId = customer.id;
        }
        
        customerNameInput.value = customer.name || customer.display_name || '';
        customerSearchInput.value = '';
        customerDropdown.style.display = 'none';
        
        // Load loyalty points for selected customer (only if client type)
        const entityType = customer.entity_type || customerIdInput.dataset.entityType || 'client';
        if (entityType === 'client' && customer.id) {
            loadLoyaltyPoints(customer.id || customer.entity_id);
        } else {
            // Hide loyalty points for non-client entities
            if (loyaltyPointsDisplay) {
                loyaltyPointsDisplay.style.display = 'none';
            }
            customerLoyaltyPoints = 0;
        }
        
        // Show source system badge if available
        if (customer.source_system) {
            console.log(`Selected ${customer.source_system}: ${customer.name}`);
        }
    }
    
    function clearCustomer() {
        customerIdInput.value = '';
        customerIdInput.removeAttribute('data-entity-type');
        customerIdInput.removeAttribute('data-entity-id');
        customerNameInput.value = '';
        customerSearchInput.value = '';
        customerDropdown.style.display = 'none';
        loyaltyPointsDisplay.style.display = 'none';
        customerLoyaltyPoints = 0;
    }

    // Discount functionality
    function applyDiscount() {
        const type = discountTypeSelect.value;
        const value = discountValueInput.value.trim();
        
        if (!type || !value) {
            showToast('Select discount type and enter value', true);
            return;
        }
        
        if (type === 'percent') {
            const percent = parseFloat(value);
            if (isNaN(percent) || percent < 0 || percent > 100) {
                showToast('Invalid percentage (0-100)', true);
                return;
            }
            currentDiscount = { type: 'percent', value: percent };
        } else if (type === 'fixed') {
            const amount = parseFloat(value);
            if (isNaN(amount) || amount < 0) {
                showToast('Invalid discount amount', true);
                return;
            }
            currentDiscount = { type: 'fixed', value: amount };
        } else if (type === 'coupon') {
            // For now, treat coupon as fixed amount
            // In future, can validate against coupon codes
            const amount = parseFloat(value) || 0;
            currentDiscount = { type: 'coupon', value: amount };
            showToast('Coupon codes not yet implemented. Using as fixed discount.');
        }
        
        renderCart();
        showToast('Discount applied');
    }

    function removeDiscount() {
        currentDiscount = { type: null, value: 0 };
        discountTypeSelect.value = '';
        discountValueInput.value = '';
        renderCart();
    }

    // Hold/Resume sales
    async function holdSale() {
        if (!cart.length) {
            showToast('Cannot hold an empty sale', true);
            return;
        }

        const storeId = parseInt(storeSelect?.value ?? '', 10);
        if (!storeId) {
            showToast('Select a store first', true);
            return;
        }

        try {
            const payload = {
                action: 'hold',
                store_id: storeId,
                cart: cart,
                customer_id: customerIdInput.value ? parseInt(customerIdInput.value) : null,
                customer_name: customerNameInput.value || null,
                discount_type: currentDiscount.type,
                discount_value: currentDiscount.value,
                notes: notesInput.value || null
            };

            const response = await fetch(holdsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            const json = await response.json();
            if (!json.success) throw new Error(json.message || 'Failed to hold sale');

            showToast('Sale held successfully');
            clearSale();
        } catch (error) {
            showToast(error.message || 'Failed to hold sale', true);
        }
    }

    async function resumeSale() {
        try {
            const response = await fetch(holdsUrl, { credentials: 'include' });
            const json = await response.json();
            
            if (!json.success || !json.data || json.data.length === 0) {
                showToast('No held sales found', true);
                return;
            }

            const holds = json.data;
            
            // Create modal for selecting held sale
            const modal = document.createElement('div');
            modal.className = 'modal active';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 600px;">
                    <h3>Select Sale to Resume</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        ${holds.map((hold, idx) => `
                            <div class="customer-dropdown-item" data-hold-id="${hold.id}" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #e5e7eb;">
                                <div style="flex: 1;" class="hold-item-content">
                                    <div class="customer-name">${hold.customer_name || 'Walk-in'}</div>
                                    <div class="customer-meta">
                                        ${hold.item_count} items • ${formatMoney(hold.total)} • ${new Date(hold.created_at).toLocaleString()}
                                    </div>
                                </div>
                                <button class="btn btn-outline" style="margin-left: 8px; padding: 6px 12px; font-size: 12px;" data-delete-hold-id="${hold.id}" onclick="event.stopPropagation();">🗑️ Delete</button>
                            </div>
                        `).join('')}
                    </div>
                    <div class="btn-group" style="margin-top: 16px; display: flex; justify-content: space-between;">
                        <button class="btn btn-outline" id="clearAllHoldsBtn" style="background: #d63638; color: white; border-color: #d63638;">Clear All</button>
                        <button class="btn btn-outline" id="cancelResumeBtn">Cancel</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Add click handlers for resuming
            modal.querySelectorAll('[data-hold-id]').forEach(item => {
                // Only attach to the main item, not delete buttons
                if (item.dataset.deleteHoldId) return;
                
                item.querySelector('.hold-item-content')?.addEventListener('click', async () => {
                    const holdId = parseInt(item.dataset.holdId);
                    modal.remove();
                    
                    try {
                        const resumeResponse = await fetch(holdsUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ action: 'resume', hold_id: holdId })
                        });

                        const resumeJson = await resumeResponse.json();
                        if (!resumeJson.success) throw new Error(resumeJson.message || 'Failed to resume sale');

                        const holdData = resumeJson.data;
                        cart = holdData.cart || [];
                        customerIdInput.value = holdData.customer_id || '';
                        customerNameInput.value = holdData.customer_name || '';
                        currentDiscount = {
                            type: holdData.discount_type || null,
                            value: holdData.discount_value ? parseFloat(holdData.discount_value) : 0
                        };
                        notesInput.value = holdData.notes || '';
                        
                        if (holdData.discount_type) {
                            discountTypeSelect.value = holdData.discount_type;
                            discountValueInput.value = holdData.discount_value || '';
                        }

                        if (holdData.store_id && storeSelect) {
                            storeSelect.value = holdData.store_id;
                        }

                        // Delete the held sale to prevent duplicates
                        try {
                            await fetch(holdsUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                credentials: 'include',
                                body: JSON.stringify({ action: 'delete', hold_id: holdId })
                            });
                        } catch (deleteError) {
                            // Non-critical error, just log it
                            console.warn('Failed to delete held sale after resume:', deleteError);
                        }

                        renderCart();
                        showToast('Sale resumed');
                    } catch (error) {
                        showToast(error.message || 'Failed to resume sale', true);
                    }
                });
            });
            
            // Add delete handlers for individual holds
            modal.querySelectorAll('[data-delete-hold-id]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const holdId = parseInt(btn.dataset.deleteHoldId);
                    
                    if (!confirm('Are you sure you want to delete this held sale?')) {
                        return;
                    }
                    
                    try {
                        const deleteResponse = await fetch(holdsUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ action: 'delete', hold_id: holdId })
                        });
                        
                        const deleteJson = await deleteResponse.json();
                        if (!deleteJson.success) throw new Error(deleteJson.message || 'Failed to delete held sale');
                        
                        // Remove the item from the modal
                        btn.closest('[data-hold-id]').remove();
                        showToast('Held sale deleted');
                        
                        // If no more holds, close modal
                        if (modal.querySelectorAll('[data-hold-id]').length === 0) {
                            modal.remove();
                            showToast('No more held sales');
                        }
                    } catch (error) {
                        showToast(error.message || 'Failed to delete held sale', true);
                    }
                });
            });
            
            // Add clear all handler
            modal.querySelector('#clearAllHoldsBtn')?.addEventListener('click', async () => {
                if (!confirm(`Are you sure you want to delete all ${holds.length} held sale(s)? This cannot be undone.`)) {
                    return;
                }
                
                try {
                    // Delete all holds one by one
                    const deletePromises = holds.map(hold => 
                        fetch(holdsUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ action: 'delete', hold_id: hold.id })
                        })
                    );
                    
                    await Promise.all(deletePromises);
                    modal.remove();
                    showToast(`Deleted ${holds.length} held sale(s)`);
                } catch (error) {
                    showToast(error.message || 'Failed to clear held sales', true);
                }
            });
            
            modal.querySelector('#cancelResumeBtn')?.addEventListener('click', () => {
                modal.remove();
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        } catch (error) {
            showToast(error.message || 'Failed to load held sales', true);
        }
    }

    // Receipt printing
    function printReceipt(saleData) {
        if (!saleData) {
            showToast('No receipt data available', true);
            return;
        }
        
        const receiptNumber = saleData.receipt_number || 'N/A';
        const paperReceiptNumber = saleData.paper_receipt_number ? ` (Paper: ${saleData.paper_receipt_number})` : '';

        const lineWidth = 42;
        const divider = '-'.repeat(lineWidth);
        const selectedStore = saleData.store_name || storeSelect?.selectedOptions[0]?.textContent?.trim() || defaultStoreName;
        const paymentLabel = paymentMethodSelect?.selectedOptions?.[0]?.textContent?.trim() || paymentMethodSelect?.value || 'N/A';
        const customerName = saleData.customer_name || 'Walk-in';
        const saleNumber = saleData.sale_number || 'N/A';
        const saleDate = saleData.sale_timestamp ? new Date(saleData.sale_timestamp).toLocaleString() : new Date().toLocaleString();
        const tendered = saleData.amount_paid || saleData.total_amount || 0;
        const changeDue = saleData.change_due || 0;

        const center = (text = '') => {
            const trimmed = text.slice(0, lineWidth);
            const padding = Math.max(0, Math.floor((lineWidth - trimmed.length) / 2));
            return ' '.repeat(padding) + trimmed;
        };

        const labelValue = (label, value) => {
            const left = (label || '').toUpperCase();
            const right = value || '';
            const spacing = Math.max(1, lineWidth - left.length - right.length);
            return `${left}${' '.repeat(spacing)}${right}`;
        };

        const lines = [
            center(companyName),
            center('POINT OF SALE RECEIPT'),
            divider,
            labelValue('Receipt #', receiptNumber + paperReceiptNumber),
            labelValue('Sale #', saleNumber),
            labelValue('Store', selectedStore),
            labelValue('Cashier', cashierId || '-'),
            labelValue('Customer', customerName),
            labelValue('Date', saleDate),
            divider,
        ];

        // Add items
        if (saleData.items && Array.isArray(saleData.items)) {
            saleData.items.forEach(item => {
                const nameLine = (item.name || 'Item').length > lineWidth 
                    ? (item.name || 'Item').slice(0, lineWidth - 1) + '…' 
                    : (item.name || 'Item');
                lines.push(nameLine);
                const lineTotal = (item.unit_price || 0) * (item.quantity || 0);
                lines.push(labelValue(`  x${item.quantity || 0} @ ${(item.unit_price || 0).toFixed(2)}`, formatMoney(lineTotal)));
            });
        }

        lines.push(divider);
        lines.push(labelValue('Subtotal', (saleData.subtotal_amount || 0).toFixed(2)));
        if ((saleData.discount_total || 0) > 0) {
            lines.push(labelValue('Discount', '-' + (saleData.discount_total || 0).toFixed(2)));
        }
        if ((saleData.tax_total || 0) > 0) {
            lines.push(labelValue('Tax', (saleData.tax_total || 0).toFixed(2)));
        }
        lines.push(labelValue('Total', (saleData.total_amount || 0).toFixed(2)));
        lines.push(labelValue('Paid', tendered.toFixed(2)));
        if (changeDue > 0) {
            lines.push(labelValue('Change', changeDue.toFixed(2)));
        }
        lines.push(divider);
        lines.push(labelValue('Payment', paymentLabel));
        if (saleData.notes) {
            lines.push('Note: ' + String(saleData.notes).substring(0, lineWidth - 6));
        }
        lines.push(center('Thank you for your business!'));
        lines.push('');

        const receiptContent = lines.join('\n');
        
        // Open print window
        const printWindow = window.open('', '_blank', 'width=400,height=600');
        if (printWindow) {
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Receipt - ${saleNumber}</title>
                    <style>
                        @media print {
                            body { margin: 0; }
                        }
                        pre {
                            font-family: 'Courier New', monospace;
                            font-size: 12px;
                            line-height: 1.4;
                            margin: 0;
                            padding: 20px;
                        }
                    </style>
                </head>
                <body>
                    <pre>${receiptContent}</pre>
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() { window.close(); }, 1000);
                        };
                    </script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    }

    // Split Payments Functions
    function toggleSplitPayments() {
        splitPaymentsMode = !splitPaymentsMode;
        if (splitPaymentsMode) {
            toggleSplitPaymentsBtn.textContent = 'Single';
            paymentsList.querySelectorAll('.remove-payment').forEach(btn => {
                btn.style.display = paymentsList.children.length > 1 ? 'block' : 'none';
            });
        } else {
            toggleSplitPaymentsBtn.textContent = 'Split';
            // Keep only first payment
            while (paymentsList.children.length > 1) {
                paymentsList.lastElementChild.remove();
            }
            paymentsList.querySelectorAll('.remove-payment').forEach(btn => {
                btn.style.display = 'none';
            });
        }
        updateTotalPaid();
    }

    function addPaymentEntry() {
        paymentIdCounter++;
        const entry = document.createElement('div');
        entry.className = 'payment-entry';
        entry.dataset.paymentId = paymentIdCounter;
        entry.innerHTML = `
            <div style="display: flex; gap: 8px; margin-bottom: 4px;">
                <select class="payment-method form-control" style="flex: 1;">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="gift_card">Gift Card</option>
                    <option value="store_credit">Store Credit</option>
                </select>
                <input type="number" class="payment-amount form-control" placeholder="Amount" step="0.01" style="flex: 1;">
                <button type="button" class="remove-payment btn btn-outline" style="padding: 4px 8px;">×</button>
            </div>
        `;
        paymentsList.appendChild(entry);
        
        entry.querySelector('.remove-payment').addEventListener('click', () => {
            if (paymentsList.children.length > 1) {
                entry.remove();
                updateTotalPaid();
            }
        });
        
        entry.querySelector('.payment-amount').addEventListener('input', updateTotalPaid);
        entry.querySelector('.payment-method').addEventListener('change', updateTotalPaid);
        
        if (paymentsList.children.length > 1) {
            paymentsList.querySelectorAll('.remove-payment').forEach(btn => {
                btn.style.display = 'block';
            });
        }
    }

    function updateTotalPaid() {
        let total = 0;
        paymentsList.querySelectorAll('.payment-amount').forEach(input => {
            const amount = parseFloat(input.value) || 0;
            total += amount;
        });
        if (totalPaidAmount) {
            totalPaidAmount.textContent = formatMoney(total);
        }
    }

    function getPayments() {
        const payments = [];
        paymentsList.querySelectorAll('.payment-entry').forEach(entry => {
            const method = entry.querySelector('.payment-method').value;
            const amount = parseFloat(entry.querySelector('.payment-amount').value) || 0;
            if (amount > 0) {
                payments.push({ payment_method: method, amount: amount });
            }
        });
        return payments;
    }

    // Cash Drawer Functions
    async function openDrawer() {
        const storeId = parseInt(storeSelect?.value ?? '', 10);
        if (!storeId) {
            showToast('Select a store first', true);
            return;
        }
        
        const openingAmount = parseFloat(prompt('Enter opening cash amount:', '0')) || 0;
        
        try {
            const response = await fetch(drawerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'open',
                    store_id: storeId,
                    opening_amount: openingAmount
                })
            });
            
            const json = await response.json();
            if (json.success) {
                showToast('Cash drawer opened');
            } else {
                showToast(json.message || 'Failed to open drawer', true);
            }
        } catch (error) {
            showToast('Failed to open drawer', true);
        }
    }

    async function closeDrawer() {
        const storeId = parseInt(storeSelect?.value ?? '', 10);
        if (!storeId) {
            showToast('Select a store first', true);
            return;
        }
        
        const countedAmount = parseFloat(prompt('Enter counted cash amount:', '')) || null;
        const notes = prompt('Enter notes (optional):', '') || null;
        
        try {
            const response = await fetch(drawerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'close',
                    store_id: storeId,
                    counted_amount: countedAmount,
                    notes: notes
                })
            });
            
            const json = await response.json();
            if (json.success) {
                const diff = json.data?.difference || 0;
                showToast(`Drawer closed. Difference: ${formatMoney(diff)}`);
            } else {
                showToast(json.message || 'Failed to close drawer', true);
            }
        } catch (error) {
            showToast('Failed to close drawer', true);
        }
    }

    // Price Override Function
    function overridePrice(product) {
        const newPrice = parseFloat(prompt(`Override price for ${product.name}\nCurrent: ${formatMoney(product.unit_price)}\nEnter new price:`, product.unit_price));
        if (!isNaN(newPrice) && newPrice >= 0) {
            // In a real system, you'd check for manager approval here
            const approved = confirm(`Override price to ${formatMoney(newPrice)}?\n(Manager approval required)`);
            if (approved) {
                addToCart(product, newPrice);
                showToast(`Price overridden to ${formatMoney(newPrice)}`);
            }
        }
    }

    // Phase 4: Promotion Code Functions
    async function validatePromotionCode(code) {
        if (!code || !code.trim()) {
            currentPromotion = null;
            calculateTotals();
            return;
        }

        try {
            const { subtotal } = calculateTotals();
            const customerId = customerIdInput.value ? parseInt(customerIdInput.value) : null;
            const response = await fetch(`${promotionsUrl}?code=${encodeURIComponent(code)}&subtotal=${subtotal}&customer_id=${customerId || ''}`);
            const json = await response.json();
            
            if (json.success && json.data) {
                currentPromotion = json.data.promotion;
                currentDiscount = {
                    type: 'promotion',
                    value: json.data.discount_amount
                };
                showToast(`Promotion "${currentPromotion.promotion_name}" applied!`);
                calculateTotals();
            } else {
                currentPromotion = null;
                showToast(json.message || 'Invalid promotion code', true);
                calculateTotals();
            }
        } catch (error) {
            console.error('Promotion validation error:', error);
            showToast('Failed to validate promotion code', true);
        }
    }

    // Phase 4: Gift Card Functions
    async function applyGiftCard(cardNumber) {
        if (!cardNumber || !cardNumber.trim()) {
            showToast('Enter gift card number', true);
            return;
        }

        try {
            const { total } = calculateTotals();
            const response = await fetch(giftCardsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'redeem',
                    card_number: cardNumber.trim(),
                    amount: total
                })
            });
            
            const json = await response.json();
            if (json.success && json.data) {
                appliedGiftCard = json.data;
                const balance = parseFloat(json.data.current_balance);
                const redeemed = Math.min(total, parseFloat(json.data.current_balance));
                
                // Add gift card payment
                if (!splitPaymentsMode) {
                    const paymentEntry = paymentsList.querySelector('.payment-entry');
                    if (paymentEntry) {
                        paymentEntry.querySelector('.payment-method').value = 'gift_card';
                        paymentEntry.querySelector('.payment-amount').value = redeemed;
                        updateTotalPaid();
                    }
                }
                
                showToast(`Gift card applied. Balance: ${formatMoney(balance)}`);
                giftCardNumberInput.value = '';
            } else {
                showToast(json.message || 'Failed to apply gift card', true);
            }
        } catch (error) {
            console.error('Gift card error:', error);
            showToast('Failed to apply gift card', true);
        }
    }

    // Phase 4: Loyalty Points Functions
    async function loadLoyaltyPoints(customerId) {
        if (!customerId) return;
        
        try {
            const response = await fetch(`${loyaltyUrl}?customer_id=${customerId}`);
            const json = await response.json();
            
            if (json.success && json.data) {
                customerLoyaltyPoints = parseInt(json.data.points_balance || 0);
                if (loyaltyPointsBalance) {
                    loyaltyPointsBalance.textContent = customerLoyaltyPoints.toLocaleString();
                }
                if (loyaltyPointsDisplay) {
                    loyaltyPointsDisplay.style.display = customerLoyaltyPoints > 0 ? 'block' : 'none';
                }
            } else {
                customerLoyaltyPoints = 0;
                if (loyaltyPointsDisplay) {
                    loyaltyPointsDisplay.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Loyalty points error:', error);
        }
    }

    async function redeemLoyaltyPoints() {
        if (!customerIdInput.value) {
            showToast('Select a customer first', true);
            return;
        }

        const pointsToRedeem = parseInt(prompt(`Enter points to redeem (Available: ${customerLoyaltyPoints}):`, ''));
        if (!pointsToRedeem || pointsToRedeem <= 0 || pointsToRedeem > customerLoyaltyPoints) {
            showToast('Invalid points amount', true);
            return;
        }

        try {
            const response = await fetch(loyaltyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'redeem',
                    customer_id: parseInt(customerIdInput.value),
                    points: pointsToRedeem
                })
            });
            
            const json = await response.json();
            if (json.success) {
                const currencyValue = json.data.currency_value || 0;
                // Apply as discount
                currentDiscount = {
                    type: 'loyalty',
                    value: currencyValue
                };
                showToast(`${pointsToRedeem} points redeemed = ${formatMoney(currencyValue)}`);
                calculateTotals();
                loadLoyaltyPoints(parseInt(customerIdInput.value)); // Refresh balance
            } else {
                showToast(json.message || 'Failed to redeem points', true);
            }
        } catch (error) {
            console.error('Loyalty redemption error:', error);
            showToast('Failed to redeem points', true);
        }
    }

    function clearSale() {
        cart = [];
        currentDiscount = { type: null, value: 0 };
        clearCustomer();
        notesInput.value = '';
        discountTypeSelect.value = '';
        discountValueInput.value = '';
        
        // Reset payments
        paymentsList.innerHTML = `
            <div class="payment-entry" data-payment-id="0">
                <div style="display: flex; gap: 8px; margin-bottom: 4px;">
                    <select class="payment-method form-control" style="flex: 1;">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="gift_card">Gift Card</option>
                        <option value="store_credit">Store Credit</option>
                    </select>
                    <input type="number" class="payment-amount form-control" placeholder="Amount" step="0.01" style="flex: 1;">
                    <button type="button" class="remove-payment btn btn-outline" style="display: none; padding: 4px 8px;">×</button>
                </div>
            </div>
        `;
        splitPaymentsMode = false;
        toggleSplitPaymentsBtn.textContent = 'Split';
        currentPromotion = null;
        appliedGiftCard = null;
        if (promotionCodeInput) promotionCodeInput.value = '';
        if (giftCardNumberInput) giftCardNumberInput.value = '';
        if (paperReceiptNumberInput) paperReceiptNumberInput.value = '';
        if (giftCardSection) giftCardSection.style.display = 'none';
        updateTotalPaid();
        renderCart();
    }

    async function submitSale() {
        if (!cart.length) {
            showToast('Add at least one item to the cart.', true);
            return;
        }

        const { subtotal, discount, tax, total } = calculateTotals();
        if (!cashierId || cashierId <= 0) {
            showToast('Unable to resolve cashier account. Please log out and back in.', true);
            return;
        }
        const storeId = Number.parseInt(storeSelect?.value ?? '', 10);
        if (!Number.isFinite(storeId) || storeId <= 0) {
            showToast('Select a valid store before completing the sale.', true);
            return;
        }

        // Get payments (split or single)
        const payments = getPayments();
        const totalPaid = payments.reduce((sum, p) => sum + p.amount, 0);
        
        if (totalPaid < total) {
            showToast('Total payment amount cannot be less than sale total.', true);
            return;
        }
        
        const amountReceived = totalPaid;
        const changeDue = amountReceived - total;

        // Get entity information from customer input
        const entityType = customerIdInput.dataset.entityType || (customerIdInput.value ? 'client' : null);
        const entityId = customerIdInput.dataset.entityId || (customerIdInput.value ? parseInt(customerIdInput.value) : null);
        
        const payload = {
            store_id: storeId,
            cashier_id: cashierId,
            customer_id: entityType === 'client' ? entityId : null, // For backward compatibility
            customer_name: customerNameInput.value || null,
            entity_type: entityType,
            entity_id: entityId,
            subtotal_amount: subtotal,
            discount_total: discount,
            tax_total: tax,
            total_amount: total,
            amount_paid: amountReceived,
            change_due: amountReceived - total,
            notes: notesInput.value || null,
            items: cart.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                tax_amount: 0,
                discount_amount: 0,
                line_total: item.unit_price * item.quantity,
                inventory_impact: item.track_inventory,
                price_override: item.price_override || false,
                original_price: item.original_price || null,
            })),
            payments,
            promotion_id: currentPromotion ? currentPromotion.id : null,
            loyalty_points_earned: (entityType === 'client' && entityId) ? Math.floor(subtotal) : 0, // 1 point per currency unit (only for clients)
            loyalty_points_redeemed: currentDiscount.type === 'loyalty' ? Math.floor(currentDiscount.value / 0.01) : 0,
            paper_receipt_number: paperReceiptNumberInput?.value.trim() || null,
        };

        try {
            completeSaleBtn.disabled = true;
            completeSaleBtn.textContent = 'Processing...';

            const response = await fetch(salesUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload),
            });

            let result;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const rawText = await response.text();
                console.error('POS sale raw response:', rawText);
                throw new Error('Invalid response from server');
            }

            if (!response.ok) {
                const message = result?.message || `Sale could not be completed (HTTP ${response.status}).`;
                throw new Error(message);
            }

            if (!result?.success) {
                throw new Error(result?.message || 'Sale failed.');
            }

            const saleNumber = result.data?.sale_number ? ` ${result.data.sale_number}` : '';
            const receiptNumber = result.data?.receipt_number ? ` (Receipt: ${result.data.receipt_number})` : '';
            showToast(`Sale${saleNumber} completed.${receiptNumber}`);
            
            // Store sale data for receipt printing
            lastSaleData = {
                ...result.data,
                items: cart.map(item => ({
                    ...item,
                    name: products.find(p => p.id === item.product_id)?.name || 'Item'
                })),
                customer_name: customerNameInput.value || 'Walk-in',
                sale_timestamp: new Date().toISOString(),
                receipt_number: result.data?.receipt_number || null,
                paper_receipt_number: paperReceiptNumberInput?.value.trim() || null
            };
            
            // Auto-print receipt and offer email
            setTimeout(async () => {
                const printChoice = confirm('Print receipt?');
                if (printChoice) {
                    printReceipt(lastSaleData);
                }
                
                // Offer to send email receipt if customer email is available
                try {
                    const entityType = customerIdInput.dataset.entityType;
                    const entityId = customerIdInput.dataset.entityId || customerIdInput.value;
                    const customerEmail = (entityType && entityId) ? await getCustomerEmail(entityType, entityId) : null;
                    
                    if (customerEmail) {
                        setTimeout(() => {
                            if (confirm(`Send receipt to ${customerEmail}?`)) {
                                const customerIdForEmail = entityType === 'client' ? parseInt(entityId) : null;
                                sendEmailReceipt(result.data.id, customerEmail, customerIdForEmail);
                            }
                        }, 500);
                    } else if (customerIdInput.value || customerNameInput.value !== 'Walk-in') {
                        // Entity selected but no email - offer to enter email manually
                        setTimeout(() => {
                            const email = prompt('Enter email address to send receipt:');
                            if (email && email.trim()) {
                                const customerIdForEmail = entityType === 'client' ? parseInt(entityId) : null;
                                sendEmailReceipt(result.data.id, email.trim(), customerIdForEmail);
                            }
                        }, 500);
                    }
                } catch (error) {
                    console.error('Error getting customer email:', error);
                    // If email fetch fails, still offer to enter email manually
                    if (customerIdInput.value || customerNameInput.value !== 'Walk-in') {
                        setTimeout(() => {
                            const email = prompt('Enter email address to send receipt:');
                            if (email && email.trim()) {
                                const entityType = customerIdInput.dataset.entityType;
                                const entityId = customerIdInput.dataset.entityId || customerIdInput.value;
                                const customerIdForEmail = entityType === 'client' ? parseInt(entityId) : null;
                                sendEmailReceipt(result.data.id, email.trim(), customerIdForEmail);
                            }
                        }, 500);
                    }
                }
            }, 500);
            
            clearSale();
        } catch (error) {
            const fallback = error instanceof Error ? error.message : 'Sale failed.';
            showToast(fallback, true);
        } finally {
            completeSaleBtn.disabled = false;
            completeSaleBtn.textContent = 'Complete Sale';
        }
    }

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.position = 'fixed';
        toast.style.bottom = '24px';
        toast.style.right = '24px';
        toast.style.padding = '12px 16px';
        toast.style.borderRadius = '12px';
        toast.style.color = '#fff';
        toast.style.background = isError ? '#ef4444' : '#16a34a';
        toast.style.boxShadow = '0 12px 24px rgba(0,0,0,0.2)';
        toast.style.zIndex = '3000';
        toast.style.fontSize = '14px';
        toast.style.fontWeight = '500';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3200);
    }

    // Event listeners
    searchInput?.addEventListener('input', (event) => {
        const value = event.target.value;
        searchQuery = value.toLowerCase().trim();
        handleBarcodeInput(value);
        applyFilters();
    });

    categorySelect?.addEventListener('change', () => {
        applyFilters();
    });

    // Customer search
    customerSearchInput?.addEventListener('input', (event) => {
        const query = event.target.value.trim();
        if (customerSearchTimeout) {
            clearTimeout(customerSearchTimeout);
        }
        customerSearchTimeout = setTimeout(() => {
            searchCustomers(query);
        }, 300);
    });
    
    // Load loyalty points when customer is selected
    customerIdInput?.addEventListener('change', () => {
        const customerId = parseInt(customerIdInput.value);
        if (customerId) {
            loadLoyaltyPoints(customerId);
        } else {
            loyaltyPointsDisplay.style.display = 'none';
            customerLoyaltyPoints = 0;
        }
    });

    customerSearchInput?.addEventListener('blur', () => {
        setTimeout(() => {
            customerDropdown.style.display = 'none';
        }, 200);
    });
    
    // Allow manual customer name entry
    customerNameInput?.addEventListener('input', (event) => {
        // If user types manually and there's no customer ID, treat as walk-in with name
        // If they clear the name completely, clear the ID too
        if (!event.target.value.trim() && customerIdInput.value) {
            customerIdInput.value = '';
            loyaltyPointsDisplay.style.display = 'none';
            customerLoyaltyPoints = 0;
        }
    });
    
    customerNameInput?.addEventListener('focus', () => {
        // When focusing on name field, allow manual entry
        // User can type directly without needing to search
    });
    
    // Clear customer button
    clearCustomerBtn?.addEventListener('click', () => {
        clearCustomer();
        showToast('Customer cleared');
    });

    // Discount
    discountTypeSelect?.addEventListener('change', (e) => {
        discountValueInput.disabled = !e.target.value;
        applyDiscountBtn.disabled = !e.target.value;
        if (!e.target.value) {
            removeDiscount();
        }
    });

    applyDiscountBtn?.addEventListener('click', applyDiscount);
    
    discountValueInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            applyDiscount();
        }
    });

    // Split Payments
    toggleSplitPaymentsBtn?.addEventListener('click', () => {
        if (splitPaymentsMode) {
            toggleSplitPayments();
        } else {
            addPaymentEntry();
            toggleSplitPayments();
        }
    });
    
    // Update total paid when payment amounts change
    if (paymentsList) {
        paymentsList.addEventListener('input', (e) => {
            if (e.target.classList.contains('payment-amount')) {
                updateTotalPaid();
            }
        });
        paymentsList.addEventListener('change', (e) => {
            if (e.target.classList.contains('payment-method')) {
                updateTotalPaid();
            }
        });
    }

    // Cash Drawer
    openDrawerBtn?.addEventListener('click', openDrawer);
    closeDrawerBtn?.addEventListener('click', closeDrawer);

    // Hold/Resume
    holdSaleBtn?.addEventListener('click', holdSale);
    resumeSaleBtn?.addEventListener('click', resumeSale);

    // Promotion code
    promotionCodeInput?.addEventListener('blur', (e) => {
        const code = e.target.value.trim();
        if (code) {
            validatePromotionCode(code);
        }
    });
    promotionCodeInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            validatePromotionCode(e.target.value.trim());
        }
    });

    // Gift card
    applyGiftCardBtn?.addEventListener('click', () => {
        const cardNumber = giftCardNumberInput?.value.trim();
        if (cardNumber) {
            applyGiftCard(cardNumber);
        }
    });
    giftCardNumberInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            applyGiftCard(e.target.value.trim());
        }
    });

    // Show/hide gift card section based on payment method
    if (paymentsList) {
        paymentsList.addEventListener('change', (e) => {
            if (e.target.classList.contains('payment-method')) {
                const isGiftCard = e.target.value === 'gift_card';
                if (giftCardSection) {
                    giftCardSection.style.display = isGiftCard ? 'block' : 'none';
                }
            }
        });
    }

    // Loyalty points
    redeemLoyaltyPointsBtn?.addEventListener('click', redeemLoyaltyPoints);

    // Clear and Complete
    clearSaleBtn?.addEventListener('click', clearSale);
    completeSaleBtn?.addEventListener('click', submitSale);
    
    // Initialize payment total
    updateTotalPaid();

    // Keyboard shortcuts
    document.addEventListener('keydown', (event) => {
        // F1 - Focus search
        if (event.key === 'F1' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            searchInput?.focus();
        }
        // F2 - Focus customer search
        if (event.key === 'F2' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            customerSearchInput?.focus();
        }
        // F3 - Complete sale
        if (event.key === 'F3' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            submitSale();
        }
        // F4 - Clear sale
        if (event.key === 'F4' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            clearSale();
        }
        // F5 - Open drawer
        if (event.key === 'F5' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            openDrawer();
        }
        // F6 - Close drawer
        if (event.key === 'F6' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            closeDrawer();
        }
        // Ctrl+H or Cmd+H - Hold sale
        if ((event.ctrlKey || event.metaKey) && event.key === 'h') {
            event.preventDefault();
            holdSale();
        }
        // Ctrl+R or Cmd+R - Resume sale
        if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
            event.preventDefault();
            resumeSale();
        }
        // Ctrl+C or Cmd+C - Clear sale (when not in input)
        if ((event.ctrlKey || event.metaKey) && event.key === 'c' && 
            event.target.tagName !== 'INPUT' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            clearSale();
        }
        // Ctrl+P or Cmd+P - Print last receipt
        if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
            if (lastSaleData) {
                event.preventDefault();
                printReceipt(lastSaleData);
            }
        }
    });

    // Email Receipt Functions
    async function getCustomerEmail(entityType, entityId) {
        if (!entityType || !entityId) return null;
        try {
            // Fetch entity details using unified search API
            const response = await fetch(`${customersUrl}?entity_type=${encodeURIComponent(entityType)}&entity_id=${entityId}`);
            const json = await response.json();
            if (json.success && json.data) {
                // If data is an array, get first item, otherwise use data directly
                const entity = Array.isArray(json.data) ? json.data[0] : json.data;
                return entity?.email || null;
            }
        } catch (error) {
            console.error('Error fetching entity email:', error);
        }
        return null;
    }

    async function sendEmailReceipt(saleId, emailAddress, customerId = null) {
        if (!receiptUrl) {
            showToast('Receipt API not configured', true);
            return;
        }
        
        if (!saleId || saleId <= 0) {
            showToast('Invalid sale ID', true);
            return;
        }
        
        if (!emailAddress || !emailAddress.trim()) {
            showToast('Email address is required', true);
            return;
        }
        
        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailAddress.trim())) {
            showToast('Invalid email address format', true);
            return;
        }
        
        try {
            const payload = {
                sale_id: saleId,
                email_address: emailAddress.trim(),
            };
            
            // Only include customer_id if it's provided and valid
            if (customerId && customerId > 0) {
                payload.customer_id = customerId;
            }
            
            const response = await fetch(receiptUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            
            let result;
            try {
                result = await response.json();
            } catch (jsonError) {
                throw new Error(`Invalid response from server: ${response.status} ${response.statusText}`);
            }
            
            if (!response.ok) {
                // Handle HTTP errors
                const errorMessage = result.message || `HTTP ${response.status}: ${response.statusText}`;
                console.error('Email receipt API error:', errorMessage, result);
                showToast(errorMessage, true);
                return;
            }
            
            if (result.success) {
                showToast(`Receipt sent to ${emailAddress}`);
            } else {
                const errorMessage = result.message || 'Failed to send email receipt';
                console.error('Email receipt failed:', errorMessage, result);
                showToast(errorMessage, true);
            }
        } catch (error) {
            console.error('Error sending email receipt:', error);
            showToast('Failed to send email receipt: ' + (error.message || 'Unknown error'), true);
        }
    }

    // Initialize
    loadCategories();
    loadProducts();
})();
