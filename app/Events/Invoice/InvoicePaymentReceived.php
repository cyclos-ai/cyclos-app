<?php

namespace App\Events\Invoice;

use App\Models\Tenant\DrayageInvoice;
use App\Models\Tenant\OceanInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly OceanInvoice|DrayageInvoice $invoice,
        public readonly array $paymentData,
    ) {}
}
