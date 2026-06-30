<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\NotificationRepository;
use GOLS\Result;
use GOLS\Services\NotificationService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the NotificationService.
 *
 * Uses an in-memory SQLite database to test notification business logic
 * including retry on failure, unread count capping, pagination, and read status.
 *
 * Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6
 */
class NotificationServiceTest extends TestCase
{
    private PDO $pdo;
    private NotificationRepository $repository;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();
        $this->createTestUser();

        $this->repository = new NotificationRepository($this->pdo);
        $this->service = new NotificationService($this->repository);
    }

    // --- Create Notification Tests ---

    public function testCreateNotificationSuccess(): void
    {
        $result = $this->service->create(1, 'shipment_status', 'Your shipment has been approved.');

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('notification_id', $result->data);
        $this->assertGreaterThan(0, $result->data['notification_id']);
    }

    public function testCreateNotificationWithReferenceSuccess(): void
    {
        $result = $this->service->create(
            1,
            'invoice_generated',
            'Invoice INV-2024-00001 has been generated.',
            42,
            'invoice'
        );

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('notification_id', $result->data);
    }

    public function testCreateNotificationRetriesOnFailure(): void
    {
        // Create a mock repository that fails twice then succeeds
        $mockRepo = $this->createMock(NotificationRepository::class);
        $mockRepo->expects($this->exactly(3))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('DB connection lost')),
                $this->throwException(new \Exception('DB connection lost')),
                5
            );

        $service = new NotificationService($mockRepo);
        $result = $service->create(1, 'test', 'Test message');

        $this->assertTrue($result->success);
        $this->assertEquals(5, $result->data['notification_id']);
    }

    public function testCreateNotificationFailsAfterMaxRetries(): void
    {
        // Create a mock repository that always fails
        $mockRepo = $this->createMock(NotificationRepository::class);
        $mockRepo->expects($this->exactly(3))
            ->method('create')
            ->willThrowException(new \Exception('Persistent DB error'));

        $service = new NotificationService($mockRepo);
        $result = $service->create(1, 'test', 'Test message');

        $this->assertFalse($result->success);
        $this->assertEquals('NOTIFICATION_FAILED', $result->errorCode);
        $this->assertStringContainsString('multiple attempts', $result->errorMessage);
    }

    // --- Unread Count Tests ---

    public function testGetUnreadCountReturnsZeroWhenNoNotifications(): void
    {
        $count = $this->service->getUnreadCount(1);

        $this->assertEquals(0, $count);
    }

    public function testGetUnreadCountReturnsCorrectCount(): void
    {
        $this->createNotifications(1, 5);

        $count = $this->service->getUnreadCount(1);

        $this->assertEquals(5, $count);
    }

    public function testGetFormattedUnreadCountReturnsNumberAsString(): void
    {
        $this->createNotifications(1, 10);

        $formatted = $this->service->getFormattedUnreadCount(1);

        $this->assertEquals('10', $formatted);
    }

    public function testGetFormattedUnreadCountReturns99PlusWhenExceeds99(): void
    {
        $this->createNotifications(1, 100);

        $formatted = $this->service->getFormattedUnreadCount(1);

        $this->assertEquals('99+', $formatted);
    }

    public function testGetFormattedUnreadCountReturnsExact99(): void
    {
        $this->createNotifications(1, 99);

        $formatted = $this->service->getFormattedUnreadCount(1);

        $this->assertEquals('99', $formatted);
    }

    public function testGetFormattedUnreadCountReturnsZeroAsString(): void
    {
        $formatted = $this->service->getFormattedUnreadCount(1);

        $this->assertEquals('0', $formatted);
    }

    // --- Get User Notifications Tests ---

    public function testGetUserNotificationsReturnsPaginatedResults(): void
    {
        $this->createNotifications(1, 25);

        $result = $this->service->getUserNotifications(1, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertCount(20, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['perPage']);
    }

    public function testGetUserNotificationsSecondPage(): void
    {
        $this->createNotifications(1, 25);

        $result = $this->service->getUserNotifications(1, 2);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(2, $result['page']);
    }

    public function testGetUserNotificationsEmptyResult(): void
    {
        $result = $this->service->getUserNotifications(1, 1);

        $this->assertCount(0, $result['data']);
        $this->assertEquals(0, $result['total']);
    }

    public function testGetUserNotificationsNormalizesInvalidPage(): void
    {
        $this->createNotifications(1, 5);

        $result = $this->service->getUserNotifications(1, 0);

        $this->assertEquals(1, $result['page']);
    }

    public function testGetUserNotificationsNormalizesNegativePage(): void
    {
        $this->createNotifications(1, 5);

        $result = $this->service->getUserNotifications(1, -1);

        $this->assertEquals(1, $result['page']);
    }

    // --- Get Recent Notifications Tests ---

    public function testGetRecentNotificationsReturnsDefaultLimit(): void
    {
        $this->createNotifications(1, 10);

        $recent = $this->service->getRecentNotifications(1);

        $this->assertCount(3, $recent);
    }

    public function testGetRecentNotificationsReturnsCustomLimit(): void
    {
        $this->createNotifications(1, 10);

        $recent = $this->service->getRecentNotifications(1, 5);

        $this->assertCount(5, $recent);
    }

    public function testGetRecentNotificationsReturnsEmptyWhenNone(): void
    {
        $recent = $this->service->getRecentNotifications(1);

        $this->assertCount(0, $recent);
    }

    // --- Mark As Read Tests ---

    public function testMarkAsReadSuccess(): void
    {
        $this->service->create(1, 'test', 'Test notification');

        $result = $this->service->markAsRead(1, 1);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->data['notification_id']);
    }

    public function testMarkAsReadFailsForNonExistentNotification(): void
    {
        $result = $this->service->markAsRead(999, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('NOTIFICATION_NOT_FOUND', $result->errorCode);
    }

    public function testMarkAsReadFailsForWrongUser(): void
    {
        $this->service->create(1, 'test', 'Test notification');

        // Try to mark as read with a different user
        $result = $this->service->markAsRead(1, 2);

        $this->assertFalse($result->success);
        $this->assertEquals('NOTIFICATION_NOT_FOUND', $result->errorCode);
    }

    public function testMarkAsReadUpdatesUnreadCount(): void
    {
        $this->createNotifications(1, 3);

        $this->service->markAsRead(1, 1);

        $count = $this->service->getUnreadCount(1);
        $this->assertEquals(2, $count);
    }

    // --- Mark All As Read Tests ---

    public function testMarkAllAsReadSuccess(): void
    {
        $this->createNotifications(1, 5);

        $result = $this->service->markAllAsRead(1);

        $this->assertTrue($result->success);
        $this->assertEquals(5, $result->data['count']);
    }

    public function testMarkAllAsReadUpdatesUnreadCount(): void
    {
        $this->createNotifications(1, 5);

        $this->service->markAllAsRead(1);

        $count = $this->service->getUnreadCount(1);
        $this->assertEquals(0, $count);
    }

    public function testMarkAllAsReadWithNoUnreadReturnsZeroCount(): void
    {
        $result = $this->service->markAllAsRead(1);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->data['count']);
    }

    public function testMarkAllAsReadOnlyAffectsSpecifiedUser(): void
    {
        // Create notifications for user 1 and user 2
        $this->createTestUserWithId(2);
        $this->createNotifications(1, 3);
        $this->createNotifications(2, 4);

        $this->service->markAllAsRead(1);

        // User 1 should have 0 unread
        $this->assertEquals(0, $this->service->getUnreadCount(1));
        // User 2 should still have 4 unread
        $this->assertEquals(4, $this->service->getUnreadCount(2));
    }

    // --- Helper Methods ---

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(15) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role TEXT NOT NULL DEFAULT "client",
                status TEXT NOT NULL DEFAULT "active",
                kyc_status TEXT NOT NULL DEFAULT "not_submitted",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                reference_id INTEGER DEFAULT NULL,
                reference_type VARCHAR(50) DEFAULT NULL,
                read_status TEXT NOT NULL DEFAULT "unread",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ');
    }

    private function createTestUser(): void
    {
        $this->pdo->exec("
            INSERT INTO users (name, email, phone, password_hash, role, status)
            VALUES ('Test User', 'test@example.com', '1234567890', 'hash', 'client', 'active')
        ");
    }

    private function createTestUserWithId(int $id): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, name, email, phone, password_hash, role, status)
            VALUES (:id, :name, :email, '1234567890', 'hash', 'client', 'active')
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => "Test User {$id}",
            ':email' => "user{$id}@example.com",
        ]);
    }

    private function createNotifications(int $userId, int $count): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
             VALUES (:user_id, :event_type, :message, :read_status, :created_at)'
        );

        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([
                ':user_id' => $userId,
                ':event_type' => 'test_event',
                ':message' => "Test notification {$i}",
                ':read_status' => 'unread',
                ':created_at' => date('Y-m-d H:i:s', time() - ($count - $i)),
            ]);
        }
    }
}
