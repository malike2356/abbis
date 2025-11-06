/**
 * Configuration Management JavaScript
 */

function switchConfigTab(tabName) {
    // Hide all tab panes
    document.querySelectorAll('.config-tabs .tab-pane').forEach(pane => {
        pane.classList.remove('active');
        pane.style.display = 'none';
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.config-tabs .tabs .tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab pane
    const activePane = document.getElementById(tabName + '-tab');
    if (activePane) {
        activePane.classList.add('active');
        activePane.style.display = 'block';
    }
    
    // Add active class to clicked tab
    const activeTab = document.querySelector(`[onclick="switchConfigTab('${tabName}')"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Scroll active tab into view on mobile
    if (activeTab && window.innerWidth < 768) {
        activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
}

// Initialize tabs on page load
document.addEventListener('DOMContentLoaded', function() {
    // Hide all tab panes except the active one
    document.querySelectorAll('.config-tabs .tab-pane').forEach(pane => {
        if (!pane.classList.contains('active')) {
            pane.style.display = 'none';
        }
    });
});

// Rig Management
function showRigModal() {
    document.getElementById('rigModal').style.display = 'flex';
    document.getElementById('rigForm').reset();
    document.getElementById('rigAction').value = 'add_rig';
    document.getElementById('rigId').value = '';
    document.getElementById('rigModalTitle').textContent = 'Add New Rig';
}

function editRig(rig) {
    document.getElementById('rigModal').style.display = 'flex';
    document.getElementById('rigAction').value = 'update_rig';
    document.getElementById('rigId').value = rig.id;
    document.getElementById('rig_name').value = rig.rig_name;
    document.getElementById('rig_code').value = rig.rig_code;
    document.getElementById('truck_model').value = rig.truck_model || '';
    document.getElementById('registration_number').value = rig.registration_number || '';
    document.getElementById('rig_status').value = rig.status;
    
    // RPM fields
    document.getElementById('current_rpm').value = rig.current_rpm || 0.00;
    document.getElementById('maintenance_rpm_interval').value = rig.maintenance_rpm_interval || 30.00;
    document.getElementById('maintenance_due_at_rpm').value = rig.maintenance_due_at_rpm || '';
    
    // Update RPM status display
    updateRpmStatusDisplay();
    
    document.getElementById('rigModalTitle').textContent = 'Edit Rig';
}

// Update RPM status display
function updateRpmStatusDisplay() {
    const currentRpm = parseFloat(document.getElementById('current_rpm').value) || 0;
    const interval = parseFloat(document.getElementById('maintenance_rpm_interval').value) || 30;
    const dueAtRpm = document.getElementById('maintenance_due_at_rpm').value ? 
        parseFloat(document.getElementById('maintenance_due_at_rpm').value) : null;
    
    const statusDisplay = document.getElementById('rpm_status_display');
    const statusText = document.getElementById('rpm_status_text');
    const calcHint = document.getElementById('rpm_calculation_hint');
    
    if (currentRpm > 0 || interval > 0) {
        statusDisplay.style.display = 'block';
        
        const calculatedDueAt = dueAtRpm || (currentRpm + interval);
        const rpmRemaining = calculatedDueAt - currentRpm;
        const percentage = calculatedDueAt > 0 ? (currentRpm / calculatedDueAt * 100) : 0;
        
        let statusHtml = `
            <div style="margin-bottom: 8px;">
                <strong>Current:</strong> ${currentRpm.toFixed(2)} RPM
            </div>
            <div style="margin-bottom: 8px;">
                <strong>Due At:</strong> ${calculatedDueAt.toFixed(2)} RPM
            </div>
            <div style="margin-bottom: 8px;">
                <strong>Remaining:</strong> <span style="font-weight: 600; color: ${rpmRemaining <= 0 ? '#dc3545' : (rpmRemaining <= interval * 0.1 ? '#ffc107' : '#28a745')};">${rpmRemaining.toFixed(2)} RPM</span>
            </div>
            <div style="margin-bottom: 8px;">
                <div style="background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                    <div style="background: ${percentage >= 100 ? '#dc3545' : (percentage >= 90 ? '#ffc107' : '#28a745')}; height: 100%; width: ${Math.min(100, percentage)}%; transition: width 0.3s;"></div>
                </div>
                <small style="color: var(--secondary);">${percentage.toFixed(1)}% to maintenance</small>
            </div>
        `;
        
        if (rpmRemaining <= 0) {
            statusHtml += '<div style="color: #dc3545; font-weight: 600;">⚠️ Maintenance Due!</div>';
        } else if (rpmRemaining <= interval * 0.1) {
            statusHtml += '<div style="color: #ffc107; font-weight: 600;">⏰ Maintenance Soon</div>';
        }
        
        statusText.innerHTML = statusHtml;
        
        // Show calculation hint if due_at is empty
        if (!dueAtRpm) {
            calcHint.style.display = 'block';
            calcHint.textContent = `Will be calculated as: ${currentRpm.toFixed(2)} + ${interval.toFixed(2)} = ${calculatedDueAt.toFixed(2)} RPM`;
        } else {
            calcHint.style.display = 'none';
        }
    } else {
        statusDisplay.style.display = 'none';
    }
}

// Add event listeners for RPM fields
document.addEventListener('DOMContentLoaded', function() {
    const rpmFields = ['current_rpm', 'maintenance_rpm_interval', 'maintenance_due_at_rpm'];
    rpmFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', updateRpmStatusDisplay);
        }
    });
});

function deleteRig(id) {
    if (confirm('Are you sure you want to delete this rig?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.setAttribute('action', '../api/config-crud.php');
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken && csrfToken.value) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_rig';
        form.appendChild(actionInput);
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeRigModal() {
    document.getElementById('rigModal').style.display = 'none';
}

// Worker Management
function showWorkerModal() {
    document.getElementById('workerModal').style.display = 'flex';
    document.getElementById('workerForm').reset();
    document.getElementById('workerAction').value = 'add_worker';
    document.getElementById('workerId').value = '';
    document.getElementById('workerModalTitle').textContent = 'Add New Worker';
    // Reset custom role container
    const customRoleContainer = document.getElementById('customRoleContainer');
    if (customRoleContainer) {
        customRoleContainer.style.display = 'none';
        const customRoleInput = document.getElementById('custom_role_input');
        if (customRoleInput) {
            customRoleInput.value = '';
            customRoleInput.required = false;
        }
    }
    // Reset role select
    const workerRoleSelect = document.getElementById('worker_role');
    if (workerRoleSelect) {
        workerRoleSelect.value = '';
    }
}

function editWorker(worker) {
    // Close duplicate analysis modal if open
    const duplicateModal = document.getElementById('duplicateAnalysisModal');
    if (duplicateModal) {
        duplicateModal.style.display = 'none';
    }
    
    document.getElementById('workerModal').style.display = 'flex';
    document.getElementById('workerAction').value = 'update_worker';
    document.getElementById('workerId').value = worker.id;
    document.getElementById('worker_name').value = worker.worker_name;
    document.getElementById('worker_role').value = worker.role;
    document.getElementById('default_rate').value = worker.default_rate;
    document.getElementById('contact_number').value = worker.contact_number || '';
    document.getElementById('worker_email').value = worker.email || '';
    document.getElementById('worker_status').value = worker.status;
    document.getElementById('workerModalTitle').textContent = 'Edit Worker';
    // Hide custom role container when editing
    const customRoleContainer = document.getElementById('customRoleContainer');
    if (customRoleContainer) {
        customRoleContainer.style.display = 'none';
        const customRoleInput = document.getElementById('custom_role_input');
        if (customRoleInput) {
            customRoleInput.value = '';
            customRoleInput.required = false;
        }
    }
}

function deleteWorker(id) {
    if (confirm('Are you sure you want to delete this worker?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.setAttribute('action', '../api/config-crud.php');
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken && csrfToken.value) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_worker';
        form.appendChild(actionInput);
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeWorkerModal() {
    document.getElementById('workerModal').style.display = 'none';
}

// Export Workers Function
function exportWorkers() {
    // Use the existing export API
    window.location.href = '../api/export.php?module=workers&format=csv';
}

// Analyze Worker Duplicates Function
async function analyzeWorkerDuplicates() {
    const modal = document.getElementById('duplicateAnalysisModal');
    const content = document.getElementById('duplicateAnalysisContent');
    
    // Show modal with loading state
    modal.style.display = 'flex';
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>Analyzing workers for duplicates...</p>
        </div>
    `;
    
    try {
        const response = await fetch('../api/analyze-worker-duplicates.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Failed to analyze duplicates');
        }
        
        // Display results
        displayDuplicateResults(result);
        
    } catch (error) {
        console.error('Error analyzing duplicates:', error);
        content.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <h3>❌ Error</h3>
                <p>${error.message || 'Failed to analyze duplicates. Please try again.'}</p>
                <button type="button" class="btn btn-outline" onclick="closeDuplicateAnalysisModal()" style="margin-top: 20px;">Close</button>
            </div>
        `;
    }
}

// Display Duplicate Analysis Results
function displayDuplicateResults(result) {
    const content = document.getElementById('duplicateAnalysisContent');
    const stats = result.stats;
    const duplicates = result.duplicates;
    
    // Calculate total duplicates that can be merged
    const totalDuplicateGroups = stats.exact_name_duplicates + stats.contact_duplicates + stats.email_duplicates + stats.potential_duplicates;
    const hasDuplicates = totalDuplicateGroups > 0;
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h3 style="margin-top: 0;">📊 Analysis Summary</h3>
            ${hasDuplicates ? `
            <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong style="color: #856404; font-size: 16px;">⚠️ ${totalDuplicateGroups} duplicate group(s) found!</strong>
                        <p style="margin: 5px 0 0 0; color: #856404; font-size: 13px;">All duplicates can be automatically merged to clean up your worker database.</p>
                    </div>
                    <button type="button" class="btn btn-warning" onclick="bulkMergeAllDuplicates()" style="font-weight: 600; padding: 10px 20px;">
                        🔄 Merge ALL Duplicates
                    </button>
                </div>
            </div>
            ` : ''}
            <div class="duplicate-stats">
                <div class="stat-card">
                    <div class="stat-value">${stats.total_workers}</div>
                    <div class="stat-label">Total Workers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.exact_name_duplicates}</div>
                    <div class="stat-label">Exact Name Duplicates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.contact_duplicates}</div>
                    <div class="stat-label">Contact Duplicates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.email_duplicates}</div>
                    <div class="stat-label">Email Duplicates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.potential_duplicates}</div>
                    <div class="stat-label">Potential Duplicates</div>
                </div>
            </div>
        </div>
    `;
    
    // Exact Name Duplicates
    if (duplicates.by_name.length > 0) {
        html += `
            <div class="duplicate-section">
                <h3>🔴 Exact Name Duplicates (${duplicates.by_name.length})</h3>
                <p style="color: #6c757d; margin-bottom: 15px;">Workers with identical names after normalization</p>
        `;
        
        duplicates.by_name.forEach((group, index) => {
            const groupIds = group.workers.map(w => w.id);
            const groupIdsJson = JSON.stringify(groupIds).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
            
            html += `
                <div class="duplicate-group">
                    <h4>Group ${index + 1}: "${group.normalized_name}" (${group.count} entries)</h4>
            `;
            
            group.workers.forEach((worker, workerIndex) => {
                const workerJson = JSON.stringify(worker).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
                const isFirst = workerIndex === 0;
                html += `
                    <div class="duplicate-worker-item" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <strong>ID ${worker.id}:</strong> ${escapeHtml(worker.worker_name)}<br>
                            <small>Role: ${escapeHtml(worker.role)} | Contact: ${escapeHtml(worker.contact_number || 'N/A')} | Status: ${escapeHtml(worker.status)}</small>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            ${isFirst ? '<label style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #28a745;"><input type="radio" name="keep_worker_group_' + index + '" value="' + worker.id + '" checked> Keep</label>' : '<label style="display: flex; align-items: center; gap: 5px; font-size: 11px;"><input type="radio" name="keep_worker_group_' + index + '" value="' + worker.id + '"> Keep</label>'}
                            <button type="button" class="btn btn-sm btn-primary" onclick="editWorkerFromString('${workerJson}')" style="font-size: 11px;">Edit</button>
                        </div>
                    </div>
                `;
            });
            
            html += `
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-sm btn-success" onclick="mergeDuplicateGroup('${groupIdsJson}', '${group.normalized_name}')" style="font-size: 11px;">
                        🔄 Merge All in This Group
                    </button>
                </div>
            `;
            
            html += `</div>`;
        });
        
        html += `</div>`;
    }
    
    // Contact Number Duplicates
    if (duplicates.by_contact.length > 0) {
        html += `
            <div class="duplicate-section">
                <h3>📞 Contact Number Duplicates (${duplicates.by_contact.length})</h3>
                <p style="color: #6c757d; margin-bottom: 15px;">Workers sharing the same contact number</p>
        `;
        
        duplicates.by_contact.forEach((group, index) => {
            const groupIds = group.workers.map(w => w.id);
            const groupIdsJson = JSON.stringify(groupIds).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
            
            html += `
                <div class="duplicate-group">
                    <h4>Group ${index + 1}: Contact "${group.contact}" (${group.count} entries)</h4>
            `;
            
            group.workers.forEach((worker, workerIndex) => {
                const workerJson = JSON.stringify(worker).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
                const isFirst = workerIndex === 0;
                html += `
                    <div class="duplicate-worker-item" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <strong>ID ${worker.id}:</strong> ${escapeHtml(worker.worker_name)}<br>
                            <small>Role: ${escapeHtml(worker.role)} | Contact: ${escapeHtml(worker.contact_number || 'N/A')} | Status: ${escapeHtml(worker.status)}</small>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            ${isFirst ? '<label style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #28a745;"><input type="radio" name="keep_worker_contact_' + index + '" value="' + worker.id + '" checked> Keep</label>' : '<label style="display: flex; align-items: center; gap: 5px; font-size: 11px;"><input type="radio" name="keep_worker_contact_' + index + '" value="' + worker.id + '"> Keep</label>'}
                            <button type="button" class="btn btn-sm btn-primary" onclick="editWorkerFromString('${workerJson}')" style="font-size: 11px;">Edit</button>
                        </div>
                    </div>
                `;
            });
            
            html += `
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-sm btn-success" onclick="mergeDuplicateGroup('${groupIdsJson}', 'Contact ${group.contact}')" style="font-size: 11px;">
                        🔄 Merge All in This Group
                    </button>
                </div>
            `;
            
            html += `</div>`;
        });
        
        html += `</div>`;
    }
    
    // Email Duplicates
    if (duplicates.by_email && duplicates.by_email.length > 0) {
        html += `
            <div class="duplicate-section">
                <h3>📧 Email Duplicates (${duplicates.by_email.length})</h3>
                <p style="color: #6c757d; margin-bottom: 15px;">Workers sharing the same email address</p>
        `;
        
        duplicates.by_email.forEach((group, index) => {
            const groupIds = group.workers.map(w => w.id);
            const groupIdsJson = JSON.stringify(groupIds).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
            
            html += `
                <div class="duplicate-group">
                    <h4>Group ${index + 1}: Email "${group.email}" (${group.count} entries)</h4>
            `;
            
            group.workers.forEach((worker, workerIndex) => {
                const workerJson = JSON.stringify(worker).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
                const isFirst = workerIndex === 0;
                html += `
                    <div class="duplicate-worker-item" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <strong>ID ${worker.id}:</strong> ${escapeHtml(worker.worker_name)}<br>
                            <small>Role: ${escapeHtml(worker.role)} | Email: ${escapeHtml(worker.email || 'N/A')} | Status: ${escapeHtml(worker.status)}</small>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            ${isFirst ? '<label style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #28a745;"><input type="radio" name="keep_worker_email_' + index + '" value="' + worker.id + '" checked> Keep</label>' : '<label style="display: flex; align-items: center; gap: 5px; font-size: 11px;"><input type="radio" name="keep_worker_email_' + index + '" value="' + worker.id + '"> Keep</label>'}
                            <button type="button" class="btn btn-sm btn-primary" onclick="editWorkerFromString('${workerJson}')" style="font-size: 11px;">Edit</button>
                        </div>
                    </div>
                `;
            });
            
            html += `
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-sm btn-success" onclick="mergeDuplicateGroup('${groupIdsJson}', 'Email ${group.email}')" style="font-size: 11px;">
                        🔄 Merge All in This Group
                    </button>
                </div>
            `;
            
            html += `</div>`;
        });
        
        html += `</div>`;
    }
    
    // Potential Duplicates (Similar Names)
    if (duplicates.potential_duplicates.length > 0) {
        html += `
            <div class="duplicate-section" style="border-left-color: #ffc107;">
                <h3 style="color: #ffc107;">⚠️ Potential Duplicates (${duplicates.potential_duplicates.length})</h3>
                <p style="color: #6c757d; margin-bottom: 15px;">Workers with similar names (70%+ similarity) - Review manually</p>
        `;
        
        duplicates.potential_duplicates.forEach((pair, index) => {
            const pairIds = [pair.worker1.id, pair.worker2.id];
            const pairIdsJson = JSON.stringify(pairIds).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
            
            html += `
                <div class="duplicate-group">
                    <h4>Pair ${index + 1}: ${pair.similarity}% Similarity</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="duplicate-worker-item">
                            <strong>Worker 1 (ID ${pair.worker1.id}):</strong><br>
                            ${escapeHtml(pair.worker1.worker_name)}<br>
                            <small>Role: ${escapeHtml(pair.worker1.role)} | Contact: ${escapeHtml(pair.worker1.contact_number || 'N/A')}</small>
                            <div style="margin-top: 5px;">
                                <label style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #28a745;">
                                    <input type="radio" name="keep_worker_pair_${index}" value="${pair.worker1.id}" checked> Keep
                                </label>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editWorkerFromString('${JSON.stringify(pair.worker1).replace(/'/g, "&#39;").replace(/"/g, '&quot;')}')" style="font-size: 11px; margin-top: 5px;">Edit</button>
                            </div>
                        </div>
                        <div class="duplicate-worker-item">
                            <strong>Worker 2 (ID ${pair.worker2.id}):</strong><br>
                            ${escapeHtml(pair.worker2.worker_name)}<br>
                            <small>Role: ${escapeHtml(pair.worker2.role)} | Contact: ${escapeHtml(pair.worker2.contact_number || 'N/A')}</small>
                            <div style="margin-top: 5px;">
                                <label style="display: flex; align-items: center; gap: 5px; font-size: 11px;">
                                    <input type="radio" name="keep_worker_pair_${index}" value="${pair.worker2.id}"> Keep
                                </label>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editWorkerFromString('${JSON.stringify(pair.worker2).replace(/'/g, "&#39;").replace(/"/g, '&quot;')}')" style="font-size: 11px; margin-top: 5px;">Edit</button>
                            </div>
                        </div>
                    </div>
                    ${pair.shared_contact ? '<div style="margin-top: 8px; color: #28a745; font-size: 12px;">✓ Same contact number</div>' : ''}
                    ${pair.shared_email ? '<div style="margin-top: 8px; color: #28a745; font-size: 12px;">✓ Same email address</div>' : ''}
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-sm btn-success" onclick="mergeDuplicatePair('${pairIdsJson}', ${index})" style="font-size: 11px;">
                            🔄 Merge This Pair
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += `</div>`;
    }
    
    // No duplicates found
    if (duplicates.by_name.length === 0 && duplicates.by_contact.length === 0 && 
        (!duplicates.by_email || duplicates.by_email.length === 0) && duplicates.potential_duplicates.length === 0) {
        html += `
            <div style="text-align: center; padding: 40px; background: #d4edda; border-radius: 8px; color: #155724;">
                <h3>✅ No Duplicates Found!</h3>
                <p>All workers appear to be unique entries.</p>
            </div>
        `;
    }
    
    html += `
        <div style="margin-top: 30px; text-align: center;">
            <button type="button" class="btn btn-primary" onclick="closeDuplicateAnalysisModal()">Close</button>
        </div>
    `;
    
    content.innerHTML = html;
    
    // Close worker modal if it was open (since we're editing from duplicate analysis)
    const workerModal = document.getElementById('workerModal');
    if (workerModal) {
        // We'll close it when user clicks edit from duplicate analysis
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close Duplicate Analysis Modal
function closeDuplicateAnalysisModal() {
    document.getElementById('duplicateAnalysisModal').style.display = 'none';
}

// Rod Length Management Functions - MUST be global
function showRodLengthModal() {
    const modal = document.getElementById('rodLengthModal');
    if (modal) {
        modal.style.display = 'flex';
        const form = document.getElementById('rodLengthForm');
        if (form) {
            form.reset();
        }
        const actionInput = document.getElementById('rodLengthAction');
        if (actionInput) {
            actionInput.value = 'add_rod_length';
        }
        const oldLengthInput = document.getElementById('rodLengthOld');
        if (oldLengthInput) {
            oldLengthInput.value = '';
        }
        const lengthInput = document.getElementById('rod_length');
        if (lengthInput) {
            lengthInput.value = '';
        }
        const title = document.getElementById('rodLengthModalTitle');
        if (title) {
            title.textContent = 'Add New Rod Length';
        }
    }
}

function editRodLength(length) {
    const modal = document.getElementById('rodLengthModal');
    if (modal) {
        modal.style.display = 'flex';
        const actionInput = document.getElementById('rodLengthAction');
        if (actionInput) {
            actionInput.value = 'update_rod_length';
        }
        const oldLengthInput = document.getElementById('rodLengthOld');
        if (oldLengthInput) {
            oldLengthInput.value = length;
        }
        const lengthInput = document.getElementById('rod_length');
        if (lengthInput) {
            lengthInput.value = length;
        }
        const title = document.getElementById('rodLengthModalTitle');
        if (title) {
            title.textContent = 'Edit Rod Length';
        }
    }
}

function closeRodLengthModal() {
    const modal = document.getElementById('rodLengthModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function deleteRodLength(length) {
    if (confirm('Are you sure you want to delete rod length ' + length + 'm? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.setAttribute('action', '../api/config-crud.php');
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken && csrfToken.value) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_rod_length';
        form.appendChild(actionInput);
        
        const lengthInput = document.createElement('input');
        lengthInput.type = 'hidden';
        lengthInput.name = 'length';
        lengthInput.value = length;
        form.appendChild(lengthInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Helper function to edit worker from JSON string (used in duplicate analysis)
function editWorkerFromString(workerJson) {
    try {
        // Decode HTML entities and parse JSON
        const workerStr = workerJson.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
        const worker = JSON.parse(workerStr);
        editWorker(worker);
    } catch (error) {
        console.error('Error parsing worker data:', error);
        alert('Error loading worker data. Please try again.');
    }
}

// Merge duplicate group
async function mergeDuplicateGroup(workerIdsJson, groupName) {
    try {
        const workerIds = JSON.parse(workerIdsJson.replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        
        if (workerIds.length < 2) {
            alert('At least 2 workers are needed to merge.');
            return;
        }
        
        // Find which worker to keep (the one with the checked radio button)
        const firstWorkerId = workerIds[0];
        let keepWorkerId = firstWorkerId;
        
        // Try to find the checked radio button for this group
        // Check all possible radio button groups (by_name, by_contact, by_email)
        const radioButtons = document.querySelectorAll(`input[name^="keep_worker_group_"]:checked, input[name^="keep_worker_contact_"]:checked, input[name^="keep_worker_email_"]:checked`);
        if (radioButtons.length > 0) {
            // Find the last checked button that matches one of our worker IDs
            for (let i = radioButtons.length - 1; i >= 0; i--) {
                const checkedValue = parseInt(radioButtons[i].value);
                if (workerIds.includes(checkedValue)) {
                    keepWorkerId = checkedValue;
                    break;
                }
            }
        }
        
        if (!confirm(`Merge ${workerIds.length - 1} duplicate worker(s) into the selected worker?\n\nThis will:\n- Update all payroll entries\n- Update all loans\n- Delete duplicate worker records\n\nThis action cannot be undone!`)) {
            return;
        }
        
        // Prepare merge worker IDs (exclude the keep worker)
        const mergeWorkerIds = workerIds.filter(id => id !== keepWorkerId);
        
        // Call merge API
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('keep_worker_id', keepWorkerId);
        mergeWorkerIds.forEach(id => {
            formData.append('merge_worker_ids[]', id);
        });
        
        const response = await fetch('../api/merge-worker-duplicates.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Successfully merged ${result.stats.workers_merged} duplicate worker(s)!\n\nUpdated:\n- ${result.stats.payroll_entries_updated} payroll entries\n- ${result.stats.loans_updated} loans\n\nKept worker: ${result.stats.keep_worker_name}`);
            // Reload the page to refresh the workers list
            location.reload();
        } else {
            alert('❌ Error: ' + (result.message || 'Failed to merge duplicates'));
        }
    } catch (error) {
        console.error('Error merging duplicates:', error);
        alert('Error merging duplicates: ' + error.message);
    }
}

// Bulk merge ALL duplicates
async function bulkMergeAllDuplicates() {
    if (!confirm(`⚠️ WARNING: This will automatically merge ALL duplicate workers found in the analysis!\n\nThis will:\n- Select the best worker to keep (most complete data)\n- Update all payroll entries\n- Update all loans\n- Delete all duplicate worker records\n\nThis action cannot be undone!\n\nAre you sure you want to proceed?`)) {
        return;
    }
    
    const content = document.getElementById('duplicateAnalysisContent');
    const originalContent = content.innerHTML;
    
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #ffc107; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p style="font-size: 16px; font-weight: 600;">Merging all duplicates...</p>
            <p style="color: #6c757d; font-size: 14px; margin-top: 10px;">This may take a few moments. Please wait...</p>
        </div>
    `;
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        
        const response = await fetch('../api/bulk-merge-worker-duplicates.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            let detailsHtml = '';
            if (result.details && result.details.length > 0) {
                detailsHtml = '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; max-height: 300px; overflow-y: auto;"><strong>Merge Details:</strong><ul style="margin: 10px 0 0 0; padding-left: 20px;">';
                result.details.forEach(detail => {
                    detailsHtml += `<li style="margin: 5px 0; font-size: 12px;">${escapeHtml(detail)}</li>`;
                });
                detailsHtml += '</ul></div>';
            }
            
            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 48px; margin-bottom: 20px;">✅</div>
                    <h3 style="color: #28a745; margin-bottom: 15px;">Successfully Merged All Duplicates!</h3>
                    <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                        <div style="margin-bottom: 10px;"><strong>Workers Merged:</strong> ${result.stats.workers_merged}</div>
                        <div style="margin-bottom: 10px;"><strong>Payroll Entries Updated:</strong> ${result.stats.payroll_entries_updated}</div>
                        <div style="margin-bottom: 10px;"><strong>Loans Updated:</strong> ${result.stats.loans_updated}</div>
                    </div>
                    ${detailsHtml}
                    <div style="margin-top: 30px;">
                        <button type="button" class="btn btn-primary" onclick="location.reload()">Reload Page to See Results</button>
                    </div>
                </div>
            `;
        } else {
            throw new Error(result.message || 'Failed to merge duplicates');
        }
    } catch (error) {
        console.error('Error bulk merging duplicates:', error);
        content.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <h3>❌ Error</h3>
                <p>${error.message || 'Failed to merge duplicates. Please try again.'}</p>
                <button type="button" class="btn btn-outline" onclick="closeDuplicateAnalysisModal()" style="margin-top: 20px;">Close</button>
            </div>
        `;
    }
}

// Merge duplicate pair
async function mergeDuplicatePair(workerIdsJson, pairIndex) {
    try {
        const workerIds = JSON.parse(workerIdsJson.replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        
        if (workerIds.length !== 2) {
            alert('Exactly 2 workers are needed to merge a pair.');
            return;
        }
        
        // Find which worker to keep (the one with the checked radio button)
        const checkedRadio = document.querySelector(`input[name="keep_worker_pair_${pairIndex}"]:checked`);
        if (!checkedRadio) {
            alert('Please select which worker to keep.');
            return;
        }
        
        const keepWorkerId = parseInt(checkedRadio.value);
        const mergeWorkerId = workerIds.find(id => id !== keepWorkerId);
        
        if (!confirm(`Merge worker ID ${mergeWorkerId} into worker ID ${keepWorkerId}?\n\nThis will:\n- Update all payroll entries\n- Update all loans\n- Delete the duplicate worker record\n\nThis action cannot be undone!`)) {
            return;
        }
        
        // Call merge API
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('keep_worker_id', keepWorkerId);
        formData.append('merge_worker_ids[]', mergeWorkerId);
        
        const response = await fetch('../api/merge-worker-duplicates.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Successfully merged duplicate worker!\n\nUpdated:\n- ${result.stats.payroll_entries_updated} payroll entries\n- ${result.stats.loans_updated} loans\n\nKept worker: ${result.stats.keep_worker_name}`);
            // Reload the page to refresh the workers list
            location.reload();
        } else {
            alert('❌ Error: ' + (result.message || 'Failed to merge duplicates'));
        }
    } catch (error) {
        console.error('Error merging duplicates:', error);
        alert('Error merging duplicates: ' + error.message);
    }
}

// Material Management
function editMaterial(materialType, material) {
    document.getElementById('materialModal').style.display = 'flex';
    document.getElementById('material_type').value = materialType;
    document.getElementById('quantity_received').value = material.quantity_received;
    document.getElementById('unit_cost').value = material.unit_cost;
}

function closeMaterialModal() {
    document.getElementById('materialModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['rigModal', 'workerModal', 'materialModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
}

// Handle AJAX form submissions
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Save';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Saving...';
            }
            
            try {
                // Ensure form.action is a valid URL string - CRITICAL FIX
                let formAction = null;
                
                // First, try to get action attribute
                if (form.hasAttribute && form.hasAttribute('action')) {
                    formAction = form.getAttribute('action');
                }
                
                // If not found, try form.action property (but be careful - it might be an element)
                if (!formAction || typeof formAction !== 'string') {
                    const actionProp = form.action;
                    if (typeof actionProp === 'string' && actionProp.length > 0 && !actionProp.includes('[object')) {
                        formAction = actionProp;
                    }
                }
                
                // Fallback to default if still not valid
                if (!formAction || typeof formAction !== 'string' || formAction.includes('[object') || formAction.trim() === '') {
                    formAction = '../api/config-crud.php';
                }
                
                // Normalize the URL - ensure it's a valid path
                formAction = formAction.trim();
                if (formAction === '' || formAction === 'null' || formAction === 'undefined') {
                    formAction = '../api/config-crud.php';
                }
                
                // Log for debugging if there's still an issue
                if (formAction.includes('[object')) {
                    console.error('Invalid form action detected:', formAction, 'Form:', form);
                    formAction = '../api/config-crud.php';
                }
                
                // Validate CSRF token exists
                const csrfInput = form.querySelector('[name="csrf_token"]');
                if (!csrfInput || !csrfInput.value) {
                    throw new Error('Security token missing. Please refresh the page and try again.');
                }
                
                const response = await fetch(formAction, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                // Check if response is OK
                if (!response.ok) {
                    // Try to get error message from response
                    let errorMsg = `HTTP error! status: ${response.status}`;
                    try {
                        const errorText = await response.text();
                        if (errorText) {
                            try {
                                const errorJson = JSON.parse(errorText);
                                errorMsg = errorJson.message || errorMsg;
                            } catch (e) {
                                // Not JSON, use text
                                if (errorText.length < 200) {
                                    errorMsg = errorText;
                                }
                            }
                        }
                    } catch (e) {
                        // Ignore, use default error message
                    }
                    throw new Error(errorMsg);
                }
                
                // Try to parse as JSON
                let result;
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    result = await response.json();
                } else {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    console.error('Response status:', response.status);
                    console.error('Response URL:', response.url);
                    
                    // Try to parse as JSON anyway (might be JSON without proper header)
                    try {
                        result = JSON.parse(text);
                    } catch (parseError) {
                        // Try to extract error message from HTML/text response
                        let errorMsg = 'Server returned invalid response format';
                        if (text.includes('Fatal error') || text.includes('Parse error') || text.includes('Warning') || text.includes('Notice')) {
                            errorMsg = 'Server error detected. Please check server logs or contact support.';
                            // Extract error message if possible
                            const fatalMatch = text.match(/Fatal error[^<:]*:?\s*([^<\n]+)/i);
                            if (fatalMatch) {
                                errorMsg = 'Server error: ' + fatalMatch[1].trim();
                            }
                        } else if (text.length > 0 && text.length < 500) {
                            errorMsg = 'Unexpected response: ' + text.substring(0, 200);
                        }
                        throw new Error(errorMsg + ' (Status: ' + response.status + ')');
                    }
                }
                
                if (result.success) {
                    if (window.abbisApp) {
                        window.abbisApp.showAlert('success', result.message || 'Saved successfully');
                    } else {
                        alert(result.message || 'Saved successfully');
                    }
                    
                    // If logo upload, update preview
                    if (form.id === 'logoUploadForm' && result.logo_url) {
                        const preview = document.getElementById('logo-preview');
                        if (preview) {
                            let img = document.getElementById('current-logo-img');
                            if (!img) {
                                const placeholder = document.getElementById('current-logo-placeholder');
                                if (placeholder) {
                                    placeholder.style.display = 'none';
                                }
                                img = document.createElement('img');
                                img.id = 'current-logo-img';
                                img.style.cssText = 'max-width: 100%; max-height: 100%; object-fit: contain;';
                                preview.appendChild(img);
                            }
                            img.src = result.logo_url;
                            img.alt = 'Company Logo';
                        }
                        // Hide upload button
                        const uploadBtn = document.getElementById('logoUploadBtn');
                        if (uploadBtn) {
                            uploadBtn.style.display = 'none';
                        }
                        // Clear file input
                        const fileInput = document.getElementById('company_logo');
                        if (fileInput) {
                            fileInput.value = '';
                        }
                    }
                    
                    // Close modals after successful submission
                    if (form.id === 'materialForm') {
                        const materialModal = document.getElementById('materialModal');
                        if (materialModal) {
                            materialModal.style.display = 'none';
                        }
                        form.reset();
                    }
                    
                    if (form.id === 'rigForm') {
                        const rigModal = document.getElementById('rigModal');
                        if (rigModal) {
                            rigModal.style.display = 'none';
                        }
                    }
                    
                    if (form.id === 'rodLengthForm') {
                        const rodLengthModal = document.getElementById('rodLengthModal');
                        if (rodLengthModal) {
                            rodLengthModal.style.display = 'none';
                        }
                    }
                    
                    // Reload page after 1.5 seconds to show updated data (unless it's logo upload)
                    if (form.id !== 'logoUploadForm') {
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    const errorMsg = result.message || 'Error saving data. Please try again.';
                    if (window.abbisApp && typeof window.abbisApp.showAlert === 'function') {
                        window.abbisApp.showAlert('error', errorMsg);
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            } catch (error) {
                console.error('AJAX form submission error:', error);
                const formId = form.id || form.getAttribute('id') || 'unknown';
                const formActionAttr = form.getAttribute('action') || 'unknown';
                console.error('Error details:', {
                    message: error.message,
                    name: error.name,
                    stack: error.stack,
                    formId: formId,
                    formAction: formActionAttr
                });
                
                let errorMsg = 'Network error occurred. ';
                if (error.message) {
                    errorMsg += error.message;
                } else if (error.name === 'TypeError' && error.message && error.message.includes('fetch')) {
                    errorMsg += 'Cannot connect to server. Please check your internet connection.';
                } else if (error.name === 'SyntaxError') {
                    errorMsg += 'Server returned invalid data. Please refresh and try again.';
                } else if (error.name === 'NetworkError' || (error.message && error.message.includes('network'))) {
                    errorMsg += 'Network connection failed. Please check your connection and try again.';
                } else {
                    errorMsg += 'Please try again or refresh the page.';
                }
                
                if (window.abbisApp && typeof window.abbisApp.showAlert === 'function') {
                    window.abbisApp.showAlert('error', errorMsg);
                } else {
                    alert('Error: ' + errorMsg);
                }
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });
});

