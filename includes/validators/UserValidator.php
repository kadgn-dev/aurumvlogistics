<?php

declare(strict_types=1);

namespace GOLS\Validators;

use GOLS\ValidationResult;

/**
 * Aurum Vault Logistics Platform (AVL)
 * UserValidator - Validates user registration, profile update, and password change data.
 *
 * Validation rules:
 * - name: required, 2-100 characters
 * - email: required, valid email format (filter_var FILTER_VALIDATE_EMAIL)
 * - phone: required, 10-15 digits only
 * - password: required, 8-72 characters, at least 1 uppercase, 1 lowercase, 1 digit, 1 special char
 *
 * Email uniqueness is handled by the service layer, not this validator.
 */
class UserValidator
{
  /**
   * Validate all registration fields.
   *
   * @param array $data Expected keys: name, email, phone, password
   * @return ValidationResult
   */
  public function validateRegistration(array $data): ValidationResult
  {
    $errors = [];

    $errors = array_merge($errors, $this->validateName($data));
    $errors = array_merge($errors, $this->validateEmail($data));
    $errors = array_merge($errors, $this->validatePhone($data));
    $errors = array_merge($errors, $this->validatePassword($data));

    if (!empty($errors)) {
      return ValidationResult::failure($errors);
    }

    return ValidationResult::success();
  }

  /**
   * Validate profile update fields (name, phone, email).
   *
   * @param array $data Expected keys: name, email, phone
   * @param int $currentUserId The ID of the user being updated (reserved for service-layer uniqueness checks)
   * @return ValidationResult
   */
  public function validateProfileUpdate(array $data, int $currentUserId): ValidationResult
  {
    $errors = [];

    $errors = array_merge($errors, $this->validateName($data));
    $errors = array_merge($errors, $this->validateEmail($data));
    $errors = array_merge($errors, $this->validatePhone($data));

    if (!empty($errors)) {
      return ValidationResult::failure($errors);
    }

    return ValidationResult::success();
  }

  /**
   * Validate password change data.
   *
   * @param array $data Expected keys: password (the new password)
   * @return ValidationResult
   */
  public function validatePasswordChange(array $data): ValidationResult
  {
    $errors = $this->validatePassword($data);

    if (!empty($errors)) {
      return ValidationResult::failure($errors);
    }

    return ValidationResult::success();
  }

  /**
   * Validate the name field.
   *
   * @param array $data
   * @return array<string, string> Errors keyed by field name
   */
  private function validateName(array $data): array
  {
    if (!isset($data['name']) || trim((string) $data['name']) === '') {
      return ['name' => 'Name is required.'];
    }

    $name = trim((string) $data['name']);
    $length = mb_strlen($name, 'UTF-8');

    if ($length < 2 || $length > 100) {
      return ['name' => 'Name must be between 2 and 100 characters.'];
    }

    return [];
  }

  /**
   * Validate the email field.
   *
   * @param array $data
   * @return array<string, string> Errors keyed by field name
   */
  private function validateEmail(array $data): array
  {
    if (!isset($data['email']) || trim((string) $data['email']) === '') {
      return ['email' => 'Email is required.'];
    }

    $email = trim((string) $data['email']);

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      return ['email' => 'Email format is invalid.'];
    }

    return [];
  }

  /**
   * Validate the phone field.
   *
   * @param array $data
   * @return array<string, string> Errors keyed by field name
   */
  private function validatePhone(array $data): array
  {
    if (!isset($data['phone']) || trim((string) $data['phone']) === '') {
      return ['phone' => 'Phone is required.'];
    }

    $phone = trim((string) $data['phone']);

    // Phone must be digits only, 10-15 characters
    if (!preg_match('/^\d{10,15}$/', $phone)) {
      return ['phone' => 'Phone must be 10 to 15 digits.'];
    }

    return [];
  }

  /**
   * Validate the password field.
   *
   * @param array $data
   * @return array<string, string> Errors keyed by field name
   */
  private function validatePassword(array $data): array
  {
    if (!isset($data['password']) || (string) $data['password'] === '') {
      return ['password' => 'Password is required.'];
    }

    $password = (string) $data['password'];
    $length = mb_strlen($password, 'UTF-8');

    if ($length < 8 || $length > 72) {
      return ['password' => 'Password must be between 8 and 72 characters.'];
    }

    if (!preg_match('/[A-Z]/', $password)) {
      return ['password' => 'Password must contain at least one uppercase letter.'];
    }

    if (!preg_match('/[a-z]/', $password)) {
      return ['password' => 'Password must contain at least one lowercase letter.'];
    }

    if (!preg_match('/\d/', $password)) {
      return ['password' => 'Password must contain at least one digit.'];
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
      return ['password' => 'Password must contain at least one special character.'];
    }

    return [];
  }
}
