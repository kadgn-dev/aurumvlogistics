<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Invoices Management - Create, Mark Paid, List All Invoices
 *
 * Create invoice form (client selection, amount, description),
 * mark as paid action, and list all invoices with pagination.
 *
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 17.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/repositories/InvoiceRepository.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/repositories/PaymentRepository.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/repositories/ContentRepository.php';
require_once __DIR__ . '/../includes/repositories/InventoryRepository.php';
require_once __DIR__ . '/../includes/services/InvoiceService.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';
require_once __DIR__ . '/../includes/services/EmailService.php';
require_once __DIR__ . '/../includes/services/AuditService.php';
require_once __DIR__ . '/../includes/validators/InvoiceValidator.php';

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Repositories\NotificationRepository;
use GOLS\Repositories\ContentRepository;
use GOLS\Repositories\InventoryRepository;
use GOLS\Services\InvoiceService;
use GOLS\Services\NotificationService;
use GOLS\Services\EmailService;
use GOLS\Services\AuditService;
use GOLS\Validators\InvoiceValidator;

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$invoiceRepo = new InvoiceRepository($pdo);
$userRepo = new UserRepository($pdo);
$paymentRepo = new PaymentRepository($pdo);
$notificationRepo = new NotificationRepository($pdo);
$contentRepo = new ContentRepository($pdo);
$notificationService = new NotificationService($notificationRepo);
$emailService = new EmailService($pdo);
$invoiceValidator = new InvoiceValidator();
$invoiceService = new InvoiceService($invoiceRepo, $invoiceValidator, $userRepo, $paymentRepo, $notificationService, $emailService);
$auditService = new AuditService($pdo);
$adminId = getCurrentUserId();

$successMessage = '';
$errorMessage = '';
$formErrors = [];

// Get all clients for the dropdown
$clientsStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role = 'client' ORDER BY name ASC");
$clientsStmt->execute();
$clients = $clientsStmt->fetchAll(\PDO::FETCH_ASSOC);

// Load invoice description presets
$descData = $contentRepo->getByPageKey('invoice_descriptions');
$descriptionPresets = $descData['content'] ?? [];

// Load fee amounts for auto-fill
$feeData = $contentRepo->getByPageKey('fee_amounts');
$feeAmounts = $feeData['content'] ?? [];
// Build a lookup map: description name => amount
$feeAmountMap = [];
foreach ($feeAmounts as $fee) {
  if (isset($fee['name'], $fee['amount'])) {
    $feeAmountMap[$fee['name']] = [
      'amount' => $fee['amount'],
      'type' => $fee['type'] ?? 'fixed',
    ];
  }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $data = [
      'user_id' => (int) ($_POST['user_id'] ?? 0),
      'amount' => $_POST['amount'] ?? '',
      'description' => trim($_POST['description'] ?? ''),
      'billing_period_start' => trim($_POST['billing_period_start'] ?? ''),
      'billing_period_end' => trim($_POST['billing_period_end'] ?? ''),
    ];

    $result = $invoiceService->createInvoice($data);
    if ($result->success) {
      $successMessage = 'Invoice ' . ($result->data['invoice_number'] ?? '') . ' created successfully.';
      $auditService->log('invoice_created', $adminId, 'invoice', $result->data['invoice_id'] ?? null);
    } else {
      if ($result->errors) {
        $formErrors = $result->errors;
      }
      $errorMessage = $result->errorMessage ?? 'Failed to create invoice.';
    }
  } elseif ($action === 'mark_paid') {
    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $result = $invoiceService->markPaid($invoiceId);
    if ($result->success) {
      $successMessage = 'Invoice marked as paid.';
      $auditService->log('invoice_paid', $adminId, 'invoice', $invoiceId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to mark invoice as paid.';
    }
  } elseif ($action === 'auto_generate') {
    $feeIndex = (int) ($_POST['fee_index'] ?? -1);
    $targetClients = $_POST['target_clients'] ?? 'all';
    $selectedClientIds = $_POST['client_ids'] ?? [];

    if (!isset($feeAmounts[$feeIndex])) {
      $errorMessage = 'Invalid fee selected.';
    } else {
      $fee = $feeAmounts[$feeIndex];
      $feeName = $fee['name'];
      $feeType = $fee['type'] ?? 'fixed';
      $feeAmount = (float) $fee['amount'];

      // Get target clients
      $inventoryRepo = new InventoryRepository($pdo);
      $targetClientList = [];

      if ($targetClients === 'selected' && !empty($selectedClientIds)) {
        foreach ($clients as $c) {
          if (in_array((string) $c['id'], $selectedClientIds, true)) {
            $targetClientList[] = $c;
          }
        }
      } else {
        $targetClientList = $clients;
      }

      $generated = 0;
      $skipped = 0;

      foreach ($targetClientList as $client) {
        $clientId = (int) $client['id'];

        if ($feeType === 'percentage') {
          // Calculate amount based on client's portfolio value
          $portfolio = $inventoryRepo->getPortfolioSummary($clientId);
          $portfolioValue = $portfolio['total_value'] ?? 0.0;

          if ($portfolioValue <= 0) {
            $skipped++;
            continue;
          }

          $invoiceAmount = round($portfolioValue * ($feeAmount / 100), 2);
          $description = sprintf('%s (%.2f%% of $%s portfolio)', $feeName, $feeAmount, number_format($portfolioValue, 2));
        } else {
          $invoiceAmount = $feeAmount;
          $description = $feeName;
        }

        if ($invoiceAmount <= 0) {
          $skipped++;
          continue;
        }

        $result = $invoiceService->createInvoice([
          'user_id' => $clientId,
          'amount' => (string) $invoiceAmount,
          'description' => $description,
        ]);

        if ($result->success) {
          $generated++;
          $auditService->log('invoice_auto_generated', $adminId, 'invoice', $result->data['invoice_id'] ?? null);
        } else {
          $skipped++;
        }
      }

      $successMessage = sprintf('Auto-generated %d invoice(s). Skipped %d client(s) (no portfolio value or error).', $generated, $skipped);
    }
  }
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $invoiceService->getAllInvoices($page);
$invoices = $result['data'];
$totalInvoices = $result['total'];
$totalPages = getTotalPages($totalInvoices, 20);

// Batch-load all user names in one query to avoid N+1
$userIds = array_filter(array_unique(array_column($invoices, 'user_id')));
$userNames = [];
if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($userIds));
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $u) {
        $userNames[(int) $u['id']] = $u['name'];
    }
}

$pageTitle = 'Invoice Management - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Invoice Management</h1>

  <?php if ($successMessage): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= sanitizeOutput($successMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= sanitizeOutput($errorMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>

  <!-- Create Invoice Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Create New Invoice</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/invoices.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div class="row g-3">
          <div class="col-md-4">
            <label for="user_id" class="form-label text-secondary">Client</label>
            <select class="form-select" id="user_id" name="user_id" required>
              <option value="">Select client...</option>
              <?php foreach ($clients as $client): ?>
              <option value="<?= (int) $client['id'] ?>">
                <?= sanitizeOutput($client['name']) ?> (<?= sanitizeOutput($client['email']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($formErrors['user_id'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['user_id']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-3">
            <label for="amount" class="form-label text-secondary">Amount ($)</label>
            <input type="number" step="0.01" min="0.01" max="999999999.99" class="form-control" id="amount" name="amount" required>
            <?php if (isset($formErrors['amount'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['amount']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-5">
            <label for="description" class="form-label text-secondary">Description</label>
            <?php if (!empty($descriptionPresets)): ?>
            <select class="form-select" id="description_select" onchange="selectPreset(this.value)">
              <option value="">-- Select preset or type custom --</option>
              <?php foreach ($descriptionPresets as $preset): ?>
              <option value="<?= sanitizeOutput($preset) ?>"><?= sanitizeOutput($preset) ?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <input type="text" class="form-control mt-2" id="description" name="description" maxlength="500" required placeholder="<?= !empty($descriptionPresets) ? 'Or type custom description...' : 'Enter description' ?>">
            <?php if (isset($formErrors['description'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['description']) ?></div>
            <?php endif; ?>
            <div class="form-text"><a href="/admin/invoice-descriptions.php" class="text-secondary">Manage presets</a></div>
          </div>
          <div class="col-md-3">
            <label for="billing_period_start" class="form-label text-secondary">Billing Period Start</label>
            <input type="date" class="form-control" id="billing_period_start" name="billing_period_start">
          </div>
          <div class="col-md-3">
            <label for="billing_period_end" class="form-label text-secondary">Billing Period End</label>
            <input type="date" class="form-control" id="billing_period_end" name="billing_period_end">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-outline-light">Create Invoice</button>
          </div>
        </div>
      </form>
      <script>
        var feeAmountMap = <?= json_encode($feeAmountMap) ?>;
        function selectPreset(value) {
          if (value) {
            document.getElementById('description').value = value;
            if (feeAmountMap[value] && feeAmountMap[value].type === 'fixed') {
              document.getElementById('amount').value = feeAmountMap[value].amount;
            }
          }
        }
      </script>
    </div>
  </div>

  <!-- Auto-Generate Invoices -->
  <div class="card mb-4">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">Auto-Generate Invoices</h2>
    </div>
    <div class="card-body">
      <p class="text-secondary mb-3">Generate invoices for multiple clients at once based on configured fee rates. Percentage fees are calculated against each client's total portfolio value.</p>

      <?php if (empty($feeAmounts)): ?>
      <div class="alert alert-info mb-0">No fee rates configured. <a href="/admin/dashboard.php" class="alert-link">Set up fees on the Dashboard</a> first.</div>
      <?php else: ?>
      <form method="POST" action="/admin/invoices.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="auto_generate">

        <div class="row g-3">
          <div class="col-md-5">
            <label for="fee_index" class="form-label text-secondary">Fee to Apply</label>
            <select class="form-select" id="fee_index" name="fee_index" required>
              <option value="">Select fee...</option>
              <?php foreach ($feeAmounts as $idx => $fee): ?>
              <option value="<?= $idx ?>">
                <?= sanitizeOutput($fee['name']) ?> — <?= ($fee['type'] ?? 'fixed') === 'percentage' ? sanitizeOutput($fee['amount']) . '% of portfolio' : '$' . sanitizeOutput($fee['amount']) . ' fixed' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="target_clients" class="form-label text-secondary">Target Clients</label>
            <select class="form-select" id="target_clients" name="target_clients" onchange="toggleClientSelect(this.value)">
              <option value="all">All Clients</option>
              <option value="selected">Select Specific Clients</option>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-light w-100" onclick="return confirm('This will generate invoices for the selected clients. Continue?')">Generate Invoices</button>
          </div>
          <div class="col-12" id="client_select_wrapper" style="display: none;">
            <label class="form-label text-secondary">Select Clients</label>
            <div class="row g-2" style="max-height: 200px; overflow-y: auto;">
              <?php foreach ($clients as $client): ?>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="client_ids[]" value="<?= (int) $client['id'] ?>" id="client_<?= (int) $client['id'] ?>">
                  <label class="form-check-label text-secondary" for="client_<?= (int) $client['id'] ?>"><?= sanitizeOutput($client['name']) ?></label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </form>
      <script>
        function toggleClientSelect(value) {
          document.getElementById('client_select_wrapper').style.display = value === 'selected' ? 'block' : 'none';
        }
      </script>
      <?php endif; ?>
    </div>
  </div>

  <!-- Invoices Table -->
  <div class="card">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;">All Invoices (<?= sanitizeOutput((string) $totalInvoices) ?>)</h2>
    </div>
    <div class="card-body">
      <?php if (empty($invoices)): ?>
      <p class="text-secondary text-center mb-0 py-3">No invoices found.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">Invoice #</th>
              <th scope="col">Client</th>
              <th scope="col">Amount</th>
              <th scope="col">Billing Period</th>
              <th scope="col">Status</th>
              <th scope="col">Created</th>
              <th scope="col">Payment Date</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $invoice): ?>
            <?php
            $clientName = $userNames[(int) $invoice['user_id']] ?? 'Unknown';
            $billingPeriod = '—';
            if (!empty($invoice['billing_period_start']) && !empty($invoice['billing_period_end'])) {
              $billingPeriod = formatDate($invoice['billing_period_start']) . ' – ' . formatDate($invoice['billing_period_end']);
            } elseif (!empty($invoice['billing_period_start'])) {
              $billingPeriod = formatDate($invoice['billing_period_start']) . ' – present';
            }
            ?>
            <tr>
              <td><?= sanitizeOutput($invoice['invoice_number']) ?></td>
              <td><?= sanitizeOutput($clientName) ?></td>
              <td><?= sanitizeOutput(formatCurrency((float) $invoice['amount'])) ?></td>
              <td><?= sanitizeOutput($billingPeriod) ?></td>
              <td>
                <?php if ($invoice['status'] === 'paid'): ?>
                <span class="badge bg-success">Paid</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark">Unpaid</span>
                <?php endif; ?>
              </td>
              <td><?= sanitizeOutput(formatDate($invoice['created_at'])) ?></td>
              <td><?= $invoice['payment_date'] ? sanitizeOutput(formatDate($invoice['payment_date'])) : '—' ?></td>
              <td>
                <?php if ($invoice['status'] === 'unpaid'): ?>
                <form method="POST" action="/admin/invoices.php" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="mark_paid">
                  <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark this invoice as paid?')">Mark Paid</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <nav aria-label="Invoice pagination" class="mt-4">
        <ul class="pagination justify-content-center mb-0">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link <?= $i === $page ? '' : 'border-secondary' ?>" href="?page=<?= $i ?>" <?= $i === $page ? 'style="background-color: #c9a227; border-color: #c9a227; color: #1a1a1a;"' : '' ?>><?= $i ?></a>
          </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
