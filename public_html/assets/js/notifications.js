/**
 * Aurum Vault Logistics Platform (AVL)
 * Notification Badge Polling
 *
 * Periodically polls the server for unread notification count
 * and updates the notification badge in the navigation bar.
 * Polls every 30 seconds. Hides badge when count is 0,
 * displays "99+" when count exceeds 99.
 *
 * Requirements: 13.4
 */

(function () {
    'use strict';

    var POLL_INTERVAL = 30000; // 30 seconds
    var API_ENDPOINT = '/client/api/notification-count.php';
    var pollTimer = null;

    /**
     * Find the notification badge element in the navigation.
     * Looks for the badge inside the Notifications link.
     */
    function findBadgeContainer() {
        // Find the nav link that contains "Notifications" text
        var navLinks = document.querySelectorAll('.nav-link');
        for (var i = 0; i < navLinks.length; i++) {
            if (navLinks[i].textContent.trim().indexOf('Notifications') !== -1) {
                return navLinks[i];
            }
        }
        return null;
    }

    /**
     * Update the notification badge with the given count.
     * Hides badge when count is 0, shows "99+" when count > 99.
     */
    function updateBadge(count) {
        var container = findBadgeContainer();
        if (!container) return;

        var badge = container.querySelector('.badge');

        if (count <= 0) {
            // Hide badge when no unread notifications
            if (badge) {
                badge.style.display = 'none';
            }
            return;
        }

        var displayText = count > 99 ? '99+' : String(count);

        if (badge) {
            // Update existing badge
            badge.style.display = '';
            badge.childNodes[0].textContent = displayText;
        } else {
            // Create badge if it doesn't exist
            badge = document.createElement('span');
            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill';
            badge.style.backgroundColor = '#c9a227';
            badge.style.color = '#1a1a1a';
            badge.textContent = displayText;

            var srText = document.createElement('span');
            srText.className = 'visually-hidden';
            srText.textContent = 'unread notifications';
            badge.appendChild(srText);

            container.classList.add('position-relative');
            container.appendChild(badge);
        }
    }

    /**
     * Fetch the unread notification count from the API.
     */
    function fetchNotificationCount() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', API_ENDPOINT, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (typeof response.count === 'number') {
                            updateBadge(response.count);
                        }
                    } catch (e) {
                        // Silently ignore parse errors
                    }
                }
                // Silently ignore HTTP errors (user may have logged out)
            }
        };

        xhr.send();
    }

    /**
     * Start polling for notification count.
     */
    function startPolling() {
        // Fetch immediately on load
        fetchNotificationCount();

        // Then poll every 30 seconds
        pollTimer = setInterval(fetchNotificationCount, POLL_INTERVAL);
    }

    /**
     * Stop polling (e.g., when page is hidden).
     */
    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    /**
     * Handle page visibility changes to pause/resume polling.
     */
    function handleVisibilityChange() {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    }

    /**
     * Initialize notification polling.
     * Only runs on pages that have the client navigation (authenticated pages).
     */
    function init() {
        // Only poll if we're on a page with the client nav
        var container = findBadgeContainer();
        if (!container) return;

        startPolling();

        // Pause polling when tab is not visible
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
