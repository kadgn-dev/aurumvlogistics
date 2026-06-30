<?php
/**
 * Admin Invoice Description Presets
 * Manage pre-configured invoice descriptions for quick selection.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';

use GOLS\Repositories\ContentRepository;

requireAdmin();

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);
$adminId = getCurrentUserId();

$successMessage = '';
$errorMessage = '';

// Load current descriptions
$descData = $contentRepo->getByPageKey('invoice_descriptions');
$descriptions = $descData['content'] ?? [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Request could not be verified.';
  } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
      $newDesc = trim($_POST['description'] ?? '');
      if (!empty($newDesc) && !in_array($newDesc, $descriptions, true)) {
        $descriptions[] = $newDesc;
        $contentRepo->upsert('invoice_descriptions', $descriptions, $adminId);
        $successMessage = 'Description added.';
      } else {
        $errorMessage = empty($newDesc) ? 'Description cannot be empty.' : 'Description already exists.';
      }
    } elseif ($action === 'delete') {
      $index = (int) ($_POST['index'] ?? -1);
      if (isset($descriptions[$index])) {
        array_splice($descriptions, $index, 1);
        $contentRepo->upsert('invoice_descriptions', $descriptions, $adminId);
        $successMessage = 'Description removed.';
      }
    }
  }
}

$pageTitle = 'Invoice Descriptions - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Invoice Description Presets</h1>
    <a href="/admin/invoices.php" class="btn btn-outline-gold">Back to Invoices</a>
  </div>

  <?php if ($successMessage): ?>
  <div class="alert alert-success"><?= sanitizeOutput($successMessage) ?></div>
  <?php endif; ?>
  <?php if ($errorMessage): ?>
  <div class="alert alert-danger"><?= sanitizeOutput($errorMessage) ?></div>
  <?php endif; ?>

  <!-- Add New -->
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Add New Description</h5></div>
    <div class="card-body">
      <form method="POST" action="/admin/invoice-descriptions.php" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="col-md-9">
          <label for="description" class="form-label">Description Text</label>
          <input type="text" class="form-control" id="description" name="description" placeholder="e.g. Monthly Vault Storage Fee" required maxlength="200">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-gold w-100">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Current Descriptions -->
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Current Presets (<?= count($descriptions) ?>)</h5></div>
    <div class="card-body">
      <?php if (empty($descriptions)): ?>
      <p class="text-secondary text-center py-3 mb-0">No presets configured. Add some above.</p>
      <?php else: ?>
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Description</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($descriptions as $index => $desc): ?>
          <tr>
            <td><?= $index + 1 ?></td>
            <td><?= sanitizeOutput($desc) ?></td>
            <td>
              <form method="POST" action="/admin/invoice-descriptions.php" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="index" value="<?= $index ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this description?')">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
