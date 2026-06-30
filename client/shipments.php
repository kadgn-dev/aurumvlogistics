<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Shipments Page
 *
 * Displays a paginated list of all shipments belonging to the authenticated client,
 * sorted by most recent update descending. Shows status, tracking number, carrier,
 * and dates.
 *
 * Requirements: 8.1, 8.3, 8.5
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ShipmentRepository.php';
require_once __DIR__ . '/../includes/services/ShipmentService.php';
require_once __DIR__ . '/../includes/repositories/InventoryRepository.php';
require_once __DIR__ . '/../includes/validators/ShipmentValidator.php';

use GOLS\Repositories\ShipmentRepository;
use GOLS\Repositories\InventoryRepository;
use GOLS\Services\ShipmentService;
use GOLS\Validators\ShipmentValidator;

requireClient();

$userId = getCurrentUserId();
$page = max(1, (int) ($_GET['page'] ?? 1));

// Instantiate service
$pdo = getDbConnection();
$shipmentRepo = new ShipmentRepository($pdo);
$inventoryRepo = new InventoryRepository($pdo);
$shipmentValidator = new ShipmentValidator();
$shipmentService = new ShipmentService($shipmentRepo, $inventoryRepo, $shipmentValidator);

// Load paginated shipments
$result = $shipmentService->getClientShipments($userId, $page);
$shipments = $result['data'] ?? [];
$totalRecords = $result['total'] ?? 0;
$perPage = 20;
$totalPages = getTotalPages($totalRecords, $perPage);

$pageTitle = 'My Shipments - Aurum Vault Logistics';

// Status badge mapping
function getStatusBadgeClass(string $status): string
{
  $map = [
    'pending_approval' => 'bg-warning text-dark',
    'approved' => 'bg-info text-dark',
    'ready_for_shipment' => 'bg-primary',
    'in_transit' => 'bg-primary',
    'delivered' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary',
  ];

  return $map[$status] ?? 'bg-secondary';
}

function formatStatusLabel(string $status): string
{
  return ucwords(str_replace('_', ' ', $status));
}

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0" style="color: #c9a227;">My Shipments</h1>
    <div>
      <a href="/client/tracking.php" class="btn btn-outline-light btn-sm me-2">Track Shipment</a>
      <a href="/client/shipment-request.php" class="btn btn-sm" style="background-color: #c9a227; color: #1a1a1a;">Request Shipment</a>
    </div>
  </div>

  <?php if (empty($shipments)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <p class="text-secondary mb-3">No shipments yet</p>
        <a href="/client/shipment-request.php" class="btn" style="background-color: #c9a227; color: #1a1a1a;">Request Your First Shipment</a>
      </div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Tracking Number</th>
            <th>Carrier</th>
            <th>Created</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($shipments as $shipment): ?>
            <tr>
              <td>#<?= (int) $shipment['id'] ?></td>
              <td>
                <span class="badge <?= getStatusBadgeClass($shipment['status']) ?>">
                  <?= sanitizeOutput(formatStatusLabel($shipment['status'])) ?>
                </span>
              </td>
              <td>
                <?php if (!empty($shipment['tracking_number'])): ?>
                  <a href="/client/tracking.php?tracking=<?= urlencode($shipment['tracking_number']) ?>" class="text-decoration-none" style="color: #c9a227;">
                    <?= sanitizeOutput($shipment['tracking_number']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($shipment['carrier'])): ?>
                  <?= sanitizeOutput(strtoupper($shipment['carrier'])) ?>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td><?= sanitizeOutput(formatDate($shipment['created_at'])) ?></td>
              <td><?= sanitizeOutput(formatDate($shipment['updated_at'])) ?></td>
              <td>
                <?php if (!empty($shipment['manifest_path']) && $shipment['status'] !== 'pending_approval'): ?>
                  <a href="/client/manifest-download.php?shipment_id=<?= (int) $shipment['id'] ?>" class="btn btn-outline-light btn-sm">Download Manifest</a>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav aria-label="Shipments pagination">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
              <span aria-hidden="true">&laquo;</span>
            </a>
          </li>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link <?= $i === $page ? '' : 'border-secondary' ?>" href="?page=<?= $i ?>"
                <?php if ($i === $page): ?> style="background-color: #c9a227; border-color: #c9a227; color: #1a1a1a;" <?php endif; ?>>
                <?= $i ?>
              </a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
              <span aria-hidden="true">&raquo;</span>
            </a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
