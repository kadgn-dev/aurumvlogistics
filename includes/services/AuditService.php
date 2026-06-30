<?php

declare(strict_types=1);

namespace GOLS\Services;

use PDO;

/**
 * Aurum Vault Logistics Platform (AVL)
 * AuditService - Centralized audit logging for admin actions and security events.
 *
 * Fire-and-forget pattern: log() never throws exceptions.
 * All database errors are written to error_log and execution continues.
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 */
class AuditService
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Log an audit event. Fire-and-forget — never throws.
   *
   * @param string     $eventType  The type of event (e.g. 'kyc_approval', 'access_denied')
   * @param int|null   $actorId    The user ID performing the action, or null for unauthenticated events
   * @param string|null $targetType The type of entity being acted upon (e.g. 'user', 'invoice')
   * @param int|null   $targetId   The ID of the target entity
   * @param array|null  $metadata   Additional context data (stored as JSON)
   * @return void
   */
  public function log(
    string $eventType,
    ?int $actorId = null,
    ?string $targetType = null,
    ?int $targetId = null,
    ?array $metadata = null
  ): void {
    try {
      $stmt = $this->pdo->prepare(
        'INSERT INTO audit_log (event_type, actor_id, target_type, target_id, ip_address, metadata, created_at)
         VALUES (:event_type, :actor_id, :target_type, :target_id, :ip_address, :metadata, :created_at)'
      );
      $stmt->execute([
        'event_type'  => $eventType,
        'actor_id'    => $actorId,
        'target_type' => $targetType,
        'target_id'   => $targetId,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'metadata'    => $metadata !== null ? json_encode($metadata) : null,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
    } catch (\Exception $e) {
      error_log('[AuditService] Failed to log event: ' . $e->getMessage());
    }
  }

  /**
   * Query audit log with optional filters.
   *
   * Builds dynamic WHERE clauses with bound parameters to prevent SQL injection.
   * Results are ordered by created_at descending with LIMIT/OFFSET pagination.
   *
   * @param string|null $eventType Filter by event type
   * @param string|null $dateFrom  Filter records on or after this date (Y-m-d)
   * @param string|null $dateTo    Filter records on or before this date (Y-m-d)
   * @param int|null    $actorId   Filter by actor user ID
   * @param int         $limit     Maximum records to return (default 20)
   * @param int         $offset    Number of records to skip (default 0)
   * @return array Matching records ordered by created_at DESC
   */
  public function query(
    ?string $eventType = null,
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?int $actorId = null,
    int $limit = 20,
    int $offset = 0
  ): array {
    $conditions = [];
    $params = [];

    if ($eventType !== null && $eventType !== '') {
      $conditions[] = 'event_type = :event_type';
      $params['event_type'] = $eventType;
    }
    if ($dateFrom !== null && $dateFrom !== '') {
      $conditions[] = 'created_at >= :date_from';
      $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== null && $dateTo !== '') {
      $conditions[] = 'created_at <= :date_to';
      $params['date_to'] = $dateTo . ' 23:59:59';
    }
    if ($actorId !== null) {
      $conditions[] = 'actor_id = :actor_id';
      $params['actor_id'] = $actorId;
    }

    $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "SELECT * FROM audit_log {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $this->pdo->prepare($sql);

    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Count audit log records matching filters (for pagination).
   *
   * Uses the same filter logic as query() but returns COUNT(*).
   *
   * @param string|null $eventType Filter by event type
   * @param string|null $dateFrom  Filter records on or after this date (Y-m-d)
   * @param string|null $dateTo    Filter records on or before this date (Y-m-d)
   * @param int|null    $actorId   Filter by actor user ID
   * @return int Total matching records
   */
  public function count(
    ?string $eventType = null,
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?int $actorId = null
  ): int {
    $conditions = [];
    $params = [];

    if ($eventType !== null && $eventType !== '') {
      $conditions[] = 'event_type = :event_type';
      $params['event_type'] = $eventType;
    }
    if ($dateFrom !== null && $dateFrom !== '') {
      $conditions[] = 'created_at >= :date_from';
      $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== null && $dateTo !== '') {
      $conditions[] = 'created_at <= :date_to';
      $params['date_to'] = $dateTo . ' 23:59:59';
    }
    if ($actorId !== null) {
      $conditions[] = 'actor_id = :actor_id';
      $params['actor_id'] = $actorId;
    }

    $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log {$where}");
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
  }

  /**
   * Delete audit records older than 90 days.
   *
   * @return int Number of deleted records
   */
  public function cleanup(): int
  {
    $cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
    $stmt = $this->pdo->prepare('DELETE FROM audit_log WHERE created_at < :cutoff');
    $stmt->execute(['cutoff' => $cutoff]);

    return $stmt->rowCount();
  }
}
