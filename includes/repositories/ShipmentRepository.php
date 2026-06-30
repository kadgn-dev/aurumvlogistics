<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;
use PDOException;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ShipmentRepository - Data access layer for shipments, shipment items, and status history.
 *
 * Requirements: 6.1, 7.1, 7.2, 7.3, 8.1, 8.2
 */
class ShipmentRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Find a shipment by its ID.
   *
   * @param int $id Shipment ID
   * @return array|null Shipment record or null if not found
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare(
      'SELECT * FROM shipments WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();

    return $result ?: null;
  }

  /**
   * Find shipments by user ID with pagination, sorted by updated_at DESC.
   *
   * @param int $userId User ID
   * @param int $page Page number (1-based)
   * @param int $perPage Records per page
   * @return array ['data' => [...], 'total' => int]
   */
  public function findByUserId(int $userId, int $page, int $perPage = 20): array
  {
    $offset = ($page - 1) * $perPage;

    $countStmt = $this->pdo->prepare(
      'SELECT COUNT(*) FROM shipments WHERE user_id = :user_id'
    );
    $countStmt->execute(['user_id' => $userId]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $this->pdo->prepare(
      'SELECT * FROM shipments WHERE user_id = :user_id ORDER BY updated_at DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
      'data' => $stmt->fetchAll(),
      'total' => $total,
    ];
  }

  /**
   * Create a new shipment with associated inventory items.
   * Uses a transaction to ensure atomicity of shipment + shipment_items creation.
   *
   * @param array $data Shipment data (user_id, street, city, state_province, postal_code, country, insurance_selected, insured_value)
   * @param array $inventoryItemIds Array of inventory item IDs to associate
   * @return int The created shipment ID
   * @throws PDOException If the transaction fails
   */
  public function create(array $data, array $inventoryItemIds): int
  {
    $this->pdo->beginTransaction();

    try {
      $stmt = $this->pdo->prepare(
        'INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, insurance_selected, insured_value, status)
         VALUES (:user_id, :street, :city, :state_province, :postal_code, :country, :insurance_selected, :insured_value, :status)'
      );
      $stmt->execute([
        'user_id' => $data['user_id'],
        'street' => $data['street'],
        'city' => $data['city'],
        'state_province' => $data['state_province'],
        'postal_code' => $data['postal_code'],
        'country' => $data['country'],
        'insurance_selected' => $data['insurance_selected'] ? 1 : 0,
        'insured_value' => $data['insured_value'] ?? 0.00,
        'status' => 'pending_approval',
      ]);

      $shipmentId = (int) $this->pdo->lastInsertId();

      // Insert shipment items into junction table
      $itemStmt = $this->pdo->prepare(
        'INSERT INTO shipment_items (shipment_id, inventory_id) VALUES (:shipment_id, :inventory_id)'
      );
      foreach ($inventoryItemIds as $inventoryId) {
        $itemStmt->execute([
          'shipment_id' => $shipmentId,
          'inventory_id' => $inventoryId,
        ]);
      }

      // Record initial status in history
      $historyStmt = $this->pdo->prepare(
        'INSERT INTO shipment_status_history (shipment_id, status, changed_by)
         VALUES (:shipment_id, :status, :changed_by)'
      );
      $historyStmt->execute([
        'shipment_id' => $shipmentId,
        'status' => 'pending_approval',
        'changed_by' => $data['user_id'],
      ]);

      $this->pdo->commit();

      return $shipmentId;
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  /**
   * Update shipment status and record the change in status history.
   *
   * @param int $id Shipment ID
   * @param string $status New status value
   * @param int $changedBy User ID who made the change
   * @return bool True if the update was successful
   */
  public function updateStatus(int $id, string $status, int $changedBy): bool
  {
    $this->pdo->beginTransaction();

    try {
      $now = date('Y-m-d H:i:s');
      $stmt = $this->pdo->prepare(
        'UPDATE shipments SET status = :status, updated_at = :updated_at WHERE id = :id'
      );
      $stmt->execute([
        'status' => $status,
        'updated_at' => $now,
        'id' => $id,
      ]);

      $affected = $stmt->rowCount();

      // Record status change in history
      $historyStmt = $this->pdo->prepare(
        'INSERT INTO shipment_status_history (shipment_id, status, changed_by)
         VALUES (:shipment_id, :status, :changed_by)'
      );
      $historyStmt->execute([
        'shipment_id' => $id,
        'status' => $status,
        'changed_by' => $changedBy,
      ]);

      $this->pdo->commit();

      return $affected > 0;
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  /**
   * Assign tracking number and carrier to a shipment.
   *
   * @param int $id Shipment ID
   * @param string $trackingNumber Tracking number
   * @param string $carrier Carrier name (dhl, fedex, brinks)
   * @return bool True if the update was successful
   */
  public function assignTracking(int $id, string $trackingNumber, string $carrier): bool
  {
    $now = date('Y-m-d H:i:s');
    $stmt = $this->pdo->prepare(
      'UPDATE shipments SET tracking_number = :tracking_number, carrier = :carrier, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
      'tracking_number' => $trackingNumber,
      'carrier' => $carrier,
      'updated_at' => $now,
      'id' => $id,
    ]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Set rejection reason for a shipment.
   *
   * @param int $id Shipment ID
   * @param string $reason Rejection reason text
   * @return bool True if the update was successful
   */
  public function setRejectionReason(int $id, string $reason): bool
  {
    $now = date('Y-m-d H:i:s');
    $stmt = $this->pdo->prepare(
      'UPDATE shipments SET rejection_reason = :reason, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
      'reason' => $reason,
      'updated_at' => $now,
      'id' => $id,
    ]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Update the manifest file path for a shipment.
   *
   * @param int $shipmentId Shipment ID
   * @param string $manifestPath Relative path to the manifest PDF file
   * @return bool True if the update was successful
   */
  public function updateManifestPath(int $shipmentId, string $manifestPath): bool
  {
    $now = date('Y-m-d H:i:s');
    $stmt = $this->pdo->prepare(
      'UPDATE shipments SET manifest_path = :manifest_path, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
      'manifest_path' => $manifestPath,
      'updated_at' => $now,
      'id' => $shipmentId,
    ]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Get the full status history for a shipment, ordered by changed_at ASC.
   *
   * @param int $shipmentId Shipment ID
   * @return array Array of status history records with timestamps
   */
  public function getStatusHistory(int $shipmentId): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT ssh.id, ssh.shipment_id, ssh.status, ssh.changed_at, ssh.changed_by, u.name AS changed_by_name
       FROM shipment_status_history ssh
       LEFT JOIN users u ON ssh.changed_by = u.id
       WHERE ssh.shipment_id = :shipment_id
       ORDER BY ssh.changed_at ASC'
    );
    $stmt->execute(['shipment_id' => $shipmentId]);

    return $stmt->fetchAll();
  }

  /**
   * Find a shipment by tracking number, scoped to a specific user.
   *
   * @param string $trackingNumber Tracking number to search
   * @param int $userId User ID to scope the search
   * @return array|null Shipment record or null if not found
   */
  public function findByTracking(string $trackingNumber, int $userId): ?array
  {
    $stmt = $this->pdo->prepare(
      'SELECT * FROM shipments WHERE tracking_number = :tracking_number AND user_id = :user_id'
    );
    $stmt->execute([
      'tracking_number' => $trackingNumber,
      'user_id' => $userId,
    ]);
    $result = $stmt->fetch();

    return $result ?: null;
  }

  /**
   * Get the most recent shipments for a user, sorted by updated_at DESC.
   *
   * @param int $userId User ID
   * @param int $limit Maximum number of records to return
   * @return array Array of recent shipment records
   */
  public function getRecentByUserId(int $userId, int $limit = 5): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT * FROM shipments WHERE user_id = :user_id ORDER BY updated_at DESC LIMIT :limit'
    );
    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get all shipments with pagination (admin view).
   *
   * @param int $page Page number (1-based)
   * @param int $perPage Records per page
   * @return array ['data' => [...], 'total' => int]
   */
  public function getAllPaginated(int $page, int $perPage = 20): array
  {
    $offset = ($page - 1) * $perPage;

    $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM shipments');
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $stmt = $this->pdo->prepare(
      'SELECT s.*, u.name AS user_name, u.email AS user_email
       FROM shipments s
       LEFT JOIN users u ON s.user_id = u.id
       ORDER BY s.updated_at DESC
       LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
      'data' => $stmt->fetchAll(),
      'total' => $total,
    ];
  }

  /**
   * Get inventory items associated with a shipment.
   *
   * @param int $shipmentId Shipment ID
   * @return array Array of inventory item records
   */
  public function getShipmentItems(int $shipmentId): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT vi.* FROM vault_inventory vi
       INNER JOIN shipment_items si ON vi.id = si.inventory_id
       WHERE si.shipment_id = :shipment_id'
    );
    $stmt->execute(['shipment_id' => $shipmentId]);

    return $stmt->fetchAll();
  }

  /**
   * Check if an inventory item is currently assigned to a pending or in-transit shipment.
   *
   * @param int $inventoryId Inventory item ID
   * @return bool True if the item is in an active shipment
   */
  public function isItemInActiveShipment(int $inventoryId): bool
  {
    $stmt = $this->pdo->prepare(
      "SELECT COUNT(*) FROM shipment_items si
       INNER JOIN shipments s ON si.shipment_id = s.id
       WHERE si.inventory_id = :inventory_id
       AND s.status IN ('pending_approval', 'approved', 'ready_for_shipment', 'in_transit')"
    );
    $stmt->execute(['inventory_id' => $inventoryId]);

    return (int) $stmt->fetchColumn() > 0;
  }
}
