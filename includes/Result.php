<?php

declare(strict_types=1);

namespace GOLS;

/**
 * Aurum Vault Logistics Platform (AVL)
 * Result Class - Standardized operation result wrapper
 *
 * Used by all services throughout the application to return
 * consistent success/error responses.
 */
class Result
{
  public bool $success;
  public ?string $errorCode;
  public ?string $errorMessage;
  public ?array $data;
  public ?array $errors;

  private function __construct(
    bool $success,
    ?array $data = null,
    ?string $errorCode = null,
    ?string $errorMessage = null,
    ?array $errors = null
  ) {
    $this->success = $success;
    $this->data = $data;
    $this->errorCode = $errorCode;
    $this->errorMessage = $errorMessage;
    $this->errors = $errors;
  }

  /**
   * Create a successful result with optional data payload.
   *
   * @param array $data Optional associative array of result data
   * @return self
   */
  public static function success(array $data = []): self
  {
    return new self(
      success: true,
      data: $data
    );
  }

  /**
   * Create an error result with a code and message.
   *
   * @param string $code Machine-readable error code
   * @param string $message Human-readable error message
   * @return self
   */
  public static function error(string $code, string $message): self
  {
    return new self(
      success: false,
      errorCode: $code,
      errorMessage: $message
    );
  }

  /**
   * Create a validation error result with field-specific errors.
   *
   * @param array $errors Associative array of field => error message
   * @return self
   */
  public static function validationError(array $errors): self
  {
    return new self(
      success: false,
      errorCode: 'VALIDATION_ERROR',
      errorMessage: 'One or more fields failed validation.',
      errors: $errors
    );
  }
}
