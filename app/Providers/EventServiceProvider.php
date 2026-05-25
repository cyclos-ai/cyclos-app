<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\Container\ContainerStatusChanged::class => [
            \App\Listeners\Container\UpdateContainerTimeline::class,
            \App\Listeners\Container\CheckDemurrageAlarm::class,
            \App\Listeners\Container\CheckDetentionAlarm::class,
            \App\Listeners\Webhook\DispatchContainerWebhook::class,
            [\App\Listeners\N8n\DispatchN8nEvent::class, 'handleContainerStatusChanged'],
        ],

        \App\Events\Container\ContainerCreated::class => [
            \App\Listeners\Tracking\InitiateTracking::class,
        ],

        \App\Events\Vessel\VesselETAUpdated::class => [
            \App\Listeners\Tracking\RecalculateContainerETAs::class,
            \App\Listeners\Webhook\DispatchVesselWebhook::class,
            [\App\Listeners\N8n\DispatchN8nEvent::class, 'handleVesselETAUpdated'],
        ],

        \App\Events\Tracking\TrackingRequestCreated::class => [
            \App\Listeners\Tracking\PollCarrierForContainer::class,
        ],

        \App\Events\Invoice\InvoiceCreated::class => [
            \App\Listeners\Invoice\CalculateInvoiceTotals::class,
            \App\Listeners\Webhook\DispatchInvoiceWebhook::class,
            [\App\Listeners\N8n\DispatchN8nEvent::class, 'handleInvoiceCreated'],
        ],

        \App\Events\Invoice\InvoicePaymentReceived::class => [
            \App\Listeners\Invoice\UpdateInvoiceStatus::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
