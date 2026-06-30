<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin FAQ Management - CRUD for FAQ Entries
 *
 * Add, edit, and delete FAQ entries displayed on the public FAQ page.
 *
 * Requirements: 17.3, 17.4, 17.5, 17.6
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/services/ContentService.php';
require_once __DIR__ . '/../includes/services/AuditService.php';

use GOLS\Repositories\ContentRepository;
use GOLS\Services\ContentService;
use GOLS\Services\AuditService;

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);
$contentService = new ContentService($contentRepo);
$auditService = new AuditService($pdo);
$adminId = getCurrentUserId();

$successMessage = '';
$errorMessage = '';
$formErrors = [];
$editEntry = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);

    $result = $contentService->addFaqEntry($question, $answer, $sortOrder);
    if ($result->success) {
      $successMessage = 'FAQ entry added successfully.';
      $auditService->log('faq_created', $adminId, 'faq', $result->data['entry_id'] ?? null);
    } else {
      if ($result->errors) {
        $formErrors = $result->errors;
      }
      $errorMessage = $result->errorMessage ?? 'Failed to add FAQ entry.';
    }
  } elseif ($action === 'update') {
    $entryId = (int) ($_POST['entry_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');

    $result = $contentService->updateFaqEntry($entryId, $question, $answer);
    if ($result->success) {
      $successMessage = 'FAQ entry updated successfully.';
      $auditService->log('faq_updated', $adminId, 'faq', $entryId);
    } else {
      if ($result->errors) {
        $formErrors = $result->errors;
      }
      $errorMessage = $result->errorMessage ?? 'Failed to update FAQ entry.';
    }
  } elseif ($action === 'delete') {
    $entryId = (int) ($_POST['entry_id'] ?? 0);
    $result = $contentService->deleteFaqEntry($entryId);
    if ($result->success) {
      $successMessage = 'FAQ entry deleted.';
      $auditService->log('faq_deleted', $adminId, 'faq', $entryId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to delete FAQ entry.';
    }
  }
}

// Handle edit mode
if (isset($_GET['edit'])) {
  $editId = (int) $_GET['edit'];
  $entries = $contentService->getFaqEntries();
  foreach ($entries as $entry) {
    if ((int) $entry['id'] === $editId) {
      $editEntry = $entry;
      break;
    }
  }
}

// Load all FAQ entries
$faqEntries = $contentService->getFaqEntries();

$pageTitle = 'FAQ Management - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0" style="color: #c9a227;">FAQ Management</h1>
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

  <!-- Add / Edit Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;"><?= $editEntry ? 'Edit FAQ Entry' : 'Add New FAQ Entry' ?></h2>
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/faq-manage.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $editEntry ? 'update' : 'add' ?>">
        <?php if ($editEntry): ?>
        <input type="hidden" name="entry_id" value="<?= (int) $editEntry['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label for="question" class="form-label text-secondary">Question (max 200 characters)</label>
          <input type="text" class="form-control" id="question" name="question" maxlength="200" value="<?= sanitizeOutput($editEntry['question'] ?? '') ?>" required>
          <?php if (isset($formErrors['question'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['question']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="answer" class="form-label text-secondary">Answer (max 2000 characters)</label>
          <textarea class="form-control" id="answer" name="answer" rows="4" maxlength="2000" required><?= sanitizeOutput($editEntry['answer'] ?? '') ?></textarea>
          <?php if (isset($formErrors['answer'])): ?>
          <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['answer']) ?></div>
          <?php endif; ?>
        </div>

        <?php if (!$editEntry): ?>
        <div class="mb-3">
          <label for="sort_order" class="form-label text-secondary">Sort Order</label>
          <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
          <div class="form-text text-secondary">Lower numbers appear first.</div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-outline-light"><?= $editEntry ? 'Update Entry' : 'Add Entry' ?></button>
        <?php if ($editEntry): ?>
        <a href="/admin/faq-manage.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- FAQ Entries List -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">FAQ Entries (<?= count($faqEntries) ?>)</h2>
    </div>
    <div class="card-body">
      <?php if (empty($faqEntries)): ?>
      <p class="text-secondary text-center mb-0 py-3">No FAQ entries yet.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">Order</th>
              <th scope="col">Question</th>
              <th scope="col">Answer</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($faqEntries as $entry): ?>
            <tr>
              <td><?= (int) $entry['sort_order'] ?></td>
              <td><?= sanitizeOutput($entry['question']) ?></td>
              <td><?= sanitizeOutput(mb_substr($entry['answer'], 0, 100)) ?><?= mb_strlen($entry['answer']) > 100 ? '...' : '' ?></td>
              <td>
                <a href="/admin/faq-manage.php?edit=<?= (int) $entry['id'] ?>" class="btn btn-sm btn-outline-light">Edit</a>
                <form method="POST" action="/admin/faq-manage.php" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="entry_id" value="<?= (int) $entry['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this FAQ entry?')">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
