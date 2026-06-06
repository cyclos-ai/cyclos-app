<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuickBooksCredential extends Model
{
    use HasUuids;

    protected $table = 'quickbooks_credentials';

    protected $guarded = [];

    protected $casts = [
        'access_token'             => 'encrypted',
        'refresh_token'            => 'encrypted',
        'token_expires_at'         => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_used_at'             => 'datetime',
        'last_sync_at'             => 'datetime',
        'is_active'                => 'boolean',
        'is_connected'             => 'boolean',
        'settings'                 => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Check if the access token is expired (or expires within 60s).
     */
    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->subSeconds(60)->isPast();
    }

    /**
     * Check if the refresh token is expired.
     */
    public function isRefreshTokenExpired(): bool
    {
        if (! $this->refresh_token_expires_at) {
            return false;
        }

        return $this->refresh_token_expires_at->isPast();
    }

    /**
     * Get the tenant's single QuickBooks credential row.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
