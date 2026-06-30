<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Authentication Middleware
 *
 * Provides authentication verification, role-based access control,
 * intended URL storage for post-login redirect, and helper functions
 * for checking authentication state.
 *
 * Requirements: 16.8, 7.5, 17.7
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/**
 * Requires the current user to be authenticated.
 *
 * Checks if $_SESSION['user_id'] is set. If not, stores the current
 * request URL in $_SESSION['intended_url'] for post-login redirect,
 * then redirects to the login page.
 *
 * @return void
 */
function requireAuth(): void
{
  initSession();

  if (!isset($_SESSION['user_id'])) {
    // Store the intended URL for post-login redirect (Requirement 16.8)
    $intendedUrl = $_SERVER['REQUEST_URI'] ?? '/';
    $_SESSION['intended_url'] = $intendedUrl;

    redirect('/login.php');
  }

  // Per-request suspension check (Requirements 6.1, 6.2, 6.3, 6.4)
  checkSuspensionStatus((int) $_SESSION['user_id']);
}

/**
 * Check if the authenticated user is suspended, using a 30-second session cache.
 *
 * Uses session keys 'suspension_check_at' (timestamp) and 'cached_account_status'
 * (string) to avoid querying the database on every request. If the cache is stale
 * (30+ seconds old) or missing, queries the users table for the current status.
 *
 * If the user's status is 'suspended', destroys the session and terminates with 403.
 * If the user record is not found, defaults to 'suspended' (fail-closed).
 *
 * Requirements: 6.1, 6.2, 6.3, 6.4
 *
 * @param int $userId The authenticated user's ID.
 * @return void
 */
function checkSuspensionStatus(int $userId): void
{
  $cacheKey = 'suspension_check_at';
  $statusKey = 'cached_account_status';
  $ttl = 30; // seconds

  $lastCheck = $_SESSION[$cacheKey] ?? 0;
  $elapsed = time() - $lastCheck;

  if ($elapsed < $ttl && isset($_SESSION[$statusKey])) {
    // Use cached value
    $status = $_SESSION[$statusKey];
  } else {
    // Query database for current status
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Default to 'suspended' if user not found (fail-closed)
    $status = $row['status'] ?? 'suspended';
    $_SESSION[$statusKey] = $status;
    $_SESSION[$cacheKey] = time();
  }

  if ($status === 'suspended') {
    destroySession();
    http_response_code(403);
    exit('Your account has been suspended. Please contact support.');
  }
}

/**
 * Requires the current user to have a specific role.
 *
 * Checks if the authenticated user's role matches the required role.
 * If the user is not authenticated, redirects to login.
 * If the user's role does not match, sends a 403 Forbidden response.
 *
 * @param string $role The required role ('client' or 'admin').
 * @return void
 */
function requireRole(string $role): void
{
  requireAuth();

  if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
    try {
      require_once __DIR__ . '/services/AuditService.php';
      $pdo = getDbConnection();
      $auditService = new \GOLS\Services\AuditService($pdo);
      $auditService->log('access_denied', getCurrentUserId(), null, null, ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
    } catch (\Exception $e) {
      // Fire-and-forget
    }

    http_response_code(403);
    exit('Access denied. Insufficient permissions.');
  }
}

/**
 * Requires the current user to have the admin role.
 *
 * Shortcut for requireRole('admin'). Used at the top of admin pages
 * to restrict access to administrators only (Requirement 7.5, 17.7).
 *
 * @return void
 */
function requireAdmin(): void
{
  requireRole('admin');
}

/**
 * Requires the current user to have the client role.
 *
 * Shortcut for requireRole('client'). Used at the top of client pages
 * to restrict access to authenticated clients only.
 *
 * @return void
 */
function requireClient(): void
{
  requireRole('client');
}

/**
 * Checks whether the current user is authenticated.
 *
 * @return bool True if a user_id exists in the session, false otherwise.
 */
function isAuthenticated(): bool
{
  return isset($_SESSION['user_id']);
}

/**
 * Checks whether the current user has the admin role.
 *
 * @return bool True if the user is authenticated and has the 'admin' role.
 */
function isAdmin(): bool
{
  return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Returns the current authenticated user's ID.
 *
 * @return int|null The user ID from the session, or null if not authenticated.
 */
function getCurrentUserId(): ?int
{
  return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Returns and clears the stored intended URL for post-login redirect.
 *
 * After a successful login, this function retrieves the URL the user
 * originally tried to access before being redirected to login, then
 * clears it from the session to prevent stale redirects.
 *
 * @return string|null The intended URL, or null if none was stored.
 */
function getIntendedUrl(): ?string
{
  if (isset($_SESSION['intended_url'])) {
    $url = $_SESSION['intended_url'];
    unset($_SESSION['intended_url']);
    return $url;
  }

  return null;
}
