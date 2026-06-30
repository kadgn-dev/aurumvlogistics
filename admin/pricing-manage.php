<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Pricing Content Management - Edit Pricing Page Content
 *
 * Edit pricing content (service names, prices, plan descriptions)
 * displayed on the public pricing page.
 *
 * Requirements: 17.5, 17.6
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

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);
$contentService = new ContentService($contentRepo);

$adminId = getCurrentUserId();
$successMessage = '';
$errorMessage = '';
$formErrors = [];

// Handle POST - update pricing content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $data = [
    'service_names' => trim($_POST['service_names'] ?? ''),
    'prices' => trim($_POST['prices'] ?? ''),
    'plan_descriptions' => trim($_POST['plan_descriptions'] ?? ''),
  ];

  $result = $contentService->updatePricingContent($data, $adminId);
  if ($result->success) {
    $successMessage = 'Pricing content updated successfully.';
  } else {
    if ($result->errors) {
      $formErrors = $result->errors;
    }
    $errorMessage = $result->errorMessage ?? 'Failed to update pricing content.';
  }
}

// Load current pricing content
$pricingContent = $contentService->getPricingContent();

$pageTitle = 'Pricing Content - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0" style="color: #c9a227;">Pricing Content</h1>
    <a href="/admin/content.php" class="btn btn-outline-secondary">Back to Content</a>
  </div>

  <?php if ($successMessage): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= sanitizeOutput($successMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= sanitizeOutput($errorMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>

  <!-- Pricing Content Form -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Edit Pricing Page Content</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/pricing-manage.php">
        <?= csrfField() ?>

        <div class="mb-4">
          <label for="service_names" class="form-label text-secondary">Service Names</label>
          <textarea class="form-control" id="service_names" name="service_names" rows="4" required><?= sanitizeOutput($pricingContent['service_names'] ?? '') ?></textarea>
          <div class="form-text text-secondary">Names of pricing tiers or services (e.g., Basic Storage, Premium Storage, Insured Shipping).</div>
          <?php if (isset($formErrors['service_names'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['service_names']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <label for="prices" class="form-label text-secondary">Prices</label>
          <textarea class="form-control" id="prices" name="prices" rows="4" required><?= sanitizeOutput($pricingContent['prices'] ?? '') ?></textarea>
          <div class="form-text text-secondary">Pricing details for each service tier.</div>
          <?php if (isset($formErrors['prices'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['prices']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <label for="plan_descriptions" class="form-label text-secondary">Plan Descriptions</label>
          <textarea class="form-control" id="plan_descriptions" name="plan_descriptions" rows="6" required><?= sanitizeOutput($pricingContent['plan_descriptions'] ?? '') ?></textarea>
          <div class="form-text text-secondary">Detailed descriptions of what each plan includes.</div>
          <?php if (isset($formErrors['plan_descriptions'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['plan_descriptions']) ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-outline-light">Save Pricing Content</button>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
