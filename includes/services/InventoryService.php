<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\InventoryRepository;
use GOLS\Result;
use GOLS\Validators\InventoryValidator;

/**
 * Aurum Vault Logistics Platform (AVL)
 * InventoryService - Business logic for vault inventory management
 *
 * Handles client inventory viewing (paginated, filtered), admin CRUD operations
 * (create, update, deactivate), ownership validation, and active shipment checks.
 *
 * Requirements: 4.1, 4.3, 5.1, 5.2, 5.3, 6.2
 */
class InventoryService
{
  private InventoryRepository $repository;
  private InventoryValidator $validator;

  public function __construct(InventoryRepository $repository, InventoryValidator $validator)
  {
    $this->repository = $repository;
    $this->validator = $validator;
  }

  /**
   * Get paginated inventory for a client with optional filters.
   *
   * Only returns active items scoped to the given user.
   *
   * @param int $userId The client's user ID
   * @param int $page Page number (1-based)
   * @param string|null $goldType Optional filter by gold type
   * @param string|null $vaultLocation Optional filter by vault location
   * @return array{data: array, total: int, page: int, perPage: int}
   */
  public function getClientInventory(
    int $userId,
    int $page,
    ?string $goldType = null,
    ?string $vaultLocation = null
  ): array {
    $page = max(1, $page);
    $perPage = 25;

    $result = $this->repository->findByUserId($userId, $page, $perPage, $goldType, $vaultLocation);

    return [
      'data' => $result['data'],
      'total' => $result['total'],
      'page' => $page,
      'perPage' => $perPage,
    ];
  }

  /**
   * Create a new inventory item (admin only).
   *
   * Validates input data and checks serial number uniqueness before creating.
   *
   * @param array $data Inventory item data
   * @return Result Success with item ID or validation/error result
   */
  public function createItem(array $data): Result
  {
    // Validate input
    $validation = $this->validator->validate($data);

    if (!$validation->isValid) {
      return Result::validationError($validation->errors);
    }

    // Check serial number uniqueness
    if ($this->repository->checkSerialExists($data['serial_number'])) {
      return Result::error('DUPLICATE_SERIAL', 'Serial number already exists.');
    }

    // Create the item
    $itemId = $this->repository->create($data);

    return Result::success(['item_id' => $itemId]);
  }

  /**
   * Update an existing inventory item (admin only).
   *
   * Validates input data and checks serial number uniqueness (excluding current item).
   *
   * @param int $itemId The item ID to update
   * @param array $data Fields to update
   * @return Result Success or validation/error result
   */
  public function updateItem(int $itemId, array $data): Result
  {
    // Check item exists
    $existingItem = $this->repository->findById($itemId);

    if ($existingItem === null) {
      return Result::error('ITEM_NOT_FOUND', 'Inventory item not found.');
    }

    // Validate input - merge existing data with updates for full validation
    $mergedData = array_merge($existingItem, $data);
    $validation = $this->validator->validate($mergedData);

    if (!$validation->isValid) {
      return Result::validationError($validation->errors);
    }

    // Check serial number uniqueness (excluding current item)
    if (isset($data['serial_number'])) {
      if ($this->repository->checkSerialExists($data['serial_number'], $itemId)) {
        return Result::error('DUPLICATE_SERIAL', 'Serial number already exists.');
      }
    }

    // Update the item
    $updated = $this->repository->update($itemId, $data);

    if (!$updated) {
      return Result::error('UPDATE_FAILED', 'No changes were made to the inventory item.');
    }

    return Result::success(['item_id' => $itemId]);
  }

  /**
   * Soft-delete an inventory item (admin only).
   *
   * Sets is_active = 0 rather than permanently deleting.
   *
   * @param int $itemId The item ID to deactivate
   * @return Result Success or error result
   */
  public function deactivateItem(int $itemId): Result
  {
    // Check item exists
    $existingItem = $this->repository->findById($itemId);

    if ($existingItem === null) {
      return Result::error('ITEM_NOT_FOUND', 'Inventory item not found.');
    }

    $deactivated = $this->repository->deactivate($itemId);

    if (!$deactivated) {
      return Result::error('ALREADY_INACTIVE', 'Inventory item is already inactive.');
    }

    return Result::success(['item_id' => $itemId]);
  }

  /**
   * Validate that all specified items belong to the given user, are active,
   * and are not currently in active shipments (pending_approval or in_transit).
   *
   * Used during shipment request to verify ownership and availability.
   *
   * @param int $userId The user ID to check ownership against
   * @param array<int> $itemIds Array of inventory item IDs
   * @return Result Success or error with invalid item IDs
   */
  public function validateOwnership(int $userId, array $itemIds): Result
  {
    if (empty($itemIds)) {
      return Result::error('NO_ITEMS', 'At least one inventory item must be specified.');
    }

    $items = $this->repository->findByIds($itemIds);

    $invalidItems = [];
    $foundIds = [];

    foreach ($items as $item) {
      $foundIds[] = (int) $item['id'];

      if ((int) $item['user_id'] !== $userId) {
        $invalidItems[] = (int) $item['id'];
      } elseif ((int) $item['is_active'] !== 1) {
        $invalidItems[] = (int) $item['id'];
      }
    }

    // Check for items that don't exist at all
    $missingIds = array_diff($itemIds, $foundIds);
    $invalidItems = array_merge($invalidItems, $missingIds);

    if (!empty($invalidItems)) {
      return Result::error(
        'INVALID_ITEMS',
        'One or more items do not belong to the user or are inactive.',
      );
    }

    // Check if any items are in active shipments (pending_approval or in_transit)
    $itemsInShipments = $this->repository->getItemsInActiveShipments($itemIds);

    if (!empty($itemsInShipments)) {
      return Result::error(
        'ITEMS_IN_ACTIVE_SHIPMENT',
        'One or more items are already in an active shipment.',
      );
    }

    return Result::success(['item_ids' => $itemIds]);
  }

  /**
   * Get inventory items by their IDs.
   *
   * @param array<int> $itemIds Array of item IDs
   * @return array Array of item records
   */
  public function getItemsByIds(array $itemIds): array
  {
    return $this->repository->findByIds($itemIds);
  }

  /**
   * Check which items are currently in active shipments (pending_approval or in_transit).
   *
   * Used to prevent items from being included in multiple active shipments.
   *
   * @param array<int> $itemIds Array of inventory item IDs to check
   * @return array<int> Array of item IDs that are in active shipments
   */
  public function getItemsInActiveShipments(array $itemIds): array
  {
    return $this->repository->getItemsInActiveShipments($itemIds);
  }
}
