<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Inventory Management - Full CRUD for Vault Inventory Items
 *
 * Displays all inventory items across all clients with pagination,
 * create new item form, edit form, and deactivate button.
 *
 * Requirements: 5.1, 5.2, 5.3, 17.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/repositories/InventoryRepository.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/services/InventoryService.php';
require_once __DIR__ . '/../includes/services/AuditService.php';
require_once __DIR__ . '/../includes/validators/InventoryValidator.php';

use GOLS\Repositories\InventoryRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Services\InventoryService;
use GOLS\Services\AuditService;
use GOLS\Validators\InventoryValidator;

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$inventoryRepo = new InventoryRepository($pdo);
$userRepo = new UserRepository($pdo);
$inventoryValidator = new InventoryValidator();
$inventoryService = new InventoryService($inventoryRepo, $inventoryValidator);
$auditService = new AuditService($pdo);
$adminId = getCurrentUserId();

$successMessage = '';
$errorMessage = '';
$editItem = null;
$formErrors = [];

// Get all clients for the dropdown
$clientsStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role = 'client' ORDER BY name ASC");
$clientsStmt->execute();
$clients = $clientsStmt->fetchAll(\PDO::FETCH_ASSOC);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $data = [
      'user_id' => (int) ($_POST['user_id'] ?? 0),
      'gold_type' => $_POST['gold_type'] ?? '',
      'weight' => $_POST['weight'] ?? '',
      'purity' => $_POST['purity'] ?? '',
      'carat' => $_POST['carat'] ?? '',
      'serial_number' => trim($_POST['serial_number'] ?? ''),
      'vault_location' => trim($_POST['vault_location'] ?? ''),
      'insurance_status' => isset($_POST['insurance_status']) ? true : false,
      'item_value' => $_POST['item_value'] ?? '',
      'date_acquired' => trim($_POST['date_acquired'] ?? ''),
    ];

    $result = $inventoryService->createItem($data);
    if ($result->success) {
      $successMessage = 'Inventory item created successfully.';
      $auditService->log('inventory_created', $adminId, 'inventory', $result->data['item_id'] ?? null);
    } else {
      if ($result->errors) {
        $formErrors = $result->errors;
      }
      $errorMessage = $result->errorMessage ?? 'Failed to create inventory item.';
    }
  } elseif ($action === 'update') {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $data = [
      'user_id' => (int) ($_POST['user_id'] ?? 0),
      'gold_type' => $_POST['gold_type'] ?? '',
      'weight' => $_POST['weight'] ?? '',
      'purity' => $_POST['purity'] ?? '',
      'carat' => $_POST['carat'] ?? '',
      'serial_number' => trim($_POST['serial_number'] ?? ''),
      'vault_location' => trim($_POST['vault_location'] ?? ''),
      'insurance_status' => isset($_POST['insurance_status']) ? true : false,
      'item_value' => $_POST['item_value'] ?? '',
      'date_acquired' => trim($_POST['date_acquired'] ?? ''),
    ];

    $result = $inventoryService->updateItem($itemId, $data);
    if ($result->success) {
      $successMessage = 'Inventory item updated successfully.';
      $auditService->log('inventory_updated', $adminId, 'inventory', $itemId);
    } else {
      if ($result->errors) {
        $formErrors = $result->errors;
      }
      $errorMessage = $result->errorMessage ?? 'Failed to update inventory item.';
    }
  } elseif ($action === 'deactivate') {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $result = $inventoryService->deactivateItem($itemId);
    if ($result->success) {
      $successMessage = 'Inventory item deactivated successfully.';
      $auditService->log('inventory_deleted', $adminId, 'inventory', $itemId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to deactivate item.';
    }
  }
}

// Handle edit mode
if (isset($_GET['edit'])) {
  $editId = (int) $_GET['edit'];
  $editItem = $inventoryRepo->findById($editId);
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all items (active and inactive) for admin view
$countStmt = $pdo->query('SELECT COUNT(*) FROM vault_inventory');
$totalItems = (int) $countStmt->fetchColumn();
$totalPages = getTotalPages($totalItems, $perPage);

$stmt = $pdo->prepare(
  'SELECT vi.*, u.name AS owner_name, u.email AS owner_email
   FROM vault_inventory vi
   LEFT JOIN users u ON vi.user_id = u.id
   ORDER BY vi.created_at DESC
   LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$pageTitle = 'Inventory Management - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">Inventory Management</h1>

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

  <!-- Create / Edit Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h2 class="h5 mb-0" style="color: #c9a227;"><?= $editItem ? 'Edit Item' : 'Create New Item' ?></h2>
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/inventory.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create' ?>">
        <?php if ($editItem): ?>
        <input type="hidden" name="item_id" value="<?= (int) $editItem['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-md-4">
            <label for="user_id" class="form-label text-secondary">Client</label>
            <select class="form-select" id="user_id" name="user_id" required>
              <option value="">Select client...</option>
              <?php foreach ($clients as $client): ?>
              <option value="<?= (int) $client['id'] ?>" <?= ($editItem && (int) $editItem['user_id'] === (int) $client['id']) ? 'selected' : '' ?>>
                <?= sanitizeOutput($client['name']) ?> (<?= sanitizeOutput($client['email']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($formErrors['user_id'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['user_id']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="gold_type" class="form-label text-secondary">Gold Type</label>
            <select class="form-select" id="gold_type" name="gold_type" required>
              <option value="">Select type...</option>
              <?php foreach (['bar', 'coin', 'grain', 'round'] as $type): ?>
              <option value="<?= $type ?>" <?= ($editItem && $editItem['gold_type'] === $type) ? 'selected' : '' ?>>
                <?= sanitizeOutput(ucfirst($type)) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($formErrors['gold_type'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['gold_type']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="weight" class="form-label text-secondary">Weight (troy oz)</label>
            <input type="number" step="0.001" min="0.001" class="form-control" id="weight" name="weight" value="<?= sanitizeOutput((string) ($editItem['weight'] ?? '')) ?>" required>
            <?php if (isset($formErrors['weight'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['weight']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="purity" class="form-label text-secondary">Purity</label>
            <select class="form-select" id="purity" name="purity" required onchange="updateCarat(this.value)">
              <option value="">Select purity...</option>
              <option value="0.9999" <?= ($editItem && (float) $editItem['purity'] == 0.9999) ? 'selected' : '' ?>>0.9999 (24K)</option>
              <option value="0.9990" <?= ($editItem && (float) $editItem['purity'] == 0.9990) ? 'selected' : '' ?>>0.9990 (24K)</option>
              <option value="0.9584" <?= ($editItem && (float) $editItem['purity'] == 0.9584) ? 'selected' : '' ?>>0.9584 (23K)</option>
              <option value="0.9167" <?= ($editItem && (float) $editItem['purity'] == 0.9167) ? 'selected' : '' ?>>0.9167 (22K)</option>
              <option value="0.8750" <?= ($editItem && (float) $editItem['purity'] == 0.8750) ? 'selected' : '' ?>>0.8750 (21K)</option>
              <option value="0.8333" <?= ($editItem && (float) $editItem['purity'] == 0.8333) ? 'selected' : '' ?>>0.8333 (20K)</option>
              <option value="0.7917" <?= ($editItem && (float) $editItem['purity'] == 0.7917) ? 'selected' : '' ?>>0.7917 (19K)</option>
              <option value="0.7500" <?= ($editItem && (float) $editItem['purity'] == 0.7500) ? 'selected' : '' ?>>0.7500 (18K)</option>
              <option value="0.5833" <?= ($editItem && (float) $editItem['purity'] == 0.5833) ? 'selected' : '' ?>>0.5833 (14K)</option>
              <option value="0.4167" <?= ($editItem && (float) $editItem['purity'] == 0.4167) ? 'selected' : '' ?>>0.4167 (10K)</option>
              <option value="0.3750" <?= ($editItem && (float) $editItem['purity'] == 0.3750) ? 'selected' : '' ?>>0.3750 (9K)</option>
            </select>
            <?php if (isset($formErrors['purity'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['purity']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="carat" class="form-label text-secondary">Carat</label>
            <input type="number" step="0.1" min="1" max="24" class="form-control" id="carat" name="carat" value="<?= sanitizeOutput((string) ($editItem['carat'] ?? '24.0')) ?>" required readonly>
            <?php if (isset($formErrors['carat'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['carat']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="serial_number" class="form-label text-secondary">Serial Number</label>
            <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?= sanitizeOutput($editItem['serial_number'] ?? generateSerialNumber()) ?>" maxlength="50" readonly>
            <div class="form-text text-secondary">Auto-generated. Format: GC##-####-####-####</div>
            <?php if (isset($formErrors['serial_number'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['serial_number']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="vault_location" class="form-label text-secondary">Vault Location</label>
            <input type="text" class="form-control" id="vault_location" name="vault_location" value="<?= sanitizeOutput($editItem['vault_location'] ?? '') ?>" required>
            <?php if (isset($formErrors['vault_location'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['vault_location']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="item_value" class="form-label text-secondary">Value (USD)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="item_value" name="item_value" value="<?= sanitizeOutput((string) ($editItem['item_value'] ?? '')) ?>" required>
            <?php if (isset($formErrors['item_value'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['item_value']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label for="date_acquired" class="form-label text-secondary">Date Acquired</label>
            <input type="date" class="form-control" id="date_acquired" name="date_acquired" value="<?= sanitizeOutput($editItem['date_acquired'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
            <?php if (isset($formErrors['date_acquired'])): ?>
            <div class="text-danger small mt-1"><?= sanitizeOutput($formErrors['date_acquired']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="insurance_status" name="insurance_status" value="1" <?= ($editItem && $editItem['insurance_status']) ? 'checked' : '' ?>>
              <label class="form-check-label text-secondary" for="insurance_status">Insured</label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-outline-light"><?= $editItem ? 'Update Item' : 'Create Item' ?></button>
            <?php if ($editItem): ?>
            <a href="/admin/inventory.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Inventory Table -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h5 mb-0" style="color: #c9a227;">All Items (<?= sanitizeOutput((string) $totalItems) ?>)</h2>
    </div>
    <div class="card-body">
      <?php if (empty($items)): ?>
      <p class="text-secondary text-center mb-0 py-3">No inventory items found.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Owner</th>
              <th scope="col">Type</th>
              <th scope="col">Weight</th>
              <th scope="col">Purity</th>
              <th scope="col">Carat</th>
              <th scope="col">Value</th>
              <th scope="col">Serial #</th>
              <th scope="col">Location</th>
              <th scope="col">Date Acquired</th>
              <th scope="col">Status</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr class="<?= !$item['is_active'] ? 'opacity-50' : '' ?>">
              <td><?= (int) $item['id'] ?></td>
              <td><?= sanitizeOutput($item['owner_name'] ?? 'Unknown') ?></td>
              <td><?= sanitizeOutput(ucfirst($item['gold_type'])) ?></td>
              <td><?= sanitizeOutput(number_format((float) $item['weight'], 3)) ?></td>
              <td><?= sanitizeOutput(number_format((float) $item['purity'], 4)) ?></td>
              <td><?= sanitizeOutput(number_format((float) ($item['carat'] ?? 24), 1)) ?>K</td>
              <td><?= sanitizeOutput(formatCurrency((float) ($item['item_value'] ?? 0))) ?></td>
              <td><?= sanitizeOutput($item['serial_number']) ?></td>
              <td><?= sanitizeOutput($item['vault_location']) ?></td>
              <td><?= !empty($item['date_acquired']) ? sanitizeOutput(formatDate($item['date_acquired'])) : '—' ?></td>
              <td>
                <?php if ($item['is_active']): ?>
                <span class="badge bg-success">Active</span>
                <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($item['is_active']): ?>
                <a href="/admin/inventory.php?edit=<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline-light">Edit</a>
                <form method="POST" action="/admin/inventory.php" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deactivate this item?')">Deactivate</button>
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
      <nav aria-label="Inventory pagination" class="mt-4">
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

<script>
var purityToKarat = {
  '0.9999': 24.0,
  '0.9990': 24.0,
  '0.9584': 23.0,
  '0.9167': 22.0,
  '0.8750': 21.0,
  '0.8333': 20.0,
  '0.7917': 19.0,
  '0.7500': 18.0,
  '0.5833': 14.0,
  '0.4167': 10.0,
  '0.3750': 9.0
};
function updateCarat(purityValue) {
  var caratField = document.getElementById('carat');
  if (purityToKarat[purityValue]) {
    caratField.value = purityToKarat[purityValue].toFixed(1);
  } else {
    caratField.value = '';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
