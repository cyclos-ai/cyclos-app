<?php

namespace App\Events\Container;

use App\Models\Tenant\Container;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContainerUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Container $container,
        public readonly array $changes,
    ) {}
}
