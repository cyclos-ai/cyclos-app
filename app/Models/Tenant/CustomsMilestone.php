<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsMilestone extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'event_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function mbl(): BelongsTo
    {
        return $this->belongsTo(MBL::class, 'mbl_id');
    }
}
