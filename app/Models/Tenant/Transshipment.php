<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transshipment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'discharge_date' => 'datetime',
        'load_date' => 'datetime',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function fromVessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'from_vessel_id');
    }

    public function toVessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'to_vessel_id');
    }
}
