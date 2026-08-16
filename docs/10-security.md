# Security

## The threat model, short version

- **Hash chaining** catches anyone who edits a revision without recomputing every hash after it.
- **Write-once Merkle proofs** let you point to the exact revision that got tampered with, instead of just "something in this batch is wrong" - but only if the proof itself genuinely can't be rewritten (see below).
- **External Bitcoin anchoring** is the ONLY piece that catches a fully privileged person who rewrites the whole chain *and* recomputes every hash to keep it looking consistent. It's the only layer backed by something the admin doesn't control. See [External Anchoring](03-external-anchoring.md).

Each layer exists because the one below it has a gap that only the layer above can close. None of them alone tells the whole story. But together, like the Infinity Gauntlet... Can change the world!

## Locking down `blocksmith_revision_proofs`

Each revision's Merkle proof gets written once, when its batch is built, and it's never supposed to change again. This is what makes it possible to say exactly which revision got tampered with instead of just "something in this batch is wrong." That only actually holds if the row genuinely can't be edited afterward.

Out of the box, it's just a normal table, so your app's own database user can update or delete rows in it like anything else. To actually make it write-once, restrict that user's permissions on this one table so it can only insert and read. For MySQL:

```sql
REVOKE UPDATE, DELETE ON your_database.blocksmith_revision_proofs FROM 'your_app_user'@'%';
```

(Same idea on Postgres or whatever database you're running - the app's regular credentials should never be able to touch a row here once it's written.)

Be realistic about what this actually protects against. It stops your own app's credentials from rewriting a proof, even if those credentials get compromised through the app itself. It does **not** stop someone with real server or database-admin access - they could change the grants themselves or edit the files directly. That's exactly why the real guarantee is the Bitcoin anchoring, not this. This permission restriction just raises the bar for casual, application-layer tampering.

## Signing keys are encrypted at rest

Custodial private keys get stored with Laravel's `encrypted` cast, so they're encrypted using your `APP_KEY` before they ever hit the database, and decrypted transparently when read. Nothing about how `RevisionService`/`Ed25519Signer` use them changes - they still just read `$signingKey->private_key` as a plain string.

One thing worth knowing if you're upgrading an existing install: this cast doesn't retroactively encrypt anything already sitting in the database in plain text. Adding the cast only changes how things get written and read from that point forward. If you've already got real custodial keys stored, re-save each one (`$key->save()`) once the cast is in place to actually encrypt it - a small one-off command or migration is the easiest way to do that in bulk.

## HTTP layer

Routes are wired now (`routes.php`), gated behind `can:manage-blocksmith` - by default that just means "logged in," same pattern as Hookamatic's `manage-hookamatic` gate. Override it in your own app if you need something more specific, like admin-only.

Route-model binding for `Document`/`Revision` resolves by `uuid`, not the internal auto-increment `id` - keeps the same identifier the CLI already uses (`blocksmith:verify-document`) and avoids exposing sequential internal ids over HTTP.

## Downloading a document's file

`DocumentController::get()` returns a temporary `download_url` alongside the document, valid for `blocksmith.download_url_expiry_minutes` (5 minutes by default). It's a signed route (`URL::temporarySignedRoute()`), not a call to `Storage::temporaryUrl()` - that matters because the `local` disk driver (the default) doesn't support `temporaryUrl()` at all, it throws if you try. Using a signed route instead of the disk's own temporary-URL mechanism means downloads work the same way regardless of which disk is configured.

Because the signature itself is what authenticates the request, the download route deliberately sits outside the `can:manage-blocksmith` group - anyone holding a valid, unexpired link can use it, the same as any signed URL. Once it expires, `signed` middleware rejects it with a 403 automatically.

Package routes registered via `loadRoutesFrom()` don't automatically get `SubstituteBindings` the way a normal app's routes do - it has to be added explicitly per route/group, or route-model binding silently doesn't happen. Worth remembering if you add more routes outside the main `blocksmith.` group later.

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md#why-proofs-never-get-regenerated)
- [Signing](04-signing.md)
- [External Anchoring (OpenTimestamps)](03-external-anchoring.md)
