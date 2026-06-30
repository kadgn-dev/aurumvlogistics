<?php

declare(strict_types=1);

namespace GOLS\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property 2: Title Sanitization Prevents HTML Injection
 *
 * For any string value of $pageTitle containing HTML special characters,
 * the rendered <title> element content SHALL contain HTML-entity-escaped
 * equivalents and SHALL NOT contain unescaped HTML tags or script content.
 *
 * **Validates: Requirements 4.4**
 */
class TitleSanitizationTest extends TestCase
{
    use TestTrait;

    private string $headerTemplatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->headerTemplatePath = dirname(__DIR__, 2) . '/includes/templates/header.php';
    }

    /**
     * Renders the header template with the given $pageTitle and returns the
     * content between <title> and </title> tags.
     */
    private function renderTitle(string $pageTitle): string
    {
        ob_start();
        include $this->headerTemplatePath;
        $output = ob_get_clean();

        if (preg_match('/<title>(.*?)<\/title>/s', $output, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Generates strings that always contain at least one HTML special character.
     * Mixes random printable ASCII with HTML special chars to create realistic
     * injection-like inputs.
     */
    private function htmlSpecialCharStringGenerator(): \Eris\Generator
    {
        $htmlChars = ['<', '>', '&', '"', "'"];

        return Generators::map(
            function (string $base) use ($htmlChars): string {
                // Inject 1-5 random HTML special characters at random positions
                $numInjections = rand(1, 5);
                for ($i = 0; $i < $numInjections; $i++) {
                    $char = $htmlChars[array_rand($htmlChars)];
                    $pos = rand(0, max(0, strlen($base)));
                    $base = substr($base, 0, $pos) . $char . substr($base, $pos);
                }
                return $base;
            },
            Generators::string()
        );
    }

    /**
     * Property: HTML special characters are always escaped in the rendered title.
     *
     * For any input containing HTML special characters, the rendered <title>
     * must contain their entity-escaped equivalents rather than raw characters
     * that could enable injection.
     *
     * **Validates: Requirements 4.4**
     */
    public function testHtmlSpecialCharsAreEscapedInTitle(): void
    {
        $this->limitTo(100);

        $this->forAll(
            $this->htmlSpecialCharStringGenerator()
        )->then(function (string $input): void {
            $renderedTitle = $this->renderTitle($input);

            // If input is whitespace-only or empty, the default title is used
            if (trim($input) === '') {
                $this->assertSame('Aurum Vault Logistics', $renderedTitle);
                return;
            }

            // The rendered title should be the htmlspecialchars-escaped version
            $expected = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $this->assertSame(
                $expected,
                $renderedTitle,
                "Title was not properly sanitized for input: " . bin2hex($input)
            );
        });
    }

    /**
     * Property: No unescaped HTML tags appear in the rendered title output.
     *
     * For any input containing angle brackets that form tag-like patterns,
     * the rendered <title> must not contain actual HTML tags (unescaped < followed
     * by a letter or / and then >).
     *
     * **Validates: Requirements 4.4**
     */
    public function testNoUnescapedHtmlTagsInTitle(): void
    {
        $this->limitTo(100);

        // Generate strings that look like HTML injection attempts
        $injectionGenerator = Generators::oneOf(
            Generators::constant('<script>alert("xss")</script>'),
            Generators::constant('<img src=x onerror=alert(1)>'),
            Generators::constant('<b>bold</b>'),
            Generators::constant('"><script>evil()</script>'),
            Generators::constant("'><img src=x onerror=alert(1)>"),
            Generators::constant('<div onmouseover="steal()">hover</div>'),
            Generators::constant('<iframe src="evil.com"></iframe>'),
            Generators::constant('<a href="javascript:alert(1)">click</a>'),
            $this->htmlSpecialCharStringGenerator()
        );

        $this->forAll(
            $injectionGenerator
        )->then(function (string $input): void {
            $renderedTitle = $this->renderTitle($input);

            // No unescaped HTML tags should be present in the title output
            // An unescaped tag would be a literal < followed by a letter or /
            $this->assertDoesNotMatchRegularExpression(
                '/<[a-zA-Z\/]/',
                $renderedTitle,
                "Unescaped HTML tag found in rendered title for input: " . $input
            );

            // No script content should appear unescaped
            $this->assertDoesNotMatchRegularExpression(
                '/<script/i',
                $renderedTitle,
                "Unescaped <script> found in rendered title for input: " . $input
            );
        });
    }
}
