# Configuration

## Publishing the config file

```bash
php artisan vendor:publish --tag=blocksmith-config
```

## `config/blocksmith.php` - top level

These descriptions are pulled straight from the comments in the config file itself, so they stay in sync with it:

| Key | Default | What it does |
|---|---|---|
| `signer` | `Ed25519Signer::class` | Signer interface implementation to use for signing documents. |
| `anchor_provider` | `OpenTimestampsAnchorProvider::class` | Anchor provider interface implementation to use for anchoring documents. |
| `hash_algorithm` | `'sha256'` | Hash algorithm used for content hashing and the Merkle tree. |
| `chunk_size` | `8192` | The chunk size to use when hashing and uploading files. |
| `disk` | `'local'` | Where to upload the documents too. |
| `allowed_file_types` | `[]` | File extensions allowed for document uploads (e.g. `['pdf', 'png', 'jpg']`). Empty array means no restriction - any file type is accepted. |
| `verify_content` | `true` | Whether to verify the content of the document when verifying a revision. |
| `organization_model` | `null` | This is the model that will be the "Organization" and used for organization scoping. |
| `tables` | see below | Tables to add organization scoping to and the column to use. |

Defaults for `tables`:

```php
'tables' => [
    'blocksmith_documents' => 'organization_id',
    'blocksmith_document_types' => 'organization_id',
    'blocksmith_logs' => 'organization_id',
],
```

See [Signing](04-signing.md) for `signer`; [External Anchoring](03-external-anchoring.md) for `anchor_provider`; [Hash Chaining](01-hash-chaining.md) for `hash_algorithm`/`chunk_size`; [Verification](06-verification.md) for `verify_content`; [Artisan Commands](08-artisan-commands.md) for `organization_model`/`tables`.

## `anchoring`

| Key | Default | What it does |
|---|---|---|
| `batch_schedule` | `'0 0 * * *'` | Cron schedule for submitting pending revisions for anchoring. |
| `batch_size_threshold` | `500` | Submit a batch early if this many revisions are pending. |
| `threshold_check_interval` | `10` | How often (minutes) to check the pending count against `batch_size_threshold`. |
| `confirmation_check_interval` | `15` | How often (minutes) to poll submitted batches for confirmation. |
| `max_time_until_give_up` | `2880` (48 hours) | How long (minutes) to wait for confirmation before marking a batch as failed. |
| `minimum_revisions_per_batch` | `2` | Don't submit a batch until at least this many revisions are pending. |
| `minimum_revisions_max_wait_minutes` | `60` | ...unless the oldest pending revision has already waited this long (minutes). |
| `verify_anchor_interval` | `14` | How often to verify the confirmed anchor against the blockchain (days). |
| `max_batches_to_verify_per_job` | `50` | How many batches to verify per job. |
| `retry_failed_anchor_verification` | `false` | Whether to retry failed anchor verification attempts (that were previously confirmed). |

See [Merkle Batching & Anchoring](02-merkle-batching.md) for the batching keys, [External Anchoring](03-external-anchoring.md) for the confirmation/give-up keys, and [Merkle Batching & Anchoring](02-merkle-batching.md) again for `verify_anchor_interval`/`max_batches_to_verify_per_job`/`retry_failed_anchor_verification`, which belong to `VerifyAnchorBatchJob`.

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md)
- [Signing](04-signing.md)
- [Verification](06-verification.md)
