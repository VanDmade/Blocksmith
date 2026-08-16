# Hash Chaining

Every revision's `hash` gets built from exactly two things: the previous revision's `hash`, plus this revision's own `content_hash`. That's the whole secret in the madness - change a revision anywhere in the middle of the history, and every hash after it stops matching, because each one was built from what came before it. Some might call this ALMOST tamper proof (With the other tools we get to 95% tamper proof... Unless you higher a malicious person that does bad things for bad people!)

## `HasherService`

```php
use VanDmade\Blocksmith\Services\HasherService;

app(HasherService::class)->hashContent($stringOrStreamResource); // hashes the actual file content
app(HasherService::class)->computeHash($previousHash, $contentHash); // hash($algorithm, ($previousHash ?? '') . $contentHash)
```

`hashContent()` takes a string or a stream, and reads it in chunks (`blocksmith.chunk_size`, 8192 bytes by default) instead of pulling the whole file into memory - matters once files get big (Big files... A LOT OF DATA). `computeHash()` is what actually builds a revision's `hash` from its `previous_hash` and `content_hash`. The very first revision passes `null` for `previous_hash`, which just gets treated as an empty string.

The chosen hasing algorithm is just a config value (`blocksmith.hash_algorithm`, `sha256` by default).

## What this catches, and what it doesn't

Hash chaining catches anyone who edits a revision (or the file it points to) without recomputing everything after it - which is expensive to do quietly, and leaves an obvious break the second anyone checks. What it doesn't catch: a database admin with full access who edits a revision *and* recomputes every hash after it to keep the chain looking consistent. The whole chain would still check out. (For someone that really wants a document to disappear...) That's exactly why external anchoring exists (Bye bye tamperer)- see [External Anchoring](03-external-anchoring.md).

## Checking the chain

`DocumentIntegrityVerifier::verifyChain()` recomputes a revision's hash from its stored `previous_hash`/`content_hash` and compares it to the stored `hash`. If `blocksmith.verify_content` is on, it also re-hashes the actual file on disk and compares that too - which catches someone swapping the file on the filesystem without touching the database. (A tamperer that doesn't understand blockchain implemention) More on that in [Verification](06-verification.md).

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md)
- [Verification](06-verification.md)
