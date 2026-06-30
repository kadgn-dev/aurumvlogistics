<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Repositories;

use GOLS\Repositories\ShipmentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ShipmentRepository.
 *
 * Uses an in-memory SQLite database to test repository logic
 * without requiring a MySQL connection.
 */
class ShipmentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ShipmentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();
        $this->seedTestData();

        $this->repository = new ShipmentRepository($this->pdo);
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(15) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role TEXT NOT NULL DEFAULT "client",
                status TEXT NOT NULL DEFAULT "pending",
                kyc_status TEXT NOT NULL DEFAULT "not_submitted",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE vault_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                gold_type TEXT NOT NULL,
                weight DECIMAL(9,3) NOT NULL,
                purity DECIMAL(5,4) NOT NULL,
                serial_number VARCHAR(50) NOT NULL UNIQUE,
                vault_location VARCHAR(255) NOT NULL,
                insurance_status INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE shipments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                street VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100) NOT NULL,
                postal_code VARCHAR(20) NOT NULL,
                country VARCHAR(100) NOT NULL,
                insurance_selected INTEGER NOT NULL DEFAULT 0,
                insured_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                status TEXT NOT NULL DEFAULT "pending_approval",
                tracking_number VARCHAR(50) DEFAULT NULL,
                carrier TEXT DEFAULT NULL,
                rejection_reason TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE shipment_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shipment_id INTEGER NOT NULL,
                inventory_id INTEGER NOT NULL,
                FOREIGN KEY (shipment_id) REFERENCES shipments(id),
                FOREIGN KEY (inventory_id) REFERENCES vault_inventory(id),
                UNIQUE (shipment_id, inventory_id)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE shipment_status_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shipment_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                changed_by INTEGER NOT NULL,
                FOREIGN KEY (shipment_id) REFERENCES shipments(id),
                FOREIGN KEY (changed_by) REFERENCES users(id)
            )
        ');
    }

    private function seedTestData(): void
    {
        // Create test users
        $this->pdo->exec("
            INSERT INTO users (id, name, email, phone, password_hash, role, status)
            VALUES (1, 'Client One', 'client1@test.com', '1234567890', 'hash1', 'client', 'active')
        ");
        $this->pdo->exec("
            INSERT INTO users (id, name, email, phone, password_hash, role, status)
            VALUES (2, 'Client Two', 'client2@test.com', '0987654321', 'hash2', 'client', 'active')
        ");
        $this->pdo->exec("
            INSERT INTO users (id, name, email, phone, password_hash, role, status)
            VALUES (3, 'Admin User', 'admin@test.com', '5555555555', 'hash3', 'admin', 'active')
        ");

        // Create test inventory items
        $this->pdo->exec("
            INSERT INTO vault_inventory (id, user_id, gold_type, weight, purity, serial_number, vault_location)
            VALUES (1, 1, 'bar', 100.000, 0.9999, 'SN001', 'Vault A')
        ");
        $this->pdo->exec("
            INSERT INTO vault_inventory (id, user_id, gold_type, weight, purity, serial_number, vault_location)
            VALUES (2, 1, 'coin', 31.103, 0.9167, 'SN002', 'Vault A')
        ");
        $this->pdo->exec("
            INSERT INTO vault_inventory (id, user_id, gold_type, weight, purity, serial_number, vault_location)
            VALUES (3, 2, 'bar', 500.000, 0.9999, 'SN003', 'Vault B')
        ");
    }

    public function testFindByIdReturnsShipment(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1, 2]
        );

        $result = $this->repository->findById($shipmentId);

        $this->assertNotNull($result);
        $this->assertEquals($shipmentId, $result['id']);
        $this->assertEquals(1, $result['user_id']);
        $this->assertEquals('123 Gold St', $result['street']);
        $this->assertEquals('pending_approval', $result['status']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function testFindByUserIdReturnsPaginatedResults(): void
    {
        // Create 3 shipments for user 1
        $this->repository->create($this->makeShipmentData(1), [1]);
        $this->repository->create($this->makeShipmentData(1), [2]);
        $this->repository->create($this->makeShipmentData(1), [1]);

        $result = $this->repository->findByUserId(1, 1, 2);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(3, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testFindByUserIdReturnsEmptyForNoShipments(): void
    {
        $result = $this->repository->findByUserId(2, 1, 20);

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['data']);
    }

    public function testFindByUserIdDoesNotReturnOtherUsersShipments(): void
    {
        $this->repository->create($this->makeShipmentData(1), [1]);
        $this->repository->create($this->makeShipmentData(2), [3]);

        $result = $this->repository->findByUserId(1, 1, 20);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['data'][0]['user_id']);
    }

    public function testCreateReturnsShipmentId(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1, 2]
        );

        $this->assertIsInt($shipmentId);
        $this->assertGreaterThan(0, $shipmentId);
    }

    public function testCreateInsertsShipmentItems(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1, 2]
        );

        $items = $this->repository->getShipmentItems($shipmentId);

        $this->assertCount(2, $items);
    }

    public function testCreateRecordsInitialStatusHistory(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );

        $history = $this->repository->getStatusHistory($shipmentId);

        $this->assertCount(1, $history);
        $this->assertEquals('pending_approval', $history[0]['status']);
        $this->assertEquals(1, $history[0]['changed_by']);
    }

    public function testUpdateStatusChangesShipmentStatus(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );

        $result = $this->repository->updateStatus($shipmentId, 'approved', 3);

        $this->assertTrue($result);

        $shipment = $this->repository->findById($shipmentId);
        $this->assertEquals('approved', $shipment['status']);
    }

    public function testUpdateStatusRecordsHistory(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );

        $this->repository->updateStatus($shipmentId, 'approved', 3);

        $history = $this->repository->getStatusHistory($shipmentId);

        $this->assertCount(2, $history);
        $this->assertEquals('pending_approval', $history[0]['status']);
        $this->assertEquals('approved', $history[1]['status']);
        $this->assertEquals(3, $history[1]['changed_by']);
    }

    public function testAssignTrackingUpdatesShipment(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );

        $result = $this->repository->assignTracking($shipmentId, 'DHL123456', 'dhl');

        $this->assertTrue($result);

        $shipment = $this->repository->findById($shipmentId);
        $this->assertEquals('DHL123456', $shipment['tracking_number']);
        $this->assertEquals('dhl', $shipment['carrier']);
    }

    public function testSetRejectionReasonUpdatesShipment(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );

        $result = $this->repository->setRejectionReason($shipmentId, 'Insufficient documentation');

        $this->assertTrue($result);

        $shipment = $this->repository->findById($shipmentId);
        $this->assertEquals('Insufficient documentation', $shipment['rejection_reason']);
    }

    public function testGetStatusHistoryReturnsOrderedRecords(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );

        $this->repository->updateStatus($shipmentId, 'approved', 3);
        $this->repository->updateStatus($shipmentId, 'ready_for_shipment', 3);

        $history = $this->repository->getStatusHistory($shipmentId);

        $this->assertCount(3, $history);
        $this->assertEquals('pending_approval', $history[0]['status']);
        $this->assertEquals('approved', $history[1]['status']);
        $this->assertEquals('ready_for_shipment', $history[2]['status']);
    }

    public function testFindByTrackingReturnsMatchingShipment(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );
        $this->repository->assignTracking($shipmentId, 'TRACK123', 'fedex');

        $result = $this->repository->findByTracking('TRACK123', 1);

        $this->assertNotNull($result);
        $this->assertEquals($shipmentId, $result['id']);
        $this->assertEquals('TRACK123', $result['tracking_number']);
    }

    public function testFindByTrackingReturnsNullForWrongUser(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1]
        );
        $this->repository->assignTracking($shipmentId, 'TRACK123', 'fedex');

        $result = $this->repository->findByTracking('TRACK123', 2);

        $this->assertNull($result);
    }

    public function testFindByTrackingReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findByTracking('NONEXISTENT', 1);

        $this->assertNull($result);
    }

    public function testGetRecentByUserIdReturnsLimitedResults(): void
    {
        // Create 7 shipments for user 1
        for ($i = 0; $i < 7; $i++) {
            $this->repository->create($this->makeShipmentData(1), [1]);
        }

        $result = $this->repository->getRecentByUserId(1, 5);

        $this->assertCount(5, $result);
    }

    public function testGetRecentByUserIdReturnsOnlyUserShipments(): void
    {
        $this->repository->create($this->makeShipmentData(1), [1]);
        $this->repository->create($this->makeShipmentData(2), [3]);

        $result = $this->repository->getRecentByUserId(1, 5);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['user_id']);
    }

    public function testGetAllPaginatedReturnsAllShipments(): void
    {
        $this->repository->create($this->makeShipmentData(1), [1]);
        $this->repository->create($this->makeShipmentData(2), [3]);

        $result = $this->repository->getAllPaginated(1, 20);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testGetAllPaginatedPaginatesCorrectly(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repository->create($this->makeShipmentData(1), [1]);
        }

        $page1 = $this->repository->getAllPaginated(1, 2);
        $page2 = $this->repository->getAllPaginated(2, 2);
        $page3 = $this->repository->getAllPaginated(3, 2);

        $this->assertEquals(5, $page1['total']);
        $this->assertCount(2, $page1['data']);
        $this->assertCount(2, $page2['data']);
        $this->assertCount(1, $page3['data']);
    }

    public function testGetShipmentItemsReturnsInventoryRecords(): void
    {
        $shipmentId = $this->repository->create(
            $this->makeShipmentData(1),
            [1, 2]
        );

        $items = $this->repository->getShipmentItems($shipmentId);

        $this->assertCount(2, $items);
        $serialNumbers = array_column($items, 'serial_number');
        $this->assertContains('SN001', $serialNumbers);
        $this->assertContains('SN002', $serialNumbers);
    }

    public function testIsItemInActiveShipmentReturnsTrueForPendingShipment(): void
    {
        $this->repository->create($this->makeShipmentData(1), [1]);

        $this->assertTrue($this->repository->isItemInActiveShipment(1));
    }

    public function testIsItemInActiveShipmentReturnsFalseForDeliveredShipment(): void
    {
        $shipmentId = $this->repository->create($this->makeShipmentData(1), [1]);
        $this->repository->updateStatus($shipmentId, 'delivered', 3);

        $this->assertFalse($this->repository->isItemInActiveShipment(1));
    }

    public function testIsItemInActiveShipmentReturnsFalseForNoShipment(): void
    {
        $this->assertFalse($this->repository->isItemInActiveShipment(2));
    }

    /**
     * Helper to create standard shipment data.
     */
    private function makeShipmentData(int $userId): array
    {
        return [
            'user_id' => $userId,
            'street' => '123 Gold St',
            'city' => 'Goldville',
            'state_province' => 'CA',
            'postal_code' => '90210',
            'country' => 'US',
            'insurance_selected' => true,
            'insured_value' => 5000.00,
        ];
    }
}
