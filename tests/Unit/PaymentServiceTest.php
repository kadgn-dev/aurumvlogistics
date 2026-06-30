<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Services\PaymentService;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Unit tests for PaymentService.
 *
 * Tests payment initiation, callback handling, success/failure handling,
 * full payment enforcement, and transaction logging.
 *
 * Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6
 */
class PaymentServiceTest extends TestCase
{
    private PDO $pdo;
    private InvoiceRepository $invoiceRepository;
    private PaymentRepository $paymentRepository;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $this->invoiceRepository = new InvoiceRepository($this->pdo);
        $this->paymentRepository = new PaymentRepository($this->pdo);
        $this->paymentService = new PaymentService(
            $this->invoiceRepository,
            $this->paymentRepository
        );
    }

    private function createTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(15) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role TEXT NOT NULL DEFAULT 'client',
                status TEXT NOT NULL DEFAULT 'pending',
                kyc_status TEXT NOT NULL DEFAULT 'not_submitted',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                invoice_number VARCHAR(20) NOT NULL UNIQUE,
                amount DECIMAL(12,2) NOT NULL,
                description TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'unpaid',
                payment_date DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE invoice_sequence (
                year INTEGER NOT NULL PRIMARY KEY,
                last_number INTEGER NOT NULL DEFAULT 0
            )
        ");

        $this->pdo->exec("
            CREATE TABLE payment_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                gateway TEXT NOT NULL,
                transaction_id VARCHAR(255) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                status VARCHAR(50) NOT NULL,
                transaction_date DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            )
        ");
    }

    private function createUser(int $id = 1): void
    {
        $this->pdo->exec("
            INSERT INTO users (id, name, email, phone, password_hash, role, status)
            VALUES ({$id}, 'Test User', 'user{$id}@example.com', '1234567890', 'hash', 'client', 'active')
        ");
    }

    private function createInvoice(int $userId = 1, float $amount = 100.00, string $status = 'unpaid'): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO invoices (user_id, invoice_number, amount, description, status)
            VALUES (:user_id, :invoice_number, :amount, :description, :status)
        ");
        $invoiceNumber = 'INV-2024-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $stmt->execute([
            ':user_id' => $userId,
            ':invoice_number' => $invoiceNumber,
            ':amount' => $amount,
            ':description' => 'Test invoice',
            ':status' => $status,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ========== initiatePayment Tests ==========

    public function testInitiatePaymentSuccess(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 250.00);

        $result = $this->paymentService->initiatePayment($invoiceId, 'paypal', 1);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('redirect_url', $result->data);
        $this->assertEquals('paypal', $result->data['gateway']);
        $this->assertEquals($invoiceId, $result->data['invoice_id']);
        $this->assertEquals('250.00', $result->data['amount']);
        $this->assertEquals(60, $result->data['timeout']);
    }

    public function testInitiatePaymentWithStripe(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 500.00);

        $result = $this->paymentService->initiatePayment($invoiceId, 'stripe', 1);

        $this->assertTrue($result->success);
        $this->assertEquals('stripe', $result->data['gateway']);
    }

    public function testInitiatePaymentInvalidGateway(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->initiatePayment($invoiceId, 'bitcoin', 1);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_GATEWAY', $result->errorCode);
    }

    public function testInitiatePaymentInvoiceNotFound(): void
    {
        $this->createUser(1);

        $result = $this->paymentService->initiatePayment(999, 'paypal', 1);

        $this->assertFalse($result->success);
        $this->assertEquals('INVOICE_NOT_FOUND', $result->errorCode);
    }

    public function testInitiatePaymentAccessDenied(): void
    {
        $this->createUser(1);
        $this->createUser(2);
        $invoiceId = $this->createInvoice(1, 100.00);

        // User 2 tries to pay user 1's invoice
        $result = $this->paymentService->initiatePayment($invoiceId, 'paypal', 2);

        $this->assertFalse($result->success);
        $this->assertEquals('ACCESS_DENIED', $result->errorCode);
    }

    public function testInitiatePaymentAlreadyPaid(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00, 'paid');

        $result = $this->paymentService->initiatePayment($invoiceId, 'paypal', 1);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_PAID', $result->errorCode);
    }

    // ========== handleCallback Tests ==========

    public function testHandleCallbackSuccess(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-123',
            'amount' => '100.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('paid', $result->data['status']);
        $this->assertEquals('TXN-123', $result->data['transaction_id']);

        // Verify invoice is now paid
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('paid', $invoice['status']);
    }

    public function testHandleCallbackFailedTransaction(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleCallback('stripe', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-456',
            'amount' => '100.00',
            'status' => 'declined',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('PAYMENT_FAILED', $result->errorCode);

        // Invoice should remain unpaid
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('unpaid', $invoice['status']);
    }

    public function testHandleCallbackTimeout(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-789',
            'amount' => '100.00',
            'status' => 'timeout',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('PAYMENT_TIMEOUT', $result->errorCode);
        $this->assertStringContainsString('60 seconds', $result->errorMessage);

        // Invoice should remain unpaid
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('unpaid', $invoice['status']);
    }

    public function testHandleCallbackAmountMismatch(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-PARTIAL',
            'amount' => '50.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('AMOUNT_MISMATCH', $result->errorCode);

        // Invoice should remain unpaid - no partial payments
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('unpaid', $invoice['status']);
    }

    public function testHandleCallbackInvalidGateway(): void
    {
        $result = $this->paymentService->handleCallback('bitcoin', [
            'invoice_id' => 1,
            'transaction_id' => 'TXN-123',
            'amount' => '100.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_GATEWAY', $result->errorCode);
    }

    public function testHandleCallbackMissingPayloadField(): void
    {
        $result = $this->paymentService->handleCallback('paypal', [
            'invoice_id' => 1,
            // missing transaction_id, amount, status, transaction_date
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_PAYLOAD', $result->errorCode);
    }

    public function testHandleCallbackAlreadyPaidInvoice(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00, 'paid');

        $result = $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-DUP',
            'amount' => '100.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_PAID', $result->errorCode);
    }

    // ========== handleSuccess Tests ==========

    public function testHandleSuccessMarksPaid(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 200.00);

        $result = $this->paymentService->handleSuccess($invoiceId, 'stripe', 'TXN-SUCCESS-1', 200.00);

        $this->assertTrue($result->success);
        $this->assertEquals('paid', $result->data['status']);
        $this->assertEquals('TXN-SUCCESS-1', $result->data['transaction_id']);
        $this->assertEquals('stripe', $result->data['gateway']);

        // Verify invoice is paid
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('paid', $invoice['status']);
        $this->assertNotNull($invoice['payment_date']);
    }

    public function testHandleSuccessRecordsTransaction(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 150.00);

        $this->paymentService->handleSuccess($invoiceId, 'paypal', 'TXN-LOG-1', 150.00);

        // Verify transaction was logged
        $transactions = $this->paymentRepository->findByInvoiceId($invoiceId);
        $this->assertCount(1, $transactions);
        $this->assertEquals('paypal', $transactions[0]['gateway']);
        $this->assertEquals('TXN-LOG-1', $transactions[0]['transaction_id']);
        $this->assertEquals('completed', $transactions[0]['status']);
    }

    public function testHandleSuccessRejectsPartialPayment(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 300.00);

        $result = $this->paymentService->handleSuccess($invoiceId, 'paypal', 'TXN-PARTIAL', 150.00);

        $this->assertFalse($result->success);
        $this->assertEquals('AMOUNT_MISMATCH', $result->errorCode);

        // Invoice should remain unpaid
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('unpaid', $invoice['status']);
    }

    public function testHandleSuccessRejectsAlreadyPaid(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00, 'paid');

        $result = $this->paymentService->handleSuccess($invoiceId, 'stripe', 'TXN-DUP', 100.00);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_PAID', $result->errorCode);
    }

    public function testHandleSuccessRejectsInvalidGateway(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleSuccess($invoiceId, 'bitcoin', 'TXN-123', 100.00);

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_GATEWAY', $result->errorCode);
    }

    public function testHandleSuccessInvoiceNotFound(): void
    {
        $result = $this->paymentService->handleSuccess(999, 'paypal', 'TXN-123', 100.00);

        $this->assertFalse($result->success);
        $this->assertEquals('INVOICE_NOT_FOUND', $result->errorCode);
    }

    // ========== handleFailure Tests ==========

    public function testHandleFailureLogsTransaction(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleFailure($invoiceId, 'paypal', 'TXN-FAIL-1', 100.00, 'declined');

        $this->assertFalse($result->success);
        $this->assertEquals('PAYMENT_FAILED', $result->errorCode);
        $this->assertStringContainsString('declined', $result->errorMessage);

        // Verify transaction was logged
        $transactions = $this->paymentRepository->findByInvoiceId($invoiceId);
        $this->assertCount(1, $transactions);
        $this->assertEquals('failed', $transactions[0]['status']);
    }

    public function testHandleFailureKeepsInvoiceUnpaid(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 250.00);

        $this->paymentService->handleFailure($invoiceId, 'stripe', 'TXN-FAIL-2', 250.00, 'insufficient funds');

        // Invoice should remain unpaid
        $invoice = $this->invoiceRepository->findById($invoiceId);
        $this->assertEquals('unpaid', $invoice['status']);
    }

    public function testHandleFailureInvalidGateway(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $result = $this->paymentService->handleFailure($invoiceId, 'bitcoin', 'TXN-123', 100.00, 'error');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_GATEWAY', $result->errorCode);
    }

    public function testHandleFailureInvoiceNotFound(): void
    {
        $result = $this->paymentService->handleFailure(999, 'paypal', 'TXN-123', 100.00, 'error');

        $this->assertFalse($result->success);
        $this->assertEquals('INVOICE_NOT_FOUND', $result->errorCode);
    }

    // ========== getPaymentUrl Tests ==========

    public function testGetPaymentUrlPaypal(): void
    {
        $url = $this->paymentService->getPaymentUrl('paypal', 100.50, 42);

        $this->assertStringContainsString('paypal', $url);
        $this->assertStringContainsString('42', $url);
        $this->assertStringContainsString('100.5', $url);
    }

    public function testGetPaymentUrlStripe(): void
    {
        $url = $this->paymentService->getPaymentUrl('stripe', 250.00, 7);

        $this->assertStringContainsString('stripe', $url);
        $this->assertStringContainsString('7', $url);
        $this->assertStringContainsString('250', $url);
    }

    public function testGetPaymentUrlWithCustomGatewayUrls(): void
    {
        $customService = new PaymentService(
            $this->invoiceRepository,
            $this->paymentRepository,
            [
                'paypal' => 'https://paypal.com/pay?id={invoice_id}&total={amount}',
                'stripe' => 'https://stripe.com/checkout?inv={invoice_id}&amt={amount}',
            ]
        );

        $url = $customService->getPaymentUrl('paypal', 99.99, 5);
        $this->assertEquals('https://paypal.com/pay?id=5&total=99.99', $url);
    }

    // ========== logTransaction Tests ==========

    public function testLogTransactionRecordsData(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        $this->paymentService->logTransaction([
            'invoice_id' => $invoiceId,
            'gateway' => 'paypal',
            'transaction_id' => 'TXN-AUDIT-1',
            'amount' => '100.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $transactions = $this->paymentRepository->findByInvoiceId($invoiceId);
        $this->assertCount(1, $transactions);
        $this->assertEquals('TXN-AUDIT-1', $transactions[0]['transaction_id']);
        $this->assertEquals('paypal', $transactions[0]['gateway']);
    }

    // ========== Full Payment Enforcement (Requirement 18.6) ==========

    public function testNoPartialPaymentsViaCallback(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 500.00);

        // Try to pay only half
        $result = $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-HALF',
            'amount' => '250.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('AMOUNT_MISMATCH', $result->errorCode);
    }

    public function testNoOverpaymentViaCallback(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        // Try to overpay
        $result = $this->paymentService->handleCallback('stripe', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-OVER',
            'amount' => '200.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:30:00',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('AMOUNT_MISMATCH', $result->errorCode);
    }

    public function testCallbackLogsAllGatewayResponses(): void
    {
        $this->createUser(1);
        $invoiceId = $this->createInvoice(1, 100.00);

        // Failed attempt
        $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-FAIL',
            'amount' => '100.00',
            'status' => 'declined',
            'transaction_date' => '2024-01-15 10:00:00',
        ]);

        // Successful attempt
        $this->paymentService->handleCallback('paypal', [
            'invoice_id' => $invoiceId,
            'transaction_id' => 'TXN-OK',
            'amount' => '100.00',
            'status' => 'completed',
            'transaction_date' => '2024-01-15 10:05:00',
        ]);

        // Both should be logged
        $transactions = $this->paymentRepository->findByInvoiceId($invoiceId);
        $this->assertCount(2, $transactions);
    }
}
