<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Shipment Request Page
 *
 * Allows clients to request shipment of their gold holdings by selecting
 * inventory items, providing a destination address, and choosing insurance.
 *
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Require client role
requireClient();

// Enforce CSRF on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();
}

$userId = getCurrentUserId();
$pdo = getDbConnection();

// Instantiate repositories and services
$inventoryRepository = new \GOLS\Repositories\InventoryRepository($pdo);
$shipmentRepository = new \GOLS\Repositories\ShipmentRepository($pdo);
$shipmentValidator = new \GOLS\Validators\ShipmentValidator();
$notificationRepository = new \GOLS\Repositories\NotificationRepository($pdo);
$notificationService = new \GOLS\Services\NotificationService($notificationRepository);
$userRepository = new \GOLS\Repositories\UserRepository($pdo);
$shipmentService = new \GOLS\Services\ShipmentService(
  $shipmentRepository,
  $inventoryRepository,
  $shipmentValidator,
  $notificationService,
  $userRepository
);

// Load available inventory items (active, not in pending/in-transit shipments)
$allItems = $inventoryRepository->findByUserId($userId, 1, 1000);
$availableItems = [];

if (!empty($allItems['data'])) {
  $allItemIds = array_map(function ($item) {
    return (int) $item['id'];
  }, $allItems['data']);

  $itemsInShipments = $inventoryRepository->getItemsInActiveShipments($allItemIds);

  foreach ($allItems['data'] as $item) {
    if (!in_array((int) $item['id'], $itemsInShipments, true)) {
      $availableItems[] = $item;
    }
  }
}

// Initialize form data and errors
$errors = [];
$formData = [
  'street' => '',
  'city' => '',
  'state_province' => '',
  'postal_code' => '',
  'country' => '',
  'inventory_items' => [],
  'insurance_selected' => 0,
];
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Collect form data
  $formData['street'] = sanitizeInput($_POST['street'] ?? '');
  $formData['city'] = sanitizeInput($_POST['city'] ?? '');
  $formData['state_province'] = sanitizeInput($_POST['state_province'] ?? '');
  $formData['postal_code'] = sanitizeInput($_POST['postal_code'] ?? '');
  $formData['country'] = sanitizeInput($_POST['country'] ?? '');
  $formData['inventory_items'] = $_POST['inventory_items'] ?? [];
  $formData['insurance_selected'] = isset($_POST['insurance_selected']) ? 1 : 0;

  // Call ShipmentService::createRequest
  $result = $shipmentService->createRequest($userId, $formData);

  if ($result->success) {
    // Redirect to shipments page with success message
    $_SESSION['flash_success'] = 'Shipment request submitted successfully. It is now pending admin approval.';
    redirect('/client/shipments.php');
  } else {
    // Display validation errors
    if ($result->errors) {
      $errors = $result->errors;
    } else {
      $errors['general'] = $result->errorMessage ?? 'An error occurred while creating the shipment request.';
    }
  }
}

// Calculate insured value for display (based on all available items for JS calculation)
$itemValues = [];
foreach ($availableItems as $item) {
  $weight = (float) ($item['weight'] ?? 0);
  $purity = (float) ($item['purity'] ?? 0);
  $itemValues[(int) $item['id']] = round($weight * $purity, 2);
}

// Get unread notification count for nav
$unreadCount = $notificationService->getUnreadCount($userId);

// Page setup
$pageTitle = 'Request Shipment - Aurum Vault Logistics';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h1 class="mb-4" style="color: #c9a227;">Request Shipment</h1>

      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger" role="alert">
          <?= sanitizeOutput($errors['general']) ?>
        </div>
      <?php endif; ?>

      <?php if (empty($availableItems)): ?>
        <div class="alert alert-info" role="alert">
          <h5 class="alert-heading">No Available Items</h5>
          <p class="mb-0">You currently have no inventory items available for shipment. Items that are already in pending or in-transit shipments cannot be selected.</p>
        </div>
        <a href="/client/inventory.php" class="btn btn-outline-light">View Inventory</a>
      <?php else: ?>
        <form method="POST" action="/client/shipment-request.php" id="shipmentRequestForm">
          <?= csrfField() ?>

          <!-- Destination Address -->
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="mb-0" style="color: #c9a227;">Destination Address</h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label for="street" class="form-label">Street Address</label>
                <input type="text" class="form-control <?= isset($errors['street']) ? 'is-invalid' : '' ?>"
                    id="street" name="street"
                    value="<?= sanitizeOutput($formData['street']) ?>"
                    required>
                <?php if (isset($errors['street'])): ?>
                  <div class="invalid-feedback"><?= sanitizeOutput($errors['street']) ?></div>
                <?php endif; ?>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="city" class="form-label">City</label>
                  <input type="text" class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>"
                      id="city" name="city"
                      value="<?= sanitizeOutput($formData['city']) ?>"
                      required>
                  <?php if (isset($errors['city'])): ?>
                    <div class="invalid-feedback"><?= sanitizeOutput($errors['city']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="state_province" class="form-label">State / Province</label>
                  <input type="text" class="form-control <?= isset($errors['state_province']) ? 'is-invalid' : '' ?>"
                      id="state_province" name="state_province"
                      value="<?= sanitizeOutput($formData['state_province']) ?>"
                      required>
                  <?php if (isset($errors['state_province'])): ?>
                    <div class="invalid-feedback"><?= sanitizeOutput($errors['state_province']) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="postal_code" class="form-label">Postal Code</label>
                  <input type="text" class="form-control <?= isset($errors['postal_code']) ? 'is-invalid' : '' ?>"
                      id="postal_code" name="postal_code"
                      value="<?= sanitizeOutput($formData['postal_code']) ?>"
                      required>
                  <?php if (isset($errors['postal_code'])): ?>
                    <div class="invalid-feedback"><?= sanitizeOutput($errors['postal_code']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="country" class="form-label">Country</label>
                  <input type="text" class="form-control <?= isset($errors['country']) ? 'is-invalid' : '' ?>"
                      id="country" name="country"
                      value="<?= sanitizeOutput($formData['country']) ?>"
                      required>
                  <?php if (isset($errors['country'])): ?>
                    <div class="invalid-feedback"><?= sanitizeOutput($errors['country']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Inventory Item Selection -->
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="mb-0" style="color: #c9a227;">Select Inventory Items</h5>
            </div>
            <div class="card-body">
              <?php if (isset($errors['inventory_items'])): ?>
                <div class="alert alert-danger" role="alert">
                  <?= sanitizeOutput($errors['inventory_items']) ?>
                </div>
              <?php endif; ?>

              <p class="text-secondary small mb-3">Select the gold items you wish to ship. At least one item is required.</p>

              <div class="table-responsive">
                <table class="table table-dark table-hover">
                  <thead>
                    <tr>
                      <th scope="col" style="width: 40px;">
                        <input type="checkbox" class="form-check-input" id="selectAll" aria-label="Select all items">
                      </th>
                      <th scope="col">Type</th>
                      <th scope="col">Weight (g)</th>
                      <th scope="col">Purity</th>
                      <th scope="col">Serial Number</th>
                      <th scope="col">Value Factor</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($availableItems as $item): ?>
                      <tr>
                        <td>
                          <input type="checkbox"
                              class="form-check-input item-checkbox"
                              name="inventory_items[]"
                              value="<?= (int) $item['id'] ?>"
                              data-value="<?= sanitizeOutput((string) ($itemValues[(int) $item['id']] ?? 0)) ?>"
                              <?= in_array((int) $item['id'], array_map('intval', $formData['inventory_items'])) ? 'checked' : '' ?>
                              aria-label="Select item <?= sanitizeOutput($item['serial_number']) ?>">
                        </td>
                        <td><?= sanitizeOutput(ucfirst($item['gold_type'])) ?></td>
                        <td><?= sanitizeOutput(number_format((float) $item['weight'], 3)) ?></td>
                        <td><?= sanitizeOutput(number_format((float) $item['purity'], 4)) ?></td>
                        <td><code><?= sanitizeOutput($item['serial_number']) ?></code></td>
                        <td><?= sanitizeOutput(number_format($itemValues[(int) $item['id']] ?? 0, 2)) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Insurance Selection -->
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="mb-0" style="color: #c9a227;">Insurance</h5>
            </div>
            <div class="card-body">
              <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="insurance_selected" name="insurance_selected" value="1"
                    <?= $formData['insurance_selected'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="insurance_selected">
                  Include shipping insurance for selected items
                </label>
              </div>

              <div class="card bg-secondary bg-opacity-25 p-3" id="insuredValueDisplay">
                <div class="d-flex justify-content-between align-items-center">
                  <span >Estimated Insured Value:</span>
                  <span class="fw-bold" style="color: #c9a227;" id="insuredValueAmount">$0.00</span>
                </div>
                <small class="text-secondary mt-1 d-block">Based on weight × purity of selected items (shown when insurance is selected)</small>
              </div>
            </div>
          </div>

          <!-- Submit -->
          <div class="d-flex justify-content-between align-items-center">
            <a href="/client/shipments.php" class="btn btn-outline-light">Cancel</a>
            <button type="submit" class="btn btn-lg" style="background-color: #c9a227; color: #1a1a1a; font-weight: 600;">
              Submit Shipment Request
            </button>
          </div>
        </form>

        <script>
        (function() {
          'use strict';

          var checkboxes = document.querySelectorAll('.item-checkbox');
          var selectAll = document.getElementById('selectAll');
          var insuranceCheckbox = document.getElementById('insurance_selected');
          var insuredValueAmount = document.getElementById('insuredValueAmount');
          var insuredValueDisplay = document.getElementById('insuredValueDisplay');

          function calculateInsuredValue() {
            var total = 0;
            var anySelected = false;

            checkboxes.forEach(function(cb) {
              if (cb.checked) {
                anySelected = true;
                total += parseFloat(cb.getAttribute('data-value')) || 0;
              }
            });

            if (insuranceCheckbox.checked && anySelected) {
              insuredValueAmount.textContent = '$' + total.toFixed(2);
              insuredValueDisplay.style.display = 'block';
            } else if (!insuranceCheckbox.checked) {
              insuredValueAmount.textContent = '$0.00';
              insuredValueDisplay.style.display = 'block';
            } else {
              insuredValueAmount.textContent = '$0.00';
              insuredValueDisplay.style.display = 'block';
            }
          }

          // Select all toggle
          if (selectAll) {
            selectAll.addEventListener('change', function() {
              checkboxes.forEach(function(cb) {
                cb.checked = selectAll.checked;
              });
              calculateInsuredValue();
            });
          }

          // Individual checkbox change
          checkboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
              // Update select all state
              var allChecked = true;
              checkboxes.forEach(function(c) {
                if (!c.checked) allChecked = false;
              });
              if (selectAll) selectAll.checked = allChecked;
              calculateInsuredValue();
            });
          });

          // Insurance checkbox change
          if (insuranceCheckbox) {
            insuranceCheckbox.addEventListener('change', calculateInsuredValue);
          }

          // Initial calculation
          calculateInsuredValue();
        })();
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
