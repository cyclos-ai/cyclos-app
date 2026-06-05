<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledDrop extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'vessel_eta'          => 'datetime',
        'estimated_drop_date' => 'date',
        'dem_lfd'             => 'date',
        'sent_at'             => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function drayageCarrier(): BelongsTo
    {
        return $this->belongsTo(DrayageCarrier::class);
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
