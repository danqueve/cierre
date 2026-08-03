/**
 * main.js - System-wide Interactions
 * Handles Toasts, Form Validation, and Loading States.
 */

// --- 1. TOAST NOTIFICATIONS ---
window.showToast = function(message, type = 'success') {
    let container = document.getElementById('toast-container');
    
    // Create container if it doesn't exist
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container); // Append to body to ensure it's on top
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Icons based on type
    let icon = '';
    if (type === 'success') icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-green);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
    else if (type === 'error') icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-red);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
    else icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-blue);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

    toast.innerHTML = `
        ${icon}
        <div class="toast-content">
            <div class="toast-title">${type === 'success' ? 'Éxito' : (type === 'error' ? 'Error' : 'Información')}</div>
            <div class="toast-message">${message}</div>
        </div>
    `;

    container.appendChild(toast);

    // Animation
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Auto-dismiss
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 400); // Wait for transition
    }, 5000);
}


// --- 2. GLOBAL FORM HANDLING (Validation & Loading) ---
document.addEventListener('DOMContentLoaded', function() {
    
    // A. Validation Logic
    const inputs = document.querySelectorAll('input[required], select[required]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateInput(this);
        });

        input.addEventListener('input', function() {
            // Remove error immediately when user starts typing
            if (this.classList.contains('invalid')) {
                this.classList.remove('invalid');
            }
            // Optional: Validate valid state on input for positive feedback
            if (this.value.trim() !== '') {
               this.classList.add('valid');
            } else {
               this.classList.remove('valid');
            }
        });
    });

    function validateInput(input) {
        if (input.value.trim() === '') {
            input.classList.add('invalid');
            input.classList.remove('valid');
            // Shake animation triggered by class
        } else {
            input.classList.remove('invalid');
            input.classList.add('valid');
        }
    }

    // B. Loading State on Form Submit
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Check basic validation first
            let isValid = true;
            const requiredInputs = this.querySelectorAll('[required]');
            
            requiredInputs.forEach(input => {
                if (input.value.trim() === '') {
                    validateInput(input);
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                showToast('Por favor, complete todos los campos obligatorios.', 'error');
                return;
            }

            // If valid, show loading state
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                btn.classList.add('btn-loading');
                const originalText = btn.innerText;
                // Preserve width if possible
                btn.dataset.text = originalText; 
                btn.innerHTML = `<span style="visibility:hidden">${originalText}</span>`; 
                // The spinner uses ::after via CSS, so we hide text but keep space
            }
        });
    });

    // C. Check for Server-Side Messages (passed via PHP to window object if needed, 
    // or just rely on inline script in PHP files calling showToast)
});
