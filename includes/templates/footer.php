<?php
/**
 * Shared footer template for the AVL platform.
 *
 * Outputs the footer content, Bootstrap 5 JS bundle, validation script,
 * and any page-specific JavaScript files.
 *
 * Variables:
 *  $pageScripts (array) - Optional array of additional JS file paths to include
 */

declare(strict_types=1);
?>
  <!-- Footer -->
  <footer class="site-footer mt-5">
    <div class="container">
      <div class="row g-4 py-5">
        <!-- Logo + Tagline -->
        <div class="col-lg-4 col-md-6">
          <?php $footerSettings = getSiteSettings(); ?>
          <?php if (!empty($footerSettings['logo_path'])): ?>
          <img src="<?= sanitizeOutput($footerSettings['logo_path']) ?>" alt="<?= sanitizeOutput($footerSettings['site_name']) ?>" style="height: 60px; width: auto;" class="mb-3">
          <?php endif; ?>
          <p class="text-secondary small mb-0"><?= sanitizeOutput($footerSettings['site_tagline'] ?: 'Secure Gold Storage & Insured Shipping Services') ?></p>
        </div>

        <!-- Quick Links -->
        <div class="col-lg-2 col-md-6">
          <h6 class="footer-heading">Services</h6>
          <ul class="footer-links">
            <li><a href="/vault-storage.php">Vault Storage</a></li>
            <li><a href="/shipping.php">Shipping</a></li>
            <li><a href="/pricing.php">Request Quote</a></li>
          </ul>
        </div>

        <!-- Company Links -->
        <div class="col-lg-2 col-md-6">
          <h6 class="footer-heading">Company</h6>
          <ul class="footer-links">
            <li><a href="/about.php">About Us</a></li>
            <li><a href="/faq.php">FAQ</a></li>
            <li><a href="/contact.php">Contact</a></li>
          </ul>
        </div>

        <!-- Contact Info -->
        <div class="col-lg-4 col-md-6">
          <h6 class="footer-heading">Get in Touch</h6>
          <ul class="footer-links">
            <li>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <a href="mailto:info@aurumvault.com">info@aurumvault.com</a>
            </li>
            <li>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span class="text-secondary">Secure Vault District, Financial Quarter</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="footer-bottom py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
          <p class="small text-secondary mb-0">&copy; <?= date('Y') ?> Aurum Vault Logistics. All rights reserved.</p>
          <div class="d-flex gap-3">
            <a href="#" class="small text-secondary text-decoration-none">Privacy Policy</a>
            <a href="#" class="small text-secondary text-decoration-none">Terms of Service</a>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap 5.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Sticky nav scroll effect -->
  <script>
  (function() {
    var nav = document.querySelector('.sticky-nav');
    if (nav) {
      window.addEventListener('scroll', function() {
        if (window.scrollY > 10) {
          nav.classList.add('scrolled');
          document.body.classList.add('scrolled');
        } else {
          nav.classList.remove('scrolled');
          document.body.classList.remove('scrolled');
        }
      });
    }
  })();
  </script>
  <!-- Validation JS -->
  <script src="/assets/js/validation.js"></script>
<?php if (!empty($pageScripts ?? [])): ?>
<?php foreach ($pageScripts as $script): ?>
  <script src="<?= sanitizeOutput($script) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
