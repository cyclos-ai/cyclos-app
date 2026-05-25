<?php

namespace App\Listeners\Webhook;

use App\Events\Container\ContainerStatusChanged;
use App\Jobs\Webhook\DispatchWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchContainerWebhook implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function handle(ContainerStatusChanged $event): void
    {
        $payload = [
            'event'      => 'container.status.changed',
            'occurred_at'=> now()->toIso8601String(),
            'data'       => [
                'container_id'      => $event->container->id,
                'container_number'  => $event->container->container_number,
                'previous_status'   => $event->previousStatus->value,
                'new_status'        => $event->newStatus->value,
                'organization_id'   => $event->container->organization_id,
            ],
        ];

        DispatchWebhookJob::dispatch('container.status.changed', $payload);
    }
}
