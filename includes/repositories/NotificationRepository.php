<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;

/**
 * Aurum Vault Logistics Platform (AVL)
 * Notification Repository - Data access layer for notifications table
 *
 * Handles CRUD operations for system-generated notifications.
 * All queries use PDO prepared statements to prevent SQL injection.
 *
 * Requirements: 13.1, 13.2, 13.3, 13.6
 */
class NotificationRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Create a new notification record.
   *
   * @param int $userId The user to notify
   * @param string $eventType The type of event (e.g., 'shipment_status', 'invoice_generated')
   * @param string $message Human-readable notification message
   * @param int|null $referenceId Optional ID of the related entity
   * @param string|null $referenceType Optional type of the related entity (e.g., 'shipment', 'invoice')
   * @return int The ID of the newly created notification
   */
  public function create(
    int $userId,
    string $eventType,
    string $message,
    ?int $referenceId = null,
    ?string $referenceType = null
  ): int {
    $stmt = $this->pdo->prepare(
      'INSERT INTO notifications (user_id, event_type, message, reference_id, reference_type)
       VALUES (:user_id, :event_type, :message, :reference_id, :reference_type)'
    );

    $stmt->execute([
      ':user_id' => $userId,
      ':event_type' => $eventType,
      ':message' => $message,
      ':reference_id' => $referenceId,
      ':reference_type' => $referenceType,
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Find notifications for a user with pagination, sorted by created_at DESC.
   *
   * @param int $userId The user whose notifications to retrieve
   * @param int $page The page number (1-based)
   * @param int $perPage Number of notifications per page (default 20)
   * @return array{data: array, total: int} Paginated result with data and total count
   */
  public function findByUserId(int $userId, int $page, int $perPage = 20): array
  {
    // Get total count
    $countStmt = $this->pdo->prepare(
      'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id'
    );
    $countStmt->execute([':user_id' => $userId]);
    $total = (int) $countStmt->fetchColumn();

    // Get paginated data
    $offset = ($page - 1) * $perPage;

    $stmt = $this->pdo->prepare(
      'SELECT id, user_id, event_type, message, reference_id, reference_type, read_status, created_at
       FROM notifications
       WHERE user_id = :user_id
       ORDER BY created_at DESC
       LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
      'data' => $data,
      'total' => $total,
    ];
  }

  /**
   * Get the count of unread notifications for a user.
   *
   * @param int $userId The user whose unread count to retrieve
   * @return int Number of unread notifications
   */
  public function getUnreadCount(int $userId): int
  {
    $stmt = $this->pdo->prepare(
      'SELECT COUNT(*) FROM notifications
       WHERE user_id = :user_id AND read_status = :read_status'
    );
    $stmt->execute([
      ':user_id' => $userId,
      ':read_status' => 'unread',
    ]);

    return (int) $stmt->fetchColumn();
  }

  /**
   * Mark a single notification as read, scoped to the owning user.
   *
   * @param int $notificationId The notification to mark as read
   * @param int $userId The user who owns the notification (security scoping)
   * @return bool True if the notification was updated, false if not found or not owned by user
   */
  public function markAsRead(int $notificationId, int $userId): bool
  {
    $stmt = $this->pdo->prepare(
      'UPDATE notifications
       SET read_status = :read_status
       WHERE id = :id AND user_id = :user_id'
    );
    $stmt->execute([
      ':read_status' => 'read',
      ':id' => $notificationId,
      ':user_id' => $userId,
    ]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Mark all unread notifications as read for a user.
   *
   * @param int $userId The user whose notifications to mark as read
   * @return int Number of notifications that were updated
   */
  public function markAllAsRead(int $userId): int
  {
    $stmt = $this->pdo->prepare(
      'UPDATE notifications
       SET read_status = :new_status
       WHERE user_id = :user_id AND read_status = :current_status'
    );
    $stmt->execute([
      ':new_status' => 'read',
      ':user_id' => $userId,
      ':current_status' => 'unread',
    ]);

    return $stmt->rowCount();
  }

  /**
   * Get the most recent notifications for a user (for dashboard display).
   *
   * @param int $userId The user whose recent notifications to retrieve
   * @param int $limit Maximum number of notifications to return (default 3)
   * @return array List of recent notification records
   */
  public function getRecentByUserId(int $userId, int $limit = 3): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT id, user_id, event_type, message, reference_id, reference_type, read_status, created_at
       FROM notifications
       WHERE user_id = :user_id
       ORDER BY created_at DESC
       LIMIT :limit'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
