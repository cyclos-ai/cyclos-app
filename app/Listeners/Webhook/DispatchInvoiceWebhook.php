<?php

namespace App\Listeners\Webhook;

use App\Events\Invoice\InvoiceCreated;
use App\Jobs\Webhook\DispatchWebhookJob;
use App\Models\Tenant\OceanInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchInvoiceWebhook implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;
        $type    = $invoice instanceof OceanInvoice ? 'ocean' : 'drayage';

        $payload = [
            'event'      => 'invoice.created',
            'occurred_at'=> now()->toIso8601String(),
            'data'       => [
                'invoice_id'      => $invoice->id,
                'invoice_number'  => $invoice->invoice_number,
                'invoice_type'    => $type,
                'total_amount'    => $invoice->total_amount,
                'status'          => $invoice->status->value,
                'organization_id' => $invoice->organization_id,
            ],
        ];

        DispatchWebhookJob::dispatch('invoice.created', $payload);
    }
}
