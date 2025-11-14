(() => {
    const app = document.getElementById('posApp');
    if (!app) return;

    const catalogUrl = app.dataset.apiCatalog;
    const inventoryUrl = app.dataset.apiInventory;
    const salesUrl = app.dataset.apiSales;
    const settingsUrl = app.dataset.apiSettings;
    const cashierId = parseInt(app.dataset.cashierId || '0', 10);
    const defaultStoreName = app.dataset.defaultStoreName || '';
    const companyName = app.dataset.companyName || 'ABBIS POS';

    const productGrid = document.getElementById('posProductGrid');
    const searchInput = document.getElementById('posSearch');
    const categorySelect = document.getElementById('posCategoryFilter');
    const refreshBtn = document.getElementById('refreshCatalogBtn');
    const storeSelect = document.getElementById('posStoreSelect');

    const cartContainer = document.getElementById('posCart');
    const itemCountEl = document.getElementById('cartItemCount');
    const subtotalEl = document.getElementById('cartSubtotal');
    const discountEl = document.getElementById('cartDiscount');
    const taxEl = document.getElementById('cartTax');
    const totalEl = document.getElementById('cartTotal');
    const receiptPreview = document.getElementById('receiptPreview');

    const customerNameInput = document.getElementById('posCustomerName');
    const paymentMethodSelect = document.getElementById('posPaymentMethod');
    const amountReceivedInput = document.getElementById('posAmountReceived');
    const notesInput = document.getElementById('posNotes');
    const clearSaleBtn = document.getElementById('clearSaleBtn');
    const completeSaleBtn = document.getElementById('completeSaleBtn');

    const inventoryModal = document.getElementById('inventoryModal');
    const adjustProductSelect = document.getElementById('adjustProductSelect');
    const adjustStoreSelect = document.getElementById('adjustStoreSelect');
    const adjustTypeSelect = document.getElementById('adjustType');
    const adjustQuantityInput = document.getElementById('adjustQuantity');
    const adjustCostInput = document.getElementById('adjustCost');
    const adjustRemarksInput = document.getElementById('adjustRemarks');
    const adjustSubmitBtn = document.getElementById('adjustSubmitBtn');
    const hardwareModal = document.getElementById('hardwareSettingsModal');
    const openHardwareBtn = document.getElementById('openHardwareSettings');
    const saveHardwareBtn = document.getElementById('saveHardwareSettingsBtn');
    const testHardwareBtn = document.getElementById('testHardwarePrintBtn');
    const printerModeSelect = document.getElementById('posPrinterMode');
    const printerWidthInput = document.getElementById('posPrinterWidth');
    const printerEndpointInput = document.getElementById('posPrinterEndpoint');
    const barcodePrefixInput = document.getElementById('posBarcodePrefix');
    const receiptFooterInput = document.getElementById('posReceiptFooter');

    let products = [];
    let filteredProducts = [];
    let categories = [];
    let searchQuery = '';
    let cart = [];

    function formatMoney(amount) {
        return 'GHS ' + Number(amount || 0).toFixed(2);
    }

    function renderProducts(list) {
        if (!list.length) {
            productGrid.innerHTML = '<div class="pos-empty" style="grid-column: 1 / -1;">No products found.</div>';
            return;
        }

        const fragment = document.createDocumentFragment();
        list.forEach(product => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'pos-product-card';
            card.dataset.productId = product.id;
            card.dataset.sku = product.sku;
            card.dataset.name = product.name;
            card.dataset.price = product.unit_price;
            card.dataset.trackInventory = product.track_inventory ? '1' : '0';

            card.innerHTML = `
                <div class="pos-product-name">${product.name}</div>
                <div class="pos-product-meta">
                    <span>${formatMoney(product.unit_price)}</span>
                    <span>${product.sku || ''}</span>
                </div>
                <div class="pos-chips">
                    ${product.category_name ? `<span class="pos-chip">${product.category_name}</span>` : ''}
                    ${product.track_inventory ? '<span class="pos-chip">Tracked</span>' : ''}
                </div>
            `;

            card.addEventListener('click', () => addToCart(product));
            fragment.appendChild(card);
        });

        productGrid.innerHTML = '';
        productGrid.appendChild(fragment);
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
            return name.includes(searchQuery) || sku.includes(searchQuery);
        });
        renderProducts(filteredProducts);
    }

    async function loadProducts() {
        try {
            productGrid.innerHTML = '<div class="pos-empty" style="grid-column: 1 / -1;">Loading products...</div>';
            const response = await fetch(`${catalogUrl}?limit=200`);
            const json = await response.json();
            if (!json.success) throw new Error(json.message || 'Failed to load catalog');
            products = Array.isArray(json.data) ? json.data : [];
            populateAdjustmentSelect(products);
            applyFilters();
        } catch (error) {
            productGrid.innerHTML = `<div class="pos-empty" style="grid-column: 1 / -1; color: var(--danger);">Error loading products: ${error.message}</div>`;
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

    function addToCart(product) {
        const existing = cart.find(item => item.id === product.id);
        if (existing) {
            existing.quantity = Math.round(existing.quantity + 1);
        } else {
            cart.push({
                id: product.id,
                sku: product.sku,
                name: product.name,
                unit_price: parseFloat(product.unit_price),
                quantity: 1,
                track_inventory: !!product.track_inventory,
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
        const discount = 0;
        const tax = subtotal * 0.0; // Provide placeholder for future tax configuration
        const total = subtotal - discount + tax;
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
                    <div style="font-size:0.75rem; color:var(--secondary);">${item.sku || ''}</div>
                </div>
                <input type="number" min="0" step="1" value="${Math.round(item.quantity)}">
                <div style="text-align:right;">
                    <div>${formatMoney(item.unit_price)}</div>
                    <div style="font-size:0.8rem; color:var(--secondary);">${formatMoney(item.unit_price * item.quantity)}</div>
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
        discountEl.textContent = formatMoney(discount);
        taxEl.textContent = formatMoney(tax);
        totalEl.textContent = formatMoney(total);
        amountReceivedInput.placeholder = total ? total.toFixed(2) : 'Amount received';
        renderReceiptPreview(subtotal, discount, tax, total);
    }

    function renderReceiptPreview(subtotal, discount, tax, total) {
        if (!cart.length) {
            receiptPreview.textContent = 'Add items to generate a receipt preview.';
            return;
        }

        const lineWidth = 42;
        const divider = '-'.repeat(lineWidth);
        const selectedStore = storeSelect?.selectedOptions[0]?.textContent?.trim() || defaultStoreName;
        const paymentLabel = paymentMethodSelect?.selectedOptions?.[0]?.textContent?.trim() || paymentMethodSelect?.value || 'N/A';
        const tendered = amountReceivedInput.value ? parseFloat(amountReceivedInput.value) : total;
        const changeDue = tendered - total;

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
            labelValue('Store', selectedStore),
            labelValue('Cashier', cashierId || '-'),
            labelValue('Date', new Date().toLocaleString()),
            divider,
        ];

        cart.forEach(item => {
            const lineTotal = item.unit_price * item.quantity;
            const nameLine = item.name.length > lineWidth ? item.name.slice(0, lineWidth - 1) + '…' : item.name;
            lines.push(nameLine);
            lines.push(labelValue(`  x${item.quantity} @ ${item.unit_price.toFixed(2)}`, formatMoney(lineTotal)));
        });

        lines.push(divider);
        lines.push(labelValue('Subtotal', subtotal.toFixed(2)));
        lines.push(labelValue('Discount', discount.toFixed(2)));
        lines.push(labelValue('Tax', tax.toFixed(2)));
        lines.push(labelValue('Total', total.toFixed(2)));
        lines.push(labelValue('Paid', tendered.toFixed(2)));
        lines.push(labelValue('Change', (changeDue > 0 ? changeDue : 0).toFixed(2)));
        lines.push(divider);
        lines.push(labelValue('Payment', paymentLabel));
        if (notesInput.value) {
            lines.push('Note: ' + notesInput.value.substring(0, lineWidth - 6));
        }
        lines.push(center('Thank you for your business!'));

        receiptPreview.textContent = lines.join('\n');
    }

    function clearSale() {
        cart = [];
        customerNameInput.value = '';
        amountReceivedInput.value = '';
        amountReceivedInput.placeholder = 'Amount received';
        paymentMethodSelect.value = paymentMethodSelect.options[0]?.value || 'cash';
        notesInput.value = '';
        renderCart();
        showToast('Sale cleared. Start fresh.');
    }

    function populateAdjustmentSelect(productList) {
        if (!adjustProductSelect) return;
        adjustProductSelect.innerHTML = '';
        productList.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = `${product.name} (${product.sku || 'SKU'})`;
            adjustProductSelect.appendChild(option);
        });
    }

    async function submitInventoryAdjustment() {
        try {
            const payload = {
                store_id: parseInt(adjustStoreSelect.value, 10),
                product_id: parseInt(adjustProductSelect.value, 10),
                quantity_delta: parseFloat(adjustQuantityInput.value || '0'),
                transaction_type: adjustTypeSelect.value,
                unit_cost: adjustCostInput.value ? parseFloat(adjustCostInput.value) : null,
                remarks: adjustRemarksInput.value,
                performed_by: cashierId,
            };

            const response = await fetch(inventoryUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const json = await response.json();
            if (!json.success) throw new Error(json.message || 'Adjustment failed');

            adjustQuantityInput.value = '';
            adjustCostInput.value = '';
            adjustRemarksInput.value = '';
            toggleModal(inventoryModal, false);
            showToast('Inventory updated.');
        } catch (error) {
            showToast(error.message, true);
        }
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
        const amountReceived = parseFloat(amountReceivedInput.value || total);
        if (Number.isNaN(amountReceived) || amountReceived < total) {
            showToast('Amount received cannot be less than total.', true);
            return;
        }

        const storeId = Number.parseInt(storeSelect?.value ?? '', 10);
        if (!Number.isFinite(storeId) || storeId <= 0) {
            showToast('Select a valid store before completing the sale.', true);
            return;
        }

        const payments = [{
            payment_method: paymentMethodSelect.value || 'cash',
            amount: amountReceived,
        }];

        const payload = {
            store_id: storeId,
            cashier_id: cashierId,
            customer_name: customerNameInput.value || null,
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
            })),
            payments,
        };

        let responseStatus = 0;
        try {
            completeSaleBtn.disabled = true;
            completeSaleBtn.textContent = 'Processing...';

            const response = await fetch(salesUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload),
            });
            responseStatus = response.status;

            let result;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const rawText = await response.text();
                console.error('POS sale raw response:', rawText);
            }

            if (!response.ok) {
                const message = result?.message
                    || (responseStatus === 403
                        ? 'You do not have permission to process sales.'
                        : `Sale could not be completed (HTTP ${responseStatus}).`);
                throw new Error(message);
            }

            if (!result?.success) {
                throw new Error(result?.message || 'Sale failed.');
            }

            const saleNumber = result.data?.sale_number ? ` ${result.data.sale_number}` : '';
            showToast(`Sale${saleNumber} completed.`);
            clearSale();
        } catch (error) {
            const fallback = error instanceof Error ? error.message : 'Sale failed.';
            showToast(fallback, true);
        } finally {
            completeSaleBtn.disabled = false;
            completeSaleBtn.textContent = 'Complete Sale';
        }
    }

    function toggleModal(modal, show) {
        if (!modal) return;
        modal.classList.toggle('active', !!show);
        modal.setAttribute('aria-hidden', show ? 'false' : 'true');
        requestAnimationFrame(() => {
            const anyActiveModal = document.querySelector('.modal-backdrop.active');
            document.body.classList.toggle('modal-open', !!anyActiveModal);
        });
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
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3200);
    }

    // Event wiring
    searchInput?.addEventListener('input', (event) => {
        searchQuery = (event.target.value || '').toLowerCase().trim();
        applyFilters();
    });

    categorySelect?.addEventListener('change', () => {
        applyFilters();
    });

    refreshBtn?.addEventListener('click', loadProducts);
    clearSaleBtn?.addEventListener('click', clearSale);
    completeSaleBtn?.addEventListener('click', submitSale);

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const targetId = e.currentTarget.getAttribute('data-close-modal');
            const modal = document.getElementById(targetId);
            toggleModal(modal, false);
        });
    });

    adjustSubmitBtn?.addEventListener('click', submitInventoryAdjustment);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            toggleModal(inventoryModal, false);
            toggleModal(hardwareModal, false);
        }
    });

    document.getElementById('adjustStoreSelect')?.addEventListener('change', () => {});

    document.getElementById('openInventoryModalBtn')?.addEventListener('click', () => {
        toggleModal(inventoryModal, true);
    });

    [inventoryModal, hardwareModal].forEach(modal => {
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) {
                toggleModal(modal, false);
            }
        });
    });

    openHardwareBtn?.addEventListener('click', async () => {
        if (!hardwareModal) return;
        await loadHardwareSettings();
        toggleModal(hardwareModal, true);
    });

    saveHardwareBtn?.addEventListener('click', async () => {
        if (!settingsUrl) return;
        try {
            const payload = {
                pos_printer_mode: printerModeSelect?.value || 'browser',
                pos_printer_width: printerWidthInput?.value || '',
                pos_printer_endpoint: printerEndpointInput?.value || '',
                pos_barcode_prefix: barcodePrefixInput?.value || '',
                pos_receipt_footer: receiptFooterInput?.value || '',
            };
            const response = await fetch(settingsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Failed to save settings.');
            }
            showToast('Printer settings saved.');
            toggleModal(hardwareModal, false);
        } catch (error) {
            showToast(error.message || 'Unable to save printer settings.', true);
        }
    });

    testHardwareBtn?.addEventListener('click', () => {
        const preview = document.getElementById('receiptPreview');
        const content = preview ? preview.textContent : 'ABBIS POS Test Print';
        const testWindow = window.open('', '_blank', 'width=400,height=600');
        if (testWindow) {
            testWindow.document.write(`
                <pre style="font-family: monospace; white-space: pre-wrap;">
${content}

---
Printer Mode: ${printerModeSelect?.value || 'browser'}
Width: ${printerWidthInput?.value || '80'}mm
Endpoint: ${printerEndpointInput?.value || 'N/A'}
                </pre>
            `);
            testWindow.document.close();
            testWindow.focus();
            testWindow.print();
        }
    });

    async function loadHardwareSettings() {
        if (!settingsUrl) return;
        try {
            const response = await fetch(settingsUrl, { credentials: 'include' });
            const result = await response.json();
            if (!result.success || !result.data) {
                return;
            }
            const data = result.data;
            if (printerModeSelect) printerModeSelect.value = data.pos_printer_mode || 'browser';
            if (printerWidthInput) printerWidthInput.value = data.pos_printer_width || '';
            if (printerEndpointInput) printerEndpointInput.value = data.pos_printer_endpoint || '';
            if (barcodePrefixInput) barcodePrefixInput.value = data.pos_barcode_prefix || '';
            if (receiptFooterInput) receiptFooterInput.value = data.pos_receipt_footer || '';
        } catch (error) {
            console.warn('Unable to load hardware settings:', error);
        }
    }

    loadCategories();
    loadProducts();
})();

