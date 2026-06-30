<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Application Configuration
 */

// Database credentials
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'goldbodvault');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');

// Pagination settings
if (!defined('PAGINATION_CLIENT_INVENTORY')) define('PAGINATION_CLIENT_INVENTORY', 25);
if (!defined('PAGINATION_ADMIN_USERS')) define('PAGINATION_ADMIN_USERS', 20);
if (!defined('PAGINATION_NOTIFICATIONS')) define('PAGINATION_NOTIFICATIONS', 20);

// Session settings
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

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
if (!defined('UPLOAD_MAX_SIZE')) define('UPLOAD_MAX_SIZE', 5242880); // 5MB
if (!defined('ALLOWED_KYC_TYPES')) define('ALLOWED_KYC_TYPES', ['pdf', 'jpg', 'png']);

// Application settings
if (!defined('APP_NAME')) define('APP_NAME', 'Aurum Vault Logistics');
if (!defined('APP_URL')) define('APP_URL', 'https://www.aurumvlogistics.com');
