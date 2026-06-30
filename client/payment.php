<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Payment Page - Wire Transfer Request
 *
 * Allows clients to request wire transfer payment for unpaid invoices.
 * Displays bank details and records the payment request for admin confirmation.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/repositories/InvoiceRepository.php';
require_once __DIR__ . '/../includes/repositories/PaymentRepository.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/validators/InvoiceValidator.php';
require_once __DIR__ . '/../includes/services/InvoiceService.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Repositories\NotificationRepository;
use GOLS\Validators\InvoiceValidator;
use GOLS\Services\InvoiceService;
use GOLS\Services\NotificationService;

// Require authenticated client
requireClient();

$userId = getCurrentUserId();
$pageTitle = 'Payment - Aurum Vault Logistics';
$errorMessage = '';
$successMessage = '';
$wireRequestSubmitted = false;

// Initialize services
$pdo = getDbConnection();
$invoiceRepository = new InvoiceRepository($pdo);
$paymentRepository = new PaymentRepository($pdo);
$userRepository = new UserRepository($pdo);
$notificationRepo = new NotificationRepository($pdo);
$notificationService = new NotificationService($notificationRepo);
$invoiceService = new InvoiceService(
  $invoiceRepository,
  new InvoiceValidator(),
  $userRepository,
  $paymentRepository
);

// Load wire transfer settings from database
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
use GOLS\Repositories\ContentRepository;
$contentRepo = new ContentRepository($pdo);
$wireData = $contentRepo->getByPageKey('wire_transfer');
$wireSettings = $wireData['content'] ?? [
  'bank_name' => 'Not configured',
  'account_name' => 'Not configured',
  'account_number' => 'Not configured',
  'swift_bic' => '',
  'iban' => '',
  'currency' => 'USD',
  'additional_notes' => '',
];

// Get invoice_id from GET parameter
$invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;

// Load invoice details
$invoice = null;
if ($invoiceId > 0) {
  $invoice = $invoiceService->getInvoiceById($invoiceId);
}

// Verify invoice exists, belongs to user, and is unpaid
$invoiceValid = false;
if ($invoice !== null) {
  if ((int) $invoice['user_id'] === $userId && $invoice['status'] === 'unpaid') {
    $invoiceValid = true;
  } elseif ((int) $invoice['user_id'] !== $userId) {
    $errorMessage = 'You do not have permission to pay this invoice.';
  } elseif ($invoice['status'] === 'paid') {
    $successMessage = 'This invoice has already been paid.';
  }
} elseif ($invoiceId > 0) {
  $errorMessage = 'Invoice not found.';
}

// Handle POST (submit wire transfer request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invoiceValid) {
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Request could not be verified. Please try again.';
  } else {
    $transferRef = sanitizeInput($_POST['transfer_reference'] ?? '');

    if (empty($transferRef)) {
      $errorMessage = 'Please enter your wire transfer reference number.';
    } else {
      // Log the wire transfer request
      $paymentRepository->create([
        'invoice_id' => $invoiceId,
        'gateway' => 'wire_transfer',
        'transaction_id' => $transferRef,
        'amount' => (string) $invoice['amount'],
        'status' => 'pending_confirmation',
        'transaction_date' => date('Y-m-d H:i:s'),
      ]);

      // Notify admins of the wire transfer request
      $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
      foreach ($admins as $admin) {
        $notificationService->create(
          (int) $admin['id'],
          'wire_transfer_request',
          sprintf('Wire transfer request for invoice %s ($%s). Reference: %s',
            $invoice['invoice_number'],
            number_format((float) $invoice['amount'], 2),
            $transferRef
          ),
          $invoiceId,
          'invoice'
        );
      }

      $wireRequestSubmitted = true;
      $successMessage = 'Your wire transfer request has been submitted. Our team will verify the payment and update your invoice once confirmed.';
    }
  }
}

// Get unread notification count for nav
$unreadCount = $notificationRepo->getUnreadCount($userId);

// Render page
include __DIR__ . '/../includes/templates/header.php';
include __DIR__ . '/../includes/templates/nav_client.php';
?>

<div class="container py-4">
  <h1 class="mb-4">Payment</h1>

  <?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger" role="alert">
      <?= sanitizeOutput($errorMessage) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($successMessage)): ?>
    <div class="alert alert-success" role="alert">
      <?= sanitizeOutput($successMessage) ?>
    </div>
  <?php endif; ?>

  <?php if ($invoiceId === 0): ?>
    <div class="alert alert-warning" role="alert">
      No invoice specified. Please select an invoice from your
      <a href="/client/invoices.php" class="alert-link">invoices page</a>.
    </div>
  <?php elseif ($invoiceValid && !$wireRequestSubmitted): ?>
    <!-- Invoice Summary -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Invoice Summary</h5>
      </div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tbody>
            <tr>
              <th scope="row" style="width: 200px;">Invoice Number</th>
              <td><?= sanitizeOutput($invoice['invoice_number'] ?? '') ?></td>
            </tr>
            <tr>
              <th scope="row">Amount Due</th>
              <td class="fw-bold" style="color: var(--gv-gold);">
                <?= sanitizeOutput(formatCurrency((float) $invoice['amount'])) ?>
              </td>
            </tr>
            <tr>
              <th scope="row">Description</th>
              <td><?= sanitizeOutput($invoice['description'] ?? '') ?></td>
            </tr>
            <tr>
              <th scope="row">Status</th>
              <td><span class="badge bg-warning">Unpaid</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Wire Transfer Details -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Wire Transfer Details</h5>
      </div>
      <div class="card-body">
        <p class="text-secondary mb-4">Please transfer the exact amount to the following bank account. Include the invoice number as the payment reference.</p>

        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm table-borderless">
              <tbody>
                <tr>
                  <td class="text-secondary fw-bold">Bank Name</td>
                  <td><?= sanitizeOutput($wireSettings['bank_name']) ?></td>
                </tr>
                <tr>
                  <td class="text-secondary fw-bold">Account Name</td>
                  <td><?= sanitizeOutput($wireSettings['account_name']) ?></td>
                </tr>
                <tr>
                  <td class="text-secondary fw-bold">Account Number</td>
                  <td><code><?= sanitizeOutput($wireSettings['account_number']) ?></code></td>
                </tr>
                <?php if (!empty($wireSettings['swift_bic'])): ?>
                <tr>
                  <td class="text-secondary fw-bold">SWIFT/BIC</td>
                  <td><code><?= sanitizeOutput($wireSettings['swift_bic']) ?></code></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($wireSettings['iban'])): ?>
                <tr>
                  <td class="text-secondary fw-bold">IBAN</td>
                  <td><code><?= sanitizeOutput($wireSettings['iban']) ?></code></td>
                </tr>
                <?php endif; ?>
                <tr>
                  <td class="text-secondary fw-bold">Currency</td>
                  <td><?= sanitizeOutput($wireSettings['currency']) ?></td>
                </tr>
              </tbody>
            </table>
            <?php if (!empty($wireSettings['additional_notes'])): ?>
            <p class="text-secondary small mt-2"><?= nl2br(sanitizeOutput($wireSettings['additional_notes'])) ?></p>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <div class="card" style="background-color: var(--gv-bg-surface, #f4f4f4);">
              <div class="card-body">
                <p class="fw-bold mb-2">Payment Reference:</p>
                <p class="mb-3"><code style="font-size: 1.1rem;"><?= sanitizeOutput($invoice['invoice_number'] ?? '') ?></code></p>
                <p class="fw-bold mb-2">Amount to Transfer:</p>
                <p class="mb-0" style="font-size: 1.3rem; color: var(--gv-gold); font-weight: 700;"><?= sanitizeOutput(formatCurrency((float) $invoice['amount'])) ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Wire Transfer -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Confirm Wire Transfer</h5>
      </div>
      <div class="card-body">
        <p class="text-secondary mb-3">Once you have completed the wire transfer, enter your bank's transaction reference number below. Our team will verify the payment within 1-2 business days.</p>

        <form method="POST" action="/client/payment.php?invoice_id=<?= (int) $invoiceId ?>">
          <?= csrfField() ?>

          <div class="mb-3">
            <label for="transfer_reference" class="form-label fw-bold">Wire Transfer Reference Number</label>
            <input type="text" class="form-control" id="transfer_reference" name="transfer_reference" placeholder="e.g. WT-2026-0524-XXXX" required>
            <div class="form-text">Enter the reference number provided by your bank after completing the transfer.</div>
          </div>

          <button type="submit" class="btn btn-gold btn-lg">
            Submit Wire Transfer Confirmation
          </button>
          <a href="/client/invoices.php" class="btn btn-outline-gold ms-2">Cancel</a>
        </form>
      </div>
    </div>

  <?php elseif ($wireRequestSubmitted): ?>
    <!-- Success state -->
    <div class="card">
      <div class="card-body text-center py-5">
        <div class="mb-3">
          <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="#a08c4a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="32" cy="32" r="28"/>
            <path d="M20 32l8 8 16-16"/>
          </svg>
        </div>
        <h3>Wire Transfer Request Submitted</h3>
        <p class="text-secondary">Our team will verify your payment and update the invoice status within 1-2 business days.</p>
        <a href="/client/invoices.php" class="btn btn-gold mt-3">Back to Invoices</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
include __DIR__ . '/../includes/templates/footer.php';
?>
