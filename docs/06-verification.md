# Verification

`DocumentIntegrityVerifier` is the only implementation of `IntegrityVerifierInterface` (Currently) - there's no separate document-agnostic wrapper class underneath it, it just implements the interface directly. The interface stays around anyway as a formal contract, even with just one implementation. You can create your own verifier for the resource you'd like to tie for Blocksmith.

```php
use VanDmade\Blocksmith\Verification\DocumentIntegrityVerifier;

$verifier = app(DocumentIntegrityVerifier::class);

$verifier->verifyChain($revision);       // bool
$verifier->verifyMerkleRoot($revision);  // bool
$verifier->verifyAnchorBatch($revision); // bool - cheap, local status check only
$verifier->verifySignature($revision);   // bool

$result = $verifier->verify($revision);  // Runs all four, gives you a VerificationResult
```

## The four checks

- **`verifyChain()`** recomputes `hash` from `previous_hash` + `content_hash` and compares it. If `blocksmith.verify_content` is on (it is by default), it also re-hashes the real file on disk and compares that - which catches a file getting swapped on the filesystem that nothing else would notice. If a revision has no document linked to it at all, the content check just gets skipped and `DocumentLinkMissing` fires instead - that's not a failure, just a bare revision with nothing attached, which is a normal thing given the core doesn't care about documents.
- **`verifyMerkleRoot()`** takes the revision's hash, walks it through its stored proof, and checks the result against the batch's root. It never rebuilds the proof (As it really shouldn't change) - see [why](02-merkle-batching.md#why-proofs-never-get-regenerated).
- **`verifyAnchorBatch()`** just checks `status === CONFIRMED` locally. No network call. See below for why the real check lives somewhere else. (The network call is very heavy on the server so it's its own job)
- **`verifySignature()`** hands off to `RevisionService::verifySignature()`. A revision with no signature, or no linked key, fails.

## `VerificationResult`

```php
$result->passed();             // true only if nothing failed
$result->checkFailed('chain'); // Ask about one specific check
$result->getErrors();          // Every failure message, in the order the checks ran
```

`verify()` runs all four checks and calls `fail()` for each one that comes back false, so you get every problem back at once instead of stopping at the first thing that's wrong.

## Why the expensive check isn't in here

Actually re-fetching the real root from OpenTimestamps to catch local database tampering is the check that really matters for security - but it's a real network call, and there's no reason to pay for it on every single `verify()` call when nothing's usually wrong. So it's split in two:

- **Cheap and local, runs every time** - `verifyAnchorBatch()`, just checking the stored status. (You can change this with a config value `blocksmith.verify_content`)
- **Expensive and live, runs on a schedule or on demand** - re-fetching from OpenTimestamps. That's meant to live in `VerifyAnchorBatchJob` eventually, but it's still an empty stub. `blocksmith:verify-document` does the equivalent live check itself right now, since someone manually asking to verify one document is exactly the case where paying for it is worth it.

That's also why `blocksmith:verify-document` only hard-fails on `chain`/`merkle_root`/`signature`, and deliberately not on the cheap `verifyAnchorBatch()` check - a batch that's actually confirmed on Bitcoin but just hasn't synced its local status yet still gets a fair shot at the live check, instead of failing on stale data. If the live check does confirm it, the command fixes the local status right then too, so nobody has to wait for the next scheduled confirmation job to catch up.

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md)
- [External Anchoring (OpenTimestamps)](03-external-anchoring.md)
- [Artisan Commands](08-artisan-commands.md)
