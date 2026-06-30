<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Login Page
 *
 * Handles user authentication with CSRF protection, rate limiting,
 * account lockout display, and role-based redirect.
 *
 * Requirements: 2.1, 2.2, 2.5, 2.6, 2.7, 2.8
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/services/AuthService.php';
require_once __DIR__ . '/../includes/services/RateLimiter.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/validators/UserValidator.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';

use GOLS\Services\AuthService;
use GOLS\Services\RateLimiter;
use GOLS\Repositories\UserRepository;
use GOLS\Validators\UserValidator;

// Initialize session
initSession();

// If already authenticated, redirect to dashboard
if (isAuthenticated()) {
  $redirectUrl = isAdmin() ? '/admin/dashboard.php' : '/client/dashboard.php';
  redirect($redirectUrl);
}

$error = '';
$email = '';
$lockoutMessage = '';

// Check for session expired message
$sessionExpired = isset($_GET['expired']) && $_GET['expired'] === '1';

// Handle POST (login attempt)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token (Requirement 2.5)
  enforceCsrf();

  $email = sanitizeInput($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

  // Validate email format and password non-empty (Requirement 2.8)
  if ($email === '' || !isValidEmail($email)) {
    $error = 'Please enter a valid email address.';
  } elseif ($password === '') {
    $error = 'Please enter your password.';
  } else {
    // Instantiate services
    $pdo = getDbConnection();
    $userRepository = new UserRepository($pdo);
    $userValidator = new UserValidator();
    $rateLimiter = new RateLimiter($pdo);
    $authService = new AuthService($userRepository, $userValidator, $rateLimiter, $pdo);

    // Call AuthService::login (Requirements 2.1, 2.6, 2.7)
    $result = $authService->login($email, $password, $ip);

    if ($result->success) {
      // Check for intended URL (post-login redirect)
      $intendedUrl = getIntendedUrl();
      $redirectUrl = $intendedUrl ?? ($result->data['redirect'] ?? '/client/dashboard.php');
      redirect($redirectUrl);
    } else {
      // Handle error codes
      switch ($result->errorCode) {
        case 'ACCOUNT_LOCKED':
          // Display lockout message with remaining time (Requirement 2.6, 2.7)
          $lockoutMessage = $result->errorMessage;
          break;

        case 'ACCOUNT_SUSPENDED':
          $error = 'Your account has been suspended. Please contact support.';
          break;

        case 'INVALID_CREDENTIALS':
        default:
          // Generic error message (Requirement 2.2)
          $error = 'Invalid email or password.';
          break;
      }
    }
  }
}

// Page setup
$pageTitle = 'Login - Aurum Vault Logistics';

// Include templates
require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card">
        <div class="card-body p-4">
          <h1 class="card-title text-center mb-4" style="color: #c9a227;">Sign In</h1>

          <?php if ($sessionExpired): ?>
            <div class="alert alert-warning" role="alert">
              <?= sanitizeOutput('Your session has expired. Please log in again.') ?>
            </div>
          <?php endif; ?>

          <?php if ($lockoutMessage !== ''): ?>
            <div class="alert alert-danger" role="alert">
              <?= sanitizeOutput($lockoutMessage) ?>
            </div>
          <?php endif; ?>

          <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
              <?= sanitizeOutput($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="/login.php" novalidate>
            <?= csrfField() ?>

            <div class="mb-3">
              <label for="email" class="form-label">Email Address</label>
              <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                value="<?= sanitizeOutput($email) ?>"
                required
                autocomplete="email"
                placeholder="Enter your email"
              >
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Enter your password"
              >
            </div>

            <div class="d-grid mb-3">
              <button type="submit" class="btn" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">
                Sign In
              </button>
            </div>
          </form>

          <div class="text-center">
            <a href="#" class="text-secondary small">Forgot your password?</a>
          </div>

          <hr class="my-4">

          <p class="text-center text-secondary mb-0">
            Don't have an account?
            <a href="/register.php" style="color: #c9a227;">Create one here</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</main>

<?php
require_once __DIR__ . '/../includes/templates/footer.php';
?>
