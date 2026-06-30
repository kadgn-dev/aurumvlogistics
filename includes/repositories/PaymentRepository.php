<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;

/**
 * Aurum Vault Logistics Platform (AVL)
 * PaymentRepository - Data access for payment_transactions table
 *
 * Handles creation and retrieval of payment transaction records
 * for invoice payments via PayPal and Stripe gateways.
 *
 * Requirements: 18.2, 18.5
 */
class PaymentRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Create a new payment transaction record.
   *
   * @param array $data Associative array with keys:
   *  - invoice_id (int): The invoice this payment is for
   *  - gateway (string): Payment gateway ('paypal' or 'stripe')
   *  - transaction_id (string): Gateway-provided transaction identifier
   *  - amount (float|string): Payment amount
   *  - status (string): Transaction status (e.g., 'completed', 'failed', 'pending')
   *  - transaction_date (string): ISO datetime of the transaction
   * @return int The ID of the newly created payment transaction record
   */
  public function create(array $data): int
  {
    $stmt = $this->pdo->prepare(
      "INSERT INTO payment_transactions (invoice_id, gateway, transaction_id, amount, status, transaction_date)
       VALUES (:invoice_id, :gateway, :transaction_id, :amount, :status, :transaction_date)"
    );

    $stmt->execute([
      ':invoice_id'    => $data['invoice_id'],
      ':gateway'     => $data['gateway'],
      ':transaction_id'  => $data['transaction_id'],
      ':amount'      => $data['amount'],
      ':status'      => $data['status'],
      ':transaction_date' => $data['transaction_date'],
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Find all payment transactions for a given invoice.
   *
   * Returns transactions ordered by transaction_date descending
   * so the most recent transaction appears first.
   *
   * @param int $invoiceId The invoice ID to look up
   * @return array Array of transaction records (associative arrays)
   */
  public function findByInvoiceId(int $invoiceId): array
  {
    $stmt = $this->pdo->prepare(
      "SELECT id, invoice_id, gateway, transaction_id, amount, status, transaction_date, created_at
       FROM payment_transactions
       WHERE invoice_id = :invoice_id
       ORDER BY transaction_date DESC"
    );

    $stmt->execute([':invoice_id' => $invoiceId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Find the successful payment transaction for a given invoice.
   *
   * Returns the first transaction with status 'completed' for the invoice,
   * or null if no successful payment exists.
   *
   * @param int $invoiceId The invoice ID to look up
   * @return array|null The successful transaction record or null
   */
  public function findSuccessfulByInvoiceId(int $invoiceId): ?array
  {
    $stmt = $this->pdo->prepare(
      "SELECT id, invoice_id, gateway, transaction_id, amount, status, transaction_date, created_at
       FROM payment_transactions
       WHERE invoice_id = :invoice_id AND status = 'completed'
       ORDER BY transaction_date DESC
       LIMIT 1"
    );

    $stmt->execute([':invoice_id' => $invoiceId]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result !== false ? $result : null;
  }

  /**
   * Log a payment transaction for audit purposes.
   *
   * This is an alias for create(), used specifically for audit logging
   * of all gateway responses as required by Requirement 18.5.
   *
   * @param array $data Same format as create() method
   * @return void
   */
  public function logTransaction(array $data): void
  {
    $this->create($data);
  }
}
