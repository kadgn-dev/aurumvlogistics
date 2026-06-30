<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\UserRepository;
use GOLS\Services\EmailService;
use GOLS\Services\NotificationService;
use GOLS\Services\UserManagementService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the UserManagementService.
 *
 * Uses an in-memory SQLite database to test admin user management logic
 * without requiring a MySQL connection.
 *
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7
 */
class UserManagementServiceTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepository;
    private UserManagementService $service;
    private NotificationService $notificationService;
    private EmailService $emailService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();

        $this->userRepository = new UserRepository($this->pdo);

        // Create mock services
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->emailService = $this->createMock(EmailService::class);

        $this->service = new UserManagementService(
            $this->userRepository,
            $this->notificationService,
            $this->emailService
        );
    }

    // --- getPaginatedUsers Tests (Req 11.1) ---

    public function testGetPaginatedUsersReturns20PerPage(): void
    {
        // Create 25 users
        for ($i = 1; $i <= 25; $i++) {
            $this->createTestUser("user{$i}@example.com", 'client', 'active');
        }

        $result = $this->service->getPaginatedUsers(1);

        $this->assertCount(20, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['perPage']);
    }

    public function testGetPaginatedUsersSecondPageReturnsRemaining(): void
    {
        // Create 25 users
        for ($i = 1; $i <= 25; $i++) {
            $this->createTestUser("user{$i}@example.com", 'client', 'active');
        }

        $result = $this->service->getPaginatedUsers(2);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(2, $result['page']);
    }

    public function testGetPaginatedUsersSortedByCreatedAtDesc(): void
    {
        // Create users with different timestamps
        $this->createTestUserWithTimestamp('oldest@example.com', '2024-01-01 00:00:00');
        $this->createTestUserWithTimestamp('middle@example.com', '2024-06-01 00:00:00');
        $this->createTestUserWithTimestamp('newest@example.com', '2024-12-01 00:00:00');

        $result = $this->service->getPaginatedUsers(1);

        $this->assertEquals('newest@example.com', $result['data'][0]['email']);
        $this->assertEquals('middle@example.com', $result['data'][1]['email']);
        $this->assertEquals('oldest@example.com', $result['data'][2]['email']);
    }

    public function testGetPaginatedUsersPageZeroTreatedAsPageOne(): void
    {
        $this->createTestUser('user@example.com', 'client', 'active');

        $result = $this->service->getPaginatedUsers(0);

        $this->assertEquals(1, $result['page']);
        $this->assertCount(1, $result['data']);
    }

    public function testGetPaginatedUsersEmptyResult(): void
    {
        $result = $this->service->getPaginatedUsers(1);

        $this->assertCount(0, $result['data']);
        $this->assertEquals(0, $result['total']);
    }

    // --- approveKyc Tests (Req 11.2, 11.6) ---

    public function testApproveKycSuccess(): void
    {
        $userId = $this->createTestUser('client@example.com', 'client', 'active', 'pending_review');

        $this->emailService->expects($this->once())
            ->method('send')
            ->with(
                'client@example.com',
                'KYC Verification Approved',
                'kyc_approved.html',
                $this->anything()
            );

        $result = $this->service->approveKyc($userId, 1);

        $this->assertTrue($result->success);
        $this->assertEquals($userId, $result->data['user_id']);
        $this->assertEquals('approved', $result->data['kyc_status']);

        // Verify database was updated
        $user = $this->userRepository->findById($userId);
        $this->assertEquals('approved', $user['kyc_status']);
    }

    public function testApproveKycRejectsAlreadyApproved(): void
    {
        $userId = $this->createTestUser('client@example.com', 'client', 'active', 'approved');

        $result = $this->service->approveKyc($userId, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_APPROVED', $result->errorCode);
        $this->assertStringContainsString('already approved', $result->errorMessage);
    }

    public function testApproveKycRejectsNonExistentUser(): void
    {
        $result = $this->service->approveKyc(9999, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('USER_NOT_FOUND', $result->errorCode);
    }

    public function testApproveKycSendsNotification(): void
    {
        $userId = $this->createTestUser('client@example.com', 'client', 'active', 'pending_review');

        $this->notificationService->expects($this->once())
            ->method('create')
            ->with(
                $userId,
                'kyc_approved',
                $this->anything(),
                $userId,
                'user'
            );

        $this->service->approveKyc($userId, 1);
    }

    // --- suspendUser Tests (Req 11.3, 11.5, 11.7) ---

    public function testSuspendUserSuccess(): void
    {
        $userId = $this->createTestUser('client@example.com', 'client', 'active');

        $this->emailService->expects($this->once())
            ->method('send')
            ->with(
                'client@example.com',
                'Account Suspended',
                'account_suspended.html',
                $this->anything()
            );

        $result = $this->service->suspendUser($userId, 1);

        $this->assertTrue($result->success);
        $this->assertEquals($userId, $result->data['user_id']);
        $this->assertEquals('suspended', $result->data['status']);

        // Verify database was updated
        $user = $this->userRepository->findById($userId);
        $this->assertEquals('suspended', $user['status']);
    }

    public function testSuspendUserRejectsAdminTarget(): void
    {
        $adminId = $this->createTestUser('admin@example.com', 'admin', 'active');

        $result = $this->service->suspendUser($adminId, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('CANNOT_SUSPEND_ADMIN', $result->errorCode);
        $this->assertStringContainsString('Admin accounts cannot be suspended', $result->errorMessage);
    }

    public function testSuspendUserRejectsAlreadySuspended(): void
    {
        $userId = $this->createTestUser('client@example.com', 'client', 'suspended');

        $result = $this->service->suspendUser($userId, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('ALREADY_SUSPENDED', $result->errorCode);
        $this->assertStringContainsString('already suspended', $result->errorMessage);
    }

    public function testSuspendUserRejectsNonExistentUser(): void
    {
        $result = $this->service->suspendUser(9999, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('USER_NOT_FOUND', $result->errorCode);
    }

    public function testSuspendUserSendsNotification(): void
    {
        $userId = $this->createTestUser('client@example.com', 'client', 'active');

        $this->notificationService->expects($this->once())
            ->method('create')
            ->with(
                $userId,
                'account_suspended',
                $this->anything(),
                $userId,
                'user'
            );

        $this->service->suspendUser($userId, 1);
    }

    // --- searchUsers Tests (Req 11.4) ---

    public function testSearchUsersFindsPartialNameMatch(): void
    {
        $this->createTestUser('john.doe@example.com', 'client', 'active');
        $this->createTestUser('jane.smith@example.com', 'client', 'active');

        $results = $this->service->searchUsers('john');

        $this->assertCount(1, $results);
        $this->assertEquals('john.doe@example.com', $results[0]['email']);
    }

    public function testSearchUsersFindsPartialEmailMatch(): void
    {
        $this->createTestUser('user@gmail.com', 'client', 'active');
        $this->createTestUser('user@yahoo.com', 'client', 'active');

        $results = $this->service->searchUsers('gmail');

        $this->assertCount(1, $results);
        $this->assertEquals('user@gmail.com', $results[0]['email']);
    }

    public function testSearchUsersReturnsEmptyForNoMatch(): void
    {
        $this->createTestUser('user@example.com', 'client', 'active');

        $results = $this->service->searchUsers('nonexistent');

        $this->assertCount(0, $results);
    }

    public function testSearchUsersReturnsEmptyForEmptyTerm(): void
    {
        $this->createTestUser('user@example.com', 'client', 'active');

        $results = $this->service->searchUsers('');

        $this->assertCount(0, $results);
    }

    public function testSearchUsersMaxFiftyResults(): void
    {
        // Create 55 users with matching names
        for ($i = 1; $i <= 55; $i++) {
            $this->createTestUser("testuser{$i}@example.com", 'client', 'active');
        }

        $results = $this->service->searchUsers('testuser');

        $this->assertLessThanOrEqual(50, count($results));
    }

    // --- getUserById Tests ---

    public function testGetUserByIdReturnsUser(): void
    {
        $userId = $this->createTestUser('user@example.com', 'client', 'active');

        $user = $this->service->getUserById($userId);

        $this->assertNotNull($user);
        $this->assertEquals('user@example.com', $user['email']);
    }

    public function testGetUserByIdReturnsNullForNonExistent(): void
    {
        $user = $this->service->getUserById(9999);

        $this->assertNull($user);
    }

    // --- Constructor without optional services ---

    public function testWorksWithoutNotificationService(): void
    {
        $service = new UserManagementService($this->userRepository, null, null);
        $userId = $this->createTestUser('client@example.com', 'client', 'active', 'pending_review');

        $result = $service->approveKyc($userId, 1);

        $this->assertTrue($result->success);
    }

    public function testWorksWithoutEmailService(): void
    {
        $service = new UserManagementService($this->userRepository, null, null);
        $userId = $this->createTestUser('client@example.com', 'client', 'active');

        $result = $service->suspendUser($userId, 1);

        $this->assertTrue($result->success);
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
                status TEXT NOT NULL DEFAULT "pending",
                kyc_status TEXT NOT NULL DEFAULT "not_submitted",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    private function createTestUser(
        string $email,
        string $role = 'client',
        string $status = 'active',
        string $kycStatus = 'not_submitted'
    ): int {
        $name = explode('@', $email)[0];

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role, status, kyc_status)
             VALUES (:name, :email, :phone, :password_hash, :role, :status, :kyc_status)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => '1234567890',
            'password_hash' => password_hash('TestP@ss1', PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
            'kyc_status' => $kycStatus,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createTestUserWithTimestamp(string $email, string $createdAt): int
    {
        $name = explode('@', $email)[0];

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role, status, kyc_status, created_at)
             VALUES (:name, :email, :phone, :password_hash, :role, :status, :kyc_status, :created_at)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => '1234567890',
            'password_hash' => password_hash('TestP@ss1', PASSWORD_BCRYPT),
            'role' => 'client',
            'status' => 'active',
            'kyc_status' => 'not_submitted',
            'created_at' => $createdAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
