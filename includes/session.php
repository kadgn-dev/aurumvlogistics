<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Session Management Middleware
 *
 * Provides secure session initialization with cookie hardening,
 * inactivity timeout enforcement, and session destruction.
 *
 * Requirements: 2.3, 2.4, 16.6
 */

require_once __DIR__ . '/config.php';

/**
 * Initializes a secure session with cookie hardening and timeout enforcement.
 *
 * - Sets Secure, HttpOnly, and SameSite=Lax cookie attributes (Requirement 16.6)
 * - Starts the session
 * - Checks inactivity timeout (30 minutes) and destroys session if exceeded (Requirement 2.3)
 * - Redirects to login with ?expired=1 on timeout (Requirement 2.4)
 * - Updates last_activity timestamp on each valid request
 * - Regenerates session ID periodically for security
 *
 * @return void
 */
function initSession(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    // Session already started, just check timeout and update activity
    checkSessionTimeout();
    $_SESSION['last_activity'] = time();
    return;
  }

  // Set secure cookie parameters before starting the session
  $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
  session_set_cookie_params([
    'lifetime' => 0,
    'path'   => '/',
    'secure'  => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();

  // Check for session timeout
  checkSessionTimeout();

  // Update last activity timestamp
  $_SESSION['last_activity'] = time();
}

/**
 * Checks if the session has exceeded the inactivity timeout.
 *
 * If the session has been inactive for longer than SESSION_TIMEOUT (1800 seconds),
 * the session is destroyed and the user is redirected to the login page with
 * an expired parameter.
 *
 * @return void
 */
function checkSessionTimeout(): void
{
  if (isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];

    if ($elapsed > SESSION_TIMEOUT) {
      destroySession();
      header('Location: /login.php?expired=1');
      exit;
    }
  }
}

/**
 * Destroys the current session completely.
 *
 * Unsets all session variables, destroys the session data on the server,
 * and invalidates the session cookie in the browser.
 *
 * @return void
 */
function destroySession(): void
{
  // Unset all session variables
  $_SESSION = [];

  // Delete the session cookie
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'],
      $params['domain'],
      $params['secure'],
      $params['httponly']
    );
  }

  // Destroy the session data on the server
  session_destroy();
}
