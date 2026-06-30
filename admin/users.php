<?php
/**
 * Aurum Vault Logistics Platform (AVL)
 * Admin Users Management - Paginated User List, Search, KYC Approval, Suspension
 *
 * Displays paginated user list (20/page, sorted by created_at DESC),
 * search form (name/email), KYC approval action, and account suspension action.
 *
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 17.2
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Result.php';
require_once __DIR__ . '/../includes/ValidationResult.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
require_once __DIR__ . '/../includes/services/UserManagementService.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';
require_once __DIR__ . '/../includes/services/EmailService.php';
require_once __DIR__ . '/../includes/services/AuditService.php';

use GOLS\Repositories\UserRepository;
use GOLS\Repositories\NotificationRepository;
use GOLS\Services\UserManagementService;
use GOLS\Services\NotificationService;
use GOLS\Services\EmailService;
use GOLS\Services\AuditService;

// Require admin authentication
requireAdmin();

$pdo = getDbConnection();
$userRepo = new UserRepository($pdo);
$notificationRepo = new NotificationRepository($pdo);
$notificationService = new NotificationService($notificationRepo);
$emailService = new EmailService($pdo);
$userManagementService = new UserManagementService($userRepo, $notificationService, $emailService);
$auditService = new AuditService($pdo);

$adminId = getCurrentUserId();
$successMessage = '';
$errorMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  enforceCsrf();

  $action = $_POST['action'] ?? '';
  $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

  if ($action === 'approve_kyc' && $userId > 0) {
    $result = $userManagementService->approveKyc($userId, $adminId);
    if ($result->success) {
      $successMessage = 'KYC approved successfully.';
      $auditService->log('kyc_approval', $adminId, 'user', $userId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to approve KYC.';
    }
  } elseif ($action === 'reject_kyc' && $userId > 0) {
    $result = $userManagementService->rejectKyc($userId, $adminId);
    if ($result->success) {
      $successMessage = 'KYC rejected.';
      $auditService->log('kyc_rejection', $adminId, 'user', $userId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to reject KYC.';
    }
  } elseif ($action === 'suspend' && $userId > 0) {
    $result = $userManagementService->suspendUser($userId, $adminId);
    if ($result->success) {
      $successMessage = 'User account suspended successfully.';
      $auditService->log('user_suspension', $adminId, 'user', $userId);
    } else {
      $errorMessage = $result->errorMessage ?? 'Failed to suspend user.';
    }
  }
}

// Handle search
$searchTerm = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($searchTerm !== '') {
  $users = $userManagementService->searchUsers($searchTerm);
  $totalPages = 1;
  $totalUsers = count($users);
} else {
  $result = $userManagementService->getPaginatedUsers($page);
  $users = $result['data'];
  $totalUsers = $result['total'];
  $totalPages = getTotalPages($totalUsers, $result['perPage']);
}

$pageTitle = 'User Management - Aurum Vault Logistics Admin';

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_admin.php';
?>

<main class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">User Management</h1>

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

  <!-- Search Form -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="/admin/users.php" class="row g-3 align-items-end">
        <div class="col-md-8">
          <label for="search" class="form-label text-secondary">Search by name or email</label>
          <input type="text" class="form-control" id="search" name="search" value="<?= sanitizeOutput($searchTerm) ?>" placeholder="Enter name or email...">
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-outline-light me-2">Search</button>
          <?php if ($searchTerm !== ''): ?>
          <a href="/admin/users.php" class="btn btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Users Table -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h5 mb-0" style="color: #c9a227;">Users (<?= sanitizeOutput((string) $totalUsers) ?>)</h2>
    </div>
    <div class="card-body">
      <?php if (empty($users)): ?>
      <p class="text-secondary text-center mb-0 py-3">No users found.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover mb-0">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Role</th>
              <th scope="col">KYC Status</th>
              <th scope="col">Created</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
              <td><?= sanitizeOutput($user['name']) ?></td>
              <td><?= sanitizeOutput($user['email']) ?></td>
              <td><span class="badge bg-secondary"><?= sanitizeOutput(ucfirst($user['role'])) ?></span></td>
              <td>
                <?php
                $kycClasses = [
                  'not_submitted' => 'bg-secondary',
                  'pending_review' => 'bg-warning text-dark',
                  'approved' => 'bg-success',
                  'rejected' => 'bg-danger',
                ];
                $kycLabel = str_replace('_', ' ', $user['kyc_status']);
                $kycBadge = $kycClasses[$user['kyc_status']] ?? 'bg-secondary';
                ?>
                <span class="badge <?= $kycBadge ?>"><?= sanitizeOutput(ucwords($kycLabel)) ?></span>
              </td>
              <td><?= sanitizeOutput(formatDate($user['created_at'])) ?></td>
              <td>
                <?php if ($user['kyc_status'] === 'pending_review'): ?>
                <form method="POST" action="/admin/users.php" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                  <input type="hidden" name="action" value="approve_kyc">
                  <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve KYC for this user?')">Approve KYC</button>
                </form>
                <?php endif; ?>

                <?php if ($user['role'] !== 'admin' && $user['status'] !== 'suspended'): ?>
                <form method="POST" action="/admin/users.php" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                  <input type="hidden" name="action" value="suspend">
                  <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Suspend this user account?')">Suspend</button>
                </form>
                <?php endif; ?>

                <?php if ($user['status'] === 'suspended'): ?>
                <span class="badge bg-danger">Suspended</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($searchTerm === '' && $totalPages > 1): ?>
      <nav aria-label="User pagination" class="mt-4">
        <ul class="pagination justify-content-center mb-0">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
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
