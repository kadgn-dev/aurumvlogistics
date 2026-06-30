<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testSuccessCreatesSuccessfulResult(): void
    {
        $result = Result::success();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->data);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->errors);
    }

    public function testSuccessWithDataPayload(): void
    {
        $data = ['user_id' => 42, 'email' => 'test@example.com'];
        $result = Result::success($data);

        $this->assertTrue($result->success);
        $this->assertSame($data, $result->data);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
        $this->assertNull($result->errors);
    }

    public function testErrorCreatesFailedResult(): void
    {
        $result = Result::error('AUTH_FAILED', 'Invalid credentials.');

        $this->assertFalse($result->success);
        $this->assertSame('AUTH_FAILED', $result->errorCode);
        $this->assertSame('Invalid credentials.', $result->errorMessage);
        $this->assertNull($result->data);
        $this->assertNull($result->errors);
    }

    public function testValidationErrorCreatesFailedResultWithFieldErrors(): void
    {
        $errors = [
            'email' => 'Email is required.',
            'password' => 'Password must be at least 8 characters.',
        ];
        $result = Result::validationError($errors);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
        $this->assertSame('One or more fields failed validation.', $result->errorMessage);
        $this->assertSame($errors, $result->errors);
        $this->assertNull($result->data);
    }
}
