<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Invoices Page
 *
 * Displays a paginated list of invoices belonging to the authenticated client.
 * Shows invoice number, amount, status, creation date, and action buttons
 * (download PDF, pay for unpaid invoices).
 *
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
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
$pageTitle = 'My Invoices - Aurum Vault Logistics';

// Get current page from query string
$page = max(1, (int) ($_GET['page'] ?? 1));

// Load invoices via InvoiceService (Requirement 9.1)
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

$result = $invoiceService->getClientInvoices($userId, $page);
$invoices = $result['data'] ?? [];
$totalRecords = $result['total'] ?? 0;
$perPage = 20;
$totalPages = getTotalPages($totalRecords, $perPage);

// Get unread notification count for nav
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';
use GOLS\Repositories\NotificationRepository;
use GOLS\Services\NotificationService;
$notificationRepository = new NotificationRepository($pdo);
$notificationService = new NotificationService($notificationRepository);
$unreadCount = $notificationService->getUnreadCount($userId);

// Include header template
require_once __DIR__ . '/../includes/templates/header.php';

// Include client navigation
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<!-- Client Invoices Page Content -->
<main class="container py-5">
  <h1 class="mb-4" style="color: #c9a227;">My Invoices</h1>

  <?php if (empty($invoices)): ?>
    <!-- Empty state -->
    <div class="card">
      <div class="card-body text-center py-5">
        <p class="text-secondary mb-0">No invoices yet.</p>
      </div>
    </div>
  <?php else: ?>
    <!-- Invoices Table -->
    <div class="table-responsive">
      <table class="table table-dark table-hover">
        <thead>
          <tr class="border-secondary">
            <th scope="col">Invoice Number</th>
            <th scope="col">Amount</th>
            <th scope="col">Status</th>
            <th scope="col">Created Date</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $invoice): ?>
          <tr class="border-secondary">
            <td><?= sanitizeOutput($invoice['invoice_number']) ?></td>
            <td><?= sanitizeOutput(formatCurrency((float) $invoice['amount'])) ?></td>
            <td>
              <?php if ($invoice['status'] === 'paid'): ?>
                <span class="badge bg-success">Paid</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Unpaid</span>
              <?php endif; ?>
            </td>
            <td><?= sanitizeOutput(formatDate($invoice['created_at'])) ?></td>
            <td>
              <a href="/client/invoice-download.php?invoice_id=<?= (int) $invoice['id'] ?>" class="btn btn-sm btn-outline-light me-1" title="Download PDF">
                Download PDF
              </a>
              <?php if ($invoice['status'] === 'unpaid'): ?>
                <a href="/client/payment.php?invoice_id=<?= (int) $invoice['id'] ?>" class="btn btn-sm" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;" title="Pay Invoice">
                  Pay
                </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Invoice pagination">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="/client/invoices.php?page=<?= $page - 1 ?>" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
          </a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link <?= $i === $page ? '' : 'border-secondary' ?>" href="/client/invoices.php?page=<?= $i ?>" <?= $i === $page ? 'style="background-color: #c9a227; border-color: #c9a227; color: #1a1a1a;"' : '' ?>>
            <?= $i ?>
          </a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="/client/invoices.php?page=<?= $page + 1 ?>" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
          </a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php
// Include footer template
require_once __DIR__ . '/../includes/templates/footer.php';
?>
