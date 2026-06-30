<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Vault Storage - Public Page
 *
 * Displays vault storage details, vault locations, and insurance information.
 * Static content (not database-driven).
 *
 * Requirements: 15.1, 15.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Vault Storage - Aurum Vault Logistics';
$currentPage = 'vault-storage';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Page Header -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-surface, #f4f4f4);">
  <div class="container">
    <h1 class="display-5 fw-bold" style="color: #c9a227;">Vault Storage</h1>
    <p class="lead">World-class security for your precious metals.</p>
  </div>
</section>

<!-- Storage Details -->
<section class="py-5">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-6">
        <h2 class="mb-4" style="color: #c9a227;">Secure Storage Solutions</h2>
        <p >
          Our vault facilities are designed to the highest security standards, providing a safe and controlled environment for your gold bars, coins, grains, and rounds. Each item is individually catalogued with a unique serial number and stored in climate-controlled conditions.
        </p>
        <p >
          We support storage of multiple gold types including bars, coins, grains, and rounds, with precise weight and purity tracking for every item in your portfolio.
        </p>
      </div>
      <div class="col-lg-6">
        <div class="card p-4">
          <h3 class="h5 mb-3">Storage Features</h3>
          <ul class="list-unstyled">
            <li class="mb-3"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Individual serial number tracking for each item</li>
            <li class="mb-3"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Climate-controlled vault environments</li>
            <li class="mb-3"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>24/7 armed security and surveillance</li>
            <li class="mb-3"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Biometric access controls</li>
            <li class="mb-3"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Regular third-party audits</li>
            <li class="mb-3"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Online portfolio management dashboard</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Vault Locations -->
<section class="py-5" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Vault Locations</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center p-5">
            <div class="mb-3">
              <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="8" y="16" width="40" height="32" rx="2"/>
                <path d="M8 24h40"/>
                <rect x="20" y="30" width="16" height="12" rx="1"/>
                <circle cx="28" cy="36" r="3"/>
                <path d="M16 16V10a12 12 0 0 1 24 0v6"/>
              </svg>
            </div>
            <h3 class="h5">Primary Vault</h3>
            <p class="card-text text-secondary">
              Our flagship facility featuring the highest level of security infrastructure and capacity for large holdings.
            </p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center p-5">
            <div class="mb-3">
              <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="20" width="22" height="28" rx="2"/>
                <rect x="30" y="20" width="22" height="28" rx="2"/>
                <path d="M15 20V12a13 13 0 0 1 26 0v8"/>
                <circle cx="15" cy="34" r="3"/>
                <circle cx="41" cy="34" r="3"/>
              </svg>
            </div>
            <h3 class="h5">Secondary Vault</h3>
            <p class="card-text text-secondary">
              A redundant storage facility providing geographic diversification for your precious metals portfolio.
            </p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center p-5">
            <div class="mb-3">
              <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M28 4L6 14v14c0 14 10 24 22 28 12-4 22-14 22-28V14L28 4z"/>
                <rect x="18" y="22" width="20" height="16" rx="2"/>
                <circle cx="28" cy="30" r="3"/>
                <path d="M28 33v3"/>
              </svg>
            </div>
            <h3 class="h5">High-Security Vault</h3>
            <p class="card-text text-secondary">
              Specialized facility for high-value holdings with enhanced security protocols and dedicated monitoring.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Insurance Information -->
<section class="py-5">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Insurance Coverage</h2>
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <p >
          All gold stored in our vaults is eligible for comprehensive insurance coverage. Our insurance policies protect against theft, natural disasters, and other unforeseen events, giving you complete peace of mind.
        </p>
        <p >
          Insurance status is tracked individually for each item in your portfolio, and you can view your coverage details at any time through your client dashboard.
        </p>
      </div>
      <div class="col-lg-6">
        <div class="card p-4">
          <h3 class="h5 mb-3">Coverage Includes</h3>
          <ul class="list-unstyled">
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Protection against theft and burglary</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Natural disaster coverage</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Fire and flood protection</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Full replacement value coverage</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Transit insurance for shipments</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container">
    <h2 class="mb-3" style="color: #c9a227;">Start Storing Your Gold Securely</h2>
    <p class="mb-4">Open an account today and experience world-class vault security.</p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="/register.php" class="btn btn-lg px-4" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Register Now</a>
      <a href="/pricing.php" class="btn btn-lg btn-outline-light px-4">View Pricing</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
