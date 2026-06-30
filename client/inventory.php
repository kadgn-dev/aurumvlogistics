<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Client Vault Inventory Page
 *
 * Displays a paginated list of the client's vault inventory items
 * with filtering by gold type and vault location.
 *
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories/InventoryRepository.php';
require_once __DIR__ . '/../includes/services/InventoryService.php';
require_once __DIR__ . '/../includes/validators/InventoryValidator.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';

use GOLS\Repositories\InventoryRepository;
use GOLS\Services\InventoryService;
use GOLS\Validators\InventoryValidator;

// Enforce client-only access (Requirement 4.4)
requireClient();

// Get current user ID from session
$userId = getCurrentUserId();

// Read filter params from GET
$filterGoldType = isset($_GET['gold_type']) && $_GET['gold_type'] !== '' ? sanitizeInput($_GET['gold_type']) : null;
$filterVaultLocation = isset($_GET['vault_location']) && $_GET['vault_location'] !== '' ? sanitizeInput($_GET['vault_location']) : null;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Validate gold_type filter against allowed values
$allowedGoldTypes = ['bar', 'coin', 'grain', 'round'];
if ($filterGoldType !== null && !in_array($filterGoldType, $allowedGoldTypes, true)) {
  $filterGoldType = null;
}

// Initialize service
$pdo = getDbConnection();
$repository = new InventoryRepository($pdo);
$validator = new InventoryValidator();
$inventoryService = new InventoryService($repository, $validator);

// Get paginated inventory (Requirement 4.1: 25 per page, Requirement 4.4: user_id scoped)
$result = $inventoryService->getClientInventory($userId, $page, $filterGoldType, $filterVaultLocation);

$items = $result['data'];
$totalItems = $result['total'];
$perPage = $result['perPage'];
$currentPage = $result['page'];
$totalPages = getTotalPages($totalItems, $perPage);

// Get distinct vault locations for filter dropdown
$vaultLocations = $repository->getDistinctVaultLocations($userId);

// Determine if filters are active
$filtersActive = ($filterGoldType !== null || $filterVaultLocation !== null);

// Page setup
$pageTitle = 'Vault Inventory - Aurum Vault Logistics';
$currentNav = 'inventory';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<!-- Page Header -->
<section class="py-4">
  <div class="container">
    <h1 class="h3 mb-0" style="color: #c9a227;">Vault Inventory</h1>
    <p class="text-secondary mt-1 mb-0">View and filter your gold holdings stored in our secure vaults.</p>
  </div>
</section>

<!-- Filters -->
<section class="pb-3">
  <div class="container">
    <form method="GET" action="/client/inventory.php" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label for="filter-gold-type" class="form-label">Gold Type</label>
        <select name="gold_type" id="filter-gold-type" class="form-select">
          <option value="">All Types</option>
          <option value="bar"<?= $filterGoldType === 'bar' ? ' selected' : '' ?>>Bar</option>
          <option value="coin"<?= $filterGoldType === 'coin' ? ' selected' : '' ?>>Coin</option>
          <option value="grain"<?= $filterGoldType === 'grain' ? ' selected' : '' ?>>Grain</option>
          <option value="round"<?= $filterGoldType === 'round' ? ' selected' : '' ?>>Round</option>
        </select>
      </div>
      <div class="col-md-4">
        <label for="filter-vault-location" class="form-label">Vault Location</label>
        <select name="vault_location" id="filter-vault-location" class="form-select">
          <option value="">All Locations</option>
          <?php foreach ($vaultLocations as $location): ?>
          <option value="<?= sanitizeOutput($location) ?>"<?= $filterVaultLocation === $location ? ' selected' : '' ?>><?= sanitizeOutput($location) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-gold me-2">Filter</button>
        <?php if ($filtersActive): ?>
        <a href="/client/inventory.php" class="btn btn-outline-gold">Clear Filters</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</section>

<!-- Inventory Table -->
<section class="pb-5">
  <div class="container">
    <?php if (empty($items) && !$filtersActive): ?>
      <!-- Empty state: no inventory items at all (Requirement 4.5) -->
      <div class="card text-center p-5">
        <div class="mb-3" style="font-size: 3rem; color: #c9a227;">&#x1f4e6;</div>
        <h3 class="h5">No Inventory Items</h3>
        <p class="text-secondary mb-0">Your vault inventory is currently empty. Items will appear here once they are added to your account by an administrator.</p>
      </div>
    <?php elseif (empty($items) && $filtersActive): ?>
      <!-- No results match filter (Requirement 4.3) -->
      <div class="alert alert-info" role="alert">
        <strong>No results found.</strong> No inventory items match the selected filter criteria. Try adjusting your filters or <a href="/client/inventory.php" class="alert-link">clear all filters</a>.
      </div>
    <?php else: ?>
      <!-- Results table (Requirement 4.2) -->
      <div class="table-responsive">
        <table class="table table-dark-gold table-striped table-hover">
          <thead>
            <tr>
              <th scope="col">Gold Type</th>
              <th scope="col">Weight (g)</th>
              <th scope="col">Purity</th>
              <th scope="col">Carat</th>
              <th scope="col">Value</th>
              <th scope="col">Serial Number</th>
              <th scope="col">Vault Location</th>
              <th scope="col">Date Acquired</th>
              <th scope="col">Insurance Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td><?= sanitizeOutput(ucfirst($item['gold_type'])) ?></td>
              <td><?= sanitizeOutput(number_format((float) $item['weight'], 3)) ?></td>
              <td><?= sanitizeOutput(number_format((float) $item['purity'], 4)) ?></td>
              <td><?= sanitizeOutput(number_format((float) ($item['carat'] ?? 24), 1)) ?>K</td>
              <td><?= sanitizeOutput(formatCurrency((float) ($item['item_value'] ?? 0))) ?></td>
              <td><?= sanitizeOutput($item['serial_number']) ?></td>
              <td><?= sanitizeOutput($item['vault_location']) ?></td>
              <td><?= !empty($item['date_acquired']) ? sanitizeOutput(formatDate($item['date_acquired'])) : '—' ?></td>
              <td>
                <?php if ((int) $item['insurance_status'] === 1): ?>
                  <span class="badge badge-status-approved">Insured</span>
                <?php else: ?>
                  <span class="badge badge-status-unpaid">Not Insured</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls -->
      <?php if ($totalPages > 1): ?>
      <nav aria-label="Inventory pagination">
        <ul class="pagination justify-content-center mt-4">
          <!-- Previous -->
          <li class="page-item<?= $currentPage <= 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= $currentPage > 1 ? '/client/inventory.php?' . http_build_query(array_filter(['gold_type' => $filterGoldType, 'vault_location' => $filterVaultLocation, 'page' => $currentPage - 1])) : '#' ?>" aria-label="Previous">
              <span aria-hidden="true">&laquo;</span>
            </a>
          </li>

          <!-- Page Numbers -->
          <?php
          $startPage = max(1, $currentPage - 2);
          $endPage = min($totalPages, $currentPage + 2);
          ?>

          <?php if ($startPage > 1): ?>
          <li class="page-item">
            <a class="page-link" href="/client/inventory.php?<?= http_build_query(array_filter(['gold_type' => $filterGoldType, 'vault_location' => $filterVaultLocation, 'page' => 1])) ?>">1</a>
          </li>
          <?php if ($startPage > 2): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
          <li class="page-item<?= $i === $currentPage ? ' active' : '' ?>">
            <a class="page-link" href="/client/inventory.php?<?= http_build_query(array_filter(['gold_type' => $filterGoldType, 'vault_location' => $filterVaultLocation, 'page' => $i])) ?>"><?= $i ?></a>
          </li>
          <?php endfor; ?>

          <?php if ($endPage < $totalPages): ?>
          <?php if ($endPage < $totalPages - 1): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link" href="/client/inventory.php?<?= http_build_query(array_filter(['gold_type' => $filterGoldType, 'vault_location' => $filterVaultLocation, 'page' => $totalPages])) ?>"><?= $totalPages ?></a>
          </li>
          <?php endif; ?>

          <!-- Next -->
          <li class="page-item<?= $currentPage >= $totalPages ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= $currentPage < $totalPages ? '/client/inventory.php?' . http_build_query(array_filter(['gold_type' => $filterGoldType, 'vault_location' => $filterVaultLocation, 'page' => $currentPage + 1])) : '#' ?>" aria-label="Next">
              <span aria-hidden="true">&raquo;</span>
            </a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>

      <!-- Results summary -->
      <p class="text-center text-secondary small mt-2">
        Showing <?= sanitizeOutput((string) (($currentPage - 1) * $perPage + 1)) ?>–<?= sanitizeOutput((string) min($currentPage * $perPage, $totalItems)) ?> of <?= sanitizeOutput((string) $totalItems) ?> items
      </p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
