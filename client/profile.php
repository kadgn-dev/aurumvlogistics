<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Profile Page
 *
 * Allows clients to update their profile information (name, phone, email),
 * change their password, view KYC status, and navigate to KYC upload.
 *
 * Requirements: 12.1, 12.2, 12.3, 12.4
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/validators/UserValidator.php';
require_once __DIR__ . '/../includes/services/AuthService.php';
require_once __DIR__ . '/../includes/services/RateLimiter.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/Result.php';

use GOLS\Repositories\UserRepository;
use GOLS\Validators\UserValidator;
use GOLS\Services\AuthService;
use GOLS\Services\RateLimiter;

// Require client authentication
requireClient();

$pdo = getDbConnection();
$userRepository = new UserRepository($pdo);
$userValidator = new UserValidator();
$rateLimiter = new RateLimiter($pdo);
$authService = new AuthService($userRepository, $userValidator, $rateLimiter, $pdo);

$userId = getCurrentUserId();
$user = $userRepository->findById($userId);

$profileSuccess = '';
$profileErrors = [];
$passwordSuccess = '';
$passwordErrors = [];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $profileErrors = ['general' => 'Request could not be verified. Please try again.'];
  } else {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_profile') {
    // Profile update
    $data = [
      'name' => trim($_POST['name'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
      'phone' => trim($_POST['phone'] ?? ''),
    ];

    // Validate profile fields
    $validation = $userValidator->validateProfileUpdate($data, $userId);

    if (!$validation->isValid) {
      $profileErrors = $validation->errors;
    } else {
      // Check email uniqueness (excluding current user)
      $email = trim($data['email']);
      if ($userRepository->emailExists($email, $userId)) {
        $profileErrors = ['email' => 'This email address is already in use by another account.'];
      } else {
        // Update user record
        $userRepository->update($userId, [
          'name' => $data['name'],
          'email' => $data['email'],
          'phone' => $data['phone'],
        ]);

        $profileSuccess = 'Profile updated successfully.';
        // Reload user data
        $user = $userRepository->findById($userId);
      }
    }
  } elseif ($action === 'change_password') {
    // Password change
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

    if ($newPassword !== $confirmNewPassword) {
      $passwordErrors = ['confirm_new_password' => 'New password and confirmation do not match.'];
    } else {
      $result = $authService->changePassword($userId, $currentPassword, $newPassword);

      if ($result->success) {
        $passwordSuccess = 'Password changed successfully.';
      } else {
        // Map error codes to user-friendly messages
        if ($result->errorCode === 'INVALID_PASSWORD') {
          $passwordErrors = ['current_password' => 'Current password is incorrect.'];
        } elseif ($result->errorCode === 'VALIDATION_ERROR') {
          $passwordErrors = $result->errors ?? ['password' => $result->errorMessage];
        } else {
          $passwordErrors = ['password' => $result->errorMessage];
        }
      }
    }
  }
  } // end CSRF validation
}

// KYC status display mapping
$kycStatusLabels = [
  'not_submitted' => 'Not Submitted',
  'pending_review' => 'Pending Review',
  'approved'    => 'Approved',
  'rejected'    => 'Rejected',
];

$kycStatusBadges = [
  'not_submitted' => 'bg-secondary',
  'pending_review' => 'bg-warning text-dark',
  'approved'    => 'bg-success',
  'rejected'    => 'bg-danger',
];

$currentKycStatus = $user['kyc_status'] ?? 'not_submitted';
$kycLabel = $kycStatusLabels[$currentKycStatus] ?? 'Unknown';
$kycBadge = $kycStatusBadges[$currentKycStatus] ?? 'bg-secondary';

// Page setup
$pageTitle = 'My Profile - Aurum Vault Logistics';
$unreadCount = 0;

// Get unread notification count
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_status = 'unread'");
$notifStmt->execute(['user_id' => $userId]);
$unreadCount = (int) $notifStmt->fetchColumn();

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<div class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">My Profile</h1>

  <!-- KYC Status -->
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-1">KYC Verification Status</h5>
        <span class="badge <?= sanitizeOutput($kycBadge) ?>"><?= sanitizeOutput($kycLabel) ?></span>
      </div>
      <a href="/client/kyc-upload.php" class="btn btn-outline-warning btn-sm">Upload KYC Document</a>
    </div>
  </div>

  <!-- Profile Update Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Profile Information</h5>
    </div>
    <div class="card-body">
      <?php if ($profileSuccess): ?>
        <div class="alert alert-success" role="alert"><?= sanitizeOutput($profileSuccess) ?></div>
      <?php endif; ?>
      <?php if (!empty($profileErrors)): ?>
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0">
            <?php foreach ($profileErrors as $field => $error): ?>
              <li><?= sanitizeOutput($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="/client/profile.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="mb-3">
          <label for="name" class="form-label">Full Name</label>
          <input type="text" class="form-control" id="name" name="name"
              value="<?= sanitizeOutput($user['name'] ?? '') ?>" required minlength="2" maxlength="100">
        </div>

        <div class="mb-3">
          <label for="phone" class="form-label">Phone Number</label>
          <input type="tel" class="form-control" id="phone" name="phone"
              value="<?= sanitizeOutput($user['phone'] ?? '') ?>" required pattern="\d{10,15}">
          <div class="form-text text-secondary">10 to 15 digits only</div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" class="form-control" id="email" name="email"
              value="<?= sanitizeOutput($user['email'] ?? '') ?>" required>
        </div>

        <button type="submit" class="btn btn-warning">Update Profile</button>
      </form>
    </div>
  </div>

  <!-- Password Change Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Change Password</h5>
    </div>
    <div class="card-body">
      <?php if ($passwordSuccess): ?>
        <div class="alert alert-success" role="alert"><?= sanitizeOutput($passwordSuccess) ?></div>
      <?php endif; ?>
      <?php if (!empty($passwordErrors)): ?>
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0">
            <?php foreach ($passwordErrors as $field => $error): ?>
              <li><?= sanitizeOutput($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="/client/profile.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="mb-3">
          <label for="current_password" class="form-label">Current Password</label>
          <input type="password" class="form-control" id="current_password" name="current_password" required>
        </div>

        <div class="mb-3">
          <label for="new_password" class="form-label">New Password</label>
          <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" maxlength="72">
          <div class="form-text text-secondary">8-72 characters, must include uppercase, lowercase, digit, and special character</div>
        </div>

        <div class="mb-3">
          <label for="confirm_new_password" class="form-label">Confirm New Password</label>
          <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
        </div>

        <button type="submit" class="btn btn-warning">Change Password</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
