<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Repositories\UserRepository;
use GOLS\Result;
use GOLS\Validators\InvoiceValidator;

/**
 * Aurum Vault Logistics Platform (AVL)
 * InvoiceService - Business logic for invoice lifecycle management.
 *
 * Handles invoice creation, payment marking, PDF generation,
 * payment recording, and client invoice retrieval.
 *
 * Requirements: 9.1, 9.2, 10.1, 10.2, 10.3, 10.5
 */
class InvoiceService
{
  private InvoiceRepository $invoiceRepository;
  private InvoiceValidator $invoiceValidator;
  private UserRepository $userRepository;
  private PaymentRepository $paymentRepository;
  private ?NotificationService $notificationService;
  private ?EmailService $emailService;

  public function __construct(
    InvoiceRepository $invoiceRepository,
    InvoiceValidator $invoiceValidator,
    UserRepository $userRepository,
    PaymentRepository $paymentRepository,
    ?NotificationService $notificationService = null,
    ?EmailService $emailService = null
  ) {
    $this->invoiceRepository = $invoiceRepository;
    $this->invoiceValidator = $invoiceValidator;
    $this->userRepository = $userRepository;
    $this->paymentRepository = $paymentRepository;
    $this->notificationService = $notificationService;
    $this->emailService = $emailService;
  }

  /**
   * Create a new invoice for a client.
   *
   * Validates input data, checks user exists and is a client,
   * then creates the invoice with a generated unique number.
   *
   * Requirement 10.1: Admin creates invoice with valid user_id, amount, description.
   * Requirement 10.3: Invoice number format INV-{YYYY}-{NNNNN}.
   * Requirement 10.4: Rejects invalid user_id or out-of-range amount.
   *
   * @param array $data Invoice data: user_id, amount, description
   * @return Result
   */
  public function createInvoice(array $data): Result
  {
    // Validate structural fields
    $validation = $this->invoiceValidator->validate($data);
    if (!$validation->isValid) {
      return Result::validationError($validation->errors);
    }

    $userId = (int) $data['user_id'];

    // Check user exists and is a client
    $user = $this->userRepository->findById($userId);
    if ($user === null) {
      return Result::error('USER_NOT_FOUND', 'The specified user does not exist.');
    }

    if ($user['role'] !== 'client') {
      return Result::error('NOT_A_CLIENT', 'Invoices can only be created for users with the client role.');
    }

    // Create invoice (repository handles invoice number generation)
    $invoiceId = $this->invoiceRepository->create([
      'user_id' => $userId,
      'amount' => $data['amount'],
      'description' => trim($data['description']),
    ]);

    // Retrieve the created invoice to return full details
    $invoice = $this->invoiceRepository->findById($invoiceId);

    // Send notification to the client about the new invoice
    if ($this->notificationService !== null && $invoice !== null) {
      $this->notificationService->create(
        $userId,
        'invoice_generated',
        sprintf(
          'A new invoice %s has been generated for $%s.',
          $invoice['invoice_number'],
          number_format((float) $invoice['amount'], 2)
        ),
        $invoiceId,
        'invoice'
      );
    }

    // Send email notification about the new invoice (Requirement 19.1)
    if ($this->emailService !== null && $invoice !== null) {
      $this->sendInvoiceEmail($userId, $invoice);
    }

    return Result::success([
      'invoice_id' => $invoiceId,
      'invoice_number' => $invoice['invoice_number'] ?? null,
      'status' => 'unpaid',
    ]);
  }

  /**
   * Mark an invoice as paid.
   *
   * Checks invoice exists, rejects if already paid, updates status
   * to paid with payment_date.
   *
   * Requirement 10.2: Admin updates invoice status from unpaid to paid.
   * Requirement 10.5: Rejects update if invoice is already paid.
   *
   * @param int $invoiceId The invoice ID
   * @param string|null $paymentDate Optional payment date (Y-m-d H:i:s). Defaults to current time.
   * @return Result
   */
  public function markPaid(int $invoiceId, ?string $paymentDate = null): Result
  {
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('NOT_FOUND', 'Invoice not found.');
    }

    if ($invoice['status'] === 'paid') {
      return Result::error('ALREADY_PAID', 'This invoice has already been paid.');
    }

    $paymentDate = $paymentDate ?? date('Y-m-d H:i:s');
    $this->invoiceRepository->updateStatus($invoiceId, 'paid', $paymentDate);

    // Send payment confirmation notification to the client
    if ($this->notificationService !== null) {
      $this->notificationService->create(
        (int) $invoice['user_id'],
        'payment_confirmed',
        sprintf(
          'Payment confirmed for invoice %s ($%s).',
          $invoice['invoice_number'],
          number_format((float) $invoice['amount'], 2)
        ),
        $invoiceId,
        'invoice'
      );
    }

    return Result::success([
      'invoice_id' => $invoiceId,
      'status' => 'paid',
      'payment_date' => $paymentDate,
    ]);
  }

  /**
   * Get paginated invoices for a client.
   *
   * Requirement 9.1: Client views invoices sorted by creation date descending.
   *
   * @param int $userId The client user ID
   * @param int $page Page number (1-based)
   * @return array Paginated result with 'data' and 'total' keys
   */
  public function getClientInvoices(int $userId, int $page): array
  {
    return $this->invoiceRepository->findByUserId($userId, max(1, $page));
  }

  /**
   * Get a single invoice by its ID.
   *
   * @param int $invoiceId The invoice ID
   * @return array|null The invoice record or null if not found
   */
  public function getInvoiceById(int $invoiceId): ?array
  {
    return $this->invoiceRepository->findById($invoiceId);
  }

  /**
   * Generate a PDF for an invoice.
   *
   * Validates that the invoice exists and belongs to the requesting user.
   * Generates a PDF using TCPDF containing invoice_number, client name,
   * amount, status, description, created_at, and payment_date.
   *
   * Requirement 9.2: Client downloads invoice as PDF.
   * Requirement 9.3: Restricts to invoices belonging to the client.
   * Requirement 9.5: Rejects if invoice doesn't exist or doesn't belong to client.
   *
   * @param int $invoiceId The invoice ID
   * @param int $userId The requesting user ID (for ownership check)
   * @return Result Success with file_path or error
   */
  public function generatePdf(int $invoiceId, int $userId): Result
  {
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('NOT_FOUND', 'Invoice not found.');
    }

    if ((int) $invoice['user_id'] !== $userId) {
      return Result::error('ACCESS_DENIED', 'You do not have access to this invoice.');
    }

    // Retrieve client details for the PDF
    $user = $this->userRepository->findById((int) $invoice['user_id']);
    $clientName = $user['name'] ?? 'Unknown Client';

    $filename = sprintf('invoice_%s.pdf', $invoice['invoice_number']);
    $filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

    try {
      $this->buildPdf($invoice, $clientName, $filePath);
    } catch (\Exception $e) {
      return Result::error('PDF_GENERATION_FAILED', 'Failed to generate invoice PDF.');
    }

    return Result::success([
      'invoice_id' => $invoiceId,
      'invoice_number' => $invoice['invoice_number'],
      'file_path' => $filePath,
      'filename' => $filename,
    ]);
  }

  /**
   * Generate a unique invoice number atomically.
   *
   * Delegates to the repository which handles the atomic sequence increment.
   * Format: INV-{YYYY}-{NNNNN}
   *
   * Requirement 10.3: Sequential invoice numbers per year.
   *
   * @return string Generated invoice number
   */
  public function generateInvoiceNumber(): string
  {
    return $this->invoiceRepository->generateInvoiceNumber();
  }

  /**
   * Record a payment transaction and mark the invoice as paid.
   *
   * Validates the invoice exists and is unpaid, records the payment
   * transaction, then marks the invoice as paid.
   *
   * Requirement 10.2: Records payment date when marking paid.
   *
   * @param int $invoiceId The invoice ID
   * @param array $transactionData Payment data: gateway, transaction_id, amount, status, transaction_date
   * @return Result
   */
  public function recordPayment(int $invoiceId, array $transactionData): Result
  {
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('NOT_FOUND', 'Invoice not found.');
    }

    if ($invoice['status'] === 'paid') {
      return Result::error('ALREADY_PAID', 'This invoice has already been paid.');
    }

    // Record the payment transaction
    $transactionData['invoice_id'] = $invoiceId;
    $transactionId = $this->paymentRepository->create($transactionData);

    // Mark invoice as paid
    $paymentDate = $transactionData['transaction_date'] ?? date('Y-m-d H:i:s');
    $this->invoiceRepository->updateStatus($invoiceId, 'paid', $paymentDate);

    // Send payment confirmation notification
    if ($this->notificationService !== null) {
      $this->notificationService->create(
        (int) $invoice['user_id'],
        'payment_confirmed',
        sprintf(
          'Payment confirmed for invoice %s ($%s).',
          $invoice['invoice_number'],
          number_format((float) $invoice['amount'], 2)
        ),
        $invoiceId,
        'invoice'
      );
    }

    return Result::success([
      'invoice_id' => $invoiceId,
      'transaction_id' => $transactionId,
      'status' => 'paid',
      'payment_date' => $paymentDate,
    ]);
  }

  /**
   * Get all invoices with pagination (admin view).
   *
   * Returns all invoices across all clients, sorted by created_at DESC.
   *
   * @param int $page Page number (1-based)
   * @return array Paginated result with 'data' and 'total' keys
   */
  public function getAllInvoices(int $page): array
  {
    return $this->invoiceRepository->getAllPaginated(max(1, $page));
  }

  /**
   * Build the PDF document using TCPDF.
   *
   * @param array $invoice The invoice record
   * @param string $clientName The client's name
   * @param string $filePath Output file path
   * @return void
   * @throws \Exception If PDF generation fails
   */
  private function buildPdf(array $invoice, string $clientName, string $filePath): void
  {
    if (!class_exists(\TCPDF::class)) {
      throw new \RuntimeException('TCPDF library is not available.');
    }

    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document metadata
    $pdf->SetCreator('Aurum Vault Logistics Platform');
    $pdf->SetAuthor('AVL');
    $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
    $pdf->SetSubject('Invoice');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Add a page
    $pdf->AddPage();

    // Company header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 12, 'Aurum Vault Logistics', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'INVOICE', 0, 1, 'C');
    $pdf->Ln(10);

    // Invoice details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Invoice Number: ' . $invoice['invoice_number'], 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(45, 7, 'Client:', 0, 0);
    $pdf->Cell(0, 7, $clientName, 0, 1);

    $pdf->Cell(45, 7, 'Amount:', 0, 0);
    $pdf->Cell(0, 7, '$' . number_format((float) $invoice['amount'], 2), 0, 1);

    $pdf->Cell(45, 7, 'Status:', 0, 0);
    $pdf->Cell(0, 7, ucfirst($invoice['status']), 0, 1);

    $pdf->Cell(45, 7, 'Date Issued:', 0, 0);
    $pdf->Cell(0, 7, $invoice['created_at'], 0, 1);

    if (!empty($invoice['payment_date'])) {
      $pdf->Cell(45, 7, 'Payment Date:', 0, 0);
      $pdf->Cell(0, 7, $invoice['payment_date'], 0, 1);
    }

    $pdf->Ln(8);

    // Description section
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'Description:', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 7, $invoice['description'], 0, 'L');

    $pdf->Ln(15);

    // Footer note
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 6, 'Thank you for choosing Aurum Vault Logistics.', 0, 1, 'C');

    // Output to file
    $pdf->Output($filePath, 'F');
  }

  /**
   * Send an invoice generated email to the client.
   *
   * Respects email unsubscribe preferences for the 'invoice_notifications' category.
   * Uses the 'invoice_generated.html' template with invoice details.
   *
   * Requirement 19.1: Send email within 60 seconds of invoice generation.
   * Requirement 19.4, 19.5: Respect unsubscribe preferences for non-critical categories.
   *
   * @param int $userId The client user ID
   * @param array $invoice The invoice record
   */
  private function sendInvoiceEmail(int $userId, array $invoice): void
  {
    // Respect unsubscribe preferences for non-critical invoice_notifications category
    if ($this->emailService->isUnsubscribed($userId, 'invoice_notifications')) {
      return;
    }

    $user = $this->userRepository->findById($userId);
    if ($user === null) {
      return;
    }

    $templateData = [
      'name' => $user['name'],
      'invoice_number' => $invoice['invoice_number'],
      'amount' => '$' . number_format((float) $invoice['amount'], 2),
      'description' => $invoice['description'] ?? '',
      'unsubscribe_url' => (defined('APP_URL') ? APP_URL : 'https://www.aurumvlogistics.com') . '/client/profile.php?unsubscribe=invoice_notifications',
    ];

    $this->emailService->sendWithRetry(
      $user['email'],
      'New Invoice Generated - Aurum Vault Logistics',
      'invoice_generated.html',
      $templateData
    );
  }
}
