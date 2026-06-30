<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Repositories;

use GOLS\Repositories\NotificationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NotificationRepository.
 *
 * Uses an in-memory SQLite database to test repository logic
 * without requiring a MySQL connection.
 */
class NotificationRepositoryTest extends TestCase
{
    private PDO $pdo;
    private NotificationRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->pdo->exec('
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                reference_id INTEGER DEFAULT NULL,
                reference_type VARCHAR(50) DEFAULT NULL,
                read_status TEXT NOT NULL DEFAULT \'unread\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->repository = new NotificationRepository($this->pdo);
    }

    public function testCreateReturnsNotificationId(): void
    {
        $id = $this->repository->create(1, 'shipment_status', 'Your shipment has been approved.');

        $this->assertSame(1, $id);
    }

    public function testCreateWithReferenceFields(): void
    {
        $id = $this->repository->create(
            1,
            'invoice_generated',
            'Invoice INV-2024-00001 has been generated.',
            42,
            'invoice'
        );

        $this->assertSame(1, $id);

        // Verify the record was stored correctly
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame(1, (int) $row['user_id']);
        $this->assertSame('invoice_generated', $row['event_type']);
        $this->assertSame('Invoice INV-2024-00001 has been generated.', $row['message']);
        $this->assertSame(42, (int) $row['reference_id']);
        $this->assertSame('invoice', $row['reference_type']);
        $this->assertSame('unread', $row['read_status']);
    }

    public function testCreateWithNullReferenceFields(): void
    {
        $id = $this->repository->create(1, 'system', 'Welcome to Aurum Vault Logistics!');

        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertNull($row['reference_id']);
        $this->assertNull($row['reference_type']);
    }

    public function testFindByUserIdReturnsPaginatedResults(): void
    {
        // Create 25 notifications for user 1
        for ($i = 1; $i <= 25; $i++) {
            $this->repository->create(1, 'test', "Notification $i");
        }

        $result = $this->repository->findByUserId(1, 1, 20);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(20, $result['data']);
        $this->assertSame(25, $result['total']);
    }

    public function testFindByUserIdReturnsSecondPage(): void
    {
        // Create 25 notifications for user 1
        for ($i = 1; $i <= 25; $i++) {
            $this->repository->create(1, 'test', "Notification $i");
        }

        $result = $this->repository->findByUserId(1, 2, 20);

        $this->assertCount(5, $result['data']);
        $this->assertSame(25, $result['total']);
    }

    public function testFindByUserIdReverseChronologicalOrder(): void
    {
        // Insert with explicit timestamps to control order
        $this->pdo->exec("INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
            VALUES (1, 'test', 'First', 'unread', '2024-01-01 10:00:00')");
        $this->pdo->exec("INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
            VALUES (1, 'test', 'Second', 'unread', '2024-01-02 10:00:00')");
        $this->pdo->exec("INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
            VALUES (1, 'test', 'Third', 'unread', '2024-01-03 10:00:00')");

        $result = $this->repository->findByUserId(1, 1, 20);

        $this->assertSame('Third', $result['data'][0]['message']);
        $this->assertSame('Second', $result['data'][1]['message']);
        $this->assertSame('First', $result['data'][2]['message']);
    }

    public function testFindByUserIdOnlyReturnsOwnNotifications(): void
    {
        $this->repository->create(1, 'test', 'User 1 notification');
        $this->repository->create(2, 'test', 'User 2 notification');
        $this->repository->create(1, 'test', 'Another user 1 notification');

        $result = $this->repository->findByUserId(1, 1, 20);

        $this->assertSame(2, $result['total']);
        foreach ($result['data'] as $notification) {
            $this->assertSame(1, (int) $notification['user_id']);
        }
    }

    public function testFindByUserIdEmptyResult(): void
    {
        $result = $this->repository->findByUserId(999, 1, 20);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['total']);
    }

    public function testGetUnreadCountReturnsCorrectCount(): void
    {
        $this->repository->create(1, 'test', 'Unread 1');
        $this->repository->create(1, 'test', 'Unread 2');
        $this->repository->create(1, 'test', 'Unread 3');

        $count = $this->repository->getUnreadCount(1);

        $this->assertSame(3, $count);
    }

    public function testGetUnreadCountExcludesReadNotifications(): void
    {
        $id1 = $this->repository->create(1, 'test', 'Unread');
        $this->repository->create(1, 'test', 'Also unread');

        // Mark one as read
        $this->repository->markAsRead($id1, 1);

        $count = $this->repository->getUnreadCount(1);

        $this->assertSame(1, $count);
    }

    public function testGetUnreadCountZeroWhenAllRead(): void
    {
        $id1 = $this->repository->create(1, 'test', 'Notification 1');
        $id2 = $this->repository->create(1, 'test', 'Notification 2');

        $this->repository->markAsRead($id1, 1);
        $this->repository->markAsRead($id2, 1);

        $count = $this->repository->getUnreadCount(1);

        $this->assertSame(0, $count);
    }

    public function testGetUnreadCountOnlyCountsOwnNotifications(): void
    {
        $this->repository->create(1, 'test', 'User 1 notification');
        $this->repository->create(2, 'test', 'User 2 notification');

        $count = $this->repository->getUnreadCount(1);

        $this->assertSame(1, $count);
    }

    public function testMarkAsReadReturnsTrueOnSuccess(): void
    {
        $id = $this->repository->create(1, 'test', 'Notification');

        $result = $this->repository->markAsRead($id, 1);

        $this->assertTrue($result);
    }

    public function testMarkAsReadUpdatesStatus(): void
    {
        $id = $this->repository->create(1, 'test', 'Notification');

        $this->repository->markAsRead($id, 1);

        $stmt = $this->pdo->prepare('SELECT read_status FROM notifications WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $status = $stmt->fetchColumn();

        $this->assertSame('read', $status);
    }

    public function testMarkAsReadReturnsFalseForWrongUser(): void
    {
        $id = $this->repository->create(1, 'test', 'Notification');

        // User 2 tries to mark user 1's notification as read
        $result = $this->repository->markAsRead($id, 2);

        $this->assertFalse($result);
    }

    public function testMarkAsReadReturnsFalseForNonExistentNotification(): void
    {
        $result = $this->repository->markAsRead(999, 1);

        $this->assertFalse($result);
    }

    public function testMarkAllAsReadUpdatesAllUnread(): void
    {
        $this->repository->create(1, 'test', 'Notification 1');
        $this->repository->create(1, 'test', 'Notification 2');
        $this->repository->create(1, 'test', 'Notification 3');

        $updated = $this->repository->markAllAsRead(1);

        $this->assertSame(3, $updated);
        $this->assertSame(0, $this->repository->getUnreadCount(1));
    }

    public function testMarkAllAsReadReturnsZeroWhenNoneUnread(): void
    {
        $id = $this->repository->create(1, 'test', 'Notification');
        $this->repository->markAsRead($id, 1);

        $updated = $this->repository->markAllAsRead(1);

        $this->assertSame(0, $updated);
    }

    public function testMarkAllAsReadOnlyAffectsOwnNotifications(): void
    {
        $this->repository->create(1, 'test', 'User 1 notification');
        $this->repository->create(2, 'test', 'User 2 notification');

        $this->repository->markAllAsRead(1);

        // User 2's notification should still be unread
        $this->assertSame(1, $this->repository->getUnreadCount(2));
    }

    public function testGetRecentByUserIdReturnsLimitedResults(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->repository->create(1, 'test', "Notification $i");
        }

        $recent = $this->repository->getRecentByUserId(1, 3);

        $this->assertCount(3, $recent);
    }

    public function testGetRecentByUserIdReverseChronologicalOrder(): void
    {
        $this->pdo->exec("INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
            VALUES (1, 'test', 'Oldest', 'unread', '2024-01-01 10:00:00')");
        $this->pdo->exec("INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
            VALUES (1, 'test', 'Middle', 'unread', '2024-01-02 10:00:00')");
        $this->pdo->exec("INSERT INTO notifications (user_id, event_type, message, read_status, created_at)
            VALUES (1, 'test', 'Newest', 'unread', '2024-01-03 10:00:00')");

        $recent = $this->repository->getRecentByUserId(1, 3);

        $this->assertSame('Newest', $recent[0]['message']);
        $this->assertSame('Middle', $recent[1]['message']);
        $this->assertSame('Oldest', $recent[2]['message']);
    }

    public function testGetRecentByUserIdOnlyReturnsOwnNotifications(): void
    {
        $this->repository->create(1, 'test', 'User 1 notification');
        $this->repository->create(2, 'test', 'User 2 notification');

        $recent = $this->repository->getRecentByUserId(1, 10);

        $this->assertCount(1, $recent);
        $this->assertSame(1, (int) $recent[0]['user_id']);
    }

    public function testGetRecentByUserIdDefaultLimitIsThree(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create(1, 'test', "Notification $i");
        }

        $recent = $this->repository->getRecentByUserId(1);

        $this->assertCount(3, $recent);
    }

    public function testGetRecentByUserIdEmptyResult(): void
    {
        $recent = $this->repository->getRecentByUserId(999);

        $this->assertSame([], $recent);
    }

    public function testFindByUserIdDefaultPerPageIsTwenty(): void
    {
        // Create 25 notifications
        for ($i = 1; $i <= 25; $i++) {
            $this->repository->create(1, 'test', "Notification $i");
        }

        $result = $this->repository->findByUserId(1, 1);

        $this->assertCount(20, $result['data']);
    }
}
