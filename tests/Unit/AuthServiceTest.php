<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\UserRepository;
use GOLS\Services\AuthService;
use GOLS\Services\RateLimiter;
use GOLS\Validators\UserValidator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the AuthService.
 *
 * Uses an in-memory SQLite database to test authentication logic
 * without requiring a MySQL connection.
 */
class AuthServiceTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepository;
    private UserValidator $userValidator;
    private RateLimiter $rateLimiter;
    private AuthService $authService;

    protected function setUp(): void
    {
        // Ensure config constants are defined for tests
        if (!defined('LOGIN_MAX_ATTEMPTS')) {
            define('LOGIN_MAX_ATTEMPTS', 5);
        }
        if (!defined('LOGIN_LOCKOUT_WINDOW')) {
            define('LOGIN_LOCKOUT_WINDOW', 900);
        }
        if (!defined('LOGIN_LOCKOUT_DURATION')) {
            define('LOGIN_LOCKOUT_DURATION', 1800);
        }
        if (!defined('CONTACT_RATE_LIMIT')) {
            define('CONTACT_RATE_LIMIT', 3);
        }
        if (!defined('CONTACT_RATE_WINDOW')) {
            define('CONTACT_RATE_WINDOW', 3600);
        }
        if (!defined('SESSION_TIMEOUT')) {
            define('SESSION_TIMEOUT', 1800);
        }

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();

        $this->userRepository = new UserRepository($this->pdo);
        $this->userValidator = new UserValidator();
        $this->rateLimiter = new RateLimiter($this->pdo);
        $this->authService = new AuthService(
            $this->userRepository,
            $this->userValidator,
            $this->rateLimiter,
            $this->pdo
        );

        // Start a session for tests if not already active
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // --- Registration Tests ---

    public function testRegisterSuccessCreatesUserWithPendingStatus(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'SecureP@ss1',
        ];

        $result = $this->authService->register($data);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('user_id', $result->data);
        $this->assertGreaterThan(0, $result->data['user_id']);

        // Verify user was created with correct attributes
        $user = $this->userRepository->findById($result->data['user_id']);
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user['name']);
        $this->assertEquals('john@example.com', $user['email']);
        $this->assertEquals('client', $user['role']);
        $this->assertEquals('pending', $user['status']);
    }

    public function testRegisterHashesPasswordWithBcrypt(): void
    {
        $data = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
            'password' => 'SecureP@ss1',
        ];

        $result = $this->authService->register($data);

        $user = $this->userRepository->findById($result->data['user_id']);
        $this->assertNotEquals('SecureP@ss1', $user['password_hash']);
        $this->assertTrue(password_verify('SecureP@ss1', $user['password_hash']));

        // Verify it's bcrypt (starts with $2y$)
        $this->assertStringStartsWith('$2y$', $user['password_hash']);
    }

    public function testRegisterRejectsInvalidInput(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'phone' => '123',
            'password' => 'weak',
        ];

        $result = $this->authService->register($data);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertNotEmpty($result->errors);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $data = [
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'phone' => '1234567890',
            'password' => 'SecureP@ss1',
        ];

        $this->authService->register($data);

        // Try to register with same email
        $data['name'] = 'Second User';
        $result = $this->authService->register($data);

        $this->assertFalse($result->success);
        $this->assertEquals('EMAIL_EXISTS', $result->errorCode);
    }

    // --- Login Tests ---

    public function testLoginSuccessCreatesSession(): void
    {
        $this->createTestUser('user@example.com', 'SecureP@ss1', 'client');

        $result = $this->authService->login('user@example.com', 'SecureP@ss1', '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('redirect', $result->data);
        $this->assertEquals('/client/dashboard.php', $result->data['redirect']);
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertEquals('client', $_SESSION['role']);
        $this->assertArrayHasKey('last_activity', $_SESSION);
    }

    public function testLoginAdminRedirectsToAdminDashboard(): void
    {
        $this->createTestUser('admin@example.com', 'SecureP@ss1', 'admin');

        $result = $this->authService->login('admin@example.com', 'SecureP@ss1', '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertEquals('/admin/dashboard.php', $result->data['redirect']);
        $this->assertEquals('admin', $_SESSION['role']);
    }

    public function testLoginWithInvalidEmailReturnsGenericError(): void
    {
        $result = $this->authService->login('nonexistent@example.com', 'password', '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_CREDENTIALS', $result->errorCode);
        $this->assertEquals('Invalid email or password.', $result->errorMessage);
    }

    public function testLoginWithWrongPasswordReturnsGenericError(): void
    {
        $this->createTestUser('user@example.com', 'SecureP@ss1', 'client');

        $result = $this->authService->login('user@example.com', 'WrongP@ss1', '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_CREDENTIALS', $result->errorCode);
        $this->assertEquals('Invalid email or password.', $result->errorMessage);
    }

    public function testLoginWithSuspendedAccountReturnsError(): void
    {
        $userId = $this->createTestUser('suspended@example.com', 'SecureP@ss1', 'client', 'suspended');

        $result = $this->authService->login('suspended@example.com', 'SecureP@ss1', '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertEquals('ACCOUNT_SUSPENDED', $result->errorCode);
    }

    // --- Account Lockout Tests ---

    public function testIsLockedReturnsFalseWithNoAttempts(): void
    {
        $this->assertFalse($this->authService->isLocked('user@example.com'));
    }

    public function testIsLockedReturnsFalseWithFewAttempts(): void
    {
        // Record 4 failed attempts (below threshold of 5)
        for ($i = 0; $i < 4; $i++) {
            $this->insertFailedAttempt('user@example.com', '127.0.0.1');
        }

        $this->assertFalse($this->authService->isLocked('user@example.com'));
    }

    public function testIsLockedReturnsTrueAtFiveAttempts(): void
    {
        // Record 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->insertFailedAttempt('user@example.com', '127.0.0.1');
        }

        $this->assertTrue($this->authService->isLocked('user@example.com'));
    }

    public function testLockedAccountRejectsValidCredentials(): void
    {
        $this->createTestUser('locked@example.com', 'SecureP@ss1', 'client');

        // Lock the account
        for ($i = 0; $i < 5; $i++) {
            $this->insertFailedAttempt('locked@example.com', '127.0.0.1');
        }

        $result = $this->authService->login('locked@example.com', 'SecureP@ss1', '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertEquals('ACCOUNT_LOCKED', $result->errorCode);
    }

    public function testRecordFailedAttemptIncreasesCount(): void
    {
        $this->authService->recordFailedAttempt('user@example.com', '127.0.0.1');

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :email AND success = 0'
        );
        $stmt->execute([':email' => 'user@example.com']);
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(1, $count);
    }

    public function testResetFailedAttemptsClearsAll(): void
    {
        // Record some failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->insertFailedAttempt('user@example.com', '127.0.0.1');
        }

        $this->authService->resetFailedAttempts('user@example.com');

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :email'
        );
        $stmt->execute([':email' => 'user@example.com']);
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    public function testSuccessfulLoginResetsFailedAttempts(): void
    {
        $this->createTestUser('user@example.com', 'SecureP@ss1', 'client');

        // Record some failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->insertFailedAttempt('user@example.com', '127.0.0.1');
        }

        // Successful login
        $this->authService->login('user@example.com', 'SecureP@ss1', '127.0.0.1');

        // Verify attempts are cleared
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :email'
        );
        $stmt->execute([':email' => 'user@example.com']);
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    // --- Session Validation Tests ---

    public function testValidateSessionReturnsFalseWithNoSession(): void
    {
        $_SESSION = [];
        $this->assertFalse($this->authService->validateSession());
    }

    public function testValidateSessionReturnsFalseWithNoLastActivity(): void
    {
        $_SESSION['user_id'] = 1;
        unset($_SESSION['last_activity']);

        $this->assertFalse($this->authService->validateSession());
    }

    public function testValidateSessionReturnsTrueWhenActive(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time();

        $this->assertTrue($this->authService->validateSession());
    }

    public function testValidateSessionReturnsFalseWhenExpired(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - SESSION_TIMEOUT - 1;

        $this->assertFalse($this->authService->validateSession());
    }

    // --- Change Password Tests ---

    public function testChangePasswordSuccess(): void
    {
        $userId = $this->createTestUser('user@example.com', 'OldP@ssw0rd', 'client');

        $result = $this->authService->changePassword($userId, 'OldP@ssw0rd', 'NewP@ssw0rd1');

        $this->assertTrue($result->success);

        // Verify new password works
        $user = $this->userRepository->findById($userId);
        $this->assertTrue(password_verify('NewP@ssw0rd1', $user['password_hash']));
        $this->assertFalse(password_verify('OldP@ssw0rd', $user['password_hash']));
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $userId = $this->createTestUser('user@example.com', 'OldP@ssw0rd', 'client');

        $result = $this->authService->changePassword($userId, 'WrongP@ss1', 'NewP@ssw0rd1');

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_PASSWORD', $result->errorCode);
    }

    public function testChangePasswordRejectsInvalidNewPassword(): void
    {
        $userId = $this->createTestUser('user@example.com', 'OldP@ssw0rd', 'client');

        $result = $this->authService->changePassword($userId, 'OldP@ssw0rd', 'weak');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
    }

    public function testChangePasswordRejectsNonExistentUser(): void
    {
        $result = $this->authService->changePassword(9999, 'OldP@ssw0rd', 'NewP@ssw0rd1');

        $this->assertFalse($result->success);
        $this->assertEquals('USER_NOT_FOUND', $result->errorCode);
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

        $this->pdo->exec('
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                success INTEGER NOT NULL DEFAULT 0,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    private function createTestUser(string $email, string $password, string $role, string $status = 'active'): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role, status, kyc_status)
             VALUES (:name, :email, :phone, :password_hash, :role, :status, :kyc_status)'
        );
        $stmt->execute([
            'name' => 'Test User',
            'email' => $email,
            'phone' => '1234567890',
            'password_hash' => $hash,
            'role' => $role,
            'status' => $status,
            'kyc_status' => 'not_submitted',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertFailedAttempt(string $email, string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, success, attempted_at)
             VALUES (:email, :ip, 0, :attempted_at)'
        );
        $stmt->execute([
            ':email' => $email,
            ':ip' => $ip,
            ':attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
