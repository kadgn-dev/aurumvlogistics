<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\ShipmentRepository;
use GOLS\Repositories\InventoryRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Result;
use GOLS\Validators\ShipmentValidator;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ShipmentService - Business logic for shipment lifecycle management.
 *
 * Handles shipment creation, approval workflow, tracking assignment,
 * status transitions (state machine enforcement), and client queries.
 *
 * Requirements: 6.1, 6.2, 6.3, 7.1, 7.2, 7.3, 7.4, 7.6, 8.1, 8.2
 */
class ShipmentService
{
  private ShipmentRepository $shipmentRepository;
  private InventoryRepository $inventoryRepository;
  private ShipmentValidator $shipmentValidator;
  private ?NotificationService $notificationService;
  private ?UserRepository $userRepository;
  private ?EmailService $emailService;
  private ?ManifestService $manifestService;

  /**
   * Valid status transitions (state machine).
   *
   * pending_approval → approved, rejected, cancelled
   * approved → ready_for_shipment, cancelled
   * ready_for_shipment → in_transit, cancelled
   * in_transit → delivered, cancelled
   *
   * @var array<string, array<string>>
   */
  private const STATUS_TRANSITIONS = [
    'pending_approval' => ['approved', 'rejected', 'cancelled'],
    'approved' => ['ready_for_shipment', 'cancelled'],
    'ready_for_shipment' => ['in_transit', 'cancelled'],
    'in_transit' => ['delivered', 'cancelled'],
    'delivered' => [],
    'rejected' => [],
    'cancelled' => [],
  ];

  /**
   * Valid carrier values.
   */
  private const VALID_CARRIERS = ['dhl', 'fedex', 'brinks'];

  public function __construct(
    ShipmentRepository $shipmentRepository,
    InventoryRepository $inventoryRepository,
    ShipmentValidator $shipmentValidator,
    ?NotificationService $notificationService = null,
    ?UserRepository $userRepository = null,
    ?EmailService $emailService = null,
    ?ManifestService $manifestService = null
  ) {
    $this->shipmentRepository = $shipmentRepository;
    $this->inventoryRepository = $inventoryRepository;
    $this->shipmentValidator = $shipmentValidator;
    $this->notificationService = $notificationService;
    $this->userRepository = $userRepository;
    $this->emailService = $emailService;
    $this->manifestService = $manifestService;
  }

  /**
   * Create a new shipment request for a client.
   *
   * Validates form data, verifies ownership of all items, checks no items
   * are in active shipments, calculates insured value, and creates the
   * shipment with status pending_approval.
   *
   * @param int $userId The client user ID
   * @param array $data Shipment request data (address fields, inventory_items, insurance_selected)
   * @return Result
   */
  public function createRequest(int $userId, array $data): Result
  {
    // Validate structural/format data
    $validation = $this->shipmentValidator->validate($data);
    if (!$validation->isValid) {
      return Result::validationError($validation->errors);
    }

    $itemIds = array_map('intval', $data['inventory_items']);

    // Validate ownership of all items
    $items = $this->inventoryRepository->findByIds($itemIds);

    if (count($items) !== count($itemIds)) {
      return Result::error('INVALID_ITEMS', 'One or more selected inventory items do not exist.');
    }

    foreach ($items as $item) {
      if ((int) $item['user_id'] !== $userId) {
        return Result::error('OWNERSHIP_ERROR', 'You do not own all selected inventory items.');
      }
      if (isset($item['is_active']) && !(int) $item['is_active']) {
        return Result::error('INACTIVE_ITEM', 'One or more selected items are no longer active.');
      }
    }

    // Check no items are in active shipments (pending_approval or in_transit)
    $conflictingItems = $this->inventoryRepository->getItemsInActiveShipments($itemIds);
    if (!empty($conflictingItems)) {
      return Result::error(
        'ITEMS_IN_ACTIVE_SHIPMENT',
        'One or more selected items are already in a pending or in-transit shipment.'
      );
    }

    // Calculate insured value based on selected items
    $insuredValue = $this->calculateInsuredValue($items, (bool) $data['insurance_selected']);

    // Create shipment record
    $shipmentData = [
      'user_id' => $userId,
      'street' => trim($data['street']),
      'city' => trim($data['city']),
      'state_province' => trim($data['state_province']),
      'postal_code' => trim($data['postal_code']),
      'country' => trim($data['country']),
      'insurance_selected' => (bool) $data['insurance_selected'],
      'insured_value' => $insuredValue,
    ];

    $shipmentId = $this->shipmentRepository->create($shipmentData, $itemIds);

    // Notify admins of new shipment request (Requirement 6.4)
    $this->notifyAdmins(
      'shipment_request_created',
      sprintf('New shipment request #%d submitted for approval.', $shipmentId),
      $shipmentId
    );

    return Result::success([
      'shipment_id' => $shipmentId,
      'status' => 'pending_approval',
      'insured_value' => $insuredValue,
    ]);
  }

  /**
   * Approve a shipment request (admin action).
   *
   * Validates current status is pending_approval, updates to approved.
   *
   * @param int $shipmentId The shipment ID
   * @param int $adminId The admin user ID performing the action
   * @return Result
   */
  public function approve(int $shipmentId, int $adminId): Result
  {
    $shipment = $this->shipmentRepository->findById($shipmentId);

    if ($shipment === null) {
      return Result::error('NOT_FOUND', 'Shipment not found.');
    }

    if ($shipment['status'] !== 'pending_approval') {
      return Result::error(
        'INVALID_STATUS',
        'Shipment can only be approved when status is pending_approval. Current status: ' . $shipment['status']
      );
    }

    $this->shipmentRepository->updateStatus($shipmentId, 'approved', $adminId);

    // Notify the client of approval (Requirement 7.1)
    $this->notifyClient(
      (int) $shipment['user_id'],
      'shipment_approved',
      sprintf('Your shipment request #%d has been approved.', $shipmentId),
      $shipmentId
    );

    // Generate shipment manifest PDF (non-blocking side effect)
    if ($this->manifestService !== null) {
      try {
        $approvalDate = date('Y-m-d H:i:s');
        $this->manifestService->generateManifest($shipmentId, $approvalDate);
      } catch (\Throwable $e) {
        error_log('Manifest generation failed for shipment #' . $shipmentId . ': ' . $e->getMessage());
      }
    }

    return Result::success([
      'shipment_id' => $shipmentId,
      'status' => 'approved',
    ]);
  }

  /**
   * Reject a shipment request (admin action).
   *
   * Requires a rejection reason between 1 and 500 characters.
   *
   * @param int $shipmentId The shipment ID
   * @param int $adminId The admin user ID performing the action
   * @param string $reason Rejection reason (1-500 chars)
   * @return Result
   */
  public function reject(int $shipmentId, int $adminId, string $reason): Result
  {
    // Validate reason length
    $reason = trim($reason);
    if (strlen($reason) < 1 || strlen($reason) > 500) {
      return Result::error(
        'INVALID_REASON',
        'Rejection reason must be between 1 and 500 characters.'
      );
    }

    $shipment = $this->shipmentRepository->findById($shipmentId);

    if ($shipment === null) {
      return Result::error('NOT_FOUND', 'Shipment not found.');
    }

    if ($shipment['status'] !== 'pending_approval') {
      return Result::error(
        'INVALID_STATUS',
        'Shipment can only be rejected when status is pending_approval. Current status: ' . $shipment['status']
      );
    }

    $this->shipmentRepository->updateStatus($shipmentId, 'rejected', $adminId);
    $this->shipmentRepository->setRejectionReason($shipmentId, $reason);

    // Notify the client of rejection with reason (Requirement 7.4)
    $this->notifyClient(
      (int) $shipment['user_id'],
      'shipment_rejected',
      sprintf('Your shipment request #%d has been rejected. Reason: %s', $shipmentId, $reason),
      $shipmentId
    );

    return Result::success([
      'shipment_id' => $shipmentId,
      'status' => 'rejected',
      'reason' => $reason,
    ]);
  }

  /**
   * Assign tracking number and carrier to a shipment (admin action).
   *
   * Validates tracking number (6-50 alphanumeric chars) and carrier.
   * Updates shipment status to ready_for_shipment.
   *
   * @param int $shipmentId The shipment ID
   * @param int $adminId The admin user ID performing the action
   * @param string $trackingNumber Tracking number (6-50 alphanumeric)
   * @param string $carrier Carrier name (dhl, fedex, brinks)
   * @return Result
   */
  public function assignTracking(int $shipmentId, int $adminId, string $trackingNumber, string $carrier): Result
  {
    // Validate tracking number: 6-50 alphanumeric characters
    $trackingNumber = trim($trackingNumber);
    if (!preg_match('/^[a-zA-Z0-9]{6,50}$/', $trackingNumber)) {
      return Result::error(
        'INVALID_TRACKING',
        'Tracking number must be between 6 and 50 alphanumeric characters.'
      );
    }

    // Validate carrier
    $carrier = strtolower(trim($carrier));
    if (!in_array($carrier, self::VALID_CARRIERS, true)) {
      return Result::error(
        'INVALID_CARRIER',
        'Carrier must be one of: ' . implode(', ', self::VALID_CARRIERS) . '.'
      );
    }

    $shipment = $this->shipmentRepository->findById($shipmentId);

    if ($shipment === null) {
      return Result::error('NOT_FOUND', 'Shipment not found.');
    }

    if ($shipment['status'] !== 'approved') {
      return Result::error(
        'INVALID_STATUS',
        'Tracking can only be assigned when shipment status is approved. Current status: ' . $shipment['status']
      );
    }

    // Assign tracking and update status to ready_for_shipment
    $this->shipmentRepository->assignTracking($shipmentId, $trackingNumber, $carrier);
    $this->shipmentRepository->updateStatus($shipmentId, 'ready_for_shipment', $adminId);

    // Notify the client of tracking assignment (Requirement 7.2)
    $this->notifyClient(
      (int) $shipment['user_id'],
      'shipment_tracking_assigned',
      sprintf(
        'Tracking assigned to shipment #%d. Tracking: %s, Carrier: %s.',
        $shipmentId,
        $trackingNumber,
        strtoupper($carrier)
      ),
      $shipmentId
    );

    return Result::success([
      'shipment_id' => $shipmentId,
      'tracking_number' => $trackingNumber,
      'carrier' => $carrier,
      'status' => 'ready_for_shipment',
    ]);
  }

  /**
   * Update shipment status (admin action).
   *
   * Enforces state machine transitions. Requires tracking for in_transit.
   *
   * @param int $shipmentId The shipment ID
   * @param int $adminId The admin user ID performing the action
   * @param string $newStatus The target status
   * @return Result
   */
  public function updateStatus(int $shipmentId, int $adminId, string $newStatus): Result
  {
    $shipment = $this->shipmentRepository->findById($shipmentId);

    if ($shipment === null) {
      return Result::error('NOT_FOUND', 'Shipment not found.');
    }

    $currentStatus = $shipment['status'];

    // Validate the transition is allowed
    $validTransitions = $this->getValidTransitions($currentStatus);
    if (!in_array($newStatus, $validTransitions, true)) {
      return Result::error(
        'INVALID_TRANSITION',
        sprintf(
          'Cannot transition from "%s" to "%s". Valid transitions: %s.',
          $currentStatus,
          $newStatus,
          empty($validTransitions) ? 'none (terminal state)' : implode(', ', $validTransitions)
        )
      );
    }

    // Require tracking number and carrier for in_transit transition
    if ($newStatus === 'in_transit') {
      if (empty($shipment['tracking_number']) || empty($shipment['carrier'])) {
        return Result::error(
          'TRACKING_REQUIRED',
          'Tracking number and carrier must be assigned before setting status to in_transit.'
        );
      }
    }

    $this->shipmentRepository->updateStatus($shipmentId, $newStatus, $adminId);

    // Notify the client of status change (Requirement 7.3)
    $this->notifyClient(
      (int) $shipment['user_id'],
      'shipment_status_update',
      sprintf(
        'Shipment #%d status updated from "%s" to "%s".',
        $shipmentId,
        $currentStatus,
        $newStatus
      ),
      $shipmentId
    );

    return Result::success([
      'shipment_id' => $shipmentId,
      'previous_status' => $currentStatus,
      'status' => $newStatus,
    ]);
  }

  /**
   * Get paginated shipments for a client.
   *
   * @param int $userId The client user ID
   * @param int $page Page number (1-based)
   * @return array Paginated result with 'data' and 'total' keys
   */
  public function getClientShipments(int $userId, int $page): array
  {
    return $this->shipmentRepository->findByUserId($userId, max(1, $page));
  }

  /**
   * Find a shipment by tracking number, scoped to a specific user.
   *
   * @param int $userId The client user ID
   * @param string $trackingNumber The tracking number to search
   * @return array|null Shipment record or null if not found
   */
  public function getShipmentByTracking(int $userId, string $trackingNumber): ?array
  {
    $trackingNumber = trim($trackingNumber);
    if ($trackingNumber === '') {
      return null;
    }

    return $this->shipmentRepository->findByTracking($trackingNumber, $userId);
  }

  /**
   * Get the full status history for a shipment.
   *
   * @param int $shipmentId The shipment ID
   * @return array Array of status history records
   */
  public function getStatusHistory(int $shipmentId): array
  {
    return $this->shipmentRepository->getStatusHistory($shipmentId);
  }

  /**
   * Get valid next statuses for a given current status.
   *
   * @param string $currentStatus The current shipment status
   * @return array Array of valid next status strings
   */
  public function getValidTransitions(string $currentStatus): array
  {
    return self::STATUS_TRANSITIONS[$currentStatus] ?? [];
  }

  /**
   * Send a notification to a client user.
   *
   * Silently handles notification failures to avoid disrupting the main workflow.
   * Also sends an email notification for shipment updates if the user has not
   * unsubscribed from the 'shipment_updates' category.
   *
   * @param int $userId The client user ID to notify
   * @param string $type The notification event type
   * @param string $message The notification message
   * @param int|null $referenceId Optional shipment ID reference
   */
  private function notifyClient(int $userId, string $type, string $message, ?int $referenceId = null): void
  {
    if ($this->notificationService !== null) {
      $this->notificationService->create(
        $userId,
        $type,
        $message,
        $referenceId,
        'shipment'
      );
    }

    // Send email notification for shipment updates (Requirement 19.1)
    $this->sendShipmentEmail($userId, $referenceId);
  }

  /**
   * Send a shipment update email to the client.
   *
   * Respects email unsubscribe preferences for the 'shipment_updates' category.
   * Uses the 'shipment_update.html' template with shipment details.
   *
   * @param int $userId The client user ID
   * @param int|null $shipmentId The shipment ID for details
   */
  private function sendShipmentEmail(int $userId, ?int $shipmentId): void
  {
    if ($this->emailService === null || $this->userRepository === null) {
      return;
    }

    // Respect unsubscribe preferences for non-critical shipment_updates category
    if ($this->emailService->isUnsubscribed($userId, 'shipment_updates')) {
      return;
    }

    $user = $this->userRepository->findById($userId);
    if ($user === null) {
      return;
    }

    // Get shipment details for the email template
    $shipment = $shipmentId !== null ? $this->shipmentRepository->findById($shipmentId) : null;

    $templateData = [
      'name' => $user['name'],
      'shipment_id' => $shipmentId !== null ? '#' . $shipmentId : 'N/A',
      'status' => $shipment !== null ? ucwords(str_replace('_', ' ', $shipment['status'])) : 'Updated',
      'tracking_number' => $shipment['tracking_number'] ?? 'Not yet assigned',
      'carrier' => !empty($shipment['carrier']) ? strtoupper($shipment['carrier']) : 'Not yet assigned',
      'unsubscribe_url' => (defined('APP_URL') ? APP_URL : 'https://www.aurumvlogistics.com') . '/client/profile.php?unsubscribe=shipment_updates',
    ];

    $this->emailService->sendWithRetry(
      $user['email'],
      'Shipment Status Update - Aurum Vault Logistics',
      'shipment_update.html',
      $templateData
    );
  }

  /**
   * Send a notification to all admin users.
   *
   * Used to alert admins of new shipment requests requiring approval (Requirement 6.4).
   * Silently handles failures to avoid disrupting the main workflow.
   *
   * @param string $type The notification event type
   * @param string $message The notification message
   * @param int|null $referenceId Optional shipment ID reference
   */
  private function notifyAdmins(string $type, string $message, ?int $referenceId = null): void
  {
    if ($this->notificationService === null || $this->userRepository === null) {
      return;
    }

    $admins = $this->userRepository->findByRole('admin');

    foreach ($admins as $admin) {
      $this->notificationService->create(
        (int) $admin['id'],
        $type,
        $message,
        $referenceId,
        'shipment'
      );
    }
  }

  /**
   * Calculate insured value based on selected inventory items.
   *
   * Uses weight * purity as a base value factor for each item.
   * Only calculates if insurance is selected.
   *
   * @param array $items Array of inventory item records
   * @param bool $insuranceSelected Whether insurance was selected
   * @return float The calculated insured value
   */
  private function calculateInsuredValue(array $items, bool $insuranceSelected): float
  {
    if (!$insuranceSelected) {
      return 0.00;
    }

    $totalValue = 0.00;
    foreach ($items as $item) {
      // Base insured value calculation: weight * purity factor
      $weight = (float) ($item['weight'] ?? 0);
      $purity = (float) ($item['purity'] ?? 0);
      $totalValue += $weight * $purity;
    }

    return round($totalValue, 2);
  }
}
