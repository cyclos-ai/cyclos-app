<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MBL extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'mbls';

    protected $guarded = [];

    protected $casts = [
        'etd' => 'datetime',
        'eta' => 'datetime',
        'atd' => 'datetime',
        'ata' => 'datetime',
        'container_count' => 'integer',
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
        return $this->hasMany(Container::class, 'mbl_id');
    }

    public function oceanInvoices(): HasMany
    {
        return $this->hasMany(OceanInvoice::class, 'mbl_id');
    }

    public function customsMilestones(): HasMany
    {
        return $this->hasMany(CustomsMilestone::class, 'mbl_id');
    }

    public function trackingRequests(): HasMany
    {
        return $this->hasMany(TrackingRequest::class, 'mbl_id');
    }
}
