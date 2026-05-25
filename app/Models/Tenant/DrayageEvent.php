<?php

namespace App\Models\Tenant;

use App\Domain\Drayage\Enums\DrayageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrayageEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'drayage_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'event_date' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'event_type' => DrayageStatus::class,
    ];

    public function importDrayage(): BelongsTo
    {
        return $this->belongsTo(ImportDrayage::class);
    }
}
