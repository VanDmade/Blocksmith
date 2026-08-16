<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Event;
use VanDmade\Blocksmith\Events\DocumentUploaded;
use VanDmade\Blocksmith\Services\DocumentService;
use VanDmade\Blocksmith\Tests\TestCase;

class DocumentServiceTest extends TestCase
{

    private function pivotAttributes(string $path = 'documents/v1.pdf'): array
    {
        return [
            'disk' => 'local',
            'path' => $path,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }

    public function test_create_creates_a_document_with_a_genesis_revision(): void
    {
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            str_repeat('a', 64),
            $this->pivotAttributes()
        );
        $revision = $document->currentRevision;
        $this->assertNotNull($revision);
        $this->assertSame(1, $revision->revision_number);
        $this->assertNull($revision->previous_hash);
        $this->assertSame($document->id, $revision->documents->first()->id);
    }

    public function test_create_dispatches_document_uploaded(): void
    {
        Event::fake([DocumentUploaded::class]);
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            str_repeat('a', 64),
            $this->pivotAttributes()
        );
        Event::assertDispatched(DocumentUploaded::class, function(DocumentUploaded $event) use ($document) {
            return $event->document->is($document) &&
                $event->revision->is($document->fresh()->currentRevision);
        });
    }

    public function test_revise_chains_onto_the_current_revision(): void
    {
        $service = app(DocumentService::class);
        $document = $service->create(
            ['name' => 'Test Document'],
            str_repeat('a', 64),
            $this->pivotAttributes('v1.pdf')
        );
        $firstRevisionHash = $document->currentRevision->hash;
        $document = $service->revise(
            $document,
            str_repeat('b', 64),
            $this->pivotAttributes('v2.pdf')
        );
        $second = $document->currentRevision;
        $this->assertSame(2, $second->revision_number);
        $this->assertSame($firstRevisionHash, $second->previous_hash);
    }

    public function test_revise_updates_the_documents_current_revision(): void
    {
        $service = app(DocumentService::class);
        $document = $service->create(
            ['name' => 'Test Document'],
            str_repeat('a', 64),
            $this->pivotAttributes('v1.pdf')
        );
        $originalRevisionId = $document->currentRevision->id;
        $document = $service->revise(
            $document,
            str_repeat('b', 64),
            $this->pivotAttributes('v2.pdf')
        );
        $this->assertNotSame($originalRevisionId, $document->current_blocksmith_revision_id);
    }

    public function test_get_revision_history_is_ordered_by_revision_number_and_includes_soft_deleted(): void
    {
        $service = app(DocumentService::class);
        // Create a document (Which creates a revision)
        $document = $service->create(
            ['name' => 'Test Document'],
            str_repeat('a', 64),
            $this->pivotAttributes('v1.pdf')
        );
        // Creates revision B
        $document = $service->revise(
            $document,
            str_repeat('b', 64),
            $this->pivotAttributes('v2.pdf')
        );
        // Creates revision C
        $document = $service->revise(
            $document,
            str_repeat('c', 64),
            $this->pivotAttributes('v3.pdf')
        );
        $middleRevision = $document->revisions()->wherePivot('path', '=', 'v2.pdf')->first();
        $middleRevision->delete();
        $history = $service->getRevisionHistory($document);
        $this->assertCount(3, $history);
        $this->assertSame([1, 2, 3], $history->pluck('revision_number')->all());
    }

    public function test_find_by_uuid_returns_null_when_the_document_does_not_exist(): void
    {
        $this->assertNull(app(DocumentService::class)->findByUuid('not-a-real-uuid'));
    }

    public function test_find_by_uuid_finds_a_document(): void
    {
        $document = app(DocumentService::class)->create(
            ['name' => 'Findable Document'],
            str_repeat('a', 64),
            $this->pivotAttributes()
        );
        $found = app(DocumentService::class)->findByUuid($document->uuid);
        $this->assertTrue($found->is($document));
    }

    public function test_delete_soft_deletes_the_document(): void
    {
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            str_repeat('a', 64),
            $this->pivotAttributes()
        );
        $this->assertTrue(app(DocumentService::class)->delete($document));
        $this->assertSoftDeleted($document);
    }

}
