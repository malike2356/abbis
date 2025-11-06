/**
 * Comprehensive Field Reports Manager
 * Loads all data dynamically from config - NO hardcoding
 */
class FieldReportsManager {
    constructor() {
        this.payrollRows = 0;
        this.expenseRows = 0;
        this.configData = {
            rigs: [],
            workers: [],
            roles: [],
            materials: [],
            rodLengths: [],
            clients: []
        };
        this.suggestedWorkers = []; // Workers suggested for current rig
        this.currentRigId = null;
        this.init();
    }

    async init() {
        await this.loadConfigData();
        this.initializeEventListeners();
        this.initializeTabs();
        this.addPayrollRow(); // Add initial row
        this.addExpenseRow(); // Add initial row
        this.setupClientExtraction();
        
        // Calculate initial values if form fields are already populated
        setTimeout(() => {
            this.calculateDuration();
            this.calculateTotalRPM();
            this.calculateTotalDepth();
            this.calculateConstructionDepth();
            this.calculateMaterialsValue(); // This will also update remaining materials
        }, 100);
    }

    // Load configuration data dynamically
    async loadConfigData() {
        try {
            const response = await fetch('../api/get-config-data.php?type=all');
            const result = await response.json();
            
            if (result.success) {
                this.configData = result.data;
                
                // Debug: Log roles data structure
                if (this.configData.roles) {
                    console.log('Roles data loaded:', this.configData.roles);
                    console.log('First role sample:', this.configData.roles[0]);
                }
                
                this.populateDropdowns();
                
                // Load suggested workers if rig is already selected
                const rigSelect = document.getElementById('rig_id');
                if (rigSelect && rigSelect.value) {
                    this.currentRigId = rigSelect.value;
                    await this.loadSuggestedWorkersForRig(rigSelect.value);
                }
            }
        } catch (error) {
            console.error('Error loading config data:', error);
        }
    }
    
    // Load suggested workers for a rig
    async loadSuggestedWorkersForRig(rigId) {
        try {
            const response = await fetch(`../api/worker-rig-preferences.php?action=get_rig_workers&rig_id=${rigId}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                this.suggestedWorkers = result.data;
                console.log(`Loaded ${this.suggestedWorkers.length} suggested workers for rig ${rigId}`);
                // Refresh existing payroll rows to show suggestions
                this.refreshPayrollWorkerOptions();
            } else {
                this.suggestedWorkers = [];
            }
        } catch (error) {
            console.error('Error loading suggested workers:', error);
            this.suggestedWorkers = [];
        }
    }
    
    // Refresh worker options in all payroll rows
    refreshPayrollWorkerOptions() {
        document.querySelectorAll('#payrollTable tbody tr.payroll-row .worker-select').forEach(select => {
            const currentValue = select.value;
            const currentOptions = Array.from(select.options).map(opt => ({ value: opt.value, text: opt.text }));
            
            // Clear and rebuild options with suggestions first
            select.innerHTML = '<option value="">Select Worker</option>';
            
            // Add suggested workers first (if any)
            if (this.suggestedWorkers.length > 0) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = '💡 Suggested Workers';
                this.suggestedWorkers.forEach(worker => {
                    const option = document.createElement('option');
                    option.value = worker.worker_name;
                    option.textContent = `${worker.worker_name} (${worker.preference_level})`;
                    option.dataset.role = this.getWorkerPrimaryRole(worker.worker_id);
                    option.dataset.rate = worker.default_rate || '';
                    optgroup.appendChild(option);
                });
                select.appendChild(optgroup);
            }
            
            // Add all other workers
            const allWorkers = this.configData.workers.filter(w => 
                !this.suggestedWorkers.some(sw => sw.worker_name === w.worker_name)
            );
            if (allWorkers.length > 0) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = 'All Workers';
                allWorkers.forEach(worker => {
                    const option = document.createElement('option');
                    option.value = worker.worker_name;
                    option.textContent = worker.worker_name;
                    option.dataset.role = worker.role || '';
                    option.dataset.rate = worker.default_rate || '';
                    optgroup.appendChild(option);
                });
                select.appendChild(optgroup);
            }
            
            // Restore previous selection if it still exists
            if (currentValue) {
                select.value = currentValue;
            }
        });
    }
    
    // Get primary role for a worker
    getWorkerPrimaryRole(workerId) {
        // This would ideally fetch from the API, but for now we'll use the workers data
        const worker = this.configData.workers.find(w => w.id === workerId);
        return worker ? (worker.role || '') : '';
    }

    populateDropdowns() {
        // Workers dropdown is populated dynamically when adding rows
        // Clients are populated in datalist
        const clientDatalist = document.getElementById('client-suggestions');
        if (clientDatalist) {
            clientDatalist.innerHTML = '';
            this.configData.clients.forEach(client => {
                const option = document.createElement('option');
                option.value = client.client_name;
                clientDatalist.appendChild(option);
            });
        }
    }

    initializeTabs() {
        document.querySelectorAll('.form-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                this.switchTab(tabId);
            });
        });
    }

    switchTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Remove active from all tabs
        document.querySelectorAll('.form-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        const activeContent = document.getElementById(tabId + '-tab');
        const activeTab = document.querySelector(`[data-tab="${tabId}"]`);
        
        if (activeContent) activeContent.classList.add('active');
        if (activeTab) activeTab.classList.add('active');
    }

    setupClientExtraction() {
        const clientNameField = document.getElementById('client_name');
        if (clientNameField) {
            clientNameField.addEventListener('blur', async () => {
                const clientName = clientNameField.value.trim();
                if (clientName) {
                    await this.extractClient({
                        client_name: clientName,
                        contact_person: document.getElementById('client_contact_person')?.value || '',
                        client_contact: document.getElementById('client_contact')?.value || '',
                        email: document.getElementById('client_email')?.value || ''
                    });
                }
            });
        }
    }

    async extractClient(clientData) {
        try {
            const response = await fetch('../api/client-extract.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(clientData)
            });
            const result = await response.json();
            if (result.success) {
                // Add to client suggestions if new
                if (!this.configData.clients.find(c => c.client_name === clientData.client_name)) {
                    this.configData.clients.push({
                        client_name: clientData.client_name,
                        contact_person: clientData.contact_person,
                        contact_number: clientData.client_contact,
                        email: clientData.email
                    });
                    this.populateDropdowns();
                }
            }
        } catch (error) {
            console.error('Error extracting client:', error);
        }
    }

    initializeEventListeners() {
        // Rig selection - load suggested workers
        const rigSelect = document.getElementById('rig_id');
        if (rigSelect) {
            rigSelect.addEventListener('change', (e) => {
                const rigId = e.target.value;
                this.currentRigId = rigId;
                if (rigId) {
                    this.loadSuggestedWorkersForRig(rigId);
                } else {
                    this.suggestedWorkers = [];
                    this.refreshPayrollWorkerOptions();
                }
            });
        }
        
        // Job type change - show/hide contract sum
        const jobType = document.getElementById('job_type');
        
        // Toggle maintenance fields based on job type or checkbox
        if (jobType) {
            jobType.addEventListener('change', toggleMaintenanceFields);
        }
        
        const maintenanceCheckbox = document.getElementById('is_maintenance_work');
        if (maintenanceCheckbox) {
            maintenanceCheckbox.addEventListener('change', toggleMaintenanceFields);
        }
        
        // Initial check
        toggleMaintenanceFields();
        if (jobType) {
            jobType.addEventListener('change', () => {
                const contractSumGroup = document.querySelector('#contract_sum').closest('.form-group');
                const contractSumLabel = document.getElementById('contract_sum_label');
                
                if (jobType.value === 'direct') {
                    contractSumGroup.style.display = 'block';
                    if (contractSumLabel) contractSumLabel.textContent = 'Full Contract Sum (GHS) *';
                    document.getElementById('contract_sum').required = true;
                } else {
                    contractSumGroup.style.display = 'none';
                    document.getElementById('contract_sum').value = 0;
                    document.getElementById('contract_sum').required = false;
                }
                this.calculateFinancialTotals();
            });
        }
        
        // Compliance agreement checkbox - show/hide document uploads
        const complianceCheckbox = document.getElementById('compliance_agreed');
        const complianceDocumentsRow = document.getElementById('compliance_documents_row');
        if (complianceCheckbox && complianceDocumentsRow) {
            complianceCheckbox.addEventListener('change', () => {
                if (complianceCheckbox.checked) {
                    complianceDocumentsRow.style.display = 'flex';
                    // Make file inputs optional
                    document.getElementById('survey_document').required = false;
                    document.getElementById('contract_document').required = false;
                } else {
                    complianceDocumentsRow.style.display = 'none';
                    // Clear file inputs when hidden
                    document.getElementById('survey_document').value = '';
                    document.getElementById('contract_document').value = '';
                }
            });
        }
        
        // Real-time calculations
        document.addEventListener('input', (e) => this.handleRealTimeCalculation(e));
        
        // Form submission
        const form = document.getElementById('fieldReportForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }

    handleRealTimeCalculation(e) {
        const target = e.target;
        
        // Time calculations
        if (target.id === 'start_time' || target.id === 'finish_time') {
            this.calculateDuration();
        }
        
        // RPM calculations
        if (target.id === 'start_rpm' || target.id === 'finish_rpm') {
            this.calculateTotalRPM();
        }
        
        // Depth calculations
        if (target.id === 'rod_length' || target.id === 'rods_used') {
            this.calculateTotalDepth();
        }
        
        // Materials calculations
        if (target.id === 'screen_pipes_used' || target.id === 'plain_pipes_used' || 
            target.id === 'screen_pipes_received' || target.id === 'plain_pipes_received' ||
            target.id === 'gravel_used' || target.id === 'gravel_received') {
            this.calculateConstructionDepth();
            this.calculateMaterialsValue();
        }
        
        // Update remaining materials display when received/used changes
        if (target.id === 'screen_pipes_received' || target.id === 'screen_pipes_used' ||
            target.id === 'plain_pipes_received' || target.id === 'plain_pipes_used' ||
            target.id === 'gravel_received' || target.id === 'gravel_used') {
            this.calculateMaterialsValue();
        }
        
        // Materials value calculation when materials provider changes
        if (target.id === 'materials_provided_by') {
            this.calculateMaterialsValue();
        }
        
        // Financial calculations
        if (target.classList.contains('financial-input')) {
            this.calculateFinancialTotals();
        }
        
        // Worker pay calculations
        if (target.closest('.payroll-row')) {
            this.calculateWorkerPay(target.closest('.payroll-row'));
        }
        
        // Expense calculations
        if (target.closest('.expense-row')) {
            this.calculateExpenseAmount(target.closest('.expense-row'));
        }
    }

    calculateDuration() {
        const startTime = document.getElementById('start_time')?.value;
        const finishTime = document.getElementById('finish_time')?.value;
        const durationField = document.getElementById('total_duration');
        const displayField = document.getElementById('duration_display');
        
        if (startTime && finishTime) {
            const duration = ABBISCalculations.calculateDuration(startTime, finishTime);
            
            // Store minutes in hidden field for database
            if (durationField) {
                durationField.value = duration;
            }
            
            // Display formatted duration (hours and minutes)
            if (displayField) {
                displayField.value = ABBISCalculations.formatDuration(duration);
            }
        } else {
            // Clear fields if times are missing
            if (durationField) durationField.value = '';
            if (displayField) displayField.value = '';
        }
    }

    calculateTotalRPM() {
        const startRPM = parseFloat(document.getElementById('start_rpm')?.value) || 0;
        const finishRPM = parseFloat(document.getElementById('finish_rpm')?.value) || 0;
        const totalRPMField = document.getElementById('total_rpm');
        
        if (totalRPMField) {
            const totalRPM = ABBISCalculations.calculateTotalRPM(startRPM, finishRPM);
            totalRPMField.value = totalRPM.toFixed(2);
            
            // Validate RPM values and show warnings
            let warnings = [];
            
            // Check for unrealistic RPM values (likely data entry errors)
            if (startRPM > 1000) {
                warnings.push(`⚠️ Start RPM (${startRPM}) seems unrealistic. Did you mean ${(startRPM / 100).toFixed(2)}? (Check for decimal point error)`);
            } else if (startRPM > 100) {
                warnings.push(`⚠️ Start RPM (${startRPM}) seems high. Please verify.`);
            }
            
            if (finishRPM > 1000) {
                warnings.push(`⚠️ Finish RPM (${finishRPM}) seems unrealistic. Did you mean ${(finishRPM / 100).toFixed(2)}? (Check for decimal point error)`);
            } else if (finishRPM > 100) {
                warnings.push(`⚠️ Finish RPM (${finishRPM}) seems high. Please verify.`);
            }
            
            if (totalRPM > 100) {
                warnings.push(`⚠️ Total RPM (${totalRPM}) seems unrealistic. Typical range is 0.5-10 per job.`);
            } else if (totalRPM > 10) {
                warnings.push(`⚠️ Total RPM (${totalRPM}) seems high. Please verify.`);
            }
            
            // Show warnings if any
            if (warnings.length > 0) {
                const warningDiv = document.getElementById('rpmWarning');
                if (warningDiv) {
                    warningDiv.innerHTML = warnings.join('<br>');
                    warningDiv.style.display = 'block';
                    warningDiv.style.color = '#f59e0b';
                    warningDiv.style.fontSize = '12px';
                    warningDiv.style.marginTop = '8px';
                    warningDiv.style.padding = '8px';
                    warningDiv.style.background = 'rgba(245,158,11,0.1)';
                    warningDiv.style.borderRadius = '4px';
                }
            } else {
                const warningDiv = document.getElementById('rpmWarning');
                if (warningDiv) {
                    warningDiv.style.display = 'none';
                }
            }
        }
    }

    calculateTotalDepth() {
        const rodLength = parseFloat(document.getElementById('rod_length')?.value) || 0;
        const rodsUsed = parseInt(document.getElementById('rods_used')?.value) || 0;
        const totalDepthField = document.getElementById('total_depth');
        
        if (totalDepthField) {
            const totalDepth = ABBISCalculations.calculateTotalDepth(rodLength, rodsUsed);
            totalDepthField.value = totalDepth.toFixed(1);
        }
    }

    calculateConstructionDepth() {
        const screenPipesUsedEl = document.getElementById('screen_pipes_used');
        const plainPipesUsedEl = document.getElementById('plain_pipes_used');
        const constructionDepthField = document.getElementById('construction_depth');
        
        if (!constructionDepthField) return;
        
        // Get values directly from elements to avoid stale data
        const screenPipesUsed = screenPipesUsedEl ? parseInt(screenPipesUsedEl.value) || 0 : 0;
        const plainPipesUsed = plainPipesUsedEl ? parseInt(plainPipesUsedEl.value) || 0 : 0;
        
        // Construction depth = (screen pipes used + plain pipes used) * 3 meters per pipe
        // Formula: (screen + plain) * 3
        const totalPipes = screenPipesUsed + plainPipesUsed;
        const constructionDepth = totalPipes * 3;
        
        // Debug log (remove in production)
        console.log('Construction Depth Calculation:', {
            screenPipesUsed,
            plainPipesUsed,
            totalPipes,
            constructionDepth
        });
        
        // Ensure we get a number, not NaN
        if (!isNaN(constructionDepth) && constructionDepth >= 0) {
            constructionDepthField.value = parseFloat(constructionDepth.toFixed(1));
        } else {
            constructionDepthField.value = 0.0;
        }
    }

    async calculateMaterialsValue() {
        // Calculate value of remaining materials based on quantity and cost
        // Only calculate when materials are provided by company (assets)
        const materialsProvidedByEl = document.getElementById('materials_provided_by');
        const materialsProvidedBy = materialsProvidedByEl ? materialsProvidedByEl.value : '';
        
        // Calculate remaining materials (always show, even if not company)
        const screenPipesReceivedEl = document.getElementById('screen_pipes_received');
        const screenPipesUsedEl = document.getElementById('screen_pipes_used');
        const plainPipesReceivedEl = document.getElementById('plain_pipes_received');
        const plainPipesUsedEl = document.getElementById('plain_pipes_used');
        const gravelReceivedEl = document.getElementById('gravel_received');
        const gravelUsedEl = document.getElementById('gravel_used');
        
        const screenPipesReceived = screenPipesReceivedEl ? parseInt(screenPipesReceivedEl.value) || 0 : 0;
        const screenPipesUsed = screenPipesUsedEl ? parseInt(screenPipesUsedEl.value) || 0 : 0;
        const plainPipesReceived = plainPipesReceivedEl ? parseInt(plainPipesReceivedEl.value) || 0 : 0;
        const plainPipesUsed = plainPipesUsedEl ? parseInt(plainPipesUsedEl.value) || 0 : 0;
        const gravelReceived = gravelReceivedEl ? parseInt(gravelReceivedEl.value) || 0 : 0;
        const gravelUsed = gravelUsedEl ? parseInt(gravelUsedEl.value) || 0 : 0;
        
        const screenPipesRemaining = Math.max(0, screenPipesReceived - screenPipesUsed);
        const plainPipesRemaining = Math.max(0, plainPipesReceived - plainPipesUsed);
        const gravelRemaining = Math.max(0, gravelReceived - gravelUsed);
        
        // Update remaining materials display
        const screenPipesRemainingEl = document.getElementById('screen_pipes_remaining');
        const plainPipesRemainingEl = document.getElementById('plain_pipes_remaining');
        const gravelRemainingEl = document.getElementById('gravel_remaining');
        
        if (screenPipesRemainingEl) screenPipesRemainingEl.value = screenPipesRemaining;
        if (plainPipesRemainingEl) plainPipesRemainingEl.value = plainPipesRemaining;
        if (gravelRemainingEl) gravelRemainingEl.value = gravelRemaining;
        
        if (materialsProvidedBy !== 'company') {
            const materialsValueField = document.getElementById('materials_value');
            if (materialsValueField) {
                materialsValueField.value = 0;
            }
            return;
        }
        
        // Wait for config data to be loaded if not available
        if (!this.configData.materials || this.configData.materials.length === 0) {
            await this.loadConfigData();
        }
        
        let totalValue = 0;
        
        // Map material types to form field names (accounting for plural forms)
        const materialFieldMap = {
            'screen_pipe': 'screen_pipes',
            'plain_pipe': 'plain_pipes',
            'gravel': 'gravel'
        };
        
        for (const material of this.configData.materials || []) {
            const materialType = material.material_type || '';
            const fieldBase = materialFieldMap[materialType] || materialType;
            
            // Get values directly from DOM elements
            const receivedEl = document.getElementById(`${fieldBase}_received`);
            const usedEl = document.getElementById(`${fieldBase}_used`);
            const received = receivedEl ? parseInt(receivedEl.value) || 0 : 0;
            const used = usedEl ? parseInt(usedEl.value) || 0 : 0;
            const remaining = received - used;
            const unitCost = parseFloat(material.unit_cost) || 0;
            
            // Debug log (remove in production)
            console.log('Material Value Calculation:', {
                materialType,
                fieldBase,
                received,
                used,
                remaining,
                unitCost,
                value: remaining * unitCost
            });
            
            // Only add value for remaining materials (assets)
            if (remaining > 0 && unitCost > 0) {
                totalValue += remaining * unitCost;
            }
        }
        
        const materialsValueField = document.getElementById('materials_value');
        if (materialsValueField) {
            materialsValueField.value = parseFloat(totalValue.toFixed(2));
            // Update summary display
            const summaryField = document.getElementById('materials_value_summary');
            if (summaryField) {
                summaryField.textContent = 'GHS ' + totalValue.toFixed(2);
            }
        }
        
        console.log('Total Materials Value:', totalValue);
    }

    calculateFinancialTotals() {
        const formData = new FormData(document.getElementById('fieldReportForm'));
        const data = Object.fromEntries(formData.entries());
        
        // Get totals from payroll and expenses
        data.total_wages = this.calculateTotalWages();
        data.daily_expenses = this.calculateTotalExpenses();
        
        const totals = ABBISCalculations.calculateFinancialTotals(data);
        
        // Update display fields
        this.updateFinancialDisplay('total_income_display', totals.totalIncome);
        this.updateFinancialDisplay('total_expenses_display', totals.totalExpenses);
        this.updateFinancialDisplay('total_wages_display', totals.totalWages);
        this.updateFinancialDisplay('total_wages_summary_display', totals.totalWages);
        this.updateFinancialDisplay('net_profit_display', totals.netProfit);
        this.updateFinancialDisplay('money_banked_display', totals.totalMoneyBanked);
        this.updateFinancialDisplay('days_balance_display', totals.daysBalance);
        this.updateFinancialDisplay('outstanding_rig_fee_display', totals.outstandingRigFee);
        this.updateFinancialDisplay('materials_income_display', totals.materialsIncome);
        
        // Update profit color
        const netProfitEl = document.getElementById('net_profit_display');
        if (netProfitEl) {
            netProfitEl.className = totals.netProfit >= 0 ? 'kpi-value' : 'kpi-value debt';
        }
        
        // Update hidden fields
        this.updateHiddenField('total_income', totals.totalIncome);
        this.updateHiddenField('total_expenses', totals.totalExpenses);
        this.updateHiddenField('total_wages', totals.totalWages);
        this.updateHiddenField('net_profit', totals.netProfit);
        this.updateHiddenField('total_money_banked', totals.totalMoneyBanked);
        this.updateHiddenField('days_balance', totals.daysBalance);
        this.updateHiddenField('outstanding_rig_fee', totals.outstandingRigFee);
    }

    calculateTotalWages() {
        let total = 0;
        document.querySelectorAll('.payroll-row input[name="amount"]').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        return total;
    }

    calculateTotalExpenses() {
        let total = 0;
        document.querySelectorAll('.expense-row input[name="amount"]').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        return total;
    }

    calculateWorkerPay(row) {
        const units = parseFloat(row.querySelector('[name="units"]')?.value) || 0;
        const rate = parseFloat(row.querySelector('[name="pay_per_unit"]')?.value) || 0;
        const benefits = parseFloat(row.querySelector('[name="benefits"]')?.value) || 0;
        const loanReclaim = parseFloat(row.querySelector('[name="loan_reclaim"]')?.value) || 0;
        const amountField = row.querySelector('[name="amount"]');
        
        if (amountField) {
            const amount = ABBISCalculations.calculateWorkerPay(units, rate, benefits, loanReclaim);
            amountField.value = amount;
        }
        
        this.calculateFinancialTotals();
    }

    calculateExpenseAmount(row) {
        const unitCost = parseFloat(row.querySelector('[name="unit_cost"]')?.value) || 0;
        const quantity = parseFloat(row.querySelector('[name="quantity"]')?.value) || 0;
        const amountField = row.querySelector('[name="amount"]');
        
        if (amountField) {
            const amount = ABBISCalculations.calculateExpenseAmount(unitCost, quantity);
            amountField.value = amount;
        }
        
        this.calculateFinancialTotals();
    }

    updateFinancialDisplay(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = ABBISCalculations.formatCurrency(value);
        }
    }

    updateHiddenField(fieldName, value) {
        let field = document.querySelector(`[name="${fieldName}"]`);
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = fieldName;
            document.getElementById('fieldReportForm').appendChild(field);
        }
        field.value = value;
    }

    // Payroll management - Dynamic worker loading
    addPayrollRow() {
        // Note: 10 workers per rig is a guideline, not a strict limit
        const existingRows = document.querySelectorAll('#payrollTable tbody tr.payroll-row').length;
        if (existingRows >= 10) {
            // Show a warning but allow adding more
            if (window.abbisApp) {
                window.abbisApp.showAlert('info', 'Note: Recommended maximum is 10 workers per rig. You can add more if needed.');
            }
        }
        
        this.payrollRows++;
        const tbody = document.querySelector('#payrollTable tbody');
        if (!tbody) return;
        
        const row = document.createElement('tr');
        row.className = 'payroll-row';
        
        // Build worker options dynamically with suggestions first
        let workerOptions = '<option value="">Select Worker</option>';
        
        // Add suggested workers first (if any)
        if (this.suggestedWorkers.length > 0) {
            workerOptions += '<optgroup label="💡 Suggested Workers">';
            this.suggestedWorkers.forEach(worker => {
                const role = this.getWorkerPrimaryRole(worker.worker_id);
                const rate = worker.default_rate || '';
                workerOptions += `<option value="${worker.worker_name}" data-role="${role}" data-rate="${rate}" style="font-weight: 600;">${worker.worker_name} (${worker.preference_level})</option>`;
            });
            workerOptions += '</optgroup>';
        }
        
        // Add all other workers
        const allWorkers = this.configData.workers.filter(w => 
            !this.suggestedWorkers.some(sw => sw.worker_name === w.worker_name)
        );
        if (allWorkers.length > 0) {
            workerOptions += '<optgroup label="All Workers">';
            allWorkers.forEach(worker => {
                workerOptions += `<option value="${worker.worker_name}" data-role="${worker.role || ''}" data-rate="${worker.default_rate || ''}">${worker.worker_name}</option>`;
            });
            workerOptions += '</optgroup>';
        }
        
        // Build role options dynamically
        // Note: roles is an array of objects with role_name and description properties
        let roleOptions = '<option value="">Select Role</option>';
        if (this.configData.roles && Array.isArray(this.configData.roles)) {
            this.configData.roles.forEach(role => {
                // Handle both object format {role_name: "...", description: "..."} and string format
                let roleName;
                if (typeof role === 'string') {
                    roleName = role;
                } else if (role && typeof role === 'object') {
                    // Extract role_name from object
                    roleName = role.role_name || role.name || role.role || '';
                } else {
                    roleName = String(role);
                }
                
                if (roleName) {
                    roleOptions += `<option value="${roleName}">${roleName}</option>`;
                }
            });
        }
        
        row.innerHTML = `
            <td>
                <select name="worker_name" class="form-control worker-select" required>
                    ${workerOptions}
                </select>
            </td>
            <td>
                <select name="role" class="form-control role-select" required>
                    ${roleOptions}
                </select>
            </td>
            <td>
                <select name="wage_type" class="form-control" required>
                    <option value="per_borehole">Per Borehole</option>
                    <option value="daily">Daily</option>
                    <option value="hourly">Hourly</option>
                    <option value="custom">Custom</option>
                </select>
            </td>
            <td><input type="number" name="units" class="form-control" min="0" step="1" value="1" required></td>
            <td><input type="number" name="pay_per_unit" class="form-control" min="0" step="0.01" value="0" required></td>
            <td><input type="number" name="benefits" class="form-control" min="0" step="0.01" value="0"></td>
            <td><input type="number" name="loan_reclaim" class="form-control" min="0" step="0.01" value="0"></td>
            <td><input type="number" name="amount" class="form-control" readonly></td>
            <td><input type="checkbox" name="paid_today" value="1"></td>
            <td><input type="text" name="notes" class="form-control" placeholder="Notes"></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); fieldReportsManager.calculateFinancialTotals();">×</button>
            </td>
        `;
        
        tbody.appendChild(row);
        
        // Auto-fill role and rate when worker is selected
        const workerSelect = row.querySelector('.worker-select');
        const roleSelect = row.querySelector('.role-select');
        const rateInput = row.querySelector('[name="pay_per_unit"]');
        
        workerSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.dataset.role) {
                roleSelect.value = selectedOption.dataset.role;
            }
            if (selectedOption.dataset.rate) {
                rateInput.value = selectedOption.dataset.rate;
                fieldReportsManager.calculateWorkerPay(row);
            }
        });
        
        this.addPayrollRowListeners(row);
    }

    addPayrollRowListeners(row) {
        const inputs = row.querySelectorAll('input[name="units"], input[name="pay_per_unit"], input[name="benefits"], input[name="loan_reclaim"]');
        inputs.forEach(input => {
            input.addEventListener('input', () => this.calculateWorkerPay(row));
        });
    }

    addExpenseRow() {
        this.expenseRows++;
        const tbody = document.querySelector('#expensesTable tbody');
        if (!tbody) return;
        
        const row = document.createElement('tr');
        row.className = 'expense-row';
        row.innerHTML = `
            <td>
                <div style="display:flex; gap:8px; align-items:center;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:12px; color: var(--secondary); white-space:nowrap;">
                        <input type="checkbox" name="is_custom" checked> Custom item
                    </label>
                    <select name="catalog_item_select" class="form-control" style="min-width:220px;" disabled><option value="">— Select from Catalog —</option></select>
                    <input type="text" name="description" class="form-control" placeholder="Description" required>
                </div>
                <input type="hidden" name="catalog_item_id">
                <input type="hidden" name="unit">
            </td>
            <td><input type="number" name="unit_cost" class="form-control" min="0" step="0.01" value="0" required></td>
            <td><input type="number" name="quantity" class="form-control" min="0" step="0.01" value="1" required></td>
            <td><input type="number" name="amount" class="form-control" readonly></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); fieldReportsManager.calculateFinancialTotals();">×</button>
            </td>
        `;
        
        tbody.appendChild(row);
        this.addExpenseRowListeners(row);

        // Load catalog items into the select for this row (lazy per-row to reduce initial load)
        const sel = row.querySelector('select[name="catalog_item_select"]');
        const isCustom = row.querySelector('input[name="is_custom"]');
        const descInput = row.querySelector('input[name="description"]');
        fetch('../api/catalog-items.php')
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                const items = res.data || [];
                sel.innerHTML = '<option value="">— Select from Catalog —</option>' + items.map(it => {
                    const price = Number(it.sell_price || 0).toFixed(2);
                    const label = `${it.name} (GHS ${price})`;
                    return `<option value="${it.id}" data-name="${it.name.replace(/"/g,'&quot;')}" data-unit="${it.unit || ''}" data-price="${it.sell_price}" data-cost="${it.cost_price}" data-type="${it.item_type}">${label}</option>`;
                }).join('');
            }).catch(()=>{});

        sel.addEventListener('change', () => {
            const opt = sel.options[sel.selectedIndex];
            const id = sel.value;
            const name = opt?.dataset?.name || '';
            const unit = opt?.dataset?.unit || '';
            const price = parseFloat(opt?.dataset?.price || '0');
            // Prefer cost for expense rows; if 0, fallback to price
            const cost = parseFloat(opt?.dataset?.cost || '0') || price;

            row.querySelector('[name="catalog_item_id"]').value = id || '';
            row.querySelector('[name="description"]').value = name;
            row.querySelector('[name="unit"]').value = unit;
            row.querySelector('[name="unit_cost"]').value = isFinite(cost) ? cost : 0;
            row.querySelector('[name="quantity"]').value = 1;
            this.calculateExpenseAmount(row);
        });

        // Toggle custom vs catalog
        isCustom.addEventListener('change', () => {
            const useCustom = isCustom.checked;
            sel.disabled = useCustom;
            if (useCustom) {
                // Clear any catalog linkage when switching to custom
                sel.value = '';
                row.querySelector('[name="catalog_item_id"]').value = '';
            }
            // Focus appropriate field
            if (useCustom) descInput.focus(); else sel.focus();
        });
    }

    addExpenseRowListeners(row) {
        const inputs = row.querySelectorAll('input[name="unit_cost"], input[name="quantity"]');
        inputs.forEach(input => {
            input.addEventListener('input', () => this.calculateExpenseAmount(row));
        });
    }

    async handleFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : 'Save Report';
        
        // Show immediate feedback
        this.showNotification('info', 'Saving report... Please wait.', false);
        
        try {
            // Extract client data first
            const clientData = {
                client_name: document.getElementById('client_name').value,
                contact_person: document.getElementById('client_contact_person').value,
                client_contact: document.getElementById('client_contact').value,
                email: document.getElementById('client_email').value
            };
            
            // Prepare payroll data
            const payrollData = [];
            document.querySelectorAll('.payroll-row').forEach(row => {
                const workerName = row.querySelector('[name="worker_name"]').value;
                if (workerName) {
                    payrollData.push({
                        worker_name: workerName,
                        role: row.querySelector('[name="role"]').value,
                        wage_type: row.querySelector('[name="wage_type"]').value,
                        units: row.querySelector('[name="units"]').value,
                        pay_per_unit: row.querySelector('[name="pay_per_unit"]').value,
                        benefits: row.querySelector('[name="benefits"]').value,
                        loan_reclaim: row.querySelector('[name="loan_reclaim"]').value,
                        amount: row.querySelector('[name="amount"]').value,
                        paid_today: row.querySelector('[name="paid_today"]').checked,
                        notes: row.querySelector('[name="notes"]').value
                    });
                }
            });
            
            // Prepare expense data
            const expenseData = [];
            document.querySelectorAll('.expense-row').forEach(row => {
                const desc = row.querySelector('[name="description"]').value;
                if (desc) {
                    expenseData.push({
                        description: desc,
                        unit_cost: row.querySelector('[name="unit_cost"]').value,
                        quantity: row.querySelector('[name="quantity"]').value,
                        amount: row.querySelector('[name="amount"]').value,
                        catalog_item_id: row.querySelector('[name="catalog_item_id"]').value || null,
                        unit: row.querySelector('[name="unit"]').value || null
                    });
                }
            });
            
            // Add to form data
            const formData = new FormData(form);
            formData.append('payroll', JSON.stringify(payrollData));
            formData.append('expenses', JSON.stringify(expenseData));
            formData.append('client_data', JSON.stringify(clientData));
            
            // Submit via AJAX
            if (window.abbisApp && submitBtn) {
                window.abbisApp.setLoadingState(submitBtn, true, originalText);
            } else if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Saving...';
            }
            
            const response = await fetch('../api/save-report.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success notification
                this.showNotification('success', 'Report saved successfully! Report ID: ' + result.report_id, true);
                
                // Also use app alert if available
                if (window.abbisApp) {
                    window.abbisApp.showAlert('success', 'Report saved successfully! Report ID: ' + result.report_id);
                }
                
                // Show receipt/report buttons
                const receiptBtn = document.getElementById('generateReceiptBtn');
                const reportBtn = document.getElementById('generateReportBtn');
                if (receiptBtn) receiptBtn.style.display = 'inline-block';
                if (reportBtn) reportBtn.style.display = 'inline-block';
                
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 2000);
                }
            } else {
                // Show error notification
                const errorMsg = result.message || 'Error saving report. Please check your input and try again.';
                this.showNotification('error', errorMsg, true);
                
                if (window.abbisApp) {
                    window.abbisApp.showAlert('error', errorMsg);
                } else {
                    alert('Error: ' + errorMsg);
                }
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            const errorMsg = 'Network error. Please check your connection and try again.';
            this.showNotification('error', errorMsg, true);
            
            if (window.abbisApp) {
                window.abbisApp.showAlert('error', errorMsg);
            } else {
                alert(errorMsg);
            }
        } finally {
            if (window.abbisApp && submitBtn) {
                window.abbisApp.setLoadingState(submitBtn, false, originalText);
            } else if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    }
    
    showNotification(type, message, autoHide = true) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.field-report-notification');
        existing.forEach(n => n.remove());
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `field-report-notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ⓘ'}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Add styles if not already added
        if (!document.getElementById('field-report-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'field-report-notification-styles';
            style.textContent = `
                .field-report-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    min-width: 300px;
                    max-width: 500px;
                    padding: 16px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    animation: slideInRight 0.3s ease-out;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                .notification-success {
                    background: #10b981;
                    color: white;
                }
                .notification-error {
                    background: #ef4444;
                    color: white;
                }
                .notification-info {
                    background: #3b82f6;
                    color: white;
                }
                .notification-content {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .notification-icon {
                    font-size: 20px;
                    font-weight: bold;
                    flex-shrink: 0;
                }
                .notification-message {
                    flex: 1;
                    font-size: 14px;
                    line-height: 1.5;
                }
                .notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    opacity: 0.8;
                    transition: opacity 0.2s;
                }
                .notification-close:hover {
                    opacity: 1;
                }
            `;
            document.head.appendChild(style);
        }
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto-hide after delay
        if (autoHide) {
            const delay = type === 'error' ? 8000 : 5000;
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                notification.style.transition = 'all 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, delay);
        }
    }
}

// Utility functions
function generateReceipt() {
    window.location.href = '../modules/receipt.php?report_id=' + (new URLSearchParams(window.location.search).get('id') || '');
}

function generateReport() {
    window.location.href = '../modules/technical-report.php?report_id=' + (new URLSearchParams(window.location.search).get('id') || '');
}

// Toggle maintenance fields visibility
function toggleMaintenanceFields() {
    const jobType = document.getElementById('job_type');
    const maintenanceCheckbox = document.getElementById('is_maintenance_work');
    const maintenanceSection = document.getElementById('maintenanceFieldsSection');
    
    if (!maintenanceSection) return;
    
    const isMaintenance = 
        (jobType && jobType.value === 'maintenance') ||
        (maintenanceCheckbox && maintenanceCheckbox.checked);
    
    if (isMaintenance) {
        maintenanceSection.style.display = 'block';
        
        // Auto-sync checkbox with job type
        if (jobType && jobType.value === 'maintenance' && maintenanceCheckbox) {
            maintenanceCheckbox.checked = true;
        }
    } else {
        maintenanceSection.style.display = 'none';
        
        // Auto-sync checkbox with job type
        if (jobType && jobType.value !== 'maintenance' && maintenanceCheckbox) {
            maintenanceCheckbox.checked = false;
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.fieldReportsManager = new FieldReportsManager();
    
    // Initialize maintenance fields toggle
    toggleMaintenanceFields();
    
    // Reload config data periodically
    setInterval(() => {
        window.fieldReportsManager.loadConfigData();
    }, 60000); // Every minute
});
