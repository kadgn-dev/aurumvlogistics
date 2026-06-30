/**
 * Aurum Vault Logistics Platform (AVL)
 * Payment Gateway Redirect Logic
 *
 * Handles gateway selection (enable/disable submit button),
 * redirect initiation with loading state, timeout handling UI
 * (shows countdown/timeout message after 60 seconds), and
 * prevents double-submit by disabling the form after submission.
 *
 * Requirements: 18.1, 18.4
 */

(function () {
    'use strict';

    var PAYMENT_TIMEOUT = 60; // seconds

    /**
     * Initialize payment page functionality.
     */
    function init() {
        var form = document.querySelector('form[action*="payment.php"]');
        if (!form) return;

        var submitBtn = form.querySelector('button[type="submit"]');
        var gatewayInputs = form.querySelectorAll('input[name="gateway"]');

        if (!submitBtn || gatewayInputs.length === 0) return;

        // Disable submit button until a gateway is selected
        submitBtn.disabled = true;

        // Enable submit button when a gateway is selected
        for (var i = 0; i < gatewayInputs.length; i++) {
            gatewayInputs[i].addEventListener('change', function () {
                submitBtn.disabled = false;
            });
        }

        // Handle form submission
        form.addEventListener('submit', function (e) {
            // Check if a gateway is selected
            var selectedGateway = form.querySelector('input[name="gateway"]:checked');
            if (!selectedGateway) {
                e.preventDefault();
                return;
            }

            // Prevent double-submit
            if (form.dataset.submitted === 'true') {
                e.preventDefault();
                return;
            }

            // Mark form as submitted
            form.dataset.submitted = 'true';

            // Disable all form inputs and the submit button
            disableForm(form, submitBtn);

            // Show loading state
            showLoadingState(submitBtn);

            // Start timeout countdown
            startTimeoutCountdown(form);
        });
    }

    /**
     * Disable all form elements to prevent double-submit.
     */
    function disableForm(form, submitBtn) {
        var inputs = form.querySelectorAll('input, button, select, textarea');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = true;
        }
        submitBtn.disabled = true;
    }

    /**
     * Show loading state on the submit button.
     */
    function showLoadingState(submitBtn) {
        submitBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            'Redirecting to payment gateway...';
    }

    /**
     * Start a 60-second timeout countdown.
     * If the page hasn't redirected after 60 seconds, show a timeout message.
     */
    function startTimeoutCountdown(form) {
        var remaining = PAYMENT_TIMEOUT;
        var countdownEl = createCountdownElement();

        // Insert countdown after the form
        form.parentElement.insertBefore(countdownEl, form.nextSibling);

        var timer = setInterval(function () {
            remaining--;

            if (remaining <= 0) {
                clearInterval(timer);
                showTimeoutMessage(countdownEl, form);
            } else {
                countdownEl.querySelector('.countdown-text').textContent =
                    'Waiting for gateway response... (' + remaining + 's)';
            }
        }, 1000);
    }

    /**
     * Create the countdown display element.
     */
    function createCountdownElement() {
        var el = document.createElement('div');
        el.className = 'mt-3';
        el.innerHTML =
            '<div class="text-secondary small">' +
            '<span class="countdown-text">Waiting for gateway response... (' + PAYMENT_TIMEOUT + 's)</span>' +
            '</div>';
        return el;
    }

    /**
     * Show timeout message and allow retry.
     */
    function showTimeoutMessage(countdownEl, form) {
        countdownEl.innerHTML =
            '<div class="alert alert-warning mt-3" role="alert">' +
            '<strong>Payment gateway timeout.</strong> ' +
            'The payment gateway did not respond within 60 seconds. ' +
            'Your payment may still be processing. ' +
            '<hr>' +
            '<button type="button" class="btn btn-outline-warning btn-sm" id="payment-retry-btn">' +
            'Retry Payment' +
            '</button>' +
            '</div>';

        // Handle retry button
        var retryBtn = document.getElementById('payment-retry-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', function () {
                // Re-enable the form for retry
                form.dataset.submitted = 'false';
                var inputs = form.querySelectorAll('input, button, select, textarea');
                for (var i = 0; i < inputs.length; i++) {
                    inputs[i].disabled = false;
                }

                // Restore submit button text
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = 'Proceed to Payment';
                    submitBtn.disabled = false;
                }

                // Remove the timeout message
                countdownEl.remove();
            });
        }
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
