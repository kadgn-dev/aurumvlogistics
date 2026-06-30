/**
 * Aurum Vault Logistics Platform (AVL)
 * Progressive Enhancement Form Validation
 *
 * Provides real-time client-side form validation using Bootstrap's
 * is-invalid/invalid-feedback classes. Works with all platform forms:
 * registration, login, contact, profile, and shipment request.
 *
 * Requirements: 1.5, 6.5, 14.4
 */

(function () {
    'use strict';

    /**
     * Validation rules keyed by field name or input type.
     * Each rule returns an error message string or empty string if valid.
     */
    var ValidationRules = {
        /**
         * Check if a required field has a value.
         */
        required: function (value) {
            return value.trim() === '' ? 'This field is required.' : '';
        },

        /**
         * Validate email format.
         */
        email: function (value) {
            if (value.trim() === '') return '';
            var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(value) ? '' : 'Please enter a valid email address.';
        },

        /**
         * Validate phone format (10-15 digits only).
         */
        phone: function (value) {
            if (value.trim() === '') return '';
            var digitsOnly = value.replace(/[\s\-\(\)\+]/g, '');
            if (!/^\d+$/.test(digitsOnly)) {
                return 'Phone number must contain only digits.';
            }
            if (digitsOnly.length < 10 || digitsOnly.length > 15) {
                return 'Phone number must be between 10 and 15 digits.';
            }
            return '';
        },

        /**
         * Validate password length (8-72 characters).
         */
        passwordLength: function (value) {
            if (value === '') return '';
            if (value.length < 8) {
                return 'Password must be at least 8 characters.';
            }
            if (value.length > 72) {
                return 'Password must not exceed 72 characters.';
            }
            return '';
        },

        /**
         * Validate password complexity:
         * 1 uppercase, 1 lowercase, 1 digit, 1 special character.
         */
        passwordComplexity: function (value) {
            if (value === '') return '';
            var errors = [];
            if (!/[A-Z]/.test(value)) errors.push('one uppercase letter');
            if (!/[a-z]/.test(value)) errors.push('one lowercase letter');
            if (!/[0-9]/.test(value)) errors.push('one digit');
            if (!/[^A-Za-z0-9]/.test(value)) errors.push('one special character');
            if (errors.length > 0) {
                return 'Password must contain at least ' + errors.join(', ') + '.';
            }
            return '';
        }
    };

    /**
     * Determine which validation rules apply to a given input field.
     */
    function getRulesForField(field) {
        var rules = [];
        var name = (field.name || '').toLowerCase();
        var type = (field.type || '').toLowerCase();

        // Required check
        if (field.hasAttribute('required')) {
            rules.push(ValidationRules.required);
        }

        // Email validation
        if (type === 'email' || name === 'email') {
            rules.push(ValidationRules.email);
        }

        // Phone validation
        if (name === 'phone' || name === 'telephone' || type === 'tel') {
            rules.push(ValidationRules.phone);
        }

        // Password validation
        if (type === 'password' && name !== 'current_password') {
            rules.push(ValidationRules.passwordLength);
            rules.push(ValidationRules.passwordComplexity);
        }

        return rules;
    }

    /**
     * Validate a single field and return the first error message (or empty string).
     */
    function validateField(field) {
        var rules = getRulesForField(field);
        var value = field.value;

        for (var i = 0; i < rules.length; i++) {
            var error = rules[i](value);
            if (error) return error;
        }

        return '';
    }

    /**
     * Show validation error on a field using Bootstrap classes.
     */
    function showError(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');

        // Find or create the invalid-feedback element
        var feedback = field.parentElement.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentElement.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    /**
     * Clear validation error from a field.
     */
    function clearError(field) {
        field.classList.remove('is-invalid');

        // Only add is-valid if the field has been interacted with and has a value
        if (field.value.trim() !== '') {
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
        }

        var feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
        }
    }

    /**
     * Handle real-time validation on a single field.
     */
    function handleFieldValidation(field) {
        var error = validateField(field);
        if (error) {
            showError(field, error);
        } else {
            clearError(field);
        }
    }

    /**
     * Validate all fields in a form. Returns true if all valid.
     */
    function validateForm(form) {
        var fields = form.querySelectorAll('input, textarea, select');
        var isValid = true;

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var type = (field.type || '').toLowerCase();

            // Skip hidden, submit, button, and CSRF token fields
            if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') {
                continue;
            }

            var error = validateField(field);
            if (error) {
                showError(field, error);
                isValid = false;
            } else {
                clearError(field);
            }
        }

        return isValid;
    }

    /**
     * Initialize validation on a single form.
     */
    function initForm(form) {
        // Skip forms that opt out of JS validation
        if (form.hasAttribute('data-no-validate')) return;

        var fields = form.querySelectorAll('input, textarea, select');

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var type = (field.type || '').toLowerCase();

            // Skip hidden, submit, button fields
            if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') {
                continue;
            }

            // Validate on blur
            field.addEventListener('blur', function () {
                handleFieldValidation(this);
            });

            // Validate on input (for real-time feedback after first blur)
            field.addEventListener('input', function () {
                // Only show real-time validation if field was already marked invalid
                if (this.classList.contains('is-invalid')) {
                    handleFieldValidation(this);
                }
            });
        }

        // Prevent form submission when fields are invalid
        form.addEventListener('submit', function (e) {
            if (!validateForm(this)) {
                e.preventDefault();
                e.stopPropagation();

                // Focus the first invalid field
                var firstInvalid = this.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    }

    /**
     * Initialize validation on all forms when DOM is ready.
     */
    function init() {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) {
            initForm(forms[i]);
        }
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
