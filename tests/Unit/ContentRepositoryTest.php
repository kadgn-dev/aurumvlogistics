<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit;

use GOLS\Repositories\ContentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ContentRepository.
 *
 * Uses an in-memory SQLite database to test content and FAQ data access
 * without requiring a MySQL connection.
 */
class ContentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ContentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

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

        $this->repository = new ContentRepository($this->pdo);
    }

    // -------------------------------------------------------------------------
    // getByPageKey tests
    // -------------------------------------------------------------------------

    public function testGetByPageKeyReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->getByPageKey('nonexistent');

        $this->assertNull($result);
    }

    public function testGetByPageKeyReturnsDecodedContent(): void
    {
        $content = ['hero_text' => 'Welcome', 'services' => ['storage', 'shipping']];
        $this->insertContentPage('home', $content, 1);

        $result = $this->repository->getByPageKey('home');

        $this->assertNotNull($result);
        $this->assertEquals('home', $result['page_key']);
        $this->assertEquals($content, $result['content']);
        $this->assertEquals(1, $result['updated_by']);
    }

    public function testGetByPageKeyReturnsCorrectPageWhenMultipleExist(): void
    {
        $this->insertContentPage('home', ['title' => 'Home'], 1);
        $this->insertContentPage('pricing', ['title' => 'Pricing'], 2);

        $result = $this->repository->getByPageKey('pricing');

        $this->assertNotNull($result);
        $this->assertEquals('pricing', $result['page_key']);
        $this->assertEquals(['title' => 'Pricing'], $result['content']);
    }

    // -------------------------------------------------------------------------
    // upsert tests
    // -------------------------------------------------------------------------

    public function testUpsertCreatesNewRecord(): void
    {
        $content = ['hero_text' => 'Gold Vault', 'highlights' => ['secure', 'insured']];

        $result = $this->repository->upsert('home', $content, 1);

        $this->assertTrue($result);

        $row = $this->repository->getByPageKey('home');
        $this->assertNotNull($row);
        $this->assertEquals($content, $row['content']);
        $this->assertEquals(1, $row['updated_by']);
    }

    public function testUpsertUpdatesExistingRecord(): void
    {
        $this->repository->upsert('home', ['version' => 1], 1);

        $updatedContent = ['version' => 2, 'new_field' => 'added'];
        $result = $this->repository->upsert('home', $updatedContent, 2);

        $this->assertTrue($result);

        $row = $this->repository->getByPageKey('home');
        $this->assertEquals($updatedContent, $row['content']);
        $this->assertEquals(2, $row['updated_by']);
    }

    public function testUpsertPreservesOtherPages(): void
    {
        $this->repository->upsert('home', ['title' => 'Home'], 1);
        $this->repository->upsert('pricing', ['title' => 'Pricing'], 1);

        $this->repository->upsert('home', ['title' => 'Updated Home'], 2);

        $pricing = $this->repository->getByPageKey('pricing');
        $this->assertEquals(['title' => 'Pricing'], $pricing['content']);
    }

    // -------------------------------------------------------------------------
    // getFaqEntries tests
    // -------------------------------------------------------------------------

    public function testGetFaqEntriesReturnsEmptyArrayWhenNone(): void
    {
        $result = $this->repository->getFaqEntries();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFaqEntriesReturnsSortedBySortOrder(): void
    {
        $this->repository->createFaqEntry('Third?', 'Third answer', 3);
        $this->repository->createFaqEntry('First?', 'First answer', 1);
        $this->repository->createFaqEntry('Second?', 'Second answer', 2);

        $entries = $this->repository->getFaqEntries();

        $this->assertCount(3, $entries);
        $this->assertEquals('First?', $entries[0]['question']);
        $this->assertEquals('Second?', $entries[1]['question']);
        $this->assertEquals('Third?', $entries[2]['question']);
    }

    public function testGetFaqEntriesReturnsAllFields(): void
    {
        $this->repository->createFaqEntry('What is gold?', 'A precious metal.', 0);

        $entries = $this->repository->getFaqEntries();

        $this->assertCount(1, $entries);
        $this->assertArrayHasKey('id', $entries[0]);
        $this->assertArrayHasKey('question', $entries[0]);
        $this->assertArrayHasKey('answer', $entries[0]);
        $this->assertArrayHasKey('sort_order', $entries[0]);
        $this->assertArrayHasKey('created_at', $entries[0]);
        $this->assertArrayHasKey('updated_at', $entries[0]);
    }

    // -------------------------------------------------------------------------
    // createFaqEntry tests
    // -------------------------------------------------------------------------

    public function testCreateFaqEntryReturnsNewId(): void
    {
        $id = $this->repository->createFaqEntry('How to store gold?', 'Use our vault.', 1);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateFaqEntryStoresCorrectData(): void
    {
        $id = $this->repository->createFaqEntry('Is it insured?', 'Yes, fully insured.', 5);

        $entries = $this->repository->getFaqEntries();
        $entry = $entries[0];

        $this->assertEquals($id, (int) $entry['id']);
        $this->assertEquals('Is it insured?', $entry['question']);
        $this->assertEquals('Yes, fully insured.', $entry['answer']);
        $this->assertEquals(5, (int) $entry['sort_order']);
    }

    public function testCreateFaqEntryDefaultsSortOrderToZero(): void
    {
        $id = $this->repository->createFaqEntry('Default order?', 'Should be zero.');

        $entries = $this->repository->getFaqEntries();

        $this->assertEquals(0, (int) $entries[0]['sort_order']);
    }

    public function testCreateFaqEntryReturnsIncrementingIds(): void
    {
        $id1 = $this->repository->createFaqEntry('Q1?', 'A1');
        $id2 = $this->repository->createFaqEntry('Q2?', 'A2');

        $this->assertGreaterThan($id1, $id2);
    }

    // -------------------------------------------------------------------------
    // updateFaqEntry tests
    // -------------------------------------------------------------------------

    public function testUpdateFaqEntryReturnsTrueOnSuccess(): void
    {
        $id = $this->repository->createFaqEntry('Old question?', 'Old answer.');

        $result = $this->repository->updateFaqEntry($id, 'New question?', 'New answer.');

        $this->assertTrue($result);
    }

    public function testUpdateFaqEntryModifiesData(): void
    {
        $id = $this->repository->createFaqEntry('Original?', 'Original answer.', 1);

        $this->repository->updateFaqEntry($id, 'Updated?', 'Updated answer.');

        $entries = $this->repository->getFaqEntries();
        $this->assertEquals('Updated?', $entries[0]['question']);
        $this->assertEquals('Updated answer.', $entries[0]['answer']);
    }

    public function testUpdateFaqEntryReturnsFalseForNonexistentId(): void
    {
        $result = $this->repository->updateFaqEntry(9999, 'Q?', 'A.');

        $this->assertFalse($result);
    }

    public function testUpdateFaqEntryDoesNotAffectOtherEntries(): void
    {
        $id1 = $this->repository->createFaqEntry('Q1?', 'A1', 1);
        $id2 = $this->repository->createFaqEntry('Q2?', 'A2', 2);

        $this->repository->updateFaqEntry($id1, 'Updated Q1?', 'Updated A1');

        $entries = $this->repository->getFaqEntries();
        $this->assertEquals('Q2?', $entries[1]['question']);
        $this->assertEquals('A2', $entries[1]['answer']);
    }

    // -------------------------------------------------------------------------
    // deleteFaqEntry tests
    // -------------------------------------------------------------------------

    public function testDeleteFaqEntryReturnsTrueOnSuccess(): void
    {
        $id = $this->repository->createFaqEntry('To delete?', 'Will be deleted.');

        $result = $this->repository->deleteFaqEntry($id);

        $this->assertTrue($result);
    }

    public function testDeleteFaqEntryRemovesRecord(): void
    {
        $id = $this->repository->createFaqEntry('To delete?', 'Will be deleted.');

        $this->repository->deleteFaqEntry($id);

        $entries = $this->repository->getFaqEntries();
        $this->assertEmpty($entries);
    }

    public function testDeleteFaqEntryReturnsFalseForNonexistentId(): void
    {
        $result = $this->repository->deleteFaqEntry(9999);

        $this->assertFalse($result);
    }

    public function testDeleteFaqEntryDoesNotAffectOtherEntries(): void
    {
        $id1 = $this->repository->createFaqEntry('Keep?', 'Keep this.', 1);
        $id2 = $this->repository->createFaqEntry('Delete?', 'Delete this.', 2);

        $this->repository->deleteFaqEntry($id2);

        $entries = $this->repository->getFaqEntries();
        $this->assertCount(1, $entries);
        $this->assertEquals('Keep?', $entries[0]['question']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function insertContentPage(string $pageKey, array $content, int $updatedBy): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO content_pages (page_key, content, updated_by, updated_at)
             VALUES (:page_key, :content, :updated_by, :updated_at)'
        );
        $stmt->execute([
            ':page_key' => $pageKey,
            ':content' => json_encode($content),
            ':updated_by' => $updatedBy,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
