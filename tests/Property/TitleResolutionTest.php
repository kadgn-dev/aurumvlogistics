<?php

declare(strict_types=1);

namespace GOLS\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based test for title resolution correctness in the header template.
 *
 * Validates: Requirements 4.1, 4.2, 4.3
 *
 * Property 1: Title Resolution Correctness
 * - When $pageTitle is non-empty/non-whitespace: rendered title equals htmlspecialchars($pageTitle)
 * - When $pageTitle is unset/null/empty/whitespace: rendered title equals "Aurum Vault Logistics"
 * - Rendered title must never contain "Gold Vault"
 */
class TitleResolutionTest extends TestCase
{
    use TestTrait;

    private string $headerTemplatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->headerTemplatePath = dirname(__DIR__, 2) . '/includes/templates/header.php';
        $this->limitTo(100);
    }

    /**
     * Render the header template with a given $pageTitle and extract the <title> content.
     */
    private function renderTitle(?string $pageTitle): string
    {
        ob_start();
        if ($pageTitle !== null) {
            include $this->headerTemplatePath;
        } else {
            // Simulate $pageTitle being unset
            unset($pageTitle);
            include $this->headerTemplatePath;
        }
        $output = ob_get_clean();

        if (preg_match('/<title>(.*?)<\/title>/s', $output, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * **Validates: Requirements 4.1, 4.2, 4.3**
     *
     * Property 1: Title Resolution Correctness
     *
     * For non-empty, non-whitespace $pageTitle values, the rendered title
     * must equal htmlspecialchars($pageTitle). The rendered title must never
     * contain "Gold Vault".
     */
    public function testNonEmptyTitleResolvesToSanitizedPageTitle(): void
    {
        $this
            ->forAll(
                Generators::string()
            )
            ->when(function (string $title): bool {
                return trim($title) !== '';
            })
            ->then(function (string $title): void {
                $rendered = $this->renderTitle($title);
                $expected = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

                $this->assertSame(
                    $expected,
                    $rendered,
                    "Non-empty title '$title' should render as its htmlspecialchars equivalent"
                );

                $this->assertStringNotContainsString(
                    'Gold Vault',
                    $rendered,
                    'Rendered title must never contain "Gold Vault"'
                );
            });
    }

    /**
     * **Validates: Requirements 4.1, 4.2, 4.3**
     *
     * Property 1: Title Resolution Correctness
     *
     * For empty or whitespace-only $pageTitle values, the rendered title
     * must equal "Aurum Vault Logistics".
     */
    public function testEmptyOrWhitespaceTitleResolvesToDefault(): void
    {
        $whitespaceGenerator = Generators::oneOf(
            Generators::constant(''),
            Generators::constant('   '),
            Generators::constant("\t"),
            Generators::constant("\n"),
            Generators::constant(" \t\n "),
            Generators::constant("\r\n")
        );

        $this
            ->forAll($whitespaceGenerator)
            ->then(function (string $title): void {
                $rendered = $this->renderTitle($title);

                $this->assertSame(
                    'Aurum Vault Logistics',
                    $rendered,
                    "Empty/whitespace title should fall back to 'Aurum Vault Logistics'"
                );

                $this->assertStringNotContainsString(
                    'Gold Vault',
                    $rendered,
                    'Rendered title must never contain "Gold Vault"'
                );
            });
    }

    /**
     * **Validates: Requirements 4.1, 4.2, 4.3**
     *
     * Property 1: Title Resolution Correctness
     *
     * When $pageTitle is unset (null), the rendered title must equal
     * "Aurum Vault Logistics".
     */
    public function testUnsetTitleResolvesToDefault(): void
    {
        $rendered = $this->renderTitle(null);

        $this->assertSame(
            'Aurum Vault Logistics',
            $rendered,
            "Unset \$pageTitle should fall back to 'Aurum Vault Logistics'"
        );

        $this->assertStringNotContainsString(
            'Gold Vault',
            $rendered,
            'Rendered title must never contain "Gold Vault"'
        );
    }
}
