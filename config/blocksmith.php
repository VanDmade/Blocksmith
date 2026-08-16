<?php

return [
    // Signer interface implementation to use for signing documents
    'signer' => \VanDmade\Blocksmith\Signing\Ed25519Signer::class,
    // Anchor provider interface implementation to use for anchoring documents
    'anchor_provider' => \VanDmade\Blocksmith\Anchoring\OpenTimestampsAnchorProvider::class,
    // Hash algorithm used for content hashing and the Merkle tree
    'hash_algorithm' => 'sha256',
    // The chunk size to use when hashing and uploading files.
    'chunk_size' => 8192,
    // Where to upload the documents too
    'disk' => 'local',
    // File extensions allowed for document uploads (e.g. ['pdf', 'png', 'jpg']).
    // Empty array means no restriction - any file type is accepted.
    'allowed_file_types' => [],
    // How long (minutes) a document's temporary download link stays valid for.
    'download_url_expiry_minutes' => 5,
    // Whether to verify the content of the document when verifying a revision
    'verify_content' => true,
    // This is the model that will be the "Organization" and used for organization scoping
    'organization_model' => null,
    // Tables to add organization scoping to and the column to use
    'tables' => [
        'blocksmith_documents' => 'organization_id',
        'blocksmith_document_types' => 'organization_id',
        'blocksmith_logs' => 'organization_id',
    ],
    'anchoring' => [
        // Cron schedule for submitting pending revisions for anchoring
        'batch_schedule' => '0 0 * * *',
        // Submit a batch early if this many revisions are pending
        'batch_size_threshold' => 500,
        // How often (minutes) to check the pending count against batch_size_threshold
        'threshold_check_interval' => 10,
        // How often (minutes) to poll submitted batches for confirmation
        'confirmation_check_interval' => 15,
        // How long (minutes) to wait for confirmation before marking a batch as failed
        'max_time_until_give_up' => 48 * 60,
        // Don't submit a batch until at least this many revisions are pending
        'minimum_revisions_per_batch' => 2,
        // ...unless the oldest pending revision has already waited this long (minutes)
        'minimum_revisions_max_wait_minutes' => 60,
        // How often to verify the confirmed anchor against the blockchain (days)
        'verify_anchor_interval' => 14,
        // How many batches to verify per job
        'max_batches_to_verify_per_job' => 50,
        // Whether to retry failed anchor verification attempts (That were previously confirmed)
        'retry_failed_anchor_verification' => false,
    ],
];
