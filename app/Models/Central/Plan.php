<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $guarded = [];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'max_containers' => 'integer',
        'max_users' => 'integer',
        'max_tracking_requests' => 'integer',
        'included_ai_tokens' => 'integer',
        'included_api_calls' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function isFree(): bool
    {
        return (float) $this->price_monthly === 0.0 && (float) $this->price_yearly === 0.0;
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
