<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerCustomerField extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function trackingRequest(): BelongsTo
    {
        return $this->belongsTo(TrackingRequest::class);
    }
}
