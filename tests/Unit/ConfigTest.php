<?php

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../includes/config.php';
    }

    public function testDatabaseCredentialsAreDefined(): void
    {
        $this->assertTrue(defined('DB_HOST'));
        $this->assertTrue(defined('DB_NAME'));
        $this->assertTrue(defined('DB_USER'));
        $this->assertTrue(defined('DB_PASSWORD'));
    }

    public function testPaginationConstants(): void
    {
        $this->assertSame(25, PAGINATION_CLIENT_INVENTORY);
        $this->assertSame(20, PAGINATION_ADMIN_USERS);
        $this->assertSame(20, PAGINATION_NOTIFICATIONS);
    }

    public function testSessionTimeout(): void
    {
        $this->assertSame(1800, SESSION_TIMEOUT);
    }

    public function testLoginSecurityConstants(): void
    {
        $this->assertSame(5, LOGIN_MAX_ATTEMPTS);
        $this->assertSame(900, LOGIN_LOCKOUT_WINDOW);
        $this->assertSame(1800, LOGIN_LOCKOUT_DURATION);
    }

    public function testContactRateLimitConstants(): void
    {
        $this->assertSame(3, CONTACT_RATE_LIMIT);
        $this->assertSame(3600, CONTACT_RATE_WINDOW);
    }

    public function testUploadConstants(): void
    {
        $this->assertSame(5242880, UPLOAD_MAX_SIZE);
        $this->assertSame(['pdf', 'jpg', 'png'], ALLOWED_KYC_TYPES);
    }

    public function testApplicationConstants(): void
    {
        $this->assertSame('Aurum Vault Logistics', APP_NAME);
        $this->assertSame('https://www.aurumvlogistics.com', APP_URL);
    }

    public function testPdfModuleConstants(): void
    {
        require_once __DIR__ . '/../../includes/pdf.php';

        $this->assertSame('Aurum Vault Logistics', GOLS_PDF_COMPANY_NAME);
        $this->assertSame('Aurum Vault Logistics Platform', GOLS_PDF_CREATOR);
    }
}
