<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\InvoiceRepository;
use GOLS\Repositories\PaymentRepository;
use GOLS\Result;

/**
 * Aurum Vault Logistics Platform (AVL)
 * PaymentService - Business logic for payment processing.
 *
 * Handles payment initiation, gateway callback processing, and transaction logging.
 * Enforces full payment only (no partial payments) and handles gateway timeouts (60s).
 *
 * Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6
 */
class PaymentService
{
  private InvoiceRepository $invoiceRepository;
  private PaymentRepository $paymentRepository;

  /**
   * Supported payment gateways.
   */
  private const VALID_GATEWAYS = ['paypal', 'stripe'];

  /**
   * Gateway timeout in seconds.
   */
  private const GATEWAY_TIMEOUT_SECONDS = 60;

  /**
   * Configurable gateway redirect URL templates.
   * In production, these would be loaded from config.
   *
   * @var array<string, string>
   */
  private array $gatewayUrls = [
    'paypal' => '/payment/redirect/paypal?invoice_id={invoice_id}&amount={amount}',
    'stripe' => '/payment/redirect/stripe?invoice_id={invoice_id}&amount={amount}',
  ];

  public function __construct(
    InvoiceRepository $invoiceRepository,
    PaymentRepository $paymentRepository,
    array $gatewayUrls = []
  ) {
    $this->invoiceRepository = $invoiceRepository;
    $this->paymentRepository = $paymentRepository;

    if (!empty($gatewayUrls)) {
      $this->gatewayUrls = $gatewayUrls;
    }
  }

  /**
   * Initiate a payment for an invoice.
   *
   * Validates that the invoice exists, belongs to the user, is unpaid,
   * and the gateway is supported. Returns a redirect URL for the chosen gateway.
   *
   * Requirement 18.1: Present gateway options and redirect within 5 seconds.
   * Requirement 18.6: Full payment only, no partial payments.
   *
   * @param int $invoiceId The invoice to pay
   * @param string $gateway The payment gateway ('paypal' or 'stripe')
   * @param int $userId The user initiating the payment
   * @return Result Success with redirect_url, or error
   */
  public function initiatePayment(int $invoiceId, string $gateway, int $userId): Result
  {
    // Validate gateway
    $gateway = strtolower(trim($gateway));
    if (!in_array($gateway, self::VALID_GATEWAYS, true)) {
      return Result::error(
        'INVALID_GATEWAY',
        'Invalid payment gateway. Supported gateways: ' . implode(', ', self::VALID_GATEWAYS) . '.'
      );
    }

    // Find the invoice
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('INVOICE_NOT_FOUND', 'Invoice not found.');
    }

    // Verify ownership
    if ((int) $invoice['user_id'] !== $userId) {
      return Result::error('ACCESS_DENIED', 'You do not have permission to pay this invoice.');
    }

    // Verify invoice is unpaid
    if ($invoice['status'] !== 'unpaid') {
      return Result::error('ALREADY_PAID', 'This invoice has already been paid.');
    }

    // Generate redirect URL (full payment only - Requirement 18.6)
    $amount = $invoice['amount'];
    $redirectUrl = $this->buildRedirectUrl($gateway, $invoiceId, $amount);

    return Result::success([
      'redirect_url' => $redirectUrl,
      'gateway' => $gateway,
      'invoice_id' => $invoiceId,
      'amount' => $amount,
      'timeout' => self::GATEWAY_TIMEOUT_SECONDS,
    ]);
  }

  /**
   * Handle a payment gateway callback (webhook/return).
   *
   * Verifies the payment amount matches the invoice total (no partial payments),
   * updates the invoice status, and records the transaction.
   *
   * Requirement 18.2: Update invoice to paid, record transaction reference.
   * Requirement 18.3: Handle failed transactions, retain unpaid status.
   * Requirement 18.4: Handle timeout (60s), retain unpaid status.
   * Requirement 18.5: Log all gateway responses.
   * Requirement 18.6: Require full payment amount.
   *
   * @param string $gateway The payment gateway ('paypal' or 'stripe')
   * @param array $payload Gateway callback payload with keys:
   *  - invoice_id (int): The invoice being paid
   *  - transaction_id (string): Gateway transaction reference
   *  - amount (float|string): Amount paid
   *  - status (string): Transaction status ('completed', 'failed', 'timeout')
   *  - transaction_date (string): ISO datetime of the transaction
   * @return Result Success or error with details
   */
  public function handleCallback(string $gateway, array $payload): Result
  {
    // Validate gateway
    $gateway = strtolower(trim($gateway));
    if (!in_array($gateway, self::VALID_GATEWAYS, true)) {
      return Result::error('INVALID_GATEWAY', 'Invalid payment gateway.');
    }

    // Validate required payload fields
    $requiredFields = ['invoice_id', 'transaction_id', 'amount', 'status', 'transaction_date'];
    foreach ($requiredFields as $field) {
      if (!isset($payload[$field]) || $payload[$field] === '') {
        return Result::error('INVALID_PAYLOAD', "Missing required field: {$field}.");
      }
    }

    $invoiceId = (int) $payload['invoice_id'];
    $transactionId = (string) $payload['transaction_id'];
    $amount = (string) $payload['amount'];
    $status = (string) $payload['status'];
    $transactionDate = (string) $payload['transaction_date'];

    // Log all gateway responses for audit (Requirement 18.5)
    $this->logTransaction([
      'invoice_id' => $invoiceId,
      'gateway' => $gateway,
      'transaction_id' => $transactionId,
      'amount' => $amount,
      'status' => $status,
      'transaction_date' => $transactionDate,
    ]);

    // Find the invoice
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('INVOICE_NOT_FOUND', 'Invoice not found.');
    }

    // Check if invoice is already paid
    if ($invoice['status'] === 'paid') {
      return Result::error('ALREADY_PAID', 'This invoice has already been paid.');
    }

    // Handle timeout (Requirement 18.4)
    if ($status === 'timeout') {
      return Result::error(
        'PAYMENT_TIMEOUT',
        'Payment gateway did not respond within ' . self::GATEWAY_TIMEOUT_SECONDS . ' seconds. Please retry.'
      );
    }

    // Handle failed transaction (Requirement 18.3)
    if ($status !== 'completed') {
      return Result::error(
        'PAYMENT_FAILED',
        'Payment failed with status: ' . $status . '. Please retry or select a different gateway.'
      );
    }

    // Validate full payment amount (Requirement 18.6)
    $invoiceAmount = (float) $invoice['amount'];
    $paidAmount = (float) $amount;

    if (abs($paidAmount - $invoiceAmount) > 0.01) {
      return Result::error(
        'AMOUNT_MISMATCH',
        'Payment amount does not match invoice total. Full payment of the outstanding amount is required.'
      );
    }

    // Update invoice status to paid (Requirement 18.2)
    $paymentDate = $transactionDate ?: date('Y-m-d H:i:s');
    $this->invoiceRepository->updateStatus($invoiceId, 'paid', $paymentDate);

    return Result::success([
      'invoice_id' => $invoiceId,
      'transaction_id' => $transactionId,
      'gateway' => $gateway,
      'amount' => $amount,
      'status' => 'paid',
      'payment_date' => $paymentDate,
    ]);
  }

  /**
   * Log a payment transaction for audit purposes.
   *
   * All gateway responses are logged regardless of success/failure status.
   * Logs are retained for a minimum of 7 years per Requirement 18.5.
   *
   * @param array $data Transaction data to log
   * @return void
   */
  public function logTransaction(array $data): void
  {
    $this->paymentRepository->logTransaction($data);
  }

  /**
   * Handle a successful payment.
   *
   * Marks the invoice as paid and records the successful transaction.
   * Enforces full payment only (amount must equal invoice amount).
   *
   * Requirement 18.2: Update invoice to paid, record transaction reference.
   * Requirement 18.6: Full payment only.
   *
   * @param int $invoiceId The invoice ID
   * @param string $gateway The payment gateway ('paypal' or 'stripe')
   * @param string $transactionId Gateway transaction reference
   * @param float $amount Amount paid
   * @return Result Success with payment details, or error
   */
  public function handleSuccess(int $invoiceId, string $gateway, string $transactionId, float $amount): Result
  {
    // Validate gateway
    $gateway = strtolower(trim($gateway));
    if (!in_array($gateway, self::VALID_GATEWAYS, true)) {
      return Result::error('INVALID_GATEWAY', 'Invalid payment gateway.');
    }

    // Find the invoice
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('INVOICE_NOT_FOUND', 'Invoice not found.');
    }

    // Check if invoice is already paid
    if ($invoice['status'] === 'paid') {
      return Result::error('ALREADY_PAID', 'This invoice has already been paid.');
    }

    // Validate full payment amount (Requirement 18.6)
    $invoiceAmount = (float) $invoice['amount'];
    if (abs($amount - $invoiceAmount) > 0.01) {
      return Result::error(
        'AMOUNT_MISMATCH',
        'Payment amount does not match invoice total. Full payment of the outstanding amount is required.'
      );
    }

    $transactionDate = date('Y-m-d H:i:s');

    // Log the successful transaction (Requirement 18.5)
    $this->logTransaction([
      'invoice_id' => $invoiceId,
      'gateway' => $gateway,
      'transaction_id' => $transactionId,
      'amount' => (string) $amount,
      'status' => 'completed',
      'transaction_date' => $transactionDate,
    ]);

    // Update invoice status to paid (Requirement 18.2)
    $this->invoiceRepository->updateStatus($invoiceId, 'paid', $transactionDate);

    return Result::success([
      'invoice_id' => $invoiceId,
      'transaction_id' => $transactionId,
      'gateway' => $gateway,
      'amount' => (string) $amount,
      'status' => 'paid',
      'payment_date' => $transactionDate,
    ]);
  }

  /**
   * Handle a failed payment.
   *
   * Logs the failed transaction for audit purposes but keeps the invoice
   * status as unpaid, allowing the client to retry.
   *
   * Requirement 18.3: Display failure reason, retain unpaid status, allow retry.
   * Requirement 18.5: Log all gateway responses.
   *
   * @param int $invoiceId The invoice ID
   * @param string $gateway The payment gateway ('paypal' or 'stripe')
   * @param string $transactionId Gateway transaction reference
   * @param float $amount Amount attempted
   * @param string $reason Failure reason category (e.g., declined, insufficient funds, timeout)
   * @return Result Error result with failure details
   */
  public function handleFailure(int $invoiceId, string $gateway, string $transactionId, float $amount, string $reason): Result
  {
    // Validate gateway
    $gateway = strtolower(trim($gateway));
    if (!in_array($gateway, self::VALID_GATEWAYS, true)) {
      return Result::error('INVALID_GATEWAY', 'Invalid payment gateway.');
    }

    // Find the invoice
    $invoice = $this->invoiceRepository->findById($invoiceId);

    if ($invoice === null) {
      return Result::error('INVOICE_NOT_FOUND', 'Invoice not found.');
    }

    $transactionDate = date('Y-m-d H:i:s');

    // Log the failed transaction for audit (Requirement 18.5)
    $this->logTransaction([
      'invoice_id' => $invoiceId,
      'gateway' => $gateway,
      'transaction_id' => $transactionId,
      'amount' => (string) $amount,
      'status' => 'failed',
      'transaction_date' => $transactionDate,
    ]);

    // Invoice remains unpaid - client can retry (Requirement 18.3)
    return Result::error(
      'PAYMENT_FAILED',
      'Payment failed: ' . $reason . '. Please retry or select a different gateway.'
    );
  }

  /**
   * Generate a gateway-specific redirect URL for payment.
   *
   * This is a placeholder for actual API integration. In production,
   * this would call the PayPal/Stripe API to create a payment session
   * and return the redirect URL.
   *
   * Requirement 18.1: Redirect to chosen gateway within 5 seconds.
   *
   * @param string $gateway The payment gateway ('paypal' or 'stripe')
   * @param float $amount The payment amount
   * @param int $invoiceId The invoice ID
   * @return string The gateway redirect URL
   */
  public function getPaymentUrl(string $gateway, float $amount, int $invoiceId): string
  {
    $gateway = strtolower(trim($gateway));
    return $this->buildRedirectUrl($gateway, $invoiceId, $amount);
  }

  /**
   * Build the redirect URL for a payment gateway.
   *
   * @param string $gateway The gateway name
   * @param int $invoiceId The invoice ID
   * @param string|float $amount The payment amount
   * @return string The redirect URL
   */
  private function buildRedirectUrl(string $gateway, int $invoiceId, string|float $amount): string
  {
    $template = $this->gatewayUrls[$gateway] ?? '';

    return str_replace(
      ['{invoice_id}', '{amount}'],
      [(string) $invoiceId, (string) $amount],
      $template
    );
  }
}
