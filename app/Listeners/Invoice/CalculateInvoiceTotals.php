<?php

namespace App\Listeners\Invoice;

use App\Events\Invoice\InvoiceCreated;
use App\Models\Tenant\DrayageInvoice;
use App\Models\Tenant\OceanInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;

class CalculateInvoiceTotals implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;

        $items    = $invoice->items()->get();
        $subtotal = $items->sum('amount');
        $taxRate  = (float) ($invoice->tax_rate ?? 0.0);
        $taxAmount = round($subtotal * $taxRate, 2);
        $total     = $subtotal + $taxAmount;

        $invoice->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'total_amount' => $total,
        ]);
    }
}
