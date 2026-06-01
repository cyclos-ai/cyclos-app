<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RailShipment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'departed_at'  => 'datetime',
        'eta'          => 'datetime',
        'arrived_at'   => 'datetime',
        'available_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'metadata'     => 'array',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function originRamp(): BelongsTo
    {
        return $this->belongsTo(RailRamp::class, 'origin_ramp_id');
    }

    public function destinationRamp(): BelongsTo
    {
        return $this->belongsTo(RailRamp::class, 'destination_ramp_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(RailMilestone::class, 'container_id', 'container_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeAtRamp(Builder $query): Builder
    {
        return $query->where('status', 'arrived');
    }

    public function scopePendingPickup(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }
}
