<?php
/**
 * Client navigation template for the Aurum Vault Logistics Platform (AVL).
 *
 * Displays a responsive Bootstrap 5 navbar with links to client pages,
 * a notification badge with unread count, and a logout button.
 *
 * Variables:
 *  $unreadCount (int) - Number of unread notifications (set externally)
 */

declare(strict_types=1);

$unreadCount = $unreadCount ?? 0;
$notificationDisplay = $unreadCount > 99 ? '99+' : (string) $unreadCount;
?>
<nav class="navbar navbar-expand-lg navbar-dark border-bottom">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/client/dashboard.php" style="color: #c9a227;">
      Aurum Vault Logistics
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navClient" aria-controls="navClient" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navClient">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="/client/dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/client/inventory.php">Inventory</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/client/shipments.php">Shipments</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/client/tracking.php">Tracking</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/client/invoices.php">Invoices</a>
        </li>
        <li class="nav-item">
          <a class="nav-link position-relative" href="/client/notifications.php">
            Notifications
            <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background-color: #c9a227; color: #1a1a1a;">
              <?= sanitizeOutput($notificationDisplay) ?>
              <span class="visually-hidden">unread notifications</span>
            </span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/client/profile.php">Profile</a>
        </li>
      </ul>
      <div class="d-flex">
        <a href="/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </div>
</nav>
