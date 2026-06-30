<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Repositories;

use GOLS\Repositories\UserRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserRepository.
 *
 * Uses an in-memory SQLite database to test repository methods
 * with real SQL execution against a lightweight database.
 */
class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Create a users table compatible with the repository's queries
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone VARCHAR(15) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(10) NOT NULL DEFAULT 'client',
                status VARCHAR(10) NOT NULL DEFAULT 'pending',
                kyc_status VARCHAR(20) NOT NULL DEFAULT 'not_submitted',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->repository = new UserRepository($this->pdo);
    }

    private function createTestUser(array $overrides = []): int
    {
        $data = array_merge([
            'name'          => 'John Doe',
            'email'         => 'john@example.com',
            'phone'         => '1234567890',
            'password_hash' => password_hash('Password1!', PASSWORD_BCRYPT),
        ], $overrides);

        return $this->repository->create($data);
    }

    public function testCreateReturnsNewUserId(): void
    {
        $id = $this->createTestUser();

        $this->assertSame(1, $id);
    }

    public function testCreateSetsDefaultRoleStatusAndKycStatus(): void
    {
        $id = $this->createTestUser();
        $user = $this->repository->findById($id);

        $this->assertSame('client', $user['role']);
        $this->assertSame('pending', $user['status']);
        $this->assertSame('not_submitted', $user['kyc_status']);
    }

    public function testCreateWithCustomRoleAndStatus(): void
    {
        $id = $this->repository->create([
            'name'          => 'Admin User',
            'email'         => 'admin@example.com',
            'phone'         => '9876543210',
            'password_hash' => password_hash('AdminPass1!', PASSWORD_BCRYPT),
            'role'          => 'admin',
            'status'        => 'active',
            'kyc_status'    => 'approved',
        ]);

        $user = $this->repository->findById($id);

        $this->assertSame('admin', $user['role']);
        $this->assertSame('active', $user['status']);
        $this->assertSame('approved', $user['kyc_status']);
    }

    public function testFindByIdReturnsUserWhenExists(): void
    {
        $id = $this->createTestUser();
        $user = $this->repository->findById($id);

        $this->assertNotNull($user);
        $this->assertSame('John Doe', $user['name']);
        $this->assertSame('john@example.com', $user['email']);
    }

    public function testFindByIdReturnsNullWhenNotExists(): void
    {
        $user = $this->repository->findById(999);

        $this->assertNull($user);
    }

    public function testFindByEmailReturnsUserWhenExists(): void
    {
        $this->createTestUser();
        $user = $this->repository->findByEmail('john@example.com');

        $this->assertNotNull($user);
        $this->assertSame('John Doe', $user['name']);
    }

    public function testFindByEmailReturnsNullWhenNotExists(): void
    {
        $user = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($user);
    }

    public function testUpdateModifiesUserFields(): void
    {
        $id = $this->createTestUser();

        $result = $this->repository->update($id, ['name' => 'Jane Doe', 'phone' => '5555555555']);

        $this->assertTrue($result);

        $user = $this->repository->findById($id);
        $this->assertSame('Jane Doe', $user['name']);
        $this->assertSame('5555555555', $user['phone']);
    }

    public function testUpdateReturnsFalseForEmptyData(): void
    {
        $id = $this->createTestUser();

        $result = $this->repository->update($id, []);

        $this->assertFalse($result);
    }

    public function testUpdateReturnsFalseForNonExistentUser(): void
    {
        $result = $this->repository->update(999, ['name' => 'Ghost']);

        $this->assertFalse($result);
    }

    public function testUpdateStatusChangesUserStatus(): void
    {
        $id = $this->createTestUser();

        $result = $this->repository->updateStatus($id, 'active');

        $this->assertTrue($result);

        $user = $this->repository->findById($id);
        $this->assertSame('active', $user['status']);
    }

    public function testUpdateStatusReturnsFalseForNonExistentUser(): void
    {
        $result = $this->repository->updateStatus(999, 'active');

        $this->assertFalse($result);
    }

    public function testUpdateKycStatusChangesKycStatus(): void
    {
        $id = $this->createTestUser();

        $result = $this->repository->updateKycStatus($id, 'approved');

        $this->assertTrue($result);

        $user = $this->repository->findById($id);
        $this->assertSame('approved', $user['kyc_status']);
    }

    public function testUpdateKycStatusReturnsFalseForNonExistentUser(): void
    {
        $result = $this->repository->updateKycStatus(999, 'approved');

        $this->assertFalse($result);
    }

    public function testSearchMatchesByName(): void
    {
        $this->createTestUser(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        $this->createTestUser(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $results = $this->repository->search('Alice');

        $this->assertCount(1, $results);
        $this->assertSame('Alice Smith', $results[0]['name']);
    }

    public function testSearchMatchesByEmail(): void
    {
        $this->createTestUser(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        $this->createTestUser(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $results = $this->repository->search('bob@');

        $this->assertCount(1, $results);
        $this->assertSame('Bob Jones', $results[0]['name']);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $this->createTestUser(['name' => 'Alice Smith', 'email' => 'alice@example.com']);

        $results = $this->repository->search('alice');

        $this->assertCount(1, $results);
        $this->assertSame('Alice Smith', $results[0]['name']);
    }

    public function testSearchReturnsEmptyArrayWhenNoMatch(): void
    {
        $this->createTestUser();

        $results = $this->repository->search('nonexistent');

        $this->assertSame([], $results);
    }

    public function testSearchRespectsLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestUser([
                'name'  => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $results = $this->repository->search('User', 3);

        $this->assertCount(3, $results);
    }

    public function testSearchLimitCappedAt50(): void
    {
        // Limit should be capped at 50 even if a higher value is passed
        $this->createTestUser();

        // This should not throw; it just caps internally
        $results = $this->repository->search('John', 100);

        $this->assertCount(1, $results);
    }

    public function testGetPaginatedReturnsDataAndTotal(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestUser([
                'name'  => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $result = $this->repository->getPaginated(1, 3);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(3, $result['data']);
        $this->assertSame(5, $result['total']);
    }

    public function testGetPaginatedSecondPage(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestUser([
                'name'  => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $result = $this->repository->getPaginated(2, 3);

        $this->assertCount(2, $result['data']);
        $this->assertSame(5, $result['total']);
    }

    public function testGetPaginatedReturnsEmptyDataWhenNoUsers(): void
    {
        $result = $this->repository->getPaginated(1, 20);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['total']);
    }

    public function testGetPaginatedDefaultsTo20PerPage(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createTestUser([
                'name'  => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $result = $this->repository->getPaginated(1);

        $this->assertCount(20, $result['data']);
        $this->assertSame(25, $result['total']);
    }

    public function testEmailExistsReturnsTrueWhenEmailExists(): void
    {
        $this->createTestUser(['email' => 'existing@example.com']);

        $this->assertTrue($this->repository->emailExists('existing@example.com'));
    }

    public function testEmailExistsReturnsFalseWhenEmailDoesNotExist(): void
    {
        $this->assertFalse($this->repository->emailExists('nonexistent@example.com'));
    }

    public function testEmailExistsExcludesSpecifiedUserId(): void
    {
        $id = $this->createTestUser(['email' => 'user@example.com']);

        // Should return false when excluding the user who owns the email
        $this->assertFalse($this->repository->emailExists('user@example.com', $id));
    }

    public function testEmailExistsWithExcludeStillDetectsOtherUsers(): void
    {
        $id1 = $this->createTestUser(['email' => 'user1@example.com']);
        $id2 = $this->createTestUser(['name' => 'User 2', 'email' => 'user2@example.com']);

        // user1's email should still be detected when excluding user2
        $this->assertTrue($this->repository->emailExists('user1@example.com', $id2));
    }
}
