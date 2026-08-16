# Signing

Signing proves a specific person actually authored or approved a revision, separate from whoever happens to control the database. Each `SigningKey` belongs to one user, and uses Ed25519. (By default)

## `SignerInterface`

```php
interface SignerInterface
{
    public function sign(string $data, string $privateKey): string;
    public function verify(string $data, string $signature, string $publicKey): bool;
}
```

Both methods just take plain key strings, not a `SigningKey` model - that keeps the signer itself from caring who's holding the key. (It doesn't care about you, only about its job) `Ed25519Signer` is the built-in implementation, using PHP's `sodium` functions. It's bound through the config file's `blocksmith.signer`.

## Custodial vs. non-custodial

Each `SigningKey` has a `custodial` flag, per-row, not a global setting - so custodial and non-custodial keys can live side by side on the same app:

- **Custodial** - Blocksmith hold the private key, and `RevisionService::create()` signs automatically when you pass in a custodial key.
- **Non-custodial** - Blocksmith never touch the private key at all. The user signs on their own device, and we only ever call `verify()`, via `RevisionService::attachSignature()`.

Verifying an old signature works the same either way, since that only ever needs the public key - you can switch which mode you use going forward without breaking anything already signed.

```php
use VanDmade\Blocksmith\Services\RevisionService;
use VanDmade\Blocksmith\Models\SigningKey;

// Custodial - we sign it for them
$signingKey = SigningKey::create([
    'public_key' => $publicKeyHex,
    'private_key' => $privateKeyHex,
    'custodial' => true,
]);
$revision = app(RevisionService::class)->create($previousHash, $contentHash, $revisionNumber, $signingKey);

// Non-custodial - they sign it, we just attach and verify
$signingKey = SigningKey::create(['public_key' => $publicKeyHex, 'custodial' => false]);
$revision = app(RevisionService::class)->create($previousHash, $contentHash, $revisionNumber, $signingKey);
app(RevisionService::class)->attachSignature($revision, $clientSuppliedSignature);
// false if there's no signature or no signing key linked
app(RevisionService::class)->verifySignature($revision); 
```

The private key for a custodial `SigningKey` is encrypted at rest (a Laravel `encrypted` cast), so it's not sitting in the database in plain text.

## See also

- [Documents & Revisions](05-documents-and-revisions.md)
- [Verification](06-verification.md)
