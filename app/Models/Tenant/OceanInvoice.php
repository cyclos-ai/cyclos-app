<?php

namespace App\Models\Tenant;

use App\Domain\Invoice\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OceanInvoice extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'invoice_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
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

    public function items(): HasMany
    {
        return $this->hasMany(OceanInvoiceItem::class);
    }
}
