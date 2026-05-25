<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CarrierApiCredential extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'api_key'          => 'encrypted',
        'consumer_key'     => 'encrypted',
        'client_id'        => 'encrypted',
        'client_secret'    => 'encrypted',
        'access_token'     => 'encrypted',
        'refresh_token'    => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_used_at'     => 'datetime',
        'is_active'        => 'boolean',
        'metadata'         => 'array',
    ];

    protected $hidden = [
        'api_key',
        'consumer_key',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the effective API URL based on environment.
     */
    public function getEffectiveApiUrl(): ?string
    {
        if ($this->environment === 'sandbox' && $this->sandbox_url) {
            return $this->sandbox_url;
        }

        return $this->api_url;
    }

    /**
     * Check if OAuth token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Scope: only active credentials.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: by carrier SCAC code.
     */
    public function scopeForCarrier($query, string $scac)
    {
        return $query->where('carrier_scac', strtoupper($scac));
    }
}
