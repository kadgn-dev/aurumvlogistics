<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;

/**
 * Repository for vault_inventory table operations.
 *
 * All client-facing queries enforce user_id scoping and only return
 * active items (is_active = 1). Uses PDO prepared statements for all queries.
 *
 * Requirements: 4.1, 4.4, 5.1, 5.2, 5.3
 */
class InventoryRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Find a single inventory item by its ID.
   *
   * @param int $id The inventory item ID
   * @return array|null The item record or null if not found
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare(
      'SELECT * FROM vault_inventory WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
  }

  /**
   * Find active inventory items for a specific user with pagination and optional filters.
   *
   * Only returns active items (is_active = 1) scoped to the given user_id.
   *
   * @param int $userId The user ID to scope results to
   * @param int $page The page number (1-based)
   * @param int $perPage Items per page (default 25)
   * @param string|null $goldType Optional filter by gold_type
   * @param string|null $vaultLocation Optional filter by vault_location
   * @return array{data: array, total: int} Paginated result with data and total count
   */
  public function findByUserId(
    int $userId,
    int $page,
    int $perPage = 25,
    ?string $goldType = null,
    ?string $vaultLocation = null
  ): array {
    $conditions = ['user_id = :user_id', 'is_active = 1'];
    $params = [':user_id' => $userId];

    if ($goldType !== null && $goldType !== '') {
      $conditions[] = 'gold_type = :gold_type';
      $params[':gold_type'] = $goldType;
    }

    if ($vaultLocation !== null && $vaultLocation !== '') {
      $conditions[] = 'vault_location = :vault_location';
      $params[':vault_location'] = $vaultLocation;
    }

    $where = implode(' AND ', $conditions);

    // Get total count
    $countSql = "SELECT COUNT(*) AS total FROM vault_inventory WHERE {$where}";
    $countStmt = $this->pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Get paginated data
    $offset = ($page - 1) * $perPage;
    $dataSql = "SELECT * FROM vault_inventory WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $dataStmt = $this->pdo->prepare($dataSql);

    foreach ($params as $key => $value) {
      $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();

    $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
      'data' => $data,
      'total' => $total,
    ];
  }

  /**
   * Find multiple inventory items by their IDs.
   *
   * @param array<int> $ids Array of item IDs
   * @return array Array of item records
   */
  public function findByIds(array $ids): array
  {
    if (empty($ids)) {
      return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
      $key = ":id_{$index}";
      $placeholders[] = $key;
      $params[$key] = (int) $id;
    }

    $placeholderStr = implode(', ', $placeholders);
    $stmt = $this->pdo->prepare(
      "SELECT * FROM vault_inventory WHERE id IN ({$placeholderStr})"
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Create a new inventory item.
   *
   * @param array $data Associative array with keys: user_id, gold_type, weight, purity,
   *          serial_number, vault_location, insurance_status, carat, date_acquired
   * @return int The ID of the newly created item
   */
  public function create(array $data): int
  {
    $stmt = $this->pdo->prepare(
      'INSERT INTO vault_inventory (user_id, gold_type, weight, purity, carat, serial_number, vault_location, insurance_status, item_value, date_acquired)
       VALUES (:user_id, :gold_type, :weight, :purity, :carat, :serial_number, :vault_location, :insurance_status, :item_value, :date_acquired)'
    );
    $stmt->execute([
      ':user_id' => $data['user_id'],
      ':gold_type' => $data['gold_type'],
      ':weight' => $data['weight'],
      ':purity' => $data['purity'],
      ':carat' => $data['carat'] ?? 24.0,
      ':serial_number' => $data['serial_number'],
      ':vault_location' => $data['vault_location'],
      ':insurance_status' => $data['insurance_status'] ? 1 : 0,
      ':item_value' => $data['item_value'] ?? 0.00,
      ':date_acquired' => !empty($data['date_acquired']) ? $data['date_acquired'] : null,
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Update an existing inventory item.
   *
   * @param int $id The item ID to update
   * @param array $data Associative array of fields to update
   * @return bool True if the update affected a row
   */
  public function update(int $id, array $data): bool
  {
    $allowedFields = [
      'gold_type',
      'weight',
      'purity',
      'carat',
      'serial_number',
      'vault_location',
      'insurance_status',
      'item_value',
      'date_acquired',
      'user_id',
    ];

    $setClauses = [];
    $params = [':id' => $id];

    foreach ($data as $field => $value) {
      if (!in_array($field, $allowedFields, true)) {
        continue;
      }

      $paramKey = ":{$field}";
      $setClauses[] = "{$field} = {$paramKey}";

      if ($field === 'insurance_status') {
        $params[$paramKey] = $value ? 1 : 0;
      } else {
        $params[$paramKey] = $value;
      }
    }

    if (empty($setClauses)) {
      return false;
    }

    $setStr = implode(', ', $setClauses);
    $stmt = $this->pdo->prepare(
      "UPDATE vault_inventory SET {$setStr} WHERE id = :id"
    );
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
  }

  /**
   * Soft-delete an inventory item by setting is_active = 0.
   *
   * @param int $id The item ID to deactivate
   * @return bool True if the deactivation affected a row
   */
  public function deactivate(int $id): bool
  {
    $stmt = $this->pdo->prepare(
      'UPDATE vault_inventory SET is_active = 0 WHERE id = :id AND is_active = 1'
    );
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Check if a serial number already exists in the database.
   *
   * @param string $serialNumber The serial number to check
   * @param int|null $excludeId Optional item ID to exclude (for updates)
   * @return bool True if the serial number exists
   */
  public function checkSerialExists(string $serialNumber, ?int $excludeId = null): bool
  {
    if ($excludeId !== null) {
      $stmt = $this->pdo->prepare(
        'SELECT COUNT(*) FROM vault_inventory WHERE serial_number = :serial_number AND id != :exclude_id'
      );
      $stmt->execute([
        ':serial_number' => $serialNumber,
        ':exclude_id' => $excludeId,
      ]);
    } else {
      $stmt = $this->pdo->prepare(
        'SELECT COUNT(*) FROM vault_inventory WHERE serial_number = :serial_number'
      );
      $stmt->execute([':serial_number' => $serialNumber]);
    }

    return (int) $stmt->fetchColumn() > 0;
  }

  /**
   * Get distinct vault locations for a specific user's active inventory.
   *
   * Used to populate filter dropdowns on the client inventory page.
   *
   * @param int $userId The user ID to scope results to
   * @return array<string> Array of distinct vault location strings
   */
  public function getDistinctVaultLocations(int $userId): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT DISTINCT vault_location FROM vault_inventory WHERE user_id = :user_id AND is_active = 1 ORDER BY vault_location ASC'
    );
    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
  }

  /**
   * Get portfolio summary for a user (total weight, total value, total insured value, vault locations count).
   *
   * Only considers active items (is_active = 1) scoped to the given user_id.
   *
   * @param int $userId The user ID to get portfolio summary for
   * @return array{total_weight: float, total_value: float, total_insured_value: float, vault_locations: int, item_count: int}
   */
  public function getPortfolioSummary(int $userId): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT
        COALESCE(SUM(weight), 0) AS total_weight,
        COALESCE(SUM(item_value), 0) AS total_value,
        COALESCE(SUM(CASE WHEN insurance_status = 1 THEN item_value ELSE 0 END), 0) AS total_insured_value,
        COUNT(DISTINCT vault_location) AS vault_locations,
        COUNT(*) AS item_count
       FROM vault_inventory
       WHERE user_id = :user_id AND is_active = 1'
    );
    $stmt->execute([':user_id' => $userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
      'total_weight' => (float) ($row['total_weight'] ?? 0),
      'total_value' => (float) ($row['total_value'] ?? 0),
      'total_insured_value' => (float) ($row['total_insured_value'] ?? 0),
      'vault_locations' => (int) ($row['vault_locations'] ?? 0),
      'item_count' => (int) ($row['item_count'] ?? 0),
    ];
  }

  /**
   * Get item IDs that are currently in active shipments (pending_approval or in_transit).
   *
   * Used to prevent items from being included in multiple active shipments.
   *
   * @param array<int> $itemIds Array of inventory item IDs to check
   * @return array<int> Array of item IDs that are in active shipments
   */
  public function getItemsInActiveShipments(array $itemIds): array
  {
    if (empty($itemIds)) {
      return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($itemIds as $index => $id) {
      $key = ":id_{$index}";
      $placeholders[] = $key;
      $params[$key] = (int) $id;
    }

    $placeholderStr = implode(', ', $placeholders);
    $stmt = $this->pdo->prepare(
      "SELECT DISTINCT si.inventory_id
       FROM shipment_items si
       INNER JOIN shipments s ON s.id = si.shipment_id
       WHERE si.inventory_id IN ({$placeholderStr})
        AND s.status IN ('pending_approval', 'in_transit')"
    );
    $stmt->execute($params);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  }
}
