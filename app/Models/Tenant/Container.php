<?php

namespace App\Models\Tenant;

use App\Domain\Container\Enums\ContainerSize;
use App\Domain\Container\Enums\ContainerStatus;
use App\Domain\Container\Enums\ContainerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Container extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => ContainerStatus::class,
        'size' => ContainerSize::class,
        'type' => ContainerType::class,
        'weight' => 'decimal:2',
        'eta' => 'datetime',
        'ata' => 'datetime',
        'etd' => 'datetime',
        'atd' => 'datetime',
        'empty_return_date' => 'datetime',
        'outgate_date' => 'datetime',
        'last_free_day_demurrage' => 'datetime',
        'last_free_day_detention' => 'datetime',
        'is_priority' => 'boolean',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mbl(): BelongsTo
    {
        return $this->belongsTo(MBL::class, 'mbl_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContainerEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function trackingRequests(): HasMany
    {
        return $this->hasMany(TrackingRequest::class);
    }

    public function demurrageCharges(): HasMany
    {
        return $this->hasMany(DemurrageCharge::class);
    }

    public function detentionCharges(): HasMany
    {
        return $this->hasMany(DetentionCharge::class);
    }

    public function transshipments(): HasMany
    {
        return $this->hasMany(Transshipment::class);
    }

    public function railMilestones(): HasMany
    {
        return $this->hasMany(RailMilestone::class);
    }

    public function customsMilestones(): HasMany
    {
        return $this->hasMany(CustomsMilestone::class);
    }

    public function customerFields(): HasMany
    {
        return $this->hasMany(ContainerCustomerField::class);
    }

    public function distributionCenters(): BelongsToMany
    {
        return $this->belongsToMany(DistributionCenter::class, 'container_distribution_center');
    }

    public function importDrayage(): HasOne
    {
        return $this->hasOne(ImportDrayage::class);
    }

    public function oceanInvoiceItems(): HasMany
    {
        return $this->hasMany(OceanInvoiceItem::class);
    }

    public function drayageInvoices(): HasMany
    {
        return $this->hasMany(DrayageInvoice::class);
    }

    public function transitTimes(): HasMany
    {
        return $this->hasMany(TransitTime::class);
    }

    public function customColumnValues(): HasMany
    {
        return $this->hasMany(CustomColumnValue::class, 'entity_id')->where('entity_type', 'container');
    }
}
