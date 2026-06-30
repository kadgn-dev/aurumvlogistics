<?php

namespace GOLS\Tests\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for email template branding.
 *
 * Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5
 */
class EmailTemplateBrandTest extends TestCase
{
    private const TEMPLATE_DIR = __DIR__ . '/../../includes/templates/email/';

    private const TEMPLATES = [
        'registration.html',
        'invoice_generated.html',
        'shipment_update.html',
        'kyc_approved.html',
        'account_suspended.html',
    ];

    private const BRAND_NAME = 'Aurum Vault Logistics';

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateContainsBrandInH1(string $templateFile): void
    {
        $content = $this->loadTemplate($templateFile);

        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>.*Aurum Vault Logistics.*<\/h1>/si',
            $content,
            "Template {$templateFile} should contain 'Aurum Vault Logistics' in <h1>"
        );
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateContainsBrandInTitle(string $templateFile): void
    {
        $content = $this->loadTemplate($templateFile);

        $this->assertMatchesRegularExpression(
            '/<title>[^<]*Aurum Vault Logistics[^<]*<\/title>/i',
            $content,
            "Template {$templateFile} should contain 'Aurum Vault Logistics' in <title>"
        );
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateContainsBrandInFooter(string $templateFile): void
    {
        $content = $this->loadTemplate($templateFile);

        // Footer copyright text should contain the brand name
        $this->assertStringContainsString(
            'Aurum Vault Logistics. All rights reserved.',
            $content,
            "Template {$templateFile} should contain 'Aurum Vault Logistics. All rights reserved.' in footer"
        );
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateContainsBrandInAltText(string $templateFile): void
    {
        $content = $this->loadTemplate($templateFile);

        $this->assertStringContainsString(
            'Aurum Vault Logistics Logo',
            $content,
            "Template {$templateFile} should contain 'Aurum Vault Logistics Logo' as img alt text"
        );
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateDoesNotContainOldBrand(string $templateFile): void
    {
        $content = $this->loadTemplate($templateFile);

        $this->assertDoesNotMatchRegularExpression(
            '/Gold\s+Vault/i',
            $content,
            "Template {$templateFile} should not contain 'Gold Vault' as brand text"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function templateProvider(): array
    {
        $data = [];
        foreach (self::TEMPLATES as $template) {
            $data[$template] = [$template];
        }
        return $data;
    }

    private function loadTemplate(string $templateFile): string
    {
        $path = self::TEMPLATE_DIR . $templateFile;
        $this->assertFileExists($path, "Email template {$templateFile} must exist");

        return file_get_contents($path);
    }
}
