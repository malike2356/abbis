/**
 * Offline Field Reports Manager
 * Handles offline report capture, storage, sync, and conflict resolution
 */

const STORAGE_KEY = 'abbis_offline_reports';
// Detect if we're in /offline/ directory and adjust API path
const basePath = window.location.pathname.includes('/offline/') ? '../' : '';
const SYNC_API = basePath + 'api/sync-offline-reports.php';
let workerRowCount = 0;
let expenseRowCount = 0;
let currentEditingId = null;
let syncInProgress = false;

const FIELD_ROLES = ['Driller', 'Rig Driver', 'Support Driver', 'Spanner/Table Boy', 'Rod Boy', 'Supervisor', 'Manager'];
const WORKER_DIRECTORY_KEY = 'abbis_offline_worker_directory';
const RIG_DIRECTORY_KEY = 'abbis_offline_rig_directory';
let workerDirectory = loadWorkerDirectory();
let rigDirectory = loadRigDirectory();
let workerModalTargetIndex = null;

function roundToTwo(value) {
    const num = parseFloat(value);
    if (Number.isNaN(num)) {
        return 0;
    }
    return Math.round((num + Number.EPSILON) * 100) / 100;
}

function toNumber(value) {
    const num = parseFloat(value);
    return Number.isNaN(num) ? 0 : num;
}

function deriveOfflineReportMetrics(report = {}) {
    const safeReport = typeof report === 'object' && report !== null ? report : {};
    const workers = Array.isArray(safeReport.workers)
        ? safeReport.workers.filter(Boolean).map(worker => {
            const normalized = {
                ...worker,
                units: toNumber(worker.units),
                pay_per_unit: toNumber(worker.pay_per_unit ?? worker.payPerUnit),
                benefits: toNumber(worker.benefits),
                loan_reclaim: toNumber(worker.loan_reclaim ?? worker.loanReclaim),
                amount: roundToTwo(toNumber(worker.amount))
            };
            if (normalized.pay_per_unit === 0 && normalized.units && normalized.amount) {
                normalized.pay_per_unit = roundToTwo(normalized.amount / normalized.units);
            }
            return normalized;
        })
        : [];

    const expenses = Array.isArray(safeReport.expenses)
        ? safeReport.expenses.filter(Boolean).map(expense => {
            const quantity = toNumber(expense.quantity);
            const unitCost = toNumber(expense.unit_cost ?? expense.unitCost);
            const amount = roundToTwo(toNumber(expense.amount) || quantity * unitCost);
            return {
                ...expense,
                quantity,
                unit_cost: unitCost,
                amount
            };
        })
        : [];

    const totalWages = roundToTwo(workers.reduce((sum, worker) => {
        return sum + toNumber(worker.amount);
    }, 0));

    const expensesTotal = roundToTwo(expenses.reduce((sum, expense) => {
        return sum + toNumber(expense.amount);
    }, 0));

    const originalWorkerCount = toNumber(safeReport.total_workers);
    const totalWorkers = Math.max(workers.length, originalWorkerCount);

    const enrichedReport = {
        ...safeReport,
        total_workers: totalWorkers,
        total_wages: roundToTwo(safeReport.total_wages !== undefined ? toNumber(safeReport.total_wages) : totalWages),
        daily_expenses: roundToTwo(safeReport.daily_expenses !== undefined ? toNumber(safeReport.daily_expenses) : expensesTotal),
        loans_amount: toNumber(safeReport.loans_amount)
    };

    const financialTotals = calculateOfflineFinancials(
        enrichedReport,
        enrichedReport.total_wages,
        enrichedReport.daily_expenses
    );

    return {
        report: enrichedReport,
        workers,
        expenses,
        totalWorkers: enrichedReport.total_workers ?? workers.length,
        totalWages: financialTotals ? financialTotals.totalWages : enrichedReport.total_wages,
        expensesTotal: enrichedReport.daily_expenses,
        financialTotals
    };
}

function loadWorkerDirectory() {
    try {
        const raw = localStorage.getItem(WORKER_DIRECTORY_KEY);
        if (!raw) {
            return [];
        }
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            return parsed.filter(entry => entry && entry.name && entry.role);
        }
        return [];
    } catch (error) {
        console.warn('Failed to load worker directory from storage:', error);
        return [];
    }
}

function saveWorkerDirectory() {
    try {
        sortWorkerDirectory();
        localStorage.setItem(WORKER_DIRECTORY_KEY, JSON.stringify(workerDirectory));
    } catch (error) {
        console.error('Failed to save worker directory:', error);
    }
}

function sortWorkerDirectory() {
    workerDirectory.sort((a, b) => {
        return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
    });
}

function loadRigDirectory() {
    try {
        const raw = localStorage.getItem(RIG_DIRECTORY_KEY);
        if (!raw) {
            return [];
        }
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            return parsed.filter(entry => entry && entry.name && entry.code);
        }
        return [];
    } catch (error) {
        console.warn('Failed to load rig directory from storage:', error);
        return [];
    }
}

function saveRigDirectory() {
    try {
        sortRigDirectory();
        localStorage.setItem(RIG_DIRECTORY_KEY, JSON.stringify(rigDirectory));
    } catch (error) {
        console.error('Failed to save rig directory:', error);
    }
}

function sortRigDirectory() {
    rigDirectory.sort((a, b) => {
        return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
    });
}

function populateRoleOptions(selectEl, currentValue = '') {
    if (!selectEl) return;
    selectEl.innerHTML = '<option value="">Select role</option>';
    FIELD_ROLES.forEach(role => {
        const option = document.createElement('option');
        option.value = role;
        option.textContent = role;
        if (role === currentValue) {
            option.selected = true;
        }
        selectEl.appendChild(option);
    });
}

function openWorkerModal(index) {
    workerModalTargetIndex = index;
    renderWorkerModal();
    const modal = document.getElementById('workerModal');
    if (modal) {
        modal.classList.add('active');
    }
    const search = document.getElementById('workerModalSearch');
    if (search) {
        search.value = '';
        search.focus();
    }
}

function closeWorkerModal() {
    const modal = document.getElementById('workerModal');
    if (modal) {
        modal.classList.remove('active');
    }
    workerModalTargetIndex = null;
}

function renderWorkerModal(filter = '') {
    const list = document.getElementById('workerModalList');
    if (!list) return;
    list.innerHTML = '';
    sortWorkerDirectory();
    const lower = filter.trim().toLowerCase();
    const filteredWorkers = workerDirectory.filter(worker => worker.name.toLowerCase().includes(lower));

    filteredWorkers.forEach(worker => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'worker-option';
        btn.innerHTML = `<span>${worker.name}</span><small>${worker.role}</small>`;
        btn.addEventListener('click', () => selectWorkerFromModal(worker.name, worker.role));
        list.appendChild(btn);
    });

    if (list.children.length === 0) {
        const empty = document.createElement('div');
        empty.style.padding = '14px';
        empty.style.color = 'rgba(15,23,42,0.7)';
        empty.style.background = 'rgba(248,250,252,0.9)';
        empty.style.border = '1px dashed rgba(148,163,184,0.4)';
        empty.style.borderRadius = '12px';
        empty.style.display = 'flex';
        empty.style.flexDirection = 'column';
        empty.style.gap = '10px';
        empty.innerHTML = `
            <strong>No workers found.</strong>
            <span style="font-size:13px;">Configure your worker list to make selection faster.</span>
        `;
        const manageBtn = document.createElement('button');
        manageBtn.type = 'button';
        manageBtn.className = 'btn btn-outline btn-sm';
        manageBtn.textContent = 'Configure Worker Directory';
        manageBtn.addEventListener('click', () => {
            closeWorkerModal();
            openWorkerDirectoryModal();
        });
        empty.appendChild(manageBtn);
        list.appendChild(empty);
    }
}

function selectWorkerFromModal(name, role) {
    if (workerModalTargetIndex === null) return;
    const nameInput = document.querySelector(`[name="worker_${workerModalTargetIndex}_name"]`);
    const roleSelect = document.querySelector(`[name="worker_${workerModalTargetIndex}_role"]`);
    if (nameInput) {
        nameInput.value = name;
    }
    if (roleSelect) {
        populateRoleOptions(roleSelect, role || '');
    }
    closeWorkerModal();
}

function openWorkerDirectoryModal() {
    const modal = document.getElementById('workerDirectoryModal');
    if (!modal) return;
    populateRoleOptions(document.getElementById('newWorkerRole'));
    renderWorkerDirectoryList();
    modal.classList.add('active');
    const nameInput = document.getElementById('newWorkerName');
    if (nameInput) {
        nameInput.value = '';
        nameInput.focus();
    }
}

function closeWorkerDirectoryModal() {
    const modal = document.getElementById('workerDirectoryModal');
    if (modal) {
        modal.classList.remove('active');
    }
    const form = document.getElementById('workerDirectoryForm');
    if (form) {
        form.reset();
    }
}

function handleWorkerDirectorySubmit(event) {
    event.preventDefault();
    const nameInput = document.getElementById('newWorkerName');
    const roleSelect = document.getElementById('newWorkerRole');
    if (!nameInput || !roleSelect) return;

    const name = nameInput.value.trim();
    const role = roleSelect.value;

    if (!name) {
        showNotification('Please enter a worker name.', 'warning');
        return;
    }
    if (!role) {
        showNotification('Please select a role for the worker.', 'warning');
        return;
    }

    const exists = workerDirectory.some(worker => worker.name.toLowerCase() === name.toLowerCase());
    if (exists) {
        showNotification('A worker with this name already exists.', 'info');
        return;
    }

    workerDirectory.push({ name, role });
    saveWorkerDirectory();
    renderWorkerDirectoryList();
    renderWorkerModal();

    nameInput.value = '';
    nameInput.focus();
    roleSelect.value = '';
    showNotification(`${name} added to worker directory.`, 'success');
}

function renderWorkerDirectoryList() {
    const list = document.getElementById('workerDirectoryList');
    if (!list) return;
    list.innerHTML = '';
    sortWorkerDirectory();

    if (workerDirectory.length === 0) {
        const empty = document.createElement('div');
        empty.style.padding = '14px';
        empty.style.borderRadius = '12px';
        empty.style.border = '1px dashed rgba(148,163,184,0.4)';
        empty.style.background = 'rgba(248,250,252,0.92)';
        empty.style.color = 'rgba(15,23,42,0.65)';
        empty.textContent = 'No workers configured yet. Add names to build your offline directory.';
        list.appendChild(empty);
        return;
    }

    workerDirectory.forEach((worker, index) => {
        const item = document.createElement('div');
        item.className = 'directory-item';
        const info = document.createElement('span');
        info.innerHTML = `${worker.name}<small>${worker.role}</small>`;
        item.appendChild(info);
        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '8px';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger btn-sm';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', () => {
            if (confirm(`Remove ${worker.name} from directory?`)) {
                workerDirectory.splice(index, 1);
                saveWorkerDirectory();
                renderWorkerDirectoryList();
                renderWorkerModal();
                showNotification(`${worker.name} removed from worker directory.`, 'info');
            }
        });

        actions.appendChild(removeBtn);
        item.appendChild(actions);
        list.appendChild(item);
    });
}

function openRigModal() {
    renderRigModal();
    const modal = document.getElementById('rigModal');
    if (modal) {
        modal.classList.add('active');
    }
    const search = document.getElementById('rigModalSearch');
    if (search) {
        search.value = '';
        search.focus();
    }
}

function closeRigModal() {
    const modal = document.getElementById('rigModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function renderRigModal(filter = '') {
    const list = document.getElementById('rigModalList');
    if (!list) return;
    list.innerHTML = '';
    sortRigDirectory();
    const lower = filter.trim().toLowerCase();
    const filteredRigs = rigDirectory.filter(rig => {
        return rig.name.toLowerCase().includes(lower) || rig.code.toLowerCase().includes(lower);
    });

    filteredRigs.forEach(rig => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'worker-option';
        btn.innerHTML = `<span>${rig.name}</span><small>${rig.code}</small>`;
        btn.addEventListener('click', () => selectRigFromModal(rig));
        list.appendChild(btn);
    });

    if (list.children.length === 0) {
        const empty = document.createElement('div');
        empty.style.padding = '14px';
        empty.style.color = 'rgba(15,23,42,0.7)';
        empty.style.background = 'rgba(248,250,252,0.9)';
        empty.style.border = '1px dashed rgba(148,163,184,0.4)';
        empty.style.borderRadius = '12px';
        empty.style.display = 'flex';
        empty.style.flexDirection = 'column';
        empty.style.gap = '10px';
        empty.innerHTML = `
            <strong>No rigs found.</strong>
            <span style="font-size:13px;">Add rigs to the directory to reuse them quickly.</span>
        `;
        const manageBtn = document.createElement('button');
        manageBtn.type = 'button';
        manageBtn.className = 'btn btn-outline btn-sm';
        manageBtn.textContent = 'Configure Rig Directory';
        manageBtn.addEventListener('click', () => {
            closeRigModal();
            openRigDirectoryModal();
        });
        empty.appendChild(manageBtn);
        list.appendChild(empty);
    }
}

function selectRigFromModal(rig) {
    const nameInput = document.querySelector('[name="rig_name"]');
    const idInput = document.querySelector('[name="rig_id"]');
    if (nameInput) {
        nameInput.value = rig.code ? `${rig.name} (${rig.code})` : rig.name;
        nameInput.dataset.rigName = rig.name;
        nameInput.dataset.rigCode = rig.code;
    }
    if (idInput) {
        idInput.value = rig.code || rig.id || rig.name;
    }
    closeRigModal();
}

function openRigDirectoryModal() {
    const modal = document.getElementById('rigDirectoryModal');
    if (!modal) return;
    renderRigDirectoryList();
    modal.classList.add('active');
    const nameInput = document.getElementById('newRigName');
    const codeInput = document.getElementById('newRigCode');
    if (nameInput) {
        nameInput.value = '';
        nameInput.focus();
    }
    if (codeInput) {
        codeInput.value = '';
    }
}

function closeRigDirectoryModal() {
    const modal = document.getElementById('rigDirectoryModal');
    if (modal) {
        modal.classList.remove('active');
    }
    const form = document.getElementById('rigDirectoryForm');
    if (form) {
        form.reset();
    }
}

function handleRigDirectorySubmit(event) {
    event.preventDefault();
    const nameInput = document.getElementById('newRigName');
    const codeInput = document.getElementById('newRigCode');
    if (!nameInput || !codeInput) return;

    const name = nameInput.value.trim();
    const code = codeInput.value.trim();

    if (!name) {
        showNotification('Please enter a rig name.', 'warning');
        return;
    }
    if (!code) {
        showNotification('Please enter a rig code.', 'warning');
        return;
    }

    const exists = rigDirectory.some(rig =>
        rig.name.toLowerCase() === name.toLowerCase() || rig.code.toLowerCase() === code.toLowerCase()
    );
    if (exists) {
        showNotification('A rig with this name or code already exists.', 'info');
        return;
    }

    rigDirectory.push({
        id: `rig_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
        name,
        code
    });
    saveRigDirectory();
    renderRigDirectoryList();
    renderRigModal();

    nameInput.value = '';
    codeInput.value = '';
    nameInput.focus();
    showNotification(`${name} (${code}) added to rig directory.`, 'success');
}

function renderRigDirectoryList() {
    const list = document.getElementById('rigDirectoryList');
    if (!list) return;
    list.innerHTML = '';
    sortRigDirectory();

    if (rigDirectory.length === 0) {
        const empty = document.createElement('div');
        empty.style.padding = '14px';
        empty.style.borderRadius = '12px';
        empty.style.border = '1px dashed rgba(148,163,184,0.4)';
        empty.style.background = 'rgba(248,250,252,0.92)';
        empty.style.color = 'rgba(15,23,42,0.65)';
        empty.textContent = 'No rigs configured yet. Add rigs to make selection faster.';
        list.appendChild(empty);
        return;
    }

    rigDirectory.forEach((rig, index) => {
        const item = document.createElement('div');
        item.className = 'directory-item';
        const info = document.createElement('span');
        info.innerHTML = `${rig.name}<small>${rig.code}</small>`;
        item.appendChild(info);

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '8px';

        const useBtn = document.createElement('button');
        useBtn.type = 'button';
        useBtn.className = 'btn btn-outline btn-sm';
        useBtn.textContent = 'Use';
        useBtn.addEventListener('click', () => {
            selectRigFromModal(rig);
            closeRigDirectoryModal();
        });

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger btn-sm';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', () => {
            if (confirm(`Remove ${rig.name} (${rig.code}) from directory?`)) {
                rigDirectory.splice(index, 1);
                saveRigDirectory();
                renderRigDirectoryList();
                renderRigModal();
                showNotification(`${rig.name} removed from rig directory.`, 'info');
            }
        });

        actions.appendChild(useBtn);
        actions.appendChild(removeBtn);
        item.appendChild(actions);
        list.appendChild(item);
    });
}

function toggleInfoModal() {
    const modal = document.getElementById('infoModal');
    if (!modal) return;
    modal.classList.toggle('active');
}

function setHeroStatus(text) {
    const el = document.getElementById('heroStatus');
    if (el) {
        el.textContent = text;
    }
}

function setDurationDisplay(value) {
    const textEl = document.getElementById('totalDurationText');
    const labelEl = document.getElementById('totalDurationDisplay');
    const minutes = parseInt(value, 10);
    let formatted = '';
    if (!Number.isNaN(minutes)) {
        const sign = minutes < 0 ? '-' : '';
        const absMinutes = Math.abs(minutes);
        const hours = Math.floor(absMinutes / 60);
        const mins = absMinutes % 60;
        formatted = `${sign}${hours}h ${mins.toString().padStart(2, '0')}m`;
    }
    if (textEl) {
        textEl.value = formatted || '0h 00m';
    }
    if (labelEl) {
        labelEl.textContent = formatted;
    }
}

function updateDurationDisplay() {
    const input = document.getElementById('totalDurationMinutes');
    if (input) {
        setDurationDisplay(input.value);
    }
}

function setHeroPendingCount(count) {
    const el = document.getElementById('heroPending');
    if (!el) return;
    if (count === 0) {
        el.textContent = 'No pending reports';
    } else {
        el.textContent = `${count} pending ${count === 1 ? 'report' : 'reports'}`;
    }
}

function updateHeroBackupLabel() {
    const el = document.getElementById('heroBackup');
    if (!el) return;
    const stored = localStorage.getItem('abbis_last_excel_backup') || localStorage.getItem('abbis_last_excel_export');
    if (!stored) {
        el.textContent = 'Not created';
        return;
    }
    const backupDate = new Date(stored);
    if (Number.isNaN(backupDate.getTime())) {
        el.textContent = 'Not created';
        return;
    }
    const now = new Date();
    const diffMs = now - backupDate;
    const diffMins = Math.floor(diffMs / 60000);
    let label;
    if (diffMins < 1) {
        label = 'Just now';
    } else if (diffMins < 60) {
        label = `${diffMins} min ago`;
    } else if (diffMins < 60 * 24) {
        const hours = Math.floor(diffMins / 60);
        label = `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        label = backupDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) +
            ' · ' + backupDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    el.textContent = label;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
    initializeForm();
    initializePosStores();
    initializeSync();
    loadPendingReports();
    setDefaultDate();
    updateHeroBackupLabel();
    
    // Set up online/offline detection
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    // Update import button visibility on load
    updateImportButtonVisibility();
    
    const workerSearch = document.getElementById('workerModalSearch');
    if (workerSearch) {
        workerSearch.addEventListener('input', (e) => renderWorkerModal(e.target.value));
    }
    const workerModal = document.getElementById('workerModal');
    if (workerModal) {
        workerModal.addEventListener('click', (e) => {
            if (e.target === workerModal) {
                closeWorkerModal();
            }
        });
    }
    const infoModal = document.getElementById('infoModal');
    if (infoModal) {
        infoModal.addEventListener('click', (e) => {
            if (e.target === infoModal) {
                toggleInfoModal();
            }
        });
    }
    const workerDirectoryModal = document.getElementById('workerDirectoryModal');
    if (workerDirectoryModal) {
        workerDirectoryModal.addEventListener('click', (e) => {
            if (e.target === workerDirectoryModal) {
                closeWorkerDirectoryModal();
            }
        });
    }
    const workerDirectoryForm = document.getElementById('workerDirectoryForm');
    if (workerDirectoryForm) {
        workerDirectoryForm.addEventListener('submit', handleWorkerDirectorySubmit);
    }
    populateRoleOptions(document.getElementById('newWorkerRole'));
    sortWorkerDirectory();
    renderWorkerDirectoryList();
    renderWorkerModal();

    const rigSearch = document.getElementById('rigModalSearch');
    if (rigSearch) {
        rigSearch.addEventListener('input', (e) => renderRigModal(e.target.value));
    }
    const rigModal = document.getElementById('rigModal');
    if (rigModal) {
        rigModal.addEventListener('click', (e) => {
            if (e.target === rigModal) {
                closeRigModal();
            }
        });
    }
    const rigDirectoryModal = document.getElementById('rigDirectoryModal');
    if (rigDirectoryModal) {
        rigDirectoryModal.addEventListener('click', (e) => {
            if (e.target === rigDirectoryModal) {
                closeRigDirectoryModal();
            }
        });
    }
    const rigDirectoryForm = document.getElementById('rigDirectoryForm');
    if (rigDirectoryForm) {
        rigDirectoryForm.addEventListener('submit', handleRigDirectorySubmit);
    }
    sortRigDirectory();
    renderRigDirectoryList();
    renderRigModal();

    const detailModal = document.getElementById('offlineDetailModal');
    if (detailModal) {
        detailModal.addEventListener('click', (e) => {
            if (e.target === detailModal) {
                closeOfflineDetailModal();
            }
        });
        detailModal.querySelectorAll('[data-close-modal="offlineDetailModal"]').forEach(btn => {
            btn.addEventListener('click', closeOfflineDetailModal);
        });
    }

    initializeSummaryModal();
    
    // Periodic sync check (every 30 seconds)
    setInterval(checkSync, 30000);
});

// Tab Navigation
function initializeTabs() {
    const tabs = document.querySelectorAll('.form-tab');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;
            
            // Remove active from all
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active to clicked
            tab.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
        });
    });
}

// Form Initialization
function initializeForm() {
    const form = document.getElementById('offlineForm');
    const saveBtn = document.getElementById('saveBtn');
    const clearBtn = document.getElementById('clearBtn');
    
    saveBtn.addEventListener('click', saveOfflineReport);
    clearBtn.addEventListener('click', clearForm);
    
    // Initialize auto-calculations
    initializeDrillingCalculations();
    updateDurationDisplay();
    
    // Auto-save draft every 30 seconds
    setInterval(saveDraft, 30000);
}

let offlinePosStores = [];

async function initializePosStores() {
    const providerSelect = document.getElementById('offlineMaterialsProvider');
    const stored = localStorage.getItem('abbisPosStores');
    if (stored) {
        try {
            offlinePosStores = JSON.parse(stored) || [];
        } catch (error) {
            offlinePosStores = [];
        }
    }

    populateOfflineStoreOptions();
    toggleOfflineStoreGroup();

    if (providerSelect) {
        providerSelect.addEventListener('change', toggleOfflineStoreGroup);
    }

    if (navigator.onLine) {
        await fetchPosStores();
    }
}

async function fetchPosStores() {
    try {
        const response = await fetch('../pos/api/inventory.php?action=stores', {
            credentials: 'include',
            headers: { 'Accept': 'application/json' },
        });
        const result = await response.json();
        if (result.success && Array.isArray(result.data)) {
            offlinePosStores = result.data;
            localStorage.setItem('abbisPosStores', JSON.stringify(offlinePosStores));
            populateOfflineStoreOptions();
            toggleOfflineStoreGroup();
        }
    } catch (error) {
        console.warn('Unable to fetch POS stores:', error);
    }
}

function populateOfflineStoreOptions() {
    const storeSelect = document.getElementById('offlineMaterialsStore');
    if (!storeSelect) return;

    const previous = storeSelect.value;
    storeSelect.innerHTML = '<option value="">Select Store</option>';
    offlinePosStores.forEach(store => {
        const opt = document.createElement('option');
        opt.value = store.id;
        opt.textContent = `${store.store_name} (${store.store_code})`;
        storeSelect.appendChild(opt);
    });
    if (previous) {
        storeSelect.value = previous;
    }
}

function toggleOfflineStoreGroup() {
    const providerSelect = document.getElementById('offlineMaterialsProvider');
    const storeGroup = document.getElementById('offlineStoreGroup');
    const storeSelect = document.getElementById('offlineMaterialsStore');
    if (!providerSelect || !storeGroup) return;

    const show = providerSelect.value === 'store';
    storeGroup.style.display = show ? 'block' : 'none';

    if (storeSelect) {
        if (show) {
            storeSelect.setAttribute('required', 'required');
        } else {
            storeSelect.removeAttribute('required');
            storeSelect.value = '';
        }
    }
}

// Set default date to today
function setDefaultDate() {
    const dateInput = document.querySelector('input[name="report_date"]');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }
}

// Save Offline Report
function saveOfflineReport() {
    const form = document.getElementById('offlineForm');
    const formData = new FormData(form);
    const data = {};
    
    // Collect all form data
    for (const [key, value] of formData.entries()) {
        if (key.startsWith('worker_') || key.startsWith('expense_')) {
            // Handle array fields
            if (!data[key.split('_')[0] + 's']) {
                data[key.split('_')[0] + 's'] = [];
            }
            const index = parseInt(key.split('_')[1]);
            const field = key.split('_').slice(2).join('_');
            if (!data[key.split('_')[0] + 's'][index]) {
                data[key.split('_')[0] + 's'][index] = {};
            }
            data[key.split('_')[0] + 's'][index][field] = value;
        } else {
            data[key] = value;
        }
    }

    if ((data.materials_provided_by || '') === 'store' && !data.materials_store_id) {
        showNotification('Select the POS store that supplied the materials.', 'error');
        const storeSelect = document.getElementById('offlineMaterialsStore');
        if (storeSelect) {
            storeSelect.focus();
        }
        return;
    }
    
    // Collect workers
    const workers = [];
    const workerRows = document.querySelectorAll('.worker-row');
    workerRows.forEach((row, index) => {
        const worker = {
            worker_name: row.querySelector('[name$="_name"]')?.value || '',
            role: row.querySelector('[name$="_role"]')?.value || '',
            wage_type: row.querySelector('[name$="_wage_type"]')?.value || '',
            units: row.querySelector('[name$="_units"]')?.value || '0',
            pay_per_unit: row.querySelector('[name$="_pay_per_unit"]')?.value || '0',
            benefits: row.querySelector('[name$="_benefits"]')?.value || '0',
            loan_reclaim: row.querySelector('[name$="_loan_reclaim"]')?.value || '0',
            amount: row.querySelector('[name$="_amount"]')?.value || '0',
            paid_today: row.querySelector('[name$="_paid_today"]')?.checked ? '1' : '0',
            notes: row.querySelector('[name$="_notes"]')?.value || ''
        };
        if (worker.worker_name) {
            workers.push(worker);
        }
    });
    data.workers = workers;
    
    // Collect expenses (with unit cost, quantity, amount structure)
    const expenses = [];
    const expenseRows = document.querySelectorAll('.expense-row');
    expenseRows.forEach((row, index) => {
        const expense = {
            description: row.querySelector('[name^="expense_description"]')?.value || '',
            unit_cost: row.querySelector('[name^="expense_unit_cost"]')?.value || '0',
            quantity: row.querySelector('[name^="expense_quantity"]')?.value || '0',
            amount: row.querySelector('[name^="expense_amount"]')?.value || '0',
            category: row.querySelector('[name^="expense_category"]')?.value || ''
        };
        if (expense.description) {
            expenses.push(expense);
        }
    });
    const expensesTotalRaw = roundToTwo(expenses.reduce((sum, expense) => {
        const amount = parseFloat(expense.amount);
        if (!Number.isNaN(amount)) {
            return sum + amount;
        }
        const qty = parseFloat(expense.quantity);
        const unit = parseFloat(expense.unit_cost);
        const fallback = (Number.isNaN(qty) ? 0 : qty) * (Number.isNaN(unit) ? 0 : unit);
        return sum + roundToTwo(fallback);
    }, 0));
    data.expenses = expenses;
    data.daily_expenses = expensesTotalRaw;
    
    data.rig_code = data.rig_id || '';
    const derivedMetrics = deriveOfflineReportMetrics(data);
    data.workers = derivedMetrics.workers;
    data.expenses = derivedMetrics.expenses;
    data.total_workers = derivedMetrics.totalWorkers;
    data.total_wages = derivedMetrics.totalWages;
    data.daily_expenses = roundToTwo(derivedMetrics.expensesTotal);

    if (derivedMetrics.financialTotals) {
        data.total_income = derivedMetrics.financialTotals.totalIncome;
        data.total_expenses = derivedMetrics.financialTotals.totalExpenses;
        data.net_profit = derivedMetrics.financialTotals.netProfit;
        data.total_money_banked = derivedMetrics.financialTotals.totalMoneyBanked;
        data.days_balance = derivedMetrics.financialTotals.daysBalance;
        data.outstanding_rig_fee = derivedMetrics.financialTotals.outstandingRigFee;
        data.loans_outstanding = derivedMetrics.financialTotals.loansOutstanding;
        data.total_debt = derivedMetrics.financialTotals.totalDebt;
    }
    
    // Calculate total duration from start/finish time
    if (data.start_time && data.finish_time) {
        const start = new Date('2000-01-01T' + data.start_time);
        const finish = new Date('2000-01-01T' + data.finish_time);
        if (finish < start) {
            finish.setDate(finish.getDate() + 1); // Next day
        }
        const diffMs = finish - start;
        data.total_duration = Math.round(diffMs / 60000); // Convert to minutes
    } else {
        data.total_duration = data.total_duration ? parseInt(data.total_duration, 10) || 0 : 0;
    }
    
    // Calculate total RPM
    if (data.start_rpm && data.finish_rpm) {
        data.total_rpm = Number((parseFloat(data.finish_rpm) - parseFloat(data.start_rpm)).toFixed(2));
    } else if (data.total_rpm) {
        data.total_rpm = Number(parseFloat(data.total_rpm).toFixed(2));
    } else {
        data.total_rpm = 0;
    }
    
    // Calculate total depth from rod length and rods used
    if (data.rod_length && data.rods_used) {
        data.total_depth = Number((parseFloat(data.rod_length) * parseFloat(data.rods_used)).toFixed(1));
    } else if (data.total_depth) {
        data.total_depth = Number(parseFloat(data.total_depth).toFixed(1));
    } else {
        data.total_depth = 0;
    }
    
    // Calculate construction depth
    const screenPipes = parseFloat(data.screen_pipes_used || 0);
    const plainPipes = parseFloat(data.plain_pipes_used || 0);
    data.construction_depth = Number(((screenPipes + plainPipes) * 3).toFixed(1));
    
    // Add metadata
    data.saved_at = new Date().toISOString();
    data.id = currentEditingId || generateId();
    data.status = 'pending';
    data.sync_attempts = 0;
    data.last_sync_attempt = null;
    
    // Save to localStorage
    const reports = getOfflineReports();
    
    if (currentEditingId) {
        // Update existing
        const index = reports.findIndex(r => r.id === currentEditingId);
        if (index !== -1) {
            reports[index] = data;
        }
    } else {
        // Add new
        reports.push(data);
    }
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(reports));
    
    // Automatically create Excel backup when saving offline (only if XLSX library is loaded)
    if (typeof XLSX !== 'undefined') {
        try {
            exportToExcel(true); // Silent export (no notification)
            showNotification('Report saved offline! Excel backup created automatically.', 'success');
        } catch (e) {
            console.warn('Excel backup failed:', e);
            showNotification('Report saved offline! (Excel backup unavailable)', 'success');
            // Don't fail the save if Excel export fails
        }
    } else {
        showNotification('Report saved offline!', 'success');
    }
    
    showOfflineSummaryModal(data, derivedMetrics);
    
    // Clear form if new report
    if (!currentEditingId) {
        clearForm();
    }
    
    // Update UI
    loadPendingReports();
    updateSyncStatus();
    
    // Try to sync if online
    if (navigator.onLine) {
        attemptSync();
    }
}

// Generate unique ID
function generateId() {
    return 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Get all offline reports
function getOfflineReports() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

// Clear Form
function clearForm() {
    document.getElementById('offlineForm').reset();
    document.getElementById('workersList').innerHTML = '';
    document.getElementById('expensesList').innerHTML = '';
    workerRowCount = 0;
    expenseRowCount = 0;
    currentEditingId = null;
    setDefaultDate();
    const rigNameInput = document.querySelector('[name="rig_name"]');
    const rigIdInput = document.querySelector('[name="rig_id"]');
    if (rigNameInput) {
        rigNameInput.value = '';
        delete rigNameInput.dataset.rigName;
        delete rigNameInput.dataset.rigCode;
    }
    if (rigIdInput) {
        rigIdInput.value = '';
    }
    updateDurationDisplay();
}

// Add Worker Row
function addWorkerRow(workerData = null) {
    const container = document.getElementById('workersList');
    const row = document.createElement('div');
    row.className = 'worker-row';
    row.style.cssText = 'display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px 20px; margin-bottom:20px; padding:18px; border:1px solid rgba(148,163,184,0.25); border-radius:14px; background:rgba(248,250,252,0.9); box-shadow:0 8px 18px rgba(15,23,42,0.12); align-items:flex-end;';
    
    const index = workerRowCount++;
    row.innerHTML = `
        <div class="form-group">
            <label class="form-label">Worker Name</label>
            <div class="input-with-button stretch">
                <input type="text" name="worker_${index}_name" class="form-control worker-name-input" value="${workerData?.worker_name || ''}" placeholder="Select worker" readonly required>
                <button type="button" class="btn btn-outline btn-sm" onclick="openWorkerModal(${index})">Select</button>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Role</label>
            <select name="worker_${index}_role" class="form-control worker-role-select">
                <option value="">Select role</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Wage Type</label>
            <select name="worker_${index}_wage_type" class="form-control">
                <option value="daily" ${workerData?.wage_type === 'daily' ? 'selected' : ''}>Daily</option>
                <option value="per_borehole" ${workerData?.wage_type === 'per_borehole' ? 'selected' : ''}>Per Borehole</option>
                <option value="hourly" ${workerData?.wage_type === 'hourly' ? 'selected' : ''}>Hourly</option>
                <option value="custom" ${workerData?.wage_type === 'custom' ? 'selected' : ''}>Custom</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Units</label>
            <input type="number" name="worker_${index}_units" class="form-control" value="${workerData?.units || '1'}" min="0" step="1">
        </div>
        <div class="form-group">
            <label class="form-label">Rate (GHS)</label>
            <input type="number" name="worker_${index}_pay_per_unit" class="form-control" value="${workerData?.pay_per_unit || '0'}" min="0" step="0.01">
        </div>
        <div class="form-group">
            <label class="form-label">Benefits (GHS)</label>
            <input type="number" name="worker_${index}_benefits" class="form-control" value="${workerData?.benefits || '0'}" min="0" step="0.01">
        </div>
        <div class="form-group">
            <label class="form-label">Loan Reclaim (GHS)</label>
            <input type="number" name="worker_${index}_loan_reclaim" class="form-control" value="${workerData?.loan_reclaim || '0'}" min="0" step="0.01">
        </div>
        <div class="form-group">
            <label class="form-label">Amount (GHS)</label>
            <input type="number" name="worker_${index}_amount" class="form-control" value="${workerData?.amount || '0'}" readonly>
        </div>
        <div class="form-group">
            <label class="form-label">Paid Today</label>
            <input type="checkbox" name="worker_${index}_paid_today" ${workerData?.paid_today === '1' ? 'checked' : ''}>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <input type="text" name="worker_${index}_notes" class="form-control" value="${workerData?.notes || ''}">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.worker-row').remove()">Delete</button>
        </div>
    `;
    
    container.appendChild(row);
    
    populateRoleOptions(row.querySelector(`[name="worker_${index}_role"]`), workerData?.role);
    
    // Add calculation listeners
    const units = row.querySelector(`[name="worker_${index}_units"]`);
    const rate = row.querySelector(`[name="worker_${index}_pay_per_unit"]`);
    const benefits = row.querySelector(`[name="worker_${index}_benefits"]`);
    const loan = row.querySelector(`[name="worker_${index}_loan_reclaim"]`);
    const amount = row.querySelector(`[name="worker_${index}_amount"]`);
    
    [units, rate, benefits, loan].forEach(input => {
        input.addEventListener('input', () => {
            const total = (parseFloat(units.value || 0) * parseFloat(rate.value || 0)) + 
                         parseFloat(benefits.value || 0) - parseFloat(loan.value || 0);
            amount.value = total.toFixed(2);
        });
    });
}

// Add auto-calculation listeners for drilling fields
function initializeDrillingCalculations() {
    const form = document.getElementById('offlineForm');
    
    // Duration calculation
    const startTime = form.querySelector('[name="start_time"]');
    const finishTime = form.querySelector('[name="finish_time"]');
    const totalDuration = form.querySelector('[name="total_duration"]');
    
    if (startTime && finishTime && totalDuration) {
        [startTime, finishTime].forEach(input => {
            input.addEventListener('change', () => {
                if (startTime.value && finishTime.value) {
                    const start = new Date('2000-01-01T' + startTime.value);
                    const finish = new Date('2000-01-01T' + finishTime.value);
                    if (finish < start) {
                        finish.setDate(finish.getDate() + 1);
                    }
                    const diffMs = finish - start;
                    totalDuration.value = Math.round(diffMs / 60000);
                    setDurationDisplay(totalDuration.value);
                } else {
                    setDurationDisplay('');
                }
            });
        });
        // Initialize display with current value
        setDurationDisplay(totalDuration.value);
    }
    
    // RPM calculation
    const startRpm = form.querySelector('[name="start_rpm"]');
    const finishRpm = form.querySelector('[name="finish_rpm"]');
    const totalRpm = form.querySelector('[name="total_rpm"]');
    
    if (startRpm && finishRpm && totalRpm) {
        [startRpm, finishRpm].forEach(input => {
            input.addEventListener('input', () => {
                if (startRpm.value && finishRpm.value) {
                    totalRpm.value = (parseFloat(finishRpm.value) - parseFloat(startRpm.value)).toFixed(2);
                }
            });
        });
    }
    
    // Total depth calculation
    const rodLength = form.querySelector('[name="rod_length"]');
    const rodsUsed = form.querySelector('[name="rods_used"]');
    const totalDepth = form.querySelector('[name="total_depth"]');
    
    if (rodLength && rodsUsed && totalDepth) {
        [rodLength, rodsUsed].forEach(input => {
            input.addEventListener('input', () => {
                if (rodLength.value && rodsUsed.value) {
                    totalDepth.value = (parseFloat(rodLength.value) * parseFloat(rodsUsed.value)).toFixed(1);
                }
            });
        });
    }
    
    // Construction depth calculation
    const screenPipes = form.querySelector('[name="screen_pipes_used"]');
    const plainPipes = form.querySelector('[name="plain_pipes_used"]');
    const constructionDepth = form.querySelector('[name="construction_depth"]');
    
    if (screenPipes && plainPipes && constructionDepth) {
        [screenPipes, plainPipes].forEach(input => {
            input.addEventListener('input', () => {
                const screen = parseFloat(screenPipes.value || 0);
                const plain = parseFloat(plainPipes.value || 0);
                constructionDepth.value = ((screen + plain) * 3).toFixed(1);
            });
        });
    }
}

// Add Expense Row
function addExpenseRow(expenseData = null) {
    const container = document.getElementById('expensesList');
    const row = document.createElement('div');
    row.className = 'expense-row';
    row.style.cssText = 'display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:12px; margin-bottom:12px; padding:12px; border:1px solid var(--border); border-radius:6px;';
    
    const index = expenseRowCount++;
    row.innerHTML = `
        <div class="form-group">
            <label class="form-label">Description</label>
            <input type="text" name="expense_${index}_description" class="form-control" value="${expenseData?.description || ''}">
        </div>
        <div class="form-group">
            <label class="form-label">Unit Cost (GHS)</label>
            <input type="number" name="expense_${index}_unit_cost" class="form-control" value="${expenseData?.unit_cost || '0'}" min="0" step="0.01">
        </div>
        <div class="form-group">
            <label class="form-label">Quantity</label>
            <input type="number" name="expense_${index}_quantity" class="form-control" value="${expenseData?.quantity || '1'}" min="0" step="1">
        </div>
        <div class="form-group">
            <label class="form-label">Amount (GHS)</label>
            <input type="number" name="expense_${index}_amount" class="form-control" value="${expenseData?.amount || '0'}" min="0" step="0.01" readonly>
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.expense-row').remove()">Delete</button>
        </div>
    `;
    
    container.appendChild(row);
    
    // Add calculation listeners
    const unitCost = row.querySelector(`[name="expense_${index}_unit_cost"]`);
    const quantity = row.querySelector(`[name="expense_${index}_quantity"]`);
    const amount = row.querySelector(`[name="expense_${index}_amount"]`);
    
    [unitCost, quantity].forEach(input => {
        input.addEventListener('input', () => {
            const total = parseFloat(unitCost.value || 0) * parseFloat(quantity.value || 0);
            amount.value = total.toFixed(2);
        });
    });
}

// Save Draft
function saveDraft() {
    const form = document.getElementById('offlineForm');
    const formData = new FormData(form);
    const draft = {};
    
    for (const [key, value] of formData.entries()) {
        draft[key] = value;
    }
    
    localStorage.setItem('abbis_offline_draft', JSON.stringify(draft));
}

// Load Draft
function loadDraft() {
    try {
        const draft = JSON.parse(localStorage.getItem('abbis_offline_draft') || '{}');
        if (Object.keys(draft).length > 0) {
            const form = document.getElementById('offlineForm');
            for (const [key, value] of Object.entries(draft)) {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                }
            }
        }
    } catch (e) {
        console.error('Error loading draft:', e);
    }
}

// Sync Management
function initializeSync() {
    updateSyncStatus();
    const syncBtn = document.getElementById('syncNowBtn');
    const syncBtnHero = document.getElementById('syncNowBtnHero');

    if (syncBtn && !syncBtn.dataset.syncBound) {
        syncBtn.addEventListener('click', () => attemptSync(true));
        syncBtn.dataset.syncBound = '1';
    }
    if (syncBtnHero && !syncBtnHero.dataset.syncBound) {
        syncBtnHero.addEventListener('click', () => attemptSync(true));
        syncBtnHero.dataset.syncBound = '1';
    }
}

function updateSyncStatus() {
    const reports = getOfflineReports();
    const pending = reports.filter(r => r.status === 'pending' || r.status === 'failed');
    const isOnline = navigator.onLine;
    
    const statusEl = document.getElementById('syncStatus');
    const syncBtn = document.getElementById('syncNowBtn');
    const syncBtnHero = document.getElementById('syncNowBtnHero');
    if (!statusEl) return;

    const indicatorEl = document.getElementById('syncIndicator');
    const textEl = document.getElementById('syncText');
    const countEl = document.getElementById('pendingCount');
    const lastSyncEl = document.getElementById('lastSync');
    const syncMessage = document.getElementById('syncMessage');

    setHeroPendingCount(pending.length);
    if (countEl) {
        countEl.textContent = pending.length;
    }
    
    if (syncInProgress) {
        statusEl.className = 'sync-status syncing';
        indicatorEl.className = 'sync-indicator syncing';
        textEl.textContent = 'Syncing...';
        if (syncBtn) {
            syncBtn.style.display = 'none';
            syncBtn.disabled = true;
        }
        if (syncBtnHero) {
            syncBtnHero.style.display = 'none';
            syncBtnHero.disabled = true;
        }
        if (syncMessage) {
            syncMessage.textContent = 'Syncing reports to server...';
        }
        setHeroStatus('Syncing…');
    } else if (isOnline) {
        statusEl.className = 'sync-status online';
        indicatorEl.className = 'sync-indicator online';
        textEl.textContent = 'Online';
        if (pending.length > 0) {
            countEl.textContent = pending.length;
            countEl.style.display = 'inline-block';
            if (syncBtn) {
                syncBtn.style.display = 'inline-block';
                syncBtn.disabled = false;
                syncBtn.textContent = '🔄 Sync Now (' + pending.length + ')';
            }
            if (syncBtnHero) {
                syncBtnHero.style.display = 'inline-flex';
                syncBtnHero.disabled = false;
                syncBtnHero.textContent = '🔄 Sync Now (' + pending.length + ')';
            }
            if (syncMessage) {
                syncMessage.textContent = pending.length + ' report(s) ready to sync. Click "Sync Now" to upload.';
            }
        } else {
            countEl.style.display = 'none';
            if (syncBtn) {
                syncBtn.style.display = 'inline-block';
                syncBtn.disabled = false;
                syncBtn.textContent = '🔄 Sync Now';
            }
            if (syncBtnHero) {
                syncBtnHero.style.display = 'inline-flex';
                syncBtnHero.disabled = false;
                syncBtnHero.textContent = '🔄 Sync Now';
            }
            if (syncMessage) {
                syncMessage.textContent = 'All reports synced. Click "Sync Now" to check for updates.';
            }
        }

        if (lastSyncEl) {
            const lastSyncValue = localStorage.getItem('abbis_last_sync');
            if (lastSyncValue) {
                const syncDate = new Date(lastSyncValue);
                const now = new Date();
                const diffMs = now - syncDate;
                const diffMins = Math.floor(diffMs / 60000);
                let timeText;
                if (diffMins < 1) {
                    timeText = 'just now';
                } else if (diffMins < 60) {
                    timeText = diffMins + ' min ago';
                } else {
                    const diffHours = Math.floor(diffMins / 60);
                    timeText = diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
                }
                lastSyncEl.textContent = 'Last sync: ' + timeText;
                lastSyncEl.style.display = 'block';
            } else {
                lastSyncEl.style.display = 'none';
            }
        }
        setHeroStatus('Online');
    } else {
        statusEl.className = 'sync-status offline';
        indicatorEl.className = 'sync-indicator offline';
        textEl.textContent = 'Offline';
        countEl.textContent = pending.length;
        countEl.style.display = pending.length > 0 ? 'inline-block' : 'none';
        if (syncBtn) {
            syncBtn.style.display = 'none';
            syncBtn.disabled = true;
        }
        if (syncBtnHero) {
            syncBtnHero.style.display = 'none';
            syncBtnHero.disabled = true;
        }
        if (syncMessage) {
            if (pending.length > 0) {
                syncMessage.textContent = pending.length + ' report(s) saved locally. Reconnect to sync.';
            } else {
                syncMessage.textContent = 'Working offline. No pending reports.';
            }
        }
        setHeroStatus('Offline');
    }
}

function handleOnline() {
    updateSyncStatus();
    updateImportButtonVisibility();
    fetchPosStores();
    attemptSync();
}

function handleOffline() {
    updateSyncStatus();
    updateImportButtonVisibility();
}

function checkSync() {
    if (navigator.onLine && !syncInProgress) {
        const reports = getOfflineReports();
        const pending = reports.filter(r => r.status === 'pending' || r.status === 'failed');
        if (pending.length > 0) {
            attemptSync();
        }
    }
}

// Attempt Sync
async function attemptSync(force = false) {
    if (!navigator.onLine) {
        showNotification('No internet connection. Please check your connection and try again.', 'error');
        updateSyncStatus();
        return;
    }
    
    if (syncInProgress && !force) {
        showNotification('Sync already in progress. Please wait...', 'info');
        return;
    }
    
    const reports = getOfflineReports();
    const pending = reports.filter(r => r.status === 'pending' || r.status === 'failed');
    
    if (pending.length === 0 && !force) {
        showNotification('All reports are already synced!', 'success');
        updateSyncStatus();
        return;
    }
    
    syncInProgress = true;
    updateSyncStatus();
    
    let synced = 0;
    let failed = 0;
    const conflicts = [];
    
    // Show progress
    const totalToSync = pending.length;
    let currentIndex = 0;
    
    for (const report of pending) {
        currentIndex++;
        try {
            // Update sync message with progress
            const syncMessage = document.getElementById('syncMessage');
            if (syncMessage) {
                syncMessage.textContent = `Syncing ${currentIndex} of ${totalToSync}...`;
            }
            
            // Include credentials for session-based auth
            const response = await fetch(SYNC_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include', // Include cookies for session auth
                body: JSON.stringify({
                    action: 'sync',
                    report: report
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type') || '';
            const rawBody = await response.text();
            let result;

            if (contentType.includes('application/json')) {
                try {
                    result = rawBody ? JSON.parse(rawBody) : {};
                } catch (parseError) {
                    const snippet = rawBody.length > 200 ? `${rawBody.slice(0, 200)}…` : rawBody;
                    throw new Error(
                        `Invalid JSON response from server. ${snippet || 'Response body was empty.'}` +
                        ' This typically means the session expired or the server returned an error page.'
                    );
                }
            } else {
                const snippet = rawBody.length > 200 ? `${rawBody.slice(0, 200)}…` : rawBody.trim();
                throw new Error(
                    `Unexpected server response. ${contentType ? `Type: ${contentType}. ` : ''}` +
                    `${snippet || 'Response body was empty.'}` +
                    ' This often means your session expired or the server returned an error page.'
                );
            }
            
            if (result.success) {
                // Mark as synced and store server ID to prevent duplicates
                report.status = 'synced';
                report.synced_at = new Date().toISOString();
                report.server_id = result.report_id;
                synced++;
                
                // Note: Report is kept in localStorage temporarily (24 hours) for user confirmation
                // It will be automatically cleaned up to prevent storage bloat and duplication
                // The server_id prevents re-syncing the same report
            } else if (result.conflict) {
                // Handle conflict
                conflicts.push({ report, server: result.server_data });
            } else {
                // Mark as failed
                report.status = 'failed';
                report.sync_attempts = (report.sync_attempts || 0) + 1;
                report.last_sync_attempt = new Date().toISOString();
                report.error = result.message || 'Unknown error';
                failed++;
            }
        } catch (error) {
            report.status = 'failed';
            report.sync_attempts = (report.sync_attempts || 0) + 1;
            report.last_sync_attempt = new Date().toISOString();
            report.error = error.message || 'Network error';
            failed++;
        }
        
        // Save updated report
        const allReports = getOfflineReports();
        const index = allReports.findIndex(r => r.id === report.id);
        if (index !== -1) {
            allReports[index] = report;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(allReports));
        }
    }
    
    // Handle conflicts
    if (conflicts.length > 0) {
        showConflictResolution(conflicts);
    }
    
    syncInProgress = false;
    updateSyncStatus();
    loadPendingReports();
    
    // Clean up old synced reports (older than 24 hours) to prevent storage bloat
    cleanupOldSyncedReports();
    
    // Show results
    if (synced > 0) {
        showNotification(`✅ ${synced} report(s) synced successfully! Synced reports are automatically cleared after 24 hours to prevent duplication.`, 'success');
        localStorage.setItem('abbis_last_sync', new Date().toISOString());
    }
    
    if (failed > 0) {
        showNotification(`❌ ${failed} report(s) failed to sync. Check pending reports for details.`, 'error');
    }
    
    if (synced === 0 && failed === 0 && pending.length === 0) {
        showNotification('✅ All reports are up to date!', 'success');
    }
}

// Remove synced report from localStorage to prevent duplication
function removeSyncedReport(reportId) {
    const reports = getOfflineReports();
    const filtered = reports.filter(r => r.id !== reportId);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(filtered));
    loadPendingReports();
    updateSyncStatus();
}

// Clean up old synced reports (older than 24 hours)
function cleanupOldSyncedReports() {
    const reports = getOfflineReports();
    const now = new Date();
    const oneDayAgo = new Date(now.getTime() - (24 * 60 * 60 * 1000)); // 24 hours ago
    
    const filtered = reports.filter(report => {
        // Keep pending and failed reports
        if (report.status === 'pending' || report.status === 'failed') {
            return true;
        }
        
        // Remove synced reports older than 24 hours
        if (report.status === 'synced' && report.synced_at) {
            const syncedDate = new Date(report.synced_at);
            return syncedDate > oneDayAgo; // Keep if synced within last 24 hours
        }
        
        // Keep reports without sync date (shouldn't happen, but safety check)
        return true;
    });
    
    if (filtered.length < reports.length) {
        const removed = reports.length - filtered.length;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(filtered));
        console.log(`Cleaned up ${removed} old synced report(s) from local storage`);
    }
}

// Conflict Resolution
function showConflictResolution(conflicts) {
    const modal = document.getElementById('conflictModal');
    const content = document.getElementById('conflictContent');
    
    let html = '<p>The following reports have conflicts with server data:</p>';
    
    conflicts.forEach((conflict, index) => {
        html += `
            <div class="conflict-warning" style="margin-bottom: 16px;">
                <strong>Report: ${conflict.report.site_name || 'Unnamed'} (${new Date(conflict.report.report_date).toLocaleDateString()})</strong>
                <p>Server has a report with similar data. Choose an action:</p>
                <div class="conflict-actions">
                    <button class="btn btn-primary btn-sm" onclick="resolveConflict(${index}, 'use_local')">Use My Version</button>
                    <button class="btn btn-outline btn-sm" onclick="resolveConflict(${index}, 'use_server')">Use Server Version</button>
                    <button class="btn btn-outline btn-sm" onclick="resolveConflict(${index}, 'merge')">Merge</button>
                    <button class="btn btn-outline btn-sm" onclick="resolveConflict(${index}, 'skip')">Skip</button>
                </div>
            </div>
        `;
    });
    
    content.innerHTML = html;
    modal.style.display = 'flex';
    
    // Store conflicts for resolution
    window.currentConflicts = conflicts;
}

function resolveConflict(index, action) {
    const conflict = window.currentConflicts[index];
    const reports = getOfflineReports();
    const reportIndex = reports.findIndex(r => r.id === conflict.report.id);
    
    if (reportIndex === -1) return;
    
    switch (action) {
        case 'use_local':
            // Force sync with local version
            conflict.report.force_sync = true;
            break;
        case 'use_server':
            // Remove local, keep server
            reports.splice(reportIndex, 1);
            break;
        case 'merge':
            // Merge data (complex - would need UI for field-by-field merge)
            showNotification('Merge feature coming soon. Using local version for now.', 'info');
            conflict.report.force_sync = true;
            break;
        case 'skip':
            // Mark as skipped
            reports[reportIndex].status = 'skipped';
            break;
    }
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(reports));
    document.getElementById('conflictModal').style.display = 'none';
    loadPendingReports();
    updateSyncStatus();
}

// Load Pending Reports
function loadPendingReports() {
    // Clean up old synced reports first
    cleanupOldSyncedReports();
    
    const reports = getOfflineReports();
    const pending = reports.filter(r => r.status === 'pending' || r.status === 'failed' || r.status === 'syncing');
    const synced = reports.filter(r => r.status === 'synced');
    setHeroPendingCount(pending.length);
    
    const section = document.getElementById('pendingReportsSection');
    const list = document.getElementById('pendingReportsList');
    const countTitle = document.getElementById('pendingCountTitle');
    
    if (pending.length === 0 && synced.length === 0) {
        section.style.display = 'none';
        return;
    }
    
    section.style.display = 'block';
    countTitle.textContent = pending.length;
    
    let html = '';
    
    if (pending.length > 0) {
        html += '<h3 style="margin-top:0;">Pending Sync</h3>';
        pending.forEach(report => {
            html += createReportItem(report);
        });
    }
    
    // Show recently synced reports (within last 24 hours) for confirmation
    if (synced.length > 0) {
        const now = new Date();
        const oneDayAgo = new Date(now.getTime() - (24 * 60 * 60 * 1000));
        const recentSynced = synced.filter(r => {
            if (!r.synced_at) return false;
            return new Date(r.synced_at) > oneDayAgo;
        });
        
        if (recentSynced.length > 0) {
            html += '<h3 style="margin-top:20px;">Recently Synced (will be cleared automatically)</h3>';
            recentSynced.slice(0, 5).forEach(report => {
                html += createReportItem(report, true);
            });
            if (recentSynced.length > 5) {
                html += `<p style="color:var(--secondary); font-size:14px;">... and ${recentSynced.length - 5} more recently synced reports</p>`;
            }
        }
    }
    
    list.innerHTML = html;
}

function createReportItem(report, isSynced = false) {
    const date = new Date(report.report_date || report.saved_at).toLocaleDateString();
    const statusClass = isSynced ? 'synced' : (report.status === 'syncing' ? 'syncing' : '');
    const statusBadge = isSynced ? '<span style="color:#10b981; font-size:12px;">✓ Synced</span>' : 
                        (report.status === 'failed' ? '<span style="color:#ef4444; font-size:12px;">✗ Failed</span>' : 
                        '<span style="color:#f59e0b; font-size:12px;">⏳ Pending</span>');
    
    // Show sync time for synced reports
    let syncTimeLabel = '';
    if (isSynced && report.synced_at) {
        const syncDate = new Date(report.synced_at);
        const syncTime = syncDate.toLocaleTimeString();
        syncTimeLabel = `Synced ${syncTime}`;
    }
    
    return `
        <div class="report-item ${statusClass}">
            <div>
                <strong>${report.site_name || 'Unnamed Report'}</strong> <span style="font-weight:500; color:rgba(15,23,42,0.55);">• ${date}</span>
                <div class="report-meta">
                    <span>${report.client_name || 'No client'}</span>
                    <span>${report.rig_name || 'No rig'}</span>
                    <span>${statusBadge}</span>
                    ${syncTimeLabel ? `<span style="color:#10b981;">${syncTimeLabel}</span>` : ''}
                    ${report.error ? `<span style="color:#ef4444;">Error: ${report.error}</span>` : ''}
                    ${isSynced ? '<span style="font-style:italic;">Will auto-clear</span>' : ''}
                </div>
            </div>
            <div class="report-actions">
                <button class="btn btn-sm btn-outline" onclick="viewReport('${report.id}')">View</button>
                ${!isSynced ? `
                    <button class="btn btn-sm btn-primary" onclick="editReport('${report.id}')">Edit</button>
                    <button class="btn btn-sm btn-outline" onclick="deleteReport('${report.id}')">Delete</button>
                ` : `
                    <button class="btn btn-sm btn-outline" onclick="deleteReport('${report.id}')" title="Remove from local storage">Clear</button>
                `}
            </div>
        </div>
    `;
}

// Edit Report
function editReport(id) {
    const reports = getOfflineReports();
    const report = reports.find(r => r.id === id);
    
    if (!report) return;
    
    currentEditingId = id;

    const workersContainer = document.getElementById('workersList');
    const expensesContainer = document.getElementById('expensesList');
    if (workersContainer) {
        workersContainer.innerHTML = '';
    }
    if (expensesContainer) {
        expensesContainer.innerHTML = '';
    }
    workerRowCount = 0;
    expenseRowCount = 0;
    
    // Populate form
    const form = document.getElementById('offlineForm');
    for (const [key, value] of Object.entries(report)) {
        if (key === 'workers' && Array.isArray(value)) {
            value.forEach(worker => addWorkerRow(worker));
        } else if (key === 'expenses' && Array.isArray(value)) {
            value.forEach(expense => addExpenseRow(expense));
        } else if (key !== 'id' && key !== 'saved_at' && key !== 'status' && key !== 'sync_attempts') {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = value;
            }
        }
    }
    
    // Scroll to top
    window.scrollTo(0, 0);
    showNotification('Report loaded for editing', 'info');
    updateDurationDisplay();
    populateOfflineStoreOptions();
    toggleOfflineStoreGroup();
}

// Delete Report
function deleteReport(id) {
    if (!confirm('Are you sure you want to delete this report?')) {
        return;
    }
    
    const reports = getOfflineReports();
    const filtered = reports.filter(r => r.id !== id);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(filtered));
    
    loadPendingReports();
    updateSyncStatus();
    showNotification('Report deleted', 'success');
}

// Notification
function showNotification(message, type = 'info') {
    // Simple notification - can be enhanced with a proper notification system
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b'
    };
    
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 2000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function calculateOfflineFinancials(report, totalWages, expensesTotal) {
    const toNumber = (value) => {
        const num = parseFloat(value);
        return Number.isNaN(num) ? 0 : num;
    };
    
    const context = {
        job_type: report.job_type || report.jobType || '',
        balance_bf: toNumber(report.balance_bf),
        contract_sum: toNumber(report.contract_sum),
        rig_fee_charged: toNumber(report.rig_fee_charged),
        rig_fee_collected: toNumber(report.rig_fee_collected),
        cash_received: toNumber(report.cash_received),
        materials_income: toNumber(report.materials_income),
        materials_cost: toNumber(report.materials_cost),
        total_wages: roundToTwo(totalWages),
        daily_expenses: roundToTwo(expensesTotal),
        momo_transfer: toNumber(report.momo_transfer),
        cash_given: toNumber(report.cash_given),
        bank_deposit: toNumber(report.bank_deposit),
        loans_amount: toNumber(report.loans_amount)
    };
    
    if (typeof ABBISCalculations !== 'undefined' && typeof ABBISCalculations.calculateFinancialTotals === 'function') {
        try {
            const totals = ABBISCalculations.calculateFinancialTotals(context);
            return {
                totalIncome: roundToTwo(totals.totalIncome ?? 0),
                totalExpenses: roundToTwo(totals.totalExpenses ?? 0),
                totalWages: roundToTwo(totals.totalWages ?? context.total_wages),
                netProfit: roundToTwo(totals.netProfit ?? 0),
                totalMoneyBanked: roundToTwo(totals.totalMoneyBanked ?? (context.momo_transfer + context.cash_given + context.bank_deposit)),
                daysBalance: roundToTwo(totals.daysBalance ?? 0),
                outstandingRigFee: roundToTwo(totals.outstandingRigFee ?? Math.max(0, context.rig_fee_charged - context.rig_fee_collected)),
                loansOutstanding: roundToTwo(totals.loansOutstanding ?? context.loans_amount),
                totalDebt: roundToTwo(
                    (totals.totalDebt ??
                        ((totals.outstandingRigFee ?? Math.max(0, context.rig_fee_charged - context.rig_fee_collected)) +
                        (totals.loansOutstanding ?? context.loans_amount)))
                ),
                context
            };
        } catch (error) {
            console.warn('Financial calculation fallback triggered:', error);
        }
    }
    
    const contractIncome = context.job_type === 'direct' ? context.contract_sum - context.rig_fee_charged : 0;
    const totalIncome = context.balance_bf + contractIncome + context.rig_fee_collected + context.cash_received + context.materials_income;
    const totalExpenses = context.materials_cost + context.total_wages + context.loans_amount + context.daily_expenses;
    const totalMoneyBanked = context.momo_transfer + context.cash_given + context.bank_deposit;
    const newIncomeToday = totalIncome - context.balance_bf;
    const cashBeforeBanking = context.balance_bf + newIncomeToday - totalExpenses;
    const daysBalance = cashBeforeBanking - totalMoneyBanked;
    const outstandingRigFee = Math.max(0, context.rig_fee_charged - context.rig_fee_collected);
    const loansOutstanding = context.loans_amount;
    
    return {
        totalIncome: roundToTwo(totalIncome),
        totalExpenses: roundToTwo(totalExpenses),
        totalWages: roundToTwo(context.total_wages),
        netProfit: roundToTwo(totalIncome - totalExpenses),
        totalMoneyBanked: roundToTwo(totalMoneyBanked),
        daysBalance: roundToTwo(daysBalance),
        outstandingRigFee: roundToTwo(outstandingRigFee),
        loansOutstanding: roundToTwo(loansOutstanding),
        totalDebt: roundToTwo(outstandingRigFee + loansOutstanding),
        context
    };
}

function initializeSummaryModal() {
    const modal = document.getElementById('offlineSummaryModal');
    if (!modal) return;
    
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeOfflineSummaryModal();
        }
    });
    
    modal.querySelectorAll('[data-close-modal="offlineSummaryModal"]').forEach(btn => {
        btn.addEventListener('click', closeOfflineSummaryModal);
    });
}

function showOfflineSummaryModal(report, metrics = null) {
    const modal = document.getElementById('offlineSummaryModal');
    if (!modal || !report) return;
    
    const setField = (key, value) => {
        const el = modal.querySelector(`[data-field="${key}"]`);
        if (el) {
            el.textContent = value;
        }
    };

    const derived = metrics || deriveOfflineReportMetrics(report);
    const totals = derived.financialTotals || {
        totalIncome: report.total_income,
        totalExpenses: report.total_expenses,
        totalWages: report.total_wages,
        netProfit: report.net_profit,
        totalMoneyBanked: report.total_money_banked,
        daysBalance: report.days_balance
    };
    
    const reportDate = report.report_date ? new Date(`${report.report_date}T00:00:00`) : null;
    const dateLabel = reportDate && !Number.isNaN(reportDate.getTime())
        ? `📅 ${reportDate.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })}`
        : '📅 Date not set';
    setField('summary-date', dateLabel);
    
    const siteLabel = report.site_name ? `📍 ${report.site_name}` : '📍 Site not set';
    setField('summary-site', siteLabel);
    
    const rigLabel = report.rig_name || report.rig_code || '';
    setField('summary-rig', rigLabel ? `🚜 ${rigLabel}` : '🚜 Rig not set');
    
    setField('summary-total-depth', formatSummaryMeters(report.total_depth, 1));
    setField('summary-rods-used', formatSummaryCount(report.rods_used, 'rod'));
    setField('summary-rod-length', formatSummaryMeters(report.rod_length, 1));
    setField('summary-construction-depth', formatSummaryMeters(report.construction_depth, 1));
    
    const durationMinutes = parseInt(report.total_duration, 10) || 0;
    setField('summary-duration', formatSummaryDuration(durationMinutes));
    setField('summary-time-range', formatSummaryTimeRange(report.start_time, report.finish_time));
    setField('summary-rpm', formatSummaryRpm(report.start_rpm, report.finish_rpm, report.total_rpm));
    
    const workerCount = derived.totalWorkers ?? (Array.isArray(report.workers) ? report.workers.length : 0);
    setField('summary-workers', formatSummaryCount(workerCount, 'worker'));
    
    setField('summary-income', formatSummaryCurrency(totals.totalIncome));
    setField('summary-expenses', formatSummaryCurrency(totals.totalExpenses));
    setField('summary-wages', formatSummaryCurrency(totals.totalWages));
    setField('summary-profit', formatSummaryCurrency(totals.netProfit));
    setField('summary-balance', formatSummaryCurrency(totals.daysBalance));

    const expenseList = modal.querySelector('[data-field="summary-expense-list"]');
    if (expenseList) {
        expenseList.innerHTML = '';
        if (!derived.expenses || derived.expenses.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'offline-summary-empty';
            empty.textContent = 'No expenses captured.';
            expenseList.appendChild(empty);
        } else {
            derived.expenses.forEach(expense => {
                const li = document.createElement('li');
                const label = expense.description ? expense.description : 'General expense';
                const qty = toNumber(expense.quantity || 0);
                const unitCost = toNumber(expense.unit_cost || 0);
                const amount = toNumber(expense.amount || (qty * unitCost));
                const qtyLabel = qty && unitCost ? ` (${qty}×${unitCost.toFixed(2)})` : '';
                li.innerHTML = `<span class="offline-summary-key">${label}${qtyLabel}</span><span class="offline-summary-value">${formatSummaryCurrency(amount)}</span>`;
                expenseList.appendChild(li);
            });
        }
    }
    
    modal.classList.add('active');
    lockBodyScroll();
}

function closeOfflineSummaryModal() {
    const modal = document.getElementById('offlineSummaryModal');
    if (!modal) return;
    modal.classList.remove('active');
    const detailModal = document.getElementById('offlineDetailModal');
    if (!detailModal || !detailModal.classList.contains('active')) {
        restoreBodyScroll();
    }
}

function openOfflineDetailModal(report) {
    const modal = document.getElementById('offlineDetailModal');
    if (!modal || !report) return;

    const derived = deriveOfflineReportMetrics(report);
    const totals = derived.financialTotals || {};
    const setField = (field, value) => {
        const el = modal.querySelector(`[data-field="${field}"]`);
        if (el) {
            el.textContent = value;
        }
    };

    const reportDate = report.report_date ? new Date(`${report.report_date}T00:00:00`) : null;
    const dateLabel = reportDate && !Number.isNaN(reportDate.getTime())
        ? `📅 ${reportDate.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })}`
        : '📅 Date not set';

    const statusLabel = report.status ? report.status.charAt(0).toUpperCase() + report.status.slice(1) : 'Pending';
    const jobTypeLabel = report.job_type ? report.job_type.replace(/_/g, ' ') : 'Not set';

    const baseTitle = report.site_name || report.client_name || report.report_date || 'Offline Report';
    setField('detail-title', `Offline Report – ${baseTitle}`);
    setField('detail-date', dateLabel);
    setField('detail-site', report.site_name ? `📍 ${report.site_name}` : '📍 Site not set');
    setField('detail-rig', report.rig_name ? `🚜 ${report.rig_name}` : '🚜 Rig not set');
    setField('detail-status', `🔄 Status: ${statusLabel}`);
    setField('detail-job-type', `💼 Job Type: ${jobTypeLabel}`);

    setField('detail-total-depth', formatSummaryMeters(report.total_depth, 1));
    setField('detail-rods-used', formatSummaryCount(report.rods_used, 'rod'));
    setField('detail-rod-length', formatSummaryMeters(report.rod_length, 1));
    setField('detail-construction-depth', formatSummaryMeters(report.construction_depth, 1));
    setField('detail-duration', formatSummaryDuration(parseInt(report.total_duration, 10) || 0));
    setField('detail-time-range', formatSummaryTimeRange(report.start_time, report.finish_time));
    setField('detail-rpm', formatSummaryRpm(report.start_rpm, report.finish_rpm, report.total_rpm));

    setField('detail-income', formatSummaryCurrency(totals.totalIncome));
    setField('detail-expenses', formatSummaryCurrency(totals.totalExpenses));
    setField('detail-wages', formatSummaryCurrency(totals.totalWages));
    setField('detail-profit', formatSummaryCurrency(totals.netProfit));
    setField('detail-balance', formatSummaryCurrency(totals.daysBalance));
    setField('detail-banked', formatSummaryCurrency(totals.totalMoneyBanked));
    setField('detail-outstanding-rig', formatSummaryCurrency(totals.outstandingRigFee));
    setField('detail-loans', formatSummaryCurrency(totals.loansOutstanding));

    const workersBody = modal.querySelector('[data-field="detail-workers"]');
    if (workersBody) {
        workersBody.innerHTML = '';
        if (!derived.workers || derived.workers.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'offline-summary-empty';
            cell.textContent = 'No workers captured.';
            row.appendChild(cell);
            workersBody.appendChild(row);
        } else {
            derived.workers.forEach(worker => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${worker.worker_name || '—'}</td>
                    <td>${worker.role || '—'}</td>
                    <td>${worker.wage_type || '—'}</td>
                    <td>${worker.units || '0'}</td>
                    <td>${formatSummaryCurrency(worker.pay_per_unit || worker.payPerUnit || 0)}</td>
                    <td>${formatSummaryCurrency(worker.amount)}</td>
                `;
                workersBody.appendChild(row);
            });
        }
    }

    const expensesBody = modal.querySelector('[data-field="detail-expenses"]');
    if (expensesBody) {
        expensesBody.innerHTML = '';
        if (!derived.expenses || derived.expenses.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'offline-summary-empty';
            cell.textContent = 'No expenses captured.';
            row.appendChild(cell);
            expensesBody.appendChild(row);
        } else {
            derived.expenses.forEach(expense => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${expense.description || '—'}</td>
                    <td>${formatSummaryCurrency(expense.unit_cost || expense.unitCost || 0)}</td>
                    <td>${expense.quantity || '0'}</td>
                    <td>${formatSummaryCurrency(expense.amount)}</td>
                `;
                expensesBody.appendChild(row);
            });
        }
    }

    const formatNote = (value, fallback) => {
        if (!value || value.trim() === '') {
            return fallback;
        }
        return value.trim();
    };

    setField('detail-remarks', formatNote(report.remarks || '', 'No remarks recorded.'));
    setField('detail-incident', formatNote(report.incident_log || '', 'No incident log recorded.'));
    setField('detail-solution', formatNote(report.solution_log || '', 'No solution log recorded.'));
    setField('detail-recommendation', formatNote(report.recommendation_log || '', 'No recommendation recorded.'));

    modal.classList.add('active');
    lockBodyScroll();
}

function closeOfflineDetailModal() {
    const modal = document.getElementById('offlineDetailModal');
    if (!modal) return;
    modal.classList.remove('active');
    const summaryModal = document.getElementById('offlineSummaryModal');
    if (!summaryModal || !summaryModal.classList.contains('active')) {
        restoreBodyScroll();
    }
}

function viewReport(id) {
    const reports = getOfflineReports();
    const report = reports.find(r => r.id === id);
    if (!report) {
        showNotification('Report not found.', 'error');
        return;
    }
    openOfflineDetailModal(report);
}

function lockBodyScroll() {
    if (!document.body.dataset.summaryOverflow) {
        document.body.dataset.summaryOverflow = document.body.style.overflow || '';
    }
    document.body.style.overflow = 'hidden';
}

function restoreBodyScroll() {
    if (document.body.dataset.summaryOverflow !== undefined) {
        document.body.style.overflow = document.body.dataset.summaryOverflow;
        delete document.body.dataset.summaryOverflow;
    } else {
        document.body.style.overflow = '';
    }
}

function formatSummaryCurrency(value) {
    const amount = parseFloat(value);
    const safeAmount = Number.isNaN(amount) ? 0 : amount;
    if (typeof ABBISCalculations !== 'undefined' && typeof ABBISCalculations.formatCurrency === 'function') {
        return ABBISCalculations.formatCurrency(safeAmount);
    }
    return 'GHS ' + roundToTwo(safeAmount).toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatSummaryMeters(value, decimals = 1) {
    const num = parseFloat(value);
    if (Number.isNaN(num)) {
        return '0 m';
    }
    return `${num.toLocaleString('en-GH', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })} m`;
}

function formatSummaryCount(value, singularLabel) {
    const count = parseInt(value, 10);
    const safeCount = Number.isNaN(count) ? 0 : count;
    const label = singularLabel + (safeCount === 1 ? '' : 's');
    return `${safeCount} ${label}`;
}

function formatSummaryTimeRange(start, finish) {
    const startLabel = start ? start : '--:--';
    const finishLabel = finish ? finish : '--:--';
    return `${startLabel} - ${finishLabel}`;
}

function formatSummaryRpm(start, finish, total) {
    const startVal = parseFloat(start);
    const finishVal = parseFloat(finish);
    let totalVal = parseFloat(total);
    const safeStart = Number.isNaN(startVal) ? 0 : startVal;
    const safeFinish = Number.isNaN(finishVal) ? 0 : finishVal;
    if (Number.isNaN(totalVal)) {
        totalVal = safeFinish - safeStart;
    }
    const formattedStart = Math.round(safeStart);
    const formattedFinish = Math.round(safeFinish);
    const delta = roundToTwo(totalVal);
    return `${formattedStart} -> ${formattedFinish} (delta ${delta})`;
}

function formatSummaryDuration(minutes) {
    const mins = Number.isFinite(minutes) ? minutes : parseInt(minutes, 10) || 0;
    if (typeof ABBISCalculations !== 'undefined' && typeof ABBISCalculations.formatDuration === 'function') {
        return ABBISCalculations.formatDuration(mins);
    }
    const hours = Math.floor(mins / 60);
    const remaining = mins % 60;
    const remainingPadded = Math.abs(remaining).toString().padStart(2, '0');
    return `${hours}h ${remainingPadded}m`;
}

// Excel Export Function
function exportToExcel(silent = false) {
    try {
        const reports = getOfflineReports();
        
        if (reports.length === 0) {
            if (!silent) {
                showNotification('No reports to export', 'warning');
            }
            return;
        }
        
        // Create workbook
        const wb = XLSX.utils.book_new();
        
        // Main reports sheet
        const reportsData = reports.map(report => ({
            'Report ID': report.id || '',
            'Report Date': report.report_date || '',
            'Site Name': report.site_name || '',
            'Client Name': report.client_name || '',
            'Rig Name': report.rig_name || '',
            'Rig Code': report.rig_code || report.rig_id || '',
            'Job Type': report.job_type || '',
            'Region': report.region || '',
            'Supervisor': report.supervisor || '',
            'Total Workers': report.total_workers || 0,
            'Start Time': report.start_time || '',
            'Finish Time': report.finish_time || '',
            'Total Duration (min)': report.total_duration || 0,
            'Start RPM': report.start_rpm || '',
            'Finish RPM': report.finish_rpm || '',
            'Total RPM': report.total_rpm || '',
            'Rod Length': report.rod_length || '',
            'Rods Used': report.rods_used || 0,
            'Total Depth': report.total_depth || '',
            'Screen Pipes Used': report.screen_pipes_used || 0,
            'Plain Pipes Used': report.plain_pipes_used || 0,
            'Gravel Used': report.gravel_used || 0,
            'Construction Depth': report.construction_depth || '',
            'Balance B/F': report.balance_bf || 0,
            'Contract Sum': report.contract_sum || 0,
            'Rig Fee Charged': report.rig_fee_charged || 0,
            'Rig Fee Collected': report.rig_fee_collected || 0,
            'Cash Received': report.cash_received || 0,
            'Materials Income': report.materials_income || 0,
            'Materials Cost': report.materials_cost || 0,
            'MoMo Transfer': report.momo_transfer || 0,
            'Cash Given': report.cash_given || 0,
            'Bank Deposit': report.bank_deposit || 0,
            'Total Income': report.total_income || 0,
            'Total Expenses': report.total_expenses || 0,
            'Total Wages': report.total_wages || 0,
            'Net Profit': report.net_profit || 0,
            'Status': report.status || 'pending',
            'Saved At': report.saved_at || '',
            'Synced At': report.synced_at || '',
            'Remarks': report.remarks || '',
            'Incident Log': report.incident_log || '',
            'Solution Log': report.solution_log || '',
            'Recommendation Log': report.recommendation_log || ''
        }));
        
        const wsReports = XLSX.utils.json_to_sheet(reportsData);
        XLSX.utils.book_append_sheet(wb, wsReports, 'Field Reports');
        
        // Workers sheet (flattened)
        const workersData = [];
        reports.forEach(report => {
            if (report.workers && Array.isArray(report.workers)) {
                report.workers.forEach(worker => {
                    workersData.push({
                        'Report ID': report.id || '',
                        'Report Date': report.report_date || '',
                        'Site Name': report.site_name || '',
                        'Worker Name': worker.worker_name || '',
                        'Role': worker.role || '',
                        'Wage Type': worker.wage_type || '',
                        'Units': worker.units || 0,
                        'Pay Per Unit': worker.pay_per_unit || 0,
                        'Benefits': worker.benefits || 0,
                        'Loan Reclaim': worker.loan_reclaim || 0,
                        'Amount': worker.amount || 0,
                        'Paid Today': worker.paid_today || '0',
                        'Notes': worker.notes || ''
                    });
                });
            }
        });
        
        if (workersData.length > 0) {
            const wsWorkers = XLSX.utils.json_to_sheet(workersData);
            XLSX.utils.book_append_sheet(wb, wsWorkers, 'Workers');
        }
        
        // Expenses sheet (flattened)
        const expensesData = [];
        reports.forEach(report => {
            if (report.expenses && Array.isArray(report.expenses)) {
                report.expenses.forEach(expense => {
                    expensesData.push({
                        'Report ID': report.id || '',
                        'Report Date': report.report_date || '',
                        'Site Name': report.site_name || '',
                        'Description': expense.description || '',
                        'Unit Cost': expense.unit_cost || 0,
                        'Quantity': expense.quantity || 0,
                        'Amount': expense.amount || 0,
                        'Category': expense.category || ''
                    });
                });
            }
        });
        
        if (expensesData.length > 0) {
            const wsExpenses = XLSX.utils.json_to_sheet(expensesData);
            XLSX.utils.book_append_sheet(wb, wsExpenses, 'Expenses');
        }
        
        // Metadata sheet
        const metadata = [{
            'Export Date': new Date().toISOString(),
            'Total Reports': reports.length,
            'Pending Reports': reports.filter(r => r.status === 'pending').length,
            'Synced Reports': reports.filter(r => r.status === 'synced').length,
            'Failed Reports': reports.filter(r => r.status === 'failed').length,
            'ABBIS Version': '3.2',
            'Export Type': 'Offline Backup'
        }];
        const wsMetadata = XLSX.utils.json_to_sheet(metadata);
        XLSX.utils.book_append_sheet(wb, wsMetadata, 'Metadata');
        
        // Generate filename with timestamp
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').split('T')[0];
        const filename = `ABBIS_Offline_Backup_${timestamp}.xlsx`;
        
        // Write file
        XLSX.writeFile(wb, filename);
        
        // Save export info to localStorage
        const exportTimestamp = new Date().toISOString();
        localStorage.setItem('abbis_last_excel_export', exportTimestamp);
        localStorage.setItem('abbis_last_excel_backup', exportTimestamp);
        updateHeroBackupLabel();
        
        if (!silent) {
            showNotification(`✅ Exported ${reports.length} report(s) to Excel: ${filename}`, 'success');
        }
    } catch (error) {
        console.error('Excel export error:', error);
        if (!silent) {
            showNotification('❌ Excel export failed: ' + error.message, 'error');
        }
        throw error;
    }
}

// Excel Import Function
function importFromExcel(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // Read Field Reports sheet
            const reportsSheet = workbook.Sheets['Field Reports'];
            if (!reportsSheet) {
                showNotification('❌ Invalid Excel file: "Field Reports" sheet not found', 'error');
                return;
            }
            
            const reportsData = XLSX.utils.sheet_to_json(reportsSheet);
            
            if (reportsData.length === 0) {
                showNotification('❌ No reports found in Excel file', 'error');
                return;
            }
            
            // Read Workers sheet if exists
            const workersSheet = workbook.Sheets['Workers'];
            const workersData = workersSheet ? XLSX.utils.sheet_to_json(workersSheet) : [];
            
            // Read Expenses sheet if exists
            const expensesSheet = workbook.Sheets['Expenses'];
            const expensesData = expensesSheet ? XLSX.utils.sheet_to_json(expensesSheet) : [];
            
            // Group workers and expenses by Report ID
            const workersByReport = {};
            workersData.forEach(worker => {
                const reportId = worker['Report ID'];
                if (!workersByReport[reportId]) {
                    workersByReport[reportId] = [];
                }
                workersByReport[reportId].push({
                    worker_name: worker['Worker Name'] || '',
                    role: worker['Role'] || '',
                    wage_type: worker['Wage Type'] || '',
                    units: worker['Units'] || '0',
                    pay_per_unit: worker['Pay Per Unit'] || '0',
                    benefits: worker['Benefits'] || '0',
                    loan_reclaim: worker['Loan Reclaim'] || '0',
                    amount: worker['Amount'] || '0',
                    paid_today: worker['Paid Today'] || '0',
                    notes: worker['Notes'] || ''
                });
            });
            
            const expensesByReport = {};
            expensesData.forEach(expense => {
                const reportId = expense['Report ID'];
                if (!expensesByReport[reportId]) {
                    expensesByReport[reportId] = [];
                }
                expensesByReport[reportId].push({
                    description: expense['Description'] || '',
                    unit_cost: expense['Unit Cost'] || '0',
                    quantity: expense['Quantity'] || '0',
                    amount: expense['Amount'] || '0',
                    category: expense['Category'] || ''
                });
            });
            
            // Convert Excel data to report format
            const importedReports = [];
            const existingReports = getOfflineReports();
            const existingIds = new Set(existingReports.map(r => r.id));
            
            reportsData.forEach(row => {
                // Generate new ID if importing duplicate or missing ID
                let reportId = row['Report ID'];
                if (!reportId || existingIds.has(reportId)) {
                    reportId = generateId();
                }
                existingIds.add(reportId);
                
                const report = {
                    id: reportId,
                    report_date: row['Report Date'] || new Date().toISOString().split('T')[0],
                    site_name: row['Site Name'] || '',
                    client_name: row['Client Name'] || '',
                    rig_name: row['Rig Name'] || '',
                    rig_code: row['Rig Code'] || '',
                    rig_id: row['Rig Code'] || '',
                    job_type: row['Job Type'] || 'direct',
                    region: row['Region'] || '',
                    supervisor: row['Supervisor'] || '',
                    total_workers: parseInt(row['Total Workers'] || 0),
                    start_time: row['Start Time'] || '',
                    finish_time: row['Finish Time'] || '',
                    total_duration: parseInt(row['Total Duration (min)'] || 0),
                    start_rpm: row['Start RPM'] || '',
                    finish_rpm: row['Finish RPM'] || '',
                    total_rpm: row['Total RPM'] || '',
                    rod_length: row['Rod Length'] || '',
                    rods_used: parseInt(row['Rods Used'] || 0),
                    total_depth: row['Total Depth'] || '',
                    screen_pipes_used: parseInt(row['Screen Pipes Used'] || 0),
                    plain_pipes_used: parseInt(row['Plain Pipes Used'] || 0),
                    gravel_used: parseInt(row['Gravel Used'] || 0),
                    construction_depth: row['Construction Depth'] || '',
                    balance_bf: parseFloat(row['Balance B/F'] || 0),
                    contract_sum: parseFloat(row['Contract Sum'] || 0),
                    rig_fee_charged: parseFloat(row['Rig Fee Charged'] || 0),
                    rig_fee_collected: parseFloat(row['Rig Fee Collected'] || 0),
                    cash_received: parseFloat(row['Cash Received'] || 0),
                    materials_income: parseFloat(row['Materials Income'] || 0),
                    materials_cost: parseFloat(row['Materials Cost'] || 0),
                    momo_transfer: parseFloat(row['MoMo Transfer'] || 0),
                    cash_given: parseFloat(row['Cash Given'] || 0),
                    bank_deposit: parseFloat(row['Bank Deposit'] || 0),
                    total_income: parseFloat(row['Total Income'] || 0),
                    total_expenses: parseFloat(row['Total Expenses'] || 0),
                    total_wages: parseFloat(row['Total Wages'] || 0),
                    net_profit: parseFloat(row['Net Profit'] || 0),
                    remarks: row['Remarks'] || '',
                    incident_log: row['Incident Log'] || '',
                    solution_log: row['Solution Log'] || '',
                    recommendation_log: row['Recommendation Log'] || '',
                    workers: workersByReport[reportId] || [],
                    expenses: expensesByReport[reportId] || [],
                    saved_at: row['Saved At'] || new Date().toISOString(),
                    status: 'pending', // Always set as pending for imported reports
                    sync_attempts: 0,
                    imported_from_excel: true,
                    imported_at: new Date().toISOString()
                };
                
                importedReports.push(report);
            });
            
            // Merge with existing reports (avoid duplicates)
            const allReports = [...existingReports];
            importedReports.forEach(imported => {
                // Check if report with same date and site already exists
                const duplicate = allReports.find(r => 
                    r.report_date === imported.report_date && 
                    r.site_name === imported.site_name &&
                    r.status !== 'synced' // Don't overwrite synced reports
                );
                
                if (!duplicate) {
                    allReports.push(imported);
                }
            });
            
            // Save to localStorage
            localStorage.setItem(STORAGE_KEY, JSON.stringify(allReports));
            
            // Update UI
            loadPendingReports();
            updateSyncStatus();
            
            const newCount = importedReports.length;
            const duplicateCount = reportsData.length - newCount;
            
            let message = `✅ Imported ${newCount} report(s) from Excel`;
            if (duplicateCount > 0) {
                message += ` (${duplicateCount} duplicate(s) skipped)`;
            }
            showNotification(message, 'success');
            
            // Clear file input
            event.target.value = '';
        } catch (error) {
            console.error('Excel import error:', error);
            showNotification('❌ Excel import failed: ' + error.message, 'error');
            event.target.value = '';
        }
    };
    
    reader.readAsArrayBuffer(file);
}

// Import Excel to Server (when online)
function importExcelToServer(event) {
    if (!event) {
        // Button clicked - trigger file input
        document.getElementById('excelServerImportInput').click();
        return;
    }
    
    const file = event.target.files[0];
    if (!file) return;
    
    if (!navigator.onLine) {
        showNotification('❌ You must be online to import to server. Use "Import from Excel Backup" for offline import.', 'error');
        event.target.value = '';
        return;
    }
    
    const formData = new FormData();
    formData.append('excel_file', file);
    
    // Show loading
    const btn = document.getElementById('importToServerBtn');
    const originalText = btn ? btn.textContent : '';
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Importing...';
    }
    
    fetch('../api/import-excel-backup.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let message = `✅ ${data.message}`;
            if (data.errors && data.errors.length > 0) {
                message += `\n\n⚠️ ${data.errors.length} error(s) occurred.`;
            }
            showNotification(message, 'success');
            
            // Refresh pending reports
            loadPendingReports();
            updateSyncStatus();
        } else {
            if (data.use_client_import) {
                showNotification('💡 Please use "Import from Excel Backup" button for client-side import.', 'info');
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        }
        
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        event.target.value = '';
    })
    .catch(error => {
        showNotification('❌ Import failed: ' + error.message, 'error');
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        event.target.value = '';
    });
}

// Update import button visibility based on online status
function updateImportButtonVisibility() {
    const btn = document.getElementById('importToServerBtn');
    if (btn) {
        if (navigator.onLine) {
            btn.style.display = 'inline-block';
        } else {
            btn.style.display = 'none';
        }
    }
}

// Make functions globally available
window.addWorkerRow = addWorkerRow;
window.addExpenseRow = addExpenseRow;
window.editReport = editReport;
window.deleteReport = deleteReport;
window.resolveConflict = resolveConflict;
window.exportToExcel = exportToExcel;
window.importFromExcel = importFromExcel;
window.importExcelToServer = importExcelToServer;
window.openWorkerModal = openWorkerModal;
window.closeWorkerModal = closeWorkerModal;
window.openRigModal = openRigModal;
window.closeRigModal = closeRigModal;
window.toggleInfoModal = toggleInfoModal;
window.openRigDirectoryModal = openRigDirectoryModal;
window.closeRigDirectoryModal = closeRigDirectoryModal;
window.openWorkerDirectoryModal = openWorkerDirectoryModal;
window.closeWorkerDirectoryModal = closeWorkerDirectoryModal;
window.closeOfflineSummaryModal = closeOfflineSummaryModal;
window.viewReport = viewReport;
window.openOfflineDetailModal = openOfflineDetailModal;
window.closeOfflineDetailModal = closeOfflineDetailModal;

