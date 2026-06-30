<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Validators;

use GOLS\Validators\UserValidator;
use PHPUnit\Framework\TestCase;

class UserValidatorTest extends TestCase
{
    private UserValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UserValidator();
    }

    // ─── Registration: Valid Data ────────────────────────────────────────

    public function testValidRegistrationReturnsSuccess(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    // ─── Registration: Name Validation ───────────────────────────────────

    public function testRegistrationFailsWhenNameMissing(): void
    {
        $data = [
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    public function testRegistrationFailsWhenNameEmpty(): void
    {
        $data = [
            'name' => '',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    public function testRegistrationFailsWhenNameTooShort(): void
    {
        $data = [
            'name' => 'A',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    public function testRegistrationFailsWhenNameTooLong(): void
    {
        $data = [
            'name' => str_repeat('A', 101),
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    public function testRegistrationAcceptsNameAtMinBoundary(): void
    {
        $data = [
            'name' => 'Jo',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
    }

    public function testRegistrationAcceptsNameAtMaxBoundary(): void
    {
        $data = [
            'name' => str_repeat('A', 100),
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
    }

    // ─── Registration: Email Validation ──────────────────────────────────

    public function testRegistrationFailsWhenEmailMissing(): void
    {
        $data = [
            'name' => 'John Doe',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    public function testRegistrationFailsWhenEmailEmpty(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => '',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    public function testRegistrationFailsWhenEmailInvalid(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    public function testRegistrationFailsWhenEmailMissingDomain(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'user@',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    // ─── Registration: Phone Validation ──────────────────────────────────

    public function testRegistrationFailsWhenPhoneMissing(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('phone', $result->errors);
    }

    public function testRegistrationFailsWhenPhoneTooShort(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456789',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('phone', $result->errors);
    }

    public function testRegistrationFailsWhenPhoneTooLong(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890123456',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('phone', $result->errors);
    }

    public function testRegistrationFailsWhenPhoneContainsLetters(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '12345abcde',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('phone', $result->errors);
    }

    public function testRegistrationFailsWhenPhoneContainsSpecialChars(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1-234-5678',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('phone', $result->errors);
    }

    public function testRegistrationAcceptsPhoneAtMinBoundary(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
    }

    public function testRegistrationAcceptsPhoneAtMaxBoundary(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456789012345',
            'password' => 'Str0ng!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
    }

    // ─── Registration: Password Validation ───────────────────────────────

    public function testRegistrationFailsWhenPasswordMissing(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
    }

    public function testRegistrationFailsWhenPasswordTooShort(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Ab1!xyz',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
    }

    public function testRegistrationFailsWhenPasswordTooLong(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => str_repeat('Aa1!', 19), // 76 chars
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
    }

    public function testRegistrationFailsWhenPasswordMissingUppercase(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'str0ng!pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
        $this->assertStringContainsString('uppercase', $result->errors['password']);
    }

    public function testRegistrationFailsWhenPasswordMissingLowercase(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'STR0NG!PASS',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
        $this->assertStringContainsString('lowercase', $result->errors['password']);
    }

    public function testRegistrationFailsWhenPasswordMissingDigit(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Strong!Pass',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
        $this->assertStringContainsString('digit', $result->errors['password']);
    }

    public function testRegistrationFailsWhenPasswordMissingSpecialChar(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Str0ngPass1',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
        $this->assertStringContainsString('special character', $result->errors['password']);
    }

    public function testRegistrationAcceptsPasswordAtMinBoundary(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Aa1!xxxx', // exactly 8 chars
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
    }

    public function testRegistrationAcceptsPasswordAtMaxBoundary(): void
    {
        // 72 chars: 'Aa1!' + 68 'x' chars
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'Aa1!' . str_repeat('x', 68),
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result->isValid);
    }

    // ─── Registration: Multiple Errors ───────────────────────────────────

    public function testRegistrationReturnsMultipleErrors(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid',
            'phone' => 'abc',
            'password' => 'short',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertArrayHasKey('phone', $result->errors);
        $this->assertArrayHasKey('password', $result->errors);
    }

    // ─── Profile Update ──────────────────────────────────────────────────

    public function testValidProfileUpdateReturnsSuccess(): void
    {
        $data = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
        ];

        $result = $this->validator->validateProfileUpdate($data, 1);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testProfileUpdateFailsWithInvalidName(): void
    {
        $data = [
            'name' => 'X',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
        ];

        $result = $this->validator->validateProfileUpdate($data, 1);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    public function testProfileUpdateFailsWithInvalidEmail(): void
    {
        $data = [
            'name' => 'Jane Smith',
            'email' => 'not-valid',
            'phone' => '9876543210',
        ];

        $result = $this->validator->validateProfileUpdate($data, 1);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    public function testProfileUpdateFailsWithInvalidPhone(): void
    {
        $data = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '12345',
        ];

        $result = $this->validator->validateProfileUpdate($data, 1);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('phone', $result->errors);
    }

    // ─── Password Change ─────────────────────────────────────────────────

    public function testValidPasswordChangeReturnsSuccess(): void
    {
        $data = ['password' => 'NewStr0ng!Pass'];

        $result = $this->validator->validatePasswordChange($data);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testPasswordChangeFailsWithWeakPassword(): void
    {
        $data = ['password' => 'weakpass'];

        $result = $this->validator->validatePasswordChange($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
    }

    public function testPasswordChangeFailsWhenPasswordMissing(): void
    {
        $data = [];

        $result = $this->validator->validatePasswordChange($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('password', $result->errors);
    }
}
