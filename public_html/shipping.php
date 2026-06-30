<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Shipping - Public Page
 *
 * Displays shipping process, insurance coverage, and delivery methods.
 * Static content (not database-driven).
 *
 * Requirements: 15.1, 15.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Shipping - Aurum Vault Logistics';
$currentPage = 'shipping';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Page Header -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-surface, #f4f4f4);">
  <div class="container">
    <h1 class="display-5 fw-bold" style="color: #c9a227;">Insured Shipping</h1>
    <p class="lead">Safe, tracked, and fully insured delivery of your precious metals.</p>
  </div>
</section>

<!-- Shipping Process -->
<section class="py-5">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">How Shipping Works</h2>
    <div class="row g-4">
      <div class="col-md-3 col-sm-6">
        <div class="text-center p-3">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background-color: #c9a227; color: #1a1a1a; font-weight: bold; font-size: 1.5rem;">1</div>
          <h4 class="h6">Request Shipment</h4>
          <p class="text-secondary small">Select items from your vault inventory and provide a destination address.</p>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="text-center p-3">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background-color: #c9a227; color: #1a1a1a; font-weight: bold; font-size: 1.5rem;">2</div>
          <h4 class="h6">Admin Approval</h4>
          <p class="text-secondary small">Our team reviews and approves your shipment request for security verification.</p>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="text-center p-3">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background-color: #c9a227; color: #1a1a1a; font-weight: bold; font-size: 1.5rem;">3</div>
          <h4 class="h6">Secure Dispatch</h4>
          <p class="text-secondary small">Your gold is securely packaged and dispatched with a trusted carrier.</p>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="text-center p-3">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; background-color: #c9a227; color: #1a1a1a; font-weight: bold; font-size: 1.5rem;">4</div>
          <h4 class="h6">Track &amp; Receive</h4>
          <p class="text-secondary small">Monitor your shipment in real-time until safe delivery is confirmed.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Delivery Methods / Carriers -->
<section class="py-5" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Trusted Carriers</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center p-4">
            <h3 class="card-title h5" style="color: #c9a227;">DHL</h3>
            <p class="card-text">
              Global express shipping with comprehensive tracking and secure handling protocols for valuable cargo.
            </p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center p-4">
            <h3 class="card-title h5" style="color: #c9a227;">FedEx</h3>
            <p class="card-text">
              Reliable international delivery with specialized precious metals handling and real-time shipment visibility.
            </p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center p-4">
            <h3 class="card-title h5" style="color: #c9a227;">Brinks</h3>
            <p class="card-text">
              Industry-leading secure transport specifically designed for precious metals and high-value shipments.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Insurance Coverage -->
<section class="py-5">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Shipping Insurance</h2>
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <p >
          Every shipment can be fully insured for the total value of the gold being transported. Our insurance coverage protects against loss, theft, and damage during transit.
        </p>
        <p >
          When you create a shipment request, the insured value is automatically calculated based on the items you select. You can choose to add insurance coverage before confirming your request.
        </p>
      </div>
      <div class="col-lg-6">
        <div class="card p-4">
          <h3 class="h5 mb-3">Insurance Benefits</h3>
          <ul class="list-unstyled">
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Full replacement value coverage</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Coverage from vault to destination</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Protection against loss, theft, and damage</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Automatic value calculation</li>
            <li class="mb-2"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#a08c4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M2 8l4 4 8-8"/></svg>Claims processed within business days</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Shipment Status Tracking -->
<section class="py-5" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Shipment Tracking</h2>
    <p class="text-center mb-4">Track your shipment through every stage of the delivery process.</p>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card p-4">
          <div class="d-flex flex-wrap justify-content-between text-center">
            <div class="p-2">
              <div class="small text-secondary">Pending Approval</div>
            </div>
            <div class="p-2">
              <div class="small" style="color: #c9a227;">&#x2192;</div>
            </div>
            <div class="p-2">
              <div class="small text-secondary">Approved</div>
            </div>
            <div class="p-2">
              <div class="small" style="color: #c9a227;">&#x2192;</div>
            </div>
            <div class="p-2">
              <div class="small text-secondary">Ready for Shipment</div>
            </div>
            <div class="p-2">
              <div class="small" style="color: #c9a227;">&#x2192;</div>
            </div>
            <div class="p-2">
              <div class="small text-secondary">In Transit</div>
            </div>
            <div class="p-2">
              <div class="small" style="color: #c9a227;">&#x2192;</div>
            </div>
            <div class="p-2">
              <div class="small text-secondary">Delivered</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="py-5 text-center">
  <div class="container">
    <h2 class="mb-3" style="color: #c9a227;">Ready to Ship Your Gold?</h2>
    <p class="mb-4">Create an account to request secure, insured shipments of your precious metals.</p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="/register.php" class="btn btn-lg px-4" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Register Now</a>
      <a href="/pricing.php" class="btn btn-lg btn-outline-light px-4">View Pricing</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
