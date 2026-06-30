<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;

/**
 * Aurum Vault Logistics Platform (AVL)
 * User Repository - Data access layer for user records.
 *
 * All queries use PDO prepared statements to prevent SQL injection
 * as required by Requirement 16.3.
 */
class UserRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Find a user by their ID.
   *
   * @param int $id The user ID.
   * @return array|null The user record or null if not found.
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
  }

  /**
   * Find a user by their email address.
   *
   * @param string $email The email address to search for.
   * @return array|null The user record or null if not found.
   */
  public function findByEmail(string $email): ?array
  {
    $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
  }

  /**
   * Create a new user record.
   *
   * @param array $data Associative array with keys: name, email, phone, password_hash, role (optional), status (optional).
   * @return int The ID of the newly created user.
   */
  public function create(array $data): int
  {
    $stmt = $this->pdo->prepare(
      'INSERT INTO users (name, email, phone, password_hash, role, status, kyc_status)
       VALUES (:name, :email, :phone, :password_hash, :role, :status, :kyc_status)'
    );

    $stmt->execute([
      'name'     => $data['name'],
      'email'     => $data['email'],
      'phone'     => $data['phone'],
      'password_hash' => $data['password_hash'],
      'role'     => $data['role'] ?? 'client',
      'status'    => $data['status'] ?? 'pending',
      'kyc_status'  => $data['kyc_status'] ?? 'not_submitted',
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Update a user record with the given data.
   *
   * @param int  $id  The user ID to update.
   * @param array $data Associative array of column => value pairs to update.
   * @return bool True if the update affected at least one row.
   */
  public function update(int $id, array $data): bool
  {
    if (empty($data)) {
      return false;
    }

    $setClauses = [];
    $params = ['id' => $id];

    foreach ($data as $column => $value) {
      $setClauses[] = "{$column} = :{$column}";
      $params[$column] = $value;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
  }

  /**
   * Update a user's account status.
   *
   * @param int  $id   The user ID.
   * @param string $status The new status (pending, active, suspended).
   * @return bool True if the update affected at least one row.
   */
  public function updateStatus(int $id, string $status): bool
  {
    $stmt = $this->pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Update a user's KYC verification status.
   *
   * @param int  $id    The user ID.
   * @param string $kycStatus The new KYC status (not_submitted, pending_review, approved, rejected).
   * @return bool True if the update affected at least one row.
   */
  public function updateKycStatus(int $id, string $kycStatus): bool
  {
    $stmt = $this->pdo->prepare('UPDATE users SET kyc_status = :kyc_status WHERE id = :id');
    $stmt->execute(['kyc_status' => $kycStatus, 'id' => $id]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Search users by name or email with case-insensitive partial matching.
   *
   * @param string $term The search term to match against name or email.
   * @param int  $limit Maximum number of results (default 50).
   * @return array Array of matching user records.
   */
  public function search(string $term, int $limit = 50): array
  {
    $limit = max(1, min($limit, 50));
    $likeTerm = '%' . $term . '%';

    $stmt = $this->pdo->prepare(
      'SELECT * FROM users
       WHERE name LIKE :term_name OR email LIKE :term_email
       ORDER BY created_at DESC
       LIMIT :limit'
    );

    $stmt->bindValue('term_name', $likeTerm, PDO::PARAM_STR);
    $stmt->bindValue('term_email', $likeTerm, PDO::PARAM_STR);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get a paginated list of users sorted by creation date descending.
   *
   * @param int $page  The page number (1-based).
   * @param int $perPage Number of records per page (default 20).
   * @return array Associative array with 'data' (user records) and 'total' (total count).
   */
  public function getPaginated(int $page, int $perPage = 20): array
  {
    $perPage = max(1, $perPage);
    $offset = getPaginationOffset($page, $perPage);

    // Get total count
    $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM users');
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    // Get paginated data
    $stmt = $this->pdo->prepare(
      'SELECT * FROM users
       ORDER BY created_at DESC
       LIMIT :limit OFFSET :offset'
    );

    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
      'data' => $stmt->fetchAll(),
      'total' => $total,
    ];
  }

  /**
   * Find all users with a specific role.
   *
   * @param string $role The role to filter by (e.g., 'admin', 'client').
   * @return array Array of user records matching the role.
   */
  public function findByRole(string $role): array
  {
    $stmt = $this->pdo->prepare('SELECT * FROM users WHERE role = :role AND status != :excluded_status');
    $stmt->execute(['role' => $role, 'excluded_status' => 'suspended']);

    return $stmt->fetchAll();
  }

  /**
   * Check if an email address already exists in the system.
   *
   * @param string  $email     The email address to check.
   * @param int|null $excludeUserId Optional user ID to exclude (for profile updates).
   * @return bool True if the email exists (excluding the specified user).
   */
  public function emailExists(string $email, ?int $excludeUserId = null): bool
  {
    if ($excludeUserId !== null) {
      $stmt = $this->pdo->prepare(
        'SELECT COUNT(*) FROM users WHERE email = :email AND id != :exclude_id'
      );
      $stmt->execute(['email' => $email, 'exclude_id' => $excludeUserId]);
    } else {
      $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
      $stmt->execute(['email' => $email]);
    }

    return (int) $stmt->fetchColumn() > 0;
  }
}
