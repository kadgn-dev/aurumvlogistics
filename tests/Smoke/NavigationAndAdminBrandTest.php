<?php

declare(strict_types=1);

namespace GOLS\Tests\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests verifying the Aurum Vault Logistics rebrand is applied
 * consistently across navigation templates, admin page titles, and
 * source file headers.
 *
 * Validates: Requirements 3.1, 8.1, 8.3, 9.1, 9.2
 */
class NavigationAndAdminBrandTest extends TestCase
{
    private static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = realpath(__DIR__ . '/../../');
    }

    // ─── Navigation Templates ───────────────────────────────────────────

    public function testNavAdminContainsAurumVaultLogistics(): void
    {
        $content = file_get_contents(self::$projectRoot . '/includes/templates/nav_admin.php');
        $this->assertNotFalse($content);
        $this->assertStringContainsString(
            'Aurum Vault Logistics',
            $content,
            'nav_admin.php must contain "Aurum Vault Logistics" brand text'
        );
    }

    public function testNavClientContainsAurumVaultLogistics(): void
    {
        $content = file_get_contents(self::$projectRoot . '/includes/templates/nav_client.php');
        $this->assertNotFalse($content);
        $this->assertStringContainsString(
            'Aurum Vault Logistics',
            $content,
            'nav_client.php must contain "Aurum Vault Logistics" brand text'
        );
    }

    // ─── Admin Page Titles ──────────────────────────────────────────────

    /**
     * @dataProvider adminPageProvider
     */
    public function testAdminPageUsesAurumVaultLogisticsAdminTitleSuffix(string $filename): void
    {
        $content = file_get_contents(self::$projectRoot . '/admin/' . $filename);
        $this->assertNotFalse($content, "Failed to read admin/{$filename}");
        $this->assertMatchesRegularExpression(
            '/\$pageTitle\s*=\s*[\'"].*Aurum Vault Logistics Admin[\'"]/',
            $content,
            "admin/{$filename} must set \$pageTitle with 'Aurum Vault Logistics Admin' suffix"
        );
    }

    public static function adminPageProvider(): array
    {
        return [
            'dashboard.php' => ['dashboard.php'],
            'users.php' => ['users.php'],
            'invoices.php' => ['invoices.php'],
            'inventory.php' => ['inventory.php'],
            'shipments.php' => ['shipments.php'],
            'site-settings.php' => ['site-settings.php'],
            'content.php' => ['content.php'],
            'faq-manage.php' => ['faq-manage.php'],
            'pricing-manage.php' => ['pricing-manage.php'],
            'wire-settings.php' => ['wire-settings.php'],
            'invoice-descriptions.php' => ['invoice-descriptions.php'],
        ];
    }

    // ─── Source File Headers ────────────────────────────────────────────

    /**
     * Verify no source file headers reference "Gold Vault" or "GOLS" as the
     * platform name. Excludes:
     * - GOLS_ constant prefixes (intentionally preserved)
     * - GOLS\ namespace references (PHP namespace, not brand)
     * - vendor/ directory
     * - tests/ directory (test data may reference old brand)
     * - setup/seed scripts (may contain sample data)
     */
    public function testNoSourceFileHeadersReferenceOldBrand(): void
    {
        $directories = [
            self::$projectRoot . '/includes',
            self::$projectRoot . '/admin',
        ];

        $violations = [];

        foreach ($directories as $dir) {
            $files = $this->getPhpFiles($dir);
            foreach ($files as $file) {
                $header = $this->extractFileHeader($file);
                if ($header === '') {
                    continue;
                }

                // Check for "Gold Vault" in file header comments
                if (stripos($header, 'Gold Vault') !== false) {
                    $relativePath = str_replace(self::$projectRoot . DIRECTORY_SEPARATOR, '', $file);
                    $violations[] = "{$relativePath}: contains 'Gold Vault' in file header";
                }

                // Check for standalone "GOLS" as platform name in comments
                // Exclude: GOLS_ (constant prefix), GOLS\ (namespace), GOLS:: (static)
                if (preg_match('/\bGOLS\b(?![_\\\\:])/', $header)) {
                    $relativePath = str_replace(self::$projectRoot . DIRECTORY_SEPARATOR, '', $file);
                    $violations[] = "{$relativePath}: contains 'GOLS' as platform name in file header";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Source file headers still reference old brand:\n" . implode("\n", $violations)
        );
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function getPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Extract the file-level docblock comment (first block comment in the file).
     * Returns the comment text or empty string if none found.
     */
    private function extractFileHeader(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return '';
        }

        // Look for the first docblock or block comment within the first 30 lines
        $lines = array_slice(explode("\n", $content), 0, 30);
        $headerBlock = implode("\n", $lines);

        // Match /** ... */ or /* ... */ style comments
        if (preg_match('/\/\*[\*]?(.+?)\*\//s', $headerBlock, $matches)) {
            return $matches[1];
        }

        // Match consecutive // comment lines at the top
        $commentLines = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//')) {
                $commentLines[] = $trimmed;
            } elseif (str_starts_with($trimmed, '<?php') || str_starts_with($trimmed, 'declare(') || $trimmed === '') {
                continue;
            } else {
                break;
            }
        }

        return implode("\n", $commentLines);
    }
}
