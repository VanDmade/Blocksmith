<?php

namespace VanDmade\Blocksmith\Models\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use VanDmade\Blocksmith\Concerns\HasOrganization;

class Type extends Model
{

    use HasOrganization, SoftDeletes;

    protected $table = 'blocksmith_document_types';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function($type) {
            $type->created_by = Auth::check() ? Auth::id() : null;
        });
        static::deleting(function($type) {
            $type->deleted_by = Auth::check() ? Auth::id() : null;
            $type->save();
        });
    }

    /**
     * @return HasMany<Document>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'blocksmith_document_type_id');
    }

}
