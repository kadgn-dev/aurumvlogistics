<?php

declare(strict_types=1);

namespace GOLS\Validators;

use GOLS\ValidationResult;

/**
 * Aurum Vault Logistics Platform (AVL)
 * InventoryValidator - Validates vault inventory item data
 *
 * Validates all fields for creating/updating vault inventory records.
 * Note: serial_number uniqueness and user_id existence checks are
 * performed at the service/repository layer, not here.
 */
class InventoryValidator
{
  private const VALID_GOLD_TYPES = ['bar', 'coin', 'grain', 'round'];
  private const MAX_WEIGHT = 999999.999;
  private const MIN_PURITY = 0.001;
  private const MAX_PURITY = 0.9999;
  private const MAX_SERIAL_NUMBER_LENGTH = 50;

  /**
   * Validate inventory item data.
   *
   * @param array $data Associative array with inventory fields
   * @return ValidationResult
   */
  public function validate(array $data): ValidationResult
  {
    $errors = [];

    $this->validateGoldType($data, $errors);
    $this->validateWeight($data, $errors);
    $this->validatePurity($data, $errors);
    $this->validateCarat($data, $errors);
    $this->validateSerialNumber($data, $errors);
    $this->validateVaultLocation($data, $errors);
    $this->validateUserId($data, $errors);
    $this->validateInsuranceStatus($data, $errors);
    $this->validateItemValue($data, $errors);
    $this->validateDateAcquired($data, $errors);

    if (empty($errors)) {
      return ValidationResult::success();
    }

    return ValidationResult::failure($errors);
  }

  private function validateGoldType(array $data, array &$errors): void
  {
    if (!isset($data['gold_type']) || $data['gold_type'] === '') {
      $errors['gold_type'] = 'Gold type is required.';
      return;
    }

    if (!in_array($data['gold_type'], self::VALID_GOLD_TYPES, true)) {
      $errors['gold_type'] = 'Gold type must be one of: bar, coin, grain, round.';
    }
  }

  private function validateWeight(array $data, array &$errors): void
  {
    if (!isset($data['weight']) || $data['weight'] === '') {
      $errors['weight'] = 'Weight is required.';
      return;
    }

    if (!is_numeric($data['weight'])) {
      $errors['weight'] = 'Weight must be a numeric value.';
      return;
    }

    $weight = (float) $data['weight'];

    if ($weight <= 0) {
      $errors['weight'] = 'Weight must be a positive value.';
      return;
    }

    if ($weight > self::MAX_WEIGHT) {
      $errors['weight'] = 'Weight must not exceed 999999.999.';
    }
  }

  private function validatePurity(array $data, array &$errors): void
  {
    if (!isset($data['purity']) || $data['purity'] === '') {
      $errors['purity'] = 'Purity is required.';
      return;
    }

    if (!is_numeric($data['purity'])) {
      $errors['purity'] = 'Purity must be a numeric value.';
      return;
    }

    $purity = (float) $data['purity'];

    if ($purity < self::MIN_PURITY || $purity > self::MAX_PURITY) {
      $errors['purity'] = 'Purity must be between 0.001 and 0.9999.';
    }
  }

  private function validateSerialNumber(array $data, array &$errors): void
  {
    if (!isset($data['serial_number']) || $data['serial_number'] === '') {
      $errors['serial_number'] = 'Serial number is required.';
      return;
    }

    $serialNumber = $data['serial_number'];

    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $serialNumber)) {
      $errors['serial_number'] = 'Serial number must be alphanumeric (dashes allowed).';
      return;
    }

    if (strlen($serialNumber) > self::MAX_SERIAL_NUMBER_LENGTH) {
      $errors['serial_number'] = 'Serial number must not exceed 50 characters.';
    }
  }

  private function validateVaultLocation(array $data, array &$errors): void
  {
    if (!isset($data['vault_location']) || trim((string) $data['vault_location']) === '') {
      $errors['vault_location'] = 'Vault location is required.';
    }
  }

  private function validateUserId(array $data, array &$errors): void
  {
    if (!isset($data['user_id']) || $data['user_id'] === '') {
      $errors['user_id'] = 'User ID is required.';
      return;
    }

    if (!is_numeric($data['user_id']) || (int) $data['user_id'] <= 0 || (int) $data['user_id'] != $data['user_id']) {
      $errors['user_id'] = 'User ID must be a positive integer.';
    }
  }

  private function validateInsuranceStatus(array $data, array &$errors): void
  {
    if (!isset($data['insurance_status']) && !array_key_exists('insurance_status', $data)) {
      $errors['insurance_status'] = 'Insurance status is required.';
      return;
    }

    $value = $data['insurance_status'];

    if ($value === null || ($value !== 0 && $value !== 1 && $value !== '0' && $value !== '1' && $value !== true && $value !== false)) {
      $errors['insurance_status'] = 'Insurance status must be a boolean value (0 or 1).';
    }
  }

  private function validateCarat(array $data, array &$errors): void
  {
    if (!isset($data['carat']) || $data['carat'] === '') {
      $errors['carat'] = 'Carat is required.';
      return;
    }

    if (!is_numeric($data['carat'])) {
      $errors['carat'] = 'Carat must be a numeric value.';
      return;
    }

    $carat = (float) $data['carat'];

    if ($carat < 1 || $carat > 24) {
      $errors['carat'] = 'Carat must be between 1 and 24.';
    }
  }

  private function validateItemValue(array $data, array &$errors): void
  {
    if (!isset($data['item_value']) || $data['item_value'] === '') {
      $errors['item_value'] = 'Item value is required.';
      return;
    }

    if (!is_numeric($data['item_value'])) {
      $errors['item_value'] = 'Item value must be a numeric value.';
      return;
    }

    $value = (float) $data['item_value'];

    if ($value < 0) {
      $errors['item_value'] = 'Item value cannot be negative.';
      return;
    }

    if ($value > 999999999999.99) {
      $errors['item_value'] = 'Item value exceeds maximum allowed.';
    }
  }

  private function validateDateAcquired(array $data, array &$errors): void
  {
    // date_acquired is required
    if (!isset($data['date_acquired']) || $data['date_acquired'] === '') {
      $errors['date_acquired'] = 'Date acquired is required.';
      return;
    }

    $date = $data['date_acquired'];

    // Validate format YYYY-MM-DD
    $parsed = \DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
      $errors['date_acquired'] = 'Date acquired must be a valid date (YYYY-MM-DD).';
      return;
    }

    // Must not be in the future
    $today = new \DateTime('today');
    if ($parsed > $today) {
      $errors['date_acquired'] = 'Date acquired cannot be in the future.';
    }
  }
}
