# Documents & Revisions

`blocksmith_revisions` doesn't really know about the documents really... It's just a cryptographic record - `hash`, `previous_hash`, `content_hash`, `signature`, and a few foreign keys. `blocksmith_documents` links into it through a `blocksmith_document_revisions` pivot table, which is where all the document-specific stuff lives - `disk`, `path`, `extension`, `mime_type`, `size`, `metadata`. That's the same shape any other resource would use to plug into the chain later. Documents aren't special, even though they will likely be the main use of this package, they're just the first thing built on top of it.

## `RevisionService`

The part that doesn't know or care what a document is:

```php
use VanDmade\Blocksmith\Services\RevisionService;

$revision = app(RevisionService::class)->create(
    previousHash: $document->currentRevision?->hash,
    contentHash: hash('sha256', $content),
    revisionNumber: ($document->currentRevision?->revision_number ?? 0) + 1,
    signingKey: $signingKey, // optional
    revisionReason: 'Quarterly update', // optional
);
```

`revisionNumber` has to be passed in - `RevisionService` has no way to figure out "the next number" on its own since it doesn't know about parents at all. `attachSignature()`, `verifySignature()`, and `delete()` round it out, and all three take either a `Revision` model or just an ID. (Simplicity)

## `DocumentService`

The document-specific wrapper around all that:

```php
use VanDmade\Blocksmith\Services\DocumentService;

$document = app(DocumentService::class)->create(
    ['name' => 'Employee Handbook'],
    hash('sha256', $content),
    [
        'disk' => 'local',
        'path' => 'documents/v1.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size' => strlen($content),
    ],
    $signingKey, // Optional
);

$document = app(DocumentService::class)
    ->revise(
        $document,
        hash('sha256', $newContent),
        $newPivotAttributes,
        $signingKey
    );
```

`create()` just makes the `Document` row and hands off to `revise()` - and it works correctly for that very first revision because `$document->currentRevision` is null right after creation, so `revise()`'s normal logic just naturally produces the right starting values (no `previous_hash`, `revision_number` of 1) without needing any special case for "this is the first one."

Both `create()` and `revise()` fire `DocumentUploaded` (with the `Document` and `Revision`) once their work is actually saved - see [Events](07-events.md).

A few other methods worth knowing: `find()`/`findByUuid()`, `getCurrentRevision()`, `getRevisionHistory()` (ordered properly, and it includes soft-deleted revisions on purpose - a real audit history shouldn't quietly drop a deleted one), and `delete()` (soft-deletes the document).

## Hooking up your own resource type

None of the actual chain/batch/anchor/sign logic cares about documents specifically. To plug in something else:

1. Give it its own pivot table linking to `blocksmith_revisions`, with whatever fields actually make sense for it.
2. Call `RevisionService::create()` directly, the same way `DocumentService::revise()` does.
3. Create your own `verifyContent()` method to help teh system tie into what you've created to verify your content.

## See also

- [Hash Chaining](01-hash-chaining.md)
- [Signing](04-signing.md)
- [Verification](06-verification.md)
