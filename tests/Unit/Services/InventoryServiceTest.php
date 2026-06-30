<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Services;

use GOLS\Repositories\InventoryRepository;
use GOLS\Services\InventoryService;
use GOLS\Validators\InventoryValidator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the InventoryService.
 *
 * Uses an in-memory SQLite database to test service logic
 * with real repository and validator integration.
 */
class InventoryServiceTest extends TestCase
{
    private PDO $pdo;
    private InventoryRepository $repository;
    private InventoryValidator $validator;
    private InventoryService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

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

        // Insert test users
        $this->pdo->exec("
            INSERT INTO users (name, email, phone, password_hash, role, status)
            VALUES ('Client One', 'client1@test.com', '1234567890', 'hash1', 'client', 'active')
        ");
        $this->pdo->exec("
            INSERT INTO users (name, email, phone, password_hash, role, status)
            VALUES ('Client Two', 'client2@test.com', '0987654321', 'hash2', 'client', 'active')
        ");

        $this->repository = new InventoryRepository($this->pdo);
        $this->validator = new InventoryValidator();
        $this->service = new InventoryService($this->repository, $this->validator);
    }

    // --- getClientInventory tests ---

    public function testGetClientInventoryReturnsPaginatedResults(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->repository->create([
                'user_id' => 1,
                'gold_type' => 'bar',
                'weight' => 10.0 + $i,
                'purity' => 0.9999,
                'serial_number' => sprintf('INVSN%03d', $i),
                'vault_location' => 'Vault A',
                'insurance_status' => true,
            ]);
        }

        $result = $this->service->getClientInventory(1, 1);

        $this->assertCount(25, $result['data']);
        $this->assertEquals(30, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(25, $result['perPage']);
    }

    public function testGetClientInventoryPage2(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->repository->create([
                'user_id' => 1,
                'gold_type' => 'bar',
                'weight' => 10.0 + $i,
                'purity' => 0.9999,
                'serial_number' => sprintf('P2SN%03d', $i),
                'vault_location' => 'Vault A',
                'insurance_status' => true,
            ]);
        }

        $result = $this->service->getClientInventory(1, 2);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(30, $result['total']);
        $this->assertEquals(2, $result['page']);
    }

    public function testGetClientInventoryFilterByGoldType(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'FILTERBAR1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'FILTERCOIN1',
            'vault_location' => 'Vault A',
            'insurance_status' => false,
        ]);

        $result = $this->service->getClientInventory(1, 1, 'bar');

        $this->assertCount(1, $result['data']);
        $this->assertEquals('bar', $result['data'][0]['gold_type']);
    }

    public function testGetClientInventoryFilterByVaultLocation(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'LOCFILTERA',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 200.0,
            'purity' => 0.9999,
            'serial_number' => 'LOCFILTERB',
            'vault_location' => 'Vault B',
            'insurance_status' => true,
        ]);

        $result = $this->service->getClientInventory(1, 1, null, 'Vault B');

        $this->assertCount(1, $result['data']);
        $this->assertEquals('Vault B', $result['data'][0]['vault_location']);
    }

    public function testGetClientInventoryReturnsEmptyForNoItems(): void
    {
        $result = $this->service->getClientInventory(1, 1);

        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['total']);
    }

    public function testGetClientInventoryNormalizesPageToMinimumOne(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'NORMPAGE1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $result = $this->service->getClientInventory(1, 0);

        $this->assertEquals(1, $result['page']);
        $this->assertCount(1, $result['data']);
    }

    // --- createItem tests ---

    public function testCreateItemSuccess(): void
    {
        $result = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.5,
            'purity' => 0.9999,
            'serial_number' => 'CREATE001',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('item_id', $result->data);
        $this->assertGreaterThan(0, $result->data['item_id']);
    }

    public function testCreateItemValidationFailure(): void
    {
        $result = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'invalid_type',
            'weight' => -5,
            'purity' => 2.0,
            'serial_number' => '',
            'vault_location' => '',
            'insurance_status' => 1,
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertNotEmpty($result->errors);
    }

    public function testCreateItemDuplicateSerialNumber(): void
    {
        $firstResult = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'DUPESN001',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);

        $this->assertTrue($firstResult->success);

        $result = $this->service->createItem([
            'user_id' => 2,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'DUPESN001',
            'vault_location' => 'Vault B',
            'insurance_status' => 1,
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('DUPLICATE_SERIAL', $result->errorCode);
    }

    public function testCreateItemMissingRequiredFields(): void
    {
        $result = $this->service->createItem([]);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
    }

    // --- updateItem tests ---

    public function testUpdateItemSuccess(): void
    {
        $createResult = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'UPD001',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);

        $this->assertTrue($createResult->success);
        $itemId = $createResult->data['item_id'];

        $result = $this->service->updateItem($itemId, [
            'weight' => 200.0,
            'vault_location' => 'Vault C',
        ]);

        $this->assertTrue($result->success);

        // Verify the update
        $items = $this->service->getItemsByIds([$itemId]);
        $this->assertEquals(200.0, (float) $items[0]['weight']);
        $this->assertEquals('Vault C', $items[0]['vault_location']);
    }

    public function testUpdateItemNotFound(): void
    {
        $result = $this->service->updateItem(9999, ['weight' => 200.0]);

        $this->assertFalse($result->success);
        $this->assertEquals('ITEM_NOT_FOUND', $result->errorCode);
    }

    public function testUpdateItemDuplicateSerialNumber(): void
    {
        $first = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'EXISTINGSN',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);
        $this->assertTrue($first->success);

        $second = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'OTHERSN001',
            'vault_location' => 'Vault B',
            'insurance_status' => 1,
        ]);
        $this->assertTrue($second->success);

        $result = $this->service->updateItem($second->data['item_id'], [
            'serial_number' => 'EXISTINGSN',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('DUPLICATE_SERIAL', $result->errorCode);
    }

    public function testUpdateItemSameSerialNumberAllowed(): void
    {
        $createResult = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'SAMESN001',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);
        $this->assertTrue($createResult->success);

        // Updating with the same serial number should be allowed
        $result = $this->service->updateItem($createResult->data['item_id'], [
            'serial_number' => 'SAMESN001',
            'weight' => 150.0,
        ]);

        $this->assertTrue($result->success);
    }

    public function testUpdateItemValidationFailure(): void
    {
        $createResult = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'VALIDUPD01',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);
        $this->assertTrue($createResult->success);

        $result = $this->service->updateItem($createResult->data['item_id'], [
            'gold_type' => 'invalid_type',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
    }

    // --- deactivateItem tests ---

    public function testDeactivateItemSuccess(): void
    {
        $createResult = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'DEACTSVC01',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);
        $this->assertTrue($createResult->success);

        $result = $this->service->deactivateItem($createResult->data['item_id']);

        $this->assertTrue($result->success);

        // Verify item is no longer in active inventory
        $inventory = $this->service->getClientInventory(1, 1);
        $activeSerials = array_column($inventory['data'], 'serial_number');
        $this->assertNotContains('DEACTSVC01', $activeSerials);
    }

    public function testDeactivateItemNotFound(): void
    {
        $result = $this->service->deactivateItem(9999);

        $this->assertFalse($result->success);
        $this->assertEquals('ITEM_NOT_FOUND', $result->errorCode);
    }

    public function testDeactivateItemAlreadyInactive(): void
    {
        $createResult = $this->service->createItem([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'DBLDEACT01',
            'vault_location' => 'Vault A',
            'insurance_status' => 1,
        ]);
        $this->assertTrue($createResult->success);

        $this->service->deactivateItem($createResult->data['item_id']);
        $result = $this->service->deactivateItem($createResult->data['item_id']);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_INACTIVE', $result->errorCode);
    }

    // --- validateOwnership tests ---

    public function testValidateOwnershipSuccess(): void
    {
        $id1 = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'OWN001',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);
        $id2 = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'OWN002',
            'vault_location' => 'Vault A',
            'insurance_status' => false,
        ]);

        $result = $this->service->validateOwnership(1, [$id1, $id2]);

        $this->assertTrue($result->success);
    }

    public function testValidateOwnershipFailsForWrongUser(): void
    {
        $id = $this->repository->create([
            'user_id' => 2,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'WRONGOWN01',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $result = $this->service->validateOwnership(1, [$id]);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_ITEMS', $result->errorCode);
    }

    public function testValidateOwnershipFailsForInactiveItems(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'INACTOWN01',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->deactivate($id);

        $result = $this->service->validateOwnership(1, [$id]);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_ITEMS', $result->errorCode);
    }

    public function testValidateOwnershipFailsForNonExistentItems(): void
    {
        $result = $this->service->validateOwnership(1, [9999]);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_ITEMS', $result->errorCode);
    }

    public function testValidateOwnershipFailsForEmptyItemIds(): void
    {
        $result = $this->service->validateOwnership(1, []);

        $this->assertFalse($result->success);
        $this->assertEquals('NO_ITEMS', $result->errorCode);
    }

    public function testValidateOwnershipFailsForItemsInActiveShipments(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'OWNSHIP01',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Create a pending_approval shipment with this item
        $this->pdo->exec("
            INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, status)
            VALUES (1, '123 St', 'City', 'State', '12345', 'US', 'pending_approval')
        ");
        $shipmentId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO shipment_items (shipment_id, inventory_id) VALUES ({$shipmentId}, {$itemId})
        ");

        $result = $this->service->validateOwnership(1, [$itemId]);

        $this->assertFalse($result->success);
        $this->assertEquals('ITEMS_IN_ACTIVE_SHIPMENT', $result->errorCode);
    }

    public function testValidateOwnershipSucceedsForItemsInDeliveredShipments(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'OWNDELIV01',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Create a delivered shipment with this item (should not block ownership validation)
        $this->pdo->exec("
            INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, status)
            VALUES (1, '123 St', 'City', 'State', '12345', 'US', 'delivered')
        ");
        $shipmentId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO shipment_items (shipment_id, inventory_id) VALUES ({$shipmentId}, {$itemId})
        ");

        $result = $this->service->validateOwnership(1, [$itemId]);

        $this->assertTrue($result->success);
    }

    // --- getItemsByIds tests ---

    public function testGetItemsByIdsReturnsItems(): void
    {
        $id1 = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'BYID001',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);
        $id2 = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'BYID002',
            'vault_location' => 'Vault B',
            'insurance_status' => false,
        ]);

        $items = $this->service->getItemsByIds([$id1, $id2]);

        $this->assertCount(2, $items);
    }

    public function testGetItemsByIdsReturnsEmptyForEmptyInput(): void
    {
        $items = $this->service->getItemsByIds([]);

        $this->assertEmpty($items);
    }

    // --- getItemsInActiveShipments tests ---

    public function testGetItemsInActiveShipmentsReturnsPendingItems(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'ACTIVESHIP1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Create a pending_approval shipment
        $this->pdo->exec("
            INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, status)
            VALUES (1, '123 St', 'City', 'State', '12345', 'US', 'pending_approval')
        ");
        $shipmentId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO shipment_items (shipment_id, inventory_id) VALUES ({$shipmentId}, {$itemId})
        ");

        $result = $this->service->getItemsInActiveShipments([$itemId]);

        $this->assertContains($itemId, $result);
    }

    public function testGetItemsInActiveShipmentsExcludesDelivered(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'DELIVSHIP1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Create a delivered shipment
        $this->pdo->exec("
            INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, status)
            VALUES (1, '123 St', 'City', 'State', '12345', 'US', 'delivered')
        ");
        $shipmentId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO shipment_items (shipment_id, inventory_id) VALUES ({$shipmentId}, {$itemId})
        ");

        $result = $this->service->getItemsInActiveShipments([$itemId]);

        $this->assertNotContains($itemId, $result);
    }

    public function testGetItemsInActiveShipmentsReturnsEmptyForNoShipments(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'NOSHIP001',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $result = $this->service->getItemsInActiveShipments([$itemId]);

        $this->assertEmpty($result);
    }
}
