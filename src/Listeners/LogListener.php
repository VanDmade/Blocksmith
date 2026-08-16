<?php

namespace VanDmade\Blocksmith\Listeners;

use Illuminate\Events\Dispatcher;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Log;

class LogListener
{

    public function handleLog(BlocksmithLog $event): void
    {
        $context = $event->context;

        Log::create([
            'event' => $event->type,
            'level' => $event->type,
            'message' => $event->message,
            'blocksmith_revision_id' => $context['revision_id'] ?? null,
            'blocksmith_document_id' => $context['document_id'] ?? null,
            'blocksmith_signing_key_id' => $context['signing_key_id'] ?? null,
            'blocksmith_anchor_batch_id' => $context['anchor_batch_id'] ?? null,
            'file' => $context['file'] ?? null,
            'line' => $context['line'] ?? null,
            'metadata' => array_diff_key($context, array_flip([
                'revision_id',
                'document_id',
                'signing_key_id',
                'anchor_batch_id',
                'file',
                'line',
            ])),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            BlocksmithLog::class => 'handleLog',
        ];
    }

}
