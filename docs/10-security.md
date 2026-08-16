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

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md#why-proofs-never-get-regenerated)
- [Signing](04-signing.md)
- [External Anchoring (OpenTimestamps)](03-external-anchoring.md)
