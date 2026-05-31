<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrayageCarrier extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'equipment_types' => 'array',
        'service_areas'   => 'array',
        'metadata'        => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CarrierInvite::class);
    }

    public function importDrayages(): HasMany
    {
        return $this->hasMany(ImportDrayage::class, 'drayage_provider_scac', 'scac');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForOrganization($query, string $orgId)
    {
        return $query->where('organization_id', $orgId);
    }
}
