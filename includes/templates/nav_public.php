<?php
/**
 * Public navigation template for the AVL platform.
 *
 * Top bar: Login/Register (separate utility bar)
 * Main nav: Site navigation links
 *
 * Renders the site name dynamically from getSiteSettings(). Falls back to
 * "Aurum Vault Logistics" when no Site_Settings record exists in the database.
 *
 * Variables:
 *  $currentPage (string) - The current page identifier for active state highlighting
 *
 * Requirements: 3.2, 3.3, 3.4
 */

declare(strict_types=1);
?>
<!-- Top Utility Bar - Login/Register -->
<div class="topbar">
  <div class="container d-flex justify-content-end align-items-center py-2">
    <a href="/login.php" class="topbar-link me-3">Login</a>
    <a href="/register.php" class="topbar-link topbar-link--register">Register</a>
  </div>
</div>

<!-- Main Navigation -->
<nav class="navbar navbar-expand-lg sticky-nav">
  <div class="container">
    <?php $siteSettings = getSiteSettings(); ?>
    <a class="navbar-brand" href="/index.php">
      <?php if (!empty($siteSettings['logo_path'])): ?>
      <img src="<?= sanitizeOutput($siteSettings['logo_path']) ?>" alt="<?= sanitizeOutput($siteSettings['site_name']) ?>" style="height: 70px; width: auto;">
      <?php endif; ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="publicNav">
      <div class="nav-pill-container ms-auto">
        <ul class="navbar-nav mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link<?= ($currentPage ?? '') === 'home' ? ' active' : '' ?>" href="/index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= ($currentPage ?? '') === 'about' ? ' active' : '' ?>" href="/about.php">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= ($currentPage ?? '') === 'vault-storage' ? ' active' : '' ?>" href="/vault-storage.php">Vault Storage</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= ($currentPage ?? '') === 'shipping' ? ' active' : '' ?>" href="/shipping.php">Shipping</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= ($currentPage ?? '') === 'faq' ? ' active' : '' ?>" href="/faq.php">FAQ</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= ($currentPage ?? '') === 'contact' ? ' active' : '' ?>" href="/contact.php">Contact</a>
          </li>
          <li class="nav-item ms-2">
            <a class="nav-link nav-cta<?= ($currentPage ?? '') === 'pricing' ? ' active' : '' ?>" href="/pricing.php">Request Quote</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>
