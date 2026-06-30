<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Client KYC Document Upload Page
 *
 * Allows clients to upload KYC documents (PDF, JPG, PNG, max 5MB),
 * stores files with system-generated filenames, and updates KYC status.
 *
 * Requirements: 12.5, 12.6, 12.7, 12.8
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';

use GOLS\Repositories\UserRepository;

// Require client authentication
requireClient();

$pdo = getDbConnection();
$userRepository = new UserRepository($pdo);

$userId = getCurrentUserId();
$user = $userRepository->findById($userId);

$successMessage = '';
$errorMessage = '';

// Allowed file types and max size
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errorMessage = 'Request could not be verified. Please try again.';
  } else
  if (!isset($_FILES['kyc_document']) || $_FILES['kyc_document']['error'] === UPLOAD_ERR_NO_FILE) {
    $errorMessage = 'Please select a file to upload.';
  } else {
    $file = $_FILES['kyc_document'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
      if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errorMessage = 'File size exceeds the maximum allowed size of 5MB.';
      } else {
        $errorMessage = 'An error occurred during file upload. Please try again.';
      }
    } else {
      // Validate file size (max 5MB)
      if ($file['size'] > UPLOAD_MAX_SIZE) {
        $errorMessage = 'File size exceeds the maximum allowed size of 5MB.';
      } else {
        // Validate file type by extension and MIME type
        $originalFilename = $file['name'];
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeType = $file['type'];

        // Also check actual MIME type using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMimeType = $finfo->file($file['tmp_name']);

        if (!in_array($extension, $allowedExtensions, true) || !in_array($actualMimeType, $allowedTypes, true)) {
          $errorMessage = 'Invalid file type. Only PDF, JPG, and PNG files are accepted.';
        } else {
          // Normalize extension (jpeg -> jpg)
          $storedExtension = $extension === 'jpeg' ? 'jpg' : $extension;

          // Map MIME to file_type enum value
          $fileTypeMap = [
            'application/pdf' => 'pdf',
            'image/jpeg'   => 'jpg',
            'image/png'    => 'png',
          ];
          $fileType = $fileTypeMap[$actualMimeType] ?? $storedExtension;

          // Generate system filename
          $storedFilename = generateRandomFilename($storedExtension);

          // Ensure upload directory exists
          $uploadDir = __DIR__ . '/../uploads/kyc/';
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }

          $destination = $uploadDir . $storedFilename;

          // Move uploaded file
          if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Store record in kyc_documents table
            $stmt = $pdo->prepare(
              'INSERT INTO kyc_documents (user_id, original_filename, stored_filename, file_type, file_size, uploaded_at)
               VALUES (:user_id, :original_filename, :stored_filename, :file_type, :file_size, :uploaded_at)'
            );
            $stmt->execute([
              'user_id'      => $userId,
              'original_filename' => $originalFilename,
              'stored_filename'  => $storedFilename,
              'file_type'     => $fileType,
              'file_size'     => $file['size'],
              'uploaded_at'    => date('Y-m-d H:i:s'),
            ]);

            // Update user kyc_status to "pending_review"
            $userRepository->updateKycStatus($userId, 'pending_review');

            $successMessage = 'KYC document uploaded successfully. Your verification status has been updated to pending review.';

            // Reload user data
            $user = $userRepository->findById($userId);
          } else {
            $errorMessage = 'Failed to save the uploaded file. Please try again.';
          }
        }
      }
    }
  }
}

// KYC status display mapping
$kycStatusLabels = [
  'not_submitted' => 'Not Submitted',
  'pending_review' => 'Pending Review',
  'approved'    => 'Approved',
  'rejected'    => 'Rejected',
];

$kycStatusBadges = [
  'not_submitted' => 'bg-secondary',
  'pending_review' => 'bg-warning text-dark',
  'approved'    => 'bg-success',
  'rejected'    => 'bg-danger',
];

$currentKycStatus = $user['kyc_status'] ?? 'not_submitted';
$kycLabel = $kycStatusLabels[$currentKycStatus] ?? 'Unknown';
$kycBadge = $kycStatusBadges[$currentKycStatus] ?? 'bg-secondary';

// Fetch previously uploaded documents
$docsStmt = $pdo->prepare(
  'SELECT original_filename, file_type, file_size, uploaded_at
   FROM kyc_documents
   WHERE user_id = :user_id
   ORDER BY uploaded_at DESC'
);
$docsStmt->execute(['user_id' => $userId]);
$uploadedDocuments = $docsStmt->fetchAll();

// Page setup
$pageTitle = 'KYC Upload - Aurum Vault Logistics';
$unreadCount = 0;

// Get unread notification count
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_status = 'unread'");
$notifStmt->execute(['user_id' => $userId]);
$unreadCount = (int) $notifStmt->fetchColumn();

require_once __DIR__ . '/../includes/templates/header.php';
require_once __DIR__ . '/../includes/templates/nav_client.php';
?>

<div class="container py-4">
  <h1 class="mb-4" style="color: #c9a227;">KYC Document Upload</h1>

  <!-- Current KYC Status -->
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-1">Current KYC Status</h5>
        <span class="badge <?= sanitizeOutput($kycBadge) ?>"><?= sanitizeOutput($kycLabel) ?></span>
      </div>
      <a href="/client/profile.php" class="btn btn-outline-light btn-sm">Back to Profile</a>
    </div>
  </div>

  <!-- Upload Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Upload Document</h5>
    </div>
    <div class="card-body">
      <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert"><?= sanitizeOutput($successMessage) ?></div>
      <?php endif; ?>
      <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert"><?= sanitizeOutput($errorMessage) ?></div>
      <?php endif; ?>

      <form method="POST" action="/client/kyc-upload.php" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="mb-3">
          <label for="kyc_document" class="form-label">Select Document</label>
          <input type="file" class="form-control" id="kyc_document" name="kyc_document"
              accept=".pdf,.jpg,.jpeg,.png" required>
          <div class="form-text text-secondary">Accepted formats: PDF, JPG, PNG. Maximum file size: 5MB.</div>
        </div>

        <button type="submit" class="btn btn-warning">Upload Document</button>
      </form>
    </div>
  </div>

  <!-- Previously Uploaded Documents -->
  <?php if (!empty($uploadedDocuments)): ?>
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Previously Uploaded Documents</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-dark table-striped table-hover">
          <thead>
            <tr>
              <th>Filename</th>
              <th>Type</th>
              <th>Size</th>
              <th>Uploaded</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($uploadedDocuments as $doc): ?>
            <tr>
              <td><?= sanitizeOutput($doc['original_filename']) ?></td>
              <td><span class="badge bg-secondary"><?= sanitizeOutput(strtoupper($doc['file_type'])) ?></span></td>
              <td><?= sanitizeOutput(number_format($doc['file_size'] / 1024, 1)) ?> KB</td>
              <td><?= sanitizeOutput(formatDateTime($doc['uploaded_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="card mb-4">
    <div class="card-body text-center text-secondary">
      <p class="mb-0">No documents uploaded yet.</p>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/templates/footer.php'; ?>
