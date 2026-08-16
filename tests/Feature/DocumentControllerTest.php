<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Services\DocumentService;
use VanDmade\Blocksmith\Tests\TestCase;

class DocumentControllerTest extends TestCase
{

    private function actingAsAUser(): void
    {
        $userModel = config('auth.providers.users.model');
        $user = new $userModel();
        $user->id = 1;
        $user->name = 'Test User';
        $user->email = 'test@example.test';
        $user->exists = true;
        $this->actingAs($user);
    }

    public function test_get_returns_the_actual_requested_document_not_an_arbitrary_one(): void
    {
        $this->actingAsAUser();
        Storage::fake('local');
        Storage::disk('local')->put('documents/a.pdf', 'a');
        Storage::disk('local')->put('documents/b.pdf', 'b');
        $documentA = app(DocumentService::class)->create(
            ['name' => 'Document A'],
            hash('sha256', 'a'),
            ['disk' => 'local', 'path' => 'documents/a.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 1]
        );
        $documentB = app(DocumentService::class)->create(
            ['name' => 'Document B'],
            hash('sha256', 'b'),
            ['disk' => 'local', 'path' => 'documents/b.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 1]
        );
        $response = $this->getJson('/blocksmith/document/'.$documentB->uuid);
        $response->assertOk();
        $this->assertSame($documentB->uuid, $response->json('document.uuid'));
        $this->assertSame('Document B', $response->json('document.name'));
    }

    public function test_get_includes_a_working_download_url(): void
    {
        $this->actingAsAUser();
        Storage::fake('local');
        Storage::disk('local')->put('documents/a.pdf', 'the real file content');
        $document = app(DocumentService::class)->create(
            ['name' => 'Document A'],
            hash('sha256', 'the real file content'),
            ['disk' => 'local', 'path' => 'documents/a.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 21]
        );
        $response = $this->getJson('/blocksmith/document/'.$document->uuid);
        $downloadUrl = $response->json('file.download_url');
        $this->assertNotNull($downloadUrl, 'Expected a download_url in the response.');
        $download = $this->get($downloadUrl);
        $download->assertOk();
        $this->assertSame('the real file content', $download->streamedContent());
    }

    public function test_download_rejects_an_unsigned_url(): void
    {
        $this->actingAsAUser();
        Storage::fake('local');
        Storage::disk('local')->put('documents/a.pdf', 'content');
        $document = app(DocumentService::class)->create(
            ['name' => 'Document A'],
            hash('sha256', 'content'),
            ['disk' => 'local', 'path' => 'documents/a.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 7]
        );
        // Attempt to download without a signature, which should be rejected.
        $response = $this->get('/blocksmith/document/'.$document->uuid.'/download');
        $response->assertForbidden();
    }

}
