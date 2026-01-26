/**
 * Universal Email Validation for Medical Surveillance System
 * Ensures all email fields across the system have proper validation
 */

// Email validation regex pattern (RFC 5322 compliant)
const EMAIL_PATTERN = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean} - True if valid, false otherwise
 */
function isValidEmail(email) {
    if (!email || email.trim() === '') {
        return true; // Allow empty emails (optional fields)
    }
    return EMAIL_PATTERN.test(email.trim());
}

/**
 * Show email validation error
 * @param {HTMLElement} input - Email input element
 * @param {string} message - Error message to display
 */
function showEmailError(input, message) {
    input.classList.add('is-invalid');
    input.setCustomValidity(message);
    
    // Remove existing error message
    const existingError = input.parentNode.querySelector('.invalid-feedback');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    input.parentNode.appendChild(errorDiv);
}

/**
 * Clear email validation error
 * @param {HTMLElement} input - Email input element
 */
function clearEmailError(input) {
    input.classList.remove('is-invalid');
    input.setCustomValidity('');
    
    // Remove error message
    const errorDiv = input.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Setup email validation for a specific input
 * @param {HTMLElement} input - Email input element
 */
function setupEmailValidation(input) {
    // Real-time validation on input
    input.addEventListener('input', function() {
        const email = this.value.trim();
        
        if (email === '') {
            clearEmailError(this);
            return;
        }
        
        if (!isValidEmail(email)) {
            showEmailError(this, 'Please enter a valid email address (e.g., user@example.com)');
        } else {
            clearEmailError(this);
        }
    });
    
    // Validation on blur (when user leaves field)
    input.addEventListener('blur', function() {
        const email = this.value.trim();
        
        if (email === '') {
            clearEmailError(this);
            return;
        }
        
        if (!isValidEmail(email)) {
            showEmailError(this, 'Please enter a valid email address (e.g., user@example.com)');
        } else {
            clearEmailError(this);
        }
    });
    
    // Validation on form submit
    const form = input.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const email = input.value.trim();
            
            // Check if field is required
            const isRequired = input.hasAttribute('required');
            
            if (isRequired && email === '') {
                e.preventDefault();
                showEmailError(input, 'Email address is required');
                input.focus();
                return false;
            }
            
            if (email !== '' && !isValidEmail(email)) {
                e.preventDefault();
                showEmailError(input, 'Please enter a valid email address (e.g., user@example.com)');
                input.focus();
                return false;
            }
            
            clearEmailError(input);
        });
    }
}

/**
 * Initialize email validation for all email fields on the page
 */
function initializeEmailValidation() {
    // Find all email input fields
    const emailInputs = document.querySelectorAll('input[type="email"], input[name="email"]');
    
    emailInputs.forEach(function(input) {
        // Ensure the input type is set to email
        if (input.name === 'email' || input.id === 'email' || input.id.includes('email')) {
            input.type = 'email';
        }
        
        // Setup validation
        setupEmailValidation(input);
        
        // Add placeholder if not present
        if (!input.placeholder) {
            input.placeholder = 'user@example.com';
        }
        
        // Ensure email fields don't get auto-capitalized
        input.style.textTransform = 'none';
        input.autocomplete = 'email';
    });
    
    console.log(`Email validation initialized for ${emailInputs.length} email fields`);
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initializeEmailValidation);

// Also initialize if script is loaded after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEmailValidation);
} else {
    initializeEmailValidation();
}

























































