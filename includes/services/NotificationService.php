<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\NotificationRepository;
use GOLS\Result;

/**
 * Aurum Vault Logistics Platform (AVL)
 * NotificationService - Business logic for notification management
 *
 * Handles notification creation with retry logic, unread count display
 * (capped at "99+"), paginated retrieval, and read status management.
 *
 * Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6
 */
class NotificationService
{
  private NotificationRepository $repository;

  private const MAX_RETRY_ATTEMPTS = 3;
  private const NOTIFICATIONS_PER_PAGE = 20;
  private const UNREAD_COUNT_CAP = 99;

  public function __construct(NotificationRepository $repository)
  {
    $this->repository = $repository;
  }

  /**
   * Create a notification with retry logic (up to 3 attempts on failure).
   *
   * Logs failure for admin review if all attempts fail.
   *
   * @param int $userId The user to notify
   * @param string $type The event type (e.g., 'shipment_status', 'invoice_generated')
   * @param string $message Human-readable notification message
   * @param int|null $referenceId Optional ID of the related entity
   * @param string|null $referenceType Optional type of the related entity
   * @return Result Success with notification_id or error
   */
  public function create(
    int $userId,
    string $type,
    string $message,
    ?int $referenceId = null,
    ?string $referenceType = null
  ): Result {
    $lastException = null;

    for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
      try {
        $notificationId = $this->repository->create(
          $userId,
          $type,
          $message,
          $referenceId,
          $referenceType
        );

        return Result::success(['notification_id' => $notificationId]);
      } catch (\Exception $e) {
        $lastException = $e;
        // Continue to next attempt
      }
    }

    // All attempts failed - log for admin review
    error_log(sprintf(
      '[NotificationService] Failed to create notification after %d attempts. '
      . 'User: %d, Type: %s, Message: %s, Error: %s',
      self::MAX_RETRY_ATTEMPTS,
      $userId,
      $type,
      $message,
      $lastException ? $lastException->getMessage() : 'Unknown error'
    ));

    return Result::error(
      'NOTIFICATION_FAILED',
      'Failed to create notification after multiple attempts.'
    );
  }

  /**
   * Get the unread notification count for a user.
   *
   * Returns the raw integer count. Use getFormattedUnreadCount() for display.
   *
   * @param int $userId The user whose unread count to retrieve
   * @return int The unread notification count
   */
  public function getUnreadCount(int $userId): int
  {
    return $this->repository->getUnreadCount($userId);
  }

  /**
   * Get the formatted unread count for display.
   *
   * Returns "99+" when count exceeds 99, otherwise the number as string.
   *
   * @param int $userId The user whose unread count to format
   * @return string Formatted count ("99+" or numeric string)
   */
  public function getFormattedUnreadCount(int $userId): string
  {
    $count = $this->repository->getUnreadCount($userId);

    if ($count > self::UNREAD_COUNT_CAP) {
      return '99+';
    }

    return (string) $count;
  }

  /**
   * Get paginated notifications for a user (20 per page).
   *
   * Returns notifications in reverse chronological order with read/unread indicators.
   *
   * @param int $userId The user whose notifications to retrieve
   * @param int $page Page number (1-based)
   * @return array{data: array, total: int, page: int, perPage: int}
   */
  public function getUserNotifications(int $userId, int $page): array
  {
    $page = max(1, $page);

    $result = $this->repository->findByUserId($userId, $page, self::NOTIFICATIONS_PER_PAGE);

    return [
      'data' => $result['data'],
      'total' => $result['total'],
      'page' => $page,
      'perPage' => self::NOTIFICATIONS_PER_PAGE,
    ];
  }

  /**
   * Mark a single notification as read.
   *
   * Scoped to the owning user for security.
   *
   * @param int $notificationId The notification to mark as read
   * @param int $userId The user who owns the notification
   * @return Result Success or error if not found/not owned
   */
  public function markAsRead(int $notificationId, int $userId): Result
  {
    $updated = $this->repository->markAsRead($notificationId, $userId);

    if (!$updated) {
      return Result::error(
        'NOTIFICATION_NOT_FOUND',
        'Notification not found or does not belong to this user.'
      );
    }

    return Result::success(['notification_id' => $notificationId]);
  }

  /**
   * Mark all unread notifications as read for a user.
   *
   * @param int $userId The user whose notifications to mark as read
   * @return Result Success with count of updated notifications
   */
  public function markAllAsRead(int $userId): Result
  {
    $count = $this->repository->markAllAsRead($userId);

    return Result::success(['count' => $count]);
  }

  /**
   * Get recent notifications for dashboard display.
   *
   * @param int $userId The user whose recent notifications to retrieve
   * @param int $limit Maximum number of notifications to return (default 3)
   * @return array List of recent notification records
   */
  public function getRecentNotifications(int $userId, int $limit = 3): array
  {
    return $this->repository->getRecentByUserId($userId, $limit);
  }
}
