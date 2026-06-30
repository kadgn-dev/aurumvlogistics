<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for getSiteSettings() default values.
 *
 * Validates that the correct brand defaults are returned when no
 * site_settings record exists in the database.
 *
 * Requirements: 2.1, 2.2
 */
class SiteSettingsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDefaultSiteNameIsAurumVaultLogistics(): void
    {
        // Create an in-memory SQLite database with content_pages table but no site_settings record
        $testDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avl_test_' . uniqid() . '.sqlite';

        $pdo = new \PDO('sqlite:' . $testDbPath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('
            CREATE TABLE content_pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_key VARCHAR(50) NOT NULL UNIQUE,
                content TEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_by INTEGER DEFAULT NULL
            )
        ');
        // Close the PDO connection so getDbConnection() can open it fresh
        $pdo = null;

        // Define DB constants before including db.php to prevent config.local.php from overriding
        define('DB_DRIVER', 'sqlite');
        define('DB_SQLITE_PATH', $testDbPath);
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'test');
        define('DB_USER', 'root');
        define('DB_PASSWORD', '');

        // Provide getDbConnection by including db.php — since constants are already defined,
        // config.local.php's define() calls will trigger warnings. Suppress them.
        @require_once __DIR__ . '/../../includes/db.php';
        require_once __DIR__ . '/../../includes/helpers.php';

        $settings = getSiteSettings();

        $this->assertSame('AURUM VAULT LOGISTICS', $settings['site_name']);

        @unlink($testDbPath);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDefaultSiteTaglineIsSecureGoldStorageAndInsuredLogisticsServices(): void
    {
        // Create an in-memory SQLite database with content_pages table but no site_settings record
        $testDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avl_test_' . uniqid() . '.sqlite';

        $pdo = new \PDO('sqlite:' . $testDbPath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('
            CREATE TABLE content_pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_key VARCHAR(50) NOT NULL UNIQUE,
                content TEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_by INTEGER DEFAULT NULL
            )
        ');
        // Close the PDO connection so getDbConnection() can open it fresh
        $pdo = null;

        // Define DB constants before including db.php to prevent config.local.php from overriding
        define('DB_DRIVER', 'sqlite');
        define('DB_SQLITE_PATH', $testDbPath);
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'test');
        define('DB_USER', 'root');
        define('DB_PASSWORD', '');

        // Provide getDbConnection by including db.php — suppress config.local.php redefinition warnings
        @require_once __DIR__ . '/../../includes/db.php';
        require_once __DIR__ . '/../../includes/helpers.php';

        $settings = getSiteSettings();

        $this->assertSame('Secure Gold Storage & Insured Logistics Services', $settings['site_tagline']);

        @unlink($testDbPath);
    }
}
