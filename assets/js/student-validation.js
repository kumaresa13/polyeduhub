/**
 * PolyEduHub Student Forms Validation Script
 * 
 * This script handles validation for student login, registration, 
 * and password reset forms before submission.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Student Login Form Validation
    const studentLoginForm = document.getElementById('studentLoginForm');
    if (studentLoginForm) {
        studentLoginForm.addEventListener('submit', function(event) {
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
    
    // Student Registration Form Validation
    const studentRegisterForm = document.getElementById('studentRegisterForm');
    if (studentRegisterForm) {
        studentRegisterForm.addEventListener('submit', function(event) {
            let isValid = true;
            const firstName = document.getElementById('inputFirstName').value.trim();
            const lastName = document.getElementById('inputLastName').value.trim();
            const email = document.getElementById('inputEmailAddress').value.trim();
            const studentID = document.getElementById('inputStudentID').value.trim();
            const department = document.getElementById('inputDepartment').value;
            const yearOfStudy = document.getElementById('inputYearOfStudy').value;
            const password = document.getElementById('inputPassword').value.trim();
            const confirmPassword = document.getElementById('inputConfirmPassword').value.trim();
            const termsAgreed = document.getElementById('termsCheck').checked;
            
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
            
            // Student ID validation
            if (!studentID) {
                displayError('inputStudentID', 'Student ID is required');
                isValid = false;
            }
            
            // Department validation
            if (!department) {
                displayError('inputDepartment', 'Please select your department');
                isValid = false;
            }
            
            // Year of Study validation
            if (!yearOfStudy) {
                displayError('inputYearOfStudy', 'Please select your year of study');
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
            
            // Terms agreement validation
            if (!termsAgreed) {
                displayError('termsCheck', 'You must agree to the terms and conditions');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    }
    
    // Student Password Reset Form Validation
    const studentPasswordResetForm = document.getElementById('studentPasswordResetForm');
    if (studentPasswordResetForm) {
        studentPasswordResetForm.addEventListener('submit', function(event) {
            let isValid = true;
            const email = document.getElementById('inputEmailAddress').value.trim();
            const studentID = document.getElementById('inputStudentID').value.trim();
            
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
            
            // Student ID validation
            if (!studentID) {
                displayError('inputStudentID', 'Student ID is required for verification');
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