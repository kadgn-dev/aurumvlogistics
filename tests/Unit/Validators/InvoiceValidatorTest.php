<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Validators;

use GOLS\ValidationResult;
use GOLS\Validators\InvoiceValidator;
use PHPUnit\Framework\TestCase;

class InvoiceValidatorTest extends TestCase
{
    private InvoiceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InvoiceValidator();
    }

    public function testValidDataReturnsSuccess(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 100.00,
            'description' => 'Storage fee for January 2024',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function testValidDataWithStringNumericValues(): void
    {
        $data = [
            'user_id' => '5',
            'amount' => '250.50',
            'description' => 'Shipping insurance premium',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    // --- user_id validation ---

    public function testMissingUserIdReturnsError(): void
    {
        $data = [
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID is required.', $result->errors['user_id']);
    }

    public function testEmptyUserIdReturnsError(): void
    {
        $data = [
            'user_id' => '',
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID is required.', $result->errors['user_id']);
    }

    public function testNegativeUserIdReturnsError(): void
    {
        $data = [
            'user_id' => -1,
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    public function testZeroUserIdReturnsError(): void
    {
        $data = [
            'user_id' => 0,
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    public function testNonIntegerUserIdReturnsError(): void
    {
        $data = [
            'user_id' => 3.5,
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    public function testNonNumericUserIdReturnsError(): void
    {
        $data = [
            'user_id' => 'abc',
            'amount' => 100.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    // --- amount validation ---

    public function testMissingAmountReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertSame('Amount is required.', $result->errors['amount']);
    }

    public function testEmptyAmountReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => '',
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertSame('Amount is required.', $result->errors['amount']);
    }

    public function testNonNumericAmountReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 'not-a-number',
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertSame('Amount must be a numeric value.', $result->errors['amount']);
    }

    public function testAmountBelowMinimumReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 0.001,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertSame('Amount must be between 0.01 and 999999999.99.', $result->errors['amount']);
    }

    public function testAmountAtZeroReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 0,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertSame('Amount must be between 0.01 and 999999999.99.', $result->errors['amount']);
    }

    public function testAmountAboveMaximumReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 1000000000.00,
            'description' => 'Test invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertSame('Amount must be between 0.01 and 999999999.99.', $result->errors['amount']);
    }

    public function testAmountAtMinimumBoundaryIsValid(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 0.01,
            'description' => 'Minimum amount invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testAmountAtMaximumBoundaryIsValid(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 999999999.99,
            'description' => 'Maximum amount invoice',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    // --- description validation ---

    public function testMissingDescriptionReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 100.00,
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('description', $result->errors);
        $this->assertSame('Description is required.', $result->errors['description']);
    }

    public function testEmptyDescriptionReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 100.00,
            'description' => '',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('description', $result->errors);
        $this->assertSame('Description is required.', $result->errors['description']);
    }

    public function testDescriptionExceeding500CharsReturnsError(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 100.00,
            'description' => str_repeat('a', 501),
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('description', $result->errors);
        $this->assertSame('Description must not exceed 500 characters.', $result->errors['description']);
    }

    public function testDescriptionAtExactly500CharsIsValid(): void
    {
        $data = [
            'user_id' => 1,
            'amount' => 100.00,
            'description' => str_repeat('b', 500),
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    // --- multiple errors ---

    public function testMultipleInvalidFieldsReturnAllErrors(): void
    {
        $data = [
            'user_id' => -1,
            'amount' => 'invalid',
            'description' => '',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertArrayHasKey('description', $result->errors);
    }

    public function testAllFieldsMissingReturnsAllErrors(): void
    {
        $data = [];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertArrayHasKey('amount', $result->errors);
        $this->assertArrayHasKey('description', $result->errors);
    }
}
