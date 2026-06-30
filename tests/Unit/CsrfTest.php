<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for /includes/csrf.php CSRF token functions.
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset session superglobal for each test
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    // --- generateCsrfToken ---

    public function testGenerateCsrfTokenReturns64HexChars(): void
    {
        $token = generateCsrfToken();
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateCsrfTokenStoresInSession(): void
    {
        $token = generateCsrfToken();
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGenerateCsrfTokenProducesUniqueTokens(): void
    {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();
        $this->assertNotSame($token1, $token2);
    }

    // --- getCsrfToken ---

    public function testGetCsrfTokenGeneratesNewTokenWhenNoneExists(): void
    {
        $token = getCsrfToken();
        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGetCsrfTokenReturnsExistingToken(): void
    {
        $_SESSION['csrf_token'] = 'existing_token_value';
        $token = getCsrfToken();
        $this->assertSame('existing_token_value', $token);
    }

    public function testGetCsrfTokenGeneratesNewWhenSessionTokenIsEmpty(): void
    {
        $_SESSION['csrf_token'] = '';
        $token = getCsrfToken();
        $this->assertSame(64, strlen($token));
        $this->assertNotSame('', $token);
    }

    // --- validateCsrfToken ---

    public function testValidateCsrfTokenReturnsTrueForMatchingToken(): void
    {
        $token = generateCsrfToken();
        $this->assertTrue(validateCsrfToken($token));
    }

    public function testValidateCsrfTokenReturnsFalseForMismatchedToken(): void
    {
        generateCsrfToken();
        $this->assertFalse(validateCsrfToken('invalid_token'));
    }

    public function testValidateCsrfTokenReturnsFalseForNull(): void
    {
        generateCsrfToken();
        $this->assertFalse(validateCsrfToken(null));
    }

    public function testValidateCsrfTokenReturnsFalseForEmptyString(): void
    {
        generateCsrfToken();
        $this->assertFalse(validateCsrfToken(''));
    }

    public function testValidateCsrfTokenReturnsFalseWhenNoSessionToken(): void
    {
        $this->assertFalse(validateCsrfToken('some_token'));
    }

    public function testValidateCsrfTokenReturnsFalseWhenSessionTokenIsEmpty(): void
    {
        $_SESSION['csrf_token'] = '';
        $this->assertFalse(validateCsrfToken('some_token'));
    }

    // --- csrfField ---

    public function testCsrfFieldReturnsHiddenInput(): void
    {
        $token = generateCsrfToken();
        $field = csrfField();
        $expected = '<input type="hidden" name="csrf_token" value="' . $token . '">';
        $this->assertSame($expected, $field);
    }

    public function testCsrfFieldGeneratesTokenIfNoneExists(): void
    {
        $field = csrfField();
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertNotEmpty($_SESSION['csrf_token']);
    }

    public function testCsrfFieldEscapesTokenValue(): void
    {
        // Force a token with special HTML characters to verify escaping
        $_SESSION['csrf_token'] = 'token"with<special>&chars';
        $field = csrfField();
        $this->assertStringContainsString('value="token&quot;with&lt;special&gt;&amp;chars"', $field);
    }

    // --- enforceCsrf ---

    public function testEnforceCsrfDoesNothingOnGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        // Should not die or throw
        enforceCsrf();
        $this->assertTrue(true); // If we reach here, the test passes
    }

    public function testEnforceCsrfDoesNothingOnHeadRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        enforceCsrf();
        $this->assertTrue(true);
    }

    public function testEnforceCsrfPassesWithValidPostToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = generateCsrfToken();
        $_POST['csrf_token'] = $token;
        enforceCsrf();
        $this->assertTrue(true);
    }

    public function testEnforceCsrfPassesWithValidHeaderToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = generateCsrfToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        enforceCsrf();
        $this->assertTrue(true);
    }

    public function testEnforceCsrfPassesOnPutWithValidToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $token = generateCsrfToken();
        $_POST['csrf_token'] = $token;
        enforceCsrf();
        $this->assertTrue(true);
    }

    public function testEnforceCsrfPassesOnDeleteWithValidToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $token = generateCsrfToken();
        $_POST['csrf_token'] = $token;
        enforceCsrf();
        $this->assertTrue(true);
    }
}
