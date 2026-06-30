<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Validators\ContactValidator;
use GOLS\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Contact Form page logic.
 *
 * Tests validation, rate limiting integration, and form processing
 * for the contact.php page.
 *
 * Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7
 */
class ContactPageTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create rate_limits table
        $this->pdo->exec('
            CREATE TABLE rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Define constants if not already defined
        if (!defined('CONTACT_RATE_LIMIT')) {
            define('CONTACT_RATE_LIMIT', 3);
        }
        if (!defined('CONTACT_RATE_WINDOW')) {
            define('CONTACT_RATE_WINDOW', 3600);
        }
        if (!defined('LOGIN_MAX_ATTEMPTS')) {
            define('LOGIN_MAX_ATTEMPTS', 5);
        }
        if (!defined('LOGIN_LOCKOUT_WINDOW')) {
            define('LOGIN_LOCKOUT_WINDOW', 900);
        }
    }

    /**
     * Test that ContactValidator accepts valid form data.
     * Validates: Requirement 14.4
     */
    public function testValidContactFormDataPassesValidation(): void
    {
        $validator = new ContactValidator();
        $result = $validator->validate([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Inquiry about vault storage',
            'message' => 'I would like to learn more about your services.',
        ]);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    /**
     * Test that ContactValidator rejects empty fields.
     * Validates: Requirement 14.7
     */
    public function testEmptyFieldsFailValidation(): void
    {
        $validator = new ContactValidator();
        $result = $validator->validate([
            'name' => '',
            'email' => '',
            'subject' => '',
            'message' => '',
        ]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertArrayHasKey('subject', $result->errors);
        $this->assertArrayHasKey('message', $result->errors);
    }

    /**
     * Test that ContactValidator rejects invalid email format.
     * Validates: Requirement 14.4
     */
    public function testInvalidEmailFailsValidation(): void
    {
        $validator = new ContactValidator();
        $result = $validator->validate([
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'subject' => 'Test',
            'message' => 'Hello',
        ]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('email', $result->errors);
    }

    /**
     * Test that ContactValidator rejects name exceeding 100 characters.
     * Validates: Requirement 14.4
     */
    public function testNameExceedingMaxLengthFailsValidation(): void
    {
        $validator = new ContactValidator();
        $result = $validator->validate([
            'name' => str_repeat('A', 101),
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
        ]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('name', $result->errors);
    }

    /**
     * Test that ContactValidator rejects subject exceeding 200 characters.
     * Validates: Requirement 14.4
     */
    public function testSubjectExceedingMaxLengthFailsValidation(): void
    {
        $validator = new ContactValidator();
        $result = $validator->validate([
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => str_repeat('A', 201),
            'message' => 'Hello',
        ]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('subject', $result->errors);
    }

    /**
     * Test that ContactValidator rejects message exceeding 5000 characters.
     * Validates: Requirement 14.4
     */
    public function testMessageExceedingMaxLengthFailsValidation(): void
    {
        $validator = new ContactValidator();
        $result = $validator->validate([
            'name' => 'John',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => str_repeat('A', 5001),
        ]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('message', $result->errors);
    }

    /**
     * Test that RateLimiter allows first 3 contact submissions.
     * Validates: Requirement 14.5
     */
    public function testRateLimiterAllowsUpToThreeSubmissions(): void
    {
        $rateLimiter = new RateLimiter($this->pdo);
        $ip = '192.168.1.100';

        // First 3 should be allowed
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rateLimiter->isAllowed($ip, 'contact'));
            $rateLimiter->recordAttempt($ip, 'contact');
        }

        // 4th should be blocked
        $this->assertFalse($rateLimiter->isAllowed($ip, 'contact'));
    }

    /**
     * Test that RateLimiter blocks after 3 submissions per IP.
     * Validates: Requirement 14.6
     */
    public function testRateLimiterBlocksAfterThreeSubmissions(): void
    {
        $rateLimiter = new RateLimiter($this->pdo);
        $ip = '10.0.0.1';

        // Record 3 attempts
        for ($i = 0; $i < 3; $i++) {
            $rateLimiter->recordAttempt($ip, 'contact');
        }

        $this->assertFalse($rateLimiter->isAllowed($ip, 'contact'));
        $this->assertGreaterThan(0, $rateLimiter->getRemainingTime($ip, 'contact'));
    }

    /**
     * Test that rate limiting is per-IP (different IPs are independent).
     * Validates: Requirement 14.5
     */
    public function testRateLimitingIsPerIp(): void
    {
        $rateLimiter = new RateLimiter($this->pdo);

        // Fill up rate limit for IP 1
        for ($i = 0; $i < 3; $i++) {
            $rateLimiter->recordAttempt('192.168.1.1', 'contact');
        }

        // IP 2 should still be allowed
        $this->assertTrue($rateLimiter->isAllowed('192.168.1.2', 'contact'));
    }

    /**
     * Test that sanitizeOutput properly escapes HTML characters.
     */
    public function testSanitizeOutputEscapesHtml(): void
    {
        require_once __DIR__ . '/../../includes/helpers.php';

        $malicious = '<script>alert("xss")</script>';
        $sanitized = sanitizeOutput($malicious);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);
    }
}
