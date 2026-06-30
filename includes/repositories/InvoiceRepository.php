<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;

/**
 * Repository for invoice data access operations.
 *
 * Handles CRUD operations for invoices including atomic invoice number
 * generation using the invoice_sequence table with row-level locking.
 *
 * Requirements: 9.1, 10.1, 10.3
 */
class InvoiceRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Find an invoice by its ID.
   *
   * @param int $id Invoice ID
   * @return array|null Invoice record or null if not found
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare(
      'SELECT id, user_id, invoice_number, amount, description, billing_period_start, billing_period_end, status, payment_date, created_at
       FROM invoices
       WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
  }

  /**
   * Find invoices by user ID with pagination, sorted by created_at DESC.
   *
   * @param int $userId User ID
   * @param int $page Page number (1-based)
   * @param int $perPage Number of records per page
   * @return array{data: array, total: int} Paginated result with data and total count
   */
  public function findByUserId(int $userId, int $page, int $perPage = 20): array
  {
    $offset = ($page - 1) * $perPage;

    // Get total count
    $countStmt = $this->pdo->prepare(
      'SELECT COUNT(*) AS total FROM invoices WHERE user_id = :user_id'
    );
    $countStmt->execute([':user_id' => $userId]);
    $total = (int) $countStmt->fetchColumn();

    // Get paginated data
    $stmt = $this->pdo->prepare(
      'SELECT id, user_id, invoice_number, amount, description, billing_period_start, billing_period_end, status, payment_date, created_at
       FROM invoices
       WHERE user_id = :user_id
       ORDER BY created_at DESC
       LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
      'data' => $data,
      'total' => $total,
    ];
  }

  /**
   * Create a new invoice with an auto-generated invoice number.
   *
   * @param array $data Invoice data: user_id, amount, description
   * @return int The newly created invoice ID
   */
  public function create(array $data): int
  {
    $invoiceNumber = $this->generateInvoiceNumber();

    $stmt = $this->pdo->prepare(
      'INSERT INTO invoices (user_id, invoice_number, amount, description, billing_period_start, billing_period_end, status, created_at)
       VALUES (:user_id, :invoice_number, :amount, :description, :billing_period_start, :billing_period_end, :status, :created_at)'
    );
    $stmt->execute([
      ':user_id' => $data['user_id'],
      ':invoice_number' => $invoiceNumber,
      ':amount' => $data['amount'],
      ':description' => $data['description'],
      ':billing_period_start' => !empty($data['billing_period_start']) ? $data['billing_period_start'] : null,
      ':billing_period_end' => !empty($data['billing_period_end']) ? $data['billing_period_end'] : null,
      ':status' => 'unpaid',
      ':created_at' => date('Y-m-d H:i:s'),
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Update the status of an invoice.
   *
   * @param int $id Invoice ID
   * @param string $status New status ('unpaid' or 'paid')
   * @param string|null $paymentDate Payment date (Y-m-d H:i:s) when marking as paid
   * @return bool True if the update affected a row
   */
  public function updateStatus(int $id, string $status, ?string $paymentDate = null): bool
  {
    $stmt = $this->pdo->prepare(
      'UPDATE invoices
       SET status = :status, payment_date = :payment_date
       WHERE id = :id'
    );
    $stmt->execute([
      ':status' => $status,
      ':payment_date' => $paymentDate,
      ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Generate a unique invoice number atomically using the invoice_sequence table.
   *
   * Uses INSERT ON DUPLICATE KEY UPDATE for atomic increment with row-level locking.
   * Format: INV-{YYYY}-{NNNNN}
   *
   * @return string Generated invoice number (e.g., INV-2024-00001)
   */
  public function generateInvoiceNumber(): string
  {
    $year = (int) date('Y');

    $this->pdo->beginTransaction();

    try {
      $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

      if ($driver === 'sqlite') {
        // SQLite-compatible: INSERT OR IGNORE + UPDATE
        $stmt = $this->pdo->prepare(
          'INSERT OR IGNORE INTO invoice_sequence (year, last_number) VALUES (:year, 0)'
        );
        $stmt->execute([':year' => $year]);

        $stmt = $this->pdo->prepare(
          'UPDATE invoice_sequence SET last_number = last_number + 1 WHERE year = :year'
        );
        $stmt->execute([':year' => $year]);
      } else {
        // MySQL: atomic INSERT ON DUPLICATE KEY UPDATE with row-level locking
        $stmt = $this->pdo->prepare(
          'INSERT INTO invoice_sequence (year, last_number) VALUES (:year, 1)
           ON DUPLICATE KEY UPDATE last_number = last_number + 1'
        );
        $stmt->execute([':year' => $year]);
      }

      // Retrieve the current sequence number
      $stmt = $this->pdo->prepare(
        'SELECT last_number FROM invoice_sequence WHERE year = :year'
      );
      $stmt->execute([':year' => $year]);
      $number = (int) $stmt->fetchColumn();

      $this->pdo->commit();

      return sprintf('INV-%04d-%05d', $year, $number);
    } catch (\Exception $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  /**
   * Get all invoices with pagination (for admin view).
   *
   * @param int $page Page number (1-based)
   * @param int $perPage Number of records per page
   * @return array{data: array, total: int} Paginated result with data and total count
   */
  public function getAllPaginated(int $page, int $perPage = 20): array
  {
    $offset = ($page - 1) * $perPage;

    // Get total count
    $countStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM invoices');
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    // Get paginated data
    $stmt = $this->pdo->prepare(
      'SELECT id, user_id, invoice_number, amount, description, billing_period_start, billing_period_end, status, payment_date, created_at
       FROM invoices
       ORDER BY created_at DESC
       LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
      'data' => $data,
      'total' => $total,
    ];
  }
}
