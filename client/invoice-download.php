<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Invoice PDF Download
 *
 * Generates and downloads a PDF for a specific invoice.
 * Enforces user_id scoping so clients can only download their own invoices.
 * Handles PDF generation failure gracefully with an error message.
 *
 * Requirements: 9.2, 9.3, 9.4, 9.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pdf.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/repositories/InvoiceRepository.php';
require_once __DIR__ . '/../includes/repositories/PaymentRepository.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/validators/InvoiceValidator.php';
require_once __DIR__ . '/../includes/services/InvoiceService.php';

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Validators\InvoiceValidator;
use GOLS\Services\InvoiceService;

// Enforce client role access (Requirement 9.3)
requireClient();

$userId = (int) $_SESSION['user_id'];
$pageTitle = 'Invoice Download - Aurum Vault Logistics';
$errorMessage = '';

// Get invoice_id from GET parameter
$invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;

if ($invoiceId <= 0) {
  $errorMessage = 'Invalid invoice ID. Please select a valid invoice to download.';
} else {
  // Initialize service
  $pdo = getDbConnection();
  $invoiceRepository = new InvoiceRepository($pdo);
  $paymentRepository = new PaymentRepository($pdo);
  $userRepository = new UserRepository($pdo);
  $invoiceValidator = new InvoiceValidator();
  $invoiceService = new InvoiceService(
    $invoiceRepository,
    $invoiceValidator,
    $userRepository,
    $paymentRepository
  );

  // Generate PDF with user_id scoping (Requirement 9.2, 9.3, 9.5)
  $result = $invoiceService->generatePdf($invoiceId, $userId);

  if ($result->success) {
    $filePath = $result->data['file_path'];
    $filename = $result->data['filename'];

    // Verify the file exists before sending
    if (file_exists($filePath)) {
      // Set headers for PDF download
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Content-Length: ' . filesize($filePath));
      header('Cache-Control: no-cache, no-store, must-revalidate');
      header('Pragma: no-cache');
      header('Expires: 0');

      // Output the file
      readfile($filePath);

      // Clean up temp file after sending
      unlink($filePath);
      exit;
    } else {
      $errorMessage = 'The invoice PDF could not be generated. Please try again later.';
    }
  } else {
    // Handle errors: NOT_FOUND, ACCESS_DENIED, PDF_GENERATION_FAILED (Requirement 9.4, 9.5)
    switch ($result->errorCode) {
      case 'NOT_FOUND':
        $errorMessage = 'The requested invoice is not available.';
        break;
      case 'ACCESS_DENIED':
        $errorMessage = 'You do not have permission to download this invoice.';
        break;
      case 'PDF_GENERATION_FAILED':
        $errorMessage = 'The invoice download could not be completed. Please try again later.';
        break;
      default:
        $errorMessage = 'An error occurred while processing your request. Please try again later.';
        break;
    }
  }
}

// If we reach here, there was an error - display error page
// Get unread notification count for nav
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';
use GOLS\Repositories\NotificationRepository;
use GOLS\Services\NotificationService;
$pdo = $pdo ?? getDbConnection();
$notificationRepository = new NotificationRepository($pdo);
$notificationService = new NotificationService($notificationRepository);
$unreadCount = $notificationService->getUnreadCount($userId);

// Include header template
require_once __DIR__ . '/../includes/templates/header.php';

// Include client navigation
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<!-- Invoice Download Error Page -->
<main class="container py-5">
  <h1 class="mb-4" style="color: #c9a227;">Invoice Download</h1>

  <div class="alert alert-danger" role="alert">
    <?= sanitizeOutput($errorMessage) ?>
  </div>

  <a href="/client/invoices.php" class="btn btn-outline-light">
    &larr; Back to Invoices
  </a>
</main>

<?php
// Include footer template
require_once __DIR__ . '/../includes/templates/footer.php';
?>
