<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirShipment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'weight' => 'decimal:2',
        'pieces' => 'integer',
        'etd' => 'datetime',
        'eta' => 'datetime',
        'atd' => 'datetime',
        'ata' => 'datetime',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function trackingRequests(): HasMany
    {
        return $this->hasMany(TrackingRequest::class);
    }
}
