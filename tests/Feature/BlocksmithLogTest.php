<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Log;
use VanDmade\Blocksmith\Tests\TestCase;

class BlocksmithLogTest extends TestCase
{

    public function test_dispatching_blocksmith_log_writes_a_row_through_the_listener(): void
    {
        BlocksmithLog::dispatch('error', 'something went wrong', [
            'revision_id' => 7,
            'document_id' => 3,
            'file' => 'SomeFile.php',
            'line' => 42,
            'extra_detail' => 'lands in metadata',
        ]);
        $log = Log::first();
        $this->assertNotNull($log);
        $this->assertSame('something went wrong', $log->message);
        $this->assertSame(7, $log->blocksmith_revision_id);
        $this->assertSame(3, $log->blocksmith_document_id);
        $this->assertSame('SomeFile.php', $log->file);
        $this->assertSame(42, $log->line);
        $this->assertSame(['extra_detail' => 'lands in metadata'], $log->metadata);
    }

    public function test_the_type_populates_both_the_event_and_level_columns(): void
    {
        BlocksmithLog::dispatch('warning', 'a warning message');
        $log = Log::first();
        $this->assertSame('warning', $log->event);
        $this->assertSame('warning', $log->level);
    }

    public function test_context_without_any_recognized_keys_leaves_the_foreign_keys_null(): void
    {
        BlocksmithLog::dispatch('info', 'plain message');
        $log = Log::first();
        // No context was sent so nothing should appear for these.
        $this->assertNull($log->blocksmith_revision_id);
        $this->assertNull($log->blocksmith_document_id);
        $this->assertNull($log->blocksmith_signing_key_id);
        $this->assertNull($log->blocksmith_anchor_batch_id);
        $this->assertSame([], $log->metadata);
    }

}
