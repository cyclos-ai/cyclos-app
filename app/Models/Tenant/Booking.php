<?php

namespace App\Models\Tenant;

use App\Domain\Booking\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => BookingStatus::class,
        'weight' => 'decimal:2',
        'container_count' => 'integer',
        'cut_off_date' => 'datetime',
        'si_cut_off' => 'datetime',
        'vgm_cut_off' => 'datetime',
        'etd' => 'datetime',
        'eta' => 'datetime',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function trackingRequests(): HasMany
    {
        return $this->hasMany(TrackingRequest::class, 'booking_id');
    }
}
