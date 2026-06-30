<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Audit Log Viewer - Browse and filter audit log entries
 *
 * Displays paginated audit log entries with filtering by event type and date range.
 * Resolves actor names from user IDs in batch to avoid N+1 queries.
 *
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/services/AuditService.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';

use GOLS\Services\AuditService;
use GOLS\Repositories\UserRepository;

requireAdmin();

$pdo = getDbConnection();
$auditService = new AuditService($pdo);
$userRepo = new UserRepository($pdo);

// Filter parameters
$filterEventType = $_GET['event_type'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = getPaginationOffset($page, $perPage);

// Query audit log
$total = $auditService->count(
  $filterEventType ?: null,
  $filterDateFrom ?: null,
  $filterDateTo ?: null
);
$entries = $auditService->query(
  $filterEventType ?: null,
  $filterDateFrom ?: null,
  $filterDateTo ?: null,
  null,
  $perPage,
  $offset
);
$totalPages = getTotalPages($total, $perPage);

// Batch-resolve actor names from actor_ids
$actorIds = array_filter(array_unique(array_column($entries, 'actor_id')));
$actorNames = [];
foreach ($actorIds as $id) {
  $user = $userRepo->findById((int) $id);
  $actorNames[(int) $id] = $user['name'] ?? 'Unknown';
}

// Known event types for filter dropdown
$eventTypes = [
  'kyc_approval', 'kyc_rejection',
  'user_suspension', 'user_reactivation',
  'invoice_created', 'invoice_paid',
  'inventory_created', 'inventory_updated', 'inventory_deleted',
  'site_settings_updated',
  'faq_created', 'faq_updated', 'faq_deleted',
  'shipment_approved', 'shipment_rejected',
  'access_denied', 'csrf_failure',
];

$pageTitle = 'Audit Log - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Audit Log</h1>

  <!-- Filter Form -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="/admin/audit-log.php" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label for="event_type" class="form-label text-secondary">Event Type</label>
          <select class="form-select" id="event_type" name="event_type">
            <option value="">All Events</option>
            <?php foreach ($eventTypes as $type): ?>
            <option value="<?= sanitizeOutput($type) ?>" <?= $filterEventType === $type ? 'selected' : '' ?>>
              <?= sanitizeOutput(ucwords(str_replace('_', ' ', $type))) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="date_from" class="form-label text-secondary">Date From</label>
          <input type="date" class="form-control" id="date_from" name="date_from" value="<?= sanitizeOutput($filterDateFrom) ?>">
        </div>
        <div class="col-md-3">
          <label for="date_to" class="form-label text-secondary">Date To</label>
          <input type="date" class="form-control" id="date_to" name="date_to" value="<?= sanitizeOutput($filterDateTo) ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-light me-2">Filter</button>
          <?php if ($filterEventType !== '' || $filterDateFrom !== '' || $filterDateTo !== ''): ?>
          <a href="/admin/audit-log.php" class="btn btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Audit Log Table -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Entries (<?= sanitizeOutput((string) $total) ?>)</h2>
    </div>
    <div class="card-body">
      <?php if (empty($entries)): ?>
      <p class="text-secondary text-center mb-0 py-3">No audit log entries found.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">Event Type</th>
              <th scope="col">Actor</th>
              <th scope="col">Target</th>
              <th scope="col">IP Address</th>
              <th scope="col">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
            <tr>
              <td><span class="badge bg-secondary"><?= sanitizeOutput(ucwords(str_replace('_', ' ', $entry['event_type']))) ?></span></td>
              <td><?= $entry['actor_id'] ? sanitizeOutput($actorNames[(int) $entry['actor_id']] ?? 'Unknown') : '<span class="text-secondary">System</span>' ?></td>
              <td>
                <?php if ($entry['target_type']): ?>
                <?= sanitizeOutput(ucfirst($entry['target_type'])) ?> #<?= (int) $entry['target_id'] ?>
                <?php else: ?>
                <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td><?= sanitizeOutput($entry['ip_address']) ?></td>
              <td><?= sanitizeOutput($entry['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <nav aria-label="Audit log pagination" class="mt-4">
        <ul class="pagination justify-content-center mb-0">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link <?= $i === $page ? '' : 'border-secondary' ?>" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" <?= $i === $page ? 'style="background-color: #c9a227; border-color: #c9a227; color: #1a1a1a;"' : '' ?>><?= $i ?></a>
          </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
