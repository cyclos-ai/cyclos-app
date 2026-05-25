<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vessel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'current_speed' => 'decimal:2',
        'current_heading' => 'decimal:2',
        'last_ais_update' => 'datetime',
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

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function mbls(): HasMany
    {
        return $this->hasMany(MBL::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function transshipmentsFrom(): HasMany
    {
        return $this->hasMany(Transshipment::class, 'from_vessel_id');
    }

    public function transshipmentsTo(): HasMany
    {
        return $this->hasMany(Transshipment::class, 'to_vessel_id');
    }
}
