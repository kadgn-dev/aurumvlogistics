<?php

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for session management middleware.
 *
 * Tests verify secure cookie configuration, timeout enforcement,
 * and session destruction behavior.
 *
 * Requirements: 2.3, 2.4, 16.6
 */
class SessionTest extends TestCase
{
    private static bool $functionsLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$functionsLoaded) {
            require_once __DIR__ . '/../../includes/config.php';
            // We need to load the session file but can't call session functions
            // in CLI without special handling, so we load the function definitions
            require_once __DIR__ . '/../../includes/session.php';
            self::$functionsLoaded = true;
        }
    }

    protected function setUp(): void
    {
        // Reset session state before each test
        $_SESSION = [];
    }

    public function testInitSessionFunctionExists(): void
    {
        $this->assertTrue(function_exists('initSession'));
    }

    public function testDestroySessionFunctionExists(): void
    {
        $this->assertTrue(function_exists('destroySession'));
    }

    public function testCheckSessionTimeoutFunctionExists(): void
    {
        $this->assertTrue(function_exists('checkSessionTimeout'));
    }

    public function testSessionTimeoutConstantIsDefined(): void
    {
        $this->assertTrue(defined('SESSION_TIMEOUT'));
        $this->assertSame(1800, SESSION_TIMEOUT);
    }

    public function testSessionFileIncludesConfig(): void
    {
        // Verify that config constants are available after including session.php
        $this->assertTrue(defined('DB_HOST'));
        $this->assertTrue(defined('SESSION_TIMEOUT'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitSessionSetsCookieParams(): void
    {
        // Start session to test cookie params are set correctly
        initSession();

        $params = session_get_cookie_params();

        $this->assertTrue($params['secure'], 'Session cookie should have Secure flag');
        $this->assertTrue($params['httponly'], 'Session cookie should have HttpOnly flag');
        $this->assertSame('Lax', $params['samesite'], 'Session cookie should have SameSite=Lax');
        $this->assertSame('/', $params['path']);
        $this->assertSame(0, $params['lifetime']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitSessionStartsSession(): void
    {
        initSession();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitSessionSetsLastActivity(): void
    {
        initSession();

        $this->assertArrayHasKey('last_activity', $_SESSION);
        $this->assertIsInt($_SESSION['last_activity']);
        // last_activity should be approximately now
        $this->assertEqualsWithDelta(time(), $_SESSION['last_activity'], 2);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitSessionUpdatesLastActivityOnSubsequentCalls(): void
    {
        initSession();

        $firstActivity = $_SESSION['last_activity'];

        // Simulate a small time passage (within timeout)
        $_SESSION['last_activity'] = time() - 10;

        initSession();

        // last_activity should be updated to current time
        $this->assertEqualsWithDelta(time(), $_SESSION['last_activity'], 2);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCheckSessionTimeoutDoesNothingWithoutLastActivity(): void
    {
        session_start();
        // No last_activity set - should not redirect
        checkSessionTimeout();

        // If we reach here, no redirect happened (exit was not called)
        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCheckSessionTimeoutDoesNothingWhenWithinTimeout(): void
    {
        session_start();
        $_SESSION['last_activity'] = time() - 100; // 100 seconds ago, well within 1800

        checkSessionTimeout();

        // If we reach here, no redirect happened
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDestroySessionClearsSessionData(): void
    {
        session_start();
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time();

        destroySession();

        $this->assertEmpty($_SESSION);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDestroySessionDestroysServerSession(): void
    {
        session_start();
        $_SESSION['user_id'] = 1;

        destroySession();

        // After destroy, session status should not be active
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitSessionDoesNotRestartActiveSession(): void
    {
        // Start session manually first
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $_SESSION['user_id'] = 42;
        $_SESSION['last_activity'] = time() - 5;

        $sessionId = session_id();

        // Call initSession - should not restart
        initSession();

        // Session ID should remain the same
        $this->assertSame($sessionId, session_id());
        // User data should be preserved
        $this->assertSame(42, $_SESSION['user_id']);
    }
}
