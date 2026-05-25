<?php

namespace App\Models\Tenant;

use App\Domain\Container\Enums\ContainerEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerEvent extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'event_type' => ContainerEventType::class,
        'event_date' => 'datetime',
        'raw_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
