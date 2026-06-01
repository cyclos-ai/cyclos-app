<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RailRamp extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function originShipments(): HasMany
    {
        return $this->hasMany(RailShipment::class, 'origin_ramp_id');
    }

    public function destinationShipments(): HasMany
    {
        return $this->hasMany(RailShipment::class, 'destination_ramp_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCarrier(Builder $query, string $scac): Builder
    {
        return $query->where('carrier_scac', strtoupper($scac));
    }

    public function scopeByState(Builder $query, string $state): Builder
    {
        return $query->where('state', strtoupper($state));
    }
}
