<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\ContentRepository;
use GOLS\Result;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ContentService - Business logic for admin content management
 *
 * Handles homepage content, FAQ entries, and pricing content management.
 * All write operations are restricted to admin users.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7
 */
class ContentService
{
  private ContentRepository $repository;

  public function __construct(ContentRepository $repository)
  {
    $this->repository = $repository;
  }

  /**
   * Get homepage content from content_pages table.
   *
   * @return array The homepage content data, or empty array if not set
   */
  public function getHomepageContent(): array
  {
    $page = $this->repository->getByPageKey('home');

    if ($page === null) {
      return [];
    }

    return $page['content'] ?? [];
  }

  /**
   * Update homepage content (admin only).
   *
   * Validates that required fields are present, then upserts the content.
   *
   * @param array $data The homepage content data (hero_text, service_descriptions, security_highlights)
   * @param int $adminId The admin user ID performing the update
   * @return Result Success or validation error
   */
  public function updateHomepageContent(array $data, int $adminId): Result
  {
    // Validate required fields
    $errors = [];

    if (empty($data['hero_text'])) {
      $errors['hero_text'] = 'Hero text is required.';
    }

    if (empty($data['service_descriptions'])) {
      $errors['service_descriptions'] = 'Service descriptions are required.';
    }

    if (empty($data['security_highlights'])) {
      $errors['security_highlights'] = 'Security highlights are required.';
    }

    if (!empty($errors)) {
      return Result::validationError($errors);
    }

    $success = $this->repository->upsert('home', $data, $adminId);

    if (!$success) {
      return Result::error('UPDATE_FAILED', 'Failed to update homepage content.');
    }

    return Result::success(['page_key' => 'home']);
  }

  /**
   * Get all FAQ entries sorted by sort_order.
   *
   * @return array List of FAQ entry records
   */
  public function getFaqEntries(): array
  {
    return $this->repository->getFaqEntries();
  }

  /**
   * Add a new FAQ entry.
   *
   * Validates question (max 200 chars) and answer (max 2000 chars) lengths.
   *
   * @param string $question The FAQ question
   * @param string $answer The FAQ answer
   * @param int $sortOrder The display order (default 0)
   * @return Result Success with entry ID or validation error
   */
  public function addFaqEntry(string $question, string $answer, int $sortOrder = 0): Result
  {
    $errors = [];

    if (empty(trim($question))) {
      $errors['question'] = 'Question is required.';
    } elseif (mb_strlen($question) > 200) {
      $errors['question'] = 'Question must not exceed 200 characters.';
    }

    if (empty(trim($answer))) {
      $errors['answer'] = 'Answer is required.';
    } elseif (mb_strlen($answer) > 2000) {
      $errors['answer'] = 'Answer must not exceed 2000 characters.';
    }

    if (!empty($errors)) {
      return Result::validationError($errors);
    }

    $entryId = $this->repository->createFaqEntry($question, $answer, $sortOrder);

    return Result::success(['entry_id' => $entryId]);
  }

  /**
   * Update an existing FAQ entry.
   *
   * Validates question (max 200 chars) and answer (max 2000 chars) lengths.
   *
   * @param int $id The FAQ entry ID
   * @param string $question The updated question
   * @param string $answer The updated answer
   * @return Result Success or validation/not-found error
   */
  public function updateFaqEntry(int $id, string $question, string $answer): Result
  {
    $errors = [];

    if (empty(trim($question))) {
      $errors['question'] = 'Question is required.';
    } elseif (mb_strlen($question) > 200) {
      $errors['question'] = 'Question must not exceed 200 characters.';
    }

    if (empty(trim($answer))) {
      $errors['answer'] = 'Answer is required.';
    } elseif (mb_strlen($answer) > 2000) {
      $errors['answer'] = 'Answer must not exceed 2000 characters.';
    }

    if (!empty($errors)) {
      return Result::validationError($errors);
    }

    $updated = $this->repository->updateFaqEntry($id, $question, $answer);

    if (!$updated) {
      return Result::error('ENTRY_NOT_FOUND', 'FAQ entry not found.');
    }

    return Result::success(['entry_id' => $id]);
  }

  /**
   * Delete a FAQ entry.
   *
   * @param int $id The FAQ entry ID to delete
   * @return Result Success or not-found error
   */
  public function deleteFaqEntry(int $id): Result
  {
    $deleted = $this->repository->deleteFaqEntry($id);

    if (!$deleted) {
      return Result::error('ENTRY_NOT_FOUND', 'FAQ entry not found.');
    }

    return Result::success(['entry_id' => $id]);
  }

  /**
   * Get pricing content from content_pages table.
   *
   * @return array The pricing content data, or empty array if not set
   */
  public function getPricingContent(): array
  {
    $page = $this->repository->getByPageKey('pricing');

    if ($page === null) {
      return [];
    }

    return $page['content'] ?? [];
  }

  /**
   * Update pricing content (admin only).
   *
   * Validates that required fields are present, then upserts the content.
   *
   * @param array $data The pricing content data (service_names, prices, plan_descriptions)
   * @param int $adminId The admin user ID performing the update
   * @return Result Success or validation error
   */
  public function updatePricingContent(array $data, int $adminId): Result
  {
    // Validate required fields
    $errors = [];

    if (empty($data['service_names'])) {
      $errors['service_names'] = 'Service names are required.';
    }

    if (empty($data['prices'])) {
      $errors['prices'] = 'Prices are required.';
    }

    if (empty($data['plan_descriptions'])) {
      $errors['plan_descriptions'] = 'Plan descriptions are required.';
    }

    if (!empty($errors)) {
      return Result::validationError($errors);
    }

    $success = $this->repository->upsert('pricing', $data, $adminId);

    if (!$success) {
      return Result::error('UPDATE_FAILED', 'Failed to update pricing content.');
    }

    return Result::success(['page_key' => 'pricing']);
  }
}
