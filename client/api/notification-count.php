<?php

declare(strict_types=1);

/**
 * Aurum Vault Logistics Platform (AVL)
 * Notification Count API Endpoint
 *
 * Returns the unread notification count for the authenticated client
 * as a JSON response: {"count": N}
 *
 * Requirements: 13.4
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/repositories/NotificationRepository.php';

use GOLS\Repositories\NotificationRepository;

// Set JSON content type
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Require authentication using standard requireAuth() flow
requireAuth();

$userId = getCurrentUserId();

try {
  $pdo = getDbConnection();
  $repository = new NotificationRepository($pdo);
  $count = $repository->getUnreadCount($userId);

  echo json_encode(['count' => $count]);
} catch (\Exception $e) {
  // Return 0 on error to avoid breaking the UI
  echo json_encode(['count' => 0]);
}
