# Events

## `BlocksmithLog`

```php
use VanDmade\Blocksmith\Events\BlocksmithLog;

BlocksmithLog::dispatch('error', 'Something went wrong', [
    'revision_id' => $revision->id,
    'document_id' => $document->id,
    'signing_key_id' => $signingKey->id,
    'anchor_batch_id' => $anchorBatch->id,
    'file' => __FILE__,
    'line' => __LINE__,
    'anything_else' => 'lands in the metadata column',
]);
```

The log event (`$type`, `$message`, `$context = []`). Instead of writing straight into the native `Log` facade, `LogListener` writes a real row into `blocksmith_logs`, because that table actually has structure to it - foreign keys to whatever document, revision, signing key, or anchor batch the log entry is about. Recognized `$context` keys (`revision_id`, `document_id`, `signing_key_id`, `anchor_batch_id`, `file`, `line`) map onto their own columns; everything else just lands in `metadata`.

`event` and `level` both get the same `$type` value right now, since the event only carries the one identifier. If you want those to mean different things, `LogListener::handleLog()` is the place to change it.

## Every job and command logs its own exceptions

Every job, console command, and controller action wraps its own work in a try/catch and dispatches `BlocksmithLog` if anything throws. What happens after that depends on where it happened:

- **Jobs** (`SubmitAnchorBatchJob`, `CheckAnchorBatchThresholdJob`, `CheckAnchorConfirmationJob`) log it and then re-throw the exact same exception. They're triggered by your own queue worker, so it's still your call what happens next - retry, `failed_jobs`, alerting, whatever you want... Blocksmith just makes sure it's visible in its own log too, on top of whatever your app already does with it.
- **Commands and controllers** (`blocksmith:verify-document`, `blocksmith:add-organization-scoping`) log it and return the same controlled failure they already would - a non-zero exit code, or a JSON error response. There's already someone waiting on that return value, so it doesn't make sense to throw a raw exception at them instead.

## `AnchorBatchComplete`

```php
class AnchorBatchComplete
{
    public function __construct(public readonly AnchorBatch $anchorBatch) {}
}
```

Fires from `AnchorBatchService::create()` once its transaction actually commits - never before, so nothing can see a batch that isn't durable yet.

## `DocumentUploaded`

```php
class DocumentUploaded
{
    public function __construct(
        public readonly Document $document,
        public readonly Revision $revision,
    ) {}
}
```

Fires from `DocumentService::revise()` (and `create()`, since it just calls `revise()`) once its transaction commits. Fires for every new revision, not just the very first one.

## `DocumentLinkMissing`

```php
class DocumentLinkMissing
{
    public function __construct(public readonly Revision $revision) {}
}
```

Fires from `DocumentIntegrityVerifier::verifyChain()` when a revision has no document attached to it at all. Since the core doesn't know about documents, a bare revision with nothing linked is completely normal, not an error - the chain check still runs and stands on its own, the content check just gets skipped, and this event is how that gets surfaced instead of silently (or wrongly) failing a revision that was never actually broken.

## Listening for these

None of the four have a listener built in besides `BlocksmithLog`'s own `LogListener`. Wire up your own the normal Laravel way - `Event::listen(AnchorBatchComplete::class, ...)`, or a listener class subscribed the same way `LogListener` is.

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md)
- [Verification](06-verification.md)
