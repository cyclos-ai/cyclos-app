<?php

namespace App\Models\Tenant;

use App\Domain\Invoice\Enums\ChargeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OceanInvoiceItem extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'charge_type' => ChargeType::class,
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function oceanInvoice(): BelongsTo
    {
        return $this->belongsTo(OceanInvoice::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
