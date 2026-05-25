<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'sso_enabled' => 'boolean',
        'sso_config' => 'array',
    ];

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function mbls(): HasMany
    {
        return $this->hasMany(MBL::class);
    }

    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function trackingRequests(): HasMany
    {
        return $this->hasMany(TrackingRequest::class);
    }

    public function airShipments(): HasMany
    {
        return $this->hasMany(AirShipment::class);
    }

    public function demurrageCharges(): HasMany
    {
        return $this->hasMany(DemurrageCharge::class);
    }

    public function detentionCharges(): HasMany
    {
        return $this->hasMany(DetentionCharge::class);
    }

    public function carrierContracts(): HasMany
    {
        return $this->hasMany(CarrierContract::class);
    }

    public function oceanInvoices(): HasMany
    {
        return $this->hasMany(OceanInvoice::class);
    }

    public function drayageInvoices(): HasMany
    {
        return $this->hasMany(DrayageInvoice::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function factories(): HasMany
    {
        return $this->hasMany(Factory::class);
    }

    public function distributionCenters(): HasMany
    {
        return $this->hasMany(DistributionCenter::class);
    }

    public function customColumns(): HasMany
    {
        return $this->hasMany(CustomColumn::class);
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(SKU::class);
    }

    public function trailerShipments(): HasMany
    {
        return $this->hasMany(TrailerShipment::class);
    }

    public function transitTimes(): HasMany
    {
        return $this->hasMany(TransitTime::class);
    }
}
