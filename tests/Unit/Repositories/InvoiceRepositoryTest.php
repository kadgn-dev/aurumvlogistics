<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Repositories;

use GOLS\Repositories\InvoiceRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InvoiceRepository.
 *
 * Uses an in-memory SQLite database to test repository logic
 * without requiring a MySQL connection.
 */
class InvoiceRepositoryTest extends TestCase
{
    private PDO $pdo;
    private InvoiceRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->createSchema();
        $this->repository = new InvoiceRepository($this->pdo);
    }

    private function createSchema(): void
    {
        // Create users table (needed for foreign key reference in real DB, simplified here)
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE
            )
        ');

        // Create invoices table (SQLite-compatible version - no default for created_at)
        $this->pdo->exec('
            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                invoice_number TEXT NOT NULL UNIQUE,
                amount REAL NOT NULL,
                description TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "unpaid",
                payment_date TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )
        ');

        // Create invoice_sequence table
        $this->pdo->exec('
            CREATE TABLE invoice_sequence (
                year INTEGER NOT NULL PRIMARY KEY,
                last_number INTEGER NOT NULL DEFAULT 0
            )
        ');

        // Insert a test user
        $this->pdo->exec("INSERT INTO users (id, name, email) VALUES (1, 'Test User', 'test@example.com')");
        $this->pdo->exec("INSERT INTO users (id, name, email) VALUES (2, 'Other User', 'other@example.com')");
    }

    public function testFindByIdReturnsInvoiceWhenExists(): void
    {
        $this->insertInvoice(1, 1, 'INV-2024-00001', 150.00, 'Storage fee');

        $result = $this->repository->findById(1);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('INV-2024-00001', $result['invoice_number']);
        $this->assertEquals(150.00, (float) $result['amount']);
        $this->assertEquals('Storage fee', $result['description']);
        $this->assertEquals('unpaid', $result['status']);
    }

    public function testFindByIdReturnsNullWhenNotExists(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function testFindByUserIdReturnsPaginatedResults(): void
    {
        // Insert 5 invoices for user 1
        for ($i = 1; $i <= 5; $i++) {
            $this->insertInvoice($i, 1, "INV-2024-0000{$i}", 100.00 * $i, "Invoice {$i}");
        }

        // Insert 2 invoices for user 2
        $this->insertInvoice(6, 2, 'INV-2024-00006', 200.00, 'Other user invoice 1');
        $this->insertInvoice(7, 2, 'INV-2024-00007', 300.00, 'Other user invoice 2');

        $result = $this->repository->findByUserId(1, 1, 3);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(5, $result['total']);
        $this->assertCount(3, $result['data']);
    }

    public function testFindByUserIdReturnsCorrectPage(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertInvoice($i, 1, "INV-2024-0000{$i}", 100.00 * $i, "Invoice {$i}");
        }

        $result = $this->repository->findByUserId(1, 2, 3);

        $this->assertEquals(5, $result['total']);
        $this->assertCount(2, $result['data']); // Page 2 with 3 per page = 2 remaining
    }

    public function testFindByUserIdReturnsEmptyForNoInvoices(): void
    {
        $result = $this->repository->findByUserId(1, 1, 20);

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['data']);
    }

    public function testFindByUserIdDoesNotReturnOtherUsersInvoices(): void
    {
        $this->insertInvoice(1, 1, 'INV-2024-00001', 100.00, 'User 1 invoice');
        $this->insertInvoice(2, 2, 'INV-2024-00002', 200.00, 'User 2 invoice');

        $result = $this->repository->findByUserId(1, 1, 20);

        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['data'][0]['user_id']);
    }

    public function testCreateReturnsNewInvoiceId(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 250.50,
            'description' => 'Monthly storage fee',
        ];

        $id = $this->repository->create($data);

        $this->assertGreaterThan(0, $id);

        // Verify the invoice was created
        $invoice = $this->repository->findById($id);
        $this->assertNotNull($invoice);
        $this->assertEquals(1, $invoice['user_id']);
        $this->assertEquals(250.50, (float) $invoice['amount']);
        $this->assertEquals('Monthly storage fee', $invoice['description']);
        $this->assertEquals('unpaid', $invoice['status']);
        $this->assertNull($invoice['payment_date']);
    }

    public function testCreateGeneratesInvoiceNumber(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $id = $this->repository->create($data);
        $invoice = $this->repository->findById($id);

        $this->assertNotNull($invoice['invoice_number']);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $invoice['invoice_number']);
    }

    public function testUpdateStatusMarksPaid(): void
    {
        $this->insertInvoice(1, 1, 'INV-2024-00001', 100.00, 'Test');

        $paymentDate = '2024-06-15 14:30:00';
        $result = $this->repository->updateStatus(1, 'paid', $paymentDate);

        $this->assertTrue($result);

        $invoice = $this->repository->findById(1);
        $this->assertEquals('paid', $invoice['status']);
        $this->assertEquals($paymentDate, $invoice['payment_date']);
    }

    public function testUpdateStatusReturnsFalseForNonExistentInvoice(): void
    {
        $result = $this->repository->updateStatus(999, 'paid', '2024-06-15 14:30:00');

        $this->assertFalse($result);
    }

    public function testUpdateStatusWithNullPaymentDate(): void
    {
        $this->insertInvoice(1, 1, 'INV-2024-00001', 100.00, 'Test');

        $result = $this->repository->updateStatus(1, 'unpaid', null);

        $this->assertTrue($result);

        $invoice = $this->repository->findById(1);
        $this->assertEquals('unpaid', $invoice['status']);
        $this->assertNull($invoice['payment_date']);
    }

    public function testGenerateInvoiceNumberFormat(): void
    {
        $number = $this->repository->generateInvoiceNumber();

        $year = date('Y');
        $this->assertMatchesRegularExpression('/^INV-' . $year . '-\d{5}$/', $number);
    }

    public function testGenerateInvoiceNumberIncrementsSequentially(): void
    {
        $number1 = $this->repository->generateInvoiceNumber();
        $number2 = $this->repository->generateInvoiceNumber();
        $number3 = $this->repository->generateInvoiceNumber();

        $year = date('Y');
        $this->assertEquals("INV-{$year}-00001", $number1);
        $this->assertEquals("INV-{$year}-00002", $number2);
        $this->assertEquals("INV-{$year}-00003", $number3);
    }

    public function testGetAllPaginatedReturnsAllInvoices(): void
    {
        $this->insertInvoice(1, 1, 'INV-2024-00001', 100.00, 'Invoice 1');
        $this->insertInvoice(2, 2, 'INV-2024-00002', 200.00, 'Invoice 2');
        $this->insertInvoice(3, 1, 'INV-2024-00003', 300.00, 'Invoice 3');

        $result = $this->repository->getAllPaginated(1, 20);

        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['data']);
    }

    public function testGetAllPaginatedRespectsPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertInvoice($i, 1, "INV-2024-0000{$i}", 100.00 * $i, "Invoice {$i}");
        }

        $page1 = $this->repository->getAllPaginated(1, 2);
        $page2 = $this->repository->getAllPaginated(2, 2);
        $page3 = $this->repository->getAllPaginated(3, 2);

        $this->assertEquals(5, $page1['total']);
        $this->assertCount(2, $page1['data']);
        $this->assertCount(2, $page2['data']);
        $this->assertCount(1, $page3['data']);
    }

    /**
     * Helper to insert an invoice directly into the database.
     */
    private function insertInvoice(int $id, int $userId, string $invoiceNumber, float $amount, string $description): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoices (id, user_id, invoice_number, amount, description, status, created_at)
             VALUES (:id, :user_id, :invoice_number, :amount, :description, :status, :created_at)'
        );
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':invoice_number' => $invoiceNumber,
            ':amount' => $amount,
            ':description' => $description,
            ':status' => 'unpaid',
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
