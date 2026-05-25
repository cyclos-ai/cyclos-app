<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RailMilestone extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'event_date' => 'datetime',
        'estimated_arrival' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
