<?php

declare(strict_types=1);

namespace GOLS\Validators;

use GOLS\ValidationResult;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ContactValidator - Validates contact form submissions
 *
 * Ensures all required fields are present, non-empty, and within
 * acceptable length limits. Email must be a valid format.
 */
class ContactValidator
{
  /**
   * Validate contact form data.
   *
   * @param array $data Associative array with keys: name, email, subject, message
   * @return ValidationResult
   */
  public function validate(array $data): ValidationResult
  {
    $errors = [];

    // Name validation
    if (!isset($data['name']) || trim((string) $data['name']) === '') {
      $errors['name'] = 'Name is required.';
    } elseif (mb_strlen($data['name']) > 100) {
      $errors['name'] = 'Name must not exceed 100 characters.';
    }

    // Email validation
    if (!isset($data['email']) || trim((string) $data['email']) === '') {
      $errors['email'] = 'Email is required.';
    } elseif (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
      $errors['email'] = 'Email format is invalid.';
    }

    // Subject validation
    if (!isset($data['subject']) || trim((string) $data['subject']) === '') {
      $errors['subject'] = 'Subject is required.';
    } elseif (mb_strlen($data['subject']) > 200) {
      $errors['subject'] = 'Subject must not exceed 200 characters.';
    }

    // Message validation
    if (!isset($data['message']) || trim((string) $data['message']) === '') {
      $errors['message'] = 'Message is required.';
    } elseif (mb_strlen($data['message']) > 5000) {
      $errors['message'] = 'Message must not exceed 5000 characters.';
    }

    if (empty($errors)) {
      return ValidationResult::success();
    }

    return ValidationResult::failure($errors);
  }
}
