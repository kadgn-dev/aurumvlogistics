<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\ContentRepository;
use GOLS\Services\ContentService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ContentService.
 *
 * Uses an in-memory SQLite database to test content management logic
 * without requiring a MySQL connection.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7
 */
class ContentServiceTest extends TestCase
{
    private PDO $pdo;
    private ContentRepository $repository;
    private ContentService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();

        $this->repository = new ContentRepository($this->pdo);
        $this->service = new ContentService($this->repository);
    }

    // --- Homepage Content Tests ---

    public function testGetHomepageContentReturnsEmptyArrayWhenNoContent(): void
    {
        $result = $this->service->getHomepageContent();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetHomepageContentReturnsStoredContent(): void
    {
        $data = [
            'hero_text' => 'Welcome to Gold Vault',
            'service_descriptions' => 'Premium storage services',
            'security_highlights' => '24/7 armed security',
        ];

        $this->service->updateHomepageContent($data, 1);

        $result = $this->service->getHomepageContent();

        $this->assertEquals('Welcome to Gold Vault', $result['hero_text']);
        $this->assertEquals('Premium storage services', $result['service_descriptions']);
        $this->assertEquals('24/7 armed security', $result['security_highlights']);
    }

    public function testUpdateHomepageContentSuccess(): void
    {
        $data = [
            'hero_text' => 'Secure Gold Storage',
            'service_descriptions' => 'Vault and shipping services',
            'security_highlights' => 'Insured and monitored',
        ];

        $result = $this->service->updateHomepageContent($data, 1);

        $this->assertTrue($result->success);
        $this->assertEquals('home', $result->data['page_key']);
    }

    public function testUpdateHomepageContentRejectsEmptyHeroText(): void
    {
        $data = [
            'hero_text' => '',
            'service_descriptions' => 'Services',
            'security_highlights' => 'Security',
        ];

        $result = $this->service->updateHomepageContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('hero_text', $result->errors);
    }

    public function testUpdateHomepageContentRejectsEmptyServiceDescriptions(): void
    {
        $data = [
            'hero_text' => 'Hero',
            'service_descriptions' => '',
            'security_highlights' => 'Security',
        ];

        $result = $this->service->updateHomepageContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('service_descriptions', $result->errors);
    }

    public function testUpdateHomepageContentRejectsEmptySecurityHighlights(): void
    {
        $data = [
            'hero_text' => 'Hero',
            'service_descriptions' => 'Services',
            'security_highlights' => '',
        ];

        $result = $this->service->updateHomepageContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('security_highlights', $result->errors);
    }

    public function testUpdateHomepageContentRejectsAllEmptyFields(): void
    {
        $data = [
            'hero_text' => '',
            'service_descriptions' => '',
            'security_highlights' => '',
        ];

        $result = $this->service->updateHomepageContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertCount(3, $result->errors);
    }

    // --- FAQ Entry Tests ---

    public function testGetFaqEntriesReturnsEmptyArrayWhenNone(): void
    {
        $result = $this->service->getFaqEntries();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAddFaqEntrySuccess(): void
    {
        $result = $this->service->addFaqEntry('What is gold storage?', 'We store gold securely.', 1);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('entry_id', $result->data);
        $this->assertGreaterThan(0, $result->data['entry_id']);
    }

    public function testAddFaqEntryRejectsEmptyQuestion(): void
    {
        $result = $this->service->addFaqEntry('', 'Some answer.');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('question', $result->errors);
    }

    public function testAddFaqEntryRejectsWhitespaceOnlyQuestion(): void
    {
        $result = $this->service->addFaqEntry('   ', 'Some answer.');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('question', $result->errors);
    }

    public function testAddFaqEntryRejectsQuestionOver200Chars(): void
    {
        $longQuestion = str_repeat('a', 201);

        $result = $this->service->addFaqEntry($longQuestion, 'Some answer.');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('question', $result->errors);
    }

    public function testAddFaqEntryAcceptsQuestionExactly200Chars(): void
    {
        $question = str_repeat('a', 200);

        $result = $this->service->addFaqEntry($question, 'Some answer.');

        $this->assertTrue($result->success);
    }

    public function testAddFaqEntryRejectsEmptyAnswer(): void
    {
        $result = $this->service->addFaqEntry('Valid question?', '');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('answer', $result->errors);
    }

    public function testAddFaqEntryRejectsWhitespaceOnlyAnswer(): void
    {
        $result = $this->service->addFaqEntry('Valid question?', '   ');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('answer', $result->errors);
    }

    public function testAddFaqEntryRejectsAnswerOver2000Chars(): void
    {
        $longAnswer = str_repeat('b', 2001);

        $result = $this->service->addFaqEntry('Valid question?', $longAnswer);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('answer', $result->errors);
    }

    public function testAddFaqEntryAcceptsAnswerExactly2000Chars(): void
    {
        $answer = str_repeat('b', 2000);

        $result = $this->service->addFaqEntry('Valid question?', $answer);

        $this->assertTrue($result->success);
    }

    public function testGetFaqEntriesReturnsSortedEntries(): void
    {
        $this->service->addFaqEntry('Third question?', 'Third answer.', 3);
        $this->service->addFaqEntry('First question?', 'First answer.', 1);
        $this->service->addFaqEntry('Second question?', 'Second answer.', 2);

        $entries = $this->service->getFaqEntries();

        $this->assertCount(3, $entries);
        $this->assertEquals('First question?', $entries[0]['question']);
        $this->assertEquals('Second question?', $entries[1]['question']);
        $this->assertEquals('Third question?', $entries[2]['question']);
    }

    public function testUpdateFaqEntrySuccess(): void
    {
        $addResult = $this->service->addFaqEntry('Original question?', 'Original answer.');
        $entryId = $addResult->data['entry_id'];

        $result = $this->service->updateFaqEntry($entryId, 'Updated question?', 'Updated answer.');

        $this->assertTrue($result->success);
        $this->assertEquals($entryId, $result->data['entry_id']);

        // Verify the update persisted
        $entries = $this->service->getFaqEntries();
        $this->assertEquals('Updated question?', $entries[0]['question']);
        $this->assertEquals('Updated answer.', $entries[0]['answer']);
    }

    public function testUpdateFaqEntryRejectsEmptyQuestion(): void
    {
        $addResult = $this->service->addFaqEntry('Question?', 'Answer.');
        $entryId = $addResult->data['entry_id'];

        $result = $this->service->updateFaqEntry($entryId, '', 'Updated answer.');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('question', $result->errors);
    }

    public function testUpdateFaqEntryRejectsQuestionOver200Chars(): void
    {
        $addResult = $this->service->addFaqEntry('Question?', 'Answer.');
        $entryId = $addResult->data['entry_id'];

        $longQuestion = str_repeat('x', 201);
        $result = $this->service->updateFaqEntry($entryId, $longQuestion, 'Updated answer.');

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('question', $result->errors);
    }

    public function testUpdateFaqEntryRejectsAnswerOver2000Chars(): void
    {
        $addResult = $this->service->addFaqEntry('Question?', 'Answer.');
        $entryId = $addResult->data['entry_id'];

        $longAnswer = str_repeat('y', 2001);
        $result = $this->service->updateFaqEntry($entryId, 'Updated question?', $longAnswer);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('answer', $result->errors);
    }

    public function testUpdateFaqEntryReturnsErrorForNonExistentEntry(): void
    {
        $result = $this->service->updateFaqEntry(9999, 'Question?', 'Answer.');

        $this->assertFalse($result->success);
        $this->assertEquals('ENTRY_NOT_FOUND', $result->errorCode);
    }

    public function testDeleteFaqEntrySuccess(): void
    {
        $addResult = $this->service->addFaqEntry('To be deleted?', 'Will be removed.');
        $entryId = $addResult->data['entry_id'];

        $result = $this->service->deleteFaqEntry($entryId);

        $this->assertTrue($result->success);
        $this->assertEquals($entryId, $result->data['entry_id']);

        // Verify deletion
        $entries = $this->service->getFaqEntries();
        $this->assertEmpty($entries);
    }

    public function testDeleteFaqEntryReturnsErrorForNonExistentEntry(): void
    {
        $result = $this->service->deleteFaqEntry(9999);

        $this->assertFalse($result->success);
        $this->assertEquals('ENTRY_NOT_FOUND', $result->errorCode);
    }

    // --- Pricing Content Tests ---

    public function testGetPricingContentReturnsEmptyArrayWhenNoContent(): void
    {
        $result = $this->service->getPricingContent();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetPricingContentReturnsStoredContent(): void
    {
        $data = [
            'service_names' => ['Storage', 'Shipping'],
            'prices' => ['$50/month', '$200/shipment'],
            'plan_descriptions' => ['Basic vault storage', 'Insured shipping'],
        ];

        $this->service->updatePricingContent($data, 1);

        $result = $this->service->getPricingContent();

        $this->assertEquals(['Storage', 'Shipping'], $result['service_names']);
        $this->assertEquals(['$50/month', '$200/shipment'], $result['prices']);
        $this->assertEquals(['Basic vault storage', 'Insured shipping'], $result['plan_descriptions']);
    }

    public function testUpdatePricingContentSuccess(): void
    {
        $data = [
            'service_names' => ['Storage'],
            'prices' => ['$100'],
            'plan_descriptions' => ['Premium storage'],
        ];

        $result = $this->service->updatePricingContent($data, 1);

        $this->assertTrue($result->success);
        $this->assertEquals('pricing', $result->data['page_key']);
    }

    public function testUpdatePricingContentRejectsEmptyServiceNames(): void
    {
        $data = [
            'service_names' => '',
            'prices' => ['$100'],
            'plan_descriptions' => ['Description'],
        ];

        $result = $this->service->updatePricingContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('service_names', $result->errors);
    }

    public function testUpdatePricingContentRejectsEmptyPrices(): void
    {
        $data = [
            'service_names' => ['Storage'],
            'prices' => '',
            'plan_descriptions' => ['Description'],
        ];

        $result = $this->service->updatePricingContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('prices', $result->errors);
    }

    public function testUpdatePricingContentRejectsEmptyPlanDescriptions(): void
    {
        $data = [
            'service_names' => ['Storage'],
            'prices' => ['$100'],
            'plan_descriptions' => '',
        ];

        $result = $this->service->updatePricingContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertArrayHasKey('plan_descriptions', $result->errors);
    }

    public function testUpdatePricingContentRejectsAllEmptyFields(): void
    {
        $data = [
            'service_names' => '',
            'prices' => '',
            'plan_descriptions' => '',
        ];

        $result = $this->service->updatePricingContent($data, 1);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertCount(3, $result->errors);
    }

    // --- Helper Methods ---

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE content_pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_key VARCHAR(50) NOT NULL UNIQUE,
                content TEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_by INTEGER DEFAULT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE faq_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question VARCHAR(200) NOT NULL,
                answer TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
}
