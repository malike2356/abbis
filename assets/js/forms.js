// Form management and validation for ABBIS
class ABBISForms {
    constructor() {
        this.initialized = false;
    }

    init() {
        if (this.initialized) return;
        
        this.initializeFormValidation();
        this.initializeAutoSave();
        this.initializeDynamicFields();
        this.initializeFileUploads();
        
        this.initialized = true;
    }

    // Initialize form validation
    initializeFormValidation() {
        const forms = document.querySelectorAll('form[data-validate="true"]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.validateForm(e));
            
            // Real-time validation
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => this.clearFieldError(input));
            });
        });
    }

    // Validate entire form
    validateForm(e) {
        const form = e.target;
        let isValid = true;
        
        const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        // Custom validation for specific fields
        const numericFields = form.querySelectorAll('input[type="number"][data-min], input[type="number"][data-max]');
        numericFields.forEach(field => {
            if (!this.validateNumericField(field)) {
                isValid = false;
            }
        });
        
        // Date validation
        const dateFields = form.querySelectorAll('input[type="date"][data-min-date], input[type="date"][data-max-date]');
        dateFields.forEach(field => {
            if (!this.validateDateField(field)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            this.showFormError(form, 'Please fix the errors highlighted below.');
        }
    }

    // Validate individual field
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        // Required field validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = field.getAttribute('data-required-message') || 'This field is required';
        }
        
        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }
        
        // Phone validation
        if (field.type === 'tel' && value) {
            const phoneRegex = /^\+?[\d\s\-\(\)]{10,}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
        }
        
        // Numeric validation
        if (field.type === 'number' && value) {
            if (!this.validateNumericField(field)) {
                isValid = false;
            }
        }
        
        // Date validation
        if (field.type === 'date' && value) {
            if (!this.validateDateField(field)) {
                isValid = false;
            }
        }
        
        if (!isValid) {
            this.showFieldError(field, errorMessage);
        } else {
            this.clearFieldError(field);
        }
        
        return isValid;
    }

    // Validate numeric field with min/max constraints
    validateNumericField(field) {
        const value = parseFloat(field.value);
        const min = parseFloat(field.getAttribute('data-min'));
        const max = parseFloat(field.getAttribute('data-max'));
        let isValid = true;
        let errorMessage = '';
        
        if (!isNaN(min) && value < min) {
            isValid = false;
            errorMessage = `Value must be at least ${min}`;
        }
        
        if (!isNaN(max) && value > max) {
            isValid = false;
            errorMessage = `Value must be at most ${max}`;
        }
        
        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }
        
        return isValid;
    }

    // Validate date field with constraints
    validateDateField(field) {
        const value = new Date(field.value);
        const minDate = field.getAttribute('data-min-date');
        const maxDate = field.getAttribute('data-max-date');
        let isValid = true;
        let errorMessage = '';
        
        if (minDate && value < new Date(minDate)) {
            isValid = false;
            errorMessage = `Date must be after ${new Date(minDate).toLocaleDateString()}`;
        }
        
        if (maxDate && value > new Date(maxDate)) {
            isValid = false;
            errorMessage = `Date must be before ${new Date(maxDate).toLocaleDateString()}`;
        }
        
        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }
        
        return isValid;
    }

    // Show field error
    showFieldError(field, message) {
        this.clearFieldError(field);
        
        field.classList.add('error');
        
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        
        field.parentNode.appendChild(errorElement);
    }

    // Clear field error
    clearFieldError(field) {
        field.classList.remove('error');
        
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    // Show form-level error
    showFormError(form, message) {
        this.clearFormError(form);
        
        const errorElement = document.createElement('div');
        errorElement.className = 'form-error alert alert-error';
        errorElement.textContent = message;
        
        form.insertBefore(errorElement, form.firstChild);
        
        // Scroll to error
        errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Clear form-level error
    clearFormError(form) {
        const existingError = form.querySelector('.form-error');
        if (existingError) {
            existingError.remove();
        }
    }

    // Initialize auto-save functionality
    initializeAutoSave() {
        const forms = document.querySelectorAll('form[data-auto-save="true"]');
        
        forms.forEach(form => {
            let saveTimeout;
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        this.autoSaveForm(form);
                    }, 1000);
                });
            });
            
            // Save on page unload
            window.addEventListener('beforeunload', () => {
                if (this.hasUnsavedChanges(form)) {
                    this.autoSaveForm(form);
                }
            });
        });
    }

    // Auto-save form data
    async autoSaveForm(form) {
        const formData = new FormData(form);
        const formId = form.id || 'unsaved_form';
        
        try {
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            localStorage.setItem(`abbis_autosave_${formId}`, JSON.stringify(data));
            localStorage.setItem(`abbis_autosave_${formId}_timestamp`, new Date().toISOString());
            
            this.showAutoSaveIndicator(form, true);
            
            setTimeout(() => {
                this.showAutoSaveIndicator(form, false);
            }, 2000);
            
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }

    // Restore auto-saved data
    restoreAutoSave(form) {
        const formId = form.id || 'unsaved_form';
        const savedData = localStorage.getItem(`abbis_autosave_${formId}`);
        
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                const timestamp = localStorage.getItem(`abbis_autosave_${formId}_timestamp`);
                
                if (confirm(`Restore unsaved changes from ${new Date(timestamp).toLocaleString()}?`)) {
                    Object.keys(data).forEach(key => {
                        const field = form.querySelector(`[name="${key}"]`);
                        if (field) {
                            field.value = data[key];
                        }
                    });
                }
            } catch (error) {
                console.error('Error restoring auto-save:', error);
            }
        }
    }

    // Clear auto-saved data
    clearAutoSave(form) {
        const formId = form.id || 'unsaved_form';
        localStorage.removeItem(`abbis_autosave_${formId}`);
        localStorage.removeItem(`abbis_autosave_${formId}_timestamp`);
    }

    // Check if form has unsaved changes
    hasUnsavedChanges(form) {
        const formId = form.id || 'unsaved_form';
        return localStorage.getItem(`abbis_autosave_${formId}`) !== null;
    }

    // Show auto-save indicator
    showAutoSaveIndicator(form, show) {
        let indicator = form.querySelector('.auto-save-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'auto-save-indicator';
            form.appendChild(indicator);
        }
        
        if (show) {
            indicator.textContent = 'Saving...';
            indicator.classList.add('visible');
        } else {
            indicator.textContent = 'Saved';
            setTimeout(() => {
                indicator.classList.remove('visible');
            }, 1000);
        }
    }

    // Initialize dynamic fields (show/hide based on other fields)
    initializeDynamicFields() {
        // Conditional field display
        document.addEventListener('change', (e) => {
            const target = e.target;
            const dependentFields = document.querySelectorAll(`[data-show-if="${target.name}"]`);
            
            dependentFields.forEach(dependentField => {
                const requiredValue = dependentField.getAttribute('data-show-if-value');
                const shouldShow = target.value === requiredValue;
                
                this.toggleFieldVisibility(dependentField, shouldShow);
            });
        });
        
        // Initialize visibility on page load
        this.initializeFieldVisibility();
    }

    // Toggle field visibility
    toggleFieldVisibility(field, show) {
        const container = field.closest('.form-group') || field;
        
        if (show) {
            container.style.display = '';
            const inputs = container.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = false;
                input.removeAttribute('aria-hidden');
            });
        } else {
            container.style.display = 'none';
            const inputs = container.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = true;
                input.setAttribute('aria-hidden', 'true');
            });
        }
    }

    // Initialize field visibility based on current values
    initializeFieldVisibility() {
        const dependentFields = document.querySelectorAll('[data-show-if]');
        
        dependentFields.forEach(dependentField => {
            const sourceFieldName = dependentField.getAttribute('data-show-if');
            const requiredValue = dependentField.getAttribute('data-show-if-value');
            const sourceField = document.querySelector(`[name="${sourceFieldName}"]`);
            
            if (sourceField) {
                const shouldShow = sourceField.value === requiredValue;
                this.toggleFieldVisibility(dependentField, shouldShow);
            }
        });
    }

    // Initialize file uploads with preview
    initializeFileUploads() {
        const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.handleFileUpload(e.target);
            });
        });
    }

    // Handle file upload with preview
    handleFileUpload(input) {
        const files = input.files;
        const previewContainer = document.getElementById(input.getAttribute('data-preview'));
        
        if (!previewContainer) return;
        
        previewContainer.innerHTML = '';
        
        Array.from(files).forEach(file => {
            const fileElement = document.createElement('div');
            fileElement.className = 'file-preview';
            fileElement.innerHTML = `
                <span class="file-name">${file.name}</span>
                <span class="file-size">(${this.formatFileSize(file.size)})</span>
                <button type="button" class="btn btn-sm btn-danger remove-file">×</button>
            `;
            
            previewContainer.appendChild(fileElement);
            
            // Add remove functionality
            const removeBtn = fileElement.querySelector('.remove-file');
            removeBtn.addEventListener('click', () => {
                fileElement.remove();
                // Clear the file input
                input.value = '';
            });
        });
    }

    // Format file size
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Reset form to initial state
    resetForm(form) {
        form.reset();
        this.clearFormError(form);
        this.clearAutoSave(form);
        
        // Clear all field errors
        const errorFields = form.querySelectorAll('.error');
        errorFields.forEach(field => {
            this.clearFieldError(field);
        });
        
        // Reset dynamic fields visibility
        this.initializeFieldVisibility();
    }

    // Submit form via AJAX
    async submitFormAjax(form, options = {}) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            this.setLoadingState(submitBtn, true);
            
            const formData = new FormData(form);
            
            const response = await fetch(form.action, {
                method: form.method,
                body: formData,
                ...options
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.clearAutoSave(form);
                this.showSuccessMessage(form, result.message);
                
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                }
                
                return result;
            } else {
                throw new Error(result.message || 'Form submission failed');
            }
            
        } catch (error) {
            this.showFormError(form, error.message);
            throw error;
        } finally {
            this.setLoadingState(submitBtn, false, originalText);
        }
    }

    // Set loading state for submit button
    setLoadingState(button, isLoading, originalText = null) {
        if (isLoading) {
            button.disabled = true;
            button.setAttribute('data-original-text', button.innerHTML);
            button.innerHTML = '<div class="spinner"></div> Processing...';
        } else {
            button.disabled = false;
            button.innerHTML = originalText || button.getAttribute('data-original-text') || 'Submit';
        }
    }

    // Show success message
    showSuccessMessage(form, message) {
        this.clearFormError(form);
        
        const successElement = document.createElement('div');
        successElement.className = 'form-success alert alert-success';
        successElement.textContent = message;
        
        form.insertBefore(successElement, form.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            successElement.remove();
        }, 5000);
    }
}

// Initialize forms when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.abbisForms = new ABBISForms();
    abbisForms.init();
});