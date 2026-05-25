<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetentionCharge extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'last_free_day' => 'datetime',
        'free_days' => 'integer',
        'days_accrued' => 'integer',
        'daily_rate' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'alarm_active' => 'boolean',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
