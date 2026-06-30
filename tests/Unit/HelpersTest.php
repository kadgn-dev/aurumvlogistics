<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for /includes/helpers.php utility functions.
 */
class HelpersTest extends TestCase
{
    // --- sanitizeOutput ---

    public function testSanitizeOutputEncodesHtmlSpecialChars(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            sanitizeOutput('<script>alert("xss")</script>')
        );
    }

    public function testSanitizeOutputEncodesSingleQuotes(): void
    {
        $this->assertSame(
            '&#039;onclick&#039;',
            sanitizeOutput("'onclick'")
        );
    }

    public function testSanitizeOutputEncodesAmpersand(): void
    {
        $this->assertSame('foo &amp; bar', sanitizeOutput('foo & bar'));
    }

    public function testSanitizeOutputPreservesPlainText(): void
    {
        $this->assertSame('Hello World', sanitizeOutput('Hello World'));
    }

    public function testSanitizeOutputHandlesEmptyString(): void
    {
        $this->assertSame('', sanitizeOutput(''));
    }

    // --- sanitizeInput ---

    public function testSanitizeInputTrimsWhitespace(): void
    {
        $this->assertSame('hello', sanitizeInput('  hello  '));
    }

    public function testSanitizeInputStripsNullBytes(): void
    {
        $this->assertSame('hello', sanitizeInput("hel\0lo"));
    }

    public function testSanitizeInputTrimsAndStripsNullBytes(): void
    {
        $this->assertSame('test', sanitizeInput(" te\0st "));
    }

    public function testSanitizeInputHandlesEmptyString(): void
    {
        $this->assertSame('', sanitizeInput(''));
    }

    // --- formatCurrency ---

    public function testFormatCurrencyDefaultUsd(): void
    {
        $this->assertSame('$1,234.56', formatCurrency(1234.56));
    }

    public function testFormatCurrencyEur(): void
    {
        $this->assertSame('€999.00', formatCurrency(999.00, 'EUR'));
    }

    public function testFormatCurrencyGbp(): void
    {
        $this->assertSame('£50.99', formatCurrency(50.99, 'GBP'));
    }

    public function testFormatCurrencyUnknownCurrency(): void
    {
        $this->assertSame('NGN 100.00', formatCurrency(100.00, 'NGN'));
    }

    public function testFormatCurrencyZeroAmount(): void
    {
        $this->assertSame('$0.00', formatCurrency(0.00));
    }

    public function testFormatCurrencyLargeAmount(): void
    {
        $this->assertSame('$1,000,000.00', formatCurrency(1000000.00));
    }

    // --- formatDate ---

    public function testFormatDateDefaultFormat(): void
    {
        $this->assertSame('Jan 15, 2024', formatDate('2024-01-15 10:30:00'));
    }

    public function testFormatDateCustomFormat(): void
    {
        $this->assertSame('2024-01-15', formatDate('2024-01-15 10:30:00', 'Y-m-d'));
    }

    public function testFormatDateInvalidInput(): void
    {
        $this->assertSame('', formatDate('not-a-date'));
    }

    public function testFormatDateEmptyString(): void
    {
        $this->assertSame('', formatDate(''));
    }

    // --- formatDateTime ---

    public function testFormatDateTimeIncludesTime(): void
    {
        $this->assertSame('Jan 15, 2024 2:30 PM', formatDateTime('2024-01-15 14:30:00'));
    }

    public function testFormatDateTimeMorning(): void
    {
        $this->assertSame('Dec 25, 2023 9:00 AM', formatDateTime('2023-12-25 09:00:00'));
    }

    // --- getPaginationOffset ---

    public function testGetPaginationOffsetFirstPage(): void
    {
        $this->assertSame(0, getPaginationOffset(1, 25));
    }

    public function testGetPaginationOffsetSecondPage(): void
    {
        $this->assertSame(25, getPaginationOffset(2, 25));
    }

    public function testGetPaginationOffsetThirdPage(): void
    {
        $this->assertSame(40, getPaginationOffset(3, 20));
    }

    public function testGetPaginationOffsetClampsPageToMinimumOne(): void
    {
        $this->assertSame(0, getPaginationOffset(0, 25));
        $this->assertSame(0, getPaginationOffset(-1, 25));
    }

    public function testGetPaginationOffsetClampsPerPageToMinimumOne(): void
    {
        $this->assertSame(0, getPaginationOffset(1, 0));
        $this->assertSame(0, getPaginationOffset(1, -5));
    }

    // --- getTotalPages ---

    public function testGetTotalPagesExactDivision(): void
    {
        $this->assertSame(4, getTotalPages(100, 25));
    }

    public function testGetTotalPagesWithRemainder(): void
    {
        $this->assertSame(5, getTotalPages(101, 25));
    }

    public function testGetTotalPagesZeroRecords(): void
    {
        $this->assertSame(1, getTotalPages(0, 25));
    }

    public function testGetTotalPagesNegativeRecords(): void
    {
        $this->assertSame(1, getTotalPages(-5, 25));
    }

    public function testGetTotalPagesClampsPerPageToMinimumOne(): void
    {
        $this->assertSame(10, getTotalPages(10, 0));
    }

    public function testGetTotalPagesSingleRecord(): void
    {
        $this->assertSame(1, getTotalPages(1, 25));
    }

    // --- generateRandomFilename ---

    public function testGenerateRandomFilenameHasCorrectExtension(): void
    {
        $filename = generateRandomFilename('pdf');
        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function testGenerateRandomFilenameStripsLeadingDot(): void
    {
        $filename = generateRandomFilename('.png');
        $this->assertStringEndsWith('.png', $filename);
        $this->assertStringNotContainsString('..', $filename);
    }

    public function testGenerateRandomFilenameIsUnique(): void
    {
        $filename1 = generateRandomFilename('jpg');
        $filename2 = generateRandomFilename('jpg');
        $this->assertNotSame($filename1, $filename2);
    }

    public function testGenerateRandomFilenameHasExpectedLength(): void
    {
        // 16 random bytes = 32 hex chars + '.' + extension
        $filename = generateRandomFilename('pdf');
        $this->assertSame(36, strlen($filename)); // 32 + 1 + 3
    }

    // --- isValidEmail ---

    public function testIsValidEmailAcceptsValidEmail(): void
    {
        $this->assertTrue(isValidEmail('user@example.com'));
    }

    public function testIsValidEmailAcceptsSubdomain(): void
    {
        $this->assertTrue(isValidEmail('user@mail.example.co.uk'));
    }

    public function testIsValidEmailRejectsNoAtSign(): void
    {
        $this->assertFalse(isValidEmail('userexample.com'));
    }

    public function testIsValidEmailRejectsNoDomain(): void
    {
        $this->assertFalse(isValidEmail('user@'));
    }

    public function testIsValidEmailRejectsEmptyString(): void
    {
        $this->assertFalse(isValidEmail(''));
    }

    public function testIsValidEmailRejectsSpaces(): void
    {
        $this->assertFalse(isValidEmail('user @example.com'));
    }
}
