<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Validators;

use GOLS\ValidationResult;
use GOLS\Validators\ContactValidator;
use PHPUnit\Framework\TestCase;

class ContactValidatorTest extends TestCase
{
    private ContactValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContactValidator();
    }

    public function testValidDataReturnsSuccess(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Inquiry about vault storage',
            'message' => 'I would like to know more about your services.',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function testMissingNameReturnsError(): void
    {
        $data = [
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    public function testEmptyNameReturnsError(): void
    {
        $data = [
            'name' => '   ',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
        $this->assertSame('Name is required.', $result->errors['name']);
    }

    public function testNameExceeding100CharsReturnsError(): void
    {
        $data = [
            'name' => str_repeat('a', 101),
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
        $this->assertSame('Name must not exceed 100 characters.', $result->errors['name']);
    }

    public function testNameAtExactly100CharsIsValid(): void
    {
        $data = [
            'name' => str_repeat('a', 100),
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testMissingEmailReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertSame('Email is required.', $result->errors['email']);
    }

    public function testEmptyEmailReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => '',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertSame('Email is required.', $result->errors['email']);
    }

    public function testInvalidEmailFormatReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'not-an-email',
            'subject' => 'Test',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertSame('Email format is invalid.', $result->errors['email']);
    }

    public function testMissingSubjectReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('subject', $result->errors);
    }

    public function testEmptySubjectReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => '',
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('subject', $result->errors);
        $this->assertSame('Subject is required.', $result->errors['subject']);
    }

    public function testSubjectExceeding200CharsReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => str_repeat('x', 201),
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('subject', $result->errors);
        $this->assertSame('Subject must not exceed 200 characters.', $result->errors['subject']);
    }

    public function testSubjectAtExactly200CharsIsValid(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => str_repeat('x', 200),
            'message' => 'Hello',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testMissingMessageReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => 'Test',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('message', $result->errors);
    }

    public function testEmptyMessageReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => '  ',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('message', $result->errors);
        $this->assertSame('Message is required.', $result->errors['message']);
    }

    public function testMessageExceeding5000CharsReturnsError(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => str_repeat('m', 5001),
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('message', $result->errors);
        $this->assertSame('Message must not exceed 5000 characters.', $result->errors['message']);
    }

    public function testMessageAtExactly5000CharsIsValid(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => str_repeat('m', 5000),
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testAllFieldsMissingReturnsAllErrors(): void
    {
        $result = $this->validator->validate([]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertArrayHasKey('subject', $result->errors);
        $this->assertArrayHasKey('message', $result->errors);
        $this->assertCount(4, $result->errors);
    }

    public function testMultipleInvalidFieldsReturnsMultipleErrors(): void
    {
        $data = [
            'name' => '',
            'email' => 'bad-email',
            'subject' => str_repeat('x', 201),
            'message' => '',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertArrayHasKey('subject', $result->errors);
        $this->assertArrayHasKey('message', $result->errors);
    }
}
