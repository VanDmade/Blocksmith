<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Models\SigningKey;
use VanDmade\Blocksmith\Services\AnchorBatchService;
use VanDmade\Blocksmith\Services\DocumentService;
use VanDmade\Blocksmith\Tests\Support\InteractsWithOpenTimestamps;
use VanDmade\Blocksmith\Tests\TestCase;

class VerifyDocumentCommandTest extends TestCase
{

    use InteractsWithOpenTimestamps;

    private function fakeCalendar(string $checkResponseBody): void
    {
        Http::fake([
            'alice.btc.calendar.opentimestamps.org/digest' => Http::response('pending-proof-bytes'),
            'alice.btc.calendar.opentimestamps.org/timestamp/*' => Http::response($checkResponseBody),
        ]);
    }

    private function signedDocument(
        string $content = 'hello world',
        string $path = 'documents/test.pdf'
    ): array {
        Storage::fake('local');
        Storage::disk('local')->put($path, $content);
        $keypair = sodium_crypto_sign_keypair();
        $signingKey = SigningKey::create([
            'public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'private_key' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
            'custodial' => true,
        ]);
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            hash('sha256', $content),
            [
                'disk' => 'local',
                'path' => $path,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($content),
            ],
            $signingKey
        );
        return [$document, $document->currentRevision];
    }

    public function test_fails_when_the_uuid_does_not_exist(): void
    {
        $this->artisan('blocksmith:verify-document', ['uuid' => 'not-a-real-uuid'])
            ->assertExitCode(1);
    }

    public function test_verifies_a_fully_valid_confirmed_revision_successfully(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, $revision] = $this->signedDocument();
        app(AnchorBatchService::class)->create([$revision]);
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid])
            ->assertExitCode(0);
    }

    public function test_fails_when_the_file_content_no_longer_matches(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, $revision] = $this->signedDocument('original content', 'documents/tampered.pdf');
        app(AnchorBatchService::class)->create([$revision]);
        Storage::disk('local')->put('documents/tampered.pdf', 'swapped content');
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid])
            ->assertExitCode(1);
    }

    public function test_catches_tampered_content_through_its_own_fallback_when_verify_content_is_disabled(): void
    {
        config(['blocksmith.verify_content' => false]);
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, $revision] = $this->signedDocument('original content', 'documents/tampered.pdf');
        app(AnchorBatchService::class)->create([$revision]);
        Storage::disk('local')->put('documents/tampered.pdf', 'swapped content');
        // This command WILL check whether or not the document's content has been tampered with
        // The verify_content config option only stops the verification within the integrity verifier
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid])
            ->assertExitCode(1);
    }

    public function test_fails_when_the_revision_has_not_been_anchored_yet(): void
    {
        [$document] = $this->signedDocument();
        // The document doesn't have an anchor batch attached yet, so nothing to verify
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid])
            ->assertExitCode(1);
    }

    public function test_fails_when_the_live_provider_still_reports_the_root_as_pending(): void
    {
        $this->fakeCalendar($this->pendingCalendarResponseBody());
        [$document, $revision] = $this->signedDocument();
        app(AnchorBatchService::class)->create([$revision]);
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid])
            ->assertExitCode(1);
    }

    public function test_confirms_the_local_anchor_batch_when_the_live_check_proves_it_first(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, $revision] = $this->signedDocument();
        $anchorBatch = app(AnchorBatchService::class)->create([$revision]);
        $this->assertTrue($anchorBatch->status === AnchorBatchStatus::SUBMITTED);
        // Verify document command allows for the anchor batch to be confirmed
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid])
            ->assertExitCode(0);
        $this->assertTrue($anchorBatch->fresh()->status === AnchorBatchStatus::CONFIRMED);
    }

    public function test_full_flag_verifies_every_revision_in_the_documents_history(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        Storage::fake('local');
        Storage::disk('local')->put('documents/v1.pdf', 'v1');
        Storage::disk('local')->put('documents/v2.pdf', 'v2');
        $keypair = sodium_crypto_sign_keypair();
        $signingKey = SigningKey::create([
            'public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'private_key' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
            'custodial' => true,
        ]);
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            hash('sha256', 'v1'),
            [
                'disk' => 'local',
                'path' => 'documents/v1.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 2,
            ],
            $signingKey
        );
        $firstRevision = $document->currentRevision;
        $document = app(DocumentService::class)->revise(
            $document,
            hash('sha256', 'v2'),
            [
                'disk' => 'local',
                'path' => 'documents/v2.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 2,
            ],
            $signingKey
        );
        $secondRevision = $document->currentRevision;
        app(AnchorBatchService::class)->create([$firstRevision, $secondRevision]);
        $this->artisan('blocksmith:verify-document', [
            'uuid' => $document->uuid,
            '--full' => true,
        ])->assertExitCode(0);
    }

    /**
     * @return array{0: Document, 1: Revision, 2: Revision}
     */
    private function twoRevisionSignedDocument(): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/v1.pdf', 'v1');
        Storage::disk('local')->put('documents/v2.pdf', 'v2');
        $keypair = sodium_crypto_sign_keypair();
        $signingKey = SigningKey::create([
            'public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'private_key' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
            'custodial' => true,
        ]);
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            hash('sha256', 'v1'),
            [
                'disk' => 'local',
                'path' => 'documents/v1.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 2,
            ],
            $signingKey
        );
        $firstRevision = $document->currentRevision;
        $document = app(DocumentService::class)->revise(
            $document,
            hash('sha256', 'v2'),
            [
                'disk' => 'local',
                'path' => 'documents/v2.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 2,
            ],
            $signingKey
        );
        $secondRevision = $document->currentRevision;
        app(AnchorBatchService::class)->create([$firstRevision, $secondRevision]);
        return [$document, $firstRevision, $secondRevision];
    }

    public function test_diagnose_flag_reports_no_tampering_when_the_whole_history_is_clean(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document] = $this->twoRevisionSignedDocument();

        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid, '--diagnose' => true])
            ->expectsOutputToContain('verified successfully - no tampering found')
            ->assertExitCode(0);
    }

    public function test_diagnose_flag_implies_the_full_history_even_without_the_full_flag(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, , $secondRevision] = $this->twoRevisionSignedDocument();
        // Only the second revision is tampered - if --diagnose didn't imply --full,
        // the loop would stop after checking just the first (clean) revision and never
        // reach this one, incorrectly reporting a clean bill of health.
        $secondRevision->hash = str_repeat('f', 64);
        $secondRevision->save();
        $this->artisan('blocksmith:verify-document', [
                'uuid' => $document->uuid,
                '--diagnose' => true,
            ])
            ->expectsOutputToContain('tampered revision(s): 2')
            ->assertExitCode(1);
    }

    public function test_diagnose_flag_reports_every_tampered_revision_instead_of_stopping_at_the_first(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, $firstRevision, $secondRevision] = $this->twoRevisionSignedDocument();
        // Tampers both revisions directly - simulates a fully rewritten chain, not just one isolated bad revision.
        $firstRevision->hash = str_repeat('f', 64);
        $firstRevision->save();
        $secondRevision->hash = str_repeat('e', 64);
        $secondRevision->save();
        $this->artisan('blocksmith:verify-document', [
                'uuid' => $document->uuid,
                '--diagnose' => true,
            ])
            ->expectsOutputToContain('tampered revision(s): 1, 2')
            ->assertExitCode(1);
    }

    public function test_without_diagnose_it_still_stops_at_the_first_failure(): void
    {
        $this->fakeCalendar($this->confirmedCalendarResponseBody());
        [$document, $firstRevision, $secondRevision] = $this->twoRevisionSignedDocument();
        $firstRevision->hash = str_repeat('f', 64);
        $firstRevision->save();
        $secondRevision->hash = str_repeat('e', 64);
        $secondRevision->save();
        // No --diagnose, but --full is on - it should still bail after revision #1
        $this->artisan('blocksmith:verify-document', ['uuid' => $document->uuid, '--full' => true])
            ->doesntExpectOutputToContain('Verifying document revision 2.')
            ->assertExitCode(1);
    }

}
