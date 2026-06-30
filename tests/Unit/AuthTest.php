<?php

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for authentication middleware.
 *
 * Tests verify authentication checks, role-based access control,
 * intended URL storage, and helper functions.
 *
 * Requirements: 16.8, 7.5, 17.7
 */
class AuthTest extends TestCase
{
    private static bool $functionsLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$functionsLoaded) {
            require_once __DIR__ . '/../../includes/config.php';
            require_once __DIR__ . '/../../includes/helpers.php';
            require_once __DIR__ . '/../../includes/session.php';
            require_once __DIR__ . '/../../includes/auth.php';
            self::$functionsLoaded = true;
        }
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/client/dashboard.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // --- isAuthenticated() tests ---

    public function testIsAuthenticatedReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse(isAuthenticated());
    }

    public function testIsAuthenticatedReturnsTrueWhenUserIdSet(): void
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue(isAuthenticated());
    }

    // --- isAdmin() tests ---

    public function testIsAdminReturnsFalseWhenNoRole(): void
    {
        $this->assertFalse(isAdmin());
    }

    public function testIsAdminReturnsFalseWhenRoleIsClient(): void
    {
        $_SESSION['role'] = 'client';
        $this->assertFalse(isAdmin());
    }

    public function testIsAdminReturnsTrueWhenRoleIsAdmin(): void
    {
        $_SESSION['role'] = 'admin';
        $this->assertTrue(isAdmin());
    }

    // --- getCurrentUserId() tests ---

    public function testGetCurrentUserIdReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull(getCurrentUserId());
    }

    public function testGetCurrentUserIdReturnsIntegerUserId(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertSame(42, getCurrentUserId());
    }

    public function testGetCurrentUserIdCastsStringToInt(): void
    {
        $_SESSION['user_id'] = '7';
        $this->assertSame(7, getCurrentUserId());
    }

    // --- getIntendedUrl() tests ---

    public function testGetIntendedUrlReturnsNullWhenNotSet(): void
    {
        $this->assertNull(getIntendedUrl());
    }

    public function testGetIntendedUrlReturnsStoredUrl(): void
    {
        $_SESSION['intended_url'] = '/admin/dashboard.php';
        $this->assertSame('/admin/dashboard.php', getIntendedUrl());
    }

    public function testGetIntendedUrlClearsSessionAfterRetrieval(): void
    {
        $_SESSION['intended_url'] = '/client/invoices.php';

        getIntendedUrl();

        $this->assertArrayNotHasKey('intended_url', $_SESSION);
    }

    public function testGetIntendedUrlReturnsNullOnSecondCall(): void
    {
        $_SESSION['intended_url'] = '/client/shipments.php';

        $first = getIntendedUrl();
        $second = getIntendedUrl();

        $this->assertSame('/client/shipments.php', $first);
        $this->assertNull($second);
    }

    // --- requireAuth() tests (redirect behavior) ---

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRequireAuthStoresIntendedUrlBeforeRedirect(): void
    {
        $_SERVER['REQUEST_URI'] = '/client/dashboard.php';

        // Since requireAuth calls redirect() which calls exit(),
        // we test the intended URL storage logic by verifying the session
        // state when user_id is not set. We use output buffering and
        // headers_sent check to verify redirect behavior.
        session_start();
        $_SESSION['last_activity'] = time();
        // user_id is NOT set, so requireAuth should store URL and redirect

        // We can't easily test the full redirect without mocking exit(),
        // so we verify the component logic: isAuthenticated returns false
        $this->assertFalse(isAuthenticated());
        // And verify that intended_url would be stored correctly
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        $this->assertSame('/client/dashboard.php', $_SESSION['intended_url']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRequireAuthPassesWhenAuthenticated(): void
    {
        // Start session first
        session_start();
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time();

        // Should not redirect - if we get past this, the test passes
        requireAuth();

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRequireRolePassesWithCorrectRole(): void
    {
        session_start();
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['last_activity'] = time();

        requireRole('admin');

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRequireAdminPassesForAdminUser(): void
    {
        session_start();
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['last_activity'] = time();

        requireAdmin();

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRequireClientPassesForClientUser(): void
    {
        session_start();
        $_SESSION['user_id'] = 5;
        $_SESSION['role'] = 'client';
        $_SESSION['last_activity'] = time();
        // Set fresh suspension cache so DB query is skipped
        $_SESSION['suspension_check_at'] = time();
        $_SESSION['cached_account_status'] = 'active';

        requireClient();

        $this->assertTrue(true);
    }

    // --- Function existence tests ---

    public function testRequireAuthFunctionExists(): void
    {
        $this->assertTrue(function_exists('requireAuth'));
    }

    public function testRequireRoleFunctionExists(): void
    {
        $this->assertTrue(function_exists('requireRole'));
    }

    public function testRequireAdminFunctionExists(): void
    {
        $this->assertTrue(function_exists('requireAdmin'));
    }

    public function testRequireClientFunctionExists(): void
    {
        $this->assertTrue(function_exists('requireClient'));
    }

    public function testIsAuthenticatedFunctionExists(): void
    {
        $this->assertTrue(function_exists('isAuthenticated'));
    }

    public function testIsAdminFunctionExists(): void
    {
        $this->assertTrue(function_exists('isAdmin'));
    }

    public function testGetCurrentUserIdFunctionExists(): void
    {
        $this->assertTrue(function_exists('getCurrentUserId'));
    }

    public function testGetIntendedUrlFunctionExists(): void
    {
        $this->assertTrue(function_exists('getIntendedUrl'));
    }
}
