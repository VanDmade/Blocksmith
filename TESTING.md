# Testing

For anyone that is working on BLocksmith... Thanks! If you're just using it, good, but this document really isn't for you so no worries and don't waste your time!

## Setup

From inside `packages/VanDmade/Blocksmith`:

```bash
composer install
```

It's a self-contained [Orchestra Testbench](https://packages.tool.dev/orchestra/testbench) package - it spins up its own throwaway Laravel app (in-memory SQLite), so no worries about your app and what you're building! (Blocksmith don't give a !@#$)

### The `sodium` extension

Anything that touches signing (`Ed25519Signer`, custodial `SigningKey`s, `verifySignature()`) needs the `sodium` PHP extension. It ships with PHP but it's not always turned on:

```bash
php -m | grep sodium
```

Nothing printed? Uncomment `extension=sodium` in your `php.ini` and restart. (I always have like 50 PHP versions installed so just check your PHP version first `php -v`) Without it, every signing test fails with `Call to undefined function sodium_crypto_sign_keypair()` - that's your environment, not a bug in the package. (I am perfect)

## Running tests

```bash
# Everything
vendor/bin/phpunit
# One file
vendor/bin/phpunit tests/Feature/DocumentIntegrityVerifierTest.php
# One test by name
vendor/bin/phpunit --filter {test-name}
```

`--filter` works together with a file path to narrow down to one test in one file.

## What's covered

Everything lives in `tests/Feature` - there are no `tests/Unit` split like some of my other packages, since pretty much everything here is worth checking against a real (in-memory) database instead of in total isolation:

| Test | Covers |
|---|---|
| `HasherServiceTest` | Content hashing, chain-hash math |
| `MerkleTreeServiceTest` | Building trees, proofs, tamper detection |
| `Ed25519SignerTest` | Sign/verify round trips and the ways they should fail |
| `RevisionServiceTest` | Chain-hash math, custodial vs. non-custodial signing |
| `DocumentServiceTest` | Creating/chaining documents and revisions, `DocumentUploaded` firing |
| `AnchorBatchServiceTest` | Submitting batches, storing proofs, confirming, failing |
| `DocumentIntegrityVerifierTest` | All four checks, both on their own and together |
| `VerificationResultTest` | How failures get collected and reported |
| `OpenTimestampsAnchorProviderTest` | The actual submit/check protocol, faked over HTTP |
| `SubmitAnchorBatchJobTest`, `CheckAnchorBatchThresholdJobTest`, `CheckAnchorConfirmationJobTest` | The three scheduled jobs, including the minimum-batch-size wait and the give-up-and-fail timeout |
| `VerifyDocumentCommandTest` | The full `blocksmith:verify-document` command, including it self-healing a stale local status |
| `AddOrganizationScopingCommandTest` | The scoping command, config-driven table list |
| `BlocksmithLogTest` | The logging event actually writing a row via `LogListener` |
| `DocumentRevisionLinkTest`, `ServiceProviderTest` | The document/revision pivot link, config merging |

A few tests also just prove that when something goes wrong inside a job, it gets logged *and* still throws - it doesn't swallow the error just because it logged it.

## CI

`.github/workflows/tests.yml` runs `composer install` + `vendor/bin/phpunit` on every push and pull request, against PHP 8.2 and 8.3 - same shape as Hookamatic/Cacheeze's workflow, with one addition: the `sodium` extension is explicitly enabled in the runner, since without it every signing test would fail in CI the same way it fails locally if you forget to turn it on.

No PHPStan job yet, unlike Hookamatic. Running `vendor/bin/phpstan analyse` right now surfaces about 107 pre-existing "access to an undefined property" findings across the models - Larastan not picking up the real columns without `@property` docblocks, not actual bugs. Wiring up a PHPStan job before that's cleaned up would just mean CI stays red forever for reasons nobody's actually going to act on.
