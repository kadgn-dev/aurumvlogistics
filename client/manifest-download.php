<?php

/**
 * Aurum Vault Logistics Platform (AVL)
 * Shipment Manifest PDF Download
 *
 * Downloads the manifest PDF for a specific shipment.
 * Clients can only download manifests for their own shipments.
 * Admins can download manifests for any shipment.
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/repositories/ShipmentRepository.php';

use GOLS\Repositories\ShipmentRepository;

// Require authentication (client or admin)
requireAuth();

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';
$pageTitle = 'Manifest Download - Aurum Vault Logistics';
$errorMessage = '';

// Get shipment_id from GET parameter
$shipmentId = isset($_GET['shipment_id']) ? (int) $_GET['shipment_id'] : 0;

if ($shipmentId <= 0) {
  $errorMessage = 'Invalid shipment ID. Please select a valid shipment.';
} else {
  // Initialize repository
  $pdo = getDbConnection();
  $shipmentRepository = new ShipmentRepository($pdo);

  // Load shipment from database
  $shipment = $shipmentRepository->findById($shipmentId);

  if ($shipment === null) {
    http_response_code(404);
    $errorMessage = 'Shipment not found.';
  } else {
    // Access control: clients can only access their own shipments (Requirement 3.3)
    if ($userRole === 'client' && (int) $shipment['user_id'] !== $userId) {
      http_response_code(403);
      $errorMessage = 'You do not have permission to download this manifest.';
    } else {
      // Admin can access any manifest; client has passed ownership check
      $manifestPath = $shipment['manifest_path'] ?? null;

      if ($manifestPath === null || $manifestPath === '') {
        $errorMessage = 'Manifest unavailable';
      } else {
        // Build full file path from relative path
        $fullPath = dirname(__DIR__) . '/' . $manifestPath;

        if (!file_exists($fullPath)) {
          $errorMessage = 'Manifest unavailable';
        } else {
          // Serve the PDF file (Requirement 3.4)
          header('Content-Type: application/pdf');
          header('Content-Disposition: attachment; filename="manifest_' . $shipmentId . '.pdf"');
          header('Content-Length: ' . filesize($fullPath));
          header('Cache-Control: no-cache, no-store, must-revalidate');
          header('Pragma: no-cache');
          header('Expires: 0');

          readfile($fullPath);
          exit;
        }
      }
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

// Include appropriate navigation based on role
if ($userRole === 'admin') {
  require_once __DIR__ . '/../includes/templates/nav_admin.php';
} else {
  require_once __DIR__ . '/../includes/templates/nav_client.php';
}
?>

<!-- Manifest Download Error Page -->
<main class="container py-5">
  <h1 class="mb-4" style="color: #c9a227;">Manifest Download</h1>

  <div class="alert alert-danger" role="alert">
    <?= sanitizeOutput($errorMessage) ?>
  </div>

  <a href="/client/shipments.php" class="btn btn-outline-light">
    &larr; Back to Shipments
  </a>
</main>

<?php
// Include footer template
require_once __DIR__ . '/../includes/templates/footer.php';
?>
