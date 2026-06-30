<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Homepage - Public Page
 *
 * Displays hero section, service overview, security highlights, and CTAs.
 * Content is loaded dynamically from the database via ContentService,
 * with static fallback content if database content is unavailable.
 *
 * Requirements: 15.1, 15.2, 15.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/services/ContentService.php';

use GOLS\Repositories\ContentRepository;
use GOLS\Services\ContentService;

// Load dynamic content from database
$heroText = '';
$serviceDescriptions = '';
$securityHighlights = '';

try {
  $pdo = getDbConnection();
  $contentRepo = new ContentRepository($pdo);
  $contentService = new ContentService($contentRepo);
  $homepageContent = $contentService->getHomepageContent();

  if (!empty($homepageContent)) {
    $heroText = $homepageContent['hero_text'] ?? '';
    $serviceDescriptions = $homepageContent['service_descriptions'] ?? '';
    $securityHighlights = $homepageContent['security_highlights'] ?? '';
  }
} catch (\Exception $e) {
  // Fallback to static content if database is unavailable
  $homepageContent = [];
}

$pageTitle = 'Aurum Vault Logistics - Secure Gold Storage & Insured Shipping';
$currentPage = 'home';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Hero Section - Full-width dark with background -->
<section class="hero">
  <div class="hero-overlay"></div>
  <div class="container hero-content">
    <p class="hero-subtitle">SECURE PRECIOUS METALS CUSTODY</p>
    <h1 class="hero-title">
      <?php if (!empty($heroText)): ?>
        <?= sanitizeOutput($heroText) ?>
      <?php else: ?>
        <span class="typewriter" data-text="The safest place for your gold"></span>
      <?php endif; ?>
    </h1>
  </div>
  <!-- Animated scroll arrow -->
  <a href="#services" class="hero-scroll-arrow" aria-label="Scroll down">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M6 9l6 6 6-6"/>
    </svg>
  </a>
</section>

<!-- Service Overview -->
<section class="py-5" id="services">
  <div class="container">
    <h2 class="text-center mb-5">Our Services</h2>
    <?php if (!empty($serviceDescriptions)): ?>
      <div class="row g-4">
        <div class="col-12">
          <p><?= sanitizeOutput($serviceDescriptions) ?></p>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body text-center p-5">
              <div class="mb-4">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="8" y="24" width="48" height="36" rx="3"/>
                  <path d="M16 24V16a16 16 0 0 1 32 0v8"/>
                  <circle cx="32" cy="42" r="6"/>
                  <path d="M32 48v4"/>
                </svg>
              </div>
              <h3 class="h5 mb-3">Vault Storage</h3>
              <p class="card-text">
                State-of-the-art vault facilities with 24/7 surveillance, climate control, and comprehensive insurance coverage for your gold holdings.
              </p>
              <a href="/vault-storage.php" class="btn btn-outline-gold btn-sm mt-2">Learn More</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body text-center p-5">
              <div class="mb-4">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="4" y="28" width="40" height="24" rx="2"/>
                  <path d="M10 28v-6a2 2 0 0 1 2-2h20a2 2 0 0 1 2 2v6"/>
                  <circle cx="24" cy="40" r="4"/>
                  <path d="M44 36h12a4 4 0 0 1 4 4v12a4 4 0 0 1-4 4H44"/>
                  <path d="M48 32l8-8M52 24h6M58 24v6"/>
                </svg>
              </div>
              <h3 class="h5 mb-3">Insured Shipping</h3>
              <p class="card-text">
                Fully insured global shipping through trusted carriers including DHL, FedEx, and Brinks with real-time tracking.
              </p>
              <a href="/shipping.php" class="btn btn-outline-gold btn-sm mt-2">Learn More</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body text-center p-5">
              <div class="mb-4">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M32 4L6 16v16c0 16 11 28 26 32 15-4 26-16 26-32V16L32 4z"/>
                  <path d="M22 34l6 6 14-14"/>
                </svg>
              </div>
              <h3 class="h5 mb-3">Full Insurance</h3>
              <p class="card-text">
                Comprehensive insurance coverage for all stored and shipped gold, protecting your investment at every stage.
              </p>
              <a href="/pricing.php" class="btn btn-outline-gold btn-sm mt-2">View Pricing</a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Security Highlights -->
<section class="py-5" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container">
    <h2 class="text-center mb-5" style="color: #c9a227;">Security You Can Trust</h2>
    <?php if (!empty($securityHighlights)): ?>
      <div class="row">
        <div class="col-12">
          <p ><?= sanitizeOutput($securityHighlights) ?></p>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <div class="col-md-3 col-sm-6">
          <div class="text-center p-4">
            <div class="mb-3">
              <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="6" y="20" width="36" height="24" rx="2"/>
                <path d="M12 20v-6a12 12 0 0 1 24 0v6"/>
                <circle cx="24" cy="32" r="3"/>
                <path d="M24 35v3"/>
              </svg>
            </div>
            <h4 class="h6">Bank-Grade Vaults</h4>
            <p class="text-secondary small">Military-grade security with biometric access controls.</p>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="text-center p-4">
            <div class="mb-3">
              <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="24" cy="24" r="18"/>
                <circle cx="24" cy="24" r="6"/>
                <path d="M24 6v4M24 38v4M6 24h4M38 24h4"/>
              </svg>
            </div>
            <h4 class="h6">24/7 Surveillance</h4>
            <p class="text-secondary small">Round-the-clock monitoring with advanced detection systems.</p>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="text-center p-4">
            <div class="mb-3">
              <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="8" y="6" width="32" height="40" rx="2"/>
                <path d="M16 14h16M16 22h16M16 30h10"/>
                <path d="M30 34l4 4 8-8"/>
              </svg>
            </div>
            <h4 class="h6">Full Audit Trail</h4>
            <p class="text-secondary small">Complete transaction history and inventory tracking.</p>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="text-center p-4">
            <div class="mb-3">
              <svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M24 4L4 14v10c0 14 9 24 20 28 11-4 20-14 20-28V14L24 4z"/>
                <path d="M16 24l6 6 10-10"/>
              </svg>
            </div>
            <h4 class="h6">Insured Holdings</h4>
            <p class="text-secondary small">Comprehensive coverage for all stored precious metals.</p>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Call to Action -->
<section class="py-5 text-center">
  <div class="container">
    <h2 class="mb-3" style="color: #c9a227;">Ready to Secure Your Gold?</h2>
    <p class="mb-4">Join thousands of investors who trust Aurum Vault Logistics for their precious metals storage and shipping needs.</p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="/register.php" class="btn btn-lg px-4" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Get Started</a>
      <a href="/contact.php" class="btn btn-lg btn-outline-light px-4">Contact Us</a>
    </div>
  </div>
</section>

<!-- Typewriter effect for hero -->
<script>
(function() {
  var el = document.querySelector('.typewriter');
  if (!el) return;
  var messages = [
    'The safest place for your gold',
    'Secure storage you can trust',
    'Insured shipping worldwide'
  ];
  var msgIndex = 0;
  var charIndex = 0;
  var typeSpeed = 70;
  var deleteSpeed = 40;
  var pauseAfterType = 2000;
  var pauseAfterDelete = 500;

  function type() {
    var text = messages[msgIndex];
    if (charIndex < text.length) {
      el.textContent += text.charAt(charIndex);
      charIndex++;
      setTimeout(type, typeSpeed);
    } else {
      setTimeout(erase, pauseAfterType);
    }
  }

  function erase() {
    if (charIndex > 0) {
      charIndex--;
      el.textContent = messages[msgIndex].substring(0, charIndex);
      setTimeout(erase, deleteSpeed);
    } else {
      msgIndex = (msgIndex + 1) % messages.length;
      setTimeout(type, pauseAfterDelete);
    }
  }

  setTimeout(type, 1200);
})();
</script>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
