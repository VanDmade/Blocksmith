# Blocksmith

![Blocksmith](images/banner.png)

Blocksmith keeps a history of your document (and really any resource you want to plug in...) Every revision gets hash chained to the one before it, batches of revisions get anchored to Bitcoin through OpenTimestamps (By default), and you can sign revisions per-user if you want that too. The core doesn't really know what a "document" is - document just so happens to be the first thing I built on top of it. Any resource could use a simple pivot table and get linked into the chain!

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (`illuminate/support` `^11.3|^12.0|^13.0`)
- The `sodium` PHP extension. It ships with PHP but isn't always turned on - run `php -m | grep sodium` to check.

## Installation

```bash
composer require vandmade/blocksmith
```

The service provider finds/discovers itself, so no need to do anything yourself. If you want to tweak the functionality there is a lovely config file, publish it with the command below:

```bash
php artisan vendor:publish --tag=blocksmith-config
```

Then run the migrations:

```bash
php artisan migrate
```

## Quick start

Create a document with its first revision:

```php
use VanDmade\Blocksmith\Services\DocumentService;

$content = file_get_contents($path);

$document = app(DocumentService::class)->create(
    ['name' => 'Embarassing High School Photo'],
    hash('sha256', $content),
    [
        'disk' => 'local',
        'path' => 'documents/high-school-photo.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'size' => strlen($content),
    ]
);
```

As you make changes you can revise the document and the new revision chains onto the current one automatically:

```php
$document = app(DocumentService::class)->revise($document, hash('sha256', $newContent), [
    'disk' => 'local',
    'path' => 'documents/ai-generated-high-school-photo-with-abs.pdf',
    'extension' => 'png',
    'mime_type' => 'image/png',
    'size' => strlen($newContent),
]);
```

Revisions get batched up and anchored to Bitcoin on their own schedule - see [Merkle Batching & Anchoring](docs/02-merkle-batching.md) for how that works. Once a revision is anchored, you can check its whole chain of custody from the terminal:

```bash
php artisan blocksmith:verify-document {uuid} --full
```

or in code:

```php
use VanDmade\Blocksmith\Verification\DocumentIntegrityVerifier;

$result = app(DocumentIntegrityVerifier::class)->verify($document->currentRevision);

$result->passed(); // bool
$result->getErrors(); // string[] - empty if it passed
```

## What it does

- Chains every revision's hash to the one before it, so tampering with anything in the middle means recomputing every hash after it too. It will grab any revision so it's not just one specific document that is grouped together
- Batches pending revisions into a Merkle tree and only anchors the root, so one Bitcoin transaction covers a whole batch.
- Anchors to Bitcoin through OpenTimestamps, with a job polling for confirmation on its own
- Lets you sign revisions per-user with Ed25519, either Blocksmith holds the key or the key is managed on the user's device/wherever else you might want to store it outside
- Actually re-hashes the real file during verification instead of just comparing stored hashes to each other, if you want that (it's a config toggle)
- Writes a proof for each revision once and never touches it again, so if something gets tampered with you can point to exactly which revision, not just "something in this batch is wrong"

## Docs

| Doc | What's in it |
|---|---|
| [Hash Chaining](docs/01-hash-chaining.md) | How the chain works, what it does and does not catch |
| [Merkle Batching & Anchoring](docs/02-merkle-batching.md) | How batches get built and submitted, the batching config |
| [External Anchoring (OpenTimestamps)](docs/03-external-anchoring.md) | The submit/check flow, how confirmation works |
| [Signing](docs/04-signing.md) | Custodial vs. non-custodial keys |
| [Documents & Revisions](docs/05-documents-and-revisions.md) | The document/revision setup, and how to hook up your own resource type |
| [Verification](docs/06-verification.md) | What actually gets checked, and why the expensive check isn't part of every verify call (Expensive load times) |
| [Events](docs/07-events.md) | The events Blocksmith fires, and the logging pattern used everywhere |
| [Artisan Commands](docs/08-artisan-commands.md) | `blocksmith:verify-document`, `blocksmith:add-organization-scoping` |
| [Configuration](docs/09-configuration.md) | Every config key, and what it's for |
| [Security](docs/10-security.md) | Locking down the proofs table, what's protected and what isn't |

## Testing

```bash
composer test
```

More detail in [TESTING.md](TESTING.md).

## License

Apache License 2.0 - see [LICENSE](LICENSE).
