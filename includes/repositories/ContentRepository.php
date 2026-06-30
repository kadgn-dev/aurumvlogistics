<?php

declare(strict_types=1);

namespace GOLS\Repositories;

use PDO;

/**
 * Repository for content_pages and faq_entries tables.
 *
 * Provides data access for admin-managed CMS content (homepage, pricing)
 * and FAQ entries displayed on public pages.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.4, 17.5
 */
class ContentRepository
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Get a content page record by its unique page key.
   *
   * Returns the record with JSON content decoded into an array,
   * or null if no record exists for the given key.
   *
   * @param string $pageKey The unique page identifier (e.g., "home", "pricing")
   * @return array|null The content page record with decoded content, or null
   */
  public function getByPageKey(string $pageKey): ?array
  {
    $stmt = $this->pdo->prepare(
      'SELECT id, page_key, content, updated_at, updated_by
       FROM content_pages
       WHERE page_key = :page_key'
    );
    $stmt->execute([':page_key' => $pageKey]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
      return null;
    }

    $row['content'] = json_decode($row['content'], true);

    return $row;
  }

  /**
   * Insert or update a content page record.
   *
   * If a record with the given page_key exists, it is updated.
   * Otherwise, a new record is created.
   *
   * @param string $pageKey The unique page identifier
   * @param array $content The content data to store (will be JSON encoded)
   * @param int $updatedBy The user ID of the admin making the change
   * @return bool True on success, false on failure
   */
  public function upsert(string $pageKey, array $content, int $updatedBy): bool
  {
    $encodedContent = json_encode($content);
    $now = date('Y-m-d H:i:s');

    // Check if record exists
    $checkStmt = $this->pdo->prepare(
      'SELECT id FROM content_pages WHERE page_key = :page_key'
    );
    $checkStmt->execute([':page_key' => $pageKey]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
      // Update existing record
      $stmt = $this->pdo->prepare(
        'UPDATE content_pages
         SET content = :content, updated_by = :updated_by, updated_at = :updated_at
         WHERE page_key = :page_key'
      );
      return $stmt->execute([
        ':page_key' => $pageKey,
        ':content' => $encodedContent,
        ':updated_by' => $updatedBy,
        ':updated_at' => $now,
      ]);
    }

    // Insert new record
    $stmt = $this->pdo->prepare(
      'INSERT INTO content_pages (page_key, content, updated_by, updated_at)
       VALUES (:page_key, :content, :updated_by, :updated_at)'
    );
    return $stmt->execute([
      ':page_key' => $pageKey,
      ':content' => $encodedContent,
      ':updated_by' => $updatedBy,
      ':updated_at' => $now,
    ]);
  }

  /**
   * Get all FAQ entries sorted by sort_order ascending.
   *
   * @return array List of FAQ entry records
   */
  public function getFaqEntries(): array
  {
    $stmt = $this->pdo->query(
      'SELECT id, question, answer, sort_order, created_at, updated_at
       FROM faq_entries
       ORDER BY sort_order ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Create a new FAQ entry.
   *
   * @param string $question The FAQ question (max 200 characters)
   * @param string $answer The FAQ answer (max 2000 characters)
   * @param int $sortOrder The display order (default 0)
   * @return int The ID of the newly created FAQ entry
   */
  public function createFaqEntry(string $question, string $answer, int $sortOrder = 0): int
  {
    $stmt = $this->pdo->prepare(
      'INSERT INTO faq_entries (question, answer, sort_order, created_at, updated_at)
       VALUES (:question, :answer, :sort_order, :created_at, :updated_at)'
    );

    $now = date('Y-m-d H:i:s');

    $stmt->execute([
      ':question' => $question,
      ':answer' => $answer,
      ':sort_order' => $sortOrder,
      ':created_at' => $now,
      ':updated_at' => $now,
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  /**
   * Update an existing FAQ entry's question and answer.
   *
   * @param int $id The FAQ entry ID
   * @param string $question The updated question
   * @param string $answer The updated answer
   * @return bool True if a row was updated, false otherwise
   */
  public function updateFaqEntry(int $id, string $question, string $answer): bool
  {
    $stmt = $this->pdo->prepare(
      'UPDATE faq_entries
       SET question = :question, answer = :answer, updated_at = :updated_at
       WHERE id = :id'
    );

    $stmt->execute([
      ':id' => $id,
      ':question' => $question,
      ':answer' => $answer,
      ':updated_at' => date('Y-m-d H:i:s'),
    ]);

    return $stmt->rowCount() > 0;
  }

  /**
   * Delete a FAQ entry by ID.
   *
   * @param int $id The FAQ entry ID to delete
   * @return bool True if a row was deleted, false otherwise
   */
  public function deleteFaqEntry(int $id): bool
  {
    $stmt = $this->pdo->prepare(
      'DELETE FROM faq_entries WHERE id = :id'
    );

    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
  }
}
