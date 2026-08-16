# Artisan Commands

## `blocksmith:verify-document`

```bash
php artisan blocksmith:verify-document {uuid}
php artisan blocksmith:verify-document {uuid} --full
php artisan blocksmith:verify-document {uuid} --diagnose
```

Looks up a document by UUID and checks its revision history. Without `--full` it only checks the current revision, with it, every revision in the history gets checked in order, and it stops at the first one that fails. So it could be quite lengthy, do this with caution...

`--diagnose` is for when you already know (or suspect) a document is bad and want to know exactly which revision - it implies `--full` even if you don't pass it too, and unlike the normal run, it doesn't stop at the first failure. It walks the entire history, notes every revision that fails a check, and reports all of them at the end (`Document X has 2 tampered revision(s): 3, 4.`). That distinction matters because tampering can cascade: if someone rewrites a revision's hash *and* recomputes every hash after it to keep the chain internally consistent, the isolated `verifyChain()` check on those later revisions can still pass - `verifyMerkleRoot()` is what catches them, since their write-once proofs no longer match. Stopping at the first failure would tell you where the damage starts, but not how far it goes; `--diagnose` shows the whole picture.

For each revision it checks:

1. `DocumentIntegrityVerifier::verify()` - hard-fails right away on `chain`, `merkle_root`, or `signature` problems. The cheap local `anchor_batch` check on its own doesn't stop things here on purpose, see [Verification](06-verification.md#why-the-expensive-check-isnt-in-here).
2. If `blocksmith.verify_content` is turned off (by default), this command checks the content anyway - it always wants to be sure the file actually matches, even when that's turned off for cheaper verifier processes.
3. Fails if the revision hasn't been anchored yet at all.
4. Calls out to the real anchor provider to check the batch's root. Fails if that comes back unconfirmed. If it comes back confirmed but the local status hasn't caught up yet, this command fixes that right there instead of waiting on the next scheduled confirmation check.

Exits `0` on success, `1` on any failure (including a UUID that doesn't exist) - normal exit code behavior for scripting.

## `blocksmith:add-organization-scoping`

```bash
php artisan blocksmith:add-organization-scoping
```

Adds an organization column to whatever tables you've listed under `blocksmith.tables` in config, for turning on multi-organization scoping after the base migrations already ran. Needs `organization_model` set in config first.

## See also

- [Verification](06-verification.md)
- [Configuration](09-configuration.md)
