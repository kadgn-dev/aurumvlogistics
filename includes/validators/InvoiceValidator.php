<?php

declare(strict_types=1);

namespace GOLS\Validators;

use GOLS\ValidationResult;

/**
 * Aurum Vault Logistics Platform (AVL)
 * InvoiceValidator - Validates invoice creation data
 *
 * Validates user_id, amount, and description fields for invoice creation.
 * Note: user_id existence check is handled at the service layer.
 */
class InvoiceValidator
{
  private const AMOUNT_MIN = 0.01;
  private const AMOUNT_MAX = 999999999.99;
  private const DESCRIPTION_MAX_LENGTH = 500;

  /**
   * Validate invoice creation fields.
   *
   * @param array $data Associative array with keys: user_id, amount, description
   * @return ValidationResult
   */
  public function validate(array $data): ValidationResult
  {
    $errors = [];

    $this->validateUserId($data, $errors);
    $this->validateAmount($data, $errors);
    $this->validateDescription($data, $errors);

    if (!empty($errors)) {
      return ValidationResult::failure($errors);
    }

    return ValidationResult::success();
  }

  /**
   * Validate user_id field: required, positive integer.
   */
  private function validateUserId(array $data, array &$errors): void
  {
    if (!isset($data['user_id']) || $data['user_id'] === '') {
      $errors['user_id'] = 'User ID is required.';
      return;
    }

    $userId = $data['user_id'];

    if (!is_numeric($userId) || (int) $userId != $userId || (int) $userId <= 0) {
      $errors['user_id'] = 'User ID must be a positive integer.';
    }
  }

  /**
   * Validate amount field: required, numeric, between 0.01 and 999999999.99.
   */
  private function validateAmount(array $data, array &$errors): void
  {
    if (!isset($data['amount']) || $data['amount'] === '') {
      $errors['amount'] = 'Amount is required.';
      return;
    }

    $amount = $data['amount'];

    if (!is_numeric($amount)) {
      $errors['amount'] = 'Amount must be a numeric value.';
      return;
    }

    $amountFloat = (float) $amount;

    if ($amountFloat < self::AMOUNT_MIN || $amountFloat > self::AMOUNT_MAX) {
      $errors['amount'] = 'Amount must be between 0.01 and 999999999.99.';
    }
  }

  /**
   * Validate description field: required, non-empty, max 500 characters.
   */
  private function validateDescription(array $data, array &$errors): void
  {
    if (!isset($data['description']) || $data['description'] === '') {
      $errors['description'] = 'Description is required.';
      return;
    }

    $description = $data['description'];

    if (!is_string($description)) {
      $errors['description'] = 'Description must be a string.';
      return;
    }

    if (mb_strlen($description, 'UTF-8') > self::DESCRIPTION_MAX_LENGTH) {
      $errors['description'] = 'Description must not exceed 500 characters.';
    }
  }
}
