<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\PaymentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the PaymentRepository.
 *
 * Uses an in-memory SQLite database to test payment transaction
 * data access without requiring a MySQL connection.
 */
class PaymentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PaymentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Create a simplified payment_transactions table compatible with SQLite
        $this->pdo->exec('
            CREATE TABLE payment_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                gateway VARCHAR(50) NOT NULL,
                transaction_id VARCHAR(255) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                status VARCHAR(50) NOT NULL,
                transaction_date DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->repository = new PaymentRepository($this->pdo);
    }

    public function testCreateReturnsInsertedId(): void
    {
        $id = $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-001',
            'amount'           => '150.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertSame(1, $id);
    }

    public function testCreateInsertsCorrectData(): void
    {
        $this->repository->create([
            'invoice_id'       => 5,
            'gateway'          => 'stripe',
            'transaction_id'   => 'pi_abc123',
            'amount'           => '999.99',
            'status'           => 'completed',
            'transaction_date' => '2024-03-20 14:00:00',
        ]);

        $stmt = $this->pdo->query('SELECT * FROM payment_transactions WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(5, $row['invoice_id']);
        $this->assertEquals('stripe', $row['gateway']);
        $this->assertEquals('pi_abc123', $row['transaction_id']);
        $this->assertEquals('999.99', $row['amount']);
        $this->assertEquals('completed', $row['status']);
        $this->assertEquals('2024-03-20 14:00:00', $row['transaction_date']);
    }

    public function testCreateMultipleReturnsIncrementingIds(): void
    {
        $id1 = $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-001',
            'amount'           => '100.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-01 00:00:00',
        ]);

        $id2 = $this->repository->create([
            'invoice_id'       => 2,
            'gateway'          => 'stripe',
            'transaction_id'   => 'TXN-002',
            'amount'           => '200.00',
            'status'           => 'failed',
            'transaction_date' => '2024-01-02 00:00:00',
        ]);

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    public function testFindByInvoiceIdReturnsAllTransactions(): void
    {
        // Insert two transactions for invoice 1
        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-FAIL',
            'amount'           => '100.00',
            'status'           => 'failed',
            'transaction_date' => '2024-01-10 09:00:00',
        ]);

        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'stripe',
            'transaction_id'   => 'TXN-SUCCESS',
            'amount'           => '100.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-10 10:00:00',
        ]);

        // Insert one transaction for invoice 2
        $this->repository->create([
            'invoice_id'       => 2,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-OTHER',
            'amount'           => '50.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-11 12:00:00',
        ]);

        $results = $this->repository->findByInvoiceId(1);

        $this->assertCount(2, $results);
        // Most recent transaction_date first
        $this->assertEquals('TXN-SUCCESS', $results[0]['transaction_id']);
        $this->assertEquals('TXN-FAIL', $results[1]['transaction_id']);
    }

    public function testFindByInvoiceIdReturnsEmptyArrayWhenNone(): void
    {
        $results = $this->repository->findByInvoiceId(999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindSuccessfulByInvoiceIdReturnsCompletedTransaction(): void
    {
        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-FAIL',
            'amount'           => '100.00',
            'status'           => 'failed',
            'transaction_date' => '2024-01-10 09:00:00',
        ]);

        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'stripe',
            'transaction_id'   => 'TXN-SUCCESS',
            'amount'           => '100.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-10 10:00:00',
        ]);

        $result = $this->repository->findSuccessfulByInvoiceId(1);

        $this->assertNotNull($result);
        $this->assertEquals('TXN-SUCCESS', $result['transaction_id']);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('stripe', $result['gateway']);
    }

    public function testFindSuccessfulByInvoiceIdReturnsNullWhenNoSuccess(): void
    {
        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-FAIL-1',
            'amount'           => '100.00',
            'status'           => 'failed',
            'transaction_date' => '2024-01-10 09:00:00',
        ]);

        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'stripe',
            'transaction_id'   => 'TXN-FAIL-2',
            'amount'           => '100.00',
            'status'           => 'declined',
            'transaction_date' => '2024-01-10 10:00:00',
        ]);

        $result = $this->repository->findSuccessfulByInvoiceId(1);

        $this->assertNull($result);
    }

    public function testFindSuccessfulByInvoiceIdReturnsNullForNonexistentInvoice(): void
    {
        $result = $this->repository->findSuccessfulByInvoiceId(999);

        $this->assertNull($result);
    }

    public function testLogTransactionCreatesRecord(): void
    {
        $this->repository->logTransaction([
            'invoice_id'       => 3,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-AUDIT',
            'amount'           => '75.50',
            'status'           => 'pending',
            'transaction_date' => '2024-02-01 08:00:00',
        ]);

        $stmt = $this->pdo->query('SELECT COUNT(*) as cnt FROM payment_transactions');
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $this->assertEquals(1, $count);

        $results = $this->repository->findByInvoiceId(3);
        $this->assertCount(1, $results);
        $this->assertEquals('TXN-AUDIT', $results[0]['transaction_id']);
        $this->assertEquals('paypal', $results[0]['gateway']);
    }

    public function testFindByInvoiceIdDoesNotReturnOtherInvoiceTransactions(): void
    {
        $this->repository->create([
            'invoice_id'       => 1,
            'gateway'          => 'paypal',
            'transaction_id'   => 'TXN-INV1',
            'amount'           => '100.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-10 09:00:00',
        ]);

        $this->repository->create([
            'invoice_id'       => 2,
            'gateway'          => 'stripe',
            'transaction_id'   => 'TXN-INV2',
            'amount'           => '200.00',
            'status'           => 'completed',
            'transaction_date' => '2024-01-11 09:00:00',
        ]);

        $results = $this->repository->findByInvoiceId(1);

        $this->assertCount(1, $results);
        $this->assertEquals('TXN-INV1', $results[0]['transaction_id']);
    }
}
