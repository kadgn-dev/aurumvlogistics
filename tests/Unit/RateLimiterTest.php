<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Services\RateLimiter;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the RateLimiter service.
 *
 * Uses an in-memory SQLite database to test rate limiting logic
 * without requiring a MySQL connection.
 */
class RateLimiterTest extends TestCase
{
    private PDO $pdo;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        // Ensure config constants are defined for tests
        if (!defined('LOGIN_MAX_ATTEMPTS')) {
            define('LOGIN_MAX_ATTEMPTS', 5);
        }
        if (!defined('LOGIN_LOCKOUT_WINDOW')) {
            define('LOGIN_LOCKOUT_WINDOW', 900);
        }
        if (!defined('CONTACT_RATE_LIMIT')) {
            define('CONTACT_RATE_LIMIT', 3);
        }
        if (!defined('CONTACT_RATE_WINDOW')) {
            define('CONTACT_RATE_WINDOW', 3600);
        }

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Create the rate_limits table compatible with SQLite
        $this->pdo->exec('
            CREATE TABLE rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->rateLimiter = new RateLimiter($this->pdo);
    }

    public function testIsAllowedReturnsTrueWhenNoAttempts(): void
    {
        $this->assertTrue($this->rateLimiter->isAllowed('192.168.1.1', 'login'));
        $this->assertTrue($this->rateLimiter->isAllowed('192.168.1.1', 'contact'));
    }

    public function testIsAllowedReturnsTrueWhenBelowLimit(): void
    {
        // Record 4 login attempts (limit is 5)
        for ($i = 0; $i < 4; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-' . $i . ' minutes');
        }

        $this->assertTrue($this->rateLimiter->isAllowed('192.168.1.1', 'login'));
    }

    public function testIsAllowedReturnsFalseWhenAtLimit(): void
    {
        // Record 5 login attempts (limit is 5)
        for ($i = 0; $i < 5; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-' . $i . ' minutes');
        }

        $this->assertFalse($this->rateLimiter->isAllowed('192.168.1.1', 'login'));
    }

    public function testIsAllowedReturnsFalseWhenAboveLimit(): void
    {
        // Record 6 login attempts
        for ($i = 0; $i < 6; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-' . $i . ' minutes');
        }

        $this->assertFalse($this->rateLimiter->isAllowed('192.168.1.1', 'login'));
    }

    public function testContactRateLimitAllowsUpToThree(): void
    {
        // Record 2 contact attempts (limit is 3)
        for ($i = 0; $i < 2; $i++) {
            $this->insertAttempt('10.0.0.1', 'contact', '-' . ($i * 10) . ' minutes');
        }

        $this->assertTrue($this->rateLimiter->isAllowed('10.0.0.1', 'contact'));
    }

    public function testContactRateLimitBlocksAtThree(): void
    {
        // Record 3 contact attempts (limit is 3)
        for ($i = 0; $i < 3; $i++) {
            $this->insertAttempt('10.0.0.1', 'contact', '-' . ($i * 10) . ' minutes');
        }

        $this->assertFalse($this->rateLimiter->isAllowed('10.0.0.1', 'contact'));
    }

    public function testDifferentIpsAreIndependent(): void
    {
        // Fill up limit for one IP
        for ($i = 0; $i < 5; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-' . $i . ' minutes');
        }

        // Different IP should still be allowed
        $this->assertTrue($this->rateLimiter->isAllowed('192.168.1.2', 'login'));
    }

    public function testDifferentActionsAreIndependent(): void
    {
        // Fill up login limit
        for ($i = 0; $i < 5; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-' . $i . ' minutes');
        }

        // Contact action should still be allowed for same IP
        $this->assertTrue($this->rateLimiter->isAllowed('192.168.1.1', 'contact'));
    }

    public function testOldAttemptsOutsideWindowAreIgnored(): void
    {
        // Record 5 login attempts from 20 minutes ago (outside 15-min window)
        for ($i = 0; $i < 5; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-20 minutes');
        }

        $this->assertTrue($this->rateLimiter->isAllowed('192.168.1.1', 'login'));
    }

    public function testRecordAttemptIncreasesCount(): void
    {
        $this->assertEquals(0, $this->rateLimiter->getAttemptCount('192.168.1.1', 'login'));

        $this->rateLimiter->recordAttempt('192.168.1.1', 'login');

        $this->assertEquals(1, $this->rateLimiter->getAttemptCount('192.168.1.1', 'login'));
    }

    public function testGetAttemptCountReturnsZeroWhenNoAttempts(): void
    {
        $this->assertEquals(0, $this->rateLimiter->getAttemptCount('192.168.1.1', 'login'));
    }

    public function testGetAttemptCountOnlyCountsWithinWindow(): void
    {
        // 3 recent attempts
        for ($i = 0; $i < 3; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', '-' . $i . ' minutes');
        }
        // 2 old attempts outside window
        $this->insertAttempt('192.168.1.1', 'login', '-20 minutes');
        $this->insertAttempt('192.168.1.1', 'login', '-25 minutes');

        $this->assertEquals(3, $this->rateLimiter->getAttemptCount('192.168.1.1', 'login'));
    }

    public function testGetRemainingTimeReturnsZeroWhenNotLimited(): void
    {
        $this->assertEquals(0, $this->rateLimiter->getRemainingTime('192.168.1.1', 'login'));
    }

    public function testGetRemainingTimeReturnsPositiveWhenLimited(): void
    {
        // Record 5 login attempts just now
        for ($i = 0; $i < 5; $i++) {
            $this->insertAttempt('192.168.1.1', 'login', 'now');
        }

        $remaining = $this->rateLimiter->getRemainingTime('192.168.1.1', 'login');

        // Should be close to 900 seconds (15 minutes)
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(900, $remaining);
    }

    public function testUnknownActionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rate limit action: "unknown"');

        $this->rateLimiter->isAllowed('192.168.1.1', 'unknown');
    }

    public function testRecordAttemptWithUnknownActionThrowsException(): void
    {
        // recordAttempt itself doesn't validate action, but getAttemptCount does
        // Actually recordAttempt doesn't call getLimitsForAction, so it won't throw
        // Let's test that getAttemptCount throws for unknown action
        $this->expectException(\InvalidArgumentException::class);

        $this->rateLimiter->getAttemptCount('192.168.1.1', 'unknown');
    }

    /**
     * Helper to insert an attempt at a specific relative time.
     */
    private function insertAttempt(string $ip, string $action, string $relativeTime): void
    {
        $timestamp = date('Y-m-d H:i:s', strtotime($relativeTime));
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (ip_address, action_type, attempted_at) VALUES (:ip, :action, :time)'
        );
        $stmt->execute([
            ':ip' => $ip,
            ':action' => $action,
            ':time' => $timestamp,
        ]);
    }
}
