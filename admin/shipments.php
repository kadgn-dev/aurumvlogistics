<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Shipments Management - List, Approve/Reject, Tracking, Status Updates
 *
 * Lists all shipments with pagination, approve/reject actions,
 * tracking assignment form, status update dropdown (state machine enforced),
 * and status history display.
 *
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.6, 17.4
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/repositories/ShipmentRepository.php';
require_once __DIR__ . '/../includes/repositories/InventoryRepository.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/services/ShipmentService.php';
require_once __DIR__ . '/../includes/services/AuditService.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';
require_once __DIR__ . '/../includes/services/EmailService.php';
require_once __DIR__ . '/../includes/validators/ShipmentValidator.php';

use GOLS\Repositories\ShipmentRepository;
use GOLS\Repositories\InventoryRepository;
use GOLS\Repositories\NotificationRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Services\ShipmentService;
use GOLS\Services\AuditService;
use GOLS\Services\NotificationService;
use GOLS\Services\EmailService;
use GOLS\Validators\ShipmentValidator;

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$shipmentRepo = new ShipmentRepository($pdo);
$inventoryRepo = new InventoryRepository($pdo);
$shipmentValidator = new ShipmentValidator();
$notificationRepo = new NotificationRepository($pdo);
$notificationService = new NotificationService($notificationRepo);
$userRepo = new UserRepository($pdo);
$emailService = new EmailService($pdo);
$shipmentService = new ShipmentService($shipmentRepo, $inventoryRepo, $shipmentValidator, $notificationService, $userRepo, $emailService);
$auditService = new AuditService($pdo);

$adminId = getCurrentUserId();
$successMessage = '';
$errorMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $action = $_POST['action'] ?? '';
  $shipmentId = (int) ($_POST['shipment_id'] ?? 0);

  if ($action === 'approve' && $shipmentId > 0) {
    $result = $shipmentService->approve($shipmentId, $adminId);
    if ($result->success) {
      $successMessage = 'Shipment approved successfully.';
      $auditService->log('shipment_approved', $adminId, 'shipment', $shipmentId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to approve shipment.';
    }
  } elseif ($action === 'reject' && $shipmentId > 0) {
    $reason = trim($_POST['rejection_reason'] ?? '');
    $result = $shipmentService->reject($shipmentId, $adminId, $reason);
    if ($result->success) {
      $successMessage = 'Shipment rejected.';
      $auditService->log('shipment_rejected', $adminId, 'shipment', $shipmentId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to reject shipment.';
    }
  } elseif ($action === 'assign_tracking' && $shipmentId > 0) {
    $trackingNumber = trim($_POST['tracking_number'] ?? '');
    $carrier = trim($_POST['carrier'] ?? '');
    $result = $shipmentService->assignTracking($shipmentId, $adminId, $trackingNumber, $carrier);
    if ($result->success) {
      $successMessage = 'Tracking assigned successfully.';
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to assign tracking.';
    }
  } elseif ($action === 'update_status' && $shipmentId > 0) {
    $newStatus = $_POST['new_status'] ?? '';
    $result = $shipmentService->updateStatus($shipmentId, $adminId, $newStatus);
    if ($result->success) {
      $successMessage = 'Shipment status updated.';
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to update status.';
    }
  }
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $shipmentRepo->getAllPaginated($page);
$shipments = $result['data'];
$totalShipments = $result['total'];
$totalPages = getTotalPages($totalShipments, 20);

// Get status history for detail view
$viewShipmentId = isset($_GET['view']) ? (int) $_GET['view'] : null;
$viewShipment = null;
$statusHistory = [];
if ($viewShipmentId) {
  $viewShipment = $shipmentRepo->findById($viewShipmentId);
  $statusHistory = $shipmentRepo->getStatusHistory($viewShipmentId);
}

$pageTitle = 'Shipment Management - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Shipment Management</h1>

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
  ?>

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

  <?php if ($viewShipment): ?>
  <!-- Shipment Detail View -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h5 mb-0" style="color: #c9a227;">Shipment #<?= (int) $viewShipment['id'] ?> Details</h2>
      <a href="/admin/shipments.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
    </div>
    <div class="card-body">
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <strong class="text-secondary">Status:</strong>
          <?php
          $badgeClass = $statusClasses[$viewShipment['status']] ?? 'bg-secondary';
          ?>
          <span class="badge <?= $badgeClass ?>"><?= sanitizeOutput(ucwords(str_replace('_', ' ', $viewShipment['status']))) ?></span>
        </div>
        <div class="col-md-4">
          <strong class="text-secondary">Tracking:</strong>
          <?= sanitizeOutput($viewShipment['tracking_number'] ?? 'Not assigned') ?>
        </div>
        <div class="col-md-4">
          <strong class="text-secondary">Carrier:</strong>
          <?= sanitizeOutput(strtoupper($viewShipment['carrier'] ?? 'N/A')) ?>
        </div>
        <div class="col-md-6">
          <strong class="text-secondary">Destination:</strong>
          <?= sanitizeOutput($viewShipment['street'] . ', ' . $viewShipment['city'] . ', ' . $viewShipment['state_province'] . ' ' . $viewShipment['postal_code'] . ', ' . $viewShipment['country']) ?>
        </div>
        <div class="col-md-3">
          <strong class="text-secondary">Insurance:</strong>
          <?= $viewShipment['insurance_selected'] ? 'Yes ($' . number_format((float) $viewShipment['insured_value'], 2) . ')' : 'No' ?>
        </div>
        <?php if ($viewShipment['rejection_reason']): ?>
        <div class="col-12">
          <strong class="text-danger">Rejection Reason:</strong>
          <?= sanitizeOutput($viewShipment['rejection_reason']) ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($viewShipment['manifest_path']) && $viewShipment['status'] !== 'pending_approval'): ?>
      <div class="mb-4">
        <a href="/client/manifest-download.php?shipment_id=<?= (int) $viewShipment['id'] ?>" class="btn btn-outline-light">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
            <path d="M4.603 14.087a.8.8 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.7 7.7 0 0 1 1.482-.645 20 20 0 0 0 1.062-2.227 7.3 7.3 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.188-.012.396-.047.614-.084.51-.27 1.134-.52 1.794a11 11 0 0 0 .98 1.686 5.8 5.8 0 0 1 1.334.05c.364.066.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.86.86 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.7 5.7 0 0 1-.911-.95 11.7 11.7 0 0 0-1.997.406 11.3 11.3 0 0 1-1.02 1.51c-.292.35-.609.656-.927.787a.8.8 0 0 1-.58.029z"/>
          </svg>
          Download Manifest
        </a>
      </div>
      <?php endif; ?>

      <!-- Actions based on current status -->
      <?php $validTransitions = $shipmentService->getValidTransitions($viewShipment['status']); ?>

      <?php if ($viewShipment['status'] === 'pending_approval'): ?>
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <form method="POST" action="/admin/shipments.php?view=<?= (int) $viewShipment['id'] ?>">
            <?= csrfField() ?>
            <input type="hidden" name="shipment_id" value="<?= (int) $viewShipment['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success w-100">Approve</button>
          </form>
        </div>
        <div class="col-md-9">
          <form method="POST" action="/admin/shipments.php?view=<?= (int) $viewShipment['id'] ?>" class="row g-2">
            <?= csrfField() ?>
            <input type="hidden" name="shipment_id" value="<?= (int) $viewShipment['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <div class="col-md-8">
              <textarea class="form-control" name="rejection_reason" placeholder="Rejection reason (required)" required maxlength="500"></textarea>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Reject this shipment?')">Reject</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($viewShipment['status'] === 'approved'): ?>
      <!-- Assign Tracking Form -->
      <div class="card mb-4">
        <div class="card-header">
          <h3 class="h6 mb-0 text-secondary">Assign Tracking</h3>
        </div>
        <div class="card-body">
          <form method="POST" action="/admin/shipments.php?view=<?= (int) $viewShipment['id'] ?>" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="shipment_id" value="<?= (int) $viewShipment['id'] ?>">
            <input type="hidden" name="action" value="assign_tracking">
            <div class="col-md-5">
              <label for="tracking_number" class="form-label text-secondary">Tracking Number</label>
              <input type="text" class="form-control" id="tracking_number" name="tracking_number" required minlength="6" maxlength="50" pattern="[a-zA-Z0-9]+">
            </div>
            <div class="col-md-4">
              <label for="carrier" class="form-label text-secondary">Carrier</label>
              <select class="form-select" id="carrier" name="carrier" required>
                <option value="">Select carrier...</option>
                <option value="dhl">DHL</option>
                <option value="fedex">FedEx</option>
                <option value="brinks">Brinks</option>
              </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <button type="submit" class="btn btn-outline-light w-100">Assign</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($validTransitions)): ?>
      <!-- Status Update -->
      <div class="card mb-4">
        <div class="card-header">
          <h3 class="h6 mb-0 text-secondary">Update Status</h3>
        </div>
        <div class="card-body">
          <form method="POST" action="/admin/shipments.php?view=<?= (int) $viewShipment['id'] ?>" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="shipment_id" value="<?= (int) $viewShipment['id'] ?>">
            <input type="hidden" name="action" value="update_status">
            <div class="col-md-6">
              <label for="new_status" class="form-label text-secondary">New Status</label>
              <select class="form-select" id="new_status" name="new_status" required>
                <option value="">Select status...</option>
                <?php foreach ($validTransitions as $transition): ?>
                <option value="<?= sanitizeOutput($transition) ?>"><?= sanitizeOutput(ucwords(str_replace('_', ' ', $transition))) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <button type="submit" class="btn btn-outline-light">Update Status</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Status History -->
      <h3 class="h6 text-secondary mb-3">Status History</h3>
      <?php if (empty($statusHistory)): ?>
      <p class="text-secondary">No status history available.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-sm mb-0">
          <thead>
            <tr>
              <th>Status</th>
              <th>Changed By</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($statusHistory as $history): ?>
            <tr>
              <td>
                <?php $hBadge = $statusClasses[$history['status']] ?? 'bg-secondary'; ?>
                <span class="badge <?= $hBadge ?>"><?= sanitizeOutput(ucwords(str_replace('_', ' ', $history['status']))) ?></span>
              </td>
              <td><?= sanitizeOutput($history['changed_by_name'] ?? 'System') ?></td>
              <td><?= sanitizeOutput(formatDateTime($history['changed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Shipments List -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">All Shipments (<?= sanitizeOutput((string) $totalShipments) ?>)</h2>
    </div>
    <div class="card-body">
      <?php if (empty($shipments)): ?>
      <p class="text-secondary text-center mb-0 py-3">No shipments found.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Client</th>
              <th scope="col">Destination</th>
              <th scope="col">Status</th>
              <th scope="col">Tracking</th>
              <th scope="col">Updated</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($shipments as $shipment): ?>
            <tr>
              <td>#<?= (int) $shipment['id'] ?></td>
              <td><?= sanitizeOutput($shipment['user_name'] ?? 'Unknown') ?></td>
              <td><?= sanitizeOutput($shipment['city'] . ', ' . $shipment['country']) ?></td>
              <td>
                <?php
                $sBadge = $statusClasses[$shipment['status']] ?? 'bg-secondary';
                ?>
                <span class="badge <?= $sBadge ?>"><?= sanitizeOutput(ucwords(str_replace('_', ' ', $shipment['status']))) ?></span>
              </td>
              <td><?= sanitizeOutput($shipment['tracking_number'] ?? '—') ?></td>
              <td><?= sanitizeOutput(formatDateTime($shipment['updated_at'])) ?></td>
              <td>
                <a href="/admin/shipments.php?view=<?= (int) $shipment['id'] ?>" class="btn btn-sm btn-outline-light">View</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <nav aria-label="Shipment pagination" class="mt-4">
        <ul class="pagination justify-content-center mb-0">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link <?= $i === $page ? '' : 'border-secondary' ?>" href="?page=<?= $i ?>" <?= $i === $page ? 'style="background-color: #c9a227; border-color: #c9a227; color: #1a1a1a;"' : '' ?>><?= $i ?></a>
          </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
