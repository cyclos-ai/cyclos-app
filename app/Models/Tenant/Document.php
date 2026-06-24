<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'size'     => 'integer',
    ];

    public function mbl(): BelongsTo
    {
        return $this->belongsTo(MBL::class, 'mbl_id');
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }
}
