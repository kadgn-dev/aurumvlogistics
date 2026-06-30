<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Services;

use GOLS\Repositories\InventoryRepository;
use GOLS\Repositories\ShipmentRepository;
use GOLS\Result;
use GOLS\Services\ShipmentService;
use GOLS\ValidationResult;
use GOLS\Validators\ShipmentValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ShipmentService.
 *
 * Tests cover: createRequest, approve, reject, assignTracking,
 * updateStatus (state machine), getClientShipments, getShipmentByTracking,
 * getStatusHistory, getValidTransitions.
 */
class ShipmentServiceTest extends TestCase
{
    private ShipmentService $service;
    private MockObject $shipmentRepository;
    private MockObject $inventoryRepository;
    private MockObject $shipmentValidator;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepository::class);
        $this->inventoryRepository = $this->createMock(InventoryRepository::class);
        $this->shipmentValidator = $this->createMock(ShipmentValidator::class);

        $this->service = new ShipmentService(
            $this->shipmentRepository,
            $this->inventoryRepository,
            $this->shipmentValidator
        );
    }

    // =========================================================================
    // createRequest tests
    // =========================================================================

    public function testCreateRequestSuccess(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10, 20],
            'insurance_selected' => 1,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->inventoryRepository->method('findByIds')
            ->with([10, 20])
            ->willReturn([
                ['id' => 10, 'user_id' => 1, 'is_active' => 1, 'weight' => '100.000', 'purity' => '0.9999'],
                ['id' => 20, 'user_id' => 1, 'is_active' => 1, 'weight' => '50.000', 'purity' => '0.9500'],
            ]);

        $this->inventoryRepository->method('getItemsInActiveShipments')
            ->with([10, 20])
            ->willReturn([]);

        $this->shipmentRepository->method('create')
            ->willReturn(42);

        $result = $this->service->createRequest($userId, $data);

        $this->assertTrue($result->success);
        $this->assertEquals(42, $result->data['shipment_id']);
        $this->assertEquals('pending_approval', $result->data['status']);
    }

    public function testCreateRequestValidationFailure(): void
    {
        $userId = 1;
        $data = ['street' => '', 'inventory_items' => []];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::failure(['street' => 'Street is required.']));

        $result = $this->service->createRequest($userId, $data);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('street', $result->errors);
    }

    public function testCreateRequestItemsNotFound(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10, 99],
            'insurance_selected' => 0,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        // Only one item found out of two requested
        $this->inventoryRepository->method('findByIds')
            ->willReturn([
                ['id' => 10, 'user_id' => 1, 'is_active' => 1, 'weight' => '100.000', 'purity' => '0.9999'],
            ]);

        $result = $this->service->createRequest($userId, $data);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_ITEMS', $result->errorCode);
    }

    public function testCreateRequestOwnershipFailure(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10],
            'insurance_selected' => 0,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        // Item belongs to user 2, not user 1
        $this->inventoryRepository->method('findByIds')
            ->willReturn([
                ['id' => 10, 'user_id' => 2, 'is_active' => 1, 'weight' => '100.000', 'purity' => '0.9999'],
            ]);

        $result = $this->service->createRequest($userId, $data);

        $this->assertFalse($result->success);
        $this->assertEquals('OWNERSHIP_ERROR', $result->errorCode);
    }

    public function testCreateRequestItemsInActiveShipment(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10, 20],
            'insurance_selected' => 0,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->inventoryRepository->method('findByIds')
            ->willReturn([
                ['id' => 10, 'user_id' => 1, 'is_active' => 1, 'weight' => '100.000', 'purity' => '0.9999'],
                ['id' => 20, 'user_id' => 1, 'is_active' => 1, 'weight' => '50.000', 'purity' => '0.9500'],
            ]);

        // Item 20 is in an active shipment
        $this->inventoryRepository->method('getItemsInActiveShipments')
            ->willReturn([20]);

        $result = $this->service->createRequest($userId, $data);

        $this->assertFalse($result->success);
        $this->assertEquals('ITEMS_IN_ACTIVE_SHIPMENT', $result->errorCode);
    }

    public function testCreateRequestInactiveItem(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10],
            'insurance_selected' => 0,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->inventoryRepository->method('findByIds')
            ->willReturn([
                ['id' => 10, 'user_id' => 1, 'is_active' => 0, 'weight' => '100.000', 'purity' => '0.9999'],
            ]);

        $result = $this->service->createRequest($userId, $data);

        $this->assertFalse($result->success);
        $this->assertEquals('INACTIVE_ITEM', $result->errorCode);
    }

    public function testCreateRequestInsuredValueCalculation(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10, 20],
            'insurance_selected' => 1,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->inventoryRepository->method('findByIds')
            ->willReturn([
                ['id' => 10, 'user_id' => 1, 'is_active' => 1, 'weight' => '100.000', 'purity' => '0.9999'],
                ['id' => 20, 'user_id' => 1, 'is_active' => 1, 'weight' => '50.000', 'purity' => '0.9500'],
            ]);

        $this->inventoryRepository->method('getItemsInActiveShipments')
            ->willReturn([]);

        $this->shipmentRepository->method('create')
            ->willReturn(42);

        $result = $this->service->createRequest($userId, $data);

        $this->assertTrue($result->success);
        // 100 * 0.9999 + 50 * 0.95 = 99.99 + 47.5 = 147.49
        $this->assertEquals(147.49, $result->data['insured_value']);
    }

    public function testCreateRequestNoInsuranceZeroValue(): void
    {
        $userId = 1;
        $data = [
            'street' => '123 Gold St',
            'city' => 'Vault City',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'inventory_items' => [10],
            'insurance_selected' => 0,
        ];

        $this->shipmentValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->inventoryRepository->method('findByIds')
            ->willReturn([
                ['id' => 10, 'user_id' => 1, 'is_active' => 1, 'weight' => '100.000', 'purity' => '0.9999'],
            ]);

        $this->inventoryRepository->method('getItemsInActiveShipments')
            ->willReturn([]);

        $this->shipmentRepository->method('create')
            ->willReturn(42);

        $result = $this->service->createRequest($userId, $data);

        $this->assertTrue($result->success);
        $this->assertEquals(0.00, $result->data['insured_value']);
    }

    // =========================================================================
    // approve tests
    // =========================================================================

    public function testApproveSuccess(): void
    {
        $this->shipmentRepository->method('findById')
            ->with(1)
            ->willReturn(['id' => 1, 'status' => 'pending_approval', 'user_id' => 5]);

        $this->shipmentRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'approved', 10);

        $result = $this->service->approve(1, 10);

        $this->assertTrue($result->success);
        $this->assertEquals('approved', $result->data['status']);
    }

    public function testApproveNotFound(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(null);

        $result = $this->service->approve(999, 10);

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    public function testApproveInvalidStatus(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'approved', 'user_id' => 5]);

        $result = $this->service->approve(1, 10);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_STATUS', $result->errorCode);
    }

    // =========================================================================
    // reject tests
    // =========================================================================

    public function testRejectSuccess(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'pending_approval', 'user_id' => 5]);

        $this->shipmentRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'rejected', 10);

        $this->shipmentRepository->expects($this->once())
            ->method('setRejectionReason')
            ->with(1, 'Insufficient documentation');

        $result = $this->service->reject(1, 10, 'Insufficient documentation');

        $this->assertTrue($result->success);
        $this->assertEquals('rejected', $result->data['status']);
        $this->assertEquals('Insufficient documentation', $result->data['reason']);
    }

    public function testRejectReasonTooShort(): void
    {
        $result = $this->service->reject(1, 10, '');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_REASON', $result->errorCode);
    }

    public function testRejectReasonTooLong(): void
    {
        $reason = str_repeat('a', 501);
        $result = $this->service->reject(1, 10, $reason);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_REASON', $result->errorCode);
    }

    public function testRejectReasonExactly500Chars(): void
    {
        $reason = str_repeat('a', 500);

        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'pending_approval', 'user_id' => 5]);

        $this->shipmentRepository->method('updateStatus')->willReturn(true);
        $this->shipmentRepository->method('setRejectionReason')->willReturn(true);

        $result = $this->service->reject(1, 10, $reason);

        $this->assertTrue($result->success);
    }

    public function testRejectNotFound(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(null);

        $result = $this->service->reject(999, 10, 'Some reason');

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    public function testRejectInvalidStatus(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'approved', 'user_id' => 5]);

        $result = $this->service->reject(1, 10, 'Some reason');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_STATUS', $result->errorCode);
    }

    // =========================================================================
    // assignTracking tests
    // =========================================================================

    public function testAssignTrackingSuccess(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'approved', 'user_id' => 5]);

        $this->shipmentRepository->expects($this->once())
            ->method('assignTracking')
            ->with(1, 'ABC123DEF456', 'dhl');

        $this->shipmentRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'ready_for_shipment', 10);

        $result = $this->service->assignTracking(1, 10, 'ABC123DEF456', 'dhl');

        $this->assertTrue($result->success);
        $this->assertEquals('ABC123DEF456', $result->data['tracking_number']);
        $this->assertEquals('dhl', $result->data['carrier']);
        $this->assertEquals('ready_for_shipment', $result->data['status']);
    }

    public function testAssignTrackingInvalidTrackingTooShort(): void
    {
        $result = $this->service->assignTracking(1, 10, 'AB12', 'dhl');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_TRACKING', $result->errorCode);
    }

    public function testAssignTrackingInvalidTrackingTooLong(): void
    {
        $tracking = str_repeat('A', 51);
        $result = $this->service->assignTracking(1, 10, $tracking, 'dhl');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_TRACKING', $result->errorCode);
    }

    public function testAssignTrackingInvalidTrackingSpecialChars(): void
    {
        $result = $this->service->assignTracking(1, 10, 'ABC-123-DEF', 'dhl');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_TRACKING', $result->errorCode);
    }

    public function testAssignTrackingInvalidCarrier(): void
    {
        $result = $this->service->assignTracking(1, 10, 'ABC123DEF456', 'ups');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_CARRIER', $result->errorCode);
    }

    public function testAssignTrackingAllValidCarriers(): void
    {
        foreach (['dhl', 'fedex', 'brinks'] as $carrier) {
            $this->setUp(); // Reset mocks

            $this->shipmentRepository->method('findById')
                ->willReturn(['id' => 1, 'status' => 'approved', 'user_id' => 5]);

            $this->shipmentRepository->method('assignTracking')->willReturn(true);
            $this->shipmentRepository->method('updateStatus')->willReturn(true);

            $result = $this->service->assignTracking(1, 10, 'ABC123DEF456', $carrier);
            $this->assertTrue($result->success, "Carrier '{$carrier}' should be valid");
        }
    }

    public function testAssignTrackingNotApprovedStatus(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'pending_approval', 'user_id' => 5]);

        $result = $this->service->assignTracking(1, 10, 'ABC123DEF456', 'dhl');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_STATUS', $result->errorCode);
    }

    public function testAssignTrackingNotFound(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(null);

        $result = $this->service->assignTracking(999, 10, 'ABC123DEF456', 'dhl');

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    // =========================================================================
    // updateStatus tests (state machine)
    // =========================================================================

    public function testUpdateStatusValidTransition(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn([
                'id' => 1,
                'status' => 'ready_for_shipment',
                'user_id' => 5,
                'tracking_number' => 'TRACK123456',
                'carrier' => 'dhl',
            ]);

        $this->shipmentRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'in_transit', 10);

        $result = $this->service->updateStatus(1, 10, 'in_transit');

        $this->assertTrue($result->success);
        $this->assertEquals('in_transit', $result->data['status']);
        $this->assertEquals('ready_for_shipment', $result->data['previous_status']);
    }

    public function testUpdateStatusInvalidTransition(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'pending_approval', 'user_id' => 5]);

        // Cannot go directly from pending_approval to in_transit
        $result = $this->service->updateStatus(1, 10, 'in_transit');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_TRANSITION', $result->errorCode);
    }

    public function testUpdateStatusInTransitRequiresTracking(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn([
                'id' => 1,
                'status' => 'ready_for_shipment',
                'user_id' => 5,
                'tracking_number' => null,
                'carrier' => null,
            ]);

        $result = $this->service->updateStatus(1, 10, 'in_transit');

        $this->assertFalse($result->success);
        $this->assertEquals('TRACKING_REQUIRED', $result->errorCode);
    }

    public function testUpdateStatusTerminalStateCannotTransition(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'delivered', 'user_id' => 5]);

        $result = $this->service->updateStatus(1, 10, 'cancelled');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_TRANSITION', $result->errorCode);
    }

    public function testUpdateStatusCancelFromPendingApproval(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'pending_approval', 'user_id' => 5]);

        $this->shipmentRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'cancelled', 10);

        $result = $this->service->updateStatus(1, 10, 'cancelled');

        $this->assertTrue($result->success);
        $this->assertEquals('cancelled', $result->data['status']);
    }

    public function testUpdateStatusCancelFromApproved(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(['id' => 1, 'status' => 'approved', 'user_id' => 5]);

        $result = $this->service->updateStatus(1, 10, 'cancelled');

        $this->assertTrue($result->success);
        $this->assertEquals('cancelled', $result->data['status']);
    }

    public function testUpdateStatusCancelFromInTransit(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn([
                'id' => 1,
                'status' => 'in_transit',
                'user_id' => 5,
                'tracking_number' => 'TRACK123',
                'carrier' => 'dhl',
            ]);

        $result = $this->service->updateStatus(1, 10, 'cancelled');

        $this->assertTrue($result->success);
        $this->assertEquals('cancelled', $result->data['status']);
    }

    public function testUpdateStatusNotFound(): void
    {
        $this->shipmentRepository->method('findById')
            ->willReturn(null);

        $result = $this->service->updateStatus(999, 10, 'approved');

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    // =========================================================================
    // getClientShipments tests
    // =========================================================================

    public function testGetClientShipments(): void
    {
        $expected = ['data' => [['id' => 1]], 'total' => 1];

        $this->shipmentRepository->method('findByUserId')
            ->with(5, 1)
            ->willReturn($expected);

        $result = $this->service->getClientShipments(5, 1);

        $this->assertEquals($expected, $result);
    }

    public function testGetClientShipmentsPageMinimumIsOne(): void
    {
        $this->shipmentRepository->expects($this->once())
            ->method('findByUserId')
            ->with(5, 1);

        $this->service->getClientShipments(5, 0);
    }

    // =========================================================================
    // getShipmentByTracking tests
    // =========================================================================

    public function testGetShipmentByTrackingFound(): void
    {
        $shipment = ['id' => 1, 'tracking_number' => 'TRACK123', 'user_id' => 5];

        $this->shipmentRepository->method('findByTracking')
            ->with('TRACK123', 5)
            ->willReturn($shipment);

        $result = $this->service->getShipmentByTracking(5, 'TRACK123');

        $this->assertEquals($shipment, $result);
    }

    public function testGetShipmentByTrackingNotFound(): void
    {
        $this->shipmentRepository->method('findByTracking')
            ->willReturn(null);

        $result = $this->service->getShipmentByTracking(5, 'NONEXISTENT');

        $this->assertNull($result);
    }

    public function testGetShipmentByTrackingEmptyString(): void
    {
        $result = $this->service->getShipmentByTracking(5, '');

        $this->assertNull($result);
    }

    // =========================================================================
    // getStatusHistory tests
    // =========================================================================

    public function testGetStatusHistory(): void
    {
        $history = [
            ['status' => 'pending_approval', 'changed_at' => '2024-01-01 10:00:00'],
            ['status' => 'approved', 'changed_at' => '2024-01-02 10:00:00'],
        ];

        $this->shipmentRepository->method('getStatusHistory')
            ->with(1)
            ->willReturn($history);

        $result = $this->service->getStatusHistory(1);

        $this->assertEquals($history, $result);
    }

    // =========================================================================
    // getValidTransitions tests
    // =========================================================================

    public function testGetValidTransitionsPendingApproval(): void
    {
        $transitions = $this->service->getValidTransitions('pending_approval');
        $this->assertEquals(['approved', 'rejected', 'cancelled'], $transitions);
    }

    public function testGetValidTransitionsApproved(): void
    {
        $transitions = $this->service->getValidTransitions('approved');
        $this->assertEquals(['ready_for_shipment', 'cancelled'], $transitions);
    }

    public function testGetValidTransitionsReadyForShipment(): void
    {
        $transitions = $this->service->getValidTransitions('ready_for_shipment');
        $this->assertEquals(['in_transit', 'cancelled'], $transitions);
    }

    public function testGetValidTransitionsInTransit(): void
    {
        $transitions = $this->service->getValidTransitions('in_transit');
        $this->assertEquals(['delivered', 'cancelled'], $transitions);
    }

    public function testGetValidTransitionsDelivered(): void
    {
        $transitions = $this->service->getValidTransitions('delivered');
        $this->assertEquals([], $transitions);
    }

    public function testGetValidTransitionsRejected(): void
    {
        $transitions = $this->service->getValidTransitions('rejected');
        $this->assertEquals([], $transitions);
    }

    public function testGetValidTransitionsCancelled(): void
    {
        $transitions = $this->service->getValidTransitions('cancelled');
        $this->assertEquals([], $transitions);
    }

    public function testGetValidTransitionsUnknownStatus(): void
    {
        $transitions = $this->service->getValidTransitions('unknown_status');
        $this->assertEquals([], $transitions);
    }
}
