<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Registration Page
 *
 * Allows guests to create a new client account.
 * Validates input via AuthService::register(), sends confirmation email,
 * and displays success/error messages.
 *
 * Requirements: 1.1, 1.2, 1.4, 1.5, 1.6, 1.7
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

use GOLS\Repositories\UserRepository;
use GOLS\Services\AuthService;
use GOLS\Services\EmailService;
use GOLS\Services\RateLimiter;
use GOLS\Validators\UserValidator;

// Start session for CSRF token
initSession();

$pageTitle = 'Register - Aurum Vault Logistics';
$errors = [];
$successMessage = '';
$formData = [
  'name' => '',
  'email' => '',
  'phone' => '',
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  enforceCsrf();

  // Collect form data
  $formData['name'] = sanitizeInput($_POST['name'] ?? '');
  $formData['email'] = sanitizeInput($_POST['email'] ?? '');
  $formData['phone'] = sanitizeInput($_POST['phone'] ?? '');
  $password     = $_POST['password'] ?? '';
  $confirmPassword  = $_POST['confirm_password'] ?? '';

  // Check password confirmation match before calling service
  if ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
  }

  if (empty($errors)) {
    // Build service dependencies
    $pdo      = getDbConnection();
    $userRepository = new UserRepository($pdo);
    $userValidator = new UserValidator();
    $rateLimiter  = new RateLimiter($pdo);
    $authService  = new AuthService($userRepository, $userValidator, $rateLimiter, $pdo);

    // Attempt registration
    $result = $authService->register([
      'name'   => $formData['name'],
      'email'  => $formData['email'],
      'phone'  => $formData['phone'],
      'password' => $password,
    ]);

    if ($result->success) {
      // Send confirmation email
      $emailService = new EmailService($pdo);
      $emailService->sendWithRetry(
        $formData['email'],
        'Welcome to Aurum Vault Logistics - Registration Confirmation',
        'registration.html',
        [
          'name' => $formData['name'],
          'email' => $formData['email'],
        ]
      );

      $successMessage = 'Your account has been created successfully! A confirmation email has been sent to your email address.';
      // Clear form data on success
      $formData = ['name' => '', 'email' => '', 'phone' => ''];
    } elseif ($result->errorCode === 'EMAIL_EXISTS') {
      $errors['email'] = 'This email address is already registered.';
    } elseif ($result->errorCode === 'VALIDATION_ERROR' && !empty($result->errors)) {
      $errors = $result->errors;
    } else {
      $errors['general'] = $result->errorMessage ?? 'An unexpected error occurred. Please try again.';
    }
  }
}

// Include templates
require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Registration Form -->
<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <h1 class="text-center mb-4" style="color: #c9a227;">Create Account</h1>
      <p class="text-center text-secondary mb-4">Join Aurum Vault Logistics to access secure storage and insured shipping services.</p>

      <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert">
          <?= sanitizeOutput($successMessage) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" role="alert">
          <?= sanitizeOutput($errors['general']) ?>
        </div>
      <?php endif; ?>

      <?php if (empty($successMessage)): ?>
      <form method="POST" action="/register.php" novalidate>
        <?= csrfField() ?>

        <!-- Name -->
        <div class="mb-3">
          <label for="name" class="form-label">Full Name</label>
          <input
            type="text"
            class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
            id="name"
            name="name"
            value="<?= sanitizeOutput($formData['name']) ?>"
            required
            minlength="2"
            maxlength="100"
            autocomplete="name"
          >
          <?php if (isset($errors['name'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($errors['name']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <input
            type="email"
            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
            id="email"
            name="email"
            value="<?= sanitizeOutput($formData['email']) ?>"
            required
            autocomplete="email"
          >
          <?php if (isset($errors['email'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($errors['email']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Phone -->
        <div class="mb-3">
          <label for="phone" class="form-label">Phone Number</label>
          <input
            type="tel"
            class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
            id="phone"
            name="phone"
            value="<?= sanitizeOutput($formData['phone']) ?>"
            required
            pattern="\d{10,15}"
            minlength="10"
            maxlength="15"
            autocomplete="tel"
            placeholder="10-15 digits"
          >
          <?php if (isset($errors['phone'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($errors['phone']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input
            type="password"
            class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
            id="password"
            name="password"
            required
            minlength="8"
            maxlength="72"
            autocomplete="new-password"
          >
          <?php if (isset($errors['password'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($errors['password']) ?></div>
          <?php endif; ?>
          <div class="form-text text-secondary">
            8-72 characters. Must include uppercase, lowercase, digit, and special character.
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <input
            type="password"
            class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
            id="confirm_password"
            name="confirm_password"
            required
            minlength="8"
            maxlength="72"
            autocomplete="new-password"
          >
          <?php if (isset($errors['confirm_password'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($errors['confirm_password']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Submit -->
        <div class="d-grid">
          <button type="submit" class="btn btn-lg" style="background-color: #c9a227; color: #1a1a1a;">
            Create Account
          </button>
        </div>
      </form>
      <?php endif; ?>

      <!-- Login Link -->
      <p class="text-center mt-4 text-secondary">
        Already have an account? <a href="/login.php" style="color: #c9a227;">Sign in</a>
      </p>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
