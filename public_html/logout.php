<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Logout Page
 *
 * Destroys the current session and redirects to the login page.
 *
 * Requirements: 2.3
 */

require_once __DIR__ . '/../includes/session.php';

// Ensure session is started before destroying
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

destroySession();

header('Location: /login.php');
exit;
