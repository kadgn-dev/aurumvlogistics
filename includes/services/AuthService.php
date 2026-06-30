<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\UserRepository;
use GOLS\Result;
use GOLS\ValidationResult;
use GOLS\Validators\UserValidator;
use PDO;

/**
 * Aurum Vault Logistics Platform (AVL)
 * AuthService - Handles user registration, authentication, session management,
 * account lockout, and password changes.
 *
 * Requirements: 1.1, 1.3, 2.1, 2.2, 2.6, 2.7, 12.3, 12.4
 */
class AuthService
{
  private UserRepository $userRepository;
  private UserValidator $userValidator;
  private RateLimiter $rateLimiter;
  private PDO $pdo;

  public function __construct(
    UserRepository $userRepository,
    UserValidator $userValidator,
    RateLimiter $rateLimiter,
    PDO $pdo
  ) {
    $this->userRepository = $userRepository;
    $this->userValidator = $userValidator;
    $this->rateLimiter = $rateLimiter;
    $this->pdo = $pdo;
  }

  /**
   * Register a new user account.
   *
   * Validates input, checks email uniqueness, hashes password with bcrypt,
   * and creates user with role=client, status=pending.
   *
   * @param array $data Expected keys: name, email, phone, password
   * @return Result Success with user_id, or validation/error result
   */
  public function register(array $data): Result
  {
    // Validate registration input
    $validation = $this->userValidator->validateRegistration($data);

    if (!$validation->isValid) {
      return Result::validationError($validation->errors);
    }

    // Check email uniqueness
    $email = trim((string) $data['email']);
    if ($this->userRepository->emailExists($email)) {
      return Result::error('EMAIL_EXISTS', 'This email address is already registered.');
    }

    // Hash password with bcrypt
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

    // Create user record
    $userId = $this->userRepository->create([
      'name'     => trim((string) $data['name']),
      'email'     => $email,
      'phone'     => trim((string) $data['phone']),
      'password_hash' => $passwordHash,
      'role'     => 'client',
      'status'    => 'pending',
    ]);

    return Result::success(['user_id' => $userId]);
  }

  /**
   * Authenticate a user with email and password.
   *
   * Checks account lockout, validates credentials, creates session,
   * and returns redirect URL based on role.
   *
   * @param string $email  User email
   * @param string $password User password
   * @param string $ip    Client IP address for rate limiting
   * @return Result Success with redirect URL, or error result
   */
  public function login(string $email, string $password, string $ip): Result
  {
    // Check if account is locked due to too many failed attempts
    if ($this->isLocked($email)) {
      $remaining = $this->rateLimiter->getRemainingTime($ip, 'login');
      $minutes = (int) ceil($remaining / 60);

      return Result::error(
        'ACCOUNT_LOCKED',
        "Account is temporarily locked. Please try again in {$minutes} minute(s)."
      );
    }

    // Find user by email
    $user = $this->userRepository->findByEmail($email);

    if ($user === null) {
      $this->recordFailedAttempt($email, $ip);
      return Result::error('INVALID_CREDENTIALS', 'Invalid email or password.');
    }

    // Check if account is suspended
    if ($user['status'] === 'suspended') {
      return Result::error('ACCOUNT_SUSPENDED', 'Your account has been suspended. Please contact support.');
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
      $this->recordFailedAttempt($email, $ip);
      return Result::error('INVALID_CREDENTIALS', 'Invalid email or password.');
    }

    // Successful login - reset failed attempts
    $this->resetFailedAttempts($email);

    // Create session
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    // Determine redirect URL based on role
    $redirectUrl = $user['role'] === 'admin'
      ? '/admin/dashboard.php'
      : '/client/dashboard.php';

    return Result::success(['redirect' => $redirectUrl]);
  }

  /**
   * Destroy the current session and log the user out.
   *
   * @return void
   */
  public function logout(): void
  {
    destroySession();
  }

  /**
   * Check if an account is locked due to too many failed login attempts.
   *
   * An account is locked if there are 5 or more failed attempts
   * within the last 15 minutes.
   *
   * @param string $email The email to check
   * @return bool True if the account is locked
   */
  public function isLocked(string $email): bool
  {
    $windowStart = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_WINDOW);

    $stmt = $this->pdo->prepare(
      'SELECT COUNT(*) AS attempt_count
       FROM login_attempts
       WHERE email = :email
        AND success = 0
        AND attempted_at > :window_start'
    );
    $stmt->execute([
      ':email' => $email,
      ':window_start' => $windowStart,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int) ($row['attempt_count'] ?? 0);

    return $count >= LOGIN_MAX_ATTEMPTS;
  }

  /**
   * Record a failed login attempt in the login_attempts table.
   *
   * @param string $email The email that failed authentication
   * @param string $ip  The IP address of the request
   * @return void
   */
  public function recordFailedAttempt(string $email, string $ip): void
  {
    $stmt = $this->pdo->prepare(
      'INSERT INTO login_attempts (email, ip_address, success, attempted_at)
       VALUES (:email, :ip, 0, :attempted_at)'
    );
    $stmt->execute([
      ':email' => $email,
      ':ip' => $ip,
      ':attempted_at' => date('Y-m-d H:i:s'),
    ]);

    // Also record in rate limiter for IP-based limiting
    $this->rateLimiter->recordAttempt($ip, 'login');
  }

  /**
   * Clear all failed login attempts for an email after successful login.
   *
   * @param string $email The email to clear attempts for
   * @return void
   */
  public function resetFailedAttempts(string $email): void
  {
    $stmt = $this->pdo->prepare(
      'DELETE FROM login_attempts WHERE email = :email'
    );
    $stmt->execute([':email' => $email]);
  }

  /**
   * Validate the current session is still active and not timed out.
   *
   * @return bool True if the session is valid
   */
  public function validateSession(): bool
  {
    if (!isset($_SESSION['user_id'])) {
      return false;
    }

    if (!isset($_SESSION['last_activity'])) {
      return false;
    }

    $elapsed = time() - $_SESSION['last_activity'];

    if ($elapsed > SESSION_TIMEOUT) {
      return false;
    }

    return true;
  }

  /**
   * Change a user's password.
   *
   * Verifies the current password, validates the new password,
   * and updates the hash in the database.
   *
   * @param int  $userId     The user ID
   * @param string $currentPassword The current password for verification
   * @param string $newPassword   The new password to set
   * @return Result Success or error result
   */
  public function changePassword(int $userId, string $currentPassword, string $newPassword): Result
  {
    // Find the user
    $user = $this->userRepository->findById($userId);

    if ($user === null) {
      return Result::error('USER_NOT_FOUND', 'User not found.');
    }

    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
      return Result::error('INVALID_PASSWORD', 'Current password is incorrect.');
    }

    // Validate new password
    $validation = $this->userValidator->validatePasswordChange(['password' => $newPassword]);

    if (!$validation->isValid) {
      return Result::validationError($validation->errors);
    }

    // Hash and update
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $this->userRepository->update($userId, ['password_hash' => $newHash]);

    return Result::success();
  }
}
