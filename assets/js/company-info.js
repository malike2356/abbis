/**
 * Company Information Form Handler
 * Clean and simple implementation
 */

(function() {
    'use strict';
    
    // Wait for DOM
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('companyInfoForm');
        
        if (!form) {
            return; // Form doesn't exist on this page
        }
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Save';
            
            // Disable button and show loading
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Saving...';
            }
            
            // Create FormData
            const formData = new FormData(form);
            
            // Send request
            fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                // Check if response is OK
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                
                // Get response text first
                return response.text();
            })
            .then(function(text) {
                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid server response');
                }
                
                // Handle result
                if (result.success) {
                    // Success
                    showMessage(result.message || 'Company information saved successfully!', 'success');
                    
                    // Reload page after 2 seconds to show updated info
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Error
                    showMessage(result.message || 'Failed to save company information', 'error');
                    
                    // Re-enable button
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showMessage('Network error: ' + error.message + '. Please try again.', 'error');
                
                // Re-enable button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    });
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        // Remove any existing messages
        const existing = document.querySelector('.company-info-message');
        if (existing) {
            existing.remove();
        }
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = 'company-info-message alert alert-' + (type === 'success' ? 'success' : 'danger');
        messageDiv.style.cssText = 'margin: 20px 0; padding: 15px; border-radius: 8px;';
        messageDiv.textContent = message;
        
        // Insert before form
        const form = document.getElementById('companyInfoForm');
        if (form && form.parentNode) {
            form.parentNode.insertBefore(messageDiv, form);
            
            // Scroll to message
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Auto-remove after 5 seconds for success
            if (type === 'success') {
                setTimeout(function() {
                    messageDiv.remove();
                }, 5000);
            }
        } else {
            // Fallback: use alert
            alert(message);
        }
    }
    
})();
