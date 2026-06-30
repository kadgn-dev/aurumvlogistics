<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * CSRF Token Generation and Validation
 *
 * Provides protection against Cross-Site Request Forgery attacks.
 * Session must already be started before calling these functions.
 */

/**
 * Generate a new CSRF token and store it in the session.
 *
 * @return string The generated token (64 hex characters)
 */
function generateCsrfToken(): string
{
  $token = bin2hex(random_bytes(32));
  $_SESSION['csrf_token'] = $token;
  return $token;
}

/**
 * Get the current CSRF token, or generate a new one if none exists.
 *
 * @return string The current session CSRF token
 */
function getCsrfToken(): string
{
  if (empty($_SESSION['csrf_token'])) {
    return generateCsrfToken();
  }
  return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 *
 * Uses hash_equals for timing-safe comparison to prevent timing attacks.
 *
 * @param string|null $token The token submitted with the request
 * @return bool True if the token is valid, false otherwise
 */
function validateCsrfToken(?string $token): bool
{
  if ($token === null || $token === '') {
    return false;
  }

  if (empty($_SESSION['csrf_token'])) {
    return false;
  }

  return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Return an HTML hidden input field containing the CSRF token.
 *
 * @return string HTML hidden input element for embedding in forms
 */
function csrfField(): string
{
  $token = getCsrfToken();
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Enforce CSRF validation on state-changing requests (POST, PUT, DELETE).
 *
 * Call at the top of POST/PUT/DELETE handlers. If the request method is
 * not a state-changing method, this function does nothing. If validation
 * fails, execution is halted with an error message.
 *
 * @return void
 */
function enforceCsrf(): void
{
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    return;
  }

  $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

  if (!validateCsrfToken($token)) {
    try {
      require_once __DIR__ . '/db.php';
      require_once __DIR__ . '/services/AuditService.php';
      $pdo = getDbConnection();
      $auditService = new \GOLS\Services\AuditService($pdo);
      $auditService->log('csrf_failure', isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null, null, null, ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $method]);
    } catch (\Exception $e) {
      // Fire-and-forget
    }

    http_response_code(403);
    die('Request could not be verified.');
  }
}
