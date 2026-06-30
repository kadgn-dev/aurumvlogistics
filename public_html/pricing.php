<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Pricing - Public Page
 *
 * Displays pricing information for storage, shipping, and insurance.
 * Content is loaded dynamically from the database via ContentService,
 * with static fallback content if database content is unavailable.
 *
 * Requirements: 15.1, 15.3, 17.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/services/ContentService.php';

use GOLS\Repositories\ContentRepository;
use GOLS\Services\ContentService;

// Load dynamic pricing content from database
$pricingContent = [];

try {
  $pdo = getDbConnection();
  $contentRepo = new ContentRepository($pdo);
  $contentService = new ContentService($contentRepo);
  $pricingContent = $contentService->getPricingContent();
} catch (\Exception $e) {
  // Fallback to static content if database is unavailable
  $pricingContent = [];
}

$pageTitle = 'Pricing - Aurum Vault Logistics';
$currentPage = 'pricing';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Page Header -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-surface, #f4f4f4);">
  <div class="container">
    <h1 class="display-5 fw-bold" style="color: #c9a227;">Pricing</h1>
    <p class="lead">Transparent pricing for all our services.</p>
  </div>
</section>

<!-- Pricing Content -->
<section class="py-5">
  <div class="container">
    <?php if (!empty($pricingContent)): ?>
      <!-- Dynamic pricing content from database -->
      <?php if (!empty($pricingContent['service_names'])): ?>
        <h2 class="text-center mb-5" style="color: #c9a227;">Our Services</h2>
        <div class="row g-4">
          <?php
          $serviceNames = is_array($pricingContent['service_names'])
            ? $pricingContent['service_names']
            : explode(',', $pricingContent['service_names']);
          $prices = is_array($pricingContent['prices'] ?? [])
            ? $pricingContent['prices']
            : explode(',', $pricingContent['prices'] ?? '');
          $descriptions = is_array($pricingContent['plan_descriptions'] ?? [])
            ? $pricingContent['plan_descriptions']
            : explode(',', $pricingContent['plan_descriptions'] ?? '');
          ?>
          <?php foreach ($serviceNames as $index => $serviceName): ?>
            <div class="col-md-4">
              <div class="card h-100">
                <div class="card-body text-center p-4">
                  <h3 class="card-title h5" style="color: #c9a227;"><?= sanitizeOutput(trim($serviceName)) ?></h3>
                  <?php if (isset($prices[$index])): ?>
                    <p class="display-6 fw-bold my-3"><?= sanitizeOutput(trim($prices[$index])) ?></p>
                  <?php endif; ?>
                  <?php if (isset($descriptions[$index])): ?>
                    <p class="card-text text-secondary"><?= sanitizeOutput(trim($descriptions[$index])) ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <!-- Static fallback pricing content -->
      <h2 class="text-center mb-5" style="color: #c9a227;">Service Pricing</h2>
      <div class="row g-4">
        <!-- Storage Fees -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-center py-3" style="background-color: var(--gv-bg-surface, #f8f8f8); border-bottom: 1px solid #c9a227;">
              <h3 class="h5 mb-0" style="color: #c9a227;">Vault Storage</h3>
            </div>
            <div class="card-body p-4">
              <ul class="list-unstyled">
                <li class="mb-3 d-flex justify-content-between">
                  <span>Gold Bars</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>Gold Coins</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>Gold Grains</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>Gold Rounds</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
              </ul>
              <p class="text-secondary small text-center">Monthly storage fees based on weight and vault location.</p>
            </div>
          </div>
        </div>

        <!-- Shipping Fees -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-center py-3" style="background-color: var(--gv-bg-surface, #f8f8f8); border-bottom: 1px solid #c9a227;">
              <h3 class="h5 mb-0" style="color: #c9a227;">Shipping</h3>
            </div>
            <div class="card-body p-4">
              <ul class="list-unstyled">
                <li class="mb-3 d-flex justify-content-between">
                  <span>DHL Express</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>FedEx Priority</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>Brinks Secure</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
              </ul>
              <p class="text-secondary small text-center">Shipping rates vary by weight, destination, and carrier.</p>
            </div>
          </div>
        </div>

        <!-- Insurance Rates -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-center py-3" style="background-color: var(--gv-bg-surface, #f8f8f8); border-bottom: 1px solid #c9a227;">
              <h3 class="h5 mb-0" style="color: #c9a227;">Insurance</h3>
            </div>
            <div class="card-body p-4">
              <ul class="list-unstyled">
                <li class="mb-3 d-flex justify-content-between">
                  <span>Vault Storage</span>
                  <span class="fw-bold" style="color: #c9a227;">Included</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>Transit Coverage</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
                <li class="mb-3 d-flex justify-content-between">
                  <span>Full Replacement</span>
                  <span class="fw-bold" style="color: #c9a227;">Contact Us</span>
                </li>
              </ul>
              <p class="text-secondary small text-center">Insurance rates based on declared value of holdings.</p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Additional Info -->
<section class="py-5" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container text-center">
    <h2 class="mb-4" style="color: #c9a227;">Custom Pricing Available</h2>
    <p class="mx-auto" style="max-width: 600px;">
      For institutional clients or large holdings, we offer custom pricing packages tailored to your specific needs. Contact our team for a personalized quote.
    </p>
    <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
      <a href="/contact.php" class="btn btn-lg px-4" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Get a Quote</a>
      <a href="/register.php" class="btn btn-lg btn-outline-light px-4">Create Account</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
