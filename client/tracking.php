<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Tracking Page
 *
 * Allows clients to search for a shipment by tracking number and view
 * shipment details along with full status history and timestamps.
 *
 * Requirements: 8.2, 8.3, 8.4
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

// Instantiate service
$pdo = getDbConnection();
$shipmentRepo = new ShipmentRepository($pdo);
$inventoryRepo = new InventoryRepository($pdo);
$shipmentValidator = new ShipmentValidator();
$shipmentService = new ShipmentService($shipmentRepo, $inventoryRepo, $shipmentValidator);

// Handle search
$trackingNumber = trim((string) ($_GET['tracking'] ?? ''));
$searched = $trackingNumber !== '';
$shipment = null;
$statusHistory = [];

if ($searched) {
  $shipment = $shipmentService->getShipmentByTracking($userId, $trackingNumber);

  if ($shipment !== null) {
    $statusHistory = $shipmentService->getStatusHistory((int) $shipment['id']);
  }
}

$pageTitle = 'Track Shipment - Aurum Vault Logistics';

// Status badge mapping
function getTrackingStatusBadgeClass(string $status): string
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

function formatTrackingStatusLabel(string $status): string
{
  return ucwords(str_replace('_', ' ', $status));
}

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<main class="container py-4">
  <h1 class="h3 mb-4" style="color: #c9a227;">Track Shipment</h1>

  <!-- Search Form -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="/client/tracking.php" class="row g-3 align-items-end">
        <div class="col-md-8">
          <label for="tracking" class="form-label">Tracking Number</label>
          <input type="text" class="form-control" id="tracking" name="tracking"
              placeholder="Enter your tracking number"
              value="<?= sanitizeOutput($trackingNumber) ?>"
              required>
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn w-100" style="background-color: #c9a227; color: #1a1a1a;">Search</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($searched && $shipment === null): ?>
    <!-- Not Found State -->
    <div class="card">
      <div class="card-body text-center py-4">
        <p class="text-secondary mb-1">No matching shipment found</p>
        <p class="text-secondary small">The tracking number you entered does not match any of your shipments. Please check the number and try again.</p>
      </div>
    </div>
  <?php elseif ($searched && $shipment !== null): ?>
    <!-- Shipment Details -->
    <div class="card mb-4">
      <div class="card-header">
        <h2 class="h5 mb-0">Shipment Details</h2>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <dl class="row mb-0">
              <dt class="col-sm-5 text-secondary">Destination</dt>
              <dd class="col-sm-7">
                <?= sanitizeOutput($shipment['street']) ?><br>
                <?= sanitizeOutput($shipment['city']) ?>, <?= sanitizeOutput($shipment['state_province']) ?> <?= sanitizeOutput($shipment['postal_code']) ?><br>
                <?= sanitizeOutput($shipment['country']) ?>
              </dd>

              <dt class="col-sm-5 text-secondary">Status</dt>
              <dd class="col-sm-7">
                <span class="badge <?= getTrackingStatusBadgeClass($shipment['status']) ?>">
                  <?= sanitizeOutput(formatTrackingStatusLabel($shipment['status'])) ?>
                </span>
              </dd>

              <dt class="col-sm-5 text-secondary">Carrier</dt>
              <dd class="col-sm-7">
                <?php if (!empty($shipment['carrier'])): ?>
                  <?= sanitizeOutput(strtoupper($shipment['carrier'])) ?>
                <?php else: ?>
                  <span class="text-secondary">Not assigned</span>
                <?php endif; ?>
              </dd>
            </dl>
          </div>
          <div class="col-md-6">
            <dl class="row mb-0">
              <dt class="col-sm-5 text-secondary">Tracking Number</dt>
              <dd class="col-sm-7">
                <?php if (!empty($shipment['tracking_number'])): ?>
                  <?= sanitizeOutput($shipment['tracking_number']) ?>
                <?php else: ?>
                  <span class="text-secondary">Not assigned</span>
                <?php endif; ?>
              </dd>

              <dt class="col-sm-5 text-secondary">Insured Value</dt>
              <dd class="col-sm-7">
                <?php if ((float) $shipment['insured_value'] > 0): ?>
                  <?= sanitizeOutput(formatCurrency((float) $shipment['insured_value'])) ?>
                <?php else: ?>
                  <span class="text-secondary">No insurance</span>
                <?php endif; ?>
              </dd>

              <dt class="col-sm-5 text-secondary">Created</dt>
              <dd class="col-sm-7"><?= sanitizeOutput(formatDateTime($shipment['created_at'])) ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <!-- Status History -->
    <div class="card">
      <div class="card-header">
        <h2 class="h5 mb-0">Status History</h2>
      </div>
      <div class="card-body">
        <?php if (empty($statusHistory)): ?>
          <p class="text-secondary mb-0">No status history available.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
              <thead>
                <tr>
                  <th>Status</th>
                  <th>Date &amp; Time</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($statusHistory as $history): ?>
                  <tr>
                    <td>
                      <span class="badge <?= getTrackingStatusBadgeClass($history['status']) ?>">
                        <?= sanitizeOutput(formatTrackingStatusLabel($history['status'])) ?>
                      </span>
                    </td>
                    <td><?= sanitizeOutput(formatDateTime($history['changed_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif (!$searched): ?>
    <!-- Empty State (no search performed) -->
    <div class="card">
      <div class="card-body text-center py-5">
        <p class="text-secondary mb-1">Enter a tracking number above to view shipment details and status history.</p>
        <p class="text-secondary small mb-0">
          You can find tracking numbers on your <a href="/client/shipments.php" style="color: #c9a227;">shipments page</a>.
        </p>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
