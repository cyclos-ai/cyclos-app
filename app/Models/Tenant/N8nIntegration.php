<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class N8nIntegration extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'api_key'              => 'encrypted',
        'is_active'            => 'boolean',
        'is_connected'         => 'boolean',
        'settings'             => 'array',
        'last_health_check_at' => 'datetime',
    ];

    protected $hidden = ['api_key'];

    public function workflowMappings(): HasMany
    {
        return $this->hasMany(N8nWorkflowMapping::class);
    }

    public function activeWorkflows(): HasMany
    {
        return $this->workflowMappings()->where('is_active', true);
    }
}
