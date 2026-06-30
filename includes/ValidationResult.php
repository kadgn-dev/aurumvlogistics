<?php

declare(strict_types=1);

namespace GOLS;

/**
 * Aurum Vault Logistics Platform (AVL)
 * ValidationResult Class - Standardized validation outcome
 *
 * Used by all validators to return consistent validation results
 * with field-specific error messages.
 */
class ValidationResult
{
  public bool $isValid;

  /** @var array<string, string> Field name => error message */
  public array $errors;

  private function __construct(bool $isValid, array $errors = [])
  {
    $this->isValid = $isValid;
    $this->errors = $errors;
  }

  /**
   * Create a successful validation result (no errors).
   *
   * @return self
   */
  public static function success(): self
  {
    return new self(isValid: true);
  }

  /**
   * Create a failed validation result with field-specific errors.
   *
   * @param array<string, string> $errors Associative array of field => error message
   * @return self
   */
  public static function failure(array $errors): self
  {
    return new self(isValid: false, errors: $errors);
  }
}
