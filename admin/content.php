<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Content Management - Homepage Content Editing
 *
 * Edit homepage content (hero text, service descriptions, security highlights).
 * Links to FAQ and pricing management pages.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.6
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

// Handle POST - update homepage content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $data = [
    'hero_text' => trim($_POST['hero_text'] ?? ''),
    'service_descriptions' => trim($_POST['service_descriptions'] ?? ''),
    'security_highlights' => trim($_POST['security_highlights'] ?? ''),
  ];

  $result = $contentService->updateHomepageContent($data, $adminId);
  if ($result->success) {
    $successMessage = 'Homepage content updated successfully.';
  } else {
    if ($result->errors) {
      $formErrors = $result->errors;
    }
    $errorMessage = $result->errorMessage ?? 'Failed to update homepage content.';
  }
}

// Load current homepage content
$homepageContent = $contentService->getHomepageContent();

$pageTitle = 'Content Management - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Content Management</h1>

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

  <!-- Quick Links -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <a href="/admin/faq-manage.php" class="card text-decoration-none h-100">
        <div class="card-body text-center p-4">
          <h2 class="h5 mb-2" style="color: var(--gv-gold);">FAQ Management</h2>
          <p class="text-secondary mb-0">Add, edit, and delete FAQ entries</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="/admin/pricing-manage.php" class="card text-decoration-none h-100">
        <div class="card-body text-center p-4">
          <h2 class="h5 mb-2" style="color: var(--gv-gold);">Pricing Content</h2>
          <p class="text-secondary mb-0">Edit pricing page content</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="/admin/wire-settings.php" class="card text-decoration-none h-100">
        <div class="card-body text-center p-4">
          <h2 class="h5 mb-2" style="color: var(--gv-gold);">Wire Transfer</h2>
          <p class="text-secondary mb-0">Edit bank details for payments</p>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h5 mb-2" style="color: var(--gv-gold);">Homepage</h2>
          <p class="text-secondary mb-0">Edit below</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Homepage Content Form -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Homepage Content</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/content.php">
        <?= csrfField() ?>

        <div class="mb-4">
          <label for="hero_text" class="form-label text-secondary">Hero Text</label>
          <textarea class="form-control" id="hero_text" name="hero_text" rows="4" required><?= sanitizeOutput($homepageContent['hero_text'] ?? '') ?></textarea>
          <div class="form-text text-secondary">Main headline and description shown on the homepage hero section.</div>
          <?php if (isset($formErrors['hero_text'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['hero_text']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <label for="service_descriptions" class="form-label text-secondary">Service Descriptions</label>
          <textarea class="form-control" id="service_descriptions" name="service_descriptions" rows="6" required><?= sanitizeOutput($homepageContent['service_descriptions'] ?? '') ?></textarea>
          <div class="form-text text-secondary">Descriptions of services offered (vault storage, shipping, insurance).</div>
          <?php if (isset($formErrors['service_descriptions'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['service_descriptions']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <label for="security_highlights" class="form-label text-secondary">Security Highlights</label>
          <textarea class="form-control" id="security_highlights" name="security_highlights" rows="4" required><?= sanitizeOutput($homepageContent['security_highlights'] ?? '') ?></textarea>
          <div class="form-text text-secondary">Security features and certifications to highlight.</div>
          <?php if (isset($formErrors['security_highlights'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['security_highlights']) ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-outline-light">Save Homepage Content</button>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
