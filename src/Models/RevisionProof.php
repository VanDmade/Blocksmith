<?php

namespace VanDmade\Blocksmith\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevisionProof extends Model
{

    const UPDATED_AT = null;

    protected $table = 'blocksmith_revision_proofs';

    protected $fillable = [
        'blocksmith_revision_id',
        'proof',
    ];

    protected $casts = [
        'proof' => 'array',
    ];

    /**
     * @return BelongsTo<Revision>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'blocksmith_revision_id');
    }

}
