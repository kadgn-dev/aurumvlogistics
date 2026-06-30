<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Dashboard - Summary Statistics & Recent Activity
 *
 * Displays total users, pending KYC count, pending shipments count,
 * total invoices, and recent activity section.
 *
 * Requirements: 17.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';

use GOLS\Repositories\ContentRepository;

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$contentRepo = new ContentRepository($pdo);
$adminId = getCurrentUserId();

// Handle fee amount updates
$feeSuccessMessage = '';
$feeErrorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $action = $_POST['action'] ?? '';

  if ($action === 'update_fees') {
    $feeNames = $_POST['fee_name'] ?? [];
    $feeAmounts = $_POST['fee_amount'] ?? [];
    $feeTypes = $_POST['fee_type'] ?? [];
    $fees = [];

    foreach ($feeNames as $index => $name) {
      $name = trim($name);
      $amount = trim($feeAmounts[$index] ?? '');
      $type = $feeTypes[$index] ?? 'fixed';
      if (!empty($name) && $amount !== '') {
        $fees[] = [
          'name' => $name,
          'amount' => number_format((float) $amount, 2, '.', ''),
          'type' => in_array($type, ['fixed', 'percentage'], true) ? $type : 'fixed',
        ];
      }
    }

    // Also handle new fee entry
    $newName = trim($_POST['new_fee_name'] ?? '');
    $newAmount = trim($_POST['new_fee_amount'] ?? '');
    $newType = $_POST['new_fee_type'] ?? 'fixed';
    if (!empty($newName) && $newAmount !== '') {
      $fees[] = [
        'name' => $newName,
        'amount' => number_format((float) $newAmount, 2, '.', ''),
        'type' => in_array($newType, ['fixed', 'percentage'], true) ? $newType : 'fixed',
      ];
    }

    $contentRepo->upsert('fee_amounts', $fees, $adminId);
    $feeSuccessMessage = 'Fee amounts updated successfully.';
  } elseif ($action === 'delete_fee') {
    $deleteIndex = (int) ($_POST['fee_index'] ?? -1);
    $feeData = $contentRepo->getByPageKey('fee_amounts');
    $fees = $feeData['content'] ?? [];
    if (isset($fees[$deleteIndex])) {
      array_splice($fees, $deleteIndex, 1);
      $contentRepo->upsert('fee_amounts', $fees, $adminId);
      $feeSuccessMessage = 'Fee removed.';
    }
  }
}

// Load current fee amounts
$feeData = $contentRepo->getByPageKey('fee_amounts');
$fees = $feeData['content'] ?? [];

// Summary statistics
$totalUsers = 0;
$pendingKycCount = 0;
$pendingShipmentsCount = 0;
$totalInvoices = 0;
$recentActivity = [];

try {
  // Total users
  $stmt = $pdo->query('SELECT COUNT(*) FROM users');
  $totalUsers = (int) $stmt->fetchColumn();

  // Pending KYC count
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE kyc_status = :status");
  $stmt->execute([':status' => 'pending_review']);
  $pendingKycCount = (int) $stmt->fetchColumn();

  // Pending shipments count
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE status = :status");
  $stmt->execute([':status' => 'pending_approval']);
  $pendingShipmentsCount = (int) $stmt->fetchColumn();

  // Total invoices
  $stmt = $pdo->query('SELECT COUNT(*) FROM invoices');
  $totalInvoices = (int) $stmt->fetchColumn();

  // Recent activity: last 10 status changes across shipments
  $stmt = $pdo->query(
    'SELECT ssh.status, ssh.changed_at, u.name AS changed_by_name, ssh.shipment_id
     FROM shipment_status_history ssh
     LEFT JOIN users u ON ssh.changed_by = u.id
     ORDER BY ssh.changed_at DESC
     LIMIT 10'
  );
  $recentActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {
  // Graceful degradation - show zeros
}

$pageTitle = 'Admin Dashboard - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Admin Dashboard</h1>

  <!-- Summary Statistics -->
  <div class="row g-4 mb-5">
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Total Users</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput((string) $totalUsers) ?>
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Pending KYC</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput((string) $pendingKycCount) ?>
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Pending Shipments</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput((string) $pendingShipmentsCount) ?>
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Total Invoices</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput((string) $totalInvoices) ?>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="card mb-5">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Recent Activity</h2>
    </div>
    <div class="card-body">
      <?php if (empty($recentActivity)): ?>
      <p class="text-secondary text-center mb-0 py-3">No recent activity.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">Shipment</th>
              <th scope="col">Status</th>
              <th scope="col">Changed By</th>
              <th scope="col">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentActivity as $activity): ?>
            <tr>
              <td>#<?= sanitizeOutput((string) $activity['shipment_id']) ?></td>
              <td>
                <?php
                $statusClasses = [
                  'pending_approval' => 'bg-warning text-dark',
                  'approved' => 'bg-info text-dark',
                  'ready_for_shipment' => 'bg-primary',
                  'in_transit' => 'bg-primary',
                  'delivered' => 'bg-success',
                  'rejected' => 'bg-danger',
                  'cancelled' => 'bg-secondary',
                ];
                $statusLabel = str_replace('_', ' ', $activity['status']);
                $badgeClass = $statusClasses[$activity['status']] ?? 'bg-secondary';
                ?>
                <span class="badge <?= $badgeClass ?>"><?= sanitizeOutput(ucwords($statusLabel)) ?></span>
              </td>
              <td><?= sanitizeOutput($activity['changed_by_name'] ?? 'System') ?></td>
              <td><?= sanitizeOutput(formatDateTime($activity['changed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Fee Amounts Management -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Fee Amounts</h2>
    </div>
    <div class="card-body">
      <p class="text-secondary mb-3">Set default fee amounts for each service. These amounts auto-fill when creating invoices.</p>

      <?php if ($feeSuccessMessage): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= sanitizeOutput($feeSuccessMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <?php if ($feeErrorMessage): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= sanitizeOutput($feeErrorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <form method="POST" action="/admin/dashboard.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_fees">

        <?php if (!empty($fees)): ?>
        <div class="table-responsive mb-3">
          <table class="table table-dark table-hover mb-0">
            <thead>
              <tr>
                <th scope="col">Fee Name</th>
                <th scope="col">Type</th>
                <th scope="col">Amount</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fees as $index => $fee): ?>
              <tr>
                <td>
                  <input type="text" class="form-control form-control-sm" name="fee_name[<?= $index ?>]" value="<?= sanitizeOutput($fee['name'] ?? '') ?>" required>
                </td>
                <td>
                  <select class="form-select form-select-sm" name="fee_type[<?= $index ?>]">
                    <option value="fixed" <?= ($fee['type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Fixed ($)</option>
                    <option value="percentage" <?= ($fee['type'] ?? '') === 'percentage' ? 'selected' : '' ?>>% of Portfolio</option>
                  </select>
                </td>
                <td>
                  <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="fee_amount[<?= $index ?>]" value="<?= sanitizeOutput($fee['amount'] ?? '0.00') ?>" required>
                </td>
                <td>
                  <button type="submit" form="delete-fee-<?= $index ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this fee?')">Remove</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-secondary mb-3">No fees configured yet. Add your first fee below.</p>
        <?php endif; ?>

        <!-- Add New Fee -->
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-4">
            <label for="new_fee_name" class="form-label text-secondary">New Fee Name</label>
            <input type="text" class="form-control" id="new_fee_name" name="new_fee_name" placeholder="e.g. Monthly Vault Storage Fee">
          </div>
          <div class="col-md-3">
            <label for="new_fee_type" class="form-label text-secondary">Type</label>
            <select class="form-select" id="new_fee_type" name="new_fee_type">
              <option value="fixed">Fixed ($)</option>
              <option value="percentage">% of Portfolio</option>
            </select>
          </div>
          <div class="col-md-2">
            <label for="new_fee_amount" class="form-label text-secondary">Amount</label>
            <input type="number" step="0.01" min="0" class="form-control" id="new_fee_amount" name="new_fee_amount" placeholder="0.00">
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-outline-light w-100">Save All</button>
          </div>
        </div>
      </form>

      <!-- Delete forms (separate to avoid nesting forms) -->
      <?php foreach ($fees as $index => $fee): ?>
      <form id="delete-fee-<?= $index ?>" method="POST" action="/admin/dashboard.php" class="d-none">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_fee">
        <input type="hidden" name="fee_index" value="<?= $index ?>">
      </form>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
