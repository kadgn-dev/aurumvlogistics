<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Notifications Page
 *
 * Displays paginated notifications (20/page, reverse chronological)
 * with read/unread indicators, mark individual as read, and "mark all as read".
 *
 * Requirements: 13.2, 13.3, 13.4, 13.6
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';

use GOLS\Repositories\NotificationRepository;
use GOLS\Services\NotificationService;

// Require authenticated client
requireClient();

$userId = (int) $_SESSION['user_id'];

// Initialize service
$pdo = getDbConnection();
$repository = new NotificationRepository($pdo);
$notificationService = new NotificationService($repository);

// Handle POST actions
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token on POST only
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Request could not be verified. Please try again.';
  } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
      $result = $notificationService->markAllAsRead($userId);
      if ($result->success) {
        $successMessage = 'All notifications marked as read.';
      } else {
        $errorMessage = 'Failed to mark notifications as read.';
      }
    } elseif ($action === 'mark_read') {
      $notificationId = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
      if ($notificationId > 0) {
        $result = $notificationService->markAsRead($notificationId, $userId);
        if ($result->success) {
          $successMessage = 'Notification marked as read.';
        } else {
          $errorMessage = $result->errorMessage ?? 'Notification not found.';
        }
      } else {
        $errorMessage = 'Invalid notification ID.';
      }
    }
  }
}

// Get current page
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Load paginated notifications
$notifications = $notificationService->getUserNotifications($userId, $page);
$totalPages = getTotalPages($notifications['total'], $notifications['perPage']);
$unreadCount = $notificationService->getUnreadCount($userId);

// Page setup
$pageTitle = 'Notifications - Aurum Vault Logistics';

// Event type icons/badges mapping
function getEventTypeBadge(string $eventType): array
{
  $badges = [
    'shipment_status' => ['icon' => '📦', 'label' => 'Shipment', 'class' => 'bg-info'],
    'shipment_approved' => ['icon' => '✅', 'label' => 'Approved', 'class' => 'bg-success'],
    'shipment_rejected' => ['icon' => '❌', 'label' => 'Rejected', 'class' => 'bg-danger'],
    'invoice_generated' => ['icon' => '🧾', 'label' => 'Invoice', 'class' => 'bg-warning'],
    'invoice_paid' => ['icon' => '💰', 'label' => 'Payment', 'class' => 'bg-success'],
    'kyc_approved' => ['icon' => '🔐', 'label' => 'KYC', 'class' => 'bg-success'],
    'kyc_rejected' => ['icon' => '🔐', 'label' => 'KYC', 'class' => 'bg-danger'],
    'account_suspended' => ['icon' => '⚠️', 'label' => 'Account', 'class' => 'bg-danger'],
  ];

  return $badges[$eventType] ?? ['icon' => '🔔', 'label' => 'System', 'class' => 'bg-secondary'];
}

include __DIR__ . '/../includes/templates/header.php';
include __DIR__ . '/../includes/templates/nav_client.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0" style="color: #c9a227;">Notifications</h1>
    <?php if (!empty($notifications['data'])): ?>
    <form method="POST" action="/client/notifications.php" class="d-inline">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="btn btn-outline-light btn-sm">
        Mark All as Read
      </button>
    </form>
    <?php endif; ?>
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

  <?php if (empty($notifications['data'])): ?>
  <div class="card">
    <div class="card-body text-center py-5">
      <p class="text-secondary mb-0">No notifications</p>
    </div>
  </div>
  <?php else: ?>
  <div class="list-group">
    <?php foreach ($notifications['data'] as $notification): ?>
      <?php
      $isUnread = ($notification['read_status'] === 'unread');
      $badge = getEventTypeBadge($notification['event_type']);
      ?>
      <div class="list-group-item d-flex align-items-start gap-3 <?= $isUnread ? 'border-start border-3' : '' ?>" style="<?= $isUnread ? 'border-left-color: #c9a227 !important;' : '' ?>">
        <div class="flex-shrink-0 mt-1">
          <span class="badge <?= sanitizeOutput($badge['class']) ?>"><?= sanitizeOutput($badge['icon']) ?> <?= sanitizeOutput($badge['label']) ?></span>
        </div>
        <div class="flex-grow-1">
          <p class="mb-1 <?= $isUnread ? 'fw-bold' : 'text-secondary' ?>">
            <?= sanitizeOutput($notification['message']) ?>
          </p>
          <small class="text-muted">
            <?= sanitizeOutput(formatDateTime($notification['created_at'])) ?>
          </small>
        </div>
        <?php if ($isUnread): ?>
        <div class="flex-shrink-0">
          <form method="POST" action="/client/notifications.php?page=<?= $page ?>" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as read" aria-label="Mark notification as read">
              ✓
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav aria-label="Notifications pagination" class="mt-4">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="/client/notifications.php?page=<?= $page - 1 ?>" aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link <?= $i === $page ? '' : 'border-secondary' ?>" href="/client/notifications.php?page=<?= $i ?>" <?= $i === $page ? 'style="background-color: #c9a227; border-color: #c9a227; color: #1a1a1a;"' : '' ?>>
          <?= $i ?>
        </a>
      </li>
      <?php endfor; ?>
      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="/client/notifications.php?page=<?= $page + 1 ?>" aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/templates/footer.php'; ?>
