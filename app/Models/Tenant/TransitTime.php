<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransitTime extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'transit_days' => 'integer',
        'port_days' => 'integer',
        'drayage_days' => 'integer',
        'total_days' => 'integer',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
