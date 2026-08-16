# Merkle Batching & Anchoring

We can't anchor every single revision because it is slow and basically pointless. Instead, pending revisions get built into a Merkle tree and only the *root* gets anchored. (We can build a Merkle Tree, example below, pretty cheaply) One Bitcoin transaction covers a whole batch, and each revision keeps a small proof (a few sibling hashes) that lets it get checked against that root without needing the rest of the batch around.

## `MerkleTreeService`

```php
use VanDmade\Blocksmith\Services\MerkleTreeService;

$tree = new MerkleTreeService();
// Odd counts duplicate the last leaf, same as Bitcoin does
$tree->build(['hash-a', 'hash-b', 'hash-c']);
$root = $tree->computeRoot();
// What leaf 0 needs to walk itself back up to the root
$proof = $tree->generateProof(0);
$tree->verify('hash-a', $proof, $root); // true
```

Tree leaves and internal nodes get hashed with different prefixes (`\x00` for leaves, `\x01` for pairs) before combining - this allows us to prevent confusing leaf hashses with internal note hashes. After a bit of research this prevents a real class of attack that nibbled on early Bitcoin implementations.

**A `MerkleTreeService` instance only works once.** `build()` doesn't reset its own state, so calling it twice on the same instance mixes old tree data into the new build. Always make a fresh instance per tree - `AnchorBatchService` already does this correctly.

## `AnchorBatchService`

```php
use VanDmade\Blocksmith\Services\AnchorBatchService;
// Array of Revision models
$anchorBatch = app(AnchorBatchService::class)->create($revisions);
```

`create()` builds the tree, submits the root through the configured `AnchorProviderInterface`, and creates the `AnchorBatch` row, links every revision to it, and stores each one's proof. The submission and the database writes happen together on purpose: if the external submission fails, nothing gets written locally either, so the whole batch just retries as one unit instead of leaving a half-finished mess behind. (There is no cleaning fairy to fix this) `AnchorBatchComplete` fires after the transaction commits - see [Events](07-events.md).

### Why proofs never get regenerated

Each revision's proof gets written once, when its batch is built, and that's it - the `blocksmith_revision_proofs` table doesn't even have an `updated_at` column, it's insert-only on purpose. Verifying a revision always checks its *current* hash against its *original* proof, never a freshly rebuilt one. Regenerating it would be expensive (you'd have to rebuild the whole tree just to check one leaf), and worse, it would ruin the whole point: if one revision got tampered with, rebuilding the tree bakes that tampering into every other revision's "recomputed" proof too, so all you'd ever learn is "something in this batch is wrong" - never which one. Keeping the original proof untouched is what lets you point to the exact tampered revision. See [Security](10-security.md) for locking that table down so it's actually write-once, not just write-once by convention.

## Example of the Merkle Tree Generation

A   B   C   D   E
 \  /    \  /   |
  AB      CD    EE
   \      /     |
    \    /      |
     ABCD      EEEE
       \       /
        \     /
        ABCDEEEE

## When batches actually go out

Two jobs handle this together:

- **`SubmitAnchorBatchJob`** does the actual submitting. It grabs every revision that isn't anchored yet, hands them to `AnchorBatchService::create()`, and just ends if there's nothing pending. It runs on `blocksmith.anchoring.batch_schedule` (a cron string, daily at midnight by default).
- **`CheckAnchorBatchThresholdJob`** is the early trigger. It checks how many revisions are pending and, if that hits `blocksmith.anchoring.batch_size_threshold` (500 by default), dispatches `SubmitAnchorBatchJob` right away instead of waiting for the schedule. It runs on its own interval (`threshold_check_interval`, 10 minutes by default) - turn that down if you're expecting a lot of volume and don't want a big backlog piling up. We sort them by ID so nothing will be forgotten in the long run.

There's also a floor on the other end: `SubmitAnchorBatchJob` won't submit a batch smaller than `minimum_revisions_per_batch` (2 by default) unless the oldest pending revision has already been waiting longer than `minimum_revisions_max_wait_minutes` (60 by default). This is just to prevent leaving a revision forever without an anchor being attached.

Both jobs get scheduled automatically in `BlocksmithServiceProvider::boot()`, so don't you worry there is nothing to do other than setting up the cron stuff. Just make sure that you run `php artisan schedule:work`.

## See also

- [External Anchoring (OpenTimestamps)](03-external-anchoring.md)
- [Verification](06-verification.md)
- [Configuration](09-configuration.md)
