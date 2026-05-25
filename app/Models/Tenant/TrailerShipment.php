<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrailerShipment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'pickup_date' => 'datetime',
        'delivery_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
