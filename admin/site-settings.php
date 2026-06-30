<?php
/**
 * Admin Site Settings
 * Configure site name, logo, and branding.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/services/AuditService.php';

use GOLS\Repositories\ContentRepository;
use GOLS\Services\AuditService;

requireAdmin();

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);
$auditService = new AuditService($pdo);
$adminId = getCurrentUserId();

$successMessage = '';
$errorMessage = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Request could not be verified.';
  } else {
    $data = [
      'site_name' => trim($_POST['site_name'] ?? ''),
      'site_tagline' => trim($_POST['site_tagline'] ?? ''),
      'footer_text' => trim($_POST['footer_text'] ?? ''),
    ];

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
      $file = $_FILES['logo'];
      $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
      $maxSize = 2 * 1024 * 1024; // 2MB

      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mimeType = $finfo->file($file['tmp_name']);

      if (!in_array($mimeType, $allowedTypes, true)) {
        $errorMessage = 'Logo must be PNG, JPG, SVG, or WebP.';
      } elseif ($file['size'] > $maxSize) {
        $errorMessage = 'Logo must be under 2MB.';
      } else {
        $ext = match($mimeType) {
          'image/png' => 'png',
          'image/jpeg' => 'jpg',
          'image/svg+xml' => 'svg',
          'image/webp' => 'webp',
          default => 'png',
        };
        $logoFilename = 'logo.' . $ext;
        $logoPath = __DIR__ . '/../assets/img/' . $logoFilename;

        if (move_uploaded_file($file['tmp_name'], $logoPath)) {
          $data['logo_path'] = '/assets/img/' . $logoFilename;
        } else {
          $errorMessage = 'Failed to upload logo.';
        }
      }
    }

    if (empty($errorMessage)) {
      if (empty($data['site_name'])) {
        $errorMessage = 'Site name is required.';
      } else {
        // Preserve existing logo if not uploading new one
        $existing = $contentRepo->getByPageKey('site_settings');
        if (!isset($data['logo_path']) && $existing) {
          $data['logo_path'] = $existing['content']['logo_path'] ?? '';
        }

        $contentRepo->upsert('site_settings', $data, $adminId);
        $successMessage = 'Site settings updated successfully.';
        $auditService->log('site_settings_updated', $adminId);
      }
    }
  }
}

// Load current settings
$settingsData = $contentRepo->getByPageKey('site_settings');
$settings = $settingsData['content'] ?? [
  'site_name' => 'AURUM VAULT LOGISTICS',
  'site_tagline' => 'Secure Gold Storage & Insured Logistics Services',
  'logo_path' => '',
  'footer_text' => '',
];

$pageTitle = 'Site Settings - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Site Settings</h1>
    <a href="/admin/dashboard.php" class="btn btn-outline-gold">Back to Dashboard</a>
  </div>

  <?php if ($successMessage): ?>
  <div class="alert alert-success"><?= sanitizeOutput($successMessage) ?></div>
  <?php endif; ?>
  <?php if ($errorMessage): ?>
  <div class="alert alert-danger"><?= sanitizeOutput($errorMessage) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">Branding & Identity</h5></div>
    <div class="card-body">
      <form method="POST" action="/admin/site-settings.php" enctype="multipart/form-data">
        <?= csrfField() ?>

        <div class="row g-4">
          <div class="col-md-6">
            <label for="site_name" class="form-label fw-bold">Site Name *</label>
            <input type="text" class="form-control" id="site_name" name="site_name" value="<?= sanitizeOutput($settings['site_name'] ?? '') ?>" required>
            <div class="form-text">Displayed in the navbar and page titles.</div>
          </div>

          <div class="col-md-6">
            <label for="site_tagline" class="form-label fw-bold">Tagline</label>
            <input type="text" class="form-control" id="site_tagline" name="site_tagline" value="<?= sanitizeOutput($settings['site_tagline'] ?? '') ?>">
            <div class="form-text">Shown in the footer.</div>
          </div>

          <div class="col-md-6">
            <label for="logo" class="form-label fw-bold">Logo</label>
            <?php if (!empty($settings['logo_path'])): ?>
            <div class="mb-2">
              <img src="<?= sanitizeOutput($settings['logo_path']) ?>" alt="Current logo" style="max-height: 50px; background: #f0f0f0; padding: 8px; border-radius: 4px;">
              <span class="text-secondary small ms-2">Current logo</span>
            </div>
            <?php endif; ?>
            <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
            <div class="form-text">PNG, JPG, SVG, or WebP. Max 2MB. Leave empty to keep current.</div>
          </div>

          <div class="col-md-6">
            <label for="footer_text" class="form-label fw-bold">Footer Text</label>
            <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?= sanitizeOutput($settings['footer_text'] ?? '') ?>">
            <div class="form-text">Custom footer copyright text. Leave empty for default.</div>
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-gold">Save Settings</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Preview -->
  <div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">Preview</h5></div>
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 p-3" style="background: #f8f8f8; border-radius: 4px;">
        <?php if (!empty($settings['logo_path'])): ?>
        <img src="<?= sanitizeOutput($settings['logo_path']) ?>" alt="Logo" style="max-height: 36px;">
        <?php endif; ?>
        <span class="fw-bold" style="font-size: 1.1rem; letter-spacing: 0.04em;"><?= sanitizeOutput($settings['site_name'] ?? 'AURUM VAULT LOGISTICS') ?></span>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
