<?php
/**
 * Admin Wire Transfer Settings
 * Allows admins to edit the bank details shown on the client payment page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/services/ContentService.php';

use GOLS\Repositories\ContentRepository;
use GOLS\Services\ContentService;

requireAdmin();

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);
$adminId = getCurrentUserId();

$successMessage = '';
$errorMessage = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Request could not be verified.';
  } else {
    $data = [
      'bank_name' => trim($_POST['bank_name'] ?? ''),
      'account_name' => trim($_POST['account_name'] ?? ''),
      'account_number' => trim($_POST['account_number'] ?? ''),
      'swift_bic' => trim($_POST['swift_bic'] ?? ''),
      'iban' => trim($_POST['iban'] ?? ''),
      'currency' => trim($_POST['currency'] ?? 'USD'),
      'additional_notes' => trim($_POST['additional_notes'] ?? ''),
    ];

    // Validate at least bank name and account number
    if (empty($data['bank_name']) || empty($data['account_number'])) {
      $errorMessage = 'Bank name and account number are required.';
    } else {
      $contentRepo->upsert('wire_transfer', $data, $adminId);
      $successMessage = 'Wire transfer details updated successfully.';
    }
  }
}

// Load current settings
$wireSettings = $contentRepo->getByPageKey('wire_transfer');
$settings = $wireSettings['content'] ?? [
  'bank_name' => '',
  'account_name' => '',
  'account_number' => '',
  'swift_bic' => '',
  'iban' => '',
  'currency' => 'USD',
  'additional_notes' => '',
];

$pageTitle = 'Wire Transfer Settings - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Wire Transfer Settings</h1>
    <a href="/admin/content.php" class="btn btn-outline-gold">Back to Content</a>
  </div>

  <?php if ($successMessage): ?>
  <div class="alert alert-success"><?= sanitizeOutput($successMessage) ?></div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
  <div class="alert alert-danger"><?= sanitizeOutput($errorMessage) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Bank Details (shown to clients on payment page)</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/wire-settings.php">
        <?= csrfField() ?>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="bank_name" class="form-label">Bank Name *</label>
            <input type="text" class="form-control" id="bank_name" name="bank_name" value="<?= sanitizeOutput($settings['bank_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label for="account_name" class="form-label">Account Name</label>
            <input type="text" class="form-control" id="account_name" name="account_name" value="<?= sanitizeOutput($settings['account_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label for="account_number" class="form-label">Account Number *</label>
            <input type="text" class="form-control" id="account_number" name="account_number" value="<?= sanitizeOutput($settings['account_number'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label for="swift_bic" class="form-label">SWIFT / BIC Code</label>
            <input type="text" class="form-control" id="swift_bic" name="swift_bic" value="<?= sanitizeOutput($settings['swift_bic'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label for="iban" class="form-label">IBAN</label>
            <input type="text" class="form-control" id="iban" name="iban" value="<?= sanitizeOutput($settings['iban'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label for="currency" class="form-label">Currency</label>
            <input type="text" class="form-control" id="currency" name="currency" value="<?= sanitizeOutput($settings['currency'] ?? 'USD') ?>">
          </div>
          <div class="col-12">
            <label for="additional_notes" class="form-label">Additional Notes</label>
            <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" placeholder="e.g. Processing time, intermediary bank details..."><?= sanitizeOutput($settings['additional_notes'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-gold">Save Wire Transfer Details</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
