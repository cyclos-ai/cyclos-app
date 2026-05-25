<?php

namespace App\Events\Container;

use App\Domain\Container\Enums\ContainerStatus;
use App\Models\Tenant\Container;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContainerStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Container $container,
        public readonly ContainerStatus $previousStatus,
        public readonly ContainerStatus $newStatus,
        public readonly ?array $eventData = null,
    ) {}
}
