<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Repositories;

use GOLS\Repositories\InventoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the InventoryRepository.
 *
 * Uses an in-memory SQLite database to test repository logic
 * without requiring a MySQL connection.
 */
class InventoryRepositoryTest extends TestCase
{
    private PDO $pdo;
    private InventoryRepository $repository;

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
    }

    public function testCreateReturnsNewItemId(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.500,
            'purity' => 0.9999,
            'serial_number' => 'SN001',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testFindByIdReturnsItem(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.103,
            'purity' => 0.9167,
            'serial_number' => 'COIN001',
            'vault_location' => 'Vault B',
            'insurance_status' => false,
        ]);

        $item = $this->repository->findById($id);

        $this->assertNotNull($item);
        $this->assertEquals('coin', $item['gold_type']);
        $this->assertEquals('COIN001', $item['serial_number']);
        $this->assertEquals('Vault B', $item['vault_location']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $item = $this->repository->findById(9999);

        $this->assertNull($item);
    }

    public function testFindByUserIdReturnsOnlyActiveItems(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'ACTIVE1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $inactiveId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'INACTIVE1',
            'vault_location' => 'Vault A',
            'insurance_status' => false,
        ]);

        // Deactivate one item
        $this->repository->deactivate($inactiveId);

        $result = $this->repository->findByUserId(1, 1);

        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('ACTIVE1', $result['data'][0]['serial_number']);
    }

    public function testFindByUserIdScopesToUser(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'USER1_ITEM',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 2,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'USER2_ITEM',
            'vault_location' => 'Vault B',
            'insurance_status' => false,
        ]);

        $result = $this->repository->findByUserId(1, 1);

        $this->assertCount(1, $result['data']);
        $this->assertEquals('USER1_ITEM', $result['data'][0]['serial_number']);
    }

    public function testFindByUserIdPagination(): void
    {
        // Create 30 items for user 1
        for ($i = 1; $i <= 30; $i++) {
            $this->repository->create([
                'user_id' => 1,
                'gold_type' => 'bar',
                'weight' => 10.0 + $i,
                'purity' => 0.9999,
                'serial_number' => "PAGE_SN_{$i}",
                'vault_location' => 'Vault A',
                'insurance_status' => true,
            ]);
        }

        // Page 1 should have 25 items
        $page1 = $this->repository->findByUserId(1, 1, 25);
        $this->assertCount(25, $page1['data']);
        $this->assertEquals(30, $page1['total']);

        // Page 2 should have 5 items
        $page2 = $this->repository->findByUserId(1, 2, 25);
        $this->assertCount(5, $page2['data']);
        $this->assertEquals(30, $page2['total']);
    }

    public function testFindByUserIdFilterByGoldType(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'BAR1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'COIN1',
            'vault_location' => 'Vault A',
            'insurance_status' => false,
        ]);

        $result = $this->repository->findByUserId(1, 1, 25, 'bar');

        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('bar', $result['data'][0]['gold_type']);
    }

    public function testFindByUserIdFilterByVaultLocation(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'LOC_A',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 200.0,
            'purity' => 0.9999,
            'serial_number' => 'LOC_B',
            'vault_location' => 'Vault B',
            'insurance_status' => true,
        ]);

        $result = $this->repository->findByUserId(1, 1, 25, null, 'Vault B');

        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Vault B', $result['data'][0]['vault_location']);
    }

    public function testFindByIdsReturnsMatchingItems(): void
    {
        $id1 = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'MULTI1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $id2 = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'MULTI2',
            'vault_location' => 'Vault B',
            'insurance_status' => false,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'grain',
            'weight' => 1.0,
            'purity' => 0.9999,
            'serial_number' => 'MULTI3',
            'vault_location' => 'Vault A',
            'insurance_status' => false,
        ]);

        $items = $this->repository->findByIds([$id1, $id2]);

        $this->assertCount(2, $items);
    }

    public function testFindByIdsReturnsEmptyForEmptyInput(): void
    {
        $items = $this->repository->findByIds([]);

        $this->assertEmpty($items);
    }

    public function testUpdateModifiesFields(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'UPDATE1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $result = $this->repository->update($id, [
            'weight' => 200.0,
            'vault_location' => 'Vault C',
        ]);

        $this->assertTrue($result);

        $item = $this->repository->findById($id);
        $this->assertEquals(200.0, (float) $item['weight']);
        $this->assertEquals('Vault C', $item['vault_location']);
    }

    public function testUpdateReturnsFalseForNoValidFields(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'NOUPDATE',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $result = $this->repository->update($id, [
            'invalid_field' => 'value',
        ]);

        $this->assertFalse($result);
    }

    public function testDeactivateSetsIsActiveToZero(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'DEACT1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $result = $this->repository->deactivate($id);

        $this->assertTrue($result);

        $item = $this->repository->findById($id);
        $this->assertEquals(0, (int) $item['is_active']);
    }

    public function testDeactivateReturnsFalseForAlreadyInactive(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'DEACT2',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->deactivate($id);
        $result = $this->repository->deactivate($id);

        $this->assertFalse($result);
    }

    public function testCheckSerialExistsReturnsTrueForExisting(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'UNIQUE1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->assertTrue($this->repository->checkSerialExists('UNIQUE1'));
    }

    public function testCheckSerialExistsReturnsFalseForNonExisting(): void
    {
        $this->assertFalse($this->repository->checkSerialExists('NONEXISTENT'));
    }

    public function testCheckSerialExistsExcludesSpecifiedId(): void
    {
        $id = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'EXCLUDE1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Should return false when excluding the item's own ID (for updates)
        $this->assertFalse($this->repository->checkSerialExists('EXCLUDE1', $id));

        // Should return true when not excluding
        $this->assertTrue($this->repository->checkSerialExists('EXCLUDE1'));
    }

    public function testGetItemsInActiveShipmentsReturnsPendingItems(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'SHIP1',
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

        $result = $this->repository->getItemsInActiveShipments([$itemId]);

        $this->assertContains($itemId, $result);
    }

    public function testGetItemsInActiveShipmentsReturnsInTransitItems(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'SHIP2',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Create an in_transit shipment with this item
        $this->pdo->exec("
            INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, status)
            VALUES (1, '123 St', 'City', 'State', '12345', 'US', 'in_transit')
        ");
        $shipmentId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO shipment_items (shipment_id, inventory_id) VALUES ({$shipmentId}, {$itemId})
        ");

        $result = $this->repository->getItemsInActiveShipments([$itemId]);

        $this->assertContains($itemId, $result);
    }

    public function testGetItemsInActiveShipmentsExcludesDeliveredItems(): void
    {
        $itemId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'SHIP3',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        // Create a delivered shipment with this item
        $this->pdo->exec("
            INSERT INTO shipments (user_id, street, city, state_province, postal_code, country, status)
            VALUES (1, '123 St', 'City', 'State', '12345', 'US', 'delivered')
        ");
        $shipmentId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO shipment_items (shipment_id, inventory_id) VALUES ({$shipmentId}, {$itemId})
        ");

        $result = $this->repository->getItemsInActiveShipments([$itemId]);

        $this->assertNotContains($itemId, $result);
    }

    public function testGetItemsInActiveShipmentsReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repository->getItemsInActiveShipments([]);

        $this->assertEmpty($result);
    }

    public function testGetPortfolioSummaryReturnsZerosForEmptyInventory(): void
    {
        $summary = $this->repository->getPortfolioSummary(1);

        $this->assertEquals(0.0, $summary['total_weight']);
        $this->assertEquals(0.0, $summary['total_insured_value']);
        $this->assertEquals(0, $summary['vault_locations']);
    }

    public function testGetPortfolioSummarySumsWeightOfActiveItems(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.5,
            'purity' => 0.9999,
            'serial_number' => 'PORT1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.103,
            'purity' => 0.9167,
            'serial_number' => 'PORT2',
            'vault_location' => 'Vault B',
            'insurance_status' => false,
        ]);

        $summary = $this->repository->getPortfolioSummary(1);

        $this->assertEqualsWithDelta(131.603, $summary['total_weight'], 0.001);
    }

    public function testGetPortfolioSummaryExcludesInactiveItems(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'PORT_ACTIVE',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $inactiveId = $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 50.0,
            'purity' => 0.9167,
            'serial_number' => 'PORT_INACTIVE',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->deactivate($inactiveId);

        $summary = $this->repository->getPortfolioSummary(1);

        $this->assertEqualsWithDelta(100.0, $summary['total_weight'], 0.001);
    }

    public function testGetPortfolioSummaryCountsDistinctVaultLocations(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'LOC1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 200.0,
            'purity' => 0.9999,
            'serial_number' => 'LOC2',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 31.0,
            'purity' => 0.9167,
            'serial_number' => 'LOC3',
            'vault_location' => 'Vault B',
            'insurance_status' => false,
        ]);

        $summary = $this->repository->getPortfolioSummary(1);

        $this->assertEquals(2, $summary['vault_locations']);
    }

    public function testGetPortfolioSummaryScopesToUser(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'SCOPE1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 2,
            'gold_type' => 'bar',
            'weight' => 500.0,
            'purity' => 0.9999,
            'serial_number' => 'SCOPE2',
            'vault_location' => 'Vault B',
            'insurance_status' => true,
        ]);

        $summary = $this->repository->getPortfolioSummary(1);

        $this->assertEqualsWithDelta(100.0, $summary['total_weight'], 0.001);
        $this->assertEquals(1, $summary['vault_locations']);
    }

    public function testGetPortfolioSummaryCalculatesInsuredValueOnlyForInsuredItems(): void
    {
        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'bar',
            'weight' => 100.0,
            'purity' => 0.9999,
            'serial_number' => 'INS1',
            'vault_location' => 'Vault A',
            'insurance_status' => true,
        ]);

        $this->repository->create([
            'user_id' => 1,
            'gold_type' => 'coin',
            'weight' => 50.0,
            'purity' => 0.9167,
            'serial_number' => 'INS2',
            'vault_location' => 'Vault A',
            'insurance_status' => false,
        ]);

        $summary = $this->repository->getPortfolioSummary(1);

        // Only the insured item contributes: 100.0 * 0.9999 = 99.99
        $this->assertEqualsWithDelta(99.99, $summary['total_insured_value'], 0.01);
    }
}
