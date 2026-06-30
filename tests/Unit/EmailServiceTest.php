<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Services\EmailService;
use GOLS\Result;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailService.
 *
 * Tests template rendering, send/retry logic, and email preference management.
 */
class EmailServiceTest extends TestCase
{
    private \PDO $pdo;
    private string $templateDir;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create email_preferences table
        $this->pdo->exec('
            CREATE TABLE email_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                category VARCHAR(50) NOT NULL,
                subscribed INTEGER NOT NULL DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, category)
            )
        ');

        // Create a temporary template directory
        $this->templateDir = sys_get_temp_dir() . '/gols_email_test_' . uniqid();
        mkdir($this->templateDir, 0777, true);

        // Create a test template
        file_put_contents(
            $this->templateDir . '/test.html',
            '<html><body><h1>{{platform_name}}</h1><img src="{{logo_url}}"><p>Hello {{name}}</p></body></html>'
        );
    }

    protected function tearDown(): void
    {
        // Clean up template directory
        if (is_dir($this->templateDir)) {
            array_map('unlink', glob($this->templateDir . '/*'));
            rmdir($this->templateDir);
        }
    }

    private function createService(?callable $mailerFactory = null, int $retryInterval = 0): EmailService
    {
        return new EmailService(
            $this->pdo,
            $this->templateDir,
            $mailerFactory,
            $retryInterval
        );
    }

    private function createMockMailer(bool $shouldSucceed = true): callable
    {
        return function () use ($shouldSucceed): PHPMailer {
            $mailer = $this->createMock(PHPMailer::class);

            if ($shouldSucceed) {
                $mailer->method('send')->willReturn(true);
            } else {
                $mailer->method('send')->willThrowException(
                    new PHPMailerException('SMTP connection failed')
                );
            }

            $mailer->method('addAddress')->willReturn(true);

            return $mailer;
        };
    }

    // --- send() tests ---

    public function testSendSuccessWithValidTemplate(): void
    {
        $service = $this->createService($this->createMockMailer(true));

        $result = $service->send('user@example.com', 'Test Subject', 'test.html', ['name' => 'John']);

        $this->assertTrue($result->success);
        $this->assertEquals('user@example.com', $result->data['recipient']);
        $this->assertEquals('Test Subject', $result->data['subject']);
    }

    public function testSendFailsWithMissingTemplate(): void
    {
        $service = $this->createService($this->createMockMailer(true));

        $result = $service->send('user@example.com', 'Test', 'nonexistent.html', []);

        $this->assertFalse($result->success);
        $this->assertEquals('TEMPLATE_NOT_FOUND', $result->errorCode);
    }

    public function testSendFailsWhenMailerThrows(): void
    {
        $service = $this->createService($this->createMockMailer(false));

        $result = $service->send('user@example.com', 'Test', 'test.html', ['name' => 'John']);

        $this->assertFalse($result->success);
        $this->assertEquals('EMAIL_SEND_FAILED', $result->errorCode);
    }

    public function testSendRendersTemplatePlaceholders(): void
    {
        $capturedBody = null;

        $mailerFactory = function () use (&$capturedBody): PHPMailer {
            $mailer = new class extends PHPMailer {
                public function send(): bool
                {
                    return true;
                }
            };
            // We'll capture the body after send is called
            return $mailer;
        };

        // Use a factory that captures the body
        $factory = function () use (&$capturedBody): PHPMailer {
            $mailer = $this->getMockBuilder(PHPMailer::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['send', 'addAddress'])
                ->getMock();

            $mailer->method('send')->willReturnCallback(function () use ($mailer, &$capturedBody) {
                $capturedBody = $mailer->Body;
                return true;
            });
            $mailer->method('addAddress')->willReturn(true);

            return $mailer;
        };

        $service = $this->createService($factory);
        $result = $service->send('user@example.com', 'Test', 'test.html', ['name' => 'Alice']);

        $this->assertTrue($result->success);
        $this->assertNotNull($capturedBody);
        $this->assertStringContainsString('Hello Alice', $capturedBody);
        $this->assertStringContainsString('Aurum Vault Logistics', $capturedBody);
    }

    // --- sendWithRetry() tests ---

    public function testSendWithRetrySucceedsOnFirstAttempt(): void
    {
        $service = $this->createService($this->createMockMailer(true));

        $result = $service->sendWithRetry('user@example.com', 'Test', 'test.html', ['name' => 'John']);

        $this->assertTrue($result->success);
    }

    public function testSendWithRetryFailsAfterMaxAttempts(): void
    {
        $service = $this->createService($this->createMockMailer(false), 0);

        $result = $service->sendWithRetry('user@example.com', 'Test', 'test.html', ['name' => 'John'], 3);

        $this->assertFalse($result->success);
        $this->assertEquals('EMAIL_DELIVERY_FAILED', $result->errorCode);
        $this->assertStringContainsString('3 attempts', $result->errorMessage);
    }

    public function testSendWithRetrySucceedsOnSecondAttempt(): void
    {
        $attemptCount = 0;

        $factory = function () use (&$attemptCount): PHPMailer {
            $attemptCount++;
            $mailer = $this->createMock(PHPMailer::class);
            $mailer->method('addAddress')->willReturn(true);

            if ($attemptCount === 1) {
                $mailer->method('send')->willThrowException(
                    new PHPMailerException('Temporary failure')
                );
            } else {
                $mailer->method('send')->willReturn(true);
            }

            return $mailer;
        };

        $service = $this->createService($factory, 0);
        $result = $service->sendWithRetry('user@example.com', 'Test', 'test.html', ['name' => 'John']);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $attemptCount);
    }

    // --- isUnsubscribed() tests ---

    public function testIsUnsubscribedReturnsFalseByDefault(): void
    {
        $service = $this->createService();

        $this->assertFalse($service->isUnsubscribed(1, 'shipment_updates'));
    }

    public function testIsUnsubscribedReturnsTrueWhenUnsubscribed(): void
    {
        $this->pdo->exec(
            "INSERT INTO email_preferences (user_id, category, subscribed) VALUES (1, 'shipment_updates', 0)"
        );

        $service = $this->createService();

        $this->assertTrue($service->isUnsubscribed(1, 'shipment_updates'));
    }

    public function testIsUnsubscribedReturnsFalseForCriticalCategories(): void
    {
        // Even if there's an unsubscribe record, critical categories return false
        $this->pdo->exec(
            "INSERT INTO email_preferences (user_id, category, subscribed) VALUES (1, 'registration_confirmation', 0)"
        );

        $service = $this->createService();

        $this->assertFalse($service->isUnsubscribed(1, 'registration_confirmation'));
        $this->assertFalse($service->isUnsubscribed(1, 'password_reset'));
        $this->assertFalse($service->isUnsubscribed(1, 'transaction_confirmation'));
    }

    // --- unsubscribe() tests ---

    public function testUnsubscribeCreatesPreferenceRecord(): void
    {
        $service = $this->createService();

        $service->unsubscribe(1, 'marketing');

        $stmt = $this->pdo->prepare('SELECT subscribed FROM email_preferences WHERE user_id = ? AND category = ?');
        $stmt->execute([1, 'marketing']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals(0, (int) $row['subscribed']);
    }

    public function testUnsubscribeUpdatesExistingRecord(): void
    {
        $this->pdo->exec(
            "INSERT INTO email_preferences (user_id, category, subscribed) VALUES (1, 'marketing', 1)"
        );

        $service = $this->createService();
        $service->unsubscribe(1, 'marketing');

        $stmt = $this->pdo->prepare('SELECT subscribed FROM email_preferences WHERE user_id = ? AND category = ?');
        $stmt->execute([1, 'marketing']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(0, (int) $row['subscribed']);
    }

    public function testUnsubscribeIgnoresCriticalCategories(): void
    {
        $service = $this->createService();

        $service->unsubscribe(1, 'registration_confirmation');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_preferences WHERE user_id = ? AND category = ?');
        $stmt->execute([1, 'registration_confirmation']);
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    // --- resubscribe() tests ---

    public function testResubscribeUpdatesRecord(): void
    {
        $this->pdo->exec(
            "INSERT INTO email_preferences (user_id, category, subscribed) VALUES (1, 'marketing', 0)"
        );

        $service = $this->createService();
        $service->resubscribe(1, 'marketing');

        $stmt = $this->pdo->prepare('SELECT subscribed FROM email_preferences WHERE user_id = ? AND category = ?');
        $stmt->execute([1, 'marketing']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(1, (int) $row['subscribed']);
    }

    public function testResubscribeCreatesRecordIfNotExists(): void
    {
        $service = $this->createService();
        $service->resubscribe(1, 'promotions');

        $stmt = $this->pdo->prepare('SELECT subscribed FROM email_preferences WHERE user_id = ? AND category = ?');
        $stmt->execute([1, 'promotions']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals(1, (int) $row['subscribed']);
    }

    // --- isCriticalCategory() tests ---

    public function testIsCriticalCategoryReturnsTrueForCritical(): void
    {
        $service = $this->createService();

        $this->assertTrue($service->isCriticalCategory('registration_confirmation'));
        $this->assertTrue($service->isCriticalCategory('password_reset'));
        $this->assertTrue($service->isCriticalCategory('transaction_confirmation'));
    }

    public function testIsCriticalCategoryReturnsFalseForNonCritical(): void
    {
        $service = $this->createService();

        $this->assertFalse($service->isCriticalCategory('marketing'));
        $this->assertFalse($service->isCriticalCategory('shipment_updates'));
        $this->assertFalse($service->isCriticalCategory('invoice_notifications'));
    }
}
