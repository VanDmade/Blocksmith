<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Tests\TestCase;

class DocumentRevisionLinkTest extends TestCase
{

    public function test_document_and_revision_link_through_the_pivot(): void
    {
        $document = Document::create([
            'name' => 'Test Document',
        ]);
        $revision = Revision::create([
            'content_hash' => str_repeat('a', 64),
            'hash' => str_repeat('b', 64),
        ]);
        $document->revisions()->attach($revision->id, [
            'disk' => 'local',
            'path' => 'documents/test.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'metadata' => ['original_name' => 'test.pdf'],
        ]);
        $documentExists = $document->revisions()
            ->where('blocksmith_revisions.id', '=', $revision->id)
            ->exists();
        $this->assertTrue($documentExists);
        $pivot = $document->revisions()->first()->pivot;
        $this->assertSame('documents/test.pdf', $pivot->path);
        $this->assertSame('application/pdf', $pivot->mime_type);
        $this->assertTrue($revision->documents()
            ->where('blocksmith_documents.id', '=', $document->id)
            ->exists());
        $document->current_blocksmith_revision_id = $revision->id;
        $document->save();
        $this->assertTrue($document->currentRevision->is($revision));
    }

}
