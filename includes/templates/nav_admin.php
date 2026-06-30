<?php
/**
 * Admin navigation template for the AVL platform.
 *
 * Displays a responsive Bootstrap 5 navbar with links to admin pages
 * and a logout button.
 */

declare(strict_types=1);
?>
<nav class="navbar navbar-expand-lg navbar-dark border-bottom">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/admin/dashboard.php" style="color: #c9a227;">
      Aurum Vault Logistics <span class="badge bg-secondary fs-6">Admin</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin" aria-controls="navAdmin" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navAdmin">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="/admin/dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/users.php">Users</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/inventory.php">Inventory</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/shipments.php">Shipments</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/invoices.php">Invoices</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/content.php">Content</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/site-settings.php">Settings</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/audit-log.php">Audit Log</a>
        </li>
      </ul>
      <div class="d-flex">
        <a href="/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </div>
</nav>
