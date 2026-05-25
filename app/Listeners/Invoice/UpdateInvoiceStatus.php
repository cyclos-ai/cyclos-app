<?php

namespace App\Listeners\Invoice;

use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Events\Invoice\InvoicePaymentReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateInvoiceStatus implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(InvoicePaymentReceived $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->status !== InvoiceStatus::PAID) {
            $invoice->update(['status' => InvoiceStatus::PAID]);
        }
    }
}
