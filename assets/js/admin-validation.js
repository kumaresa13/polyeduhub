/**
 * PolyEduHub Admin Forms Validation Script
 * 
 * This script handles validation for admin login, registration, 
 * and password reset forms before submission.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Admin Login Form Validation
    const adminLoginForm = document.getElementById('adminLoginForm');
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', function(event) {
            let isValid = true;
            const email = document.getElementById('inputEmailAddress').value.trim();
            const password = document.getElementById('inputPassword').value.trim();
            
            // Reset error messages
            clearErrorMessages();
            
            // Email validation
            if (!email) {
                displayError('inputEmailAddress', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email)) {
                displayError('inputEmailAddress', 'Please enter a valid email address');
                isValid = false;
            }
            
            // Password validation
            if (!password) {
                displayError('inputPassword', 'Password is required');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    }
    
    // Admin Registration Form Validation
    const adminRegisterForm = document.getElementById('adminRegisterForm');
    if (adminRegisterForm) {
        adminRegisterForm.addEventListener('submit', function(event) {
            let isValid = true;
            const firstName = document.getElementById('inputFirstName').value.trim();
            const lastName = document.getElementById('inputLastName').value.trim();
            const email = document.getElementById('inputEmailAddress').value.trim();
            const adminCode = document.getElementById('inputAdminCode').value.trim();
            const password = document.getElementById('inputPassword').value.trim();
            const confirmPassword = document.getElementById('inputConfirmPassword').value.trim();
            
            // Reset error messages
            clearErrorMessages();
            
            // Name validation
            if (!firstName) {
                displayError('inputFirstName', 'First name is required');
                isValid = false;
            }
            
            if (!lastName) {
                displayError('inputLastName', 'Last name is required');
                isValid = false;
            }
            
            // Email validation
            if (!email) {
                displayError('inputEmailAddress', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email)) {
                displayError('inputEmailAddress', 'Please enter a valid email address');
                isValid = false;
            }
            
            // Admin code validation
            if (!adminCode) {
                displayError('inputAdminCode', 'Admin registration code is required');
                isValid = false;
            }
            
            // Password validation
            if (!password) {
                displayError('inputPassword', 'Password is required');
                isValid = false;
            } else if (password.length < 8) {
                displayError('inputPassword', 'Password must be at least 8 characters long');
                isValid = false;
            }
            
            // Confirm password validation
            if (!confirmPassword) {
                displayError('inputConfirmPassword', 'Please confirm your password');
                isValid = false;
            } else if (password !== confirmPassword) {
                displayError('inputConfirmPassword', 'Passwords do not match');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    }
    
    // Admin Password Reset Form Validation
    const adminPasswordResetForm = document.getElementById('adminPasswordResetForm');
    if (adminPasswordResetForm) {
        adminPasswordResetForm.addEventListener('submit', function(event) {
            let isValid = true;
            const email = document.getElementById('inputEmailAddress').value.trim();
            
            // Reset error messages
            clearErrorMessages();
            
            // Email validation
            if (!email) {
                displayError('inputEmailAddress', 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email)) {
                displayError('inputEmailAddress', 'Please enter a valid email address');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    }
    
    // Helper Functions
    
    // Validate email format
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Display error message
    function displayError(inputId, message) {
        const inputElement = document.getElementById(inputId);
        const errorElement = document.createElement('div');
        errorElement.className = 'text-danger small mt-1';
        errorElement.textContent = message;
        
        inputElement.classList.add('is-invalid');
        inputElement.parentNode.appendChild(errorElement);
    }
    
    // Clear all error messages
    function clearErrorMessages() {
        const errorMessages = document.querySelectorAll('.text-danger.small.mt-1');
        errorMessages.forEach(function(element) {
            element.remove();
        });
        
        const invalidInputs = document.querySelectorAll('.is-invalid');
        invalidInputs.forEach(function(element) {
            element.classList.remove('is-invalid');
        });
    }
    
    // Show/hide password functionality
    const passwordToggles = document.querySelectorAll('.password-toggle');
    if (passwordToggles) {
        passwordToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const passwordField = document.getElementById(this.dataset.target);
                const icon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                } else {
                    passwordField.type = 'password';
                    icon.className = 'fas fa-eye';
                }
            });
        });
    }
});