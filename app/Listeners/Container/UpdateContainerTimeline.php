<?php

namespace App\Listeners\Container;

use App\Domain\Container\Enums\ContainerEventType;
use App\Events\Container\ContainerStatusChanged;
use App\Models\Tenant\ContainerEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateContainerTimeline implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(ContainerStatusChanged $event): void
    {
        ContainerEvent::create([
            'container_id' => $event->container->id,
            'event_type'   => ContainerEventType::STATUS_CHANGE,
            'description'  => sprintf(
                'Status changed: %s → %s',
                $event->previousStatus->label(),
                $event->newStatus->label()
            ),
            'event_date'   => now(),
            'location'     => $event->eventData['location'] ?? null,
            'raw_data'     => $event->eventData,
            'created_at'   => now(),
        ]);
    }
}
