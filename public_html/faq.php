<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * FAQ - Public Page
 *
 * Displays FAQ entries from the database in an accordion/collapsible format.
 * Uses ContentService->getFaqEntries() for dynamic content.
 * Shows an empty state message if no entries exist.
 *
 * Requirements: 15.1, 15.3, 17.2, 17.3, 17.4
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/services/ContentService.php';

use GOLS\Repositories\ContentRepository;
use GOLS\Services\ContentService;

// Load FAQ entries from database
$faqEntries = [];

try {
  $pdo = getDbConnection();
  $contentRepo = new ContentRepository($pdo);
  $contentService = new ContentService($contentRepo);
  $faqEntries = $contentService->getFaqEntries();
} catch (\Exception $e) {
  // If database is unavailable, show empty state
  $faqEntries = [];
}

$pageTitle = 'FAQ - Aurum Vault Logistics';
$currentPage = 'faq';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Page Header -->
<section class="py-5 text-center" style="background-color: var(--gv-bg-surface, #f4f4f4);">
  <div class="container">
    <h1 class="display-5 fw-bold" style="color: #c9a227;">Frequently Asked Questions</h1>
    <p class="lead">Find answers to common questions about our services.</p>
  </div>
</section>

<!-- FAQ Content -->
<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <?php if (!empty($faqEntries)): ?>
          <div class="accordion" id="faqAccordion">
            <?php foreach ($faqEntries as $index => $entry): ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="faqHeading<?= $index ?>">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?= $index ?>" aria-expanded="false" aria-controls="faqCollapse<?= $index ?>">
                    <?= sanitizeOutput($entry['question']) ?>
                  </button>
                </h2>
                <div id="faqCollapse<?= $index ?>" class="accordion-collapse collapse" aria-labelledby="faqHeading<?= $index ?>" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">
                    <?= nl2br(sanitizeOutput($entry['answer'])) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <div class="mb-3">
              <svg width="56" height="56" viewBox="0 0 56 56" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="28" cy="28" r="22"/>
                <path d="M20 22a8 8 0 0 1 16 0c0 4-4 5-4 10h-8c0-5-4-6-4-10z"/>
                <path d="M24 38h8"/>
              </svg>
            </div>
            <h3>No FAQ Entries Yet</h3>
            <p class="text-secondary">Check back soon for answers to frequently asked questions about our services.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Contact CTA -->
<section class="py-5" style="background-color: var(--gv-bg-section, #f4f4f4);">
  <div class="container text-center">
    <h2 class="mb-3" style="color: #c9a227;">Still Have Questions?</h2>
    <p class="mb-4">Our team is here to help. Reach out and we will get back to you promptly.</p>
    <a href="/contact.php" class="btn btn-lg px-4" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Contact Us</a>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
