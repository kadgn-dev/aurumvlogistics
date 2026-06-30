<?php

declare(strict_types=1);

namespace GOLS\Validators;

use GOLS\ValidationResult;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ShipmentValidator - Validates shipment request structural/format data.
 *
 * Handles address field presence, inventory_items array requirement,
 * and insurance_selected boolean check. Ownership and conflict
 * validation are handled at the service layer.
 */
class ShipmentValidator
{
  /**
   * Validate a shipment request payload.
   *
   * @param array $data Shipment request data
   * @return ValidationResult
   */
  public function validate(array $data): ValidationResult
  {
    $errors = [];

    // Address fields: required, non-empty strings
    $addressFields = ['street', 'city', 'state_province', 'postal_code', 'country'];

    foreach ($addressFields as $field) {
      if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
      }
    }

    // inventory_items: required, must be a non-empty array
    if (!isset($data['inventory_items'])) {
      $errors['inventory_items'] = 'At least one inventory item is required.';
    } elseif (!is_array($data['inventory_items'])) {
      $errors['inventory_items'] = 'Inventory items must be an array.';
    } elseif (count($data['inventory_items']) === 0) {
      $errors['inventory_items'] = 'At least one inventory item is required.';
    }

    // insurance_selected: required, boolean (0 or 1)
    if (!isset($data['insurance_selected']) && !array_key_exists('insurance_selected', $data)) {
      $errors['insurance_selected'] = 'Insurance selection is required.';
    } elseif (
      $data['insurance_selected'] !== 0
      && $data['insurance_selected'] !== 1
      && $data['insurance_selected'] !== true
      && $data['insurance_selected'] !== false
    ) {
      $errors['insurance_selected'] = 'Insurance selection must be a boolean value.';
    }

    if (count($errors) > 0) {
      return ValidationResult::failure($errors);
    }

    return ValidationResult::success();
  }
}
