<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Contact Form Page
 *
 * Provides a contact form for guests to submit inquiries.
 * Includes server-side validation, CSRF protection, rate limiting,
 * and SMTP email delivery.
 *
 * Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/validators/ContactValidator.php';
require_once __DIR__ . '/../includes/services/RateLimiter.php';
require_once __DIR__ . '/../includes/services/EmailService.php';

use GOLS\Validators\ContactValidator;
use GOLS\Services\RateLimiter;
use GOLS\Services\EmailService;

// Initialize session (needed for CSRF token)
initSession();

$pageTitle = 'Contact Us - Aurum Vault Logistics';
$successMessage = '';
$errorMessage = '';
$validationErrors = [];
$formData = [
  'name' => '',
  'email' => '',
  'subject' => '',
  'message' => '',
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1. Validate CSRF token
  enforceCsrf();

  // Sanitize input
  $formData = [
    'name' => sanitizeInput($_POST['name'] ?? ''),
    'email' => sanitizeInput($_POST['email'] ?? ''),
    'subject' => sanitizeInput($_POST['subject'] ?? ''),
    'message' => sanitizeInput($_POST['message'] ?? ''),
  ];

  // 2. Check rate limit (3 submissions per IP per hour)
  $pdo = getDbConnection();
  $rateLimiter = new RateLimiter($pdo);
  $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

  if (!$rateLimiter->isAllowed($clientIp, 'contact')) {
    $remainingTime = $rateLimiter->getRemainingTime($clientIp, 'contact');
    $remainingMinutes = (int) ceil($remainingTime / 60);
    $errorMessage = 'You have exceeded the maximum number of submissions. Please try again in ' . $remainingMinutes . ' minute' . ($remainingMinutes !== 1 ? 's' : '') . '.';
  } else {
    // 3. Validate form data via ContactValidator
    $validator = new ContactValidator();
    $validationResult = $validator->validate($formData);

    if (!$validationResult->isValid) {
      $validationErrors = $validationResult->errors;
    } else {
      // 4. Send email via EmailService
      $emailService = new EmailService($pdo);
      $companyEmail = getenv('COMPANY_EMAIL') ?: 'info@aurumvlogistics.com';

      $emailBody = sprintf(
        "<h2>New Contact Form Submission</h2>" .
        "<p><strong>Name:</strong> %s</p>" .
        "<p><strong>Email:</strong> %s</p>" .
        "<p><strong>Subject:</strong> %s</p>" .
        "<p><strong>Message:</strong></p><p>%s</p>",
        sanitizeOutput($formData['name']),
        sanitizeOutput($formData['email']),
        sanitizeOutput($formData['subject']),
        nl2br(sanitizeOutput($formData['message']))
      );

      // Use PHPMailer directly for contact form (no template needed)
      try {
        if (!function_exists('createMailer')) {
          require_once __DIR__ . '/../includes/mailer.php';
        }

        $mailer = createMailer();
        $mailer->addAddress($companyEmail);
        $mailer->addReplyTo($formData['email'], $formData['name']);
        $mailer->Subject = 'Contact Form: ' . $formData['subject'];
        $mailer->Body = $emailBody;
        $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailBody));

        $mailer->send();

        // Record the attempt for rate limiting
        $rateLimiter->recordAttempt($clientIp, 'contact');

        // 5. Display success message
        $successMessage = 'Thank you for your message. We will get back to you shortly.';

        // Clear form data on success
        $formData = [
          'name' => '',
          'email' => '',
          'subject' => '',
          'message' => '',
        ];
      } catch (\Exception $e) {
        // 6. Log details server-side, display generic error
        error_log(sprintf(
          '[Contact Form] SMTP send failed. From: %s, Subject: %s, Error: %s, Time: %s',
          $formData['email'],
          $formData['subject'],
          $e->getMessage(),
          date('Y-m-d H:i:s')
        ));

        $errorMessage = 'We were unable to send your message at this time. Please try again later or contact us directly by email.';
      }
    }
  }
}

// Include header template
require_once __DIR__ . '/../includes/templates/header.php';

// Include public navigation
require_once __DIR__ . '/../includes/templates/nav_public.php';
?>

<!-- Contact Page Content -->
<main class="container py-5">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <h1 class="mb-4" style="color: #c9a227;">Contact Us</h1>
      <p class="text-secondary mb-4">Have questions about our vault storage or shipping services? Get in touch with our team.</p>

      <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert">
          <?= sanitizeOutput($successMessage) ?>
        </div>
      <?php endif; ?>

      <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert">
          <?= sanitizeOutput($errorMessage) ?>
        </div>
      <?php endif; ?>

      <!-- Contact Form -->
      <form method="POST" action="/contact.php" novalidate>
        <?= csrfField() ?>

        <div class="mb-3">
          <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control <?= isset($validationErrors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= sanitizeOutput($formData['name']) ?>" maxlength="100" required>
          <?php if (isset($validationErrors['name'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($validationErrors['name']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" class="form-control <?= isset($validationErrors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= sanitizeOutput($formData['email']) ?>" required>
          <?php if (isset($validationErrors['email'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($validationErrors['email']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
          <input type="text" class="form-control <?= isset($validationErrors['subject']) ? 'is-invalid' : '' ?>" id="subject" name="subject" value="<?= sanitizeOutput($formData['subject']) ?>" maxlength="200" required>
          <?php if (isset($validationErrors['subject'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($validationErrors['subject']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
          <textarea class="form-control <?= isset($validationErrors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" maxlength="5000" required><?= sanitizeOutput($formData['message']) ?></textarea>
          <?php if (isset($validationErrors['message'])): ?>
            <div class="invalid-feedback"><?= sanitizeOutput($validationErrors['message']) ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn px-4 py-2" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">Send Message</button>
      </form>
    </div>
  </div>

  <!-- Contact Information Section -->
  <div class="row mt-5">
    <div class="col-lg-8 mx-auto">
      <hr class="border-secondary">
      <div class="row mt-4">
        <div class="col-md-6 mb-4">
          <h5 style="color: #c9a227;">Get In Touch</h5>
          <p class="text-secondary mb-2">
            <strong>Email:</strong> info@aurumvlogistics.com
          </p>
          <p class="text-secondary">
            <strong>Hours:</strong> Mon-Fri 9:00 AM - 6:00 PM EST
          </p>
        </div>
        <div class="col-md-6 mb-4">
          <h5 style="color: #c9a227;">Our Location</h5>
          <!-- Google Maps Placeholder -->
          <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="height: 200px;" aria-label="Map location placeholder">
            <p class="mb-0"><em>Google Maps will be displayed here</em></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php
// Include footer template
require_once __DIR__ . '/../includes/templates/footer.php';
?>
