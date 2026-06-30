<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Dashboard - Portfolio Summary & Recent Activity
 *
 * Displays portfolio summary (total weight, insured value, vault locations),
 * 5 most recent shipments, unread notification count, and 3 most recent
 * notifications. Handles empty states and partial data source failures gracefully.
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/InventoryRepository.php';
require_once __DIR__ . '/../includes/repositories/ShipmentRepository.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';

use GOLS\Repositories\InventoryRepository;
use GOLS\Repositories\ShipmentRepository;
use GOLS\Repositories\NotificationRepository;
use GOLS\Services\NotificationService;

// Require client authentication
requireClient();

$userId = getCurrentUserId();

// Initialize data with defaults
$portfolioSummary = ['total_weight' => 0.0, 'total_value' => 0.0, 'total_insured_value' => 0.0, 'vault_locations' => 0, 'item_count' => 0];
$recentShipments = [];
$unreadCount = 0;
$formattedUnreadCount = '0';
$recentNotifications = [];

// Track partial failures
$portfolioError = false;
$shipmentsError = false;
$notificationsError = false;

try {
  $pdo = getDbConnection();
} catch (\Exception $e) {
  // If DB is completely unavailable, show error state for all sections
  $portfolioError = true;
  $shipmentsError = true;
  $notificationsError = true;
  $pdo = null;
}

// Load portfolio data (Requirement 3.1)
if ($pdo !== null) {
  try {
    $inventoryRepo = new InventoryRepository($pdo);
    $portfolioSummary = $inventoryRepo->getPortfolioSummary($userId);
  } catch (\Exception $e) {
    $portfolioError = true;
  }
}

// Load 5 most recent shipments (Requirement 3.3)
if ($pdo !== null) {
  try {
    $shipmentRepo = new ShipmentRepository($pdo);
    $recentShipments = $shipmentRepo->getRecentByUserId($userId, 5);
  } catch (\Exception $e) {
    $shipmentsError = true;
  }
}

// Load notification data (Requirement 3.4)
if ($pdo !== null) {
  try {
    $notificationRepo = new NotificationRepository($pdo);
    $notificationService = new NotificationService($notificationRepo);
    $formattedUnreadCount = $notificationService->getFormattedUnreadCount($userId);
    $unreadCount = $notificationService->getUnreadCount($userId);
    $recentNotifications = $notificationService->getRecentNotifications($userId, 3);
  } catch (\Exception $e) {
    $notificationsError = true;
  }
}

$pageTitle = 'Dashboard - Aurum Vault Logistics';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Dashboard</h1>

  <!-- Portfolio Summary Cards (Requirement 3.1, 3.2) -->
  <?php if ($portfolioError): ?>
  <div class="alert alert-warning" role="alert">
    Portfolio data is temporarily unavailable. Please try again later.
  </div>
  <?php else: ?>
  <div class="row g-4 mb-5">
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Portfolio Value</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput(formatCurrency($portfolioSummary['total_value'])) ?>
          </p>
          <small class="text-secondary">USD</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Total Gold Weight</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput(number_format($portfolioSummary['total_weight'], 3)) ?>
          </p>
          <small class="text-secondary">troy oz</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Insured Value</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput(formatCurrency($portfolioSummary['total_insured_value'])) ?>
          </p>
          <small class="text-secondary">USD</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body text-center p-4">
          <h2 class="h6 text-secondary mb-2">Vault Locations</h2>
          <p class="display-6 fw-bold mb-0" style="color: #c9a227;">
            <?= sanitizeOutput((string) $portfolioSummary['vault_locations']) ?>
          </p>
          <small class="text-secondary"><?= $portfolioSummary['item_count'] ?> items</small>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Recent Shipments (Requirement 3.3) -->
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="h5 mb-0" style="color: #c9a227;">Recent Shipments</h2>
          <a href="/client/shipments.php" class="btn btn-sm btn-outline-light">View All</a>
        </div>
        <div class="card-body">
          <?php if ($shipmentsError): ?>
          <div class="alert alert-warning mb-0" role="alert">
            Shipment data is temporarily unavailable. Please try again later.
          </div>
          <?php elseif (empty($recentShipments)): ?>
          <p class="text-secondary text-center mb-0 py-3">No shipments yet.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
              <thead>
                <tr>
                  <th scope="col">Shipment ID</th>
                  <th scope="col">Status</th>
                  <th scope="col">Last Updated</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentShipments as $shipment): ?>
                <tr>
                  <td>#<?= sanitizeOutput((string) $shipment['id']) ?></td>
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
                    $statusLabel = str_replace('_', ' ', $shipment['status']);
                    $badgeClass = $statusClasses[$shipment['status']] ?? 'bg-secondary';
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= sanitizeOutput(ucwords($statusLabel)) ?></span>
                  </td>
                  <td><?= sanitizeOutput(formatDateTime($shipment['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Notifications (Requirement 3.4) -->
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="h5 mb-0" style="color: #c9a227;">
            Notifications
            <?php if (!$notificationsError && $unreadCount > 0): ?>
            <span class="badge rounded-pill ms-2" style="background-color: #c9a227; color: #1a1a1a; font-size: 0.7rem;">
              <?= sanitizeOutput($formattedUnreadCount) ?> unread
            </span>
            <?php endif; ?>
          </h2>
          <a href="/client/notifications.php" class="btn btn-sm btn-outline-light">View All</a>
        </div>
        <div class="card-body">
          <?php if ($notificationsError): ?>
          <div class="alert alert-warning mb-0" role="alert">
            Notification data is temporarily unavailable. Please try again later.
          </div>
          <?php elseif (empty($recentNotifications)): ?>
          <p class="text-secondary text-center mb-0 py-3">No notifications.</p>
          <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($recentNotifications as $notification): ?>
            <li class="list-group-item d-flex align-items-start gap-2 <?= $notification['read_status'] === 'unread' ? 'border-start border-3' : '' ?>" <?= $notification['read_status'] === 'unread' ? 'style="border-left-color: #c9a227 !important;"' : '' ?>>
              <div class="flex-grow-1">
                <p class="mb-1 <?= $notification['read_status'] === 'unread' ? 'fw-bold' : 'text-secondary' ?>">
                  <?= sanitizeOutput($notification['message']) ?>
                </p>
                <small class="text-secondary">
                  <?= sanitizeOutput(formatDateTime($notification['created_at'])) ?>
                </small>
              </div>
              <?php if ($notification['read_status'] === 'unread'): ?>
              <span class="badge rounded-pill" style="background-color: #c9a227; color: #1a1a1a; font-size: 0.6rem;">New</span>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
