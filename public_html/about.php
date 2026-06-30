<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * About Us - Public Page
 *
 * Displays company history, mission statement, and leadership section.
 * Static content (not database-driven).
 *
 * Requirements: 15.1, 15.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'About Us - Aurum Vault Logistics';
$currentPage = 'about';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Page Header -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-surface, #f4f4f4);">
  <div class="container">
    <h1 class="display-5 fw-bold">About Aurum Vault Logistics</h1>
    <p class="lead text-secondary">Trusted custodians of precious metals since our founding.</p>
  </div>
</section>

<!-- Company History -->
<section class="py-5">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <h2 class="mb-4">Our History</h2>
        <p>
          Aurum Vault Logistics was founded with a singular mission: to provide the most secure and reliable precious metals custody services available. From our humble beginnings, we have grown into a trusted partner for gold investors, bullion traders, and institutional clients worldwide.
        </p>
        <p>
          Over the years, we have continuously invested in state-of-the-art vault infrastructure, advanced security systems, and insured logistics partnerships to ensure your gold is protected at every stage.
        </p>
      </div>
      <div class="col-lg-6">
        <img src="/assets/img/Vault_Facility.png" alt="Vault Facility" class="img-fluid rounded" style="height: 360px; width: 100%; object-fit: cover;">
      </div>
    </div>
  </div>
</section>

<!-- Mission Statement -->
<section class="py-5" style="background-color: var(--gv-bg-surface, #f4f4f4);">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5">
        <img src="/assets/img/office.jpg" alt="Team Office" class="img-fluid rounded" style="height: 320px; width: 100%; object-fit: cover;">
      </div>
      <div class="col-lg-7">
        <h2 class="mb-4">Our Mission</h2>
        <p class="lead" style="max-width: 600px;">
          To provide uncompromising security, transparency, and convenience for precious metals storage and shipping, empowering our clients to invest with confidence.
        </p>
        <div class="row g-4 mt-3">
      <div class="col-md-4">
        <div class="p-3">
          <div class="mb-3">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="24" cy="24" r="18"/>
              <circle cx="24" cy="24" r="6"/>
              <path d="M24 6v4M24 38v4M6 24h4M38 24h4"/>
            </svg>
          </div>
          <h4 class="h6">Security First</h4>
          <p class="text-secondary small">Every decision we make prioritizes the safety of your assets.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 text-center">
          <div class="mb-3">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 28c-6 0-12 3-12 8v4h24v-4c0-5-6-8-12-8z"/>
              <circle cx="16" cy="16" r="8"/>
              <path d="M32 28c4 0 8 2 8 6v4H34"/>
              <circle cx="32" cy="16" r="6"/>
            </svg>
          </div>
          <h4 class="h6">Client Trust</h4>
          <p class="text-secondary small">Building lasting relationships through transparency and reliability.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 text-center">
          <div class="mb-3">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M24 4v8M24 36v8M4 24h8M36 24h8"/>
              <path d="M10 10l6 6M32 32l6 6M10 38l6-6M32 16l6-6"/>
              <circle cx="24" cy="24" r="8"/>
            </svg>
          </div>
          <h4 class="h6">Innovation</h4>
          <p class="text-secondary small">Continuously improving our technology and processes.</p>
        </div>
      </div>
    </div>
      </div>
    </div>
  </div>
</section>

<!-- Leadership -->
<section class="py-5">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Leadership Team</h2>
    <div class="row g-4 justify-content-center">
      <div class="col-md-4">
        <div class="card text-center p-5 h-100">
          <div class="mb-3">
            <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="28" cy="18" r="10"/>
              <path d="M12 48c0-9 7-16 16-16s16 7 16 16"/>
              <path d="M28 34v6M22 38h12"/>
            </svg>
          </div>
          <h4 class="h5">Chief Executive Officer</h4>
          <p class="text-secondary small">Leading our vision for secure precious metals custody with decades of industry experience.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-center p-5 h-100">
          <div class="mb-3">
            <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="28" cy="18" r="10"/>
              <path d="M12 48c0-9 7-16 16-16s16 7 16 16"/>
              <path d="M28 4L20 12v6c0 4 3.5 8 8 8s8-4 8-8v-6L28 4z"/>
            </svg>
          </div>
          <h4 class="h5">Chief Security Officer</h4>
          <p class="text-secondary small">Overseeing vault security operations and ensuring the highest standards of asset protection.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-center p-5 h-100">
          <div class="mb-3">
            <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="28" cy="18" r="10"/>
              <path d="M12 48c0-9 7-16 16-16s16 7 16 16"/>
              <path d="M38 36l6-4M38 42l8 2M18 36l-6-4M18 42l-8 2"/>
            </svg>
          </div>
          <h4 class="h5">Chief Operations Officer</h4>
          <p class="text-secondary small">Managing logistics, shipping partnerships, and day-to-day platform operations.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container">
    <h2 class="mb-3" style="color: #c9a227;">Partner With Us</h2>
    <p class="mb-4">Experience the Aurum Vault Logistics difference. Secure, reliable, and transparent.</p>
    <a href="/register.php" class="btn btn-lg px-4" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Create an Account</a>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
