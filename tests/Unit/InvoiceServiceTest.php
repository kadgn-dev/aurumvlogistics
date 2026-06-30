<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Result;
use GOLS\Services\InvoiceService;
use GOLS\Services\NotificationService;
use GOLS\ValidationResult;
use GOLS\Validators\InvoiceValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InvoiceServiceTest extends TestCase
{
    private InvoiceService $service;
    private MockObject $invoiceRepository;
    private MockObject $invoiceValidator;
    private MockObject $userRepository;
    private MockObject $paymentRepository;
    private MockObject $notificationService;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepository::class);
        $this->invoiceValidator = $this->createMock(InvoiceValidator::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->service = new InvoiceService(
            $this->invoiceRepository,
            $this->invoiceValidator,
            $this->userRepository,
            $this->paymentRepository,
            $this->notificationService
        );
    }

    // --- createInvoice tests ---

    public function testCreateInvoiceSuccess(): void
    {
        $data = ['user_id' => 1, 'amount' => 100.50, 'description' => 'Storage fee'];

        $this->invoiceValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->userRepository->method('findById')
            ->with(1)
            ->willReturn(['id' => 1, 'name' => 'John', 'role' => 'client']);

        $this->invoiceRepository->method('create')
            ->willReturn(42);

        $this->invoiceRepository->method('findById')
            ->with(42)
            ->willReturn([
                'id' => 42,
                'user_id' => 1,
                'invoice_number' => 'INV-2024-00001',
                'amount' => '100.50',
                'description' => 'Storage fee',
                'status' => 'unpaid',
                'payment_date' => null,
                'created_at' => '2024-01-01 00:00:00',
            ]);

        $this->notificationService->expects($this->once())
            ->method('create')
            ->with(1, 'invoice_generated', $this->anything(), 42, 'invoice');

        $result = $this->service->createInvoice($data);

        $this->assertTrue($result->success);
        $this->assertEquals(42, $result->data['invoice_id']);
        $this->assertEquals('INV-2024-00001', $result->data['invoice_number']);
        $this->assertEquals('unpaid', $result->data['status']);
    }

    public function testCreateInvoiceValidationFailure(): void
    {
        $data = ['user_id' => '', 'amount' => '', 'description' => ''];

        $this->invoiceValidator->method('validate')
            ->willReturn(ValidationResult::failure(['amount' => 'Amount is required.']));

        $result = $this->service->createInvoice($data);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('amount', $result->errors);
    }

    public function testCreateInvoiceUserNotFound(): void
    {
        $data = ['user_id' => 999, 'amount' => 50.00, 'description' => 'Test'];

        $this->invoiceValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->userRepository->method('findById')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->createInvoice($data);

        $this->assertFalse($result->success);
        $this->assertEquals('USER_NOT_FOUND', $result->errorCode);
    }

    public function testCreateInvoiceRejectsNonClient(): void
    {
        $data = ['user_id' => 1, 'amount' => 50.00, 'description' => 'Test'];

        $this->invoiceValidator->method('validate')
            ->willReturn(ValidationResult::success());

        $this->userRepository->method('findById')
            ->with(1)
            ->willReturn(['id' => 1, 'name' => 'Admin', 'role' => 'admin']);

        $result = $this->service->createInvoice($data);

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_A_CLIENT', $result->errorCode);
    }

    // --- markPaid tests ---

    public function testMarkPaidSuccess(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '200.00',
            'status' => 'unpaid',
            'payment_date' => null,
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $this->invoiceRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'paid', $this->anything());

        $this->notificationService->expects($this->once())
            ->method('create')
            ->with(5, 'payment_confirmed', $this->anything(), 1, 'invoice');

        $result = $this->service->markPaid(1);

        $this->assertTrue($result->success);
        $this->assertEquals('paid', $result->data['status']);
        $this->assertNotEmpty($result->data['payment_date']);
    }

    public function testMarkPaidWithCustomDate(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '200.00',
            'status' => 'unpaid',
            'payment_date' => null,
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $customDate = '2024-06-15 14:30:00';

        $this->invoiceRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'paid', $customDate);

        $result = $this->service->markPaid(1, $customDate);

        $this->assertTrue($result->success);
        $this->assertEquals($customDate, $result->data['payment_date']);
    }

    public function testMarkPaidInvoiceNotFound(): void
    {
        $this->invoiceRepository->method('findById')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->markPaid(999);

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    public function testMarkPaidRejectsAlreadyPaid(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '200.00',
            'status' => 'paid',
            'payment_date' => '2024-01-01 00:00:00',
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $result = $this->service->markPaid(1);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_PAID', $result->errorCode);
    }

    // --- getClientInvoices tests ---

    public function testGetClientInvoicesReturnsData(): void
    {
        $expected = [
            'data' => [['id' => 1, 'invoice_number' => 'INV-2024-00001']],
            'total' => 1,
        ];

        $this->invoiceRepository->method('findByUserId')
            ->with(5, 1)
            ->willReturn($expected);

        $result = $this->service->getClientInvoices(5, 1);

        $this->assertEquals($expected, $result);
    }

    public function testGetClientInvoicesEnforcesMinPage(): void
    {
        $this->invoiceRepository->expects($this->once())
            ->method('findByUserId')
            ->with(5, 1);

        $this->service->getClientInvoices(5, 0);
    }

    // --- getInvoiceById tests ---

    public function testGetInvoiceByIdReturnsInvoice(): void
    {
        $invoice = ['id' => 1, 'invoice_number' => 'INV-2024-00001'];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $result = $this->service->getInvoiceById(1);

        $this->assertEquals($invoice, $result);
    }

    public function testGetInvoiceByIdReturnsNullWhenNotFound(): void
    {
        $this->invoiceRepository->method('findById')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->getInvoiceById(999);

        $this->assertNull($result);
    }

    // --- generatePdf tests ---

    public function testGeneratePdfInvoiceNotFound(): void
    {
        $this->invoiceRepository->method('findById')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->generatePdf(999, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    public function testGeneratePdfAccessDenied(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '100.00',
            'description' => 'Test',
            'status' => 'unpaid',
            'payment_date' => null,
            'created_at' => '2024-01-01 00:00:00',
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $result = $this->service->generatePdf(1, 99);

        $this->assertFalse($result->success);
        $this->assertEquals('ACCESS_DENIED', $result->errorCode);
    }

    public function testGeneratePdfSuccess(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '100.00',
            'description' => 'Storage fee for January',
            'status' => 'unpaid',
            'payment_date' => null,
            'created_at' => '2024-01-01 00:00:00',
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $this->userRepository->method('findById')
            ->with(5)
            ->willReturn(['id' => 5, 'name' => 'John Doe', 'role' => 'client']);

        $result = $this->service->generatePdf(1, 5);

        // If TCPDF is available, it should succeed; otherwise it will fail gracefully
        if (class_exists(\TCPDF::class)) {
            $this->assertTrue($result->success);
            $this->assertEquals('INV-2024-00001', $result->data['invoice_number']);
            $this->assertStringContainsString('invoice_INV-2024-00001.pdf', $result->data['filename']);
        } else {
            // Without TCPDF, the buildPdf method throws and we get an error result
            $this->assertFalse($result->success);
            $this->assertEquals('PDF_GENERATION_FAILED', $result->errorCode);
        }
    }

    // --- generateInvoiceNumber tests ---

    public function testGenerateInvoiceNumberDelegatesToRepository(): void
    {
        $this->invoiceRepository->method('generateInvoiceNumber')
            ->willReturn('INV-2024-00005');

        $result = $this->service->generateInvoiceNumber();

        $this->assertEquals('INV-2024-00005', $result);
    }

    // --- recordPayment tests ---

    public function testRecordPaymentSuccess(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '200.00',
            'status' => 'unpaid',
            'payment_date' => null,
        ];

        $transactionData = [
            'gateway' => 'stripe',
            'transaction_id' => 'txn_123',
            'amount' => '200.00',
            'status' => 'completed',
            'transaction_date' => '2024-06-15 10:00:00',
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $this->paymentRepository->expects($this->once())
            ->method('create')
            ->willReturn(10);

        $this->invoiceRepository->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'paid', '2024-06-15 10:00:00');

        $this->notificationService->expects($this->once())
            ->method('create')
            ->with(5, 'payment_confirmed', $this->anything(), 1, 'invoice');

        $result = $this->service->recordPayment(1, $transactionData);

        $this->assertTrue($result->success);
        $this->assertEquals(10, $result->data['transaction_id']);
        $this->assertEquals('paid', $result->data['status']);
    }

    public function testRecordPaymentInvoiceNotFound(): void
    {
        $this->invoiceRepository->method('findById')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->recordPayment(999, []);

        $this->assertFalse($result->success);
        $this->assertEquals('NOT_FOUND', $result->errorCode);
    }

    public function testRecordPaymentRejectsAlreadyPaid(): void
    {
        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '200.00',
            'status' => 'paid',
            'payment_date' => '2024-01-01 00:00:00',
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $result = $this->service->recordPayment(1, []);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_PAID', $result->errorCode);
    }

    // --- getAllInvoices tests ---

    public function testGetAllInvoicesReturnsData(): void
    {
        $expected = [
            'data' => [
                ['id' => 1, 'invoice_number' => 'INV-2024-00001'],
                ['id' => 2, 'invoice_number' => 'INV-2024-00002'],
            ],
            'total' => 2,
        ];

        $this->invoiceRepository->method('getAllPaginated')
            ->with(1)
            ->willReturn($expected);

        $result = $this->service->getAllInvoices(1);

        $this->assertEquals($expected, $result);
    }

    public function testGetAllInvoicesEnforcesMinPage(): void
    {
        $this->invoiceRepository->expects($this->once())
            ->method('getAllPaginated')
            ->with(1);

        $this->service->getAllInvoices(-1);
    }

    // --- Constructor without NotificationService ---

    public function testWorksWithoutNotificationService(): void
    {
        $serviceWithoutNotifications = new InvoiceService(
            $this->invoiceRepository,
            $this->invoiceValidator,
            $this->userRepository,
            $this->paymentRepository,
            null
        );

        $invoice = [
            'id' => 1,
            'user_id' => 5,
            'invoice_number' => 'INV-2024-00001',
            'amount' => '200.00',
            'status' => 'unpaid',
            'payment_date' => null,
        ];

        $this->invoiceRepository->method('findById')
            ->with(1)
            ->willReturn($invoice);

        $this->invoiceRepository->method('updateStatus')
            ->willReturn(true);

        // Should not throw even without notification service
        $result = $serviceWithoutNotifications->markPaid(1);

        $this->assertTrue($result->success);
    }
}
