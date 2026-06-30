<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function testSuccessCreatesValidResult(): void
    {
        $result = ValidationResult::success();

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function testFailureCreatesInvalidResultWithErrors(): void
    {
        $errors = [
            'name' => 'Name is required.',
            'phone' => 'Phone must be 10-15 digits.',
        ];
        $result = ValidationResult::failure($errors);

        $this->assertFalse($result->isValid);
        $this->assertSame($errors, $result->errors);
    }

    public function testFailureWithSingleError(): void
    {
        $errors = ['email' => 'Email format is invalid.'];
        $result = ValidationResult::failure($errors);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertSame('Email format is invalid.', $result->errors['email']);
    }
}
