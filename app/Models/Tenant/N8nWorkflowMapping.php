<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class N8nWorkflowMapping extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_active'        => 'boolean',
        'metadata'         => 'array',
        'last_executed_at' => 'datetime',
        'execution_count'  => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(N8nIntegration::class, 'n8n_integration_id');
    }

    public function incrementExecution(): void
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }
}
