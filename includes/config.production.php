<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Production Configuration (cPanel)
 *
 * INSTRUCTIONS:
 * 1. Copy this file to config.php on the server
 * 2. Fill in your cPanel MySQL database credentials
 * 3. Update APP_URL with your actual domain
 * 4. Delete config.local.php from the server
 */

// Database credentials - UPDATE THESE
define('DB_HOST', 'localhost');
define('DB_NAME', 'auruwlzj_aurumvault');
define('DB_USER', 'auruwlzj_lyfe');
define('DB_PASSWORD', 'oCCeans3484@');

// Pagination settings
define('PAGINATION_CLIENT_INVENTORY', 25);
define('PAGINATION_ADMIN_USERS', 20);
define('PAGINATION_NOTIFICATIONS', 20);

// Session settings
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// Login security
if (!defined('LOGIN_MAX_ATTEMPTS')) {
  define('LOGIN_MAX_ATTEMPTS', 5);
}
if (!defined('LOGIN_LOCKOUT_WINDOW')) {
  define('LOGIN_LOCKOUT_WINDOW', 900);  // 15 minutes
}
if (!defined('LOGIN_LOCKOUT_DURATION')) {
  define('LOGIN_LOCKOUT_DURATION', 1800); // 30 minutes
}

// Contact form rate limiting
if (!defined('CONTACT_RATE_LIMIT')) {
  define('CONTACT_RATE_LIMIT', 3);
}
if (!defined('CONTACT_RATE_WINDOW')) {
  define('CONTACT_RATE_WINDOW', 3600); // 1 hour
}

// File upload settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_KYC_TYPES', ['pdf', 'jpg', 'png']);

// Application settings - UPDATE THESE
define('APP_NAME', 'Aurum Vault Logistics');
define('APP_URL', 'https://www.aurumvlogistics.com');
