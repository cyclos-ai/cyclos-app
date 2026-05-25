<?php

namespace App\Listeners\N8n;

use App\Services\N8n\N8nWorkflowManager;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchN8nEvent implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function __construct(
        private readonly N8nWorkflowManager $manager,
    ) {}

    /**
     * Handle ContainerStatusChanged — forwards payload to n8n.
     */
    public function handleContainerStatusChanged($event): void
    {
        $this->manager->triggerEvent('container.status.changed', [
            'event'       => 'container.status.changed',
            'occurred_at' => now()->toIso8601String(),
            'data'        => [
                'container_id'     => $event->container->id,
                'container_number' => $event->container->container_number,
                'previous_status'  => $event->previousStatus->value,
                'new_status'       => $event->newStatus->value,
            ],
        ]);
    }

    /**
     * Handle VesselETAUpdated — forwards payload to n8n.
     */
    public function handleVesselETAUpdated($event): void
    {
        $this->manager->triggerEvent('vessel.eta.updated', [
            'event'       => 'vessel.eta.updated',
            'occurred_at' => now()->toIso8601String(),
            'data'        => [
                'vessel_id'   => $event->vessel->id ?? null,
                'vessel_name' => $event->vessel->name ?? null,
                'new_eta'     => $event->newEta ?? null,
                'old_eta'     => $event->oldEta ?? null,
            ],
        ]);
    }

    /**
     * Handle InvoiceCreated — forwards payload to n8n.
     */
    public function handleInvoiceCreated($event): void
    {
        $this->manager->triggerEvent('invoice.created', [
            'event'       => 'invoice.created',
            'occurred_at' => now()->toIso8601String(),
            'data'        => [
                'invoice_id'     => $event->invoice->id ?? null,
                'invoice_number' => $event->invoice->invoice_number ?? null,
                'total_amount'   => $event->invoice->total_amount ?? null,
            ],
        ]);
    }
}
